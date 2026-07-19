<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingCancelEffectiveRoleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_dual_role_owner_and_assignee_cancels_progress_booking(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBooking($user, $user->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($user)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_dual_role_owner_and_assignee_cancels_confirmed_booking(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBooking($user, $user->id, Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($user)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_manager_only_cancels_assigned_progress_booking(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($manager)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_tourist_only_cancels_own_new_unassigned_booking(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_tourist_only_cannot_cancel_own_assigned_progress_booking(): void
    {
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        $response->assertForbidden();
        $booking->refresh();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
    }

    public function test_foreign_manager_cannot_cancel_another_managers_booking(): void
    {
        $owner           = $this->makeUser([Role::TOURIST]);
        $assignedManager = $this->makeUser([Role::MANAGER]);
        $foreignManager  = $this->makeUser([Role::MANAGER]);
        $booking         = $this->makeBooking($owner, $assignedManager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($foreignManager)
            ->post(route('bookings.cancel', $booking));

        $response->assertForbidden();
        $booking->refresh();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
    }

    public function test_admin_cancels_confirmed_booking_assigned_to_another_manager(): void
    {
        $admin   = $this->makeUser([Role::ADMIN]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $manager = $this->makeUser([Role::MANAGER]);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeBooking(
        User $owner,
        ?int $managerId,
        string $status
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
