<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\UserFormMail;
use App\Models\Article;
use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\News;
use App\Models\OurClient;
use App\Support\Seo\PublicSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * NOV-PUBLIC-02: метаданные публичных страниц, JSON-LD, robots/noindex и
 * безопасность автоответа формы. Только синтетические данные; письма не
 * отправляются (Mail::fake).
 */
class PublicSeoMetadataReadinessTest extends TestCase
{
    use RefreshDatabase;

    private const STATIC_PATHS = [
        '/', '/tours', '/contacts', '/countries', '/destinations', '/reviews',
        '/helpful_information/news', '/helpful_information/interesting_articles',
        '/helpful_information/for_our_clients', '/helpful_information/travel_dictionary',
        '/company/about_company', '/company/employees', '/company/awards',
        '/cookies', '/personal-data-consent',
    ];

    private function xpath(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }

    private function meta(\DOMXPath $x, string $query): string
    {
        $nodes = $x->query($query);
        $this->assertSame(1, $nodes->length, "expected exactly one node for {$query}");

        return $nodes->item(0)->getAttribute('content') ?: $nodes->item(0)->getAttribute('href');
    }

    /** @return array<string, string> */
    private function createDetailFixtures(): array
    {
        Countries_image::create(['title' => 'Страна Альфа', 'slug' => 'alfa', 'category' => 'Европа',
            'description' => '<p>Страна Альфа расположена на берегу моря.</p>', 'image_large' => null, 'image_small' => null]);
        Destination_image::create(['title' => 'Курорт Бета', 'slug' => 'beta', 'description' => '<p>Курорт для спокойного отдыха.</p>']);
        Article::create(['title' => 'Статья Гамма', 'slug' => 'gamma', 'content' => '<p>Первый абзац статьи.</p>', 'image' => null]);
        News::create(['title' => 'Новость Дельта', 'slug' => 'delta', 'link' => 'https://example.com/d',
            'description' => 'Короткая новость.', 'image' => null, 'pub_date' => now()]);
        OurClient::create(['title' => 'Материал Эпсилон', 'slug' => 'epsilon', 'content' => '<p>Материал для клиентов.</p>', 'image' => null]);

        return [
            '/countries/alfa', '/destinations/beta', '/helpful_information/interesting_articles/gamma',
            '/helpful_information/news/delta', '/helpful_information/for_our_clients/epsilon',
        ];
    }

    public function test_every_public_page_has_exactly_one_of_each_seo_tag_and_unique_title_and_description(): void
    {
        $paths = array_merge(self::STATIC_PATHS, $this->createDetailFixtures());
        $titles = [];
        $descriptions = [];

        foreach ($paths as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $x = $this->xpath($response->getContent());

            $this->assertSame(1, $x->query('//title')->length, "{$path}: one <title>");
            $title = trim($x->query('//title')->item(0)->textContent);
            $description = $this->meta($x, '//meta[@name="description"]');
            $canonical = $this->meta($x, '//link[@rel="canonical"]');

            $this->assertNotSame('', $title, $path);
            $this->assertNotSame('', $description, $path);
            $this->assertStringStartsWith('https://avilona.ru', $canonical, $path);
            $this->assertSame($canonical, $this->meta($x, '//meta[@property="og:url"]'), $path);
            $this->assertNotSame('', $this->meta($x, '//meta[@property="og:title"]'), $path);
            $this->assertNotSame('', $this->meta($x, '//meta[@property="og:description"]'), $path);
            $this->assertNotSame('', $this->meta($x, '//meta[@name="twitter:title"]'), $path);
            $this->assertNotSame('', $this->meta($x, '//meta[@name="twitter:description"]'), $path);
            $this->assertStringStartsWith('https://', $this->meta($x, '//meta[@property="og:image"]'), $path);
            $this->assertSame('index, follow', $this->meta($x, '//meta[@name="robots"]'), $path);
            $response->assertHeaderMissing('X-Robots-Tag');
            $this->assertSame(0, $x->query('//meta[@name="keywords"]')->length, "{$path}: no meta keywords");
            $this->assertSame(1, $x->query('//h1')->length, "{$path}: exactly one H1");

            $this->assertArrayNotHasKey($title, $titles, "duplicate <title> on {$path} and " . ($titles[$title] ?? ''));
            $this->assertArrayNotHasKey($description, $descriptions, "duplicate description on {$path} and " . ($descriptions[$description] ?? ''));
            $titles[$title] = $path;
            $descriptions[$description] = $path;
        }
    }

    public function test_social_title_and_description_follow_the_page_when_not_set_explicitly(): void
    {
        foreach (['/', '/tours', '/countries', '/destinations', '/helpful_information/travel_dictionary'] as $path) {
            $x = $this->xpath($this->get($path)->getContent());

            $this->assertSame(
                trim($x->query('//title')->item(0)->textContent),
                $this->meta($x, '//meta[@property="og:title"]'),
                $path
            );
            $this->assertSame(
                $this->meta($x, '//meta[@name="description"]'),
                $this->meta($x, '//meta[@property="og:description"]'),
                $path
            );
        }
    }

    public function test_dynamic_descriptions_come_from_real_content_and_contain_no_boilerplate(): void
    {
        $this->createDetailFixtures();

        $x = $this->xpath($this->get('/countries/alfa')->getContent());
        $description = $this->meta($x, '//meta[@name="description"]');

        $this->assertSame('Страна Альфа. Страна Альфа расположена на берегу моря.', $description);
        $this->assertStringNotContainsString('Узнайте больше', $description);
        $this->assertStringNotContainsString('<', $description);
    }

    public function test_empty_content_falls_back_to_a_neutral_description_with_no_invented_facts(): void
    {
        Article::create(['title' => 'Пустая статья', 'slug' => 'empty', 'content' => '', 'image' => null]);

        $x = $this->xpath($this->get('/helpful_information/interesting_articles/empty')->getContent());

        $this->assertSame(
            'Пустая статья — статья о путешествиях на сайте туристической фирмы «Авилона» (Санкт-Петербург).',
            $this->meta($x, '//meta[@name="description"]')
        );
    }

    public function test_long_content_is_cut_on_a_sentence_or_word_without_a_hard_160_limit(): void
    {
        $sentence = 'Это довольно длинное предложение о путешествии по тёплому морю и интересным городам.';
        $long = str_repeat($sentence . ' ', 12);

        $description = PublicSeo::describe($long, 'fallback');

        $this->assertGreaterThan(160, mb_strlen($description), 'no hard 160-character cut');
        $this->assertLessThanOrEqual(PublicSeo::DESCRIPTION_SOFT_LENGTH + 1, mb_strlen($description));
        $this->assertMatchesRegularExpression('/[.…]$/u', $description);
        $this->assertSame('fallback', PublicSeo::describe(" <p> </p> ", 'fallback'));
        $this->assertSame('fallback', PublicSeo::describe(null, 'fallback'));
    }

    public function test_database_values_are_escaped_in_head_metadata(): void
    {
        News::create(['title' => '<script>alert(1)</script> "кавычки" & <b>теги</b>', 'slug' => 'xss',
            'link' => 'https://example.com/x', 'description' => '<img src=x onerror=alert(2)> Текст "в кавычках"',
            'image' => null, 'pub_date' => now()]);

        $html = $this->get('/helpful_information/news/xss')->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $head);
        $this->assertStringNotContainsString('<img src=x', $head);
        $this->assertStringNotContainsString('onerror=alert(2)>', $head);
        $this->assertStringNotContainsString('&amp;amp;', $head, 'no double escaping');

        $x = $this->xpath($html);
        $this->assertSame(1, $x->query('//meta[@name="description"]')->length);
        $this->assertStringContainsString('"в кавычках"', $this->meta($x, '//meta[@name="description"]'));
    }

    public function test_pagination_page_gets_a_distinct_title_exactly_once(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            News::create(['title' => 'N' . $i, 'slug' => 'n' . $i, 'link' => 'https://example.com/' . $i,
                'description' => 'd', 'image' => null, 'pub_date' => now()->subDays($i)]);
        }

        $page1 = $this->get('/helpful_information/news')->getContent();
        $page2 = $this->get('/helpful_information/news?page=2')->getContent();

        preg_match('/<title>(.*?)<\/title>/su', $page1, $t1);
        preg_match('/<title>(.*?)<\/title>/su', $page2, $t2);

        $this->assertNotSame(trim($t1[1]), trim($t2[1]));
        $this->assertSame(1, substr_count(mb_strtolower($t2[1]), 'страница'));

        // description страницы 2 отличается от первой и тоже несёт номер ровно один раз
        $d1 = $this->meta($this->xpath($page1), '//meta[@name="description"]');
        $d2 = $this->meta($this->xpath($page2), '//meta[@name="description"]');
        $this->assertNotSame($d1, $d2);
        $this->assertStringEndsWith(' Страница 2.', $d2);
        $this->assertSame(1, substr_count($d2, 'Страница'));
        $this->assertStringNotContainsString('страница', mb_strtolower($t1[1]));
        $this->assertSame('https://avilona.ru/helpful_information/news?page=2', $this->meta($this->xpath($page2), '//link[@rel="canonical"]'));
    }

    public function test_filtered_list_urls_canonicalise_to_the_unfiltered_list(): void
    {
        Countries_image::create(['title' => 'A', 'slug' => 'a', 'category' => 'Европа', 'description' => 'd']);

        $x = $this->xpath($this->get('/countries?category=%D0%95%D0%B2%D1%80%D0%BE%D0%BF%D0%B0&title=x')->getContent());

        $this->assertSame('https://avilona.ru/countries', $this->meta($x, '//link[@rel="canonical"]'));
    }

    public function test_og_image_uses_only_valid_https_or_existing_local_assets(): void
    {
        $fixtures = [
            [null, PublicSeo::defaultImageUrl()],
            ['img/does-not-exist-9a8b7c.jpg', PublicSeo::defaultImageUrl()],
            ['/img/does-not-exist-9a8b7c.jpg', PublicSeo::defaultImageUrl()],
            ['img/logo.png', 'https://avilona.ru/img/logo.png'],
            ['/img/logo.png', 'https://avilona.ru/img/logo.png'],
            ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/a.jpg'],
            ['http://insecure.example.com/a.jpg', PublicSeo::defaultImageUrl()],
            ['javascript:alert(1)', PublicSeo::defaultImageUrl()],
            ['//evil.example.com/a.jpg', PublicSeo::defaultImageUrl()],
            ['../.env', PublicSeo::defaultImageUrl()],
        ];

        foreach ($fixtures as [$stored, $expected]) {
            $this->assertSame($expected, PublicSeo::imageUrl($stored, PublicSeo::defaultImageUrl()), (string) $stored);
        }

        Countries_image::create(['title' => 'Img', 'slug' => 'img', 'category' => 'Европа', 'description' => 'd',
            'image_large' => 'https://cdn.example.com/country.jpg']);

        $x = $this->xpath($this->get('/countries/img')->getContent());
        $this->assertSame('https://cdn.example.com/country.jpg', $this->meta($x, '//meta[@property="og:image"]'));
        $this->assertSame('https://cdn.example.com/country.jpg', $this->meta($x, '//meta[@name="twitter:image"]'));
    }

    public function test_404_page_is_noindex_and_sends_the_404_status(): void
    {
        $response = $this->get('/definitely-not-a-page-9f8e');

        $response->assertNotFound();
        $x = $this->xpath($response->getContent());
        $this->assertSame('noindex, follow', $this->meta($x, '//meta[@name="robots"]'));
    }

    public function test_noindex_flag_protects_non_production_environments_at_header_and_meta_level(): void
    {
        config(['seo.noindex' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame('noindex, nofollow', $this->meta($this->xpath($response->getContent()), '//meta[@name="robots"]'));
        $this->get('/sitemap.xml')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_business_identity_json_ld_is_valid_and_limited_to_confirmed_facts(): void
    {
        foreach (['/', '/contacts'] as $path) {
            $html = $this->get($path)->getContent();

            $this->assertSame(1, substr_count($html, 'application/ld+json'), "{$path}: one JSON-LD block");
            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/su', $html, $m);
            $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('https://schema.org', $data['@context']);
            $this->assertSame('TravelAgency', $data['@type']);
            $this->assertSame('https://avilona.ru/#organization', $data['@id']);
            $this->assertSame('ООО «Авилона»', $data['legalName']);
            $this->assertSame('avilonatur@bk.ru', $data['email']);
            $this->assertSame('https://avilona.ru/img/logo.png', $data['logo']);
            $this->assertSame('198261', $data['address']['postalCode']);
            $this->assertSame('ул. Генерала Симоняка, д. 10', $data['address']['streetAddress']);
            $this->assertSame('Санкт-Петербург', $data['address']['addressLocality']);

            // регистрационный адрес (квартира) не используется как адрес офиса
            foreach (['198302', 'Морской', 'Пехоты', 'кв.'] as $registered) {
                $this->assertStringNotContainsString($registered, $m[1]);
            }
            // ничего неподтверждённого
            foreach (['geo', 'openingHours', 'openingHoursSpecification', 'aggregateRating', 'review', 'priceRange',
                      'sameAs', 'award', 'hasCredential'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $data);
            }
        }

        foreach (['/countries', '/tours', '/company/about_company'] as $path) {
            $this->assertStringNotContainsString('application/ld+json', $this->get($path)->getContent(), $path);
        }
    }

    public function test_json_ld_follows_the_configured_public_host_and_cannot_break_out_of_the_script_tag(): void
    {
        config(['seo.public_url' => 'https://example-canonical.test']);

        $json = PublicSeo::businessIdentityJson();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://example-canonical.test/#organization', $data['@id']);
        $this->assertSame('https://example-canonical.test/', $data['url']);
        $this->assertStringNotContainsString('</', $json);
        $this->assertStringNotContainsString('<', $json);
    }

    public function test_primary_navigation_and_footer_link_to_tour_search_and_the_page_has_a_visible_fallback(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertMatchesRegularExpression('#<a class="nav-link[^"]*" href="[^"]*/tours"[^>]*>Поиск туров</a>#u', $html);
        $this->assertMatchesRegularExpression('#<li><a href="[^"]*/tours">Поиск туров</a></li>#u', $html);

        $tours = $this->get('/tours')->getContent();
        $this->assertMatchesRegularExpression('#<a class="nav-link\s+active\s*" href="[^"]*/tours"\s+aria-current="page"\s*>Поиск туров</a>#u', $tours);
        $this->assertSame(2, substr_count($tours, 'href="tel:+79219314345"'), 'noscript + delayed-load fallback phone');
        // 2 в запасных блоках страницы + 2 в подвале
        $this->assertSame(4, substr_count($tours, 'href="mailto:avilonatur@bk.ru"'), 'fallback blocks + footer e-mail');
        $this->assertStringContainsString('не бронирование и не оплата', $tours);
        $this->assertStringNotContainsString('автоматическ', mb_strtolower($tours), 'no automated-booking claim');
    }

    public function test_robots_txt_keeps_the_site_crawlable_and_follows_the_configured_host(): void
    {
        config(['seo.public_url' => 'https://example-canonical.test']);

        $response = $this->get('/robots.txt');
        $body = $response->getContent();

        $this->assertSame(1, substr_count(strtolower($body), 'sitemap:'));
        $this->assertStringContainsString("Sitemap: https://example-canonical.test/sitemap.xml\n", $body);
        $this->assertStringNotContainsString("Disallow: /\n", $body, 'public pages must stay crawlable');
        $this->assertDoesNotMatchRegularExpression('/^Disallow:\s*\/\s*$/m', $body);
        $this->assertCount(0, $response->headers->getCookies());

        foreach (['/api/', '/admin', '/cabinet', '/login', '/register', '/reload-captcha', '/contact/'] as $private) {
            $this->assertStringContainsString("Disallow: {$private}", $body);
        }

        // sitemap и robots не противоречат: ни один URL из sitemap не закрыт Disallow
        $disallows = [];
        foreach (preg_split('/\R/', $body) as $line) {
            if (str_starts_with($line, 'Disallow:') && trim(substr($line, 9)) !== '') {
                $disallows[] = trim(substr($line, 9));
            }
        }
        $this->createDetailFixtures();
        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());
        foreach ($xml->url as $url) {
            $path = parse_url((string) $url->loc, PHP_URL_PATH) ?? '/';
            foreach ($disallows as $rule) {
                $this->assertFalse(str_starts_with($path, $rule), "sitemap URL {$path} is blocked by robots rule {$rule}");
            }
        }
    }

    // ------------------------------------------------------------------
    // Contact-form acknowledgement mail safety
    // ------------------------------------------------------------------

    private function submit(string $route, array $overrides = [])
    {
        return $this->post(route($route), array_merge([
            'name' => 'Анна Иванова', 'email' => 'visitor@example.com', 'subject' => 'Hello',
            'message' => str_repeat('A', 60), 'captcha' => 'x', 'agree' => '1', 'personal_data_consent' => '1',
        ], $overrides));
    }

    public function test_acknowledgement_greets_only_name_like_input(): void
    {
        $this->assertSame('Анна Иванова', (new UserFormMail(['name' => ' Анна Иванова ']))->validatedData['name']);
        $this->assertSame("O'Brien-Smith", (new UserFormMail(['name' => "O'Brien-Smith"]))->validatedData['name']);

        foreach ([
            'Купите дёшево www.spam.example', 'evil.com', '[click](http://evil.example)', '<b>x</b>',
            'a@b.example', 'http://x.example', '123456', '', str_repeat('А', 61),
        ] as $hostile) {
            $this->assertSame('', (new UserFormMail(['name' => $hostile]))->validatedData['name'], $hostile);
        }

        $html = (new UserFormMail(['name' => 'evil.com', 'message' => 'SECRET-BODY', 'email' => 'v@e.test']))->render();
        $this->assertStringContainsString('Здравствуйте!', $html);
        $this->assertStringNotContainsString('evil.com', $html);
        $this->assertStringNotContainsString('SECRET-BODY', $html);
    }

    public function test_acknowledgement_is_limited_per_recipient_while_staff_notifications_keep_flowing(): void
    {
        Validator::extend('captcha', fn () => true);
        Mail::fake();
        RateLimiter::clear('contact-ack:' . sha1('visitor@example.com'));

        for ($i = 0; $i < 4; $i++) {
            $this->submit('contact.send_contact')->assertSessionHas('success');
        }

        Mail::assertSent(ContactFormMail::class, 4);
        Mail::assertSent(UserFormMail::class, 2);

        // другой адрес не затронут; регистр адреса не обходит лимит
        $this->submit('contact.send_home', ['email' => 'other@example.com'])->assertSessionHas('success');
        $this->submit('contact.send_home', ['email' => 'VISITOR@example.com'])->assertSessionHas('success');
        Mail::assertSent(UserFormMail::class, 3);
    }

    public function test_acknowledgement_failure_does_not_turn_a_delivered_lead_into_an_error(): void
    {
        Validator::extend('captcha', fn () => true);

        $pending = \Mockery::mock();
        $pending->shouldReceive('send')->once();
        Mail::shouldReceive('to')->with('straus97@mail.ru')->once()->andReturn($pending);
        Mail::shouldReceive('to')->with('visitor@example.com')->once()->andThrow(new \RuntimeException('smtp down'));
        Log::shouldReceive('warning')->once()->with(
            'Visitor acknowledgement mail failed',
            \Mockery::on(fn ($ctx) => $ctx === ['exception' => \RuntimeException::class])
        );

        $this->submit('contact.send_contact')->assertSessionHas('success')->assertSessionMissing('error');
    }
}
