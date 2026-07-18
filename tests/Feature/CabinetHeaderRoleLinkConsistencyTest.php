<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Правило: ссылки «Мой профиль» и «Настройки» в выпадающем меню шапки кабинета
 * (resources/views/cabinet/layouts/app.blade.php) должны вести напрямую на
 * маршруты, соответствующие эффективной роли пользователя — тот же приоритет,
 * что уже используется для отображения названия роли рядом: admin > manager > tourist.
 *
 * Пользователь admin+manager — поддерживаемый сценарий; шапка должна вести
 * себя как для администратора, а не для менеджера или общего туриста.
 */
class CabinetHeaderRoleLinkConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tourist_only_user_receives_shared_header_urls(): void
    {
        $user = $this->makeUser([Role::TOURIST]);

        $html = $this->renderHeader($user);

        $this->assertStringContainsString('href="' . route('cabinet.profile') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.settings') . '"', $html);
    }

    public function test_manager_only_user_receives_manager_header_urls(): void
    {
        $user = $this->makeUser([Role::MANAGER]);

        $html = $this->renderHeader($user);

        $this->assertStringContainsString('href="' . route('cabinet.manager.profile') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.settings') . '"', $html);

        $this->assertStringNotContainsString('href="' . route('cabinet.profile') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.settings') . '"', $html);
    }

    public function test_admin_only_user_receives_admin_header_urls(): void
    {
        $user = $this->makeUser([Role::ADMIN]);

        $html = $this->renderHeader($user);

        $this->assertStringContainsString('href="' . route('cabinet.admin.profile') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.settings') . '"', $html);

        $this->assertStringNotContainsString('href="' . route('cabinet.manager.profile') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.settings') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.profile') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.settings') . '"', $html);
    }

    public function test_admin_and_manager_user_receives_admin_header_urls_not_manager_or_shared(): void
    {
        $user = $this->makeUser([Role::ADMIN, Role::MANAGER]);

        $html = $this->renderHeader($user);

        $this->assertStringContainsString('href="' . route('cabinet.admin.profile') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.settings') . '"', $html);

        $this->assertStringNotContainsString('href="' . route('cabinet.manager.profile') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.settings') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.profile') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.settings') . '"', $html);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function renderHeader(User $user): string
    {
        Auth::login($user);

        return view('cabinet.layouts.app')->render();
    }

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
