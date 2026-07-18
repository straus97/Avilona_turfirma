<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: эффективная роль в редактировании заявки должна выбираться с
 * приоритетом admin > manager > tourist, а не как объединение полей всех
 * ролей пользователя. Пользователь со staff-ролью (admin или manager) и
 * ролью tourist одновременно должен получать только staff-поля.
 */
class BookingEditEffectiveRoleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // UI tests
    // -----------------------------------------------------------------------

    public function test_admin_tourist_receives_staff_only_edit_fields(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::TOURIST]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner);

        $html = $this->getEditHtml($user, $booking);

        $this->assertStaffOnlyEditFields($html);
    }

    public function test_manager_tourist_receives_staff_only_edit_fields(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBooking($user, $user->id);

        $html = $this->getEditHtml($user, $booking);

        $this->assertStaffOnlyEditFields($html);
    }

    public function test_admin_only_receives_staff_only_edit_fields(): void
    {
        $user    = $this->makeUser([Role::ADMIN]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner);

        $html = $this->getEditHtml($user, $booking);

        $this->assertStaffOnlyEditFields($html);
    }

    public function test_manager_only_receives_staff_only_edit_fields(): void
    {
        $user    = $this->makeUser([Role::MANAGER]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, $user->id);

        $html = $this->getEditHtml($user, $booking);

        $this->assertStaffOnlyEditFields($html);
    }

    // -----------------------------------------------------------------------
    // Update tests
    // -----------------------------------------------------------------------

    public function test_admin_tourist_update_ignores_forged_tourist_fields(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::TOURIST]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_NEW, [
            'notes'         => 'Original notes',
            'tourists_data' => ['adult_1' => 'Original Tourist'],
        ]);

        $this->assertUpdateIgnoresForgedTouristFields($user, $booking);
    }

    public function test_manager_tourist_update_ignores_forged_tourist_fields(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBooking($user, $user->id, Booking::STATUS_NEW, [
            'notes'         => 'Original notes',
            'tourists_data' => ['adult_1' => 'Original Tourist'],
        ]);

        $this->assertUpdateIgnoresForgedTouristFields($user, $booking);
    }

    // -----------------------------------------------------------------------
    // Shared assertions
    // -----------------------------------------------------------------------

    private function assertStaffOnlyEditFields(string $html): void
    {
        $this->assertStringContainsString('name="total_price"', $html);
        $this->assertStringContainsString('name="manager_notes"', $html);

        $this->assertStringNotContainsString('name="notes"', $html);
        $this->assertStringNotContainsString(
            'После начала обработки заявки менеджером, редактирование будет недоступно.',
            $html
        );
    }

    private function assertUpdateIgnoresForgedTouristFields(User $user, Booking $booking): void
    {
        $response = $this->actingAs($user)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_PROGRESS,
                'manager_notes' => 'Updated by staff',
                'total_price'   => 65000,
                'notes'         => 'Forged tourist notes',
                'tourists_data' => ['adult_1' => 'Forged Tourist'],
            ]);

        $response->assertRedirect(route('bookings.show', $booking));

        $booking->refresh();

        $this->assertSame('Updated by staff', $booking->manager_notes);
        $this->assertEquals(65000, $booking->total_price);
        $this->assertSame('Original notes', $booking->notes);
        $this->assertSame(['adult_1' => 'Original Tourist'], $booking->tourists_data);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getEditHtml(User $user, Booking $booking): string
    {
        $response = $this->actingAs($user)->get(route('bookings.edit', $booking));
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
        string $status = Booking::STATUS_NEW,
        array $overrides = []
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create(array_merge([
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
            ], $overrides))
        );
    }
}
