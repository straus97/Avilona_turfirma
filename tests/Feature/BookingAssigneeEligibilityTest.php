<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Правило эксплуатации: ответственным по заявке может быть только АКТИВНЫЙ пользователь
 * с ролью менеджера ИЛИ администратора (админ может вести заявку лично, в том числе
 * назначить её на себя). Единый источник правды — User::assignableToBookings().
 */
class BookingAssigneeEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Заглушаем слушатели (почта/уведомления), чтобы изолировать логику назначения,
        // включая цепочку ManagerAssigned при назначении на НОВУЮ заявку.
        Event::fake();
    }

    // -----------------------------------------------------------------------
    // 1. Активный менеджер — назначение и переназначение (совместимость)
    // -----------------------------------------------------------------------

    public function test_active_manager_can_be_assigned_to_new_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $manager->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $manager->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_active_manager_can_replace_another_eligible_assignee(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $manager1 = $this->makeUser(Role::MANAGER);
        $manager2 = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $manager1->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $manager2->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $manager2->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // 2. Активный администратор — назначение, самоназначение, переназначение
    // -----------------------------------------------------------------------

    public function test_active_admin_can_be_assigned_to_new_booking(): void
    {
        $owner        = $this->makeUser(Role::TOURIST);
        $actingAdmin  = $this->makeUser(Role::ADMIN);
        $targetAdmin  = $this->makeUser(Role::ADMIN);
        $booking      = $this->makeBookingFor($owner);

        $this->actingAs($actingAdmin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $targetAdmin->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $targetAdmin->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_acting_admin_can_assign_booking_to_themselves(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $admin->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $admin->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_active_admin_can_be_a_reassignment_target(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $manager = $this->makeUser(Role::MANAGER);
        $target  = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $target->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $target->id,
        ]);
    }

    public function test_user_with_both_admin_and_manager_roles_can_be_assigned(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $both    = $this->makeUser(Role::MANAGER);
        $both->assignRole(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $both->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $both->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    // -----------------------------------------------------------------------
    // 3. Неактивные пользователи не могут быть НОВОЙ целью назначения
    // -----------------------------------------------------------------------

    public function test_inactive_manager_cannot_be_assigned(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $inactive = $this->makeUser(Role::MANAGER, active: false);
        $booking  = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $inactive->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => null,
            'status'     => Booking::STATUS_NEW,
        ]);
    }

    public function test_inactive_manager_cannot_replace_current_assignee(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $current  = $this->makeUser(Role::MANAGER);
        $inactive = $this->makeUser(Role::MANAGER, active: false);
        $booking  = $this->makeBookingFor($owner, $current->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $inactive->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $current->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_inactive_admin_cannot_be_assigned(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $inactive = $this->makeUser(Role::ADMIN, active: false);
        $booking  = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $inactive->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => null,
            'status'     => Booking::STATUS_NEW,
        ]);
    }

    public function test_inactive_admin_cannot_replace_current_assignee(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $current  = $this->makeUser(Role::MANAGER);
        $inactive = $this->makeUser(Role::ADMIN, active: false);
        $booking  = $this->makeBookingFor($owner, $current->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $inactive->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $current->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. Пользователь без нужной роли остаётся отклонённым
    // -----------------------------------------------------------------------

    public function test_tourist_cannot_be_assigned(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $tourist = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $tourist->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => null,
            'status'     => Booking::STATUS_NEW,
        ]);
    }

    // -----------------------------------------------------------------------
    // 5. Консистентность UI: выпадающий список формы назначения (глазами админа)
    // -----------------------------------------------------------------------

    public function test_assignment_dropdown_offers_active_managers_and_admins_including_self(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $admin          = $this->makeUser(Role::ADMIN);
        $activeManager  = $this->makeUser(Role::MANAGER);
        $activeAdmin    = $this->makeUser(Role::ADMIN);
        $booking        = $this->makeBookingFor($owner);

        $response = $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk();

        $ids = $response->viewData('assignableEmployees')->pluck('id')->all();

        $this->assertContains($activeManager->id, $ids);
        $this->assertContains($activeAdmin->id, $ids);
        $this->assertContains($admin->id, $ids, 'Действующий админ должен быть доступен для самоназначения.');

        $response->assertSee($activeManager->name);
        $response->assertSee($activeAdmin->name);
    }

    public function test_assignment_dropdown_excludes_inactive_managers_and_admins(): void
    {
        $owner            = $this->makeUser(Role::TOURIST);
        $admin            = $this->makeUser(Role::ADMIN);
        $inactiveManager  = $this->makeUser(Role::MANAGER, active: false);
        $inactiveAdmin    = $this->makeUser(Role::ADMIN, active: false);
        $booking          = $this->makeBookingFor($owner);

        $response = $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk();

        $ids = $response->viewData('assignableEmployees')->pluck('id')->all();

        $this->assertNotContains($inactiveManager->id, $ids);
        $this->assertNotContains($inactiveAdmin->id, $ids);
    }

    // -----------------------------------------------------------------------
    // 6. Историческое назначение: неактивный текущий ответственный
    // -----------------------------------------------------------------------

    public function test_viewing_booking_with_now_inactive_assignee_preserves_and_shows_but_excludes_from_targets(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $assignee = $this->makeUser(Role::MANAGER); // назначен, когда был активен
        $booking  = $this->makeBookingFor($owner, $assignee->id, Booking::STATUS_PROGRESS);

        // Пользователь впоследствии деактивирован.
        $assignee->update(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk();

        // 1. Просмотр не мутирует manager_id.
        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $assignee->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);

        // 2. Текущий ответственный по-прежнему виден на странице.
        $response->assertSee($assignee->name);

        // 3. Но он не является допустимой НОВОЙ целью назначения.
        $ids = $response->viewData('assignableEmployees')->pluck('id')->all();
        $this->assertNotContains($assignee->id, $ids);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $roleName, bool $active = true): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create(['is_active' => $active]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeBookingFor(
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
