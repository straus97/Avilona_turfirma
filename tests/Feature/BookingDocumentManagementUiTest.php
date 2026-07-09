<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDocumentManagementUiTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Assigned manager sees all controls
    // -----------------------------------------------------------------------

    public function test_assigned_manager_sees_document_section_upload_form_and_controls(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $document = $this->makeDocument($booking, $manager->id);

        $response = $this->actingAs($manager)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        // Document heading
        $response->assertSee('Документы по заявке', false);

        // Upload form is present
        $response->assertSee(route('bookings.documents.store', $booking), false);
        $response->assertSee('enctype="multipart/form-data"', false);

        // All three upload fields
        $response->assertSee('name="title"', false);
        $response->assertSee('name="document_type"', false);
        $response->assertSee('name="file"', false);

        // Manager download URL for the document
        $response->assertSee(
            route('bookings.documents.download', [$booking, $document]),
            false
        );

        // Delete form action URL
        $response->assertSee(
            route('bookings.documents.destroy', [$booking, $document]),
            false
        );
    }

    // -----------------------------------------------------------------------
    // 2. Admin on unassigned booking sees all controls
    // -----------------------------------------------------------------------

    public function test_admin_can_open_unassigned_booking_and_sees_upload_download_delete(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $admin    = $this->makeUser(Role::ADMIN);
        $booking  = $this->makeBookingFor($owner); // no manager
        $document = $this->makeDocument($booking, $admin->id);

        $response = $this->actingAs($admin)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        // Upload form
        $response->assertSee(route('bookings.documents.store', $booking), false);

        // Download URL
        $response->assertSee(
            route('bookings.documents.download', [$booking, $document]),
            false
        );

        // Delete URL
        $response->assertSee(
            route('bookings.documents.destroy', [$booking, $document]),
            false
        );
    }

    // -----------------------------------------------------------------------
    // 3. Tourist sees document title and tourist download, but not manager controls
    // -----------------------------------------------------------------------

    public function test_tourist_sees_document_and_tourist_download_but_not_manager_controls(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $manager  = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $manager->id);
        $document = $this->makeDocument($booking, $manager->id);

        $response = $this->actingAs($owner)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        // Tourist sees document title
        $response->assertSee($document->title, false);

        // Tourist download URL present
        $response->assertSee(
            route('cabinet.documents.bookings.download', [$booking, $document]),
            false
        );

        // No upload form
        $response->assertDontSee(route('bookings.documents.store', $booking), false);
        $response->assertDontSee('name="title"', false);
        $response->assertDontSee('name="document_type"', false);
        $response->assertDontSee('name="file"', false);

        // No manager/admin download URL
        $response->assertDontSee(
            route('bookings.documents.download', [$booking, $document]),
            false
        );

        // No delete URL
        $response->assertDontSee(
            route('bookings.documents.destroy', [$booking, $document]),
            false
        );
    }

    // -----------------------------------------------------------------------
    // 4. Foreign manager receives HTTP 403
    // -----------------------------------------------------------------------

    public function test_foreign_manager_receives_403_on_booking_show(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $assignedManager = $this->makeUser(Role::MANAGER);
        $foreignManager  = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $assignedManager->id);

        $this->actingAs($foreignManager)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // 5. Document with null uploaded_by renders with safe fallback
    // -----------------------------------------------------------------------

    public function test_document_with_null_uploader_renders_safely_with_fallback(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $manager  = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $manager->id);
        $document = $this->makeDocument($booking, null); // null uploader

        $response = $this->actingAs($manager)
            ->get(route('bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Не указан', false);
    }

    // -----------------------------------------------------------------------
    // 6. Empty booking displays empty-state text
    // -----------------------------------------------------------------------

    public function test_empty_booking_displays_empty_state_text(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id); // no documents

        $response = $this->actingAs($manager)
            ->get(route('bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Документы по этой заявке пока не загружены', false);
    }

    // -----------------------------------------------------------------------
    // 7. Validation feedback on invalid upload
    // -----------------------------------------------------------------------

    public function test_invalid_upload_as_manager_produces_session_errors_and_renders_invalid_feedback(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Submit with all fields missing
        $this->actingAs($manager)
            ->from(route('bookings.show', $booking))
            ->post(route('bookings.documents.store', $booking), [])
            ->assertSessionHasErrors(['title', 'document_type', 'file']);

        // On the subsequent GET, the session still holds the flashed errors,
        // so @error directives render invalid-feedback elements.
        $response = $this->actingAs($manager)
            ->get(route('bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('invalid-feedback', false);
        $response->assertSee('is-invalid', false);
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
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => Booking::STATUS_NEW,
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

    private function makeDocument(Booking $booking, ?int $uploaderId, string $filePath = 'documents/bookings/test/doc.pdf'): BookingDocument
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
