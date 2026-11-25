<?php

namespace Tests\Unit;

use App\Services\Legal\LegalArchiveException;
use App\Services\Legal\LegalRegistry;
use LegalPackageCheck;
use PHPUnit\Framework\TestCase;
use ReleaseTarException;

require_once dirname(__DIR__, 2) . '/scripts/release/legal-package-check.php';

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: active legal-package verification (scripts/release/legal-package-check.php),
 * which is not wired into the release builder in S1-A. Pure PHPUnit, no application boot.
 * Every package here is a synthetic TAR built in this file: synthetic "PDF" bytes, a synthetic fragment and a
 * registry pinned to them. Nothing reads the repository's public PDFs; temporary files live outside the
 * repository and are removed.
 */
class LegalPackageCheckTest extends TestCase
{
    private const ROOT = 'avilona-public-synthetic';
    private const COMMIT = 'fedcba9876543210fedcba9876543210fedcba98';
    private const ARCHIVE = 'resources/legal-archive';
    private const CONSENT = 'inquiry-consent-text';
    private const FRAGMENT = "<h1>Synthetic consent</h1>\n"
        . "<p>Read the <a href=\"%%LEGAL_LINK:POLICY%%\" target=\"_blank\" rel=\"noopener\">synthetic policy</a>.</p>\n";
    private const ASSETS = [
        'personal-data-policy' => 'documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
        'separate-services-terms' => 'documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf',
        'tour-product-terms' => 'documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf',
        'travel-memo-general' => 'documents/Reminder_for_those_traveling_abroad_general.pdf',
        'user-agreement' => 'documents/User_Agreement.pdf',
    ];
    private const UA_PUBLIC = 'public/documents/User_Agreement.pdf';
    private const UA_ARCHIVED = self::ARCHIVE . '/versions/user-agreement/rev-0001/document.pdf';

    private ?string $dir = null;
    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if ($this->dir !== null && is_dir($this->dir)) {
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------------------------------- valid input

    public function test_valid_complete_synthetic_archive_passes(): void
    {
        $files = self::files(self::artifacts());
        $tar = self::tar($files);

        $summary = $this->check($tar);

        $this->assertSame(array_fill_keys(LegalRegistry::DOCUMENT_KEYS, 'rev-0001'), $summary['current']);
        $this->assertSame(6, $summary['artifacts']);
        $this->assertSame(5, $summary['public_files']);
        $this->assertSame(hash('sha256', $files[self::ARCHIVE . '/registry.json']), $summary['registry_sha256']);
        $this->assertSame(hash('sha256', $tar), $summary['archive_sha256']);
    }

    public function test_history_is_verified_and_public_pdfs_must_equal_the_current_version(): void
    {
        // A valid update appends a new policy version and a new consent version linking it.
        $artifacts = self::artifacts();
        $artifacts['personal-data-policy']['rev-0002'] = self::pdf('personal-data-policy', 'rev-0002');
        $artifacts[self::CONSENT]['rev-0002'] = str_replace('Synthetic consent', 'Synthetic consent, second', self::FRAGMENT);
        $files = self::files($artifacts);

        $summary = $this->check(self::tar($files));
        $this->assertSame('rev-0002', $summary['current']['personal-data-policy']);
        $this->assertSame('rev-0002', $summary['current'][self::CONSENT]);
        $this->assertSame(8, $summary['artifacts']);

        $stale = $files;
        $stale['public/' . self::ASSETS['personal-data-policy']] = $artifacts['personal-data-policy']['rev-0001'];
        $this->assertLegalRejected('package_public_mismatch', self::tar($stale), 'public PDF still holds the superseded version');

        $missingHistory = $files;
        unset($missingHistory[self::ARCHIVE . '/versions/personal-data-policy/rev-0001/document.pdf']);
        $this->assertLegalRejected('package_inventory_missing', self::tar($missingHistory), 'superseded artifacts stay archived');
    }

    // ------------------------------------------------------------------------------------- registry

    public function test_registry_must_be_present_exactly_once_as_a_regular_file(): void
    {
        $files = self::files(self::artifacts());
        $withoutRegistry = $files;
        unset($withoutRegistry[self::ARCHIVE . '/registry.json']);

        $this->assertLegalRejected('package_registry_missing', self::tar($withoutRegistry), 'missing');
        $this->assertLegalRejected('package_registry_missing', self::tar($withoutRegistry, [self::ARCHIVE . '/registry.json']), 'directory instead of file');

        $duplicate = self::entry(self::ARCHIVE . '/registry.json', $files[self::ARCHIVE . '/registry.json']);
        $this->assertTarRejected('tar_duplicate', self::tar($files, [], $duplicate), 'duplicate registry');
        $caseVariant = self::entry(self::ARCHIVE . '/Registry.json', $files[self::ARCHIVE . '/registry.json']);
        $this->assertTarRejected('tar_case_collision', self::tar($files, [], $caseVariant), 'case-variant registry');

        $invalid = $files;
        $invalid[self::ARCHIVE . '/registry.json'] = str_replace("\n", "\r\n", $files[self::ARCHIVE . '/registry.json']);
        $this->assertLegalRejected('registry_encoding', self::tar($invalid), 'registry rules apply inside the package');
    }

    // ------------------------------------------------------------------------------------- inventory

    public function test_archive_inventory_is_exact(): void
    {
        $files = self::files(self::artifacts());

        $missingFragment = $files;
        unset($missingFragment[self::ARCHIVE . '/versions/inquiry-consent-text/rev-0001/fragment.html']);
        $this->assertLegalRejected('package_inventory_missing', self::tar($missingFragment), 'missing fragment');

        $missingPdf = $files;
        unset($missingPdf[self::UA_ARCHIVED]);
        $this->assertLegalRejected('package_inventory_missing', self::tar($missingPdf), 'missing archived PDF');

        foreach ([
            'note beside an artifact' => self::ARCHIVE . '/versions/user-agreement/rev-0001/notes.txt',
            'unregistered version' => self::ARCHIVE . '/versions/user-agreement/rev-0002/document.pdf',
            'file at the archive root' => self::ARCHIVE . '/README.txt',
            'legacy copy' => self::ARCHIVE . '/legacy/User_Agreement.pdf',
        ] as $label => $path) {
            $extra = $files;
            $extra[$path] = self::pdf('user-agreement', 'extra');
            $this->assertLegalRejected('package_inventory_unexpected', self::tar($extra), $label);
        }

        $this->assertLegalRejected('package_inventory_unexpected', self::tar($files, [self::ARCHIVE . '/drafts']), 'unexpected directory');

        $caseRoot = $files;
        $caseRoot['resources/Legal-Archive/extra.pdf'] = self::pdf('user-agreement', 'rev-0001');
        // Two spellings of one directory, without directory entries: the reader rejects it before the checker.
        $this->assertTarRejected('tar_case_collision', self::tar($caseRoot, [], '', false), 'case variant beside the archive root');

        $variantOnly = [];
        foreach ($files as $path => $bytes) {
            $variantOnly[str_replace(self::ARCHIVE, 'resources/Legal-Archive', $path)] = $bytes;
        }
        $this->assertLegalRejected('package_inventory_unexpected', self::tar($variantOnly), 'only a case variant of the archive root');
    }

    // ------------------------------------------------------------------------------------- artifacts

    public function test_artifact_size_and_hash_must_match_the_registry(): void
    {
        $files = self::files(self::artifacts());

        $sameSize = $files;
        $sameSize[self::UA_ARCHIVED] = strtoupper($files[self::UA_ARCHIVED]);
        $this->assertSame(strlen($files[self::UA_ARCHIVED]), strlen($sameSize[self::UA_ARCHIVED]));
        $this->assertLegalRejected('package_artifact_mismatch', self::tar($sameSize), 'same size, different bytes');

        $longer = $files;
        $longer[self::UA_ARCHIVED] .= "\n";
        $this->assertLegalRejected('package_artifact_mismatch', self::tar($longer), 'different size');

        $fragment = $files;
        $fragment[self::ARCHIVE . '/versions/inquiry-consent-text/rev-0001/fragment.html'] = str_replace('policy', 'POLICY', self::FRAGMENT);
        $this->assertLegalRejected('package_artifact_mismatch', self::tar($fragment), 'fragment bytes differ from the pin');
    }

    public function test_fragment_grammar_and_declared_links_are_verified(): void
    {
        $artifacts = self::artifacts();
        $artifacts[self::CONSENT]['rev-0001'] = "<h1>Synthetic consent</h1>\n<p>Broken<br>markup.</p>\n";
        $this->assertLegalRejected('fragment_markup', self::tar(self::files($artifacts)), 'pinned but invalid fragment');

        $artifacts[self::CONSENT]['rev-0001'] = "<h1>Synthetic consent</h1>\n<p>No link at all.</p>\n";
        $this->assertLegalRejected('fragment_placeholder', self::tar(self::files($artifacts)), 'declared link not used');
    }

    public function test_a_package_whose_consent_lacks_the_policy_link_is_rejected(): void
    {
        $artifacts = self::artifacts();
        $artifacts[self::CONSENT]['rev-0001'] = "<h1>Synthetic consent</h1>\n<p>No link at all.</p>\n";
        $files = self::files($artifacts);
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $data = json_decode($files[self::ARCHIVE . '/registry.json'], true, 64, JSON_THROW_ON_ERROR);
        $data['documents'][0]['versions'][0]['links'] = [];
        $files[self::ARCHIVE . '/registry.json'] = json_encode($data, $flags) . "\n";

        $this->assertLegalRejected('registry_policy_link', self::tar($files));
    }

    public function test_retained_evidence_is_bounded(): void
    {
        $this->assertSame(LegalRegistry::MAX_PAGE_VERSIONS, LegalPackageCheck::MAX_RETAINED_FRAGMENTS);

        $cases = [
            'public PDFs' => [LegalPackageCheck::MAX_PUBLIC_PDFS, static fn (int $i): array => ["public/documents/$i.pdf", 'file']],
            'fragments' => [LegalPackageCheck::MAX_RETAINED_FRAGMENTS, static fn (int $i): array => [self::ARCHIVE . "/v/$i/fragment.html", 'file']],
            'archive entries' => [LegalPackageCheck::MAX_ARCHIVE_ENTRIES, static fn (int $i): array => [self::ARCHIVE . "/d$i", 'dir']],
        ];
        foreach ($cases as $label => [$limit, $make]) {
            $check = new LegalPackageCheck();
            $visit = static function (int $i) use ($check, $make): void {
                [$path, $type] = $make($i);
                $check->visit(['relative' => $path, 'type' => $type, 'size' => 1, 'sha256' => str_repeat('0', 64)], 'x');
            };
            for ($i = 0; $i < $limit; $i++) {
                $visit($i);
            }
            try {
                $visit($limit);
                $this->fail("$label: the limit must apply");
            } catch (LegalArchiveException $e) {
                $this->assertSame('package_limit', $e->reason, $label);
            }
        }
    }

    // ------------------------------------------------------------------------------------- public PDFs

    public function test_public_pdfs_are_exactly_the_five_registered_files_with_current_bytes(): void
    {
        $files = self::files(self::artifacts());

        $drift = $files;
        $drift[self::UA_PUBLIC] = strrev($files[self::UA_PUBLIC]);
        $this->assertLegalRejected('package_public_mismatch', self::tar($drift), 'public PDF drift');

        $missing = $files;
        unset($missing[self::UA_PUBLIC]);
        $this->assertLegalRejected('package_public_missing', self::tar($missing), 'missing public PDF');

        foreach ([
            'extra PDF' => 'public/documents/Extra_Synthetic.pdf',
            'nested PDF' => 'public/documents/archive/Nested.pdf',
            'upper-case extension' => 'public/documents/Other.PDF',
        ] as $label => $path) {
            $extra = $files;
            $extra[$path] = self::pdf('user-agreement', 'rev-0001');
            $this->assertLegalRejected('package_public_unexpected', self::tar($extra), $label);
        }

        $other = $files;
        $other['public/img/brochure.pdf'] = self::pdf('user-agreement', 'rev-0001');
        $this->assertSame(5, $this->check(self::tar($other))['public_files'], 'PDFs outside public/documents are not legal bindings');
    }

    public function test_archived_and_public_pdf_must_be_byte_identical_even_when_both_match_their_own_pins(): void
    {
        $artifacts = self::artifacts();
        $files = self::files($artifacts);
        $artifacts['user-agreement']['rev-0001'] = self::pdf('user-agreement', 'different-approved-bytes');
        $consistentArchive = self::files($artifacts);
        $consistentArchive[self::UA_PUBLIC] = $files[self::UA_PUBLIC];

        $this->assertLegalRejected('package_public_mismatch', self::tar($consistentArchive));
    }

    public function test_integrity_is_verified_above_the_content_scan_threshold(): void
    {
        $artifacts = self::artifacts();
        $large = self::pdf('user-agreement', 'rev-0001') . str_repeat('%synthetic padding%', 110_000);
        $this->assertGreaterThan(2_000_000, strlen($large));
        $artifacts['user-agreement']['rev-0001'] = $large;
        $files = self::files($artifacts);

        $this->assertSame('rev-0001', $this->check(self::tar($files))['current']['user-agreement']);

        $lastByte = $files;
        $lastByte[self::UA_PUBLIC] = substr($large, 0, -1) . 'X';
        $this->assertLegalRejected('package_public_mismatch', self::tar($lastByte), 'only the final byte of a large PDF differs');

        $archived = $files;
        $archived[self::UA_ARCHIVED] = substr($large, 0, -1) . 'X';
        $this->assertLegalRejected('package_artifact_mismatch', self::tar($archived), 'only the final byte of a large artifact differs');
    }

    // ------------------------------------------------------------------------------------- invalid entries

    public function test_nonregular_and_invalid_entries_are_rejected(): void
    {
        $files = self::files(self::artifacts());

        $symlink = self::header(self::ROOT . '/' . self::ARCHIVE . '/current', 0, '2', ['linkname' => 'registry.json']);
        $this->assertTarRejected('tar_type', self::tar($files, [], $symlink), 'symbolic link in the archive');

        $hardlink = self::header(self::ROOT . '/public/documents/Linked.pdf', 0, '1', ['linkname' => self::ROOT . '/' . self::UA_PUBLIC]);
        $this->assertTarRejected('tar_type', self::tar($files, [], $hardlink), 'hard link to a public PDF');

        $tar = self::tar($files);
        $truncated = substr($tar, 0, intdiv(strlen($tar), 2));
        try {
            $this->check($truncated);
            $this->fail('a truncated package must be rejected');
        } catch (ReleaseTarException $e) {
            $this->assertContains($e->reason, ['tar_truncated', 'tar_termination', 'tar_header_checksum', 'tar_header_format']);
        }

        $corrupt = $tar;
        $corrupt[2 * 512 + 3] = $corrupt[2 * 512 + 3] === 'a' ? 'b' : 'a';
        $this->assertTarRejected('tar_header_checksum', $corrupt, 'corrupted header');

        $this->assertTarRejected('tar_prefix', $tar, 'wrong expected prefix', 'avilona-public-other/');
    }

    public function test_verification_uses_only_the_tar_input(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scripts/release/legal-package-check.php');
        $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $source);

        foreach (['file_get_contents', 'fopen', 'hash_file', 'file_exists', 'is_file', 'glob(', 'scandir', 'public_path', 'base_path', 'getcwd', 'file_put_contents', 'unlink', 'asset('] as $call) {
            $this->assertStringNotContainsString($call, $code, "the checker must not use $call");
        }
        $this->assertStringContainsString('ReleaseTarReader::read(', $code);

        $buildSource = (string) file_get_contents(dirname(__DIR__, 2) . '/scripts/release/build-public-package.php');
        $buildCode = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $buildSource);
        $this->assertStringNotContainsString('legal-package-check', $buildCode, 'S1-A: the active verifier is not wired into the builder');
        $this->assertStringNotContainsString('LegalPackageCheck', $buildCode);
        $this->assertStringContainsString('legal_archive=NOT_ACTIVE', $buildCode);
        $this->assertStringContainsString("legal_archive NOT_ACTIVE\\n", $buildCode);
        // Static regression guard only; publication behaviour itself is an owner-run acceptance scenario.
        $this->assertStringNotContainsString('rename(', $buildCode, 'no replace-capable publication fallback');
    }

    // ------------------------------------------------------------------------------------- package fixtures

    /** @return array<string, array<string, string>> document key => version id => synthetic artifact bytes */
    private static function artifacts(): array
    {
        $artifacts = [self::CONSENT => ['rev-0001' => self::FRAGMENT]];
        foreach (array_keys(self::ASSETS) as $key) {
            $artifacts[$key] = ['rev-0001' => self::pdf($key, 'rev-0001')];
        }

        return $artifacts;
    }

    private static function pdf(string $key, string $version): string
    {
        return "%PDF-1.4\n% synthetic placeholder for $key $version\n%%EOF\n";
    }

    /** Registry pinned to the given artifacts: every version activated in order, the last one current. */
    private static function registry(array $artifacts): string
    {
        $documents = [];
        foreach ($artifacts as $key => $versions) {
            $isPage = $key === self::CONSENT;
            $records = [];
            $activations = [];
            $n = 0;
            foreach ($versions as $id => $bytes) {
                $n++;
                $type = $isPage ? LegalRegistry::CONTENT_HTML : LegalRegistry::CONTENT_PDF;
                $records[] = [
                    'version_id' => $id,
                    'locked' => true,
                    'approval_reference' => 'SYN-APR-' . $n,
                    'content_type' => $type,
                    'path' => LegalRegistry::artifactPath($key, $id, $type),
                    'sha256' => hash('sha256', $bytes),
                    'bytes' => strlen($bytes),
                    // Consent rev-N links policy rev-N, so adding a version never edits an earlier record.
                    'links' => $isPage ? [[
                        'placeholder' => 'POLICY',
                        'document_key' => 'personal-data-policy',
                        'version_id' => isset($artifacts['personal-data-policy'][$id]) ? $id : 'rev-0001',
                    ]] : [],
                ];
                $activations[] = ['version_id' => $id, 'reference' => 'SYN-ACT-' . $n, 'effective_from' => sprintf('2027-01-%02d', $n)];
            }
            $documents[] = [
                'key' => $key,
                'surface' => $isPage
                    ? ['type' => 'page', 'route_name' => 'personal_data_consent.info']
                    : ['type' => 'public-file', 'asset_path' => self::ASSETS[$key], 'repository_path' => 'public/' . self::ASSETS[$key]],
                'versions' => $records,
                'activations' => $activations,
                'restrictions' => [],
            ];
        }

        return json_encode(['format' => 1, 'hash_algorithm' => 'sha256', 'documents' => $documents],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /** @return array<string, string> package-relative path => bytes */
    private static function files(array $artifacts): array
    {
        $files = [
            'artisan' => "synthetic\n",
            'public/documents/.htaccess' => "synthetic\n",
            self::ARCHIVE . '/registry.json' => self::registry($artifacts),
        ];
        foreach ($artifacts as $key => $versions) {
            foreach ($versions as $id => $bytes) {
                $files[self::ARCHIVE . '/' . LegalRegistry::artifactPath($key, $id, $key === self::CONSENT ? LegalRegistry::CONTENT_HTML : LegalRegistry::CONTENT_PDF)] = $bytes;
            }
            if (isset(self::ASSETS[$key])) {
                $files['public/' . self::ASSETS[$key]] = $versions[array_key_last($versions)];
            }
        }

        return $files;
    }

    /** git-archive-like package: global header, root, sorted directories, sorted files, extra raw entries. */
    private static function tar(array $files, array $extraDirs = [], string $extra = '', bool $withDirs = true): string
    {
        $dirs = array_fill_keys($extraDirs, true);
        if ($withDirs) {
            foreach (array_keys($files) as $path) {
                $parts = explode('/', $path);
                array_pop($parts);
                $dir = '';
                foreach ($parts as $part) {
                    $dir = $dir === '' ? $part : "$dir/$part";
                    $dirs[$dir] = true;
                }
            }
        }
        ksort($dirs, SORT_STRING);
        ksort($files, SORT_STRING);

        $body = self::paxEntry('g', 'pax_global_header', self::record('comment', self::COMMIT)) . self::entry('', null);
        foreach (array_keys($dirs) as $dir) {
            $body .= self::entry($dir, null);
        }
        foreach ($files as $path => $bytes) {
            $body .= self::entry($path, $bytes);
        }

        return self::finish($body . $extra);
    }

    // ------------------------------------------------------------------------------------- TAR fixtures

    /** One entry below the root; long paths use the ustar prefix split like git. A null body is a directory. */
    private static function entry(string $path, ?string $bytes): string
    {
        $full = self::ROOT . '/' . $path . ($bytes === null ? ($path === '' ? '' : '/') : '');
        $type = $bytes === null ? '5' : '0';
        $size = $bytes === null ? 0 : strlen($bytes);
        if (strlen($full) <= 100) {
            $header = self::header($full, $size, $type);
        } else {
            $cut = strrpos(substr(rtrim($full, '/'), 0, 156), '/');
            if ($cut === false || strlen($full) - $cut - 1 > 100) {
                throw new \LogicException('fixture path too long for a ustar split');
            }
            $header = self::header(substr($full, $cut + 1), $size, $type, ['prefix' => substr($full, 0, $cut)]);
        }

        return $header . (string) $bytes . self::padding($size);
    }

    private static function header(string $name, int $size, string $type, array $o = []): string
    {
        $h = self::field($name, 100)
            . sprintf('%07o', $type === '5' ? 0775 : 0664) . "\0"
            . sprintf('%07o', 0) . "\0"
            . sprintf('%07o', 0) . "\0"
            . sprintf('%011o', $size) . "\0"
            . sprintf('%011o', 1764000000) . "\0"
            . '        '
            . $type
            . self::field($o['linkname'] ?? '', 100)
            . "ustar\0" . '00'
            . self::field('root', 32)
            . self::field('root', 32)
            . sprintf('%07o', 0) . "\0"
            . sprintf('%07o', 0) . "\0"
            . self::field($o['prefix'] ?? '', 155)
            . str_repeat("\0", 12);

        return substr_replace($h, sprintf('%07o', array_sum(unpack('C*', $h))) . "\0", 148, 8);
    }

    private static function field(string $value, int $width): string
    {
        if (strlen($value) > $width) {
            throw new \LogicException('fixture field too long');
        }

        return str_pad($value, $width, "\0");
    }

    private static function padding(int $size): string
    {
        return str_repeat("\0", (512 - $size % 512) % 512);
    }

    private static function record(string $key, string $value): string
    {
        $body = ' ' . $key . '=' . $value . "\n";
        $length = strlen($body) + 1;
        while (strlen((string) $length) + strlen($body) !== $length) {
            $length = strlen((string) $length) + strlen($body);
        }

        return $length . $body;
    }

    private static function paxEntry(string $type, string $name, string $payload): string
    {
        return self::header($name, strlen($payload), $type) . $payload . self::padding(strlen($payload));
    }

    private static function finish(string $body): string
    {
        $total = strlen($body) + 1024;

        return $body . str_repeat("\0", 1024 + (10240 - $total % 10240) % 10240);
    }

    // ------------------------------------------------------------------------------------- assertions

    private function check(string $tar, ?string $prefix = self::ROOT . '/'): array
    {
        return LegalPackageCheck::checkTar($this->write($tar), $prefix);
    }

    private function assertLegalRejected(string $reason, string $tar, string $label = ''): void
    {
        try {
            $this->check($tar);
        } catch (LegalArchiveException $e) {
            $this->assertSame($reason, $e->reason, "$label: " . $e->getMessage());

            return;
        }
        $this->fail("$label: package must be rejected with $reason");
    }

    private function assertTarRejected(string $reason, string $tar, string $label, ?string $prefix = self::ROOT . '/'): void
    {
        try {
            $this->check($tar, $prefix);
        } catch (ReleaseTarException $e) {
            $this->assertSame($reason, $e->reason, "$label: " . $e->getMessage());

            return;
        }
        $this->fail("$label: package must be rejected with $reason");
    }

    private function write(string $bytes): string
    {
        if ($this->dir === null) {
            $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avilona-s1a-pkg-' . bin2hex(random_bytes(8));
            $this->assertTrue(mkdir($this->dir, 0700));
        }
        $file = $this->dir . DIRECTORY_SEPARATOR . count($this->created) . '.tar';
        $this->created[] = $file;
        $this->assertSame(strlen($bytes), file_put_contents($file, $bytes));

        return $file;
    }
}
