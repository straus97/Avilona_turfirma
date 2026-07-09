<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Upload (storeDocument)
    // -----------------------------------------------------------------------

    public function test_assigned_manager_can_upload_valid_document(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $file = UploadedFile::fake()->create('contract.pdf', 512, 'application/pdf');

        $this->actingAs($manager)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Test Contract',
                'document_type' => 'contract',
                'file'          => $file,
            ])
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success');
    }

    public function test_uploaded_record_has_correct_fields_and_file_exists(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $file = UploadedFile::fake()->create('voucher.pdf', 1024, 'application/pdf');

        $this->actingAs($manager)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'My Voucher',
                'document_type' => 'voucher',
                'file'          => $file,
            ]);

        $document = BookingDocument::first();

        $this->assertNotNull($document);
        $this->assertSame($booking->id, $document->booking_id);
        $this->assertSame($manager->id, $document->uploaded_by);
        $this->assertSame('voucher', $document->document_type);
        $this->assertSame('My Voucher', $document->title);
        $this->assertGreaterThan(0, $document->file_size);

        // Exact file size matches the uploaded file
        $this->assertEquals($file->getSize(), $document->file_size);

        // File stored under the correct booking-scoped directory
        $this->assertStringStartsWith(
            "documents/bookings/{$booking->id}/",
            $document->file_path
        );

        // uploaded_at is persisted
        $this->assertNotNull($document->uploaded_at);

        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_foreign_manager_receives_403_on_upload(): void
    {
        Storage::fake('local');

        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($foreignManager)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Intruder Doc',
                'document_type' => 'other',
                'file'          => $file,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('booking_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty("documents/bookings/{$booking->id}");
    }

    public function test_tourist_cannot_use_upload_route(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($owner)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Tourist Doc',
                'document_type' => 'other',
                'file'          => $file,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_upload_to_any_booking(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner); // no manager assigned

        $file = UploadedFile::fake()->create('insurance.pdf', 256, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Admin Insurance',
                'document_type' => 'insurance',
                'file'          => $file,
            ])
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('booking_documents', [
            'booking_id'    => $booking->id,
            'uploaded_by'   => $admin->id,
            'document_type' => 'insurance',
        ]);
    }

    // -----------------------------------------------------------------------
    // Download (downloadDocument)
    // -----------------------------------------------------------------------

    public function test_assigned_manager_can_download_document(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        Storage::disk('local')->put(
            "documents/bookings/{$booking->id}/ticket.pdf",
            'binary-content'
        );

        $document = $this->makeDocument($booking, $manager->id, "documents/bookings/{$booking->id}/ticket.pdf");

        $this->actingAs($manager)
            ->get(route('bookings.documents.download', [$booking, $document]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_admin_can_download_any_booking_document(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner);

        Storage::disk('local')->put(
            "documents/bookings/{$booking->id}/admin-doc.pdf",
            'binary-content'
        );

        $document = $this->makeDocument($booking, $admin->id, "documents/bookings/{$booking->id}/admin-doc.pdf");

        $this->actingAs($admin)
            ->get(route('bookings.documents.download', [$booking, $document]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_foreign_manager_receives_403_on_download(): void
    {
        Storage::fake('local');

        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        Storage::disk('local')->put(
            "documents/bookings/{$booking->id}/voucher.pdf",
            'binary-content'
        );

        $document = $this->makeDocument($booking, $assignedManager->id, "documents/bookings/{$booking->id}/voucher.pdf");

        $this->actingAs($foreignManager)
            ->get(route('bookings.documents.download', [$booking, $document]))
            ->assertForbidden();
    }

    public function test_mismatched_booking_document_pair_returns_404(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $other   = $this->makeBookingFor($owner, $manager->id);

        Storage::disk('local')->put(
            "documents/bookings/{$booking->id}/doc.pdf",
            'binary-content'
        );

        // Document belongs to $booking but route uses $other
        $document = $this->makeDocument($booking, $manager->id, "documents/bookings/{$booking->id}/doc.pdf");

        $this->actingAs($manager)
            ->get(route('bookings.documents.download', [$other, $document]))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Delete (destroyDocument)
    // -----------------------------------------------------------------------

    public function test_assigned_manager_can_delete_document(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $filePath = "documents/bookings/{$booking->id}/to-delete.pdf";
        Storage::disk('local')->put($filePath, 'binary-content');

        $document = $this->makeDocument($booking, $manager->id, $filePath);

        $this->actingAs($manager)
            ->delete(route('bookings.documents.destroy', [$booking, $document]))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success');

        // Soft-deleted row still in DB, deleted_at set
        $this->assertSoftDeleted('booking_documents', ['id' => $document->id]);

        // Physical file removed
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_foreign_manager_cannot_delete_document(): void
    {
        Storage::fake('local');

        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $filePath = "documents/bookings/{$booking->id}/protected.pdf";
        Storage::disk('local')->put($filePath, 'binary-content');

        $document = $this->makeDocument($booking, $assignedManager->id, $filePath);

        $this->actingAs($foreignManager)
            ->delete(route('bookings.documents.destroy', [$booking, $document]))
            ->assertForbidden();

        $this->assertDatabaseHas('booking_documents', [
            'id'         => $document->id,
            'deleted_at' => null,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }

    public function test_tourist_cannot_delete_document(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $filePath = "documents/bookings/{$booking->id}/tourist-test.pdf";
        Storage::disk('local')->put($filePath, 'binary-content');

        $document = $this->makeDocument($booking, $manager->id, $filePath);

        $this->actingAs($owner)
            ->delete(route('bookings.documents.destroy', [$booking, $document]))
            ->assertForbidden();
    }

    public function test_invalid_extension_is_rejected(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

        $this->actingAs($manager)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Evil File',
                'document_type' => 'other',
                'file'          => $file,
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('booking_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty("documents/bookings/{$booking->id}");
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // 11 MB — exceeds the 10 MB limit
        $file = UploadedFile::fake()->create('big.pdf', 11264, 'application/pdf');

        $this->actingAs($manager)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Big File',
                'document_type' => 'other',
                'file'          => $file,
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('booking_documents', 0);

        // No private file must have been created
        Storage::disk('local')->assertDirectoryEmpty("documents/bookings/{$booking->id}");
    }

    // -----------------------------------------------------------------------
    // Additional focused tests
    // -----------------------------------------------------------------------

    public function test_unassigned_manager_receives_403_on_upload(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        // Booking has no manager_id
        $booking = $this->makeBookingFor($owner);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($manager)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Unassigned Attempt',
                'document_type' => 'other',
                'file'          => $file,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('booking_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty("documents/bookings/{$booking->id}");
    }

    public function test_tourist_receives_403_on_manager_download_route(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        Storage::disk('local')->put(
            "documents/bookings/{$booking->id}/ticket.pdf",
            'binary-content'
        );

        $document = $this->makeDocument(
            $booking,
            $manager->id,
            "documents/bookings/{$booking->id}/ticket.pdf"
        );

        $this->actingAs($owner)
            ->get(route('bookings.documents.download', [$booking, $document]))
            ->assertForbidden();
    }

    public function test_download_returns_404_when_file_missing(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Document row exists but the physical file was never put on disk
        $document = $this->makeDocument(
            $booking,
            $manager->id,
            "documents/bookings/{$booking->id}/ghost.pdf"
        );

        $this->actingAs($manager)
            ->get(route('bookings.documents.download', [$booking, $document]))
            ->assertNotFound();
    }

    public function test_delete_with_mismatched_booking_document_returns_404(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $other   = $this->makeBookingFor($owner, $manager->id);

        $filePath = "documents/bookings/{$booking->id}/mismatch.pdf";
        Storage::disk('local')->put($filePath, 'binary-content');

        // Document belongs to $booking, but route uses $other
        $document = $this->makeDocument($booking, $manager->id, $filePath);

        $this->actingAs($manager)
            ->delete(route('bookings.documents.destroy', [$other, $document]))
            ->assertNotFound();

        // Row must not be soft-deleted
        $this->assertDatabaseHas('booking_documents', [
            'id'         => $document->id,
            'deleted_at' => null,
        ]);

        // File must still exist
        Storage::disk('local')->assertExists($filePath);
    }

    public function test_db_failure_removes_stored_file_and_returns_error(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Capture the original dispatcher so we can restore it exactly.
        $originalDispatcher = BookingDocument::getEventDispatcher();

        // Install an isolated clone so our throwing listener cannot affect
        // any other test's event state.
        $isolatedDispatcher = clone $originalDispatcher;
        BookingDocument::setEventDispatcher($isolatedDispatcher);

        // Register the throwing listener only on the isolated dispatcher.
        BookingDocument::creating(static function (): never {
            throw new \RuntimeException('Simulated DB failure');
        });

        try {
            $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

            $this->actingAs($manager)
                ->post(route('bookings.documents.store', $booking), [
                    'title'         => 'DB Fail Doc',
                    'document_type' => 'contract',
                    'file'          => $file,
                ])
                ->assertRedirect(route('bookings.show', $booking))
                ->assertSessionHas('error');
        } finally {
            // Restore the exact original dispatcher — no forget() needed.
            BookingDocument::setEventDispatcher($originalDispatcher);
        }

        // Dispatcher identity is fully restored after the test body.
        $this->assertSame($originalDispatcher, BookingDocument::getEventDispatcher());

        // No record was persisted.
        $this->assertDatabaseCount('booking_documents', 0);

        // Stored file must have been cleaned up.
        Storage::disk('local')->assertDirectoryEmpty(
            "documents/bookings/{$booking->id}"
        );
    }

    public function test_download_content_disposition_uses_sanitized_filename_and_real_extension(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Actual file stored on disk is a PDF
        $filePath = "documents/bookings/{$booking->id}/actual.pdf";
        Storage::disk('local')->put($filePath, 'binary-content');

        // Title contains path separators, CR+LF, and a misleading extension
        $document = BookingDocument::query()->create([
            'booking_id'    => $booking->id,
            'document_type' => 'voucher',
            'title'         => "../../evil/path\r\nmalicious.exe",
            'file_path'     => $filePath,
            'file_size'     => 14,
            'uploaded_by'   => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('bookings.documents.download', [$booking, $document]));

        $response->assertOk();

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertNotEmpty($disposition, 'Content-Disposition header must be present');

        // Extract the filename value — handles both quoted and unquoted token forms:
        //   filename="foo.pdf"  (quoted, when the name contains spaces or special chars)
        //   filename=foo.pdf    (unquoted token, pure ASCII name)
        $downloadName = '';
        if (preg_match('/\bfilename\s*=\s*"([^"]*)"/', $disposition, $m)) {
            $downloadName = $m[1];
        } elseif (preg_match('/\bfilename\s*=\s*([^\s;]+)/', $disposition, $m)) {
            $downloadName = $m[1];
        }

        $this->assertNotEmpty($downloadName, 'Filename in Content-Disposition must not be empty');

        // Extension must come from file_path (.pdf), not from the title (.exe)
        $this->assertStringEndsWith('.pdf', $downloadName);

        // No path separators
        $this->assertStringNotContainsString('/', $downloadName);
        $this->assertStringNotContainsString('\\', $downloadName);

        // No control characters
        $this->assertStringNotContainsString("\r", $downloadName);
        $this->assertStringNotContainsString("\n", $downloadName);
        $this->assertStringNotContainsString("\0", $downloadName);
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

    private function makeBookingFor(User $owner, ?int $managerId = null): Booking
    {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'              => $owner->id,
                'manager_id'           => $managerId,
                'status'               => Booking::STATUS_NEW,
                'departure_city'       => 'Moscow',
                'destination_country'  => 'Turkey',
                'destination_city'     => 'Antalya',
                'start_date'           => '2026-08-15',
                'nights'               => 7,
                'adults'               => 2,
                'children'             => 0,
            ])
        );
    }

    private function makeDocument(Booking $booking, int $uploaderId, string $filePath): BookingDocument
    {
        return BookingDocument::query()->create([
            'booking_id'    => $booking->id,
            'document_type' => 'voucher',
            'title'         => 'Test Document',
            'file_path'     => $filePath,
            'file_size'     => 1024,
            'uploaded_by'   => $uploaderId,
        ]);
    }
}
