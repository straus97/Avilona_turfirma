<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * E3-A5 — Admin Profile / Settings IA merge.
 *
 * Strategy A (approved): personal password + notification preferences live on
 * Admin Profile ("Мой профиль"); Admin Settings becomes the System page
 * ("Система") with only system diagnostics and cache management. Route names
 * (cabinet.admin.settings, cabinet.admin.settings.password,
 * cabinet.admin.settings.notifications) are unchanged — only the rendered
 * page/label and the two successful-redirect targets moved.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['name' => Role::ADMIN],
            ['description' => Role::availableRoles()[Role::ADMIN] ?? Role::ADMIN]
        );

        $admin->roles()->attach($role->id);

        return $admin;
    }

    // -----------------------------------------------------------------------
    // Settings/System page — system diagnostics and cache only
    // -----------------------------------------------------------------------

    public function test_settings_page_renders_for_admin(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk();
    }

    public function test_settings_page_shows_system_diagnostics(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();

        $this->assertStringContainsString('PHP версия:', $html);
        $this->assertStringContainsString('Laravel версия:', $html);
        $this->assertStringContainsString('Окружение:', $html);
        $this->assertStringContainsString('Режим отладки:', $html);
        $this->assertStringContainsString('Драйвер сессий:', $html);
        $this->assertStringContainsString('Драйвер очередей:', $html);
    }

    public function test_cache_block_shows_driver_without_misleading_enabled_disabled_status(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();

        $this->assertStringContainsString('Драйвер кэша: ' . config('cache.default'), $html);
        $this->assertStringNotContainsString('Статус: Включен', $html);
        $this->assertStringNotContainsString('Статус: Отключен', $html);
        $this->assertStringNotContainsString('>Включен</span>', $html);
        $this->assertStringNotContainsString('>Отключен</span>', $html);
    }

    public function test_cache_form_targets_correct_route_and_keeps_confirmation(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('cabinet.admin.clear-cache') . '"', $html);
        $this->assertStringContainsString('onsubmit="return confirm(', $html);
    }

    public function test_quick_commands_block_is_removed(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Быстрые команды', $html);
        $this->assertStringNotContainsString('php artisan cache:clear', $html);
        $this->assertStringNotContainsString('php artisan config:clear', $html);
        $this->assertStringNotContainsString('php artisan route:clear', $html);
        $this->assertStringNotContainsString('php artisan view:clear', $html);
        $this->assertStringNotContainsString('php artisan optimize', $html);
    }

    public function test_settings_page_no_longer_renders_password_form(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();

        $this->assertStringNotContainsString('action="' . route('cabinet.admin.settings.password') . '"', $html);
        $this->assertStringNotContainsString('name="current_password"', $html);
    }

    public function test_settings_page_no_longer_renders_notification_controls(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();

        $this->assertStringNotContainsString('action="' . route('cabinet.admin.settings.notifications') . '"', $html);
        $this->assertStringNotContainsString('name="email_notifications"', $html);
        $this->assertStringNotContainsString('name="booking_updates"', $html);
        $this->assertStringNotContainsString('name="new_messages"', $html);
    }

    // -----------------------------------------------------------------------
    // Admin sidebar — "Система" label for the settings route, no "Настройки"
    // -----------------------------------------------------------------------

    public function test_admin_sidebar_labels_the_settings_destination_as_system_not_settings(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();

        $settingsHref = preg_quote('href="' . route('cabinet.admin.settings') . '"', '/');
        $this->assertMatchesRegularExpression(
            '/<a[^>]*' . $settingsHref . '[^>]*>[\s\S]{0,120}?<span>Система<\/span>/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]*' . $settingsHref . '[^>]*>[\s\S]{0,120}?<span>Настройки<\/span>/',
            $html
        );
    }

    /**
     * Browser-QA regression: a stale render once showed "УПРАВЛЕНИЕ /
     * Пользователи" twice in the Admin sidebar on the System page (once as a
     * lone item right after "Мой профиль", then again as the full section).
     * Current source and compiled output were verified not to reproduce it,
     * but this pins the exact contract — exactly one management section and
     * exactly one link to each destination — on both entry points (System
     * and Profile) so any regression here fails loudly instead of relying on
     * a whole-page snapshot.
     */
    public function test_admin_sidebar_has_no_duplicate_management_section_on_system_or_profile_pages(): void
    {
        $admin = $this->createAdmin();

        $systemHtml = $this->actingAs($admin)->get(route('cabinet.admin.settings'))->assertOk()->getContent();
        $profileHtml = $this->actingAs($admin)->get(route('cabinet.admin.profile'))->assertOk()->getContent();

        foreach (['System' => $systemHtml, 'Profile' => $profileHtml] as $page => $html) {
            $this->assertSame(
                1,
                substr_count($html, '<div class="menu-section-title">Управление</div>'),
                "Expected exactly one \"Управление\" sidebar section on the Admin {$page} page."
            );
            $this->assertSame(
                1,
                substr_count($html, 'href="' . route('cabinet.admin.users') . '"'),
                "Expected exactly one sidebar link to \"Пользователи\" on the Admin {$page} page."
            );
            $this->assertSame(
                1,
                substr_count($html, 'href="' . route('cabinet.admin.settings') . '"'),
                "Expected exactly one sidebar link to \"Система\" on the Admin {$page} page."
            );
        }
    }

    // -----------------------------------------------------------------------
    // Admin Profile page — personal account center
    // -----------------------------------------------------------------------

    public function test_profile_page_renders_for_admin(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('cabinet.admin.profile'))->assertOk();
    }

    public function test_profile_page_contains_password_form_targeting_correct_route(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.profile'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('cabinet.admin.settings.password') . '"', $html);
    }

    public function test_profile_page_contains_notifications_form_targeting_correct_route_and_renders_three_toggles(): void
    {
        $admin = $this->createAdmin();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.profile'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('cabinet.admin.settings.notifications') . '"', $html);
        $this->assertStringContainsString('name="email_notifications"', $html);
        $this->assertStringContainsString('name="booking_updates"', $html);
        $this->assertStringContainsString('name="new_messages"', $html);
    }

    // -----------------------------------------------------------------------
    // Behavior: persistence, validation and redirects are unchanged except
    // for the successful-submission redirect target (settings -> profile).
    // -----------------------------------------------------------------------

    public function test_notification_preferences_persist_and_redirect_to_profile(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('cabinet.admin.settings.notifications'), [
                'booking_updates' => '1',
            ])
            ->assertRedirect(route('cabinet.admin.profile'));

        $settings = json_decode($admin->fresh()->notification_settings, true);

        $this->assertFalse($settings['email_notifications']);
        $this->assertTrue($settings['booking_updates']);
        $this->assertFalse($settings['new_messages']);
    }

    public function test_password_update_rejects_incorrect_current_password(): void
    {
        $admin = $this->createAdmin();
        $admin->password = Hash::make('correct-password-123');
        $admin->save();

        $this->actingAs($admin)->post(route('cabinet.admin.settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-strong-password-456',
            'password_confirmation' => 'new-strong-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('correct-password-123', $admin->fresh()->password));
    }

    public function test_password_update_accepts_correct_current_password_and_redirects_to_profile(): void
    {
        $admin = $this->createAdmin();
        $admin->password = Hash::make('correct-password-123');
        $admin->save();

        $this->actingAs($admin)->post(route('cabinet.admin.settings.password'), [
            'current_password' => 'correct-password-123',
            'password' => 'new-strong-password-456',
            'password_confirmation' => 'new-strong-password-456',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cabinet.admin.profile'));

        $this->assertTrue(Hash::check('new-strong-password-456', $admin->fresh()->password));
    }

    public function test_cache_clear_action_only_flushes_array_cache_store_in_test_environment(): void
    {
        $admin = $this->createAdmin();

        $this->assertSame('array', config('cache.default'));

        $this->actingAs($admin)
            ->post(route('cabinet.admin.clear-cache'))
            ->assertRedirect();
    }
}
