<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Award;
use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\Employee;
use App\Models\News;
use App\Models\OurClient;
use App\Models\ReviewConsent;
use App\Models\Reviews;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * NOV-PUBLIC-02: точный контракт допуска URL в sitemap.xml.
 *
 * Карта строится из базы, а не из статического списка (прежний sitemap.xml
 * содержал 273 URL, не привязанных к реальным записям). Здесь — синтетические
 * записи; настоящих данных нет. URL попадает в карту, только если публичный
 * маршрут его отдаёт с 200 и страница индексируется.
 */
class PublicSitemapConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://avilona.ru';

    /** @return array<int, string> */
    private function locs(): array
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml, 'sitemap.xml must parse as valid XML');

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = trim((string) $url->loc);
        }

        return $locs;
    }

    /** @return array<int, string> */
    private function paths(): array
    {
        return array_map(fn ($loc) => substr($loc, strlen(self::BASE)), $this->locs());
    }

    private function news(string $slug, array $extra = []): News
    {
        return News::create(array_merge([
            'title' => 'Synthetic news ' . $slug, 'slug' => $slug, 'link' => 'https://example.com/' . $slug,
            'description' => 'Synthetic body', 'image' => null, 'pub_date' => now(),
        ], $extra));
    }

    private function review(bool $published, bool $withdrawn = false): Reviews
    {
        $review = Reviews::create([
            'name' => 'Synthetic', 'title' => null, 'content' => str_repeat('a', 60), 'image' => null,
            'is_published' => $published,
        ]);

        if ($withdrawn) {
            ReviewConsent::create([
                'review_id' => $review->id, 'evidence_id' => (string) Str::uuid(),
                'consent_full_name' => 'X', 'consent_email' => 'x@example.com',
                'user_agreement_accepted_at' => now(), 'personal_data_consent_accepted_at' => now(),
                'review_publication_consent_accepted_at' => now(),
                'user_agreement_version' => 'v', 'personal_data_consent_version' => 'v',
                'review_publication_consent_version' => 'v', 'publication_scope' => ['name'],
                'publication_conditions' => null, 'review_payload_sha256' => hash('sha256', 'p' . $review->id),
                'withdrawn_at' => now(),
            ]);
        }

        return $review;
    }

    public function test_content_free_site_lists_only_the_static_pages(): void
    {
        $this->assertSame([
            '/', '/tours', '/contacts', '/company/about_company', '/helpful_information/travel_dictionary',
        ], $this->paths());
    }

    public function test_sitemap_uses_standard_namespace_and_the_configured_host(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->getNamespaces()['']);

        config(['seo.public_url' => 'https://example-canonical.test']);

        foreach ($this->locs() as $loc) {
            $this->assertStringStartsWith('https://example-canonical.test/', $loc);
        }
    }

    public function test_published_records_and_their_list_pages_are_included(): void
    {
        Countries_image::create(['title' => 'Страна', 'slug' => 'strana', 'category' => 'Европа', 'description' => 'd']);
        Destination_image::create(['title' => 'Напр', 'slug' => 'napr', 'description' => 'd']);
        Article::create(['title' => 'Стат', 'slug' => 'stat', 'content' => 'c', 'image' => null]);
        $this->news('novost');
        OurClient::create(['title' => 'Спец', 'slug' => 'spets', 'content' => 'c', 'image' => null]);
        Employee::create(['name' => 'E', 'position' => 'p', 'tel' => '1', 'email' => 'e@e.test', 'whatsapp' => '1', 'vk' => '1', 'image' => null]);
        Award::create(['image' => null, 'category' => 'c']);
        $this->review(true);

        $paths = $this->paths();

        foreach ([
            '/countries', '/countries/strana', '/destinations', '/destinations/napr',
            '/helpful_information/interesting_articles', '/helpful_information/interesting_articles/stat',
            '/helpful_information/news', '/helpful_information/news/novost',
            '/helpful_information/for_our_clients', '/helpful_information/for_our_clients/spets',
            '/company/employees', '/company/awards', '/reviews',
        ] as $expected) {
            $this->assertContains($expected, $paths);
        }
    }

    public function test_every_listed_url_is_a_successful_public_page(): void
    {
        Countries_image::create(['title' => 'Страна', 'slug' => 'strana', 'category' => 'Европа', 'description' => 'd']);
        $this->news('novost');
        $this->review(true);

        foreach ($this->paths() as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_soft_deleted_and_slugless_records_are_excluded(): void
    {
        $gone = $this->news('udalena');
        $this->news('zhivaya');
        $gone->delete();
        $this->news('', ['title' => 'No slug']);
        Countries_image::create(['title' => 'Del', 'slug' => 'del', 'category' => 'Европа', 'description' => 'd'])->delete();

        $paths = $this->paths();

        $this->assertContains('/helpful_information/news/zhivaya', $paths);
        $this->assertNotContains('/helpful_information/news/udalena', $paths);
        $this->assertNotContains('/helpful_information/news/', $paths);
        $this->assertNotContains('/countries/del', $paths);
        $this->assertNotContains('/countries', $paths, 'a list whose only record is deleted must not be listed');
    }

    public function test_lists_without_records_are_not_listed(): void
    {
        $paths = $this->paths();

        foreach ([
            '/countries', '/destinations', '/reviews', '/company/employees', '/company/awards',
            '/helpful_information/news', '/helpful_information/interesting_articles',
            '/helpful_information/for_our_clients',
        ] as $absent) {
            $this->assertNotContains($absent, $paths);
        }
    }

    public function test_unpublished_and_withdrawn_reviews_do_not_make_the_reviews_page_listable(): void
    {
        $this->review(false);
        $this->review(true, withdrawn: true);

        $this->assertNotContains('/reviews', $this->paths());

        $this->review(true);

        $this->assertContains('/reviews', $this->paths());
    }

    public function test_pagination_pages_are_exact_and_page_one_is_never_duplicated(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->news('n' . $i);
        }

        $paths = $this->paths();

        $this->assertContains('/helpful_information/news?page=2', $paths);
        $this->assertContains('/helpful_information/news?page=3', $paths);
        $this->assertNotContains('/helpful_information/news?page=4', $paths);
        $this->assertNotContains('/helpful_information/news?page=1', $paths);

        // ровно 6 записей — одна страница
        News::query()->where('id', '>', 6)->forceDelete();
        $this->assertNotContains('/helpful_information/news?page=2', $this->paths());
    }

    public function test_urls_are_unique_https_and_contain_no_private_pdf_or_filter_urls(): void
    {
        Countries_image::create(['title' => 'Страна', 'slug' => 'strana', 'category' => 'Европа', 'description' => 'd']);
        for ($i = 1; $i <= 8; $i++) {
            $this->news('n' . $i);
        }

        $locs = $this->locs();
        $this->assertSame(count($locs), count(array_unique($locs)));

        $private = [
            '/admin', '/manager', '/cabinet', '/login', '/register', '/forgot-password', '/api', '/messages',
            '/bookings', '/profile', '/documents', '/reload-captcha', '/contact/', '/password', '/captcha',
        ];

        foreach ($locs as $loc) {
            $this->assertSame('https', parse_url($loc, PHP_URL_SCHEME));
            $this->assertSame('avilona.ru', parse_url($loc, PHP_URL_HOST));
            $this->assertStringEndsNotWith('.pdf', $loc);
            $this->assertDoesNotMatchRegularExpression('/\?(?!page=\d+$)/', $loc, "only ?page=N allowed: {$loc}");

            $path = parse_url($loc, PHP_URL_PATH) ?? '';
            foreach ($private as $prefix) {
                $this->assertFalse(str_starts_with($path, $prefix), "private prefix {$prefix} in {$loc}");
            }
        }

        $this->assertSame(1, count(array_keys($this->paths(), '/tours', true)));
    }

    public function test_slugs_are_url_encoded_and_xml_safe(): void
    {
        $this->news('a&b c');

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertNotFalse(simplexml_load_string($body));
        // «&» в пути экранируется как XML-сущность, пробел кодируется в URL
        $this->assertStringContainsString('/helpful_information/news/a&amp;b%20c', $body);
        $this->assertStringNotContainsString('a&b', $body);
    }

    public function test_lastmod_is_omitted_by_default_and_uses_updated_at_only_when_enabled(): void
    {
        $this->news('n1')->forceFill(['updated_at' => '2026-05-04 10:00:00'])->saveQuietly();

        $this->assertStringNotContainsString('<lastmod>', $this->get('/sitemap.xml')->getContent());

        config(['seo.sitemap_lastmod' => true]);

        $body = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('<lastmod>2026-05-04T10:00:00', $body);
        // lastmod только у записей с реальной датой, не у статических страниц
        $this->assertSame(1, substr_count($body, '<lastmod>'));
    }

    public function test_generation_is_bounded_by_the_configured_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->news('n' . $i);
        }
        config(['seo.sitemap_max_urls' => 7]);

        $this->assertCount(7, $this->locs());
    }

    public function test_sitemap_is_cookie_free_and_cacheable_and_fails_closed_on_database_errors(): void
    {
        $response = $this->get('/sitemap.xml');
        $this->assertCount(0, $response->headers->getCookies());
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));

        $this->mock(SitemapBuilder::class, fn ($m) => $m->shouldReceive('toXml')->andThrow(new \RuntimeException('db')));

        $this->get('/sitemap.xml')->assertStatus(503)->assertHeader('Retry-After');
    }
}
