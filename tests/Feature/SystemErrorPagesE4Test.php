<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * E4-B: безопасные брендированные системные страницы ошибок.
 *
 * Проверяются тело/фолбэки страниц ошибок; статусные контракты авторизации и
 * throttle покрыты существующими тестами. Тестовые маршруты регистрируются
 * только внутри рантайма теста.
 */
class SystemErrorPagesE4Test extends TestCase
{
    private const SENTINEL = 'E4B_SENTINEL_SECRET_MESSAGE_7f3a';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::middleware('web')->group(function () {
            Route::get('/__e4b/401', fn () => abort(401, self::SENTINEL));
            Route::get('/__e4b/402', fn () => abort(402, self::SENTINEL));
            Route::get('/__e4b/403', fn () => abort(403, self::SENTINEL));
            Route::get('/__e4b/419', fn () => throw new TokenMismatchException(self::SENTINEL));
            Route::get('/__e4b/429', fn () => abort(429, self::SENTINEL));
            Route::get('/__e4b/500', fn () => throw new RuntimeException(self::SENTINEL));
            Route::get('/__e4b/500-sql', fn () => throw new QueryException(
                'sqlite',
                'select * from e4b_sentinel_table where secret = ?',
                [self::SENTINEL],
                new RuntimeException(self::SENTINEL)
            ));
            Route::get('/__e4b/418', fn () => abort(418, self::SENTINEL));
            Route::get('/__e4b/502', fn () => abort(502, self::SENTINEL));
        });

        // Без web-middleware: страница не должна требовать сессию/аутентификацию.
        Route::get('/__e4b/503', fn () => abort(503, self::SENTINEL));
    }

    private function assertBrandedErrorPage($response, int $status, string $heading): void
    {
        $response->assertStatus($status);

        $html = $response->getContent();

        $response->assertSee('<html lang="ru">', false);
        $this->assertSame(1, substr_count($html, '<h1'));
        $response->assertSee($heading);
        $response->assertSee('Ошибка');
        $response->assertSee('href="' . route('home.index') . '"', false);
        $response->assertSee('href="' . route('contact.index') . '"', false);

        // Не страница-«оболочка» фреймворка и без внешних зависимостей.
        $response->assertDontSee('Unauthorized');
        $response->assertDontSee('Payment Required');
        $response->assertDontSee('Forbidden');
        $response->assertDontSee('Page Expired');
        $response->assertDontSee('Too Many Requests');
        $response->assertDontSee('Server Error');
        $response->assertDontSee('Service Unavailable');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('http-equiv="refresh"', $html);
        $this->assertStringNotContainsString('cdn.', $html);

        $this->assertNoLeak($html);
    }

    private function assertNoLeak(string $html): void
    {
        foreach ([
            self::SENTINEL,
            'e4b_sentinel_table',
            'RuntimeException',
            'QueryException',
            'TokenMismatchException',
            'Stack trace',
            'Illuminate\\',
            'SystemErrorPagesE4Test',
            'vendor/laravel',
            'vendor\\laravel',
            base_path(),
            'APP_ENV',
            'APP_DEBUG',
            'APP_KEY',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "Утечка внутренних данных: {$needle}");
        }
    }

    public function test_401_renders_exact_branded_page_with_login_link(): void
    {
        $response = $this->get('/__e4b/401');

        $this->assertBrandedErrorPage($response, 401, 'Требуется авторизация');
        $response->assertSee('href="' . route('login') . '"', false);
        $response->assertDontSee('Не удалось обработать запрос');
    }

    public function test_402_renders_exact_neutral_page_without_payment_semantics(): void
    {
        $response = $this->get('/__e4b/402');

        $this->assertBrandedErrorPage($response, 402, 'Не удалось выполнить запрос');
        $response->assertDontSee('оплат');
        $response->assertDontSee('платёж');
        $response->assertDontSee('платеж');
        $response->assertDontSee('Не удалось обработать запрос');
    }

    public function test_403_renders_branded_russian_page_without_leaks(): void
    {
        $response = $this->get('/__e4b/403');

        $this->assertBrandedErrorPage($response, 403, 'Доступ запрещён');
        $response->assertSee('нет прав');
    }

    public function test_419_renders_session_expired_page_with_recovery_links(): void
    {
        $response = $this->get('/__e4b/419');

        $this->assertBrandedErrorPage($response, 419, 'Сессия истекла');
        $response->assertSee('Откройте страницу заново');
        $response->assertSee('href="' . route('login') . '"', false);
    }

    public function test_429_renders_retry_later_page_and_preserves_status(): void
    {
        $response = $this->get('/__e4b/429');

        $this->assertBrandedErrorPage($response, 429, 'Слишком много запросов');
        $response->assertSee('Подождите немного');
    }

    public function test_500_hides_exception_details(): void
    {
        $response = $this->get('/__e4b/500');

        $this->assertBrandedErrorPage($response, 500, 'Что-то пошло не так');
        $response->assertDontSee('.php');
    }

    public function test_500_from_database_exception_hides_sql_and_bindings(): void
    {
        $response = $this->get('/__e4b/500-sql');

        $this->assertBrandedErrorPage($response, 500, 'Что-то пошло не так');
        $response->assertDontSee('select *');
        $response->assertDontSee('sqlite');
    }

    public function test_503_renders_standalone_page_without_session_or_auth(): void
    {
        $response = $this->get('/__e4b/503');

        $this->assertBrandedErrorPage($response, 503, 'Сервис временно недоступен');
        $response->assertSee('зайдите чуть позже');
        $response->assertCookieMissing(config('session.cookie'));
    }

    public function test_layout_does_not_depend_on_main_layouts_or_auth(): void
    {
        $dir = resource_path('views/errors');

        foreach (['layout', '401', '402', '403', '419', '429', '500', '503', '4xx', '5xx'] as $name) {
            // Комментарии Blade не считаются кодом.
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents("{$dir}/{$name}.blade.php"));

            $this->assertStringNotContainsString("layouts.main", $source, $name);
            $this->assertStringNotContainsString('cabinet.layouts', $source, $name);
            $this->assertStringNotContainsString('Auth::', $source, $name);
            $this->assertStringNotContainsString('auth()', $source, $name);
            $this->assertStringNotContainsString('session(', $source, $name);
            $this->assertStringNotContainsString('csrf_', $source, $name);
            $this->assertStringNotContainsString('$exception->getMessage', $source, $name);
        }
    }

    public function test_generic_4xx_fallback_is_used_for_status_without_dedicated_view(): void
    {
        $response = $this->get('/__e4b/418');

        $this->assertBrandedErrorPage($response, 418, 'Не удалось обработать запрос');
        $response->assertSee('418');
    }

    public function test_generic_5xx_fallback_is_used_for_status_without_dedicated_view(): void
    {
        $response = $this->get('/__e4b/502');

        $this->assertBrandedErrorPage($response, 502, 'Сервис временно недоступен');
        $response->assertSee('502');
    }

    public function test_method_not_allowed_uses_generic_4xx_fallback(): void
    {
        // POST на GET-only маршрут -> 405 без выделенного шаблона.
        $response = $this->post('/__e4b/418');

        $response->assertStatus(405);
        $response->assertSee('<html lang="ru">', false);
        $response->assertSee('Не удалось обработать запрос');
        $this->assertNoLeak($response->getContent());
    }

    public function test_existing_404_still_uses_dedicated_view(): void
    {
        $response = $this->get('/this-route-does-not-exist-e4-b');

        $response->assertStatus(404);
        $response->assertSee('Ой! Похоже, вы заблудились', false);
        $response->assertDontSee('Не удалось обработать запрос');
        $response->assertDontSee('class="err-card"', false);
    }
}
