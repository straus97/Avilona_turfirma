<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Публичная страница новостей (маршрут helpful_news.index →
 * HelpfulNewsController) должна быть строго только для чтения:
 *  - рендерится из существующих записей таблицы news;
 *  - не выполняет внешних сетевых обращений;
 *  - не пишет и не изменяет данные (никаких insert/update/delete по news);
 *  - фильтрация/сортировка/пагинация продолжают работать.
 *
 * Замечание: старый код выполнял сетевой запрос через file_get_contents,
 * который HTTP-фейки Laravel не перехватывают. Удаление этого кода
 * подтверждается точечным diff контроллера; здесь дополнительно ставим
 * Http::preventStrayRequests(), чтобы любой незамоканный запрос упал.
 */
class HelpfulNewsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeNews(string $title, string $suffix, string $pubDate): News
    {
        return News::create([
            'title' => $title,
            'slug' => 'slug-' . $suffix,
            'link' => 'https://example.com/news/' . $suffix,
            'description' => 'Description ' . $suffix,
            'image' => null,
            'pub_date' => $pubDate,
        ]);
    }

    /** P1: страница успешно рендерится из записей базы. */
    public function test_renders_from_database_records(): void
    {
        $this->makeNews('Newsitem Alpha', '1', '2024-01-01 10:00:00');
        $this->makeNews('Newsitem Beta', '2', '2024-01-02 10:00:00');

        $response = $this->get('/helpful_information/news');

        $response->assertOk();
        $response->assertSee('Newsitem Alpha');
        $response->assertSee('Newsitem Beta');
    }

    /** P2: фильтрация по дате, сортировка по pub_date desc и пагинация не сломаны. */
    public function test_listing_filtering_and_pagination_intact(): void
    {
        // 8 записей, pub_date по возрастанию: Newsitem08 — самая свежая.
        for ($n = 1; $n <= 8; $n++) {
            $this->makeNews('Newsitem' . str_pad((string) $n, 2, '0', STR_PAD_LEFT), (string) $n, sprintf('2024-01-%02d 10:00:00', $n));
        }

        // Страница 1: 6 самых свежих (08..03), старые (02,01) не показаны.
        $page1 = $this->get('/helpful_information/news');
        $page1->assertOk();
        $page1->assertSee('Newsitem08');
        $page1->assertSee('Newsitem03');
        $page1->assertDontSee('Newsitem02');

        // Страница 2: оставшиеся старые записи.
        $page2 = $this->get('/helpful_information/news?page=2');
        $page2->assertOk();
        $page2->assertSee('Newsitem02');
        $page2->assertSee('Newsitem01');
        $page2->assertDontSee('Newsitem08');

        // Фильтр по дате: pub_date <= 2024-01-05 00:00:00 → записи 04..01
        // (04 в 10:00 проходит, 05 в 10:00 — уже позже полуночи 05-го — отсекается).
        $filtered = $this->get('/helpful_information/news?date=2024-01-05');
        $filtered->assertOk();
        $filtered->assertSee('Newsitem04');
        $filtered->assertDontSee('Newsitem05');
    }

    /** P3: GET не делает внешних запросов и не меняет ни одной строки news. */
    public function test_get_performs_no_external_request_and_no_writes(): void
    {
        $this->makeNews('Snapshot One', '1', '2024-01-01 10:00:00');
        $this->makeNews('Snapshot Two', '2', '2024-01-02 10:00:00');
        $this->makeNews('Snapshot Three', '3', '2024-01-03 10:00:00');

        $before = $this->newsSnapshot();

        Http::preventStrayRequests();
        Http::fake();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) && stripos($query->sql, 'news') !== false) {
                $writes[] = $query->sql;
            }
        });

        $response = $this->get('/helpful_information/news');
        $response->assertOk();

        // Никаких внешних HTTP-обращений.
        Http::assertNothingSent();
        // Никаких мутаций таблицы news.
        $this->assertSame([], $writes, 'GET must not issue insert/update/delete on news');
        // Полный логический снимок не изменился.
        $this->assertSame($before, $this->newsSnapshot());
    }

    /**
     * E2-A5-I1: страница списка новостей должна публиковать ровно одну ссылку
     * RSS-автообнаружения в <head>, указывающую на route('news.rss'). Ссылка
     * добавляется через общий layout-хук @yield('head_extra'); сам RSS-фид и
     * его контроллер/шаблон при этом не редактируются.
     */
    public function test_listing_page_advertises_rss_autodiscovery_link(): void
    {
        $this->makeNews('Rss Discovery News', 'rss-disc', '2024-01-01 10:00:00');

        $response = $this->get('/helpful_information/news');
        $response->assertOk();

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//head/link[@rel="alternate" and @type="application/rss+xml"]');

        $this->assertSame(1, $links->length, 'News listing must expose exactly one RSS autodiscovery link');
        $this->assertSame(route('news.rss'), $links->item(0)->getAttribute('href'));
    }

    /**
     * Снимок всех строк news (включая soft-deleted) по значимым атрибутам.
     *
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
}
