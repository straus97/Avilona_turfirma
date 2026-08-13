<?php

namespace Tests\Feature;

use App\Http\Middleware\CacheResponse;
use App\Support\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Stage 13: изоляция кэша ответов (App\Http\Middleware\CacheResponse) по
 * нормализованному состоянию согласия на cookie.
 *
 * Правило: до этого изменения CacheResponse кэшировало полный рендер по
 * $request->fullUrl() без учёта cookie, поэтому ответ, отрисованный для
 * одного состояния согласия, мог быть воспроизведён для посетителя с другим
 * состоянием на том же URL в течение 60 минут. Теперь ключ кэша включает
 * ровно одно из трёх нормализованных состояний из App\Support\CookieConsent
 * (analytics / necessary / undecided) — сырое значение cookie в ключ не
 * попадает никогда, а любое отсутствующее/пустое/неизвестное/несовпадающее
 * по версии значение нормализуется в undecided.
 *
 * Contact\IndexController и HelpfulInformation\DictionaryController ранее
 * дополнительно кэшировали готовый HTML под фиксированными ключами
 * ('contacts_page', 'travel_dictionary_page') независимо от согласия — этот
 * внутренний слой удалён, и они полагаются на внешний consent-aware
 * CacheResponse.
 */
class CacheResponseConsentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE_NAME = 'avilona_cookie_consent';

    private const ANALYTICS_MARKERS = [
        'mc.yandex.ru',
        'counter.yadro.ru',
        'top-fwz1.mail.ru',
        'ym(56393833',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    // -----------------------------------------------------------------------
    // Scenario 7 — normalizer contract (direct, no HTTP)
    // -----------------------------------------------------------------------

    public function test_normalizer_maps_v1_all_to_analytics(): void
    {
        $this->assertSame(CookieConsent::STATE_ANALYTICS, CookieConsent::normalize('v1_all'));
        $this->assertTrue(CookieConsent::allowsAnalytics('v1_all'));
        $this->assertTrue(CookieConsent::isValid('v1_all'));
    }

    public function test_normalizer_maps_v1_necessary_to_necessary(): void
    {
        $this->assertSame(CookieConsent::STATE_NECESSARY, CookieConsent::normalize('v1_necessary'));
        $this->assertFalse(CookieConsent::allowsAnalytics('v1_necessary'));
        $this->assertTrue(CookieConsent::isValid('v1_necessary'));
    }

    public function test_normalizer_maps_missing_and_invalid_values_to_undecided(): void
    {
        foreach ([null, '', 'invalid', 'v0_all', 'v2_all', 'V1_ALL', 'v1_all '] as $rawValue) {
            $this->assertSame(
                CookieConsent::STATE_UNDECIDED,
                CookieConsent::normalize($rawValue),
                'Expected [' . var_export($rawValue, true) . '] to normalize to undecided'
            );
            $this->assertFalse(CookieConsent::allowsAnalytics($rawValue));
            $this->assertFalse(CookieConsent::isValid($rawValue));
        }
    }

    // -----------------------------------------------------------------------
    // Scenario 1 — analytics cached first, undecided second, same URL
    // -----------------------------------------------------------------------

    public function test_analytics_cached_response_is_not_replayed_to_an_undecided_visitor(): void
    {
        $first = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('awards.index'));
        $first->assertOk();
        $this->assertAnalyticsPresent($first->getContent());

        $this->clearConsentCookie();
        $second = $this->get(route('awards.index'));
        $second->assertOk();
        $this->assertAnalyticsAbsent($second->getContent());
        $second->assertSee('id="cookie-consent-banner"', false);
    }

    // -----------------------------------------------------------------------
    // Scenario 2 — undecided cached first, analytics second, same URL
    // -----------------------------------------------------------------------

    public function test_undecided_cached_response_does_not_suppress_analytics_for_a_consenting_visitor(): void
    {
        $first = $this->get(route('awards.index'));
        $first->assertOk();
        $this->assertAnalyticsAbsent($first->getContent());

        $second = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('awards.index'));
        $second->assertOk();
        $this->assertAnalyticsPresent($second->getContent());
    }

    // -----------------------------------------------------------------------
    // Scenario 3 — necessary vs analytics isolation, both directions
    // -----------------------------------------------------------------------

    public function test_analytics_then_necessary_is_isolated_on_the_same_url(): void
    {
        $first = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('awards.index'));
        $first->assertOk();
        $this->assertAnalyticsPresent($first->getContent());

        $second = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_necessary')->get(route('awards.index'));
        $second->assertOk();
        $this->assertAnalyticsAbsent($second->getContent());
    }

    public function test_necessary_then_analytics_is_isolated_on_the_same_url(): void
    {
        $first = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_necessary')->get(route('awards.index'));
        $first->assertOk();
        $this->assertAnalyticsAbsent($first->getContent());

        $second = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('awards.index'));
        $second->assertOk();
        $this->assertAnalyticsPresent($second->getContent());
    }

    public function test_necessary_consent_round_trip_has_no_analytics_and_does_not_autoshow_banner(): void
    {
        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_necessary')->get(route('awards.index'));

        $response->assertOk();
        $this->assertAnalyticsAbsent($response->getContent());
        $this->assertMatchesRegularExpression(
            '/id="cookie-consent-banner"[^>]*\shidden(?:[\s>]|=)/',
            $response->getContent()
        );
    }

    // -----------------------------------------------------------------------
    // Scenario 4 — invalid consent normalizes into the undecided bucket
    // -----------------------------------------------------------------------

    public function test_invalid_consent_shares_the_undecided_cache_bucket_and_does_not_leak_into_analytics(): void
    {
        $invalid = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v2_all')->get(route('awards.index'));
        $invalid->assertOk();
        $this->assertAnalyticsAbsent($invalid->getContent());
        $invalid->assertSee('id="cookie-consent-banner"', false);

        // Отсутствие cookie должно попасть в тот же bucket, что и невалидное значение.
        $this->clearConsentCookie();
        $undecided = $this->get(route('awards.index'));
        $undecided->assertOk();
        $this->assertAnalyticsAbsent($undecided->getContent());
        $this->assertDoesNotMatchRegularExpression(
            '/id="cookie-consent-banner"[^>]*\shidden(?:[\s>]|=)/',
            $undecided->getContent()
        );

        // Bucket невалидного значения не должен был случайно слиться с analytics bucket.
        $consented = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('awards.index'));
        $consented->assertOk();
        $this->assertAnalyticsPresent($consented->getContent());
    }

    // -----------------------------------------------------------------------
    // Scenario 5 — Contact double-cache regression (both directions)
    // -----------------------------------------------------------------------

    public function test_contacts_undecided_then_analytics_no_longer_replays_stale_consent(): void
    {
        $undecided = $this->get(route('contact.index'));
        $undecided->assertOk();
        $this->assertAnalyticsAbsent($undecided->getContent());

        $consented = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('contact.index'));
        $consented->assertOk();
        $this->assertAnalyticsPresent($consented->getContent());
    }

    public function test_contacts_analytics_then_undecided_no_longer_replays_stale_consent(): void
    {
        $consented = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('contact.index'));
        $consented->assertOk();
        $this->assertAnalyticsPresent($consented->getContent());

        $this->clearConsentCookie();
        $undecided = $this->get(route('contact.index'));
        $undecided->assertOk();
        $this->assertAnalyticsAbsent($undecided->getContent());
    }

    // -----------------------------------------------------------------------
    // Scenario 6 — Travel dictionary double-cache regression (both directions)
    // -----------------------------------------------------------------------

    public function test_travel_dictionary_undecided_then_analytics_no_longer_replays_stale_consent(): void
    {
        $undecided = $this->get(route('travel_dictionary.index'));
        $undecided->assertOk();
        $this->assertAnalyticsAbsent($undecided->getContent());

        $consented = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('travel_dictionary.index'));
        $consented->assertOk();
        $this->assertAnalyticsPresent($consented->getContent());
    }

    public function test_travel_dictionary_analytics_then_undecided_no_longer_replays_stale_consent(): void
    {
        $consented = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')->get(route('travel_dictionary.index'));
        $consented->assertOk();
        $this->assertAnalyticsPresent($consented->getContent());

        $this->clearConsentCookie();
        $undecided = $this->get(route('travel_dictionary.index'));
        $undecided->assertOk();
        $this->assertAnalyticsAbsent($undecided->getContent());
    }

    // -----------------------------------------------------------------------
    // Static source regression: inner rendered-HTML caches are gone
    // -----------------------------------------------------------------------

    public function test_contact_controller_no_longer_caches_rendered_html_directly(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Contact/IndexController.php'));

        $this->assertStringNotContainsString('contacts_page', $controller);
        $this->assertStringNotContainsString('Cache::', $controller);
    }

    public function test_dictionary_controller_no_longer_caches_rendered_html_directly(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HelpfulInformation/DictionaryController.php'));

        $this->assertStringNotContainsString('travel_dictionary_page', $controller);
        $this->assertStringNotContainsString('Cache::', $controller);
    }

    // -----------------------------------------------------------------------
    // Scenario 8 — query-string dimension preserved alongside consent state
    // -----------------------------------------------------------------------

    public function test_cache_key_still_distinguishes_query_strings_alongside_consent_state(): void
    {
        $reflection = $this->cacheKeyMethod();
        $middleware = new CacheResponse();

        $baseKey = $reflection->invoke($middleware, Request::create('https://example.test/countries', 'GET'));
        $filteredKey = $reflection->invoke(
            $middleware,
            Request::create('https://example.test/countries', 'GET', ['category' => 'Азия'])
        );

        $this->assertNotSame($baseKey, $filteredKey);
        $this->assertStringContainsString('|consent:undecided', $baseKey);
        $this->assertStringContainsString('|consent:undecided', $filteredKey);
    }

    public function test_cache_key_varies_by_normalized_consent_state_and_never_contains_the_raw_cookie_value(): void
    {
        $reflection = $this->cacheKeyMethod();
        $middleware = new CacheResponse();

        $undecidedRequest = Request::create('https://example.test/countries', 'GET');
        $undecidedRequest->cookies->set(self::COOKIE_NAME, 'totally-arbitrary-raw-value-987');

        $analyticsRequest = Request::create('https://example.test/countries', 'GET');
        $analyticsRequest->cookies->set(self::COOKIE_NAME, 'v1_all');

        $necessaryRequest = Request::create('https://example.test/countries', 'GET');
        $necessaryRequest->cookies->set(self::COOKIE_NAME, 'v1_necessary');

        $undecidedKey = $reflection->invoke($middleware, $undecidedRequest);
        $analyticsKey = $reflection->invoke($middleware, $analyticsRequest);
        $necessaryKey = $reflection->invoke($middleware, $necessaryRequest);

        $this->assertStringContainsString('|consent:undecided', $undecidedKey);
        $this->assertStringContainsString('|consent:analytics', $analyticsKey);
        $this->assertStringContainsString('|consent:necessary', $necessaryKey);

        $this->assertNotSame($undecidedKey, $analyticsKey);
        $this->assertNotSame($undecidedKey, $necessaryKey);
        $this->assertNotSame($analyticsKey, $necessaryKey);

        // Сырое значение cookie не должно попадать в ключ буквально.
        $this->assertStringNotContainsString('totally-arbitrary-raw-value-987', $undecidedKey);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * withUnencryptedCookie()/withCookie() записывают в $this->defaultCookies
     * / $this->unencryptedCookies и НЕ сбрасываются автоматически между
     * последующими вызовами $this->get() в рамках одного теста. Без явной
     * очистки "второй запрос без cookie" в реальности продолжил бы отправлять
     * cookie, выставленное первым запросом в этом же тесте.
     */
    private function clearConsentCookie(): void
    {
        unset($this->defaultCookies[self::COOKIE_NAME]);
        unset($this->unencryptedCookies[self::COOKIE_NAME]);
    }

    private function cacheKeyMethod(): ReflectionMethod
    {
        $reflection = new ReflectionMethod(CacheResponse::class, 'cacheKey');
        $reflection->setAccessible(true);

        return $reflection;
    }

    private function assertAnalyticsPresent(string $html): void
    {
        foreach (self::ANALYTICS_MARKERS as $marker) {
            $this->assertStringContainsString(
                $marker,
                $html,
                "Expected analytics marker [$marker] to be present for valid v1_all consent."
            );
        }
    }

    private function assertAnalyticsAbsent(string $html): void
    {
        foreach (self::ANALYTICS_MARKERS as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $html,
                "Analytics marker [$marker] must be absent without valid v1_all consent."
            );
        }
    }
}
