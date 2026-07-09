<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserDeletionDocumentCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_deletion_removes_owned_document_files(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $uploader = $this->createUserWithRole(Role::MANAGER);

        $records = $this->createOwnedDocuments(
            $owner,
            $uploader,
            'model-delete'
        );

        $owner->delete();

        Storage::disk('local')->assertMissing(
            $records['personal_path']
        );

        Storage::disk('local')->assertMissing(
            $records['booking_path']
        );

        $this->assertDatabaseMissing('users', [
            'id' => $owner->id,
        ]);

        $this->assertDatabaseMissing('user_documents', [
            'id' => $records['personal_document_id'],
        ]);

        $this->assertDatabaseMissing('bookings', [
            'id' => $records['booking_id'],
        ]);

        $this->assertDatabaseMissing('booking_documents', [
            'id' => $records['booking_document_id'],
        ]);
    }

    public function test_tourist_account_deletion_removes_owned_document_files(): void
    {
        Storage::fake('local');

        $owner = $this->createUserWithRole(Role::TOURIST);
        $uploader = $this->createUserWithRole(Role::MANAGER);

        $records = $this->createOwnedDocuments(
            $owner,
            $uploader,
            'tourist-account'
        );

        $this
            ->actingAs($owner)
            ->delete(
                route('cabinet.settings.destroy-account'),
                ['password' => 'password']
            )
            ->assertRedirect(route('home.index'));

        Storage::disk('local')->assertMissing(
            $records['personal_path']
        );

        Storage::disk('local')->assertMissing(
            $records['booking_path']
        );

        $this->assertDatabaseMissing('users', [
            'id' => $owner->id,
        ]);

        $this->assertDatabaseMissing('user_documents', [
            'id' => $records['personal_document_id'],
        ]);

        $this->assertDatabaseMissing('booking_documents', [
            'id' => $records['booking_document_id'],
        ]);
    }

    public function test_admin_user_deletion_removes_owned_document_files(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole(Role::ADMIN);
        $target = $this->createUserWithRole(Role::TOURIST);

        $records = $this->createOwnedDocuments(
            $target,
            $admin,
            'admin-delete'
        );

        $this
            ->actingAs($admin)
            ->delete(
                route(
                    'cabinet.admin.delete-user',
                    $target
                )
            )
            ->assertRedirect();

        Storage::disk('local')->assertMissing(
            $records['personal_path']
        );

        Storage::disk('local')->assertMissing(
            $records['booking_path']
        );

        $this->assertDatabaseMissing('users', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseMissing('user_documents', [
            'id' => $records['personal_document_id'],
        ]);

        $this->assertDatabaseMissing('booking_documents', [
            'id' => $records['booking_document_id'],
        ]);
    }

    /**
     * @return array{
     *     personal_path: string,
     *     booking_path: string,
     *     personal_document_id: int,
     *     booking_document_id: int,
     *     booking_id: int
     * }
     */
    private function createOwnedDocuments(
        User $owner,
        User $uploader,
        string $suffix
    ): array {
        $personalPath =
            'documents/personal/' . $suffix . '.pdf';

        $bookingPath =
            'documents/bookings/' . $suffix . '.pdf';

        Storage::disk('local')->put(
            $personalPath,
            'private-personal-document'
        );

        Storage::disk('local')->put(
            $bookingPath,
            'private-booking-document'
        );

        $personalDocument = UserDocument::query()->create([
            'user_id' => $owner->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $personalPath,
            'file_type' => 'pdf',
            'file_size' => 25,
        ]);

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

        $bookingDocument = BookingDocument::query()->create([
            'booking_id' => $booking->id,
            'document_type' => 'voucher',
            'title' => 'Tour Voucher',
            'file_path' => $bookingPath,
            'file_size' => 24,
            'uploaded_by' => $uploader->id,
        ]);

        return [
            'personal_path' => $personalPath,
            'booking_path' => $bookingPath,
            'personal_document_id' =>
                $personalDocument->id,
            'booking_document_id' =>
                $bookingDocument->id,
            'booking_id' => $booking->id,
        ];
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