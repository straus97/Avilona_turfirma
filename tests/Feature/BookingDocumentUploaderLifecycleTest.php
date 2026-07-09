<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDocumentUploaderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_uploader_preserves_booking_document_and_file(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $uploader = $this->createUserWithRole(Role::MANAGER);

        $booking = Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id' => $owner->id,
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

        $path =
            'documents/bookings/uploader-lifecycle.pdf';

        Storage::disk('local')->put(
            $path,
            'private-booking-document'
        );

        $document = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'document_type' => 'voucher',
            'title' => 'Tour Voucher',
            'file_path' => $path,
            'file_size' => 24,
            'uploaded_by' => $uploader->id,
        ]);

        $uploader->delete();

        $this->assertDatabaseMissing('users', [
            'id' => $uploader->id,
        ]);

        $this->assertDatabaseHas('booking_documents', [
            'id' => $document->id,
            'booking_id' => $booking->id,
            'uploaded_by' => null,
            'deleted_at' => null,
        ]);

        Storage::disk('local')->assertExists($path);

        $freshDocument = $document->fresh();

        $this->assertNotNull($freshDocument);
        $this->assertNull($freshDocument->uploaded_by);
    }

    private function createUserWithRole(
        string $roleName
    ): User {
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