<?php

namespace Tests\Feature;

use App\Services\Content\Legacy\LegacyImageArchiveInspector;
use Tests\Feature\Concerns\BuildsSyntheticLegacyDump;
use Tests\TestCase;

/**
 * NOV-PUBLIC-04A: архив изображений инспектируется БЕЗ распаковки на диск.
 * Архивы синтетические; содержимое — сгенерированные PNG и текстовые заглушки.
 */
class LegacyImageArchiveInspectorTest extends TestCase
{
    use BuildsSyntheticLegacyDump;

    protected function tearDown(): void
    {
        $this->tearDownSynthetic();
        parent::tearDown();
    }

    private function ref(string $ref, string $group = 'public_content', string $column = 'image_small', string $table = 'countries_images'): array
    {
        return ['group' => $group, 'table' => $table, 'column' => $column, 'slug' => 's', 'ref' => $ref];
    }

    public function test_references_are_classified_present_missing_case_mismatch_and_ambiguous(): void
    {
        $zip = $this->makeZip([
            'img/countries_image/egipet.png' => $this->png(40, 30),
            'countries_image/large/rooted.png' => $this->png(800, 600),
            'img/countries_image/Mixed.PNG' => $this->png(5, 5),
            'a/dup.png' => $this->png(2, 2),
            'b/dup.png' => $this->png(3, 3),
            'img/orphan.png' => $this->png(7, 7),
            'img/countries_image/small-only.png' => $this->png(10, 10),
        ]);

        $r = (new LegacyImageArchiveInspector())->inspect($zip, [
            $this->ref('img/countries_image/egipet.png'),
            $this->ref('/countries_image/egipet.png'),
            $this->ref('img/countries_image/large/rooted.png', column: 'image_large'),
            $this->ref('countries_image/large/missing.png', column: 'image_large'),
            $this->ref('img/countries_image/mixed.png'),
            $this->ref('dup.png'),
            $this->ref('https://cdn.example.invalid/x.png'),
        ]);

        $refs = $r['references'];
        $this->assertSame('present', $refs['img/countries_image/egipet.png']['status']);
        $this->assertSame(40, $refs['img/countries_image/egipet.png']['width']);
        $this->assertSame(30, $refs['img/countries_image/egipet.png']['height']);
        $this->assertSame('present', $refs['countries_image/egipet.png']['status'], 'a reference without the img/ prefix still resolves');
        $this->assertSame('present', $refs['img/countries_image/large/rooted.png']['status'], 'archive rooted inside img/ resolves');
        $this->assertSame(800, $refs['img/countries_image/large/rooted.png']['width']);
        $this->assertSame('missing', $refs['countries_image/large/missing.png']['status']);
        $this->assertSame('case_mismatch', $refs['img/countries_image/mixed.png']['status']);
        $this->assertSame('ambiguous', $refs['dup.png']['status']);
        $this->assertCount(2, $refs['dup.png']['entries']);
        $this->assertArrayNotHasKey('https://cdn.example.invalid/x.png', $refs, 'external URLs are not archive references');

        $s = $r['summary'];
        $this->assertSame(1, $s['references_missing']);
        $this->assertSame(1, $s['references_case_mismatch']);
        $this->assertSame(1, $s['references_ambiguous']);
        $this->assertTrue($r['entries']['img/orphan.png']['referenced'] === false);
        $this->assertContains('unreferenced', $r['entries']['img/orphan.png']['flags']);
        $this->assertSame(7, $s['by_extension']['png'], 'extensions are counted case-insensitively');
    }

    public function test_hostile_entry_names_are_flagged_never_matched_and_nothing_is_written_to_disk(): void
    {
        $zip = $this->makeZip([
            'img/ok.png' => $this->png(2, 2),
            '../evil.png' => $this->png(1, 1),
            '/abs.png' => $this->png(1, 1),
            'C:/win.png' => $this->png(1, 1),
            'a\\back.png' => $this->png(1, 1),
            'img/./dot.png' => $this->png(1, 1),
        ]);
        $before = $this->snapshot(dirname($zip));
        $beforeParent = $this->snapshot(dirname($zip, 2));

        $r = (new LegacyImageArchiveInspector())->inspect($zip, [
            $this->ref('../evil.png'), $this->ref('abs.png'), $this->ref('img/ok.png'),
        ]);

        $this->assertSame(5, $r['summary']['unsafe_paths']);
        $this->assertSame('present', $r['references']['img/ok.png']['status']);
        $this->assertSame('missing', $r['references']['abs.png']['status'], 'an unsafe entry is never matched to a reference');
        $this->assertSame($before, $this->snapshot(dirname($zip)), 'inspection must not extract anything');
        $this->assertSame($beforeParent, $this->snapshot(dirname($zip, 2)));
        $this->assertFileDoesNotExist(dirname($zip, 2) . DIRECTORY_SEPARATOR . 'evil.png');
    }

    /** @return array<int,string> */
    private function snapshot(string $dir): array
    {
        $list = array_diff(scandir($dir) ?: [], ['.', '..']);
        sort($list);

        return array_values($list);
    }

    public function test_unsupported_editable_executable_and_mismatching_files_are_suspicious_or_unpublishable(): void
    {
        $zip = $this->makeZip([
            'img/design/header.psd' => '8BPS-synthetic',
            'img/shell.php' => '<?php echo 1;',
            'img/vector.svg' => '<svg onload="x()"/>',
            'img/notes.txt' => 'text',
            'img/fake.png' => '<?php echo 1;',
            'img/mismatch.jpg' => $this->png(2, 2),
            'img/polyglot.png' => $this->png(2, 2) . '<?php system($_GET[1]); ?>',
            'img/good.png' => $this->png(2, 2),
        ], function (\ZipArchive $zip) {
            $zip->addFromString('img/link.png', '/etc/passwd');
            $zip->setExternalAttributesName('img/link.png', \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        });

        $e = (new LegacyImageArchiveInspector())->inspect($zip, [])['entries'];

        $this->assertSame('editable_source', $e['img/design/header.psd']['status']);
        $this->assertSame(['executable_or_markup'], $e['img/shell.php']['flags']);
        $this->assertSame('suspicious', $e['img/vector.svg']['status']);
        $this->assertSame(['unsupported_type'], $e['img/notes.txt']['flags']);
        $this->assertContains('not_a_valid_image', $e['img/fake.png']['flags']);
        $this->assertContains('content_does_not_match_extension', $e['img/mismatch.jpg']['flags']);
        $this->assertContains('embedded_script', $e['img/polyglot.png']['flags']);
        $this->assertContains('symlink', $e['img/link.png']['flags']);
        $this->assertSame('image', $e['img/good.png']['status']);
        $this->assertSame([2, 2], [$e['img/good.png']['width'], $e['img/good.png']['height']]);
        $this->assertSame(64, strlen($e['img/good.png']['sha256']));
    }

    public function test_size_and_count_limits_are_enforced(): void
    {
        $zip = $this->makeZip(['img/big.png' => $this->png(200, 200), 'img/small.png' => $this->png(2, 2)]);

        $e = (new LegacyImageArchiveInspector(maxEntryBytes: 100))->inspect($zip, [])['entries'];
        $this->assertContains('oversize', $e['img/big.png']['flags']);
        $this->assertSame('image', $e['img/small.png']['status']);

        $this->expectExceptionMessage('zip_too_many_entries');
        (new LegacyImageArchiveInspector(maxEntries: 1))->inspect($zip, []);
    }

    public function test_total_size_limit_and_unreadable_archive_are_refused(): void
    {
        $zip = $this->makeZip(['img/a.png' => $this->png(50, 50)]);
        try {
            (new LegacyImageArchiveInspector(maxTotalBytes: 10))->inspect($zip, []);
            $this->fail('expected refusal');
        } catch (\RuntimeException $e) {
            $this->assertSame('zip_total_size_exceeded', $e->getMessage());
        }

        $bad = $this->tempFile('this is not a zip', '.zip');
        $this->expectExceptionMessage('zip_unreadable');
        (new LegacyImageArchiveInspector())->inspect($bad, []);
    }

    public function test_portrait_directories_and_staff_references_are_flagged_for_owner_review(): void
    {
        $zip = $this->makeZip([
            'img/personal/a.png' => $this->png(2, 2),
            'img/reviews/b.png' => $this->png(3, 3),
            'img/special/s.png' => $this->png(4, 4),
            'img/other/staff-ref.png' => $this->png(5, 5),
        ]);

        $r = (new LegacyImageArchiveInspector())->inspect($zip, [
            $this->ref('img/other/staff-ref.png', 'staff_portrait', 'image', 'employees'),
            $this->ref('img/special/s.png', 'unpublished_inventory', 'image', 'our_clients'),
        ]);

        $this->assertContains('portrait_review', $r['entries']['img/personal/a.png']['flags']);
        $this->assertContains('portrait_review', $r['entries']['img/reviews/b.png']['flags']);
        $this->assertContains('portrait_review', $r['entries']['img/other/staff-ref.png']['flags']);
        $this->assertNotContains('portrait_review', $r['entries']['img/special/s.png']['flags']);
        $this->assertContains('referenced_only_by_unpublished_content', $r['entries']['img/special/s.png']['flags']);
        $this->assertSame(3, $r['summary']['portrait_review']);
    }

    public function test_reference_normalisation(): void
    {
        $this->assertSame('img/a.png', LegacyImageArchiveInspector::normalizeRef('/img/a.png'));
        $this->assertSame('img/a.png', LegacyImageArchiveInspector::normalizeRef('public/img/a.png'));
        $this->assertSame('img/a/b.png', LegacyImageArchiveInspector::normalizeRef('img\\a\\b.png'));
        $this->assertNull(LegacyImageArchiveInspector::normalizeRef('https://x.invalid/a.png'));
        $this->assertNull(LegacyImageArchiveInspector::normalizeRef('//cdn.invalid/a.png'));
        $this->assertNull(LegacyImageArchiveInspector::normalizeRef('   '));
    }
}
