<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminChatListQueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $roleName): User
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
                'user_id' => $owner->id,
                'manager_id' => $managerId,
                'status' => $status,
                'departure_city' => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city' => 'Antalya',
                'start_date' => '2026-08-15',
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
            ])
        );
    }

    private function makeMessage(
        Booking $booking,
        int $senderId,
        int $receiverId,
        bool $isRead
    ): Message {
        return Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => 'Plain message',
            'is_read' => $isRead,
        ]);
    }

    /**
     * Counts query-log entries whose SQL touches the messages table,
     * including a bookings query that embeds correlated messages
     * subqueries (the bounded aggregate form this slice introduces) —
     * not just standalone `select ... from messages` statements (the
     * old per-booking form).
     */
    private function countMessagesTableQueries(array $queryLog): int
    {
        return collect($queryLog)
            ->filter(fn (array $entry): bool => (bool) preg_match(
                '/from\s+[`"]messages[`"]/i',
                $entry['query']
            ))
            ->count();
    }

    private function requestChatsAndCaptureMessagesQueryCount(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->actingAs($admin)->get(route('cabinet.admin.chats'));
            $response->assertOk();
            $queryLog = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return $this->countMessagesTableQueries($queryLog);
    }

    public function test_unread_badge_counts_are_correct_per_booking(): void
    {
        $admin = $this->createUserWithRole(Role::ADMIN);
        $manager = $this->createUserWithRole(Role::MANAGER);
        $touristA = $this->createUserWithRole(Role::TOURIST);
        $touristB = $this->createUserWithRole(Role::TOURIST);
        $bystander = $this->createUserWithRole(Role::TOURIST);

        $assignedBooking = $this->makeBookingFor($touristA, $manager->id, Booking::STATUS_PROGRESS);

        // Unread to manager (must be counted): 2
        $this->makeMessage($assignedBooking, $touristA->id, $manager->id, false);
        $this->makeMessage($assignedBooking, $touristA->id, $manager->id, false);

        // Read to manager (must NOT be counted)
        $this->makeMessage($assignedBooking, $touristA->id, $manager->id, true);

        // Unread to tourist (must be counted): 3
        $this->makeMessage($assignedBooking, $manager->id, $touristA->id, false);
        $this->makeMessage($assignedBooking, $manager->id, $touristA->id, false);
        $this->makeMessage($assignedBooking, $manager->id, $touristA->id, false);

        // Read to tourist (must NOT be counted)
        $this->makeMessage($assignedBooking, $manager->id, $touristA->id, true);

        // Unread to an unrelated bystander on the same booking (must NOT
        // affect either the manager or the tourist count).
        $this->makeMessage($assignedBooking, $touristA->id, $bystander->id, false);

        $unassignedBooking = $this->makeBookingFor($touristB, null, Booking::STATUS_NEW);

        // Unread to tourist on the unassigned booking: 2
        $this->makeMessage($unassignedBooking, $manager->id, $touristB->id, false);
        $this->makeMessage($unassignedBooking, $manager->id, $touristB->id, false);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.chats'));

        $response->assertOk();
        $response->assertViewIs('admin.chats');

        $unreadByBooking = $response->viewData('unreadByBooking');

        $this->assertSame(2, $unreadByBooking[$assignedBooking->id]['manager']);
        $this->assertSame(3, $unreadByBooking[$assignedBooking->id]['tourist']);

        // No manager assigned: manager count must be exactly 0.
        $this->assertSame(0, $unreadByBooking[$unassignedBooking->id]['manager']);
        $this->assertSame(2, $unreadByBooking[$unassignedBooking->id]['tourist']);
    }

    public function test_messages_query_count_does_not_grow_with_booking_count(): void
    {
        $admin = $this->createUserWithRole(Role::ADMIN);

        $managerOne = $this->createUserWithRole(Role::MANAGER);
        $touristOne = $this->createUserWithRole(Role::TOURIST);
        $bookingOne = $this->makeBookingFor($touristOne, $managerOne->id, Booking::STATUS_PROGRESS);
        $this->makeMessage($bookingOne, $touristOne->id, $managerOne->id, false);
        $this->makeMessage($bookingOne, $managerOne->id, $touristOne->id, false);

        $countForOneBooking = $this->requestChatsAndCaptureMessagesQueryCount($admin);

        for ($i = 0; $i < 5; $i++) {
            $manager = $this->createUserWithRole(Role::MANAGER);
            $tourist = $this->createUserWithRole(Role::TOURIST);
            $booking = $this->makeBookingFor($tourist, $manager->id, Booking::STATUS_PROGRESS);
            $this->makeMessage($booking, $tourist->id, $manager->id, false);
            $this->makeMessage($booking, $manager->id, $tourist->id, false);
        }

        $countForSixBookings = $this->requestChatsAndCaptureMessagesQueryCount($admin);

        $this->assertSame($countForOneBooking, $countForSixBookings);
        $this->assertLessThanOrEqual(2, $countForOneBooking);
    }
}
