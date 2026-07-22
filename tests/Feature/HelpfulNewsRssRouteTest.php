<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Публичный RSS-эндпоинт (маршрут news.rss → RssController) должен быть
 * строго локальным и только для чтения:
 *  - статический /helpful_information/news/rss не перехватывается
 *    динамическим /helpful_information/news/{slug};
 *  - лента формируется из локальных записей таблицы news;
 *  - никаких внешних сетевых обращений (нет file_get_contents /
 *    simplexml_load_file);
 *  - никаких записей в базу во время GET;
 *  - выдаётся валидный RSS 2.0 c корректным экранированием XML.
 *
 * Стилистически повторяет HelpfulNewsPageTest и SyncNewsRssCommandTest:
 * RefreshDatabase, SQLite :memory:, Http::preventStrayRequests(),
 * снимок таблицы news до/после запроса.
 */
class HelpfulNewsRssRouteTest extends TestCase
{
    use RefreshDatabase;

    private const RSS_URL = '/helpful_information/news/rss';

    protected function setUp(): void
    {
        parent::setUp();

        // Response-кэш маршрута (middleware cache.response) кэширует тело по
        // полному URL. Чистим кэш перед каждым тестом для детерминизма.
        Cache::flush();
    }

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
     * Разобрать XML строго: well-formed, без ошибок libxml.
     */
    private function parseXml(string $xml): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new \DOMDocument();
        $ok = $doc->loadXML($xml);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertTrue($ok, 'RSS output must be well-formed XML');
        $this->assertSame([], $errors, 'RSS output must produce no XML parse errors');

        return $doc;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function newsSnapshot(): array
    {
        return News::withTrashed()
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'link', 'description', 'image', 'pub_date', 'deleted_at'])
            ->map(fn ($row) => $row->getAttributes())
            ->all();
    }

    /** R1: статический /news/rss резолвится в news.rss, а не в {slug}. */
    public function test_rss_url_resolves_to_rss_route_not_slug(): void
    {
        $route = Route::getRoutes()->match(Request::create(self::RSS_URL, 'GET'));

        $this->assertSame('news.rss', $route->getName());
        $this->assertNotSame('helpful_news_id.index', $route->getName());
    }

    /** R2: обычная страница /news/{slug} по-прежнему резолвится и рендерится. */
    public function test_normal_slug_page_still_resolves(): void
    {
        $route = Route::getRoutes()->match(Request::create('/helpful_information/news/some-slug', 'GET'));
        $this->assertSame('helpful_news_id.index', $route->getName());

        $this->makeNews('Regular News', 'regular', '2024-01-01 10:00:00', ['slug' => 'some-slug']);

        $this->get('/helpful_information/news/some-slug')->assertOk();
    }

    /** R3: валидный ответ — 200, RSS/XML UTF-8, разбираемый RSS 2.0 с channel. */
    public function test_valid_rss_response(): void
    {
        $this->makeNews('Channel News', '1', '2024-01-01 10:00:00');

        $response = $this->get(self::RSS_URL);
        $response->assertOk();

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/rss+xml', (string) $contentType);
        $this->assertStringContainsStringIgnoringCase('utf-8', (string) $contentType);

        $doc = $this->parseXml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $this->assertSame(1, $xpath->query('/rss[@version="2.0"]')->length, 'rss@version=2.0 must exist');
        $this->assertSame(1, $xpath->query('/rss/channel')->length, 'channel must exist');
        $this->assertSame(1, $xpath->query('/rss/channel/title')->length);
        $this->assertSame(1, $xpath->query('/rss/channel/link')->length);
        $this->assertSame(1, $xpath->query('/rss/channel/description')->length);
        $this->assertSame('ru', $xpath->evaluate('string(/rss/channel/language)'));
    }

    /** R4: данные элементов приходят из локально созданных записей News. */
    public function test_items_come_from_local_records(): void
    {
        $this->makeNews('Local Title One', '1', '2024-01-02 10:00:00');
        $this->makeNews('Local Title Two', '2', '2024-01-01 10:00:00');

        $doc = $this->parseXml($this->get(self::RSS_URL)->getContent());
        $xpath = new \DOMXPath($doc);

        $titles = [];
        foreach ($xpath->query('/rss/channel/item/title') as $node) {
            $titles[] = $node->textContent;
        }

        $this->assertSame(['Local Title One', 'Local Title Two'], $titles);

        $this->assertSame(
            'https://example.com/news/1',
            $xpath->evaluate('string(/rss/channel/item[1]/link)')
        );
        $this->assertSame(
            'https://example.com/news/1',
            $xpath->evaluate('string(/rss/channel/item[1]/guid)')
        );
    }

    /** R5: детерминированный порядок — pub_date DESC, затем id DESC. */
    public function test_deterministic_ordering(): void
    {
        $this->makeNews('Oldest', '1', '2024-01-01 10:00:00');   // id 1
        $this->makeNews('Tie Older Id', '2', '2024-01-03 10:00:00'); // id 2
        $this->makeNews('Tie Newer Id', '3', '2024-01-03 10:00:00'); // id 3

        $doc = $this->parseXml($this->get(self::RSS_URL)->getContent());
        $xpath = new \DOMXPath($doc);

        $links = [];
        foreach ($xpath->query('/rss/channel/item/link') as $node) {
            $links[] = $node->textContent;
        }

        // pub_date DESC ставит группу 2024-01-03 вперёд; внутри неё id DESC → slug-3, slug-2.
        $this->assertSame([
            'https://example.com/news/3',
            'https://example.com/news/2',
            'https://example.com/news/1',
        ], $links);
    }

    /** R6: soft-deleted записи отсутствуют в ленте. */
    public function test_soft_deleted_records_are_absent(): void
    {
        $this->makeNews('Visible', '1', '2024-01-02 10:00:00');
        $trashed = $this->makeNews('Trashed', '2', '2024-01-01 10:00:00');
        $trashed->delete();
        $this->assertSoftDeleted('news', ['id' => $trashed->id]);

        $doc = $this->parseXml($this->get(self::RSS_URL)->getContent());
        $xpath = new \DOMXPath($doc);

        $titles = [];
        foreach ($xpath->query('/rss/channel/item/title') as $node) {
            $titles[] = $node->textContent;
        }

        $this->assertSame(['Visible'], $titles);
        $this->assertSame(0, $xpath->query('/rss/channel/item[link="https://example.com/news/2"]')->length);
    }

    /** R7: спецсимволы и HTML в полях экранируются и не ломают XML. */
    public function test_special_characters_and_html_are_escaped(): void
    {
        $title = 'Tom & Jerry <"quoted"> \'apos\'';
        $description = '<b>Bold</b> & <script>alert(1)</script> ]]> end';
        $link = 'https://example.com/news?a=1&b=2&x=<y>';

        $this->makeNews($title, 'x', '2024-01-01 10:00:00', [
            'slug' => 'special-slug',
            'link' => $link,
            'description' => $description,
        ]);

        $doc = $this->parseXml($this->get(self::RSS_URL)->getContent());
        $xpath = new \DOMXPath($doc);

        // Разобранные значения совпадают с исходным логическим текстом.
        $this->assertSame($title, $xpath->evaluate('string(/rss/channel/item[1]/title)'));
        $this->assertSame($description, $xpath->evaluate('string(/rss/channel/item[1]/description)'));
        $this->assertSame($link, $xpath->evaluate('string(/rss/channel/item[1]/link)'));
        $this->assertSame($link, $xpath->evaluate('string(/rss/channel/item[1]/guid)'));

        // HTML внутри description — это текст, а не разметка: никаких дочерних узлов-элементов.
        $descNodes = $xpath->query('/rss/channel/item[1]/description/*');
        $this->assertSame(0, $descNodes->length, 'HTML in description must be escaped text, not markup');
    }

    /** R8: элемент с null pub_date не содержит pubDate и не выдаёт выдуманную дату. */
    public function test_null_pub_date_has_no_pubdate_element(): void
    {
        $this->makeNews('With Date', '1', '2024-01-02 10:00:00');
        $this->makeNews('No Date', '2', null);

        $doc = $this->parseXml($this->get(self::RSS_URL)->getContent());
        $xpath = new \DOMXPath($doc);

        $withDate = $xpath->query('/rss/channel/item[link="https://example.com/news/1"]/pubDate');
        $noDate = $xpath->query('/rss/channel/item[link="https://example.com/news/2"]/pubDate');

        $this->assertSame(1, $withDate->length, 'item with pub_date must expose pubDate');
        $this->assertSame(0, $noDate->length, 'item with null pub_date must omit pubDate');
    }

    /** R9: пустая таблица даёт валидный channel без item. */
    public function test_empty_table_produces_valid_channel_without_items(): void
    {
        $response = $this->get(self::RSS_URL);
        $response->assertOk();

        $doc = $this->parseXml($response->getContent());
        $xpath = new \DOMXPath($doc);

        $this->assertSame(1, $xpath->query('/rss/channel')->length);
        $this->assertSame(0, $xpath->query('/rss/channel/item')->length);
    }

    /** R10: GET читает локально, не ходит в сеть и ничего не пишет в news. */
    public function test_get_is_offline_and_read_only(): void
    {
        $this->makeNews('Snapshot One', '1', '2024-01-01 10:00:00');
        $this->makeNews('Snapshot Two', '2', '2024-01-02 10:00:00');
        $trashed = $this->makeNews('Snapshot Trashed', '3', '2024-01-03 10:00:00');
        $trashed->delete();

        $before = $this->newsSnapshot();

        Http::preventStrayRequests();
        Http::fake();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) && stripos($query->sql, 'news') !== false) {
                $writes[] = $query->sql;
            }
        });

        $this->get(self::RSS_URL)->assertOk();

        Http::assertNothingSent();
        $this->assertSame([], $writes, 'RSS GET must not issue insert/update/delete on news');
        $this->assertSame($before, $this->newsSnapshot());
    }

    /** R11: статический контроль исходников — никаких внешних обращений/записи файла. */
    public function test_sources_contain_no_external_requests(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/HelpfulInformation/RssController.php'));
        $view = file_get_contents(base_path('resources/views/news/rss.blade.php'));

        foreach (['controller' => $controller, 'view' => $view] as $label => $source) {
            $this->assertStringNotContainsString('file_get_contents', $source, "{$label} must not use file_get_contents");
            $this->assertStringNotContainsString('http://', $source, "{$label} must not contain an external http URL");
            $this->assertStringNotContainsString('https://', $source, "{$label} must not contain an external https URL");
        }

        $this->assertStringNotContainsString('simplexml_load_file', $view, 'view must not load an external SimpleXML feed');
        $this->assertStringNotContainsString('asXML', $view, 'view must not write an RSS file via asXML');
    }
}
