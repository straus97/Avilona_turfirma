<?php

namespace Tests\Feature;

use App\Services\Content\Legacy\LegacyImageArchiveInspector;
use App\Services\Content\Legacy\LegacyPublicContentPipeline;
use App\Services\Content\PublicContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSyntheticLegacyDump;
use Tests\TestCase;

/**
 * NOV-PUBLIC-04B: узкие, опциональные преобразования производного файла-обмена
 * (каталог-заглушка documents_url, отсутствующий большой файл) и точное правило
 * «<?=» в сжатых данных PNG. Все данные синтетические.
 */
class LegacyPublicContentNormalizationTest extends TestCase
{
    use BuildsSyntheticLegacyDump;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSynthetic();
        parent::tearDown();
    }

    // ------------------------------------------------------------ scanner

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    /** PNG с произвольным хвостом внутри IDAT (zlib-поток не проверяется, проверяются сигнатура, длины и CRC). */
    private function pngWithIdatTail(string $tail, bool $breakCrc = false, string $extraChunk = '', string $afterIend = ''): string
    {
        $raw = '';
        for ($y = 0; $y < 8; $y++) {
            $raw .= "\x00" . str_repeat("\x80", 8);
        }
        $idat = $this->chunk('IDAT', gzcompress($raw) . $tail);
        if ($breakCrc) {
            $idat = substr($idat, 0, -1) . chr((ord(substr($idat, -1)) + 1) % 256);
        }

        return "\x89PNG\r\n\x1a\n"
            . $this->chunk('IHDR', pack('NNCCCCC', 8, 8, 8, 0, 0, 0, 0))
            . $extraChunk . $idat . $this->chunk('IEND', '') . $afterIend;
    }

    /** @return array<string,mixed> */
    private function inspectOne(string $bytes, string $name = 'img/x.png'): array
    {
        $zip = $this->makeZip([$name => $bytes]);

        return (new LegacyImageArchiveInspector())->inspect($zip, [])['entries'][$name];
    }

    public function test_short_echo_bytes_inside_compressed_png_data_are_not_treated_as_a_script(): void
    {
        $entry = $this->inspectOne($this->pngWithIdatTail("<?=\x01\x02\x03\x90\xA4\xFF\x00\x17\x80\x81\x82\x83"));

        $this->assertSame('image', $entry['status']);
        $this->assertNotContains('embedded_script', $entry['flags']);
        $this->assertContains('short_tag_bytes_in_compressed_data', $entry['flags']);
        $this->assertSame([8, 8], [$entry['width'], $entry['height']]);
    }

    public function test_payload_like_short_echo_tag_in_png_data_is_still_held(): void
    {
        $closing = $this->inspectOne($this->pngWithIdatTail('<?=$_GET[0]?>'));
        $printable = $this->inspectOne($this->pngWithIdatTail('<?=' . str_repeat('a', 40)));

        foreach ([$closing, $printable] as $entry) {
            $this->assertSame('suspicious', $entry['status']);
            $this->assertContains('embedded_script', $entry['flags']);
        }
    }

    public function test_php_open_tag_is_held_wherever_it_appears_in_any_case(): void
    {
        foreach (["<?php\x01\x02", "<?PHP\x01\x02", "<?Php\x01\x02"] as $tail) {
            $entry = $this->inspectOne($this->pngWithIdatTail($tail));
            $this->assertSame('suspicious', $entry['status'], $tail);
            $this->assertContains('embedded_script', $entry['flags']);
        }
    }

    public function test_short_echo_tag_outside_idat_or_in_an_unverifiable_png_is_held(): void
    {
        $binary = "<?=\x01\x02\x03\x90\xA4\xFF\x00\x17";
        $cases = [
            'metadata chunk' => $this->pngWithIdatTail('', false, $this->chunk('tEXt', "Comment\0" . $binary)),
            'trailing data after IEND' => $this->pngWithIdatTail('', false, '', $binary),
            'bad chunk CRC' => $this->pngWithIdatTail($binary, true),
        ];

        foreach ($cases as $label => $bytes) {
            $entry = $this->inspectOne($bytes);
            $this->assertSame('suspicious', $entry['status'], $label);
            $this->assertContains('embedded_script', $entry['flags'], $label);
        }
    }

    public function test_short_echo_bytes_in_a_non_png_image_stay_held(): void
    {
        $this->assertTrue(function_exists('imagecreatetruecolor'), 'GD is required for this check');
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $entry = $this->inspectOne($jpeg . "<?=\x01\x02\x03", 'img/x.jpg');

        $this->assertSame('suspicious', $entry['status']);
        $this->assertContains('embedded_script', $entry['flags']);
    }

    public function test_a_clean_png_has_no_script_flags(): void
    {
        $entry = $this->inspectOne($this->png(8, 8));

        $this->assertSame('image', $entry['status']);
        $this->assertSame(['unreferenced'], $entry['flags']);
    }

    // ------------------------------------------------------------ normalization

    private function dumpWithDocuments(): string
    {
        return $this->dumpHeader()
            . $this->table('countries_images', $this->countryColumns(), [
                $this->country('dir-a', ['id' => '1', 'documents_url' => '/documents/countries/']),
                $this->country('dir-b', ['id' => '2', 'documents_url' => '/documents/countries/']),
                $this->country('real-doc', ['id' => '3', 'documents_url' => 'https://example.invalid/memo.pdf']),
                $this->country('other-dir', ['id' => '4', 'documents_url' => '/documents/other/']),
                $this->country('with-file', ['id' => '5', 'documents_url' => '/documents/countries/egipet.pdf']),
                $this->country('no-doc', ['id' => '6']),
            ])
            . $this->dumpFooter();
    }

    /** @param array<string,bool> $options */
    private function extract(string $dump, ?string $zip = null, array $options = []): array
    {
        return LegacyPublicContentPipeline::make()->run(
            $this->tempFile($dump),
            $zip,
            [],
            base_path('resources/seo/legacy-sitemap-paths.txt'),
            $options,
        );
    }

    public function test_documents_url_directory_placeholder_is_held_by_default(): void
    {
        $result = $this->extract($this->dumpWithDocuments());
        $held = array_column($result['report']['held']['countries_images'], 'slug');

        $this->assertEqualsCanonicalizing(['dir-a', 'dir-b', 'other-dir', 'with-file'], $held);
        $this->assertArrayNotHasKey('normalization', $result['report']);
        $this->assertNull($result['evidence']);
    }

    public function test_exact_directory_placeholder_becomes_empty_when_explicitly_enabled(): void
    {
        $before = $this->dumpWithDocuments();
        $result = $this->extract($before, null, ['normalize_documents_url' => true]);
        $rows = array_column($result['content']['tables']['countries_images'], null, 'slug');

        // распознанные каталоги: принятые, без documents_url и без выдуманной замены
        foreach (['dir-a', 'dir-b'] as $slug) {
            $this->assertArrayHasKey($slug, $rows);
            $this->assertArrayNotHasKey('documents_url', $rows[$slug]);
        }
        // настоящий URL сохранён байт в байт; нет документа остаётся без документа
        $this->assertSame('https://example.invalid/memo.pdf', $rows['real-doc']['documents_url']);
        $this->assertArrayNotHasKey('documents_url', $rows['no-doc']);

        // любое иное значение остаётся удержанным: нормализация не «чистит всё подряд»
        $held = array_column($result['report']['held']['countries_images'], 'reasons', 'slug');
        $this->assertSame(['invalid_documents_url'], $held['other-dir']);
        $this->assertSame(['invalid_documents_url'], $held['with-file']);
        $this->assertArrayNotHasKey('dir-a', $held);

        // свидетельство: исходное значение, результат и обоснование по каждой записи
        $items = $result['evidence']['items'];
        $this->assertCount(2, $items);
        $this->assertSame(['dir-a', 'dir-b'], array_column($items, 'slug'));
        foreach ($items as $item) {
            $this->assertSame('/documents/countries/', $item['source_value']);
            $this->assertNull($item['output_value']);
            $this->assertSame('documents_url_directory_placeholder_to_null', $item['rule']);
            $this->assertNotSame('', $item['rationale']);
        }

        $rec = $result['report']['reconciliation']['countries_images'];
        $this->assertTrue($rec['balanced']);
        $this->assertSame([6, 0, 4, 2], [$rec['source_rows'], $rec['soft_deleted'], $rec['accepted'], $rec['held']]);
        $this->assertSame(2, $rec['rows_transformed']);
        $this->assertSame(['invalid_documents_url' => 2], $rec['held_reasons']);
    }

    public function test_normalized_output_passes_the_importer_contract(): void
    {
        $result = $this->extract($this->dumpWithDocuments(), null, ['normalize_documents_url' => true]);
        $json = json_encode($result['content'], JSON_UNESCAPED_UNICODE);

        $check = (new PublicContentImporter())->validate($json);

        $this->assertSame([], $check['errors']);
        $this->assertTrue($check['ok']);
        $this->assertSame(4, $check['counts']['countries_images']);
    }

    // ------------------------------------------------------------ missing large

    private function dumpForLarge(): string
    {
        return $this->dumpHeader()
            . $this->table('countries_images', $this->countryColumns(), [
                $this->country('egipet', ['id' => '1']),
                $this->country('kipr', ['id' => '2']),
                $this->country('no-small', ['id' => '3']),
            ])
            . $this->dumpFooter();
    }

    private function largeArchive(): string
    {
        return $this->makeZip([
            'img/countries_image/egipet.png' => $this->png(27, 20),
            'img/countries_image/kipr.png' => $this->png(27, 20),
            'img/countries_image/large/kipr.png' => $this->png(80, 60),
        ]);
    }

    public function test_missing_large_is_left_alone_unless_the_option_is_enabled(): void
    {
        $result = $this->extract($this->dumpForLarge(), $this->largeArchive());
        $rows = array_column($result['content']['tables']['countries_images'], null, 'slug');

        $this->assertSame('img/countries_image/large/egipet.png', $rows['egipet']['image_large']);
        $this->assertSame(2, $result['report']['images']['large_variants']['rows_with_missing_large']);
    }

    public function test_missing_large_becomes_empty_only_when_small_exists_and_evidence_keeps_the_source(): void
    {
        $result = $this->extract($this->dumpForLarge(), $this->largeArchive(), ['null_missing_large' => true]);
        $rows = array_column($result['content']['tables']['countries_images'], null, 'slug');

        $this->assertArrayNotHasKey('image_large', $rows['egipet']);
        $this->assertSame('img/countries_image/egipet.png', $rows['egipet']['image_small']);
        $this->assertSame('img/countries_image/large/kipr.png', $rows['kipr']['image_large']);   // существующий файл не тронут
        $this->assertSame('img/countries_image/large/no-small.png', $rows['no-small']['image_large']); // нет и small: решает владелец

        $items = $result['evidence']['items'];
        $this->assertSame(['egipet'], array_column($items, 'slug'));
        $this->assertSame('img/countries_image/large/egipet.png', $items[0]['source_value']);
        $this->assertNull($items[0]['output_value']);
        $this->assertSame([27, 20], [$items[0]['small_width'], $items[0]['small_height']]);
        $this->assertSame(['image_large_missing_in_archive_to_null' => 1], $result['report']['normalization']['by_rule']);
    }

    public function test_null_missing_large_requires_an_images_archive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->extract($this->dumpForLarge(), null, ['null_missing_large' => true]);
    }

    public function test_evidence_file_is_written_only_when_transformations_were_requested(): void
    {
        $pipeline = LegacyPublicContentPipeline::make();
        $plain = $pipeline->write($this->extract($this->dumpForLarge(), $this->largeArchive()), $this->tempDir() . DIRECTORY_SEPARATOR . 'a');
        $derived = $pipeline->write(
            $this->extract($this->dumpForLarge(), $this->largeArchive(), ['null_missing_large' => true]),
            $this->tempDir() . DIRECTORY_SEPARATOR . 'b',
        );

        $this->assertNotContains('normalization-evidence.json', $plain);
        $this->assertContains('normalization-evidence.json', $derived);
    }
}
