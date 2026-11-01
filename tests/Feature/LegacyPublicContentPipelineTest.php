<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use App\Services\Content\Legacy\LegacyPublicContentPipeline;
use App\Services\Content\Legacy\LegacyUrlReconciler;
use App\Services\Seo\LegacyUrlClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsSyntheticLegacyDump;
use Tests\TestCase;

/**
 * NOV-PUBLIC-04A: конвейер целиком (выгрузка + архив + сверка URL), команда
 * content:extract-legacy-dump и репетиция импорта на синтетических данных.
 * Реальных выгрузок, архивов и баз тест не касается.
 */
class LegacyPublicContentPipelineTest extends TestCase
{
    use BuildsSyntheticLegacyDump;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSynthetic();
        parent::tearDown();
    }

    private function legacyFile(): string
    {
        return base_path('resources/seo/legacy-sitemap-paths.txt');
    }

    /** Архив: часть изображений есть, часть нет; есть портрет, исходник PSD и «сироты». */
    private function archive(): string
    {
        return $this->makeZip([
            'img/countries_image/egipet.png' => $this->png(40, 30),
            'img/countries_image/kipr.png' => $this->png(40, 30),
            'img/countries_image/large/kipr.png' => $this->png(800, 600),
            'img/destanation_image/morskie-kruizy.png' => $this->png(20, 20),
            'img/personal/PRIVATE-MARKER-portrait.png' => $this->png(30, 30),
            'img/personal/PRIVATE-MARKER-unreferenced-staff.png' => $this->png(31, 31),
            'img/special/s1.png' => $this->png(10, 10),
            'img/design/header.psd' => '8BPS-synthetic',
            'img/orphan.png' => $this->png(7, 7),
        ]);
    }

    private function outDir(): string
    {
        return $this->tempDir() . DIRECTORY_SEPARATOR . 'out';
    }

    /** @return array{0: array<string,mixed>, 1: string} */
    private function runPipeline(?string $zip = null): array
    {
        $pipeline = LegacyPublicContentPipeline::make();
        $result = $pipeline->run($this->tempFile($this->fullDump()), $zip, [], $this->legacyFile());
        $out = $this->outDir();
        $pipeline->write($result, $out);

        return [$result, $out];
    }

    private function allOutput(string $dir): string
    {
        $text = '';
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            $text .= file_get_contents($file);
        }

        return $text;
    }

    public function test_full_run_reports_images_holds_and_legacy_url_reconciliation(): void
    {
        [$result, $out] = $this->runPipeline($this->archive());
        $r = $result['report'];

        $this->assertSame('EXTRACTED', $r['status']);
        $this->assertSame(64, strlen($r['source']['sha256']));

        $img = $r['images'];
        $s = $img['summary'];
        $this->assertSame($s['references_total'], $s['references_present'] + $s['references_missing'] + $s['references_case_mismatch'] + $s['references_ambiguous']);
        $this->assertArrayHasKey('img/countries_image/large/egipet.png', $img['missing_references']);
        $this->assertArrayHasKey('img/interesting-articles/a1.png', $img['missing_references'], 'images embedded in article content are inventoried');
        $this->assertSame(1, $img['large_variants']['rows_with_missing_large']);
        $this->assertSame(1, $img['large_variants']['with_small_present']);
        $this->assertSame(
            ['table' => 'countries_images', 'slug' => 'egipet', 'large_ref' => 'img/countries_image/large/egipet.png', 'large_status' => 'missing',
                'small_ref' => 'img/countries_image/egipet.png', 'small_status' => 'present', 'small_width' => 40, 'small_height' => 30],
            $img['large_variants']['rows'][0]
        );
        $this->assertSame(['img/design/header.psd'], $img['editable_sources_not_publishable']);
        $unreferenced = array_keys(array_filter(
            $result['manifest']['entries'],
            fn ($e) => in_array('unreferenced', $e['flags'], true) && ! in_array('portrait_review', $e['flags'], true)
        ));
        $this->assertSame(['img/orphan.png'], $unreferenced);

        $byPath = array_column($r['legacy_urls']['rows'], 'status', 'path');
        $this->assertSame('exact_match_image_missing', $byPath['/countries/egipet']);
        $this->assertSame('exact_match', $byPath['/countries/kipr']);
        $this->assertSame('exact_match', $byPath['/destinations/morskie-kruizy']);
        $this->assertSame('exact_match_image_missing', $byPath['/helpful_information/interesting_articles/kipr-gde-otdoxnut-molodezi']);
        $this->assertSame('published_content_missing', $byPath['/countries/abxaziia']);
        $this->assertSame('static_or_list_page', $byPath['/countries']);
        $this->assertSame('unresolved_mapping', $byPath['/helpful_information/news/kipr-annuliroval-zolotye-pasporta-eshhe-u-troix-investorov']);
        $this->assertSame(272, $r['legacy_urls']['legacy_paths']);
        $this->assertSame(272, array_sum($r['legacy_urls']['counts']));

        $files = array_map('basename', glob($out . '/*') ?: []);
        sort($files);
        $this->assertSame(['asset-manifest.json', 'public-content.json', 'report.json', 'report.md'], $files);
    }

    public function test_written_files_contain_no_private_values_and_portrait_names_are_redacted(): void
    {
        [, $out] = $this->runPipeline($this->archive());
        $text = $this->allOutput($out);

        foreach ($this->privateMarkers() as $marker) {
            $this->assertStringNotContainsString($marker, $text, $marker);
        }
        $this->assertStringNotContainsString('PRIVATE-MARKER', $text, 'staff/customer portrait file names must not be written');
        $this->assertStringContainsString('[portrait-', $text, 'portraits are still listed for owner review, by hash');

        $manifest = json_decode((string) file_get_contents($out . '/asset-manifest.json'), true);
        $portraits = array_filter(array_keys($manifest['entries']), fn ($p) => str_contains($p, '[portrait-'));
        $this->assertCount(2, $portraits, 'referenced and unreferenced staff portraits are both listed (redacted)');
        foreach ($portraits as $p) {
            $this->assertStringStartsWith('img/personal/', $p);
            $this->assertSame(64, strlen($manifest['entries'][$p]['sha256']));
        }
    }

    public function test_run_without_archive_skips_image_checks_but_still_reconciles_urls(): void
    {
        [$result, $out] = $this->runPipeline();

        $this->assertStringStartsWith('NOT_RUN', $result['report']['images']['status']);
        $this->assertSame('not_run', $result['report']['legacy_urls']['image_check']);
        $this->assertNull($result['manifest']);
        $this->assertFileDoesNotExist($out . '/asset-manifest.json');
        $byPath = array_column($result['report']['legacy_urls']['rows'], 'status', 'path');
        $this->assertSame('exact_match', $byPath['/countries/egipet']);
    }

    public function test_unknown_table_blocks_the_public_file_but_writes_the_report(): void
    {
        $pipeline = LegacyPublicContentPipeline::make();
        $dump = $this->tempFile($this->fullDump($this->table('payments', ['id', 'card'], [['1', 'PRIVATE-MARKER-CARD']])));
        $result = $pipeline->run($dump, null, [], $this->legacyFile());
        $out = $this->outDir();
        $files = $pipeline->write($result, $out);

        $this->assertSame('BLOCKED_UNKNOWN_TABLES', $result['report']['status']);
        $this->assertSame(['report.json', 'report.md'], $files);
        $this->assertFileDoesNotExist($out . '/public-content.json');
        $this->assertStringNotContainsString('PRIVATE-MARKER', $this->allOutput($out));
    }

    public function test_reconciler_does_not_invent_mappings_for_ids_case_variants_or_missing_pages(): void
    {
        $reconciler = new LegacyUrlReconciler(app(LegacyUrlClassifier::class));

        $r = $reconciler->reconcile(
            ['/countries/123', '/countries/EGIPET', '/countries/egipet', '/countries/missing-slug', '/countries/held-one',
                '/helpful_information/news/x', '/helpful_information/for_our_clients/y', '/login', '/nowhere', '/countries', '/helpful_information/news?page=3'],
            ['countries_images' => ['egipet', 'brand-new']],
            ['countries_images' => ['held-one']],
            ['countries_images' => ['egipet' => true]],
            ['countries_images'],
        );

        $byPath = array_column($r['rows'], null, 'path');
        $this->assertSame(['status' => 'unresolved_mapping', 'reason' => 'legacy_numeric_id (no id-to-slug evidence; never redirected)'], array_diff_key($byPath['/countries/123'], ['path' => 1]));
        $this->assertSame('slug_differs_only_by_case', $byPath['/countries/EGIPET']['reason']);
        $this->assertSame('exact_match_image_missing', $byPath['/countries/egipet']['status']);
        $this->assertSame('published_content_missing', $byPath['/countries/missing-slug']['status']);
        $this->assertSame('held_by_quality_gate', $byPath['/countries/held-one']['status']);
        $this->assertSame('table_not_in_this_slice:news', $byPath['/helpful_information/news/x']['reason']);
        $this->assertSame('table_not_in_this_slice:our_clients', $byPath['/helpful_information/for_our_clients/y']['reason']);
        $this->assertSame('private_or_service', $byPath['/login']['reason']);
        $this->assertSame('no_route', $byPath['/nowhere']['reason']);
        $this->assertSame('static_or_list_page', $byPath['/countries']['status']);
        $this->assertSame('static_or_list_page', $byPath['/helpful_information/news?page=3']['status']);
        $this->assertSame(['countries_images' => 1], $r['recovered_slugs_without_legacy_url']);
        $this->assertArrayNotHasKey('redirect', $byPath['/countries/missing-slug'], 'no redirect target is ever fabricated');
    }

    // ------------------------------------------------------------------ command

    public function test_command_runs_offline_writes_outside_the_repo_and_prints_only_counts(): void
    {
        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });
        $out = $this->outDir();

        $code = Artisan::call('content:extract-legacy-dump', [
            'dump' => $this->tempFile($this->fullDump()), '--out' => $out, '--images' => $this->archive(),
        ]);
        $console = Artisan::output();

        $this->assertSame(0, $code, $console);
        $this->assertSame([], $queries, 'no database access, default connection included');
        $this->assertStringContainsString('extract countries_images', $console);
        $this->assertStringContainsString('ничего не импортировано и не развёрнуто', $console);
        $this->assertStringNotContainsString('PRIVATE-MARKER', $console . $this->allOutput($out));
        $this->assertStringNotContainsString('SYNTHETIC', $console, 'record text is never printed');
    }

    public function test_command_refuses_paths_inside_the_repository_and_non_empty_output(): void
    {
        $dump = $this->tempFile($this->fullDump());

        $inside = base_path('storage/framework/legacy04a-should-not-exist');
        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $dump, '--out' => $inside]));
        $this->assertStringContainsString('REFUSED', Artisan::output());
        $this->assertDirectoryDoesNotExist($inside);

        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => base_path('composer.json'), '--out' => $this->outDir()]));
        $this->assertStringContainsString('вне репозитория', Artisan::output());

        $full = $this->tempDir();
        file_put_contents($full . '/keep.txt', 'x');
        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $dump, '--out' => $full]));
        $this->assertSame('x', file_get_contents($full . '/keep.txt'), 'existing files are never overwritten');

        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $dump]));
        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $dump . '.missing', '--out' => $this->outDir()]));
    }

    public function test_command_blocks_on_unknown_tables_and_honours_explicit_acknowledgement(): void
    {
        $dump = $this->tempFile($this->fullDump($this->table('payments', ['id', 'card'], [['1', 'PRIVATE-MARKER-CARD']])));

        $out = $this->outDir();
        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $dump, '--out' => $out]));
        $this->assertStringContainsString('BLOCKED', Artisan::output());
        $this->assertFileDoesNotExist($out . '/public-content.json');

        $out2 = $this->outDir();
        $this->assertSame(0, Artisan::call('content:extract-legacy-dump', ['dump' => $dump, '--out' => $out2, '--acknowledge-unknown' => 'payments']));
        $this->assertFileExists($out2 . '/public-content.json');
        $this->assertStringNotContainsString('PRIVATE-MARKER', $this->allOutput($out2));
    }

    public function test_command_refuses_malformed_dumps_without_echoing_content(): void
    {
        $dump = $this->tempFile("CREATE TABLE `articles` (`id` int, `title` text);\nINSERT INTO `articles` (`id`, `title`) VALUES (1, 'PRIVATE-MARKER-OPEN);\n");
        $out = $this->outDir();

        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $dump, '--out' => $out]));
        $console = Artisan::output();

        $this->assertStringContainsString('unterminated_quote', $console);
        $this->assertStringNotContainsString('PRIVATE-MARKER', $console);
        $this->assertDirectoryDoesNotExist($out);

        $bad = $this->tempFile($this->fullDump() . "DELETE FROM `users`;\n");
        $this->assertSame(1, Artisan::call('content:extract-legacy-dump', ['dump' => $bad, '--out' => $this->outDir()]));
        $this->assertStringContainsString('unsupported_statement:DELETE', Artisan::output());
    }

    // ------------------------------------------------------------------ rehearsal

    public function test_extracted_file_passes_the_current_importer_and_preserves_legacy_urls_in_the_sitemap(): void
    {
        $out = $this->outDir();
        $this->assertSame(0, Artisan::call('content:extract-legacy-dump', ['dump' => $this->tempFile($this->fullDump()), '--out' => $out, '--images' => $this->archive()]));
        $file = $out . '/public-content.json';

        // текущий импортёр принимает файл (dry-run ничего не пишет), затем фиксируем в одноразовой SQLite
        $this->artisan('content:import-public', ['file' => $file])->expectsOutputToContain('Режим dry-run')->assertExitCode(0);
        $this->assertSame(0, Countries_image::count());
        $this->artisan('content:import-public', ['file' => $file, '--commit' => true, '--force' => true])->assertExitCode(0);
        $this->assertSame(2, Countries_image::count());
        $this->assertSame('2024-05-01 10:00:00', Countries_image::where('slug', 'egipet')->firstOrFail()->created_at->toDateTimeString(), 'original dates survive');

        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $paths = [];
        foreach ($xml->url as $url) {
            $paths[] = substr(trim((string) $url->loc), strlen('https://avilona.ru'));
        }

        $legacy = LegacyUrlReconciler::readPaths($this->legacyFile());
        $preserved = ['/countries/egipet', '/countries/kipr', '/destinations/morskie-kruizy', '/helpful_information/interesting_articles/kipr-gde-otdoxnut-molodezi'];
        foreach ($preserved as $path) {
            $this->assertContains($path, $legacy, "{$path} is an historical URL");
            $this->assertContains($path, $paths, "{$path} must be in the dynamic sitemap");
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, preg_match_all('#<h1[\s>]#', $html), $path);
            $this->assertStringContainsString('SYNTHETIC', $html, $path);
        }
        $this->assertNotContains('/countries/removed-country', $paths, 'soft-deleted source rows are not published');
        $this->assertNotContains('/helpful_information/news/synthetic-news-1', $paths, 'inventory-only news is not published');
        $this->assertNotContains('/company/employees', $paths, 'staff pages stay empty until the owner approves');
    }
}
