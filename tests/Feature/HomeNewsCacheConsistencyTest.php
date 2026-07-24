<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Правило: тизер новостей на главной (home.index → Home\IndexController)
 * больше не оборачивает новостной запрос в Cache::remember('home_news', ...).
 * news:sync-rss не выполняет никакой инвалидации кэша, поэтому единственный
 * способ гарантировать немедленную видимость синхронизированных новостей на
 * главной — читать напрямую из БД на каждый GET, как уже сделано для
 * публичного списка/детали/RSS новостей. Кэши home_best_offers и
 * home_partners сознательно остаются нетронутыми.
 */
class HomeNewsCacheConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://www.turizm.ru/news/rss/yandex/';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // -----------------------------------------------------------------------
    // 1. Static source regression
    // -----------------------------------------------------------------------

    public function test_home_news_controller_uses_live_query_and_preserves_unrelated_component_caches(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Home/IndexController.php'));

        $this->assertStringNotContainsString("Cache::remember('home_news'", $controller);
        $this->assertStringNotContainsString("Cache::remember('home_reviews'", $controller);
        $this->assertStringContainsString('Illuminate\Support\Facades\Cache', $controller);
        $this->assertStringContainsString("Cache::remember('home_best_offers'", $controller);
        $this->assertStringContainsString("Cache::remember('home_partners'", $controller);

        $this->assertStringContainsString(
            "News::select('id', 'title', 'slug', 'link', 'description', 'image', 'pub_date')",
            $controller
        );
        $this->assertStringContainsString("->orderBy('pub_date', 'desc')", $controller);
        $this->assertStringContainsString('->take(4)', $controller);
        $this->assertStringContainsString('->get();', $controller);

        $routesContent = file_get_contents(base_path('routes/web.php'));
        preg_match('/Route::get\([^;]*?"home\.index"[^;]*?\);/s', $routesContent, $matches);
        $this->assertNotEmpty($matches, 'Route definition for home.index was not found');
        $this->assertStringNotContainsString('cache.response', $matches[0]);
    }

    // -----------------------------------------------------------------------
    // 2. Latest four, ordered by pub_date desc, regardless of insertion order
    // -----------------------------------------------------------------------

    public function test_homepage_shows_four_latest_news_ordered_by_publication_date(): void
    {
        $items = [
            'third' => ['News Marker Third 003', '2024-01-03 10:00:00'],
            'oldest' => ['News Marker Oldest 001', '2024-01-01 10:00:00'],
            'newest' => ['News Marker Newest 005', '2024-01-05 10:00:00'],
            'second' => ['News Marker Second 002', '2024-01-02 10:00:00'],
            'fourth' => ['News Marker Fourth 004', '2024-01-04 10:00:00'],
        ];

        foreach ($items as $suffix => [$title, $pubDate]) {
            $this->makeNews($title, $suffix, $pubDate);
        }

        $response = $this->get(route('home.index'));
        $response->assertOk();

        $response->assertSee('News Marker Newest 005');
        $response->assertSee('News Marker Fourth 004');
        $response->assertSee('News Marker Third 003');
        $response->assertSee('News Marker Second 002');
        $response->assertDontSee('News Marker Oldest 001');

        $content = $response->getContent();
        $posNewest = strpos($content, 'News Marker Newest 005');
        $posFourth = strpos($content, 'News Marker Fourth 004');
        $posThird = strpos($content, 'News Marker Third 003');
        $posSecond = strpos($content, 'News Marker Second 002');

        $this->assertLessThan($posFourth, $posNewest, 'Newest item must render before the fourth-newest item');
        $this->assertLessThan($posThird, $posFourth, 'Fourth-newest item must render before the third-newest item');
        $this->assertLessThan($posSecond, $posThird, 'Third-newest item must render before the second-newest item');
    }

    // -----------------------------------------------------------------------
    // 3. Soft-delete scope remains intact
    // -----------------------------------------------------------------------

    public function test_soft_deleted_news_is_not_returned_on_homepage(): void
    {
        $news = $this->makeNews('Soft Deleted Home Marker News', 'soft-deleted', '2024-01-01 10:00:00');
        $news->delete();

        $this->get(route('home.index'))->assertDontSee('Soft Deleted Home Marker News');
    }

    // -----------------------------------------------------------------------
    // 4, 5. Immediate visibility after sync
    // -----------------------------------------------------------------------

    public function test_successful_sync_create_is_visible_immediately_after_homepage_was_requested(): void
    {
        $this->makeNews('Existing Home News One', 'existing-1', '2024-01-01 10:00:00');
        $this->makeNews('Existing Home News Two', 'existing-2', '2024-01-02 10:00:00');

        $before = $this->get(route('home.index'));
        $before->assertOk();
        $before->assertDontSee('Sync Create Marker News 2020');

        $this->fakeFeed($this->rss([[
            'title' => 'Sync Create Marker News 2020',
            'link' => 'https://example.com/sync-create-news',
            'pubDate' => 'Fri, 05 Jan 2024 10:00:00 +0000',
            'encoded' => '<p>Fresh synced homepage body</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $after = $this->get(route('home.index'));
        $after->assertOk();
        $after->assertSee('Sync Create Marker News 2020');
    }

    public function test_successful_sync_update_is_visible_immediately_after_homepage_was_requested(): void
    {
        // RssNewsSyncService matches existing rows by `link`; id/slug are preserved on update.
        News::create([
            'title' => 'Old Sync Update Marker News',
            'slug' => 'stable-home-sync-slug',
            'link' => 'https://example.com/sync-update-news',
            'description' => '<p>Old description</p>',
            'image' => null,
            'pub_date' => '2024-01-05 10:00:00',
        ]);

        $before = $this->get(route('home.index'));
        $before->assertOk();
        $before->assertSee('Old Sync Update Marker News');

        $this->fakeFeed($this->rss([[
            'title' => 'New Sync Update Marker News',
            'link' => 'https://example.com/sync-update-news',
            'pubDate' => 'Fri, 05 Jan 2024 10:00:00 +0000',
            'encoded' => '<p>New description</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $after = $this->get(route('home.index'));
        $after->assertOk();
        $after->assertSee('New Sync Update Marker News');
        $after->assertDontSee('Old Sync Update Marker News');
    }

    // -----------------------------------------------------------------------
    // 6, 7. Legacy home_news cache key is dead
    // -----------------------------------------------------------------------

    public function test_homepage_ignores_legacy_home_news_cache_value(): void
    {
        $this->makeNews('Current Home News Marker XYZ', 'current-xyz', '2024-01-01 10:00:00');

        Cache::put('home_news', 'stale-home-news-value-xyz', now()->addMinutes(60));

        $response = $this->get(route('home.index'));
        $response->assertOk();
        $response->assertSee('Current Home News Marker XYZ');
        $response->assertDontSee('stale-home-news-value-xyz');
    }

    public function test_homepage_request_does_not_create_legacy_home_news_cache_key(): void
    {
        $this->assertFalse(Cache::has('home_news'));

        $this->makeNews('No Legacy Key Home News', 'no-legacy', '2024-01-01 10:00:00');

        $this->get(route('home.index'))->assertOk();
        $this->assertFalse(Cache::has('home_news'));

        $this->get(route('home.index'))->assertOk();
        $this->assertFalse(Cache::has('home_news'));
    }

    // -----------------------------------------------------------------------
    // 8. Failed sync leaves the homepage unchanged
    // -----------------------------------------------------------------------

    public function test_failed_sync_does_not_change_current_homepage_news(): void
    {
        $this->makeNews('Kept Home News Marker ABC', 'kept-home', '2024-01-01 10:00:00');

        $before = $this->get(route('home.index'));
        $before->assertOk();
        $before->assertSee('Kept Home News Marker ABC');

        Http::preventStrayRequests();
        Http::fake([self::FEED => Http::response('', 500)]);

        $this->artisan('news:sync-rss')->assertExitCode(1);

        $this->assertSame(1, News::count());

        $after = $this->get(route('home.index'));
        $after->assertOk();
        $after->assertSee('Kept Home News Marker ABC');
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
     * Собрать валидный RSS из массива элементов (совпадает по форме с
     * SyncNewsRssCommandTest::rss() / NewsPublicCacheConsistencyTest::rss()).
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
