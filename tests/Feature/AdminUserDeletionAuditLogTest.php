<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminUserDeletionAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    public function test_admin_deleting_a_user_emits_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::TOURIST, Role::MANAGER]);

        // Deterministic, alphabetically sorted expectation matching the
        // controller's orderBy('name') query.
        $expectedRoles = [Role::MANAGER, Role::TOURIST];

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin deleted user account',
                \Mockery::on(function ($context) use ($admin, $target, $expectedRoles): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'target_user_id', 'target_user_roles']) {
                        return false;
                    }

                    return $context['actor_id'] === $admin->id
                        && $context['target_user_id'] === $target->id
                        && $context['target_user_roles'] === $expectedRoles;
                })
            );

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->delete(route('cabinet.admin.delete-user', $target));

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Пользователь успешно удален');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_self_delete_attempt_does_not_emit_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        Log::shouldReceive('warning')->never();

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->delete(route('cabinet.admin.delete-user', $admin));

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('error', 'Вы не можете удалить свой собственный аккаунт');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_trigger_the_user_deletion_audit_log(): void
    {
        $nonAdmin = $this->createUserWithRoles([Role::TOURIST]);
        $target = $this->createUserWithRoles([Role::TOURIST]);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($nonAdmin)
            ->delete(route('cabinet.admin.delete-user', $target));

        $response->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $nonAdmin->id]);
    }
}
