<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: приоритет ролей на общих маршрутах кабинета един с dashboard() —
 * admin > manager > tourist. Общие «туристические» маршруты
 * (role:tourist,manager,admin) перенаправляют не-туриста на страницу его роли
 * через CabinetController::redirectIfNotTourist().
 *
 * Пользователь admin+manager — поддерживаемый сценарий; он должен вести себя
 * как администратор на ВСЕХ общих маршрутах, а не расходиться между
 * admin-дашбордом (/cabinet) и manager-страницами (общие маршруты).
 *
 * Покрытие организовано по ВЕТКАМ помощника (bookings, chat±id, profile,
 * settings, default/null, tourist fall-through), а не по каждому call-site:
 * все default/null методы передают одинаковые аргументы одному и тому же
 * помощнику как первый оператор, поэтому одна безопасная точка (/cabinet/wishlist)
 * репрезентативна для всей ветки.
 */
class CabinetRoleRedirectConsistencyTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Tourist fall-through: общие маршруты отдают представление (нет редиректа)
    // -----------------------------------------------------------------------

    public function test_tourist_reaches_shared_pages_without_redirect(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        foreach ([
            route('cabinet.bookings'),
            route('cabinet.chat'),
            route('cabinet.profile'),
            route('cabinet.settings'),
            route('cabinet.wishlist'),
        ] as $url) {
            $this->actingAs($tourist)->get($url)->assertOk();
        }
    }

    // -----------------------------------------------------------------------
    // bookings branch
    // -----------------------------------------------------------------------

    public function test_bookings_branch_precedence(): void
    {
        $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.bookings'))
            ->assertRedirect(route('cabinet.manager.bookings'));

        $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.bookings'))
            ->assertRedirect(route('cabinet.admin.bookings'));

        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.bookings'))
            ->assertRedirect(route('cabinet.admin.bookings'));
    }

    // -----------------------------------------------------------------------
    // chat branch — с сохранением необязательного bookingId
    // -----------------------------------------------------------------------

    public function test_chat_branch_preserves_booking_id(): void
    {
        // Редирект срабатывает в guard-операторе до обращения к БД,
        // поэтому реальная заявка не требуется — важен лишь перенос параметра.
        $bookingId = 777;

        $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.chat', ['bookingId' => $bookingId]))
            ->assertRedirect(route('cabinet.manager.chat', ['bookingId' => $bookingId]));

        $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.chat', ['bookingId' => $bookingId]))
            ->assertRedirect(route('cabinet.admin.chats', ['bookingId' => $bookingId]));

        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.chat', ['bookingId' => $bookingId]))
            ->assertRedirect(route('cabinet.admin.chats', ['bookingId' => $bookingId]));
    }

    // -----------------------------------------------------------------------
    // chat branch — без bookingId (чистый базовый URL)
    // -----------------------------------------------------------------------

    public function test_chat_branch_without_booking_id(): void
    {
        $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.chat'))
            ->assertRedirect(route('cabinet.manager.chat'));

        $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.chat'))
            ->assertRedirect(route('cabinet.admin.chats'));

        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.chat'))
            ->assertRedirect(route('cabinet.admin.chats'));
    }

    // -----------------------------------------------------------------------
    // profile branch (admin-arm ранее отсутствовал → падал на admin.dashboard)
    // -----------------------------------------------------------------------

    public function test_profile_branch_precedence(): void
    {
        $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.profile'))
            ->assertRedirect(route('cabinet.manager.profile'));

        $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.profile'))
            ->assertRedirect(route('cabinet.admin.profile'));

        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.profile'))
            ->assertRedirect(route('cabinet.admin.profile'));
    }

    // -----------------------------------------------------------------------
    // settings branch
    // -----------------------------------------------------------------------

    public function test_settings_branch_precedence(): void
    {
        $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.settings'))
            ->assertRedirect(route('cabinet.manager.settings'));

        $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.settings'))
            ->assertRedirect(route('cabinet.admin.settings'));

        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.settings'))
            ->assertRedirect(route('cabinet.admin.settings'));
    }

    // -----------------------------------------------------------------------
    // default/null branch — репрезентативно через /cabinet/wishlist
    // (без route-model-binding, без валидации, без побочных эффектов)
    // -----------------------------------------------------------------------

    public function test_default_branch_precedence(): void
    {
        $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.wishlist'))
            ->assertRedirect(route('cabinet.manager.dashboard'));

        $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.wishlist'))
            ->assertRedirect(route('cabinet.admin.dashboard'));

        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.wishlist'))
            ->assertRedirect(route('cabinet.admin.dashboard'));
    }

    // -----------------------------------------------------------------------
    // Якорь консистентности: /cabinet dashboard для admin+manager
    // -----------------------------------------------------------------------

    public function test_dashboard_consistency_anchor_for_admin_and_manager(): void
    {
        $this->actingAs($this->makeUser([Role::ADMIN, Role::MANAGER]))
            ->get(route('cabinet.dashboard'))
            ->assertRedirect(route('cabinet.admin.dashboard'));
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
