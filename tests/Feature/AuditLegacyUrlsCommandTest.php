<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NOV-PUBLIC-03A: seo:audit-urls — только чтение, без сети, без аккаунтов поисковиков.
 * Вход — синтетический CSV; настоящих выгрузок нет.
 */
class AuditLegacyUrlsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audit-urls-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function csv(string $contents, string $name = 'in.csv'): string
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    /** @return array<int, array<string, string>> */
    private function readReport(string $path): array
    {
        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle, 0, ',', '"', '');
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rows;
    }

    public function test_it_classifies_rows_without_opening_the_database_or_the_network(): void
    {
        $input = $this->csv(implode("\n", [
            'url,source,note',
            'https://avilona.ru/countries/egipet,indexed,',
            'https://www.avilona.ru/countries/17,not_indexed_404,numeric id from owner screenshot',
            '/helpful_information/news?page=1,sitemap,',
            '/helpful_information/news?page=16,sitemap,',
            '/reviews?page=abc,other,',
            '/documents/User_Agreement.pdf,indexed,',
            '/documents/x.docx,other,',
            '/login,other,',
            '/cabinet,other,',
            '/no/such/page,not_indexed_404,',
            'https://example.org/foreign,other,',
            'ftp://avilona.ru/x,other,',
            '/countries?page=3&utm_source=ads,other,',
            '/tours?utm_source=ads,other,',
        ]) . "\n");
        $report = $this->dir . DIRECTORY_SEPARATOR . 'out.csv';

        DB::enableQueryLog();
        $this->artisan('seo:audit-urls', ['file' => $input, '--output' => $report])->assertSuccessful();
        $this->assertSame([], DB::getQueryLog(), 'without --check-db the command must not query the database');

        $rows = $this->readReport($report);
        $byUrl = array_column($rows, null, 'url');

        $this->assertSame('supported_slug', $byUrl['https://avilona.ru/countries/egipet']['class']);
        $this->assertSame('legacy_numeric_id', $byUrl['https://www.avilona.ru/countries/17']['class']);
        $this->assertSame('owner_evidence', $byUrl['https://www.avilona.ru/countries/17']['action']);
        $this->assertSame('', $byUrl['https://www.avilona.ru/countries/17']['redirect_target'], 'numeric ids never get a target');
        $this->assertSame('redirect_first_page', $byUrl['/helpful_information/news?page=1']['class']);
        $this->assertSame('/helpful_information/news', $byUrl['/helpful_information/news?page=1']['redirect_target']);
        $this->assertSame('yes', $byUrl['/helpful_information/news?page=1']['in_legacy_sitemap']);
        $this->assertSame('supported_pagination', $byUrl['/helpful_information/news?page=16']['class']);
        $this->assertSame('redirect_first_page', $byUrl['/reviews?page=abc']['class']);
        $this->assertSame('document_file', $byUrl['/documents/User_Agreement.pdf']['class']);
        $this->assertSame('document_editable_source', $byUrl['/documents/x.docx']['class']);
        $this->assertSame('yes', $byUrl['/documents/x.docx']['blocked_by_robots']);
        $this->assertSame('private_or_service', $byUrl['/login']['class']);
        $this->assertSame('yes', $byUrl['/login']['blocked_by_robots']);
        $this->assertSame('private_or_service', $byUrl['/cabinet']['class']);
        $this->assertSame('never_redirect', $byUrl['/cabinet']['action']);
        $this->assertSame('no_route', $byUrl['/no/such/page']['class']);
        $this->assertSame('serve_404', $byUrl['/no/such/page']['action']);
        $this->assertSame('foreign_host', $byUrl['https://example.org/foreign']['class']);
        $this->assertSame('invalid_url', $byUrl['ftp://avilona.ru/x']['class']);
        $this->assertSame('page_param_ignored', $byUrl['/countries?page=3&utm_source=ads']['class']);
        $this->assertSame('tracking_param', $byUrl['/tours?utm_source=ads']['class']);
    }

    public function test_check_db_reports_slug_presence_with_exists_queries_only(): void
    {
        Countries_image::create(['title' => 'SYNTHETIC', 'slug' => 'egipet', 'category' => 'x', 'description' => 'd']);
        News::create(['title' => 'SYNTHETIC', 'slug' => 'novost', 'link' => 'https://example.com/n', 'description' => 'd', 'image' => null, 'pub_date' => now()]);

        $input = $this->csv("url\n/countries/egipet\n/countries/neegipet\n/helpful_information/news/novost\n/helpful_information/news/propala\n/countries/42\n");
        $report = $this->dir . DIRECTORY_SEPARATOR . 'db.csv';

        config(['database.connections.sqlite.qa_disposable' => true]); // :memory: — одноразовая синтетическая база

        $before = [Countries_image::count(), News::count()];
        DB::enableQueryLog();
        $this->artisan('seo:audit-urls', ['file' => $input, '--output' => $report, '--check-db' => true, '--connection' => 'sqlite'])->assertSuccessful();

        foreach (DB::getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query'], 'read-only: ' . $query['query']);
        }
        $this->assertSame($before, [Countries_image::count(), News::count()]);

        $byUrl = array_column($this->readReport($report), null, 'url');
        $this->assertSame('slug_present', $byUrl['/countries/egipet']['class']);
        $this->assertSame('slug_missing', $byUrl['/countries/neegipet']['class']);
        $this->assertSame('owner_evidence', $byUrl['/countries/neegipet']['action']);
        $this->assertSame('slug_present', $byUrl['/helpful_information/news/novost']['class']);
        $this->assertSame('slug_missing', $byUrl['/helpful_information/news/propala']['class']);
        $this->assertSame('legacy_numeric_id', $byUrl['/countries/42']['class'], 'a numeric segment is never looked up as a slug');
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function unsafeConnections(): array
    {
        $mysql = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3397', 'database' => 'aoa03_qa_audit', 'username' => 'qa', 'password' => 'qa', 'unix_socket' => '', 'qa_disposable' => true];

        return [
            'canonical database' => [array_merge($mysql, ['database' => 'turfirma_rebuild_v4'])],
            'WAMP port 3306' => [array_merge($mysql, ['port' => '3306'])],
            'WAMP port 3308' => [array_merge($mysql, ['port' => '3308'])],
            'not marked disposable' => [array_merge($mysql, ['qa_disposable' => false])],
            'marker as string' => [array_merge($mysql, ['qa_disposable' => 'true'])],
            'non-loopback host' => [array_merge($mysql, ['host' => '10.0.0.5'])],
            'ordinary database name' => [array_merge($mysql, ['database' => 'avilona'])],
            'url override' => [array_merge($mysql, ['url' => 'mysql://root@127.0.0.1:3308/turfirma_rebuild_v4'])],
            'empty configuration' => [['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => null, 'database' => null, 'qa_disposable' => false]],
            'unsupported driver' => [['driver' => 'pgsql', 'qa_disposable' => true]],
            'sqlite file outside temp' => [['driver' => 'sqlite', 'database' => __DIR__ . '/../../database/database.sqlite', 'qa_disposable' => true]],
        ];
    }

    /**
     * @dataProvider unsafeConnections
     * @param array<string, mixed> $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeConnections')]
    public function test_check_db_refuses_unsafe_targets_without_any_query(array $config): void
    {
        config(['database.connections.qa_unsafe' => $config]);
        $input = $this->csv("url
/countries/egipet
");
        $report = $this->dir . DIRECTORY_SEPARATOR . 'refused.csv';

        DB::enableQueryLog();
        $this->artisan('seo:audit-urls', ['file' => $input, '--output' => $report, '--check-db' => true, '--connection' => 'qa_unsafe'])
            ->expectsOutputToContain('REFUSED')
            ->assertFailed();

        $this->assertSame([], DB::getQueryLog(), 'no query on the default connection');
        $this->assertArrayNotHasKey('qa_unsafe', DB::getConnections(), 'the protected target was never opened');
        $this->assertFileDoesNotExist($report);
    }

    public function test_check_db_refuses_without_a_connection_and_never_uses_the_default_one(): void
    {
        $input = $this->csv("url
/countries/egipet
");
        config(['database.connections.sqlite.qa_disposable' => true]); // даже помеченное по умолчанию подключение не берётся неявно

        DB::enableQueryLog();
        $this->artisan('seo:audit-urls', ['file' => $input, '--check-db' => true])->expectsOutputToContain('REFUSED')->assertFailed();
        $this->artisan('seo:audit-urls', ['file' => $input, '--check-db' => true, '--connection' => ''])->assertFailed();
        $this->artisan('seo:audit-urls', ['file' => $input, '--check-db' => true, '--connection' => 'no_such'])->assertFailed();
        $this->artisan('seo:audit-urls', ['file' => $input, '--check-db' => true, '--connection' => 'sqlite; drop'])->assertFailed();
        $this->artisan('seo:audit-urls', ['file' => $input, '--connection' => 'sqlite'])->assertFailed(); // --connection без --check-db

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_default_configuration_is_refused_and_output_leaks_no_paths_or_secrets(): void
    {
        $input = $this->csv("url
/countries/egipet
");
        config(['database.connections.qa_unsafe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3308', 'database' => 'turfirma_rebuild_v4', 'username' => 'root', 'password' => 'SECRET-PW-123', 'qa_disposable' => true]]);

        $this->artisan('seo:audit-urls', ['file' => $input, '--check-db' => true, '--connection' => 'qa_unsafe']);
        $output = Artisan::output();

        foreach (['SECRET-PW-123', 'turfirma_rebuild_v4', $this->dir, 'root'] as $leak) {
            $this->assertStringNotContainsString($leak, $output);
        }
    }

    public function test_malformed_input_stays_safe_with_a_verified_target(): void
    {
        config(['database.connections.sqlite.qa_disposable' => true]);
        $input = $this->csv("url
/countries/%00x

,\"unterminated
//evil.example/x
/countries/" . str_repeat('a', 3000) . "
javascript:alert(1)
");
        $report = $this->dir . DIRECTORY_SEPARATOR . 'm.csv';

        $this->artisan('seo:audit-urls', ['file' => $input, '--output' => $report, '--check-db' => true, '--connection' => 'sqlite'])->assertSuccessful();

        foreach ($this->readReport($report) as $row) {
            $this->assertContains($row['class'], ['invalid_url', 'slug_missing', 'no_route', 'foreign_host']);
        }
    }

    public function test_it_accepts_semicolon_and_bom_input_and_neutralizes_spreadsheet_formulas(): void
    {
        $input = $this->csv("\xEF\xBB\xBFURL;Source;Note\n/countries/egipet;indexed;=HYPERLINK(\"http://evil.example\")\n/contacts;SITEMAP;+cmd\n");
        $report = $this->dir . DIRECTORY_SEPARATOR . 'formula.csv';

        $this->artisan('seo:audit-urls', ['file' => $input, '--output' => $report])->assertSuccessful();

        $rows = $this->readReport($report);
        $this->assertCount(2, $rows);
        $this->assertSame("'=HYPERLINK(\"http://evil.example\")", $rows[0]['note']);
        $this->assertSame("'+cmd", $rows[1]['note']);
        $this->assertSame('sitemap', $rows[1]['source']);
    }

    public function test_it_rejects_bad_input_and_never_overwrites_a_report(): void
    {
        $this->artisan('seo:audit-urls', ['file' => $this->dir . DIRECTORY_SEPARATOR . 'missing.csv'])->assertFailed();
        $this->artisan('seo:audit-urls', ['file' => $this->csv("path,source\n/x,other\n", 'nourl.csv')])->assertFailed();

        $existing = $this->csv('keep', 'existing.csv');
        $this->artisan('seo:audit-urls', ['file' => $this->csv("url\n/contacts\n"), '--output' => $existing])->assertFailed();
        $this->assertSame('keep', file_get_contents($existing));
    }

    public function test_the_command_is_read_only_and_has_no_network_or_account_code(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/AuditLegacyUrlsCommand.php'))
            . (string) file_get_contents(base_path('app/Services/Seo/LegacyUrlClassifier.php'));

        foreach (['Http::', 'curl_', 'file_get_contents(\'http', 'Guzzle', '->insert(', '->update(', '->delete(', '->save(', 'firstOrCreate', 'updateOrCreate', 'Redirect::', 'redirect('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, "forbidden construct {$forbidden}");
        }
    }
}
