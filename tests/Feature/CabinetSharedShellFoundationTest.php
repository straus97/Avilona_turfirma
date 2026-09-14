<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * E3-A1 — защитные проверки общей оболочки кабинета (дизайн-фундамент).
 *
 * Проверяются контракты, а не точная разметка/CSS: набор ссылок сайдбара,
 * доступное состояние текущего пункта, общая область флэш-сообщений для всех
 * существующих ключей контроллеров, видимость ошибок валидации, редирект
 * при обязательной смене пароля и наличие управляющих хуков выдвижного меню.
 */
class CabinetSharedShellFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // A. Оболочка рендерится для всех трёх ролей + landmark / skip-link
    // ------------------------------------------------------------------

    public function test_shared_shell_renders_core_landmarks_for_each_role(): void
    {
        foreach ([
            [Role::TOURIST, 'cabinet.dashboard'],
            [Role::MANAGER, 'cabinet.manager.profile'],
            [Role::ADMIN, 'cabinet.admin.dashboard'],
        ] as [$role, $routeName]) {
            $html = $this->actingAs($this->makeUser([$role]))
                ->get(route($routeName))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('cabinet-skip-link', $html, "skip-link отсутствует для роли {$role}");
            $this->assertStringContainsString('href="#cabinet-main-content"', $html);
            $this->assertStringContainsString('id="cabinet-main-content"', $html);
            $this->assertStringContainsString('<main', $html);
            $this->assertStringContainsString('role="banner"', $html);
        }
    }

    public function test_effective_role_profile_and_settings_links_are_preserved_in_header(): void
    {
        // Общий турист.
        $touristHtml = $this->renderLayoutFor($this->makeUser([Role::TOURIST]));
        $this->assertStringContainsString('href="' . route('cabinet.profile') . '"', $touristHtml);
        $this->assertStringContainsString('href="' . route('cabinet.settings') . '"', $touristHtml);

        // admin+manager ведёт себя как администратор.
        $adminHtml = $this->renderLayoutFor($this->makeUser([Role::ADMIN, Role::MANAGER]));
        $this->assertStringContainsString('href="' . route('cabinet.admin.profile') . '"', $adminHtml);
        // E3-A5: Admin header dropdown merges "Настройки" into "Мой профиль" —
        // it must not carry a second personal settings link.
        $this->assertStringNotContainsString('href="' . route('cabinet.admin.settings') . '"', $adminHtml);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.profile') . '"', $adminHtml);
    }

    // ------------------------------------------------------------------
    // B. Сайдбар каждой роли сохраняет ключевые маршруты
    // ------------------------------------------------------------------

    public function test_tourist_sidebar_keeps_its_key_destinations(): void
    {
        $html = $this->actingAs($this->makeUser([Role::TOURIST]))
            ->get(route('cabinet.dashboard'))->assertOk()->getContent();

        foreach ([
            route('cabinet.dashboard'),
            route('cabinet.bookings'),
            route('cabinet.chat'),
            route('cabinet.documents.personal'),
            route('cabinet.documents.bookings'),
            route('cabinet.bonus'),
            route('cabinet.wishlist'),
            route('cabinet.profile'),
            route('cabinet.settings'),
        ] as $url) {
            $this->assertStringContainsString('href="' . $url . '"', $html);
        }
    }

    public function test_manager_sidebar_keeps_its_key_destinations(): void
    {
        $html = $this->actingAs($this->makeUser([Role::MANAGER]))
            ->get(route('cabinet.manager.profile'))->assertOk()->getContent();

        foreach ([
            route('cabinet.manager.dashboard'),
            route('cabinet.manager.clients'),
            route('cabinet.manager.bookings'),
            route('cabinet.manager.chat'),
            route('cabinet.manager.documents'),
            route('cabinet.manager.statistics'),
            route('cabinet.manager.finance'),
            route('cabinet.manager.content'),
            route('cabinet.manager.profile'),
            route('cabinet.manager.settings'),
        ] as $url) {
            $this->assertStringContainsString('href="' . $url . '"', $html);
        }
    }

    public function test_admin_sidebar_keeps_its_key_destinations(): void
    {
        $html = $this->actingAs($this->makeUser([Role::ADMIN]))
            ->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();

        foreach ([
            route('cabinet.admin.dashboard'),
            route('cabinet.admin.profile'),
            route('cabinet.admin.users'),
            route('cabinet.admin.bookings'),
            route('cabinet.admin.chats'),
            route('cabinet.admin.content'),
            route('cabinet.admin.finance'),
            route('cabinet.admin.bonus'),
            route('cabinet.admin.settings'),
            route('cabinet.admin.logs'),
        ] as $url) {
            $this->assertStringContainsString('href="' . $url . '"', $html);
        }
    }

    // ------------------------------------------------------------------
    // C. Активный пункт сайдбара имеет доступное состояние текущей страницы
    // ------------------------------------------------------------------

    public function test_active_sidebar_destination_exposes_aria_current_page(): void
    {
        $html = $this->actingAs($this->makeUser([Role::TOURIST]))
            ->get(route('cabinet.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-current="page"', $html);

        // Именно ссылка на текущий раздел («Главная») несёт признак и класс active.
        $dashboardHref = preg_quote('href="' . route('cabinet.dashboard') . '"', '/');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*' . $dashboardHref . '[^>]*aria-current="page"/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<a[^>]*' . $dashboardHref . '[^>]*class="[^"]*\bactive\b/s',
            $html
        );
    }

    // ------------------------------------------------------------------
    // D. Общая область флэш-сообщений поддерживает все существующие ключи
    // ------------------------------------------------------------------

    #[DataProvider('flashKeyProvider')]
    public function test_shared_flash_region_renders_each_controller_flash_key(string $key): void
    {
        $message = 'FLASH-MARKER-' . strtoupper($key) . '-UNIQUE';

        $this->actingAs($this->makeUser([Role::TOURIST]))
            ->withSession([$key => $message])
            ->get(route('cabinet.dashboard'))
            ->assertOk()
            ->assertSee($message);
    }

    public static function flashKeyProvider(): array
    {
        return [
            'success' => ['success'],
            'error' => ['error'],
            'status' => ['status'],
            'warning' => ['warning'],
        ];
    }

    // ------------------------------------------------------------------
    // E. Ошибки валидации видны через общую оболочку
    // ------------------------------------------------------------------

    public function test_validation_errors_are_visible_through_shared_shell(): void
    {
        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag([
            'example_field' => 'VALIDATION-MARKER-UNIQUE сообщение об ошибке.',
        ]));

        $this->actingAs($this->makeUser([Role::TOURIST]))
            ->withSession(['errors' => $errors])
            ->get(route('cabinet.dashboard'))
            ->assertOk()
            ->assertSee('VALIDATION-MARKER-UNIQUE сообщение об ошибке.')
            ->assertSee('Проверьте правильность заполнения формы');
    }

    // ------------------------------------------------------------------
    // F. Обязательная смена пароля: редирект с защищённых страниц кабинета,
    //    маршрут выхода остаётся доступным (отражает текущий middleware).
    // ------------------------------------------------------------------

    public function test_password_change_required_user_is_redirected_from_protected_cabinet_pages(): void
    {
        $user = $this->makeUser([Role::TOURIST]);
        $user->forceFill(['password_change_required' => true])->save();

        $this->actingAs($user)
            ->get(route('cabinet.dashboard'))
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->get(route('cabinet.bookings'))
            ->assertRedirect(route('password.change'));

        // Разрешённые «аварийные» маршруты остаются достижимыми.
        $this->actingAs($user)->get(route('password.change'))->assertOk();
        $this->actingAs($user)->post(route('logout'))->assertRedirect();
    }

    // ------------------------------------------------------------------
    // G. Разметка выдвижного меню имеет хуки, нужные Alpine-поведению
    // ------------------------------------------------------------------

    public function test_mobile_drawer_markup_exposes_required_control_hooks(): void
    {
        $html = $this->renderLayoutFor($this->makeUser([Role::TOURIST]));

        $this->assertStringContainsString('sidebarOpen', $html);
        $this->assertStringContainsString('id="cabinet-sidebar"', $html);
        $this->assertStringContainsString('aria-controls="cabinet-sidebar"', $html);
        $this->assertStringContainsString('cabinet-sidebar-backdrop', $html);
        $this->assertStringContainsString('cabinet-no-scroll', $html);
        $this->assertStringContainsString('keydown.escape.window', $html);
    }

    // ------------------------------------------------------------------
    // H. Общий флэш-элемент несёт полную структуру закрытия Bootstrap-alert
    // ------------------------------------------------------------------

    public function test_dismissible_flash_message_carries_bootstrap_alert_dismissal_structure(): void
    {
        $html = $this->actingAs($this->makeUser([Role::TOURIST]))
            ->withSession(['success' => 'DISMISS-MARKER-UNIQUE'])
            ->get(route('cabinet.dashboard'))
            ->assertOk()
            ->getContent();

        // Обёртка сообщения несёт и базовый класс `alert`, и `alert-dismissible`
        // (Bootstrap разрешает цель закрытия через предка `.alert`).
        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="[^"]*\balert\b[^"]*\balert-dismissible\b[^"]*"[^>]*>/s',
            $html
        );
        // И реальную кнопку закрытия с data-bs-dismiss.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="[^"]*\bbtn-close\b[^"]*"[^>]*data-bs-dismiss="alert"/s',
            $html
        );
    }

    // ------------------------------------------------------------------
    // I. Триггер меню пользователя — нативный <button>, а не div[role=button]
    // ------------------------------------------------------------------

    public function test_header_user_dropdown_trigger_is_a_native_button(): void
    {
        $html = $this->renderLayoutFor($this->makeUser([Role::MANAGER]));

        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="[^"]*\bheader-user\b[^"]*"[^>]*data-bs-toggle="dropdown"/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div[^>]*\bheader-user\b[^>]*role="button"/s',
            $html
        );

        // Назначения меню сохранены (эффективная роль — менеджер).
        $this->assertStringContainsString('href="' . route('cabinet.manager.profile') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.settings') . '"', $html);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function renderLayoutFor(User $user): string
    {
        Auth::login($user);

        return view('cabinet.layouts.app')->render();
    }

    /**
     * @param string[] $roleNames
     */
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
