<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 13: Cookie Consent + Analytics Gating.
 *
 * Правило: третий-party аналитика (Яндекс.Метрика, LiveInternet, Top.Mail.Ru)
 * рендерится сервером в HTML только тогда, когда в запросе присутствует
 * валидное first-party cookie avilona_cookie_consent со значением v1_all.
 * Любое отсутствующее/невалидное/устаревшее значение и значение v1_necessary
 * трактуются как отсутствие согласия — в этом случае в ответе не должно быть
 * ни одного из хостов/маркеров аналитики (mc.yandex.ru, counter.yadro.ru,
 * top-fwz1.mail.ru), включая noscript-изображения.
 *
 * Тесты гоняют реальный HTTP-стек (включая App\Http\Middleware\EncryptCookies),
 * а не читают Blade-шаблоны напрямую, чтобы проверить именно то поведение,
 * которое увидит браузер. Cookie согласия передаётся через
 * withUnencryptedCookie(), потому что в реальности его пишет JS напрямую
 * (document.cookie), а не серверный Cookie::queue() — EncryptCookies должен
 * прочитать его как обычный (незашифрованный) cookie благодаря узкому
 * исключению для этого конкретного имени.
 */
class PublicCookieConsentTest extends TestCase
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
    }

    // -----------------------------------------------------------------------
    // 1. Cookie information page
    // -----------------------------------------------------------------------

    public function test_cookies_info_page_renders_expected_heading(): void
    {
        $response = $this->get(route('cookies.info'));

        $response->assertOk();
        $response->assertSee('Использование cookie');
        $response->assertSee(self::COOKIE_NAME);
    }

    public function test_cookies_info_page_links_to_personal_data_policy_pdf(): void
    {
        $response = $this->get(route('cookies.info'));

        $response->assertOk();
        $response->assertSee(
            'documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
            false
        );
    }

    // -----------------------------------------------------------------------
    // 2. First visit, no consent cookie
    // -----------------------------------------------------------------------

    public function test_first_visit_without_consent_shows_banner_and_hides_all_analytics(): void
    {
        Http::preventStrayRequests();

        $response = $this->get(route('home.index'));

        $response->assertOk();

        // Баннер согласия присутствует и видим (нет атрибута hidden рядом с ним).
        $response->assertSee('id="cookie-consent-banner"', false);
        $response->assertSee('Мы используем cookie');
        $this->assertDoesNotMatchRegularExpression(
            '/id="cookie-consent-banner"[^>]*\shidden(?:[\s>]|=)/',
            $response->getContent()
        );

        $this->assertAnalyticsAbsent($response->getContent());
    }

    // -----------------------------------------------------------------------
    // 3. "Только необходимые" (v1_necessary)
    // -----------------------------------------------------------------------

    public function test_necessary_only_consent_keeps_analytics_absent(): void
    {
        Http::preventStrayRequests();

        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_necessary')
            ->get(route('home.index'));

        $response->assertOk();
        $this->assertAnalyticsAbsent($response->getContent());

        // Баннер не должен навязчиво показываться повторно при валидном выборе.
        $this->assertMatchesRegularExpression(
            '/id="cookie-consent-banner"[^>]*\shidden(?:[\s>]|=)/',
            $response->getContent()
        );
    }

    // -----------------------------------------------------------------------
    // 4. "Принять" (v1_all)
    // -----------------------------------------------------------------------

    public function test_full_consent_renders_all_three_existing_analytics_integrations(): void
    {
        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')
            ->get(route('home.index'));

        $response->assertOk();

        // Yandex.Metrika
        $response->assertSee('mc.yandex.ru/metrika/tag.js', false);
        $response->assertSee('ym(56393833', false);
        $response->assertSee('mc.yandex.ru/watch/56393833', false);

        // LiveInternet
        $response->assertSee('counter.yadro.ru', false);

        // Top.Mail.Ru
        $response->assertSee('top-fwz1.mail.ru/js/code.js', false);
        $response->assertSee('_tmr.push({id: "3150807"', false);
        $response->assertSee('top-fwz1.mail.ru/counter?id=3150807;js=na', false);
    }

    public function test_full_consent_hides_banner_by_default(): void
    {
        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')
            ->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('id="cookie-consent-banner"', false);
        // Валидное согласие -> баннер отрисован со скрытым состоянием.
        $this->assertMatchesRegularExpression(
            '/id="cookie-consent-banner"[^>]*\shidden(?:[\s>]|=)/',
            $response->getContent()
        );
    }

    // -----------------------------------------------------------------------
    // 5. Invalid / unknown / malformed consent value
    // -----------------------------------------------------------------------

    public function test_invalid_consent_value_is_treated_as_no_decision(): void
    {
        Http::preventStrayRequests();

        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v2_all')
            ->get(route('home.index'));

        $response->assertOk();

        // Баннер снова видим (нет hidden рядом с ним).
        $this->assertDoesNotMatchRegularExpression(
            '/id="cookie-consent-banner"[^>]*\shidden(?:[\s>]|=)/',
            $response->getContent()
        );

        $this->assertAnalyticsAbsent($response->getContent());
    }

    public function test_empty_consent_value_is_treated_as_no_decision(): void
    {
        Http::preventStrayRequests();

        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, '')
            ->get(route('home.index'));

        $response->assertOk();
        $this->assertAnalyticsAbsent($response->getContent());
    }

    // -----------------------------------------------------------------------
    // 6. Footer cookie-settings action
    // -----------------------------------------------------------------------

    public function test_footer_contains_cookie_settings_action(): void
    {
        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('id="cookie-settings-open"', false);
        $response->assertSee('Настройки cookie');
    }

    // -----------------------------------------------------------------------
    // 7. Static layout contract: EncryptCookies exception is narrow
    // -----------------------------------------------------------------------

    public function test_encrypt_cookies_exception_is_scoped_to_consent_cookie_only(): void
    {
        $reflection = new \ReflectionProperty(\App\Http\Middleware\EncryptCookies::class, 'except');
        $reflection->setAccessible(true);

        $except = $reflection->getValue(new \App\Http\Middleware\EncryptCookies(
            app('encrypter')
        ));

        $this->assertSame(
            [self::COOKIE_NAME],
            array_values($except),
            'EncryptCookies must except exactly avilona_cookie_consent and nothing else.'
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function assertAnalyticsAbsent(string $html): void
    {
        foreach (self::ANALYTICS_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, $html, "Analytics marker [$marker] must be absent without valid v1_all consent.");
        }

        $this->assertStringNotContainsString('informer.yandex.ru', $html);
        $this->assertStringNotContainsString('licntF51C', $html);
        $this->assertStringNotContainsString('top-fwz1.mail.ru', $html);
    }

    // -----------------------------------------------------------------------
    // 8. E1-A1-F3: Google Maps embed gated on the same v1_all threshold
    // -----------------------------------------------------------------------

    private const MAP_OFFICE_ADDRESS = '198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10';

    private function assertMapEmbedAbsentWithPlaceholder(string $html): void
    {
        $this->assertStringNotContainsString('www.google.com/maps/embed', $html);
        $this->assertStringNotContainsString('maps.googleapis.com', $html);
        $this->assertStringContainsString('id="google-map-consent-placeholder"', $html);
        $this->assertStringContainsString(self::MAP_OFFICE_ADDRESS, $html);
        $this->assertStringContainsString('Открыть в Google Картах', $html);
    }

    public function test_first_visit_without_consent_does_not_load_google_maps_embed(): void
    {
        Http::preventStrayRequests();

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $this->assertMapEmbedAbsentWithPlaceholder($response->getContent());
    }

    public function test_necessary_only_consent_does_not_load_google_maps_embed(): void
    {
        Http::preventStrayRequests();

        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_necessary')
            ->get(route('home.index'));

        $response->assertOk();
        $this->assertMapEmbedAbsentWithPlaceholder($response->getContent());
    }

    public function test_full_consent_renders_the_existing_google_maps_embed(): void
    {
        $response = $this->withUnencryptedCookie(self::COOKIE_NAME, 'v1_all')
            ->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('www.google.com/maps/embed', false);
        $response->assertDontSee('id="google-map-consent-placeholder"', false);
    }
}
