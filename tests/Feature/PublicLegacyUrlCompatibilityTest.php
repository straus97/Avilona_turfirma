<?php

namespace Tests\Feature;

use App\Http\Controllers\HelpfulInformation\HelpfulNewsController;
use App\Models\Article;
use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\News;
use App\Models\OurClient;
use App\Models\Reviews;
use App\Services\Seo\LegacyUrlClassifier;
use App\Support\Seo\PublicSeo;
use App\Support\Seo\RobotsRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * NOV-PUBLIC-03A: совместимость исторических публичных URL с текущими маршрутами.
 *
 * Историческая карта сайта (272 URL, sitemap.xml из первого коммита) сохранена в
 * resources/seo/legacy-sitemap-paths.txt. Поведение проверяется на настоящих
 * маршрутах с синтетическими записями; живых данных, сети и поисковых аккаунтов
 * нет. Тест не утверждает, что в будущем контенте все эти записи будут.
 */
class PublicLegacyUrlCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function inventory(): array
    {
        $lines = preg_split('/\R/', trim((string) file_get_contents(resource_path('seo/legacy-sitemap-paths.txt'))));

        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    private function news(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            News::create([
                'title' => "SYNTHETIC news $i", 'slug' => "synthetic-news-$i", 'link' => "https://example.com/$i",
                'description' => 'Synthetic body', 'image' => null, 'pub_date' => now()->subDays($i),
            ]);
        }
    }

    private function articles(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Article::create(['title' => "SYNTHETIC article $i", 'slug' => "synthetic-article-$i", 'content' => 'c', 'image' => null]);
        }
    }

    private function clients(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            OurClient::create(['title' => "SYNTHETIC special $i", 'slug' => "synthetic-special-$i", 'content' => 'c', 'image' => null]);
        }
    }

    private function reviews(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Reviews::create(['name' => 'SYNTHETIC', 'title' => null, 'content' => str_repeat('a', 60), 'image' => null, 'is_published' => true]);
        }
    }

    private function country(string $slug = 'egipet'): Countries_image
    {
        return Countries_image::create(['title' => 'SYNTHETIC ' . $slug, 'slug' => $slug, 'category' => 'x', 'description' => 'd']);
    }

    // -----------------------------------------------------------------------
    // 1. Инвентарь и классификация
    // -----------------------------------------------------------------------

    public function test_inventory_is_the_272_url_historical_sitemap_with_the_expected_composition(): void
    {
        $paths = $this->inventory();

        $this->assertCount(272, $paths);
        $this->assertSame($paths, array_values(array_unique($paths)));

        $count = fn (string $re) => count(array_filter($paths, fn ($p) => preg_match($re, $p) === 1));

        $this->assertSame(55, $count('#^/countries/#'));
        $this->assertSame(12, $count('#^/destinations/#'));
        $this->assertSame(100, $count('#^/helpful_information/news/#'));
        $this->assertSame(50, $count('#^/helpful_information/interesting_articles/#'));
        $this->assertSame(1, $count('#^/helpful_information/for_our_clients/#'));
        $this->assertSame(5, $count('#^/documents/.+\.pdf$#'));
        $this->assertSame(37, $count('#\?page=\d+$#'));
        $this->assertSame(0, $count('#^/countries/\d+$#'), 'the sitemap used slugs; numeric ids come only from the owner evidence');
        $this->assertSame(0, $count('#^https?://#'), 'inventory is host-less');
    }

    public function test_every_historical_url_has_a_current_route_or_file_and_none_is_guessed(): void
    {
        $classifier = $this->app->make(LegacyUrlClassifier::class);
        $classes = [];

        foreach ($this->inventory() as $path) {
            $c = $classifier->classify($path);
            $classes[$c['class']] = ($classes[$c['class']] ?? 0) + 1;

            $this->assertNotContains(
                $c['class'],
                ['no_route', 'private_or_service', 'legacy_numeric_id', 'document_missing', 'document_editable_source', 'invalid_url', 'foreign_host'],
                "historical URL {$path} must map to a current public route or published file"
            );
        }

        ksort($classes);
        // A/B: все 272 исторических адреса укладываются в текущие маршруты; 3 дубля ?page=1 уже получают доказанный 301.
        $this->assertSame([
            'document_file' => 5,
            'redirect_first_page' => 3,
            'supported_list' => 6,
            'supported_page' => 6,
            'supported_pagination' => 34,
            'supported_slug' => 218,
        ], $classes);
    }

    // -----------------------------------------------------------------------
    // 2. Доказанный редирект: ?page=1 == тот же документ
    // -----------------------------------------------------------------------

    public function test_first_page_variants_are_the_same_document_as_the_clean_url(): void
    {
        $this->news(8);

        $this->app->instance('request', Request::create('/helpful_information/news'));
        $baseline = (new HelpfulNewsController())(request())->getData()['news']->pluck('id')->all();
        $this->assertCount(6, $baseline);

        foreach (['1', '0', '-3', 'abc', '', '1.5', '00'] as $value) {
            $this->app->instance('request', Request::create('/helpful_information/news?page=' . urlencode($value)));
            $items = (new HelpfulNewsController())(request())->getData()['news'];

            $this->assertSame($baseline, $items->pluck('id')->all(), "page={$value} must show exactly the first page");
            $this->assertSame(1, $items->currentPage());
        }

        $this->app->instance('request', Request::create('/helpful_information/news?page=2'));
        $this->assertNotSame($baseline, (new HelpfulNewsController())(request())->getData()['news']->pluck('id')->all());
    }

    public function test_first_page_variants_redirect_permanently_to_the_clean_path(): void
    {
        $this->news(8);
        $this->articles(8);
        $this->clients(8);
        $this->reviews(8);

        foreach (['/reviews', '/helpful_information/news', '/helpful_information/interesting_articles', '/helpful_information/for_our_clients'] as $path) {
            foreach (['1', '0', '-3', 'abc', ''] as $value) {
                $response = $this->get($path . '?page=' . urlencode($value));
                $response->assertStatus(301)->assertHeader('Location', $path);
                $this->assertStringNotContainsString('page=', (string) $response->headers->get('Location'));

                // целевой адрес — финальный: без второго редиректа
                $this->get($path)->assertOk();
            }

            // страница 2 — отдельный документ, не редиректится
            $this->get($path . '?page=2')->assertOk();
        }
    }

    public function test_redirect_preserves_other_parameters_and_is_never_host_dependent(): void
    {
        $this->news(2);

        $response = $this->get('/helpful_information/news?page=1&utm_source=ads&q[]=a%20b');
        $response->assertStatus(301);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('/helpful_information/news?', $location);
        $this->assertStringNotContainsString('page=', $location);
        $this->assertStringContainsString('utm_source=ads', $location);

        // открытый редирект невозможен: цель берётся из пути маршрута, значения идут только в query
        $evil = $this->get('/reviews?page=0&next=//evil.example/x&url=https://evil.example');
        $evil->assertStatus(301);
        $this->assertMatchesRegularExpression('#^/reviews\?#', (string) $evil->headers->get('Location'));
        $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#i', (string) $evil->headers->get('Location'));

        // подмена Host / X-Forwarded-Host не меняет цель
        $hostile = $this->withHeaders(['X-Forwarded-Host' => 'evil.example'])->get('http://evil.example/reviews?page=1');
        $hostile->assertStatus(301)->assertHeader('Location', '/reviews');

        // цель редиректа сама не редиректится (петли нет)
        $this->get('/reviews')->assertOk();
        $this->get((string) $this->get('/helpful_information/news?page=1&utm_source=ads')->headers->get('Location'))->assertOk();
    }

    public function test_only_safe_methods_and_only_the_four_paginated_lists_are_redirected(): void
    {
        $this->country();

        // непагинируемые и приватные адреса с ?page=1 никогда не получают 301
        foreach (['/', '/tours', '/countries', '/destinations', '/contacts', '/company/about_company', '/countries/egipet', '/login', '/register'] as $path) {
            $this->assertNotSame(301, $this->get($path . '?page=1')->getStatusCode(), $path);
        }

        foreach (['/cabinet', '/cabinet/bookings', '/manager/clients', '/admin', '/bookings/1', '/api/destination-cities'] as $private) {
            $status = $this->get($private . '?page=1')->getStatusCode();
            $this->assertNotSame(301, $status, "{$private} must never be permanently redirected");
        }
    }

    // -----------------------------------------------------------------------
    // 3. Честные 404: нет подмены отсутствующей страницы успехом
    // -----------------------------------------------------------------------

    public function test_pages_beyond_the_last_page_are_real_404_not_empty_200(): void
    {
        $this->news(7);       // 2 страницы
        $this->articles(6);   // ровно 1 страница
        $this->clients(7);
        $this->reviews(6);

        $this->get('/helpful_information/news?page=2')->assertOk();
        $this->get('/helpful_information/news?page=3')->assertNotFound();
        $this->get('/helpful_information/news?page=17')->assertNotFound();
        $this->get('/helpful_information/news?page=99999')->assertNotFound();

        $this->get('/helpful_information/interesting_articles')->assertOk();
        $this->get('/helpful_information/interesting_articles?page=2')->assertNotFound();

        $this->get('/helpful_information/for_our_clients?page=2')->assertOk();
        $this->get('/helpful_information/for_our_clients?page=3')->assertNotFound();

        $this->get('/reviews')->assertOk();
        $this->get('/reviews?page=2')->assertNotFound();
        $this->get('/reviews?page=11')->assertNotFound();

        $missing = $this->get('/helpful_information/news?page=17');
        $this->assertStringContainsString('noindex', $missing->getContent());
        $this->assertNull($missing->headers->get('Location'));
        $this->assertStringNotContainsString('?page=17', $missing->getContent(), 'a missing page must not self-canonicalise');
    }

    public function test_empty_lists_keep_a_valid_first_page_but_have_no_second_page(): void
    {
        $this->get('/helpful_information/news')->assertOk();
        $this->get('/reviews')->assertOk();
        $this->get('/helpful_information/news?page=2')->assertNotFound();
        $this->get('/reviews?page=2')->assertNotFound();
    }

    public function test_legacy_numeric_country_ids_are_never_mapped_to_a_slug(): void
    {
        $country = $this->country('egipet');
        $this->country('turciia');

        $this->get('/countries/egipet')->assertOk();

        // id существующей записи — не адрес: числового соответствия репозиторий не даёт
        foreach ([(string) $country->id, '1', '2', '17', '999'] as $numeric) {
            $response = $this->get('/countries/' . $numeric);
            $response->assertNotFound();
            $this->assertNull($response->headers->get('Location'));
        }

        $classifier = $this->app->make(LegacyUrlClassifier::class);
        $c = $classifier->classify('/countries/17');
        $this->assertSame('legacy_numeric_id', $c['class']);
        $this->assertSame('owner_evidence', $c['action']);
        $this->assertNull(LegacyUrlClassifier::firstPageTarget($c));
    }

    public function test_missing_content_and_unknown_urls_stay_404_without_redirects_to_home(): void
    {
        $this->country('egipet');
        Destination_image::create(['title' => 'Напр', 'slug' => 'napr', 'description' => 'd']);
        $this->news(1);

        foreach ([
            '/countries/net-takoi-strany', '/destinations/net-takogo', '/helpful_information/news/net-takoi-novosti',
            '/helpful_information/interesting_articles/net-takoi-stati', '/helpful_information/for_our_clients/net-takogo',
            '/Countries/egipet', '/countries/EGIPET', '/home', '/index.php', '/news', '/about', '/tour', '/company',
            '/helpful_information', '/documents/User_Agreement_old.pdf',
        ] as $path) {
            $response = $this->get($path);
            $this->assertSame(404, $response->getStatusCode(), $path);
            $this->assertNull($response->headers->get('Location'), "{$path} must not redirect");
            $this->assertStringContainsString('noindex', $response->getContent(), "{$path} 404 must be noindex");
        }
    }

    public function test_trailing_slash_serves_the_same_document_with_the_slashless_canonical(): void
    {
        $this->country('egipet');
        $this->news(1);

        foreach (['/countries/egipet/' => '/countries/egipet', '/helpful_information/news/synthetic-news-1/' => '/helpful_information/news/synthetic-news-1', '/company/about_company/' => '/company/about_company'] as $url => $canonical) {
            $response = $this->get($url)->assertOk();
            $this->assertStringContainsString('<link rel="canonical" href="https://avilona.ru' . $canonical . '">', $response->getContent());
        }
    }

    // -----------------------------------------------------------------------
    // 4. Метаданные и внутренние ссылки согласованы
    // -----------------------------------------------------------------------

    public function test_pagination_links_point_to_final_urls_without_page_one(): void
    {
        $this->news(14); // 3 страницы

        $html = $this->get('/helpful_information/news?page=2')->assertOk()->getContent();
        preg_match_all('#<a class="page-link" href="([^"]+)"#', $html, $m);
        $hrefs = array_unique(array_map('html_entity_decode', $m[1]));

        $this->assertNotEmpty($hrefs);

        foreach ($hrefs as $href) {
            $this->assertStringNotContainsString('page=1', $href, 'page 1 link must be the clean URL');
            $path = (string) parse_url($href, PHP_URL_PATH) . (parse_url($href, PHP_URL_QUERY) ? '?' . parse_url($href, PHP_URL_QUERY) : '');
            $this->get($path)->assertOk(); // каждая внутренняя ссылка пагинации — 200, без редиректа
        }

        $this->assertContains('/helpful_information/news', array_map(fn ($h) => (string) parse_url($h, PHP_URL_PATH) . (parse_url($h, PHP_URL_QUERY) ? '?' . parse_url($h, PHP_URL_QUERY) : ''), $hrefs));
    }

    public function test_page_link_helper_only_drops_a_bare_page_one_pair(): void
    {
        $this->assertSame('/n', PublicSeo::pageLinkUrl('/n?page=1'));
        $this->assertSame('/n?a=1&b=2', PublicSeo::pageLinkUrl('/n?a=1&page=1&b=2'));
        $this->assertSame('/n?page=2', PublicSeo::pageLinkUrl('/n?page=2'));
        $this->assertSame('/n?page=11', PublicSeo::pageLinkUrl('/n?page=11'));
        $this->assertSame('/n?q=%D0%B0&x[]=1', PublicSeo::pageLinkUrl('/n?q=%D0%B0&x[]=1'));
        $this->assertSame('/n#top', PublicSeo::pageLinkUrl('/n?page=1#top'));
        $this->assertSame('/n', PublicSeo::pageLinkUrl('/n'));
        $this->assertSame('', PublicSeo::pageLinkUrl(null));
    }

    public function test_canonical_og_url_sitemap_and_robots_use_one_host_on_real_pages(): void
    {
        config(['seo.public_url' => 'https://canonical.example']);

        $this->country('egipet');
        $this->news(8);

        foreach (['/', '/countries/egipet', '/helpful_information/news', '/helpful_information/news?page=2', '/contacts'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('#<link rel="canonical"#', $html), "one canonical on {$path}");
            $this->assertSame(1, preg_match_all('#<meta property="og:url"#', $html), "one og:url on {$path}");
            $this->assertSame(1, preg_match_all('#<meta name="description"#', $html), "one description on {$path}");
            $this->assertSame(1, preg_match_all('#<meta name="robots"#', $html), "one robots meta on {$path}");

            preg_match('#<link rel="canonical" href="([^"]+)"#', $html, $canonical);
            preg_match('#<meta property="og:url" content="([^"]+)"#', $html, $og);
            $this->assertSame($canonical[1], $og[1]);
            $this->assertStringStartsWith('https://canonical.example/', $canonical[1]);
        }

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#^Sitemap: #m', $robots));
        $this->assertStringContainsString('Sitemap: https://canonical.example/sitemap.xml', $robots);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        foreach ($xml->url as $url) {
            $this->assertStringStartsWith('https://canonical.example/', trim((string) $url->loc));
        }
    }

    // -----------------------------------------------------------------------
    // 5. robots.txt и sitemap не противоречат друг другу
    // -----------------------------------------------------------------------

    public function test_no_sitemap_url_and_no_supported_historical_url_is_blocked_by_robots(): void
    {
        $this->country('egipet');
        Destination_image::create(['title' => 'Напр', 'slug' => 'napr', 'description' => 'd']);
        $this->news(14);
        $this->articles(8);
        $this->clients(8);
        $this->reviews(8);

        $robots = RobotsRules::fromFile(base_path('robots.txt'));
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        $this->assertGreaterThan(20, count($xml->url));

        foreach ($xml->url as $url) {
            $path = (string) parse_url(trim((string) $url->loc), PHP_URL_PATH);
            $query = parse_url(trim((string) $url->loc), PHP_URL_QUERY);
            $this->assertTrue($robots->allows($path . ($query ? '?' . $query : '')), "sitemap URL blocked by robots: {$url->loc}");
        }

        foreach ($this->inventory() as $historical) {
            $this->assertTrue($robots->allows($historical), "historical public URL blocked by robots: {$historical}");
        }

        // публичные страницы остаются доступными для рендеринга: ассеты публичной темы не закрыты
        foreach (['/css/unified.css', '/js/e2-public.js', '/img/logo.png', '/img/favicon.ico', '/favicon.ico', '/robots.txt', '/sitemap.xml'] as $asset) {
            $this->assertTrue($robots->allows($asset), $asset);
        }
    }

    public function test_robots_blocks_private_surfaces_editable_sources_and_cabinet_asset_trees(): void
    {
        $robots = RobotsRules::fromFile(base_path('robots.txt'));

        foreach ([
            '/login', '/register', '/cabinet', '/cabinet/bookings', '/manager/clients', '/admin', '/profile', '/api/destination-cities',
            '/messages/1', '/bookings/1', '/password/change', '/reload-captcha', '/contact/send_home', '/storage/avatars/x.png',
            '/documents/x.doc', '/documents/x.docx',
            '/dist/css/adminlte.min.css', '/plugins/summernote/summernote-bs4.min.css', '/vendor/autoload.php', '/.env',
        ] as $blocked) {
            $this->assertFalse($robots->allows($blocked), "{$blocked} must be disallowed");
        }

        // /contacts (страница) не должна попадать под /contact/ (POST-эндпоинты)
        $this->assertTrue($robots->allows('/contacts'));
        $this->assertTrue($robots->allows('/documents/User_Agreement.pdf'), 'legal PDFs are intentionally not blocked (owner decision N-4)');
    }

    public function test_robots_rules_parser_follows_longest_match_and_wildcards(): void
    {
        $rules = new RobotsRules("User-agent: Other\nDisallow: /\n\nUser-agent: *\nDisallow: /a/\nAllow: /a/b\nDisallow: /*.doc\$\nDisallow:\n# comment\nSitemap: https://x/sitemap.xml\n");

        $this->assertFalse($rules->allows('/a/c'));
        $this->assertTrue($rules->allows('/a/b/c'));
        $this->assertFalse($rules->allows('/x/file.doc'));
        $this->assertTrue($rules->allows('/x/file.docx'));
        $this->assertTrue($rules->allows('/anything'));
        $this->assertSame(['/a/', '/*.doc$'], $rules->disallowPatterns());
    }
}
