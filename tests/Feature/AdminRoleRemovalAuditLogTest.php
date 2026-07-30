<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminRoleRemovalAuditLogTest extends TestCase
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

    private function roleModel(string $roleName): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );
    }

    public function test_admin_removing_an_attached_role_emits_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::MANAGER]);
        $managerRole = $this->roleModel(Role::MANAGER);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin removed user role',
                \Mockery::on(function ($context) use ($admin, $target): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'target_user_id', 'removed_role']) {
                        return false;
                    }

                    return $context['actor_id'] === $admin->id
                        && $context['target_user_id'] === $target->id
                        && $context['removed_role'] === Role::MANAGER;
                })
            );

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->delete(route('cabinet.admin.remove-role', [$target, $managerRole]));

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль успешно удалена');

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $target->id,
            'role_id' => $managerRole->id,
        ]);

        $adminRole = $this->roleModel(Role::ADMIN);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
        ]);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_removing_a_role_not_attached_to_target_does_not_emit_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::TOURIST]);
        $managerRole = $this->roleModel(Role::MANAGER);

        Log::shouldReceive('warning')->never();

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->delete(route('cabinet.admin.remove-role', [$target, $managerRole]));

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль успешно удалена');

        $this->assertDatabaseHas('users', ['id' => $target->id]);

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $target->id,
            'role_id' => $managerRole->id,
        ]);
    }

    public function test_non_admin_cannot_trigger_the_role_removal_audit_log(): void
    {
        $nonAdmin = $this->createUserWithRoles([Role::TOURIST]);
        $target = $this->createUserWithRoles([Role::MANAGER]);
        $managerRole = $this->roleModel(Role::MANAGER);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($nonAdmin)
            ->delete(route('cabinet.admin.remove-role', [$target, $managerRole]));

        $response->assertStatus(403);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'role_id' => $managerRole->id,
        ]);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $nonAdmin->id]);
    }
}
