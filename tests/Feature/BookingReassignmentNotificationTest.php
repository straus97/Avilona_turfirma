<?php

namespace Tests\Feature;

use App\Events\ManagerAssigned;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Правило: переназначение ответственного по уже обрабатываемой заявке должно
 * уведомлять клиента и нового ответственного (событие ManagerAssigned), как и
 * первичное назначение — но только при реальной смене ответственного. При этом
 * сбой уведомления после успешной записи manager_id не должен откатывать
 * переназначение или превращать ответ в ошибку.
 */
class BookingReassignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Реальное переназначение уведомляет и несёт новый manager_id
    // -----------------------------------------------------------------------

    public function test_reassigning_to_a_different_manager_dispatches_manager_assigned_once_with_new_manager(): void
    {
        Event::fake([ManagerAssigned::class]);

        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $managerB->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $managerB->id,
        ]);

        Event::assertDispatched(ManagerAssigned::class, 1);
        Event::assertDispatched(
            ManagerAssigned::class,
            fn (ManagerAssigned $event): bool =>
                $event->booking->id === $booking->id
                && (int) $event->booking->manager_id === $managerB->id
        );
    }

    // -----------------------------------------------------------------------
    // 2. Переназначение на того же ответственного — no-op без уведомления
    // -----------------------------------------------------------------------

    public function test_reassigning_to_the_same_manager_dispatches_nothing(): void
    {
        Event::fake([ManagerAssigned::class]);

        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $managerA = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $managerA->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $managerA->id,
        ]);

        Event::assertNotDispatched(ManagerAssigned::class);
    }

    // -----------------------------------------------------------------------
    // 3. Переназначение не меняет статус
    // -----------------------------------------------------------------------

    public function test_reassignment_does_not_change_booking_status(): void
    {
        Event::fake([ManagerAssigned::class]);

        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id, Booking::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $managerB->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $managerB->id,
            'status'     => Booking::STATUS_CONFIRMED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. Первичное назначение по-прежнему уведомляет ровно один раз
    // -----------------------------------------------------------------------

    public function test_initial_assignment_still_dispatches_manager_assigned_once(): void
    {
        Event::fake([ManagerAssigned::class]);

        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $managerA = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $managerA->id])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $managerA->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);

        Event::assertDispatched(ManagerAssigned::class, 1);
    }

    // -----------------------------------------------------------------------
    // 5. Недопустимая цель — ничего не отправляется, текущий ответственный цел
    // -----------------------------------------------------------------------

    public function test_ineligible_target_dispatches_nothing_and_keeps_current_manager(): void
    {
        Event::fake([ManagerAssigned::class]);

        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $managerA = $this->makeUser(Role::MANAGER);
        $tourist  = $this->makeUser(Role::TOURIST);
        $booking  = $this->makeBookingFor($owner, $managerA->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $tourist->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $managerA->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);

        Event::assertNotDispatched(ManagerAssigned::class);
    }

    // -----------------------------------------------------------------------
    // 6. Сбой уведомления после успешной записи не откатывает переназначение
    // -----------------------------------------------------------------------

    public function test_notification_failure_after_reassignment_persists_manager_id_and_returns_success(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id, Booking::STATUS_PROGRESS);

        // Настоящий путь события/слушателя (ManagerAssigned НЕ подменяется).
        // Незачереденный SendManagerAssignedNotification первым делом вызывает
        // Mail::to(...), поэтому детерминированный throw на первом вызове доказывает,
        // что реальный слушатель был достигнут, не завязываясь на число получателей.
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('mail down'));

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $managerB->id])
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasNoErrors();

        // Запись manager_id пережила проглоченный сбой уведомления.
        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $managerB->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();
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
