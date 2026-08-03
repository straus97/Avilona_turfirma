<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BookingCancellationAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seat_restoration_exception_leaves_booking_status_and_seats_unchanged(): void
    {
        $owner = $this->makeUser(Role::TOURIST);

        $sentinelSlug = 'atomicity-sentinel-' . Str::uuid();

        $tour = Tour::factory()->create([
            'slug' => $sentinelSlug,
            'available_seats' => 10,
            'max_tourists' => 20,
        ]);
        $tourId = $tour->id;

        $booking = $this->makeBooking($owner, Booking::STATUS_NEW, [
            'tour_id' => $tour->id,
            'adults' => 2,
            'children' => 0,
        ]);
        $originalStatus = $booking->status;

        $fired = false;

        Tour::updating(function (Tour $updating) use ($tourId, $sentinelSlug, &$fired): void {
            if ($fired) {
                return;
            }

            if ($updating->id !== $tourId) {
                return;
            }

            if ($updating->slug !== $sentinelSlug) {
                return;
            }

            $fired = true;

            throw new RuntimeException('forced tour seat restoration failure');
        });

        $this->withoutExceptionHandling();

        try {
            $booking->transitionTo(Booking::STATUS_CANCELLED);

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced tour seat restoration failure', $e->getMessage());
        }

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => $originalStatus,
        ]);

        $this->assertDatabaseHas('tours', [
            'id' => $tourId,
            'available_seats' => 10,
        ]);

        // Best-effort refresh must resync the in-memory instance with the
        // rolled-back database row.
        $this->assertSame($originalStatus, $booking->status);
    }

    public function test_seat_restoration_capacity_exceeded_leaves_booking_status_and_seats_unchanged(): void
    {
        $owner = $this->makeUser(Role::TOURIST);

        $tour = Tour::factory()->create([
            'available_seats' => 20,
            'max_tourists' => 20,
        ]);

        $booking = $this->makeBooking($owner, Booking::STATUS_NEW, [
            'tour_id' => $tour->id,
            'adults' => 2,
            'children' => 0,
        ]);
        $originalStatus = $booking->status;

        $this->withoutExceptionHandling();

        $threw = false;

        try {
            $booking->transitionTo(Booking::STATUS_CANCELLED);
            $this->fail('Expected \DomainException was not thrown.');
        } catch (\DomainException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, '\DomainException must be thrown when seat restoration would exceed max_tourists.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => $originalStatus,
        ]);

        $this->assertDatabaseHas('tours', [
            'id' => $tour->id,
            'available_seats' => 20,
        ]);

        // Best-effort refresh must resync the in-memory instance with the
        // rolled-back database row.
        $this->assertSame($originalStatus, $booking->status);
    }

    public function test_successful_cancellation_updates_booking_status_and_tour_seats_exactly_once(): void
    {
        $owner = $this->makeUser(Role::TOURIST);

        $tour = Tour::factory()->create([
            'available_seats' => 10,
            'max_tourists' => 20,
        ]);

        $booking = $this->makeBooking($owner, Booking::STATUS_NEW, [
            'tour_id' => $tour->id,
            'adults' => 2,
            'children' => 0,
        ]);

        $booking->transitionTo(Booking::STATUS_CANCELLED);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('tours', [
            'id' => $tour->id,
            'available_seats' => 12,
        ]);
    }

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
        string $status,
        array $overrides = []
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create(array_merge([
                'user_id' => $owner->id,
                'manager_id' => null,
                'status' => $status,
                'departure_city' => 'Saint Petersburg',
                'destination_country' => 'Tunisia',
                'destination_city' => 'Hammamet',
                'start_date' => '2026-08-20',
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
            ], $overrides))
        );
    }
}
