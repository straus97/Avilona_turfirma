<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Правило: публичные новостные точки (helpful_news.index, news.rss,
 * helpful_news_id.index) больше не используют ни middleware cache.response,
 * ни контроллерный Cache::remember (ранее — только в HelpfulNewsIdController).
 * news:sync-rss не выполняет никакой инвалидации кэша, поэтому единственный
 * способ гарантировать немедленную видимость синхронизированных данных —
 * читать напрямую из БД на каждый GET. Эти тесты фиксируют это поведение.
 */
class NewsPublicCacheConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://www.turizm.ru/news/rss/yandex/';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // -----------------------------------------------------------------------
    // 1. Middleware regression
    // -----------------------------------------------------------------------

    public function test_public_news_routes_do_not_use_response_cache_middleware(): void
    {
        $indexRoute = Route::getRoutes()->getByName('helpful_news.index');
        $rssRoute = Route::getRoutes()->getByName('news.rss');
        $detailRoute = Route::getRoutes()->getByName('helpful_news_id.index');

        $this->assertNotNull($indexRoute);
        $this->assertNotNull($rssRoute);
        $this->assertNotNull($detailRoute);

        $this->assertNotContains('cache.response', $indexRoute->gatherMiddleware());
        $this->assertNotContains('cache.response', $rssRoute->gatherMiddleware());
        $this->assertNotContains('cache.response', $detailRoute->gatherMiddleware());

        $this->assertSame('helpful_information/news', $indexRoute->uri());
        $this->assertContains('GET', $indexRoute->methods());
        $this->assertContains('HEAD', $indexRoute->methods());

        $this->assertSame('helpful_information/news/rss', $rssRoute->uri());
        $this->assertContains('GET', $rssRoute->methods());
        $this->assertContains('HEAD', $rssRoute->methods());

        $this->assertSame('helpful_information/news/{slug}', $detailRoute->uri());
        $this->assertContains('GET', $detailRoute->methods());
        $this->assertContains('HEAD', $detailRoute->methods());

        $matched = Route::getRoutes()->match(Request::create('/helpful_information/news/rss', 'GET'));
        $this->assertSame('news.rss', $matched->getName());
        $this->assertNotSame('helpful_news_id.index', $matched->getName());
    }

    // -----------------------------------------------------------------------
    // 2. Static source regression
    // -----------------------------------------------------------------------

    public function test_news_detail_controller_does_not_use_controller_cache(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HelpfulInformation/HelpfulNewsIdController.php'));

        $this->assertStringNotContainsString('Cache::remember', $controller);
        $this->assertStringNotContainsString('Illuminate\Support\Facades\Cache', $controller);
        $this->assertStringContainsString("News::where('slug', \$slug)->firstOrFail()", $controller);

        $routesContent = file_get_contents(base_path('routes/web.php'));

        foreach (['helpful_news.index', 'news.rss', 'helpful_news_id.index'] as $name) {
            preg_match('/Route::get\([^;]*?"' . preg_quote($name, '/') . '"[^;]*?\);/s', $routesContent, $matches);
            $this->assertNotEmpty($matches, "Route definition for {$name} was not found");
            $this->assertStringNotContainsString('cache.response', $matches[0]);
        }
    }

    // -----------------------------------------------------------------------
    // 3, 4, 5. Public endpoints remain available
    // -----------------------------------------------------------------------

    public function test_public_news_index_remains_available(): void
    {
        $this->makeNews('Index Marker News 111', 'index-111', '2024-01-01 10:00:00');

        $response = $this->get(route('helpful_news.index'));

        $response->assertOk();
        $response->assertSee('Index Marker News 111');
    }

    public function test_public_news_detail_remains_available(): void
    {
        $this->makeNews('Detail Marker News 222', 'detail-222', '2024-01-01 10:00:00', [
            'description' => 'Detail marker description 222',
        ]);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'slug-detail-222']));

        $response->assertOk();
        $response->assertSee('Detail Marker News 222');
        $response->assertSee('Detail marker description 222', false);
    }

    public function test_public_rss_remains_available_and_local(): void
    {
        $this->makeNews('Rss Marker News 333', 'rss-333', '2024-01-01 10:00:00');

        Http::preventStrayRequests();
        Http::fake();

        $response = $this->get(route('news.rss'));

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/rss+xml', (string) $contentType);
        $this->assertStringContainsStringIgnoringCase('utf-8', (string) $contentType);
        $response->assertSee('Rss Marker News 333');
        $response->assertSee('https://example.com/news/rss-333', false);

        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // 6, 7, 8. Immediate visibility after sync
    // -----------------------------------------------------------------------

    public function test_successful_sync_is_visible_immediately_on_previously_requested_index(): void
    {
        $before = $this->get(route('helpful_news.index'));
        $before->assertOk();
        $before->assertDontSee('Sync New Marker XYZ');

        $this->fakeFeed($this->rss([[
            'title' => 'Sync New Marker XYZ',
            'link' => 'https://example.com/sync-new',
            'pubDate' => 'Mon, 01 Jan 2024 10:00:00 +0000',
            'encoded' => '<p>Fresh synced body</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $after = $this->get(route('helpful_news.index'));
        $after->assertOk();
        $after->assertSee('Sync New Marker XYZ');
    }

    public function test_successful_sync_update_is_visible_immediately_at_the_same_detail_url(): void
    {
        $existing = News::create([
            'title' => 'Old Sync Title',
            'slug' => 'stable-sync-slug',
            'link' => 'https://example.com/stable-sync',
            'description' => '<p>Old Sync Marker</p>',
            'image' => null,
            'pub_date' => '2024-01-01 10:00:00',
        ]);

        $detailUrl = route('helpful_news_id.index', ['slug' => 'stable-sync-slug']);

        $before = $this->get($detailUrl);
        $before->assertOk();
        $before->assertSee('Old Sync Title');
        $before->assertSee('Old Sync Marker', false);

        $this->fakeFeed($this->rss([[
            'title' => 'New Sync Title',
            'link' => 'https://example.com/stable-sync',
            'encoded' => '<p>New Sync Marker</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $updated = News::findOrFail($existing->id);
        $this->assertSame('stable-sync-slug', $updated->slug);

        $after = $this->get($detailUrl);
        $after->assertOk();
        $after->assertSee('New Sync Title');
        $after->assertSee('New Sync Marker', false);
        $after->assertDontSee('Old Sync Title');
        $after->assertDontSee('Old Sync Marker');
    }

    public function test_successful_sync_is_visible_immediately_in_previously_requested_rss(): void
    {
        Http::preventStrayRequests();

        $before = $this->get(route('news.rss'));
        $before->assertOk();
        $before->assertDontSee('Rss New Marker ABC');

        $this->fakeFeed($this->rss([[
            'title' => 'Rss New Marker ABC',
            'link' => 'https://example.com/rss-new',
            'pubDate' => 'Mon, 01 Jan 2024 10:00:00 +0000',
            'encoded' => '<p>Rss new body</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $after = $this->get(route('news.rss'));
        $after->assertOk();
        $after->assertSee('Rss New Marker ABC');
    }

    // -----------------------------------------------------------------------
    // 9, 10. Legacy controller cache key is dead
    // -----------------------------------------------------------------------

    public function test_old_detail_controller_cache_key_is_not_created_or_used(): void
    {
        $this->makeNews('Real Title Not Stale', 'legacy-cache', '2024-01-01 10:00:00');

        Cache::put('news_slug-legacy-cache', 'stale-value-not-a-response', now()->addMinutes(60));

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'slug-legacy-cache']));

        $response->assertOk();
        $response->assertSee('Real Title Not Stale');

        // Легаси-ключ не тронут запросом: контроллер больше не читает и не пишет его.
        $this->assertSame('stale-value-not-a-response', Cache::get('news_slug-legacy-cache'));
    }

    public function test_public_news_requests_do_not_create_old_controller_cache_keys(): void
    {
        $this->makeNews('No Legacy Key News', 'no-legacy', '2024-01-01 10:00:00');

        $this->get(route('helpful_news_id.index', ['slug' => 'slug-no-legacy']))->assertOk();

        $this->assertFalse(Cache::has('news_slug-no-legacy'));
    }

    // -----------------------------------------------------------------------
    // 11. Soft-delete scope remains intact
    // -----------------------------------------------------------------------

    public function test_soft_deleted_news_is_not_returned_by_index_detail_or_rss(): void
    {
        $news = $this->makeNews('Soft Deleted Marker News', 'soft-deleted', '2024-01-01 10:00:00');
        $news->delete();

        $this->get(route('helpful_news.index'))->assertDontSee('Soft Deleted Marker News');
        $this->get(route('helpful_news_id.index', ['slug' => 'slug-soft-deleted']))->assertNotFound();

        Http::preventStrayRequests();
        Http::fake();
        $this->get(route('news.rss'))->assertDontSee('Soft Deleted Marker News');
    }

    // -----------------------------------------------------------------------
    // 12. Failed sync leaves public content unchanged
    // -----------------------------------------------------------------------

    public function test_failed_sync_does_not_change_current_public_content(): void
    {
        $this->makeNews('Kept Sync Marker News', 'kept-sync', '2024-01-01 10:00:00', [
            'description' => '<p>Kept sync description</p>',
        ]);

        Http::preventStrayRequests();
        Http::fake([self::FEED => Http::response('', 500)]);

        $this->artisan('news:sync-rss')->assertExitCode(1);

        $this->get(route('helpful_news.index'))->assertSee('Kept Sync Marker News');

        $detail = $this->get(route('helpful_news_id.index', ['slug' => 'slug-kept-sync']));
        $detail->assertOk();
        $detail->assertSee('Kept Sync Marker News');
        $detail->assertSee('Kept sync description', false);

        Http::fake();
        $this->get(route('news.rss'))->assertSee('Kept Sync Marker News');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeNews(string $title, string $suffix, ?string $pubDate, array $overrides = []): News
    {
        return News::create(array_merge([
            'title' => $title,
            'slug' => 'slug-' . $suffix,
            'link' => 'https://example.com/news/' . $suffix,
            'description' => 'Description ' . $suffix,
            'image' => null,
            'pub_date' => $pubDate,
        ], $overrides));
    }

    /**
     * Собрать валидный RSS из массива элементов (совпадает по форме с SyncNewsRssCommandTest::rss()).
     *
     * @param array<int,array<string,string>> $items ключи: title, link, encoded, image, pubDate
     */
    private function rss(array $items): string
    {
        $body = '';
        foreach ($items as $item) {
            $body .= '<item>';
            $body .= '<title>' . ($item['title'] ?? '') . '</title>';
            if (array_key_exists('link', $item)) {
                $body .= '<link>' . $item['link'] . '</link>';
            }
            if (array_key_exists('pubDate', $item)) {
                $body .= '<pubDate>' . $item['pubDate'] . '</pubDate>';
            }
            if (array_key_exists('image', $item)) {
                $body .= '<enclosure url="' . $item['image'] . '" type="image/jpeg"/>';
            }
            if (array_key_exists('encoded', $item)) {
                $body .= '<content:encoded><![CDATA[' . $item['encoded'] . ']]></content:encoded>';
            }
            $body .= '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">'
            . '<channel><title>Feed</title>' . $body . '</channel></rss>';
    }

    private function fakeFeed(string $xml): void
    {
        Http::preventStrayRequests();
        Http::fake([self::FEED => Http::response($xml, 200, ['Content-Type' => 'application/xml'])]);
    }
}
