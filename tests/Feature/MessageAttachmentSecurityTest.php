<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    // =======================================================================
    // Upload / storage
    // =======================================================================

    public function test_allowed_attachment_upload_is_stored_on_private_disk(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->post(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'See attached',
            'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $message = Message::query()->firstOrFail();
        $this->assertNotEmpty($message->attachment_url);
        Storage::disk('local')->assertExists($message->attachment_url);
    }

    public function test_uploaded_attachment_is_not_stored_on_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->post(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'See attached',
            'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEmpty(
            Storage::disk('public')->allFiles('messages'),
            'Attachment must not be written to the public disk.'
        );
    }

    public function test_attachment_only_message_succeeds(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // No 'message' key at all — attachment-only mode.
        $this->actingAs($owner)->post(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', [
            'booking_id' => $booking->id,
            'sender_id'  => $owner->id,
            'message'    => '',
        ]);
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Payload',
            'attachment'  => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('attachment');

        $this->assertDatabaseCount('messages', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('messages'));
    }

    public function test_archive_attachment_is_rejected(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Archive',
            'attachment'  => UploadedFile::fake()->create('bundle.zip', 100, 'application/zip'),
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('attachment');

        $this->assertDatabaseCount('messages', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('messages'));
    }

    public function test_oversized_attachment_is_rejected(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Big file',
            'attachment'  => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('attachment');

        $this->assertDatabaseCount('messages', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('messages'));
    }

    public function test_orphan_attachment_is_removed_when_message_persistence_fails(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $observedAtFailure = [];

        // Force Message persistence to fail *after* the file has been stored.
        Message::creating(function () use (&$observedAtFailure) {
            $observedAtFailure = Storage::disk('local')->allFiles('messages');
            throw new \RuntimeException('forced persistence failure');
        });

        try {
            $this->actingAs($owner)->post(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $manager->id,
                'message'     => 'Boom',
                'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])->assertStatus(500);
        } finally {
            Message::flushEventListeners();
        }

        // (1) the file really was on disk when persistence failed
        $this->assertNotEmpty(
            $observedAtFailure,
            'Attachment should have been stored before Message::create ran.'
        );
        // (3) the orphan file was cleaned up
        $this->assertEmpty(Storage::disk('local')->allFiles('messages'));
        // (4) no message row remains
        $this->assertDatabaseCount('messages', 0);
    }

    // =======================================================================
    // Download authorization
    // =======================================================================

    public function test_unauthenticated_user_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);

        $this->get(route('messages.attachment', $message))->assertRedirect();
    }

    public function test_booking_owner_tourist_can_download_attachment(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $manager->id, $owner->id);

        $this->actingAs($owner)
            ->get(route('messages.attachment', $message))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_current_assigned_manager_can_download_attachment(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);

        $this->actingAs($manager)
            ->get(route('messages.attachment', $message))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_assigned_admin_can_download_attachment(): void
    {
        Storage::fake('local');

        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        // Admin personally handles the booking: admin id lives in manager_id.
        $booking = $this->makeBookingFor($owner, $admin->id, Booking::STATUS_PROGRESS);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $admin->id);

        $this->actingAs($admin)
            ->get(route('messages.attachment', $message))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_supervising_admin_can_download_attachment(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN); // not assigned to the booking
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);

        $this->actingAs($admin)
            ->get(route('messages.attachment', $message))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_unrelated_tourist_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $owner    = $this->makeUser(Role::TOURIST);
        $manager  = $this->makeUser(Role::MANAGER);
        $intruder = $this->makeUser(Role::TOURIST);
        $booking  = $this->makeBookingFor($owner, $manager->id);
        $message  = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);

        $this->actingAs($intruder)
            ->get(route('messages.attachment', $message))
            ->assertForbidden();
    }

    public function test_foreign_manager_cannot_download_attachment(): void
    {
        Storage::fake('local');

        $owner          = $this->makeUser(Role::TOURIST);
        $manager        = $this->makeUser(Role::MANAGER);
        $foreignManager = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $manager->id);
        $message        = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);

        $this->actingAs($foreignManager)
            ->get(route('messages.attachment', $message))
            ->assertForbidden();
    }

    public function test_previous_assignee_cannot_download_after_reassignment(): void
    {
        Storage::fake('local');

        $owner    = $this->makeUser(Role::TOURIST);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id);

        // Manager A wrote the message while still assigned.
        $message = $this->makeMessageWithAttachment($booking, $managerA->id, $owner->id);

        // Booking is reassigned to manager B.
        $booking->manager_id = $managerB->id;
        $booking->saveQuietly();

        // Manager A is no longer the current handler — access is revoked,
        // even though A is the sender of the message.
        $this->actingAs($managerA)
            ->get(route('messages.attachment', $message))
            ->assertForbidden();
    }

    public function test_current_assignee_can_download_after_reassignment(): void
    {
        Storage::fake('local');

        $owner    = $this->makeUser(Role::TOURIST);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id);

        $message = $this->makeMessageWithAttachment($booking, $managerA->id, $owner->id);

        $booking->manager_id = $managerB->id;
        $booking->saveQuietly();

        $this->actingAs($managerB)
            ->get(route('messages.attachment', $message))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_missing_private_attachment_file_returns_not_found(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Row references a path that was never written to disk.
        $message = Message::query()->create([
            'booking_id'     => $booking->id,
            'sender_id'      => $manager->id,
            'receiver_id'    => $owner->id,
            'message'        => 'Missing file',
            'attachment_url' => 'messages/ghost.pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('messages.attachment', $message))
            ->assertNotFound();
    }

    public function test_message_without_attachment_returns_not_found(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $message = Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $manager->id,
            'receiver_id' => $owner->id,
            'message'     => 'Plain text',
        ]);

        $this->actingAs($owner)
            ->get(route('messages.attachment', $message))
            ->assertNotFound();
    }

    // =======================================================================
    // Polling / JSON
    // =======================================================================

    public function test_polled_message_exposes_protected_attachment_download_url(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $manager->id, $owner->id);

        $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk()
            ->assertJsonFragment([
                'attachment_download_url' => route('messages.attachment', $message),
            ]);
    }

    public function test_polled_message_does_not_expose_raw_private_attachment_path(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $manager->id, $owner->id);

        $response = $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $payload = $response->json();
        $this->assertNotEmpty($payload);
        $this->assertArrayNotHasKey('attachment_url', $payload[0]);
        $this->assertStringNotContainsString('/storage/', $response->getContent());
        $this->assertStringNotContainsString($message->attachment_url, $response->getContent());
    }

    // =======================================================================
    // UI — active chat views render the protected link
    // =======================================================================

    public function test_tourist_chat_view_renders_protected_attachment_link(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $manager->id, $owner->id, false);

        $this->actingAs($owner)
            ->get(route('cabinet.chat', $booking->id))
            ->assertOk()
            ->assertSee(route('messages.attachment', $message), false);
    }

    public function test_manager_chat_view_renders_protected_attachment_link(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id, false);

        $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))
            ->assertOk()
            ->assertSee(route('messages.attachment', $message), false);
    }

    public function test_admin_chat_view_renders_protected_attachment_link(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id, false);

        $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()
            ->assertSee(route('messages.attachment', $message), false);
    }

    // =======================================================================
    // Delete / cleanup
    // =======================================================================

    public function test_deleting_message_removes_private_attachment(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);
        $path    = $message->attachment_url;

        Storage::disk('local')->assertExists($path);

        $this->actingAs($owner)
            ->delete(route('messages.destroy', $message))
            ->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_message_without_attachment_succeeds(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $message = Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $owner->id,
            'receiver_id' => $manager->id,
            'message'     => 'No attachment here',
        ]);

        $this->actingAs($owner)
            ->delete(route('messages.destroy', $message))
            ->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_admin_can_delete_another_users_message(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);
        $path    = $message->attachment_url;

        $this->actingAs($admin)
            ->delete(route('messages.destroy', $message))
            ->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_unrelated_user_cannot_delete_message_and_attachment_is_preserved(): void
    {
        Storage::fake('local');

        $owner    = $this->makeUser(Role::TOURIST);
        $manager  = $this->makeUser(Role::MANAGER);
        $intruder = $this->makeUser(Role::TOURIST);
        $booking  = $this->makeBookingFor($owner, $manager->id);
        $message  = $this->makeMessageWithAttachment($booking, $owner->id, $manager->id);
        $path     = $message->attachment_url;

        $this->actingAs($intruder)
            ->delete(route('messages.destroy', $message))
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        Storage::disk('local')->assertExists($path);
    }

    // =======================================================================
    // Helpers
    // =======================================================================

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

    /**
     * Create a message whose attachment lives on the private (local) disk.
     * Pass $storeFile = false for view-render tests that only need the row.
     */
    private function makeMessageWithAttachment(
        Booking $booking,
        int $senderId,
        int $receiverId,
        bool $storeFile = true
    ): Message {
        $path = 'messages/' . uniqid('att_', true) . '.pdf';

        if ($storeFile) {
            Storage::disk('local')->put($path, 'private-attachment-content');
        }

        return Message::query()->create([
            'booking_id'     => $booking->id,
            'sender_id'      => $senderId,
            'receiver_id'    => $receiverId,
            'message'        => 'Attachment message',
            'attachment_url' => $path,
        ]);
    }
}
