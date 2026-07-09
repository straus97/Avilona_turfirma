<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_owner_can_download_a_private_booking_document(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $uploader = $this->createUserWithRole(Role::MANAGER);
        $booking = $this->createBookingFor($owner);

        Storage::disk('local')->put(
            'documents/bookings/owner-voucher.pdf',
            'private-booking-document'
        );

        $document = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'document_type' => 'voucher',
            'title' => 'Tour Voucher',
            'file_path' =>
                'documents/bookings/owner-voucher.pdf',
            'file_size' => 24,
            'uploaded_by' => $uploader->id,
        ]);

        $this
            ->actingAs($owner)
            ->get(
                route(
                    'cabinet.documents.bookings.download',
                    [$booking, $document]
                )
            )
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_other_tourist_cannot_download_booking_document(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $intruder = $this->createUserWithRole(Role::TOURIST);
        $booking = $this->createBookingFor($owner);

        Storage::disk('local')->put(
            'documents/bookings/private-ticket.pdf',
            'private-booking-document'
        );

        $document = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'document_type' => 'tickets',
            'title' => 'Private Ticket',
            'file_path' =>
                'documents/bookings/private-ticket.pdf',
            'file_size' => 24,
            'uploaded_by' => $owner->id,
        ]);

        $this
            ->actingAs($intruder)
            ->get(
                route(
                    'cabinet.documents.bookings.download',
                    [$booking, $document]
                )
            )
            ->assertForbidden();
    }

    public function test_booking_document_download_rejects_mismatched_booking(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $booking = $this->createBookingFor($owner);
        $otherBooking = $this->createBookingFor($owner);

        Storage::disk('local')->put(
            'documents/bookings/mismatched.pdf',
            'private-booking-document'
        );

        $document = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'document_type' => 'other',
            'title' => 'Mismatched Document',
            'file_path' =>
                'documents/bookings/mismatched.pdf',
            'file_size' => 24,
            'uploaded_by' => $owner->id,
        ]);

        $this
            ->actingAs($owner)
            ->get(
                route(
                    'cabinet.documents.bookings.download',
                    [$otherBooking, $document]
                )
            )
            ->assertNotFound();
    }

    public function test_missing_private_booking_document_returns_not_found(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $booking = $this->createBookingFor($owner);

        $document = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'document_type' => 'insurance',
            'title' => 'Missing Insurance',
            'file_path' =>
                'documents/bookings/missing-insurance.pdf',
            'file_size' => 24,
            'uploaded_by' => $owner->id,
        ]);

        $this
            ->actingAs($owner)
            ->get(
                route(
                    'cabinet.documents.bookings.download',
                    [$booking, $document]
                )
            )
            ->assertNotFound();
    }

    private function createBookingFor(User $user): Booking
    {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id' => $user->id,
                'status' => Booking::STATUS_NEW,
                'departure_city' => 'Saint Petersburg',
                'destination_country' => 'Tunisia',
                'destination_city' => 'Hammamet',
                'start_date' => '2026-07-20',
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
            ])
        );
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            [
                'description' =>
                    Role::availableRoles()[$roleName]
                    ?? $roleName,
            ]
        );

        $user = User::factory()->create();

        $user->roles()->attach($role->id);

        return $user;
    }
}