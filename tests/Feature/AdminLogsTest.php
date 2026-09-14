<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E3-A5 — Admin Logs path-disclosure hardening.
 *
 * The Logs page previously echoed the absolute filesystem path
 * (storage_path('logs/laravel.log')) next to the log output. That value has
 * no product value and reveals deployment filesystem structure, so the view
 * now renders a static neutral label ("Журнал приложения") instead. The
 * controller's read-only tail-reading/escaping behavior is unchanged.
 */
class AdminLogsTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_can_open_logs_page(): void
    {
        $admin = $this->createUserWithRole(Role::ADMIN);

        $this->actingAs($admin)->get(route('cabinet.admin.logs'))->assertOk();
    }

    public function test_logs_page_shows_expected_labels(): void
    {
        $admin = $this->createUserWithRole(Role::ADMIN);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringContainsString('Логи системы', $html);
        $this->assertStringContainsString('Последние 200 строк', $html);
        $this->assertStringContainsString('Журнал приложения', $html);
    }

    public function test_logs_page_does_not_expose_absolute_log_file_path(): void
    {
        $admin = $this->createUserWithRole(Role::ADMIN);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString(storage_path('logs/laravel.log'), $html);
        $this->assertStringNotContainsString(storage_path(), $html);
    }

    public function test_logs_page_renders_no_destructive_controls(): void
    {
        $admin = $this->createUserWithRole(Role::ADMIN);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Очистить', $html);
        $this->assertStringNotContainsString('Удалить', $html);
        $this->assertStringNotContainsString('clear-log', $html);
        $this->assertStringNotContainsString('delete-log', $html);
        $this->assertStringNotContainsString('truncate', $html);
        $this->assertStringNotContainsString('download', $html);
    }

    public function test_manager_cannot_access_admin_logs_route(): void
    {
        $manager = $this->createUserWithRole(Role::MANAGER);

        $this->actingAs($manager)->get(route('cabinet.admin.logs'))->assertForbidden();
    }

    public function test_tourist_cannot_access_admin_logs_route(): void
    {
        $tourist = $this->createUserWithRole(Role::TOURIST);

        $this->actingAs($tourist)->get(route('cabinet.admin.logs'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cabinet.admin.logs'))->assertRedirect(route('login'));
    }
}
