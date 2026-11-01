<?php

namespace Tests\Feature;

use App\Services\Content\Legacy\LegacyContentChecks;
use App\Services\Content\Legacy\LegacyContentExtractor;
use App\Services\Content\Legacy\LegacySqlDumpParser;
use App\Services\Content\Legacy\LegacyTablePolicy;
use App\Services\Content\PublicContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsSyntheticLegacyDump;
use Tests\TestCase;

/**
 * NOV-PUBLIC-04A: строгий список таблиц, закрытие по умолчанию, перенос без правки
 * текста, удержание записей, которые импортёр отверг бы, отчёты без приватных данных.
 */
class LegacyContentExtractorTest extends TestCase
{
    use BuildsSyntheticLegacyDump;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSynthetic();
        parent::tearDown();
    }

    private function extractor(?\Closure $classify = null): LegacyContentExtractor
    {
        return new LegacyContentExtractor(new LegacySqlDumpParser(), new LegacyContentChecks($classify, 2026));
    }

    private function extract(string $sql, array $ack = [], ?\Closure $classify = null): array
    {
        return $this->extractor($classify)->extract($sql, $ack);
    }

    public function test_only_the_three_allowlisted_tables_reach_the_public_file(): void
    {
        $r = $this->extract($this->fullDump());

        $this->assertFalse($r['blocked']);
        $this->assertSame(PublicContentImporter::FORMAT, $r['content']['format']);
        $this->assertSame(['articles', 'countries_images', 'destination_images'], array_keys($r['content']['tables']));
        $this->assertSame(['egipet', 'kipr'], array_column($r['content']['tables']['countries_images'], 'slug'));

        // image_refs — внутренний вход инспектора архива (на диск не пишется; портреты скрывает конвейер,
        // см. LegacyPublicContentPipelineTest); здесь проверяется всё остальное содержимое результата.
        $encoded = json_encode(array_diff_key($r, ['image_refs' => true]));
        foreach ($this->privateMarkers() as $marker) {
            $this->assertStringNotContainsString($marker, $encoded, "{$marker} must never appear in the result or report");
        }
        $this->assertStringNotContainsString('PRIVATE-MARKER', $encoded);
    }

    public function test_table_classification_is_closed_by_default(): void
    {
        foreach (['users', 'password_resets', 'personal_access_tokens', 'role_user', 'notifications', 'bookings', 'messages', 'incoming_inquiries'] as $table) {
            $this->assertSame('private', LegacyTablePolicy::classify($table), $table);
            $this->assertNull(LegacyTablePolicy::columnsToRead($table), $table);
        }
        foreach (['news', 'partners', 'awards', 'best_offers', 'employees', 'reviews', 'our_clients'] as $table) {
            $this->assertSame('inventory', LegacyTablePolicy::classify($table), $table);
        }
        $this->assertNull(LegacyTablePolicy::columnsToRead('reviews'), 'review values are never read');
        $this->assertSame('unknown', LegacyTablePolicy::classify('payments'));
        $this->assertSame('private', LegacyTablePolicy::classify('USERS'));
    }

    public function test_unknown_table_blocks_all_output_until_explicitly_acknowledged(): void
    {
        $extra = $this->table('payments', ['id', 'card'], [['1', 'PRIVATE-MARKER-CARD']]);

        $blocked = $this->extract($this->fullDump($extra));
        $this->assertTrue($blocked['blocked']);
        $this->assertNull($blocked['content']);
        $this->assertSame(['payments'], $blocked['unacknowledged_unknown_tables']);
        $this->assertSame(['id', 'card'], $blocked['tables_seen']['payments']['columns']);
        $this->assertStringNotContainsString('PRIVATE-MARKER-CARD', json_encode($blocked));

        $ok = $this->extract($this->fullDump($extra), ['payments']);
        $this->assertFalse($ok['blocked']);
        $this->assertNotNull($ok['content']);
        $this->assertStringNotContainsString('PRIVATE-MARKER-CARD', json_encode($ok));
    }

    public function test_private_tables_cannot_be_unlocked_by_acknowledgement(): void
    {
        $r = $this->extract($this->fullDump(), ['users', 'password_resets']);

        $this->assertSame(['users', 'password_resets'], $r['rejected_acknowledgements']);
        $this->assertArrayNotHasKey('users', $r['content']['tables']);
        $this->assertSame('private', $r['tables_seen']['users']['class']);
    }

    public function test_inventory_tables_are_counted_but_not_published_and_staff_data_stays_out(): void
    {
        $r = $this->extract($this->fullDump());

        foreach (['news', 'our_clients', 'best_offers', 'partners', 'awards', 'employees', 'reviews'] as $table) {
            $this->assertArrayHasKey($table, $r['inventory'], $table);
            $this->assertSame('NOT_PUBLISHED_PENDING_OWNER_DECISION', $r['inventory'][$table]['publish_status']);
            $this->assertArrayNotHasKey($table, $r['content']['tables'], $table);
        }
        $this->assertSame(2, $r['inventory']['news']['rows_total']);
        $this->assertSame(1, $r['inventory']['news']['without_source_link']);
        $this->assertSame(1, $r['inventory']['news']['older_than_12_months']);
        $this->assertSame(['2024-01-01', '2099-01-01'], [$r['inventory']['news']['pub_date_min'], $r['inventory']['news']['pub_date_max']]);
        $this->assertSame(['news.example.invalid' => 1], $r['inventory']['news']['source_hosts']);
        $this->assertSame(1, $r['inventory']['employees']['rows_total']);
        $this->assertSame(1, $r['inventory']['reviews']['rows_total']);
        $this->assertArrayNotHasKey('rows_active', $r['inventory']['reviews'], 'review values are not read at all');

        $groups = array_column($r['image_refs'], 'group', 'table');
        $this->assertSame('staff_portrait', $groups['employees']);
        $this->assertNotContains('reviews', array_column($r['image_refs'], 'table'));
    }

    public function test_soft_deleted_rows_are_excluded_and_counted(): void
    {
        $r = $this->extract($this->fullDump());

        $this->assertSame(3, $r['extract']['countries_images']['source_rows']);
        $this->assertSame(1, $r['extract']['countries_images']['soft_deleted']);
        $this->assertSame(2, $r['extract']['countries_images']['emitted']);
        $this->assertNotContains('removed-country', array_column($r['content']['tables']['countries_images'], 'slug'));
    }

    public function test_records_are_transferred_verbatim_with_original_slugs_titles_and_dates(): void
    {
        $r = $this->extract($this->fullDump());
        $row = $r['content']['tables']['countries_images'][0];

        $this->assertSame('egipet', $row['slug']);
        $this->assertSame('SYNTHETIC egipet', $row['title']);
        $this->assertSame('<p>SYNTHETIC описание egipet.</p>', $row['description']);
        $this->assertSame('img/countries_image/egipet.png', $row['image_small']);
        $this->assertSame('2024-05-01 10:00:00', $row['created_at']);
        $this->assertArrayNotHasKey('id', $row);
        $this->assertArrayNotHasKey('deleted_at', $row);
        $this->assertArrayNotHasKey('documents_url', $row, 'null optional fields are omitted, not invented');
    }

    public function test_duplicate_slugs_exact_and_case_insensitive_are_held_not_chosen(): void
    {
        $sql = $this->table('countries_images', $this->countryColumns(), [
            $this->country('dup', ['id' => '1']),
            $this->country('dup', ['id' => '2', 'title' => 'SYNTHETIC second']),
            $this->country('Case', ['id' => '3']),
            $this->country('case', ['id' => '4']),
            $this->country('unique-one', ['id' => '5']),
        ]);

        $r = $this->extract($sql);

        $this->assertSame(['unique-one'], array_column($r['content']['tables']['countries_images'], 'slug'));
        $this->assertSame(4, $r['extract']['countries_images']['held']);
        foreach ($r['held']['countries_images'] as $held) {
            $this->assertSame(['duplicate_slug'], $held['reasons']);
        }
    }

    public function test_rows_the_importer_would_reject_are_held_with_codes_not_repaired(): void
    {
        $cols = $this->countryColumns();
        $sql = $this->table('countries_images', $cols, [
            $this->country('blank-title', ['title' => '   ']),
            $this->country('has space', ['id' => '2']),
            $this->country('.dotted', ['id' => '3']),
            $this->country('long-title', ['id' => '4', 'title' => str_repeat('я', 256)]),
            $this->country('cyrillic-img', ['id' => '5', 'image_small' => 'img/страна.png']),
            $this->country('traversal', ['id' => '6', 'image_small' => '../secret.png']),
            $this->country('http-doc', ['id' => '7', 'documents_url' => 'http://insecure.invalid/x.pdf']),
            $this->country('bad-date', ['id' => '8', 'created_at' => 'yesterday']),
            $this->country('bad-utf8', ['id' => '9', 'title' => "bad \xC3\x28 bytes"]),
            $this->country('ok-row', ['id' => '10']),
        ]);

        $r = $this->extract($sql);

        $codes = [];
        foreach ($r['held']['countries_images'] as $h) {
            $codes[$h['slug'] ?? '(invalid)'] = $h['reasons'];
        }
        $this->assertSame(['required_blank:title'], $codes['blank-title']);
        $this->assertSame(['invalid_slug'], $codes['(invalid)'] ?? []);
        $this->assertSame(['too_long:title'], $codes['long-title']);
        $this->assertSame(['invalid_image_ref:image_small'], $codes['cyrillic-img']);
        $this->assertSame(['invalid_image_ref:image_small'], $codes['traversal']);
        $this->assertSame(['invalid_documents_url'], $codes['http-doc']);
        $this->assertSame(['invalid_timestamp:created_at'], $codes['bad-date']);
        $this->assertSame(['invalid_utf8:title'], $codes['bad-utf8']);
        $this->assertSame(['ok-row'], array_column($r['content']['tables']['countries_images'], 'slug'));
    }

    public function test_held_rows_fail_the_real_importer_and_emitted_rows_pass_it(): void
    {
        $sql = $this->table('countries_images', $this->countryColumns(), [
            $this->country('cyrillic-img', ['id' => '1', 'image_small' => 'img/страна.png']),
            $this->country('fine', ['id' => '2']),
        ]);
        $r = $this->extract($sql);

        $path = $this->tempFile(json_encode($r['content'], JSON_UNESCAPED_UNICODE), '.json');
        $this->artisan('content:import-public', ['file' => $path])->assertExitCode(0);

        $bad = $this->tempFile(json_encode(['format' => PublicContentImporter::FORMAT, 'tables' => ['countries_images' => [[
            'title' => 'SYNTHETIC', 'slug' => 'cyrillic-img', 'category' => 'x', 'description' => '<p>x</p>', 'image_small' => 'img/страна.png',
        ]]]], JSON_UNESCAPED_UNICODE), '.json');
        $this->artisan('content:import-public', ['file' => $bad])->assertExitCode(1);
    }

    public function test_zero_dates_are_omitted_and_missing_publication_date_is_flagged(): void
    {
        $sql = $this->table('articles', ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'], [
            ['1', 'SYNTHETIC', 'zero-date', '<p>text</p>', null, '0000-00-00 00:00:00', '0000-00-00 00:00:00', '0000-00-00 00:00:00'],
        ]);

        $r = $this->extract($sql);
        $row = $r['content']['tables']['articles'][0];

        $this->assertArrayNotHasKey('created_at', $row);
        $this->assertArrayNotHasKey('updated_at', $row);
        $this->assertSame(2, $r['extract']['articles']['zero_date_omitted']);
        $this->assertSame(1, $r['quality']['articles']['missing_publication_date']['rows']);
    }

    public function test_schema_mismatch_missing_required_column_and_unmapped_columns_are_documented(): void
    {
        $sql = $this->table('articles', ['id', 'title', 'body'], [['1', 'SYNTHETIC', 'text']])
            . $this->table('destination_images', ['id', 'title', 'slug', 'description', 'secret_note'], [['1', 'SYNTHETIC', 'dest', '<p>x</p>', 'PRIVATE-MARKER-NOTE']]);

        $r = $this->extract($sql);

        $this->assertSame(['slug', 'content'], $r['schema_errors']['articles']['missing_required_columns']);
        $this->assertArrayNotHasKey('articles', $r['content']['tables']);
        $this->assertSame(['secret_note'], $r['unmapped_columns']['destination_images']);
        $this->assertSame(['title', 'slug', 'description'], array_keys($r['content']['tables']['destination_images'][0]));
        $this->assertStringNotContainsString('PRIVATE-MARKER-NOTE', json_encode($r), 'values of unmapped columns are never emitted');
    }

    public function test_html_is_preserved_verbatim_and_unsafe_markup_is_flagged_not_rewritten(): void
    {
        $html = '<p>Видимый</p><script>alert(1)</script><img src=x onerror=alert(2)><table><tr><td>t</td></tr></table>';
        $sql = $this->table('articles', ['id', 'title', 'slug', 'content', 'image', 'created_at'], [
            ['1', 'SYNTHETIC', 'unsafe', $html, null, '2024-01-01 00:00:00'],
        ]);

        $r = $this->extract($sql);

        $this->assertSame($html, $r['content']['tables']['articles'][0]['content'], 'editorial text is not rewritten by the extractor');
        $q = $r['quality']['articles'];
        $this->assertArrayHasKey('unsafe_markup', $q);
        $this->assertContains('script', $q['tags_dropped_by_sanitizer']['detail']);
        $this->assertContains('table', $q['tags_dropped_by_sanitizer']['detail']);
    }

    public function test_quality_checks_detect_malformed_html_links_years_prices_rules_and_mojibake(): void
    {
        $classify = fn (string $path): string => str_contains($path, 'gone') ? 'no_route' : 'supported_slug';
        $content = '<p>Цена 1 200 руб. в 2019 году, правила въезда и ПЦР.</p>'
            . '<a href="http://example.invalid/x">a</a><a href="http://www.avilona.ru/countries/gone">b</a><a href="/old/gone">c</a>'
            . '<div><p>unclosed';
        $sql = $this->table('articles', ['id', 'title', 'slug', 'content', 'image', 'created_at'], [
            ['1', 'SYNTHETIC', 'quality', $content, null, '2024-01-01 00:00:00'],
            ['2', 'Ð¿Ñ€Ð¸Ð²ÐµÑ‚', 'moji', '<p>fine</p>', null, '2024-01-01 00:00:00'],
            ['3', 'SYNTHETIC <b>markup</b>', 'title-markup', '<p>fine</p>', null, '2024-01-01 00:00:00'],
            ['4', 'SYNTHETIC', 'empty', '<p>  </p>', null, '2024-01-01 00:00:00'],
        ]);

        $q = $this->extract($sql, [], $classify)['quality']['articles'];

        foreach (['malformed_html', 'insecure_http_link', 'www_host_link', 'outdated_internal_link', 'past_year_reference', 'price_reference', 'rules_reference'] as $code) {
            $this->assertSame(['quality'], $q[$code]['coordinates'], $code);
        }
        $this->assertSame(['2019'], $q['past_year_reference']['detail']);
        $this->assertSame(2, $q['outdated_internal_link']['detail']['count']);
        $this->assertSame(['moji'], $q['possible_mojibake']['coordinates']);
        $this->assertSame(['title-markup'], $q['title_has_markup']['coordinates']);
        $this->assertSame(['empty'], $q['empty_text']['coordinates']);
    }

    public function test_image_references_include_columns_and_images_embedded_in_content(): void
    {
        $r = $this->extract($this->fullDump());

        $public = array_values(array_filter($r['image_refs'], fn ($x) => $x['group'] === 'public_content'));
        $refs = array_map(fn ($x) => "{$x['table']}.{$x['column']}={$x['ref']}", $public);

        $this->assertContains('countries_images.image_large=img/countries_image/large/egipet.png', $refs);
        $this->assertContains('articles.content:img=/img/interesting-articles/a1.png', $refs);
        $this->assertContains('destination_images.image_small=img/destanation_image/morskie-kruizy.png', $refs);
    }

    public function test_extraction_never_queries_a_database(): void
    {
        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $this->extract($this->fullDump());

        $this->assertSame([], $queries);
    }

    public function test_absent_public_tables_are_reported_and_an_empty_dump_is_not_an_error(): void
    {
        $r = $this->extract($this->dumpHeader() . $this->table('migrations', ['id', 'migration'], [['1', '2023_x']]));

        $this->assertFalse($r['blocked']);
        $this->assertSame(['articles', 'countries_images', 'destination_images'], $r['tables_absent']);
        $this->assertSame([], $r['content']['tables']);
    }
}
