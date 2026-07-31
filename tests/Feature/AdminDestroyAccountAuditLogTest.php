<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminDestroyAccountAuditLogTest extends TestCase
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

    public function test_admin_self_deletion_emits_the_audit_log_exactly_once_with_expected_context(): void
    {
        $password = 'correct-password';
        $admin = $this->createUserWithRole(Role::ADMIN, $password);
        $adminId = $admin->id;

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin deleted own account',
                \Mockery::on(function ($context) use ($adminId): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id']) {
                        return false;
                    }

                    return $context['actor_id'] === $adminId;
                })
            );

        $response = $this->actingAs($admin)
            ->delete(route('cabinet.admin.destroy-account'), [
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
        $admin = $this->createUserWithRole(Role::ADMIN, $password);
        $profileUrl = route('cabinet.admin.profile');

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($admin)
            ->from($profileUrl)
            ->delete(route('cabinet.admin.destroy-account'), [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('password');
        $response->assertRedirect($profileUrl);

        $this->assertAuthenticatedAs($admin);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_trigger_the_admin_self_deletion_audit_log(): void
    {
        $password = 'correct-password';
        $tourist = $this->createUserWithRole(Role::TOURIST, $password);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($tourist)
            ->delete(route('cabinet.admin.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertStatus(403);

        $this->assertAuthenticatedAs($tourist);

        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
    }
}
