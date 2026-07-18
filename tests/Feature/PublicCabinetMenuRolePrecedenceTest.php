<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: приоритет ролей в раскрывающемся меню авторизованного пользователя
 * на публичных страницах (resources/views/layouts/main.blade.php) должен
 * совпадать с приоритетом на общих маршрутах кабинета — admin > manager > tourist.
 *
 * Пользователь admin+manager — поддерживаемый сценарий; меню должно показывать
 * ему ссылки администратора, а не менеджера.
 */
class PublicCabinetMenuRolePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tourist_only_user_receives_tourist_menu_urls(): void
    {
        $user = $this->makeUser([Role::TOURIST]);

        $html = $this->actingAs($user)->get(route('home.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('cabinet.bookings') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.dashboard') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.settings') . '"', $html);
    }

    public function test_manager_only_user_receives_manager_menu_urls(): void
    {
        $user = $this->makeUser([Role::MANAGER]);

        $html = $this->actingAs($user)->get(route('home.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('cabinet.manager.bookings') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.dashboard') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.settings') . '"', $html);
    }

    public function test_admin_only_user_receives_admin_menu_urls(): void
    {
        $user = $this->makeUser([Role::ADMIN]);

        $html = $this->actingAs($user)->get(route('home.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('cabinet.admin.bookings') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.dashboard') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.settings') . '"', $html);
    }

    public function test_admin_and_manager_user_receives_admin_menu_urls_not_manager(): void
    {
        $user = $this->makeUser([Role::ADMIN, Role::MANAGER]);

        $html = $this->actingAs($user)->get(route('home.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('cabinet.admin.bookings') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.dashboard') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.settings') . '"', $html);

        $this->assertStringNotContainsString('href="' . route('cabinet.manager.bookings') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.dashboard') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.settings') . '"', $html);
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
