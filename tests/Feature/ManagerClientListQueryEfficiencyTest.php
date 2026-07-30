<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagerClientListQueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRoles(array $roleNames): User
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

    /**
     * Creates a Booking with an explicit created_at, bypassing the
     * BookingCreated event (matching the repository's existing
     * withoutEvents() fixture convention) so no mail listener runs.
     */
    private function createBooking(array $attributes, \DateTimeInterface $createdAt): Booking
    {
        return Booking::withoutEvents(function () use ($attributes, $createdAt): Booking {
            $booking = new Booking();
            $booking->forceFill(array_merge($attributes, [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
            $booking->save();

            return $booking;
        });
    }

    private function baseBookingAttributes(int $userId, ?int $managerId, string $status): array
    {
        return [
            'user_id' => $userId,
            'manager_id' => $managerId,
            'status' => $status,
            'departure_city' => 'Saint Petersburg',
            'destination_country' => 'Turkey',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7,
            'adults' => 2,
        ];
    }

    private function isHydrationQuery(string $sql): bool
    {
        return (bool) preg_match(
            '/^select \* from [`"]bookings[`"] where ([`"]bookings[`"]\.)?[`"]id[`"] in \(/i',
            trim($sql)
        );
    }

    public function test_client_list_shows_correct_active_count_and_latest_booking(): void
    {
        $managerA = $this->createUserWithRoles([Role::MANAGER]);
        $managerB = $this->createUserWithRoles([Role::MANAGER]);
        $clientA = $this->createUserWithRoles([Role::TOURIST]);
        $clientB = $this->createUserWithRoles([Role::TOURIST]);
        $outsider = $this->createUserWithRoles([Role::TOURIST]);

        $olderCompleted = $this->createBooking(
            $this->baseBookingAttributes($clientA->id, $managerA->id, Booking::STATUS_COMPLETED),
            now()->subDays(10)
        );

        $newerNewForManagerA = $this->createBooking(
            $this->baseBookingAttributes($clientA->id, $managerA->id, Booking::STATUS_NEW),
            now()->subDay()
        );

        // Newer than both of manager A's bookings, but belongs to manager B —
        // must not affect manager A's active_bookings or latest_booking.
        $this->createBooking(
            $this->baseBookingAttributes($clientA->id, $managerB->id, Booking::STATUS_NEW),
            now()
        );

        $clientBCancelled = $this->createBooking(
            $this->baseBookingAttributes($clientB->id, $managerA->id, Booking::STATUS_CANCELLED),
            now()->subDays(5)
        );

        // Outsider only has a booking with manager B, so must not appear in
        // manager A's client list at all.
        $this->createBooking(
            $this->baseBookingAttributes($outsider->id, $managerB->id, Booking::STATUS_NEW),
            now()
        );

        $response = $this->actingAs($managerA)->get(route('cabinet.manager.clients'));

        $response->assertOk();

        $paginator = $response->viewData('clients');
        $items = $paginator->getCollection();

        $this->assertSame(20, $paginator->perPage());

        $listedIds = $items->pluck('id')->sort()->values()->all();
        $this->assertSame(
            collect([$clientA->id, $clientB->id])->sort()->values()->all(),
            $listedIds
        );
        $this->assertFalse($items->contains('id', $outsider->id));

        $clientARow = $items->firstWhere('id', $clientA->id);
        $clientBRow = $items->firstWhere('id', $clientB->id);

        $this->assertSame(2, $clientARow->bookings_count);
        $this->assertSame(1, $clientARow->active_bookings);
        $this->assertInstanceOf(Booking::class, $clientARow->latest_booking);
        $this->assertSame($newerNewForManagerA->id, $clientARow->latest_booking->id);

        $this->assertSame(0, $clientBRow->active_bookings);
        $this->assertInstanceOf(Booking::class, $clientBRow->latest_booking);
        $this->assertSame($clientBCancelled->id, $clientBRow->latest_booking->id);
    }

    public function test_latest_booking_hydration_is_bounded_to_one_model_per_client(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $clients = [
            $this->createUserWithRoles([Role::TOURIST]),
            $this->createUserWithRoles([Role::TOURIST]),
            $this->createUserWithRoles([Role::TOURIST]),
        ];

        $expectedLatestIds = [];

        foreach ($clients as $index => $client) {
            $latest = null;

            foreach ([3, 2, 1] as $daysAgo) {
                $booking = $this->createBooking(
                    $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
                    now()->subDays($daysAgo)->subHours($index)
                );

                if ($daysAgo === 1) {
                    $latest = $booking;
                }
            }

            $expectedLatestIds[$client->id] = $latest->id;
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->actingAs($manager)->get(route('cabinet.manager.clients'));
            $response->assertOk();
            $queryLog = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $hydrationEntries = collect($queryLog)->filter(
            fn (array $entry): bool => $this->isHydrationQuery($entry['query'])
        )->values();

        $this->assertCount(1, $hydrationEntries);

        $hydrationEntry = $hydrationEntries->first();

        $this->assertCount(3, $hydrationEntry['bindings']);
        $this->assertFalse(str_contains(strtolower($hydrationEntry['query']), 'manager_id'));

        $boundIds = collect($hydrationEntry['bindings'])->map(fn ($value) => (int) $value);
        $this->assertCount(3, $boundIds->unique());

        $paginator = $response->viewData('clients');
        $items = $paginator->getCollection();

        foreach ($clients as $client) {
            $row = $items->firstWhere('id', $client->id);

            $this->assertInstanceOf(Booking::class, $row->latest_booking);
            $this->assertSame($expectedLatestIds[$client->id], $row->latest_booking->id);
        }
    }

    private function runClientsRequestAndCountBookingQueries(User $manager): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->actingAs($manager)->get(route('cabinet.manager.clients'));
            $response->assertOk();
            $queryLog = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return collect($queryLog)
            ->filter(fn (array $entry): bool => (bool) preg_match('/[`"]bookings[`"]/i', $entry['query']))
            ->count();
    }

    public function test_booking_query_count_does_not_grow_with_client_count(): void
    {
        $managerOne = $this->createUserWithRoles([Role::MANAGER]);
        $clientOne = $this->createUserWithRoles([Role::TOURIST]);
        $this->createBooking(
            $this->baseBookingAttributes($clientOne->id, $managerOne->id, Booking::STATUS_NEW),
            now()->subDay()
        );

        $managerFive = $this->createUserWithRoles([Role::MANAGER]);
        for ($i = 0; $i < 5; $i++) {
            $client = $this->createUserWithRoles([Role::TOURIST]);
            $this->createBooking(
                $this->baseBookingAttributes($client->id, $managerFive->id, Booking::STATUS_NEW),
                now()->subDay()
            );
        }

        $countForOneClient = $this->runClientsRequestAndCountBookingQueries($managerOne);
        $countForFiveClients = $this->runClientsRequestAndCountBookingQueries($managerFive);

        $this->assertSame($countForOneClient, $countForFiveClients);
        $this->assertSame(3, $countForOneClient);
        $this->assertNotSame(2 * 5, $countForFiveClients);
    }
}
