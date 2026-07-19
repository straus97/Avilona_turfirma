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

/**
 * Правило: на staff-only маршрутах документов заявки (bookings.documents.*)
 * эффективная роль определяется приоритетом admin > manager > tourist.
 * Пользователь с ролями manager+tourist, владеющий заявкой как турист, но не
 * назначенный её менеджером, не должен получать доступ через ветку
 * tourist-владельца: middleware role:manager,admin пропускает его как
 * менеджера, а authorizeBooking() обязан отклонить его как чужого менеджера.
 */
class BookingDocumentEffectiveRoleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_tourist_owner_not_assigned_cannot_upload_document(): void
    {
        Storage::fake('local');

        $multiRoleOwner  = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $assignedManager = $this->makeUser([Role::MANAGER]);
        $booking         = $this->makeBooking($multiRoleOwner, $assignedManager->id);

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $this->actingAs($multiRoleOwner)
            ->post(route('bookings.documents.store', $booking), [
                'title'         => 'Owner Bypass Attempt',
                'document_type' => 'contract',
                'file'          => $file,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('booking_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty("documents/bookings/{$booking->id}");
    }

    public function test_manager_tourist_owner_not_assigned_cannot_download_document(): void
    {
        Storage::fake('local');

        $multiRoleOwner  = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $assignedManager = $this->makeUser([Role::MANAGER]);
        $booking         = $this->makeBooking($multiRoleOwner, $assignedManager->id);

        $filePath = "documents/bookings/{$booking->id}/voucher.pdf";
        Storage::disk('local')->put($filePath, 'private-booking-document');

        $document = $this->makeDocument($booking, $assignedManager->id, $filePath);

        $this->actingAs($multiRoleOwner)
            ->get(route('bookings.documents.download', [$booking, $document]))
            ->assertForbidden();

        Storage::disk('local')->assertExists($filePath);
    }

    public function test_manager_tourist_owner_not_assigned_cannot_delete_document(): void
    {
        Storage::fake('local');

        $multiRoleOwner  = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $assignedManager = $this->makeUser([Role::MANAGER]);
        $booking         = $this->makeBooking($multiRoleOwner, $assignedManager->id);

        $filePath = "documents/bookings/{$booking->id}/protected.pdf";
        Storage::disk('local')->put($filePath, 'private-booking-document');

        $document = $this->makeDocument($booking, $assignedManager->id, $filePath);

        $this->actingAs($multiRoleOwner)
            ->delete(route('bookings.documents.destroy', [$booking, $document]))
            ->assertForbidden();

        $this->assertDatabaseHas('booking_documents', [
            'id'         => $document->id,
            'deleted_at' => null,
        ]);

        Storage::disk('local')->assertExists($filePath);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeBooking(User $owner, ?int $managerId): Booking
    {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => Booking::STATUS_PROGRESS,
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
