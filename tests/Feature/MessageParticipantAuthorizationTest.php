<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageParticipantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Suppress all event listeners (mail, notifications) to isolate authorization logic.
        Event::fake();
    }

    // -----------------------------------------------------------------------
    // 1. Unauthenticated user cannot store a message
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_store_message(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 2. Owner tourist can send to the assigned manager
    // -----------------------------------------------------------------------

    public function test_tourist_can_send_to_assigned_manager(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello manager',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $owner->id,
            'receiver_id' => $manager->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // 3. Owner tourist cannot send to an unrelated user
    // -----------------------------------------------------------------------

    public function test_tourist_cannot_send_to_unrelated_user(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $other->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 4. Owner tourist cannot send to another non-assigned manager
    // -----------------------------------------------------------------------

    public function test_tourist_cannot_send_to_non_assigned_manager(): void
    {
        $owner           = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $otherManager    = $this->makeUser(Role::MANAGER);
        $booking         = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $otherManager->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 5. Owner tourist cannot send to themselves
    // -----------------------------------------------------------------------

    public function test_tourist_cannot_send_to_themselves(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $owner->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 6. Owner tourist cannot send for an unassigned booking
    // -----------------------------------------------------------------------

    public function test_tourist_cannot_send_for_unassigned_booking(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner); // no manager assigned

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $other->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 7. Assigned manager can send to the booking owner
    // -----------------------------------------------------------------------

    public function test_assigned_manager_can_send_to_booking_owner(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $owner->id,
            'message'     => 'Hello tourist',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $manager->id,
            'receiver_id' => $owner->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // 8. Assigned manager cannot send to an unrelated tourist
    // -----------------------------------------------------------------------

    public function test_assigned_manager_cannot_send_to_unrelated_tourist(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($manager)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $other->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 9. Foreign manager cannot send to the booking owner (403)
    // -----------------------------------------------------------------------

    public function test_foreign_manager_cannot_send_to_booking_owner(): void
    {
        $owner           = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking         = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $owner->id,
            'message'     => 'Hello',
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 10. Foreign tourist cannot send to the assigned manager (403)
    // -----------------------------------------------------------------------

    public function test_foreign_tourist_cannot_send_to_assigned_manager(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $manager        = $this->makeUser(Role::MANAGER);
        $foreignTourist = $this->makeUser(Role::TOURIST);
        $booking        = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($foreignTourist)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello',
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 11. Admin can send to the booking owner
    // -----------------------------------------------------------------------

    public function test_admin_can_send_to_booking_owner(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($admin)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $owner->id,
            'message'     => 'Hello tourist',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $admin->id,
            'receiver_id' => $owner->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // 12. Admin can send to the assigned manager
    // -----------------------------------------------------------------------

    public function test_admin_can_send_to_assigned_manager(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($admin)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello manager',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $admin->id,
            'receiver_id' => $manager->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // 13. Admin cannot send to an unrelated user
    // -----------------------------------------------------------------------

    public function test_admin_cannot_send_to_unrelated_user(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($admin)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $other->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 14. Receiver validation occurs before attachment storage
    // -----------------------------------------------------------------------

    public function test_receiver_validation_occurs_before_attachment_storage(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)
            ->post(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $other->id,
                'message'     => 'Hello',
                'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('receiver_id');

        $this->assertDatabaseCount('messages', 0);
        $this->assertEmpty(
            Storage::disk('local')->allFiles('messages'),
            'No attachment should have been stored when receiver validation fails.'
        );
    }

    // -----------------------------------------------------------------------
    // 15. Assigned admin as booking handler (admin stored in manager_id)
    // -----------------------------------------------------------------------

    public function test_owner_can_send_to_assigned_admin(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        // Администратор лично ведёт заявку: он и есть manager_id.
        $booking = $this->makeBookingFor($owner, $admin->id, Booking::STATUS_PROGRESS);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $admin->id,
            'message'     => 'Hello responsible admin',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $owner->id,
            'receiver_id' => $admin->id,
        ]);
    }

    public function test_assigned_admin_can_send_to_booking_owner(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $admin->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $owner->id,
            'message'     => 'Hello tourist',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $admin->id,
            'receiver_id' => $owner->id,
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
