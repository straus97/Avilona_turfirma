<?php

namespace Tests\Feature;

use App\Models\News;
use App\Services\Content\PublicContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NOV-PUBLIC-02: репетиция импорта публичного контента на синтетическом файле.
 *
 * Все данные здесь выдуманы для проверки механики (маркер SYNTHETIC); это не
 * контент сайта и не копия какой-либо базы. Реальных выгрузок, боевых и
 * rebuild-баз тест не касается.
 */
class PublicContentImportRehearsalTest extends TestCase
{
    use RefreshDatabase;

    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function file(array $tables, string $format = PublicContentImporter::FORMAT): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pci');
        file_put_contents($path, json_encode(['format' => $format, 'tables' => $tables], JSON_UNESCAPED_UNICODE));
        $this->tmp[] = $path;

        return $path;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function fixture(): array
    {
        $news = [];
        for ($i = 1; $i <= 7; $i++) {
            $news[] = ['title' => "SYNTHETIC новость {$i}", 'slug' => "synthetic-news-{$i}", 'link' => "https://example.com/n/{$i}",
                'description' => "<p>SYNTHETIC текст новости {$i}.</p>", 'pub_date' => '2026-01-0' . $i . ' 10:00:00'];
        }

        return [
            'countries_images' => [
                ['title' => 'SYNTHETIC Страна 1', 'slug' => 'synthetic-country-1', 'category' => 'Европа', 'description' => '<p>SYNTHETIC описание.</p>',
                    'image_small' => 'img/logo.png'],
                ['title' => 'SYNTHETIC Страна 2', 'slug' => 'synthetic-country-2', 'category' => 'Азия', 'description' => '<p>SYNTHETIC описание 2.</p>',
                    'image_large' => 'img/missing-synthetic-asset.jpg'],
            ],
            'destination_images' => [['title' => 'SYNTHETIC Направление', 'slug' => 'synthetic-destination', 'description' => '<p>SYNTHETIC.</p>']],
            'articles' => [['title' => 'SYNTHETIC Статья', 'slug' => 'synthetic-article', 'content' => '<p>SYNTHETIC статья.</p>']],
            'news' => $news,
            'our_clients' => [['title' => 'SYNTHETIC Материал', 'slug' => 'synthetic-client', 'content' => '<p>SYNTHETIC.</p>']],
            'employees' => [['name' => 'SYNTHETIC Сотрудник', 'position' => 'Менеджер', 'tel' => '+70000000000',
                'email' => 'synthetic@example.invalid', 'whatsapp' => '+70000000000', 'vk' => 'https://example.invalid/vk']],
            'awards' => [['category' => 'SYNTHETIC награда']],
            'partners' => [['name_partner' => 'SYNTHETIC партнёр']],
            'best_offers' => [['title' => 'SYNTHETIC предложение', 'content' => 'SYNTHETIC']],
        ];
    }

    private function dryRun(array $tables, array $options = [])
    {
        return $this->artisan('content:import-public', array_merge(['file' => $this->file($tables)], $options));
    }

    public function test_dry_run_validates_and_writes_nothing(): void
    {
        $this->dryRun($this->fixture())
            ->expectsOutputToContain('Режим dry-run')
            ->assertExitCode(0);

        foreach (array_keys($this->fixture()) as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} must stay empty in dry-run");
        }
    }

    public function test_commit_imports_a_site_that_renders_and_whose_sitemap_matches_exactly(): void
    {
        $this->dryRun($this->fixture(), ['--commit' => true, '--force' => true])->assertExitCode(0);

        $this->assertSame(7, News::count());
        $this->assertSame(2, DB::table('countries_images')->count());

        // карта сайта = ровно импортированное подмножество (+ статические страницы), без ?page=1
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $paths = [];
        foreach ($xml->url as $url) {
            $paths[] = substr(trim((string) $url->loc), strlen('https://avilona.ru'));
        }

        $expected = [
            '/', '/tours', '/contacts', '/company/about_company', '/helpful_information/travel_dictionary',
            '/company/employees', '/company/awards',
            '/countries', '/countries/synthetic-country-1', '/countries/synthetic-country-2',
            '/destinations', '/destinations/synthetic-destination',
            '/helpful_information/interesting_articles', '/helpful_information/interesting_articles/synthetic-article',
            '/helpful_information/news', '/helpful_information/news?page=2',
            '/helpful_information/for_our_clients', '/helpful_information/for_our_clients/synthetic-client',
        ];
        for ($i = 1; $i <= 7; $i++) {
            $expected[] = "/helpful_information/news/synthetic-news-{$i}";
        }
        sort($expected);
        sort($paths);
        $this->assertSame($expected, $paths);

        // каждая страница из карты доступна, имеет один H1 и свой canonical; пустых <img src=""> нет
        foreach ($paths as $path) {
            $response = $this->get($path)->assertOk();
            $html = $response->getContent();
            $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html), "{$path}: one H1");
            $this->assertStringContainsString('<link rel="canonical" href="https://avilona.ru' . ($path === '/' ? '/' : $path) . '">', $html, $path);
            $this->assertStringNotContainsString('src=""', $html, $path);
        }
    }

    public function test_html_is_sanitised_on_import_and_timestamps_are_preserved(): void
    {
        $tables = ['news' => [[
            'title' => 'SYNTHETIC', 'slug' => 'synthetic-xss', 'link' => 'https://example.com/x',
            'description' => '<p>Видимый текст</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            'updated_at' => '2026-02-03 04:05:06',
        ]]];

        $this->dryRun($tables, ['--commit' => true, '--force' => true])->assertExitCode(0);

        $row = News::where('slug', 'synthetic-xss')->firstOrFail();
        $this->assertStringContainsString('Видимый текст', $row->description);
        $this->assertStringNotContainsString('<script', $row->description);
        $this->assertStringNotContainsString('onerror', $row->description);
        $this->assertSame('2026-02-03 04:05:06', $row->updated_at->toDateTimeString());
    }

    public function test_local_image_paths_are_stored_root_absolute_so_they_resolve_on_any_page_depth(): void
    {
        $this->dryRun($this->fixture(), ['--commit' => true, '--force' => true])->assertExitCode(0);

        $this->assertSame('/img/logo.png', DB::table('countries_images')->where('slug', 'synthetic-country-1')->value('image_small'));

        $tables = ['news' => [['title' => 'SYNTHETIC', 'slug' => 'synthetic-img', 'link' => 'https://example.com/i',
            'description' => 'd', 'image' => 'https://cdn.example.com/i.jpg']]];
        DB::table('news')->delete();
        $this->dryRun($tables, ['--commit' => true, '--force' => true])->assertExitCode(0);
        $this->assertSame('https://cdn.example.com/i.jpg', News::where('slug', 'synthetic-img')->value('image'));
    }

    public function test_missing_image_asset_is_reported_as_a_warning_not_a_failure(): void
    {
        $this->dryRun($this->fixture())
            ->expectsOutputToContain('missing-synthetic-asset.jpg')
            ->assertExitCode(0);
    }

    public function test_non_content_tables_and_personal_fields_are_refused_outright(): void
    {
        foreach (['users', 'bookings', 'inquiries', 'messages', 'reviews', 'review_consents', 'incoming_inquiries'] as $table) {
            $this->dryRun([$table => [['name' => 'x']]])
                ->expectsOutputToContain('не входит в список публичного контента')
                ->assertExitCode(1);
        }

        $tables = $this->fixture();
        $tables['employees'][0]['password'] = 'secret';
        $this->dryRun($tables, ['--commit' => true, '--force' => true])
            ->expectsOutputToContain('недопустимые поля: password')
            ->assertExitCode(1);

        $this->assertSame(0, DB::table('employees')->count());
    }

    public function test_invalid_records_block_the_whole_import(): void
    {
        $cases = [
            'missing slug' => fn (&$t) => $t['news'][0]['slug'] = '',
            'duplicate slug' => fn (&$t) => $t['news'][1]['slug'] = $t['news'][0]['slug'],
            'bad slug' => fn (&$t) => $t['news'][0]['slug'] = 'a/b',
            'dot slug' => fn (&$t) => $t['news'][0]['slug'] = '.hidden',
            'js image' => fn (&$t) => $t['countries_images'][0]['image_small'] = 'javascript:alert(1)',
            'traversal image' => fn (&$t) => $t['countries_images'][0]['image_small'] = '../.env',
            'http image' => fn (&$t) => $t['countries_images'][0]['image_small'] = 'http://example.com/a.jpg',
            'bad link' => fn (&$t) => $t['news'][0]['link'] = 'javascript:alert(1)',
            'bad date' => fn (&$t) => $t['news'][0]['pub_date'] = 'not-a-date',
            'long title' => fn (&$t) => $t['articles'][0]['title'] = str_repeat('я', 256),
            'missing required' => fn (&$t) => $t['countries_images'][0]['category'] = '',
        ];

        foreach ($cases as $name => $mutate) {
            $tables = $this->fixture();
            $mutate($tables);

            $this->dryRun($tables, ['--commit' => true, '--force' => true])->assertExitCode(1);
            $this->assertSame(0, News::count(), "{$name}: nothing may be written");
        }
    }

    public function test_wrong_format_and_unreadable_files_are_rejected(): void
    {
        $this->artisan('content:import-public', ['file' => $this->file($this->fixture(), 'something/else')])->assertExitCode(1);
        $this->artisan('content:import-public', ['file' => '/nonexistent/path.json'])->assertExitCode(1);

        $bad = tempnam(sys_get_temp_dir(), 'pci');
        $this->tmp[] = $bad;
        file_put_contents($bad, '{not json');
        $this->artisan('content:import-public', ['file' => $bad])->assertExitCode(1);
    }

    public function test_commit_is_atomic_and_refuses_non_empty_targets_and_slug_collisions(): void
    {
        News::create(['title' => 'existing', 'slug' => 'synthetic-news-1', 'link' => 'https://example.com/e',
            'description' => 'd', 'image' => null, 'pub_date' => now()]);

        // совпадение slug с уже существующей записью найдено на проверке
        $this->dryRun($this->fixture())
            ->expectsOutputToContain('уже есть в целевой базе')
            ->assertExitCode(1);

        // непустая таблица news: запись отклоняется целиком, остальные таблицы не изменяются
        $tables = $this->fixture();
        $tables['news'] = [['title' => 'other', 'slug' => 'other-news', 'link' => 'https://example.com/o', 'description' => 'd']];
        $this->dryRun($tables, ['--commit' => true, '--force' => true])->assertExitCode(1);

        $this->assertSame(1, News::count());
        $this->assertSame(0, DB::table('countries_images')->count(), 'earlier tables rolled back');
    }
}
