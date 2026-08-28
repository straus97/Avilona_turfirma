<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E1-FINAL-08: маршрут reload-captcha отдаёт одноразовый токен капчи и не должен
 * переотдаваться из кэша ответов (App\Http\Middleware\CacheResponse). Раньше на
 * него навешивалось 'cache.response' — снято точечно, глобальное поведение
 * CacheResponse не меняется.
 */
class CaptchaReloadNotCachedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_reload_captcha_route_has_no_response_cache_middleware(): void
    {
        $route = Route::getRoutes()->getByName('captcha_reload.index');

        $this->assertNotNull($route);
        $this->assertNotContains('cache.response', $route->gatherMiddleware());
        $this->assertSame('reload-captcha', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_routes_file_no_longer_attaches_cache_response_to_reload_captcha(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));

        preg_match('/Route::get\([^;]*?"captcha_reload\.index"[^;]*?\);/s', $routes, $matches);
        $this->assertNotEmpty($matches, 'reload-captcha route definition not found');
        $this->assertStringNotContainsString('cache.response', $matches[0]);
    }

    public function test_reload_captcha_remains_publicly_reachable_and_returns_json(): void
    {
        $response = $this->get(route('captcha_reload.index'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertArrayHasKey('captcha', $response->json());
    }

    public function test_repeated_reload_captcha_requests_are_not_served_from_response_cache(): void
    {
        $this->get(route('captcha_reload.index'))->assertOk();

        // If CacheResponse were active for this route it would have stored the
        // rendered body under a fullUrl-derived key. It must not.
        foreach (['undecided', 'necessary', 'analytics'] as $consentState) {
            $this->assertFalse(Cache::has(url('/reload-captcha') . '|consent:' . $consentState));
        }

        // The endpoint keeps executing the controller (JSON envelope) on every hit.
        $second = $this->get(route('captcha_reload.index'));
        $second->assertOk();
        $this->assertArrayHasKey('captcha', $second->json());
    }
}
