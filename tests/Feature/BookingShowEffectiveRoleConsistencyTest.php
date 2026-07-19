<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: эффективная роль в блоке кнопок действий на странице показа заявки
 * должна выбираться с приоритетом admin > manager > tourist, а не как
 * объединение полей всех ролей пользователя. Пользователь со staff-ролью
 * (admin или manager) и ролью tourist одновременно должен получать только
 * staff-действия, а комбинация admin+manager должна давать полные
 * admin-права редактирования, а не ограничения, свойственные "чистому"
 * менеджеру.
 */
class BookingShowEffectiveRoleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const TOURIST_CANCEL_TEXT = 'Отменить заявку';
    private const GUIDANCE_BEFORE_MANAGER = 'Вы можете отменить заявку до назначения менеджера';
    private const GUIDANCE_MANAGER_ASSIGNED = 'Заявка взята в работу менеджером. Для отмены свяжитесь с менеджером.';

    public function test_tourist_only_new_unassigned_booking_sees_only_tourist_actions(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $html = $this->getShowHtml($owner, $booking);

        $this->assertStringContainsString(self::TOURIST_CANCEL_TEXT, $html);
        $this->assertStringContainsString(self::GUIDANCE_BEFORE_MANAGER, $html);
        $this->assertStringContainsString('action="' . route('bookings.cancel', $booking) . '"', $html);

        $this->assertStringNotContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);
    }

    public function test_manager_only_completed_booking_sees_no_actions(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_COMPLETED);

        $html = $this->getShowHtml($manager, $booking);

        $this->assertStringNotContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);
        $this->assertStringNotContainsString(self::TOURIST_CANCEL_TEXT, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_BEFORE_MANAGER, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_MANAGER_ASSIGNED, $html);
    }

    public function test_admin_only_completed_booking_sees_edit_and_delete(): void
    {
        $admin   = $this->makeUser([Role::ADMIN]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_COMPLETED);

        $html = $this->getShowHtml($admin, $booking);

        $this->assertStringContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);

        $this->assertStringNotContainsString(self::TOURIST_CANCEL_TEXT, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_BEFORE_MANAGER, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_MANAGER_ASSIGNED, $html);
    }

    public function test_admin_tourist_new_booking_sees_staff_actions_not_tourist_actions(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::TOURIST]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $html = $this->getShowHtml($user, $booking);

        $this->assertStringContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);
        $this->assertStringContainsString('action="' . route('bookings.cancel', $booking) . '"', $html);

        $this->assertStringNotContainsString(self::TOURIST_CANCEL_TEXT, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_BEFORE_MANAGER, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_MANAGER_ASSIGNED, $html);
    }

    public function test_manager_tourist_progress_booking_sees_staff_actions_not_tourist_actions(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBooking($user, $user->id, Booking::STATUS_PROGRESS);

        $html = $this->getShowHtml($user, $booking);

        $this->assertStringContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringContainsString('action="' . route('bookings.confirm', $booking) . '"', $html);
        $this->assertStringContainsString('action="' . route('bookings.cancel', $booking) . '"', $html);

        $this->assertStringNotContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);
        $this->assertStringNotContainsString(self::TOURIST_CANCEL_TEXT, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_BEFORE_MANAGER, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_MANAGER_ASSIGNED, $html);
    }

    public function test_admin_manager_completed_booking_sees_edit_and_delete(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::MANAGER]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, $user->id, Booking::STATUS_COMPLETED);

        $html = $this->getShowHtml($user, $booking);

        $this->assertStringContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);

        $this->assertStringNotContainsString(self::TOURIST_CANCEL_TEXT, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_BEFORE_MANAGER, $html);
        $this->assertStringNotContainsString(self::GUIDANCE_MANAGER_ASSIGNED, $html);
    }

    public function test_manager_tourist_owner_not_assigned_progress_booking_sees_only_owner_facing_state(): void
    {
        $ownerNotAssigned = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $assignedManager  = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($ownerNotAssigned, $assignedManager->id, Booking::STATUS_PROGRESS);

        $html = $this->getShowHtml($ownerNotAssigned, $booking);

        $this->assertStringNotContainsString('href="' . route('bookings.edit', $booking) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('bookings.confirm', $booking) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('bookings.complete', $booking) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('bookings.destroy', $booking) . '"', $html);
        $this->assertStringNotContainsString(self::TOURIST_CANCEL_TEXT, $html);

        // Same non-staff guidance a pure tourist owner sees once a manager has taken the booking into work.
        $this->assertStringContainsString(self::GUIDANCE_MANAGER_ASSIGNED, $html);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getShowHtml(User $user, Booking $booking): string
    {
        $response = $this->actingAs($user)->get(route('bookings.show', $booking));
        $response->assertOk();

        return $response->getContent();
    }

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeBooking(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => $status,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => '2026-08-15',
                'nights'              => 7,
                'adults'              => 2,
                'children'            => 0,
            ])
        );
    }
}
