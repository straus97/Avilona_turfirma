<?php

namespace Tests\Feature;

use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Only fake the events we assert on; model lifecycle events (slug
        // generation, etc.) must still reach their real listeners.
        Event::fake([BookingStatusChanged::class]);
    }

    // -----------------------------------------------------------------------
    // 1. Model transition matrix (tested via HTTP routes)
    // -----------------------------------------------------------------------

    public function test_new_to_progress_with_assigned_manager_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_NEW);

        $this->actingAs($manager)
            ->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_PROGRESS,
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_unassigned_new_to_progress_is_rejected(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)
            ->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_PROGRESS,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_new_to_cancelled_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_progress_to_confirmed_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_progress_to_cancelled_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_confirmed_to_completed_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $this->actingAs($manager)
            ->post(route('bookings.complete', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    public function test_confirmed_to_cancelled_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $this->actingAs($manager)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 2. Invalid dedicated actions
    // -----------------------------------------------------------------------

    public function test_confirm_from_new_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)
            ->post(route('bookings.confirm', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_confirm_from_cancelled_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_CANCELLED);

        $this->actingAs($admin)
            ->post(route('bookings.confirm', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_complete_from_new_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)
            ->post(route('bookings.complete', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_complete_from_progress_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->post(route('bookings.complete', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_cancel_from_cancelled_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_CANCELLED);

        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_cancel_from_completed_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_COMPLETED);

        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 3. Generic update route
    // -----------------------------------------------------------------------

    public function test_same_status_can_update_manager_notes_and_total_price(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_PROGRESS,
                'manager_notes' => 'Updated notes',
                'total_price'   => 99000,
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'            => $booking->id,
            'status'        => Booking::STATUS_PROGRESS,
            'manager_notes' => 'Updated notes',
            'total_price'   => 99000,
        ]);
    }

    public function test_valid_transition_through_update_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_CONFIRMED,
                'manager_notes' => 'Confirmed via update',
                'total_price'   => 80000,
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id'            => $booking->id,
            'status'        => Booking::STATUS_CONFIRMED,
            'manager_notes' => 'Confirmed via update',
            'total_price'   => 80000,
        ]);
    }

    public function test_new_to_completed_via_update_is_rejected(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)
            ->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_COMPLETED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);
    }

    public function test_progress_to_completed_via_update_is_rejected(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_COMPLETED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_PROGRESS,
        ]);
    }

    public function test_terminal_booking_cannot_be_reopened_via_update(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_CANCELLED);

        $this->actingAs($admin)
            ->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_NEW,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    public function test_completed_booking_cannot_be_reopened_via_update(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_COMPLETED);

        $this->actingAs($admin)
            ->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_CONFIRMED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    public function test_invalid_transition_does_not_partially_update_other_fields(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW, [
            'manager_notes' => null,
            'total_price'   => 50000,
        ]);

        $this->actingAs($admin)
            ->put(route('bookings.update', $booking), [
                'status'        => Booking::STATUS_COMPLETED,
                'manager_notes' => 'Should not be saved',
                'total_price'   => 999999,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'          => $booking->id,
            'status'      => Booking::STATUS_NEW,
            'total_price' => 50000,
        ]);

        $this->assertDatabaseMissing('bookings', [
            'id'            => $booking->id,
            'manager_notes' => 'Should not be saved',
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. Side effects
    // -----------------------------------------------------------------------

    public function test_successful_real_transition_dispatches_booking_status_changed_once(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->post(route('bookings.confirm', $booking));

        Event::assertDispatched(BookingStatusChanged::class, 1);
    }

    public function test_invalid_transition_dispatches_no_booking_status_changed(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $this->actingAs($admin)
            ->post(route('bookings.confirm', $booking));

        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_repeated_cancellation_does_not_restore_tour_seats_twice(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);

        $tour = Tour::factory()->create([
            'available_seats' => 10,
            'max_tourists'    => 20,
        ]);

        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW, [
            'tour_id' => $tour->id,
            'adults'  => 2,
            'children' => 0,
        ]);

        // First cancellation — tour gets 2 seats back (10 + 2 = 12).
        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('tours', [
            'id'              => $tour->id,
            'available_seats' => 12,
        ]);

        // Second attempt — booking is already CANCELLED; must be rejected.
        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertSessionHasErrors('status');

        // Seats must still be 12 — no second restoration.
        $this->assertDatabaseHas('tours', [
            'id'              => $tour->id,
            'available_seats' => 12,
        ]);
    }

    // -----------------------------------------------------------------------
    // 5. Direct model-level transition enforcement
    // -----------------------------------------------------------------------

    public function test_direct_invalid_transition_throws_domain_exception_without_mutation_or_event(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $threw = false;
        try {
            $booking->transitionTo(Booking::STATUS_COMPLETED);
            $this->fail('Expected \DomainException was not thrown.');
        } catch (\DomainException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, '\DomainException must be thrown for an invalid transition.');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_NEW,
        ]);

        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_direct_unassigned_new_to_progress_throws_domain_exception(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $threw = false;
        try {
            $booking->transitionTo(Booking::STATUS_PROGRESS);
            $this->fail('Expected \DomainException was not thrown.');
        } catch (\DomainException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, '\DomainException must be thrown when manager_id is null.');

        $this->assertDatabaseHas('bookings', [
            'id'         => $booking->id,
            'status'     => Booking::STATUS_NEW,
            'manager_id' => null,
        ]);

        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_confirm_from_confirmed_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $this->actingAs($manager)
            ->post(route('bookings.confirm', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_complete_from_completed_is_rejected_and_unchanged(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_COMPLETED);

        $this->actingAs($manager)
            ->post(route('bookings.complete', $booking))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Event::assertNotDispatched(BookingStatusChanged::class);
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
