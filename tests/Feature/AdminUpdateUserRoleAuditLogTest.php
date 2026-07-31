<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminUpdateUserRoleAuditLogTest extends TestCase
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

    private function currentRoleNames(User $user): array
    {
        $names = $user->roles()->pluck('name')->all();
        sort($names);

        return $names;
    }

    private function ensureRoleExists(string $roleName): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );
    }

    public function test_admin_assigning_a_role_to_a_roleless_user_emits_the_audit_log_as_attach_only(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([]);
        $this->ensureRoleExists(Role::MANAGER);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin updated user role',
                \Mockery::on(function ($context) use ($admin, $target): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'target_user_id', 'added_roles', 'removed_roles', 'resulting_roles']) {
                        return false;
                    }

                    return $context['actor_id'] === $admin->id
                        && $context['target_user_id'] === $target->id
                        && $context['added_roles'] === [Role::MANAGER]
                        && $context['removed_roles'] === []
                        && $context['resulting_roles'] === [Role::MANAGER];
                })
            );

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->post(route('cabinet.admin.user-update-role', $target), [
                'role' => Role::MANAGER,
            ]);

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль пользователя обновлена');

        $this->assertSame([Role::MANAGER], $this->currentRoleNames($target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_replacing_a_role_with_an_already_held_role_emits_the_audit_log_as_detach_only(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::ADMIN, Role::MANAGER]);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin updated user role',
                \Mockery::on(function ($context) use ($admin, $target): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'target_user_id', 'added_roles', 'removed_roles', 'resulting_roles']) {
                        return false;
                    }

                    return $context['actor_id'] === $admin->id
                        && $context['target_user_id'] === $target->id
                        && $context['added_roles'] === []
                        && $context['removed_roles'] === [Role::MANAGER]
                        && $context['resulting_roles'] === [Role::ADMIN];
                })
            );

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->post(route('cabinet.admin.user-update-role', $target), [
                'role' => Role::ADMIN,
            ]);

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль пользователя обновлена');

        $this->assertSame([Role::ADMIN], $this->currentRoleNames($target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_replacing_a_role_with_a_different_role_emits_the_audit_log_as_attach_and_detach(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::MANAGER]);
        $this->ensureRoleExists(Role::TOURIST);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Admin updated user role',
                \Mockery::on(function ($context) use ($admin, $target): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id', 'target_user_id', 'added_roles', 'removed_roles', 'resulting_roles']) {
                        return false;
                    }

                    return $context['actor_id'] === $admin->id
                        && $context['target_user_id'] === $target->id
                        && $context['added_roles'] === [Role::TOURIST]
                        && $context['removed_roles'] === [Role::MANAGER]
                        && $context['resulting_roles'] === [Role::TOURIST];
                })
            );

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->post(route('cabinet.admin.user-update-role', $target), [
                'role' => Role::TOURIST,
            ]);

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль пользователя обновлена');

        $this->assertSame([Role::TOURIST], $this->currentRoleNames($target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_setting_the_role_a_user_already_exclusively_has_does_not_emit_the_audit_log(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $target = $this->createUserWithRoles([Role::TOURIST]);

        Log::shouldReceive('warning')->never();

        $refererUrl = route('cabinet.admin.users');

        $response = $this->actingAs($admin)
            ->from($refererUrl)
            ->post(route('cabinet.admin.user-update-role', $target), [
                'role' => Role::TOURIST,
            ]);

        $response->assertRedirect($refererUrl);
        $response->assertSessionHas('success', 'Роль пользователя обновлена');

        $this->assertSame([Role::TOURIST], $this->currentRoleNames($target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_non_admin_cannot_trigger_the_user_role_update_audit_log(): void
    {
        $nonAdmin = $this->createUserWithRoles([Role::TOURIST]);
        $target = $this->createUserWithRoles([Role::MANAGER]);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($nonAdmin)
            ->post(route('cabinet.admin.user-update-role', $target), [
                'role' => Role::TOURIST,
            ]);

        $response->assertStatus(403);

        $this->assertSame([Role::MANAGER], $this->currentRoleNames($target));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $nonAdmin->id]);
    }
}
