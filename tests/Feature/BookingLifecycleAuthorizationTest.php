<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingLifecycleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Suppress all event listeners (mail, notifications) to isolate authorization logic.
        Event::fake();
    }

    // -----------------------------------------------------------------------
    // 1. Unauthenticated users cannot access lifecycle mutation routes
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_view_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->get(route('bookings.show', $booking))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_cannot_open_edit_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->get(route('bookings.edit', $booking))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_cannot_update_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->put(route('bookings.update', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_unauthenticated_cannot_assign_manager(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner);

        $this->post(route('bookings.assign-manager', $booking), ['manager_id' => $manager->id])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => null,
            'status'     => Booking::STATUS_NEW,
        ]);
    }

    public function test_unauthenticated_cannot_destroy_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->delete(route('bookings.destroy', $booking))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
    }

    public function test_unauthenticated_cannot_cancel_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_unauthenticated_cannot_confirm_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->post(route('bookings.confirm', $booking))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_unauthenticated_cannot_complete_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->post(route('bookings.complete', $booking))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    // -----------------------------------------------------------------------
    // 2. Owner tourist
    // -----------------------------------------------------------------------

    public function test_tourist_can_view_own_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_tourist_cannot_view_foreign_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($other);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    public function test_tourist_cannot_open_edit_route(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($owner)
            ->get(route('bookings.edit', $booking))
            ->assertForbidden();
    }

    public function test_tourist_cannot_submit_generic_update(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($owner)
            ->put(route('bookings.update', $booking), ['status' => Booking::STATUS_NEW])
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_tourist_cannot_forge_status_via_update(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($owner)
            ->put(route('bookings.update', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_tourist_can_cancel_own_new_unassigned_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_tourist_cannot_cancel_after_manager_assigned(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_tourist_cannot_confirm_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)
            ->post(route('bookings.confirm', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_tourist_cannot_complete_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)
            ->post(route('bookings.complete', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    // -----------------------------------------------------------------------
    // 3. Assigned manager
    // -----------------------------------------------------------------------

    public function test_assigned_manager_can_view_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_assigned_manager_can_open_edit(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)
            ->get(route('bookings.edit', $booking))
            ->assertOk();
    }

    public function test_assigned_manager_can_update_permitted_fields(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_PROGRESS,
                'manager_notes' => 'В работе',
                'total_price'   => 75000,
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'            => $booking->id,
            'status'        => Booking::STATUS_PROGRESS,
            'manager_notes' => 'В работе',
            'total_price'   => 75000,
        ]);
    }

    public function test_assigned_manager_can_cancel_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_assigned_manager_can_confirm_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_assigned_manager_can_complete_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $this->actingAs($manager)
            ->post(route('bookings.complete', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. Foreign manager
    // -----------------------------------------------------------------------

    public function test_foreign_manager_cannot_view_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    public function test_foreign_manager_cannot_edit_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)
            ->get(route('bookings.edit', $booking))
            ->assertForbidden();
    }

    public function test_foreign_manager_cannot_update_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_PROGRESS,
                'manager_notes' => 'Взломано',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'            => $booking->id,
            'status'        => Booking::STATUS_NEW,
            'manager_notes' => null,
        ]);
    }

    public function test_foreign_manager_cannot_cancel_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)
            ->post(route('bookings.cancel', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_foreign_manager_cannot_confirm_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($foreignManager)
            ->post(route('bookings.confirm', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_foreign_manager_cannot_complete_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id, Booking::STATUS_CONFIRMED);

        $this->actingAs($foreignManager)
            ->post(route('bookings.complete', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 5. Admin
    // -----------------------------------------------------------------------

    public function test_admin_can_view_any_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_admin_can_update_any_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_NEW,
                'manager_notes' => 'Admin notes',
                'total_price'   => 50000,
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'            => $booking->id,
            'status'        => Booking::STATUS_NEW,
            'manager_notes' => 'Admin notes',
            'total_price'   => 50000,
        ]);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_admin_can_confirm_any_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, null, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_admin_can_complete_any_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, null, Booking::STATUS_CONFIRMED);

        $this->actingAs($admin)
            ->post(route('bookings.complete', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    public function test_admin_can_assign_real_manager(): void
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
        ]);
    }

    public function test_admin_can_reassign_manager(): void
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
    // 5b. Destroy — role-based access
    // -----------------------------------------------------------------------

    public function test_tourist_cannot_destroy_own_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($owner)
            ->delete(route('bookings.destroy', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
    }

    public function test_assigned_manager_cannot_destroy_assigned_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)
            ->delete(route('bookings.destroy', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
    }

    public function test_foreign_manager_cannot_destroy_booking(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)
            ->delete(route('bookings.destroy', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
    }

    public function test_admin_can_destroy_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        $this->actingAs($admin)
            ->delete(route('bookings.destroy', $booking))
            ->assertRedirect(route('cabinet.admin.bookings'));

        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }

    // -----------------------------------------------------------------------
    // 6. Assignment integrity
    // -----------------------------------------------------------------------

    public function test_admin_cannot_assign_tourist_as_manager(): void
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

    public function test_failed_assignment_leaves_manager_id_and_status_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $tourist = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)
            ->post(route('bookings.assign-manager', $booking), ['manager_id' => $tourist->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'manager_id' => $manager->id,
            'status'     => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_assigning_real_manager_to_new_booking_sets_status_to_progress(): void
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
