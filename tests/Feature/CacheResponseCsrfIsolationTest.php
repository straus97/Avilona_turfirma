<?php

namespace Tests\Feature;

use App\Http\Middleware\CacheResponse;
use App\Support\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * NOV-PUBLIC-01: страница с формой несёт CSRF-токен сессии посетителя. Общий кэш
 * ответов (CacheResponse) не должен переотдавать её другому посетителю, иначе
 * его POST (на /contacts) получит чужой токен и будет отклонён (HTTP 419).
 * Страницы без токена по-прежнему кэшируются.
 */
class CacheResponseCsrfIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function cacheKey(string $url): string
    {
        return $url . '|consent:' . CookieConsent::STATE_UNDECIDED;
    }

    private function formToken(string $html): ?string
    {
        return preg_match('/name="_token" value="([^"]+)"/', $html, $m) === 1 ? $m[1] : null;
    }

    public function test_contacts_page_is_rendered_per_visitor_with_their_own_csrf_token(): void
    {
        $first = $this->get(route('contact.index'));
        $first->assertOk();
        $firstToken = $this->formToken($first->getContent());
        $this->assertNotNull($firstToken);

        $this->flushSession();

        $second = $this->get(route('contact.index'));
        $second->assertOk();
        $secondToken = $this->formToken($second->getContent());

        $this->assertSame(session()->token(), $secondToken, 'the form must carry the current visitor token');
        $this->assertNotSame($firstToken, $secondToken, 'a token must never be replayed to another visitor');
    }

    public function test_token_bearing_response_is_not_stored_in_the_response_cache(): void
    {
        $this->get(route('contact.index'))->assertOk();

        $this->assertFalse(Cache::has($this->cacheKey(route('contact.index'))));
    }

    public function test_token_free_public_page_is_still_cached(): void
    {
        $this->get(route('awards.index'))->assertOk();

        $this->assertTrue(Cache::has($this->cacheKey(route('awards.index'))));
    }

    public function test_every_response_cached_page_is_free_of_the_session_token(): void
    {
        foreach (['about_company.index', 'employees.index', 'awards.index', 'countries.index', 'destination.index',
            'for_our_clients.index', 'travel_dictionary.index', 'contact.index'] as $name) {
            $response = $this->get(route($name));
            $response->assertOk();
            $cached = Cache::get($this->cacheKey(route($name)));

            if ($cached !== null) {
                $this->assertStringNotContainsString(session()->token(), $cached, "{$name} cached a session token");
            }
        }
    }

    public function test_cache_response_middleware_declares_the_token_guard(): void
    {
        $this->assertTrue(method_exists(CacheResponse::class, 'handle'));
        $this->assertTrue((new \ReflectionClass(CacheResponse::class))->hasMethod('containsSessionToken'));
    }
}
