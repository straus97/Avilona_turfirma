<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Проверяет, что общий компонент <x-application-logo> и гостевой layout
 * (логин/регистрация) используют утверждённый растровый логотип
 * public/img/logo.png вместо устаревшего logo_s.png, сохраняют его
 * естественные пропорции на странице входа и не отбрасывают атрибуты,
 * переданные вызывающим кодом (например, компактный размер в навигации).
 */
class ApplicationLogoConsistencyTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Страница входа успешно рендерится
    // -----------------------------------------------------------------------

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // 2. Страница входа содержит утверждённый URL logo.png
    // -----------------------------------------------------------------------

    public function test_login_page_references_approved_logo(): void
    {
        $response = $this->get('/login');

        $response->assertSee(asset('img/logo.png'), false);
    }

    // -----------------------------------------------------------------------
    // 3. Устаревший logo_s.png больше не упоминается на странице входа
    // -----------------------------------------------------------------------

    public function test_login_page_does_not_reference_obsolete_logo(): void
    {
        $response = $this->get('/login');

        $response->assertDontSee('logo_s.png');
    }

    // -----------------------------------------------------------------------
    // 4. Логотип содержит корректный русскоязычный alt-текст
    // -----------------------------------------------------------------------

    public function test_login_page_logo_has_expected_alt_text(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Логотип Авилона');
    }

    // -----------------------------------------------------------------------
    // 5. Гостевой layout сохраняет пропорции: без старого квадрата,
    //    с автоматической шириной и ограниченной максимальной шириной
    // -----------------------------------------------------------------------

    public function test_guest_layout_preserves_natural_logo_proportions(): void
    {
        $response = $this->get('/login');

        $response->assertDontSee('height="150px" width="150px"', false);
        $response->assertSee('height: 150px; width: auto; max-width: 100%;', false);
    }

    // -----------------------------------------------------------------------
    // 6. Компонент прокидывает переданные вызывающим кодом атрибуты
    // -----------------------------------------------------------------------

    public function test_component_forwards_caller_supplied_attributes(): void
    {
        $rendered = Blade::render('<x-application-logo data-test-marker="logo-attr-forwarded" />');

        $this->assertStringContainsString('data-test-marker="logo-attr-forwarded"', $rendered);
    }

    // -----------------------------------------------------------------------
    // 7. Страница регистрации получает тот же утверждённый логотип
    //    через общий гостевой layout
    // -----------------------------------------------------------------------

    public function test_register_page_renders_and_references_approved_logo(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee(asset('img/logo.png'), false);
        $response->assertDontSee('logo_s.png');
    }
}
