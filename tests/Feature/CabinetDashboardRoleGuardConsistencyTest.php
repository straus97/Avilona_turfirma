<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: '/cabinet' (CabinetController::dashboard()) не входит в группу
 * маршрутов с middleware('role:tourist,manager,admin'), которая защищает
 * остальные общие маршруты кабинета (bookings, chat, profile, settings, ...).
 * Поэтому dashboard() обязан применять тот же ролевой guard самостоятельно —
 * пользователь без admin/manager/tourist должен получать 403, а не
 * туристический дашборд по умолчанию.
 */
class CabinetDashboardRoleGuardConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_only_user_is_redirected_to_admin_dashboard(): void
    {
        $user = $this->makeUser([Role::ADMIN]);

        $this->actingAs($user)
            ->get(route('cabinet.dashboard'))
            ->assertRedirect(route('cabinet.admin.dashboard'));
    }

    public function test_manager_only_user_is_redirected_to_manager_dashboard(): void
    {
        $user = $this->makeUser([Role::MANAGER]);

        $this->actingAs($user)
            ->get(route('cabinet.dashboard'))
            ->assertRedirect(route('cabinet.manager.dashboard'));
    }

    public function test_tourist_only_user_receives_tourist_dashboard_view(): void
    {
        $user = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($user)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertViewIs('cabinet.tourist.dashboard');
    }

    public function test_admin_and_manager_user_is_redirected_to_admin_dashboard(): void
    {
        $user = $this->makeUser([Role::ADMIN, Role::MANAGER]);

        $this->actingAs($user)
            ->get(route('cabinet.dashboard'))
            ->assertRedirect(route('cabinet.admin.dashboard'));
    }

    public function test_user_without_roles_is_forbidden(): void
    {
        $user = $this->makeUser([]);

        $this->actingAs($user)
            ->get(route('cabinet.dashboard'))
            ->assertForbidden();
    }

    public function test_user_with_only_unsupported_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['name' => 'auditor'],
            ['description' => 'Auditor - read-only audit access']
        );
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get(route('cabinet.dashboard'))
            ->assertForbidden();
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
}
