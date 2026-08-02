<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ManagerDestroyAccountPartialFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_deleting_exception_preserves_manager_booking_personal_document_and_file(): void
    {
        Storage::fake('local');

        $password = 'correct-password';

        $role = Role::query()->firstOrCreate(
            ['name' => Role::MANAGER],
            ['description' => Role::availableRoles()[Role::MANAGER]]
        );

        $manager = User::factory()->create([
            'email' => 'partial-failure-manager@example.com',
            'password' => bcrypt($password),
        ]);
        $manager->roles()->attach($role->id);
        $managerId = $manager->id;

        $bookingOwner = User::factory()->create();

        $booking = Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id' => $bookingOwner->id,
                'manager_id' => $managerId,
                'status' => Booking::STATUS_PROGRESS,
                'departure_city' => 'Saint Petersburg',
                'destination_country' => 'Tunisia',
                'destination_city' => 'Hammamet',
                'start_date' => '2026-08-20',
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
            ])
        );
        $bookingId = $booking->id;
        $originalManagerId = $booking->manager_id;

        $documentPath = 'documents/personal/manager-partial-failure-passport.pdf';

        $document = UserDocument::query()->create([
            'user_id' => $managerId,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $documentPath,
            'file_type' => 'pdf',
            'file_size' => 26,
        ]);
        $documentId = $document->id;

        Storage::disk('local')->put($documentPath, 'private-personal-document');

        $this->assertDatabaseHas('users', ['id' => $managerId]);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'manager_id' => $originalManagerId,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('user_documents', [
            'id' => $documentId,
            'user_id' => $managerId,
        ]);

        Storage::disk('local')->assertExists($documentPath);

        Log::shouldReceive('warning')->never();

        User::deleting(function (User $user) use ($managerId): void {
            if ($user->id !== $managerId) {
                return;
            }

            throw new RuntimeException('forced manager user deletion failure');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($manager)
                ->delete(route('cabinet.manager.destroy-account'), [
                    'password' => $password,
                ]);

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced manager user deletion failure', $e->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $managerId]);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'manager_id' => $originalManagerId,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('user_documents', [
            'id' => $documentId,
            'user_id' => $managerId,
        ]);

        Storage::disk('local')->assertExists($documentPath);
    }
}
