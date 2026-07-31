<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagerDestroyAccountAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $roleName, string $password): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_manager_self_deletion_emits_the_audit_log_exactly_once_with_expected_context(): void
    {
        Storage::fake('local');

        $password = 'correct-password';
        $manager = $this->createUserWithRole(Role::MANAGER, $password);
        $managerId = $manager->id;

        $bookingOwner = User::factory()->create();

        $booking = Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id' => $bookingOwner->id,
                'manager_id' => $manager->id,
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

        $documentPath = 'documents/personal/manager-passport.pdf';
        Storage::disk('local')->put($documentPath, 'private-personal-document');

        $document = UserDocument::query()->create([
            'user_id' => $manager->id,
            'name' => 'Passport',
            'document_type' => 'passport',
            'file_path' => $documentPath,
            'file_type' => 'pdf',
            'file_size' => 26,
        ]);
        $documentId = $document->id;

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'User deleted own account via manager settings',
                \Mockery::on(function ($context) use ($managerId): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'actor_roles']) {
                        return false;
                    }

                    return $context['actor_id'] === $managerId
                        && $context['actor_roles'] === [Role::MANAGER];
                })
            );

        $response = $this->actingAs($manager)
            ->delete(route('cabinet.manager.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertRedirect(route('home.index'));
        $response->assertSessionHas('status', 'Аккаунт удален.');

        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['id' => $managerId]);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'manager_id' => null,
        ]);

        $this->assertDatabaseMissing('user_documents', ['id' => $documentId]);

        Storage::disk('local')->assertMissing($documentPath);
    }

    public function test_admin_using_manager_settings_self_deletion_emits_the_audit_log_with_admin_role_context(): void
    {
        $password = 'correct-password';
        $admin = $this->createUserWithRole(Role::ADMIN, $password);
        $adminId = $admin->id;

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'User deleted own account via manager settings',
                \Mockery::on(function ($context) use ($adminId): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'actor_roles']) {
                        return false;
                    }

                    return $context['actor_id'] === $adminId
                        && $context['actor_roles'] === [Role::ADMIN];
                })
            );

        $response = $this->actingAs($admin)
            ->delete(route('cabinet.manager.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertRedirect(route('home.index'));
        $response->assertSessionHas('status', 'Аккаунт удален.');

        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['id' => $adminId]);
    }

    public function test_incorrect_password_does_not_emit_the_audit_log_and_does_not_delete_the_account(): void
    {
        $password = 'correct-password';
        $manager = $this->createUserWithRole(Role::MANAGER, $password);
        $settingsUrl = route('cabinet.manager.settings');

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($manager)
            ->from($settingsUrl)
            ->delete(route('cabinet.manager.destroy-account'), [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('password');
        $response->assertRedirect($settingsUrl);

        $this->assertAuthenticatedAs($manager);

        $this->assertDatabaseHas('users', ['id' => $manager->id]);
    }

    public function test_unauthorized_tourist_cannot_trigger_the_manager_settings_self_deletion_audit_log(): void
    {
        $password = 'correct-password';
        $tourist = $this->createUserWithRole(Role::TOURIST, $password);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($tourist)
            ->delete(route('cabinet.manager.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertStatus(403);

        $this->assertAuthenticatedAs($tourist);

        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
    }
}
