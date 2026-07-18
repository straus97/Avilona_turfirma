<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Явная синхронизация RSS через команду news:sync-rss
 * (App\Services\News\RssNewsSyncService). Все сетевые обращения замоканы,
 * база — SQLite :memory:. Проверяются идемпотентность, сохранение публичных
 * URL, политика soft-delete, уникальность slug (в т.ч. в пределах одного
 * фида и с учётом trashed), обработка неоднозначных данных, атомарность
 * (откат при ошибке записи) и детерминированные коды возврата.
 */
class SyncNewsRssCommandTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://www.turizm.ru/news/rss/yandex/';

    protected function tearDown(): void
    {
        // Снять возможные временные слушатели событий модели (тест отката).
        News::getEventDispatcher()->forget('eloquent.creating: ' . News::class);

        parent::tearDown();
    }

    /**
     * Собрать валидный RSS из массива элементов.
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

    private function seedNews(array $attributes): News
    {
        return News::create(array_merge([
            'title' => 'Seed title',
            'slug' => 'seed-slug',
            'link' => 'https://example.com/seed',
            'description' => 'Seed description',
            'image' => null,
            'pub_date' => '2024-01-01 10:00:00',
        ], $attributes));
    }

    /** S1 + S5(part): encoded-контент попадает в description (с очисткой). */
    public function test_encoded_content_maps_to_description(): void
    {
        $this->fakeFeed($this->rss([[
            'title' => 'Title A',
            'link' => 'https://example.com/a',
            'encoded' => '<p>Body</p><p>&nbsp;</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $row = News::where('link', 'https://example.com/a')->firstOrFail();
        $this->assertSame('<p>Body</p>', $row->description);
    }

    /** S2: синхронизация не пытается сохранить атрибут content. */
    public function test_never_persists_content_attribute(): void
    {
        $this->assertFalse(Schema::hasColumn('news', 'content'));

        $this->fakeFeed($this->rss([[
            'title' => 'Title A',
            'link' => 'https://example.com/a',
            'encoded' => '<p>Body</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $this->assertArrayNotHasKey('content', News::firstOrFail()->getAttributes());
    }

    /** S3: успешный прогон возвращает код 0. */
    public function test_success_exit_code(): void
    {
        $this->fakeFeed($this->rss([[
            'title' => 'Title A',
            'link' => 'https://example.com/a',
            'encoded' => '<p>Body</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);
        $this->assertSame(1, News::count());
    }

    /** S4: повторный прогон идемпотентен — количество не растёт, строка обновляется на месте. */
    public function test_rerun_is_idempotent(): void
    {
        $xml = $this->rss([[
            'title' => 'Title A',
            'link' => 'https://example.com/a',
            'encoded' => '<p>Body</p>',
        ]]);
        $this->fakeFeed($xml);

        $this->artisan('news:sync-rss')->assertExitCode(0);
        $first = News::firstOrFail();

        $this->fakeFeed($xml);
        $this->artisan('news:sync-rss')->assertExitCode(0);

        $this->assertSame(1, News::count());
        $this->assertSame($first->id, News::firstOrFail()->id);
    }

    /** S5: совпадение по link сохраняет id и slug, даже если заголовок в фиде изменился. */
    public function test_existing_link_preserves_id_and_slug(): void
    {
        $existing = $this->seedNews([
            'link' => 'https://example.com/a',
            'slug' => 'original-slug',
            'title' => 'Old Title',
        ]);

        $this->fakeFeed($this->rss([[
            'title' => 'Completely New Title',
            'link' => 'https://example.com/a',
            'encoded' => '<p>Fresh body</p>',
        ]]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $row = News::findOrFail($existing->id);
        $this->assertSame($existing->id, $row->id);
        $this->assertSame('original-slug', $row->slug);
        $this->assertSame('Completely New Title', $row->title);
        $this->assertSame('<p>Fresh body</p>', $row->description);
        $this->assertSame(1, News::count());
    }

    /** S6: одинаковые заголовки в одном фиде получают slug same-title / same-title-2. */
    public function test_duplicate_titles_within_one_feed_get_unique_slugs(): void
    {
        $this->fakeFeed($this->rss([
            ['title' => 'Same Title', 'link' => 'https://example.com/a', 'encoded' => '<p>1</p>'],
            ['title' => 'Same Title', 'link' => 'https://example.com/b', 'encoded' => '<p>2</p>'],
        ]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $this->assertSame(2, News::count());
        $slugs = News::orderBy('id')->pluck('slug')->all();
        $this->assertSame(['same-title', 'same-title-2'], $slugs);
    }

    /** S7: коллизия с существующим активным slug → суффикс. */
    public function test_collision_with_active_slug(): void
    {
        $this->seedNews(['slug' => 'same-title', 'link' => 'https://example.com/existing']);

        $this->fakeFeed($this->rss([
            ['title' => 'Same Title', 'link' => 'https://example.com/a', 'encoded' => '<p>1</p>'],
        ]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $new = News::where('link', 'https://example.com/a')->firstOrFail();
        $this->assertSame('same-title-2', $new->slug);
    }

    /** S8: коллизия с soft-deleted slug → суффикс (UNIQUE в БД учитывает trashed). */
    public function test_collision_with_soft_deleted_slug(): void
    {
        $trashed = $this->seedNews(['slug' => 'same-title', 'link' => 'https://example.com/trashed']);
        $trashed->delete();
        $this->assertSoftDeleted('news', ['id' => $trashed->id]);

        $this->fakeFeed($this->rss([
            ['title' => 'Same Title', 'link' => 'https://example.com/a', 'encoded' => '<p>1</p>'],
        ]));

        $this->artisan('news:sync-rss')->assertExitCode(0);

        $new = News::where('link', 'https://example.com/a')->firstOrFail();
        $this->assertSame('same-title-2', $new->slug);
    }

    /** S9: link совпадает с soft-deleted строкой → пропуск, без восстановления и дубликата. */
    public function test_soft_deleted_matching_link_is_skipped(): void
    {
        $trashed = $this->seedNews(['slug' => 'trashed-slug', 'link' => 'https://example.com/a']);
        $trashed->delete();

        $this->fakeFeed($this->rss([
            ['title' => 'Title A', 'link' => 'https://example.com/a', 'encoded' => '<p>Body</p>'],
        ]));

        $this->artisan('news:sync-rss')
            ->expectsOutput('Synced 0 news items, skipped 1.')
            ->assertExitCode(0);

        // Не восстановлена и не создан дубликат.
        $this->assertSoftDeleted('news', ['id' => $trashed->id]);
        $this->assertSame(0, News::where('link', 'https://example.com/a')->count());
        $this->assertSame(1, News::withTrashed()->where('link', 'https://example.com/a')->count());
    }

    /** S10: несколько активных строк на один link → сбой всей синхронизации, без изменений. */
    public function test_ambiguous_active_duplicate_links_fail_without_changes(): void
    {
        $this->seedNews(['slug' => 'dup-1', 'link' => 'https://example.com/a', 'title' => 'Dup One']);
        $this->seedNews(['slug' => 'dup-2', 'link' => 'https://example.com/a', 'title' => 'Dup Two']);

        $this->fakeFeed($this->rss([
            ['title' => 'New Title', 'link' => 'https://example.com/a', 'encoded' => '<p>Body</p>'],
        ]));

        $this->artisan('news:sync-rss')->assertExitCode(1);

        // Обе строки без изменений.
        $this->assertSame('Dup One', News::where('slug', 'dup-1')->value('title'));
        $this->assertSame('Dup Two', News::where('slug', 'dup-2')->value('title'));
        $this->assertSame(2, News::count());
    }

    /**
     * S11: сетевая ошибка не меняет существующие новости и возвращает код 1;
     * в консоль выводится только санитизированное сообщение (без деталей HTTP),
     * а полное исключение логируется ровно один раз.
     */
    public function test_remote_failure_leaves_news_unchanged(): void
    {
        $this->seedNews(['slug' => 'keep', 'link' => 'https://example.com/keep', 'title' => 'Keep Me']);
        $before = News::orderBy('id')->get()->map->getAttributes()->all();

        Log::spy();
        Http::preventStrayRequests();
        Http::fake([self::FEED => Http::response('', 500)]);

        $this->artisan('news:sync-rss')
            ->expectsOutput('RSS news sync failed. See application log for details.')
            ->doesntExpectOutputToContain('500')
            ->assertExitCode(1);

        // Логирование ровно один раз, с контекстом исключения.
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn ($message, $context = []) => $message === 'RSS news sync failed'
                && isset($context['exception'])
                && $context['exception'] instanceof \Throwable);

        // Снимок базы не изменился.
        $this->assertSame($before, News::orderBy('id')->get()->map->getAttributes()->all());
    }

    /** S12: некорректный XML не меняет существующие новости и возвращает код 1. */
    public function test_malformed_xml_leaves_news_unchanged(): void
    {
        $this->seedNews(['slug' => 'keep', 'link' => 'https://example.com/keep', 'title' => 'Keep Me']);
        $before = News::orderBy('id')->get()->map->getAttributes()->all();

        Http::preventStrayRequests();
        Http::fake([self::FEED => Http::response('<not-xml', 200)]);

        $this->artisan('news:sync-rss')->assertExitCode(1);

        $this->assertSame($before, News::orderBy('id')->get()->map->getAttributes()->all());
    }

    /** S13: ошибка записи после первой строки откатывает всю транзакцию. */
    public function test_persistence_failure_rolls_back_batch(): void
    {
        $preexisting = $this->seedNews(['slug' => 'preexisting', 'link' => 'https://example.com/pre', 'title' => 'Pre Existing']);
        $before = News::withTrashed()->orderBy('id')->get()->map->getAttributes()->all();

        // Временный слушатель: бросает исключение при создании второй новой строки.
        $created = 0;
        News::creating(function () use (&$created) {
            $created++;
            if ($created === 2) {
                throw new \RuntimeException('forced failure on second insert');
            }
        });

        $this->fakeFeed($this->rss([
            ['title' => 'First New', 'link' => 'https://example.com/1', 'encoded' => '<p>1</p>'],
            ['title' => 'Second New', 'link' => 'https://example.com/2', 'encoded' => '<p>2</p>'],
        ]));

        $this->artisan('news:sync-rss')->assertExitCode(1);

        // Первая вставка откатилась; ранее существовавшие строки не изменились.
        $this->assertSame(1, News::count());
        $this->assertSame($preexisting->id, News::firstOrFail()->id);
        $this->assertSame($before, News::withTrashed()->orderBy('id')->get()->map->getAttributes()->all());
    }

    /** S14: нормализация ограничений колонок; слишком длинный link пропускается (без усечения). */
    public function test_field_constraints_are_normalized(): void
    {
        $longTitle = str_repeat('T', 300);
        $longLink = 'https://example.com/' . str_repeat('x', 300);
        $longImage = 'https://example.com/' . str_repeat('i', 300) . '.jpg';

        $this->fakeFeed($this->rss([
            // Валидный элемент с граничными значениями.
            [
                'title' => $longTitle,
                'link' => 'https://example.com/valid',
                'image' => $longImage,
                'pubDate' => 'not-a-real-date',
                'encoded' => '',
            ],
            // Идентификатор слишком длинный — элемент пропускается целиком.
            [
                'title' => 'Has Long Link',
                'link' => $longLink,
                'encoded' => '<p>x</p>',
            ],
        ]));

        $this->artisan('news:sync-rss')
            ->expectsOutput('Synced 1 news items, skipped 1.')
            ->assertExitCode(0);

        // Создана только валидная запись.
        $this->assertSame(1, News::count());
        $row = News::firstOrFail();
        $this->assertSame(255, mb_strlen($row->title));       // title усечён до 255
        $this->assertSame('https://example.com/valid', $row->link);
        $this->assertNull($row->image);                        // слишком длинный image → null
        $this->assertNull($row->pub_date);                     // некорректная дата → null
        $this->assertSame('', $row->description);              // пустой encoded → ''
        // Слишком длинный link не сохранён ни усечённым, ни целиком.
        $this->assertSame(0, News::where('link', 'like', 'https://example.com/xxx%')->count());
    }
}
