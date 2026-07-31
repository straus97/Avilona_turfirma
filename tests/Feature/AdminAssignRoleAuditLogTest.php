<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminAssignRoleAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = $this->ensureRoleExists($roleName);

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function ensureRoleExists(string $roleName): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );
    }

    private function currentRoleNames(User $user): array
    {
        $names = $user->roles()->pluck('name')->all();
        sort($names);

        return $names;
    }

    public function test_admin_assigning_a_new_role_to_a_user_with_an_existing_role_emits_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::TOURIST]);
        $this->ensureRoleExists(Role::MANAGER);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin assigned user role',
                \Mockery::on(function ($context) use ($admin, $target): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'target_user_id', 'assigned_role']) {
                        return false;
                    }

                    return $context['actor_id'] === $admin->id
                        && $context['target_user_id'] === $target->id
                        && $context['assigned_role'] === Role::MANAGER;
                })
            );

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->post(route('cabinet.admin.assign-role', $target->id), [
                'role' => Role::MANAGER,
            ]);

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль успешно назначена');

        $this->assertSame([Role::MANAGER, Role::TOURIST], $this->currentRoleNames($target));
        $this->assertCount(2, $target->roles()->get());
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_assigning_a_role_the_user_already_has_does_not_emit_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::TOURIST]);

        Log::shouldReceive('warning')->never();

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->post(route('cabinet.admin.assign-role', $target->id), [
                'role' => Role::TOURIST,
            ]);

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль успешно назначена');

        $this->assertSame([Role::TOURIST], $this->currentRoleNames($target));
        $this->assertCount(1, $target->roles()->get());
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_non_admin_cannot_trigger_the_role_assignment_audit_log(): void
    {
        $nonAdmin = $this->createUserWithRoles([Role::TOURIST]);
        $target = $this->createUserWithRoles([Role::MANAGER]);
        $this->ensureRoleExists(Role::TOURIST);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($nonAdmin)
            ->post(route('cabinet.admin.assign-role', $target->id), [
                'role' => Role::TOURIST,
            ]);

        $response->assertStatus(403);

        $this->assertSame([Role::MANAGER], $this->currentRoleNames($target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $nonAdmin->id]);
    }
}
