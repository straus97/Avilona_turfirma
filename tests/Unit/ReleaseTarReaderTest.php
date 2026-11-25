<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReleaseGzipVerifier;
use ReleaseTarException;
use ReleaseTarReader;

require_once dirname(__DIR__, 2) . '/scripts/release/read-tar.php';

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: strict TAR reader (scripts/release/read-tar.php).
 * Pure PHPUnit, no application boot. Archives are synthetic and built here byte by byte in the layout
 * `git archive --format=tar` writes; temporary files live outside the repository and are removed.
 */
class ReleaseTarReaderTest extends TestCase
{
    private const OID = '0123456789abcdef0123456789abcdef01234567';
    private const COMMIT = 'fedcba9876543210fedcba9876543210fedcba98';
    private const ROOT = 'avilona-public-synthetic';

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

    public function test_valid_git_style_archive_is_read_with_metadata_and_hashes(): void
    {
        $readme = "synthetic text\n";
        $logo = str_repeat("\x89PNG", 300);
        $tar = self::finish(
            self::globalHeader(self::COMMIT)
            . self::dir(self::ROOT . '/')
            . self::dir(self::ROOT . '/public/')
            . self::file(self::ROOT . '/public/readme.txt', $readme)
            . self::file(self::ROOT . '/public/logo.png', $logo)
        );

        $delivered = [];
        $result = ReleaseTarReader::read($this->write($tar), [
            'prefix' => self::ROOT . '/',
            'want_content' => static fn (string $path, int $size): bool => str_ends_with($path, '.txt'),
            'visit' => static function (array $entry, ?string $content) use (&$delivered): void {
                $delivered[$entry['relative']] = $content;
            },
        ]);

        $this->assertSame(self::COMMIT, $result['global_comment']);
        $this->assertSame(strlen($tar), $result['archive_bytes']);
        $this->assertSame(hash('sha256', $tar), $result['archive_sha256']);
        $this->assertSame(['', 'public', 'public/readme.txt', 'public/logo.png'], array_column($result['entries'], 'relative'));
        $this->assertSame(['dir', 'dir', 'file', 'file'], array_column($result['entries'], 'type'));
        $this->assertSame(hash('sha256', $readme), $result['entries'][2]['sha256']);
        $this->assertSame(hash('sha256', $logo), $result['entries'][3]['sha256']);
        $this->assertNull($result['entries'][0]['sha256']);
        $this->assertSame(strlen($logo), $result['entries'][3]['size']);
        $this->assertSame(0664, $result['entries'][2]['mode']);
        $this->assertSame($readme, $delivered['public/readme.txt']);
        $this->assertNull($delivered['public/logo.png'], 'unrequested content is never delivered');
    }

    public function test_extended_pax_path_and_ustar_prefix_split_are_resolved(): void
    {
        $long = self::ROOT . '/public/img/' . str_repeat('d', 120) . '/' . str_repeat('n', 150) . '.png';
        $split = str_repeat('p', 120) . '/' . str_repeat('q', 90) . '.css';
        $tar = self::finish(
            self::globalHeader(self::COMMIT)
            . self::extended(self::OID, $long)
            . self::file(self::OID . '.data', 'x')
            . self::header(substr($split, 121), 1, '0', ['prefix' => substr($split, 0, 120)]) . 'y' . self::padding(1)
        );

        $result = ReleaseTarReader::read($this->write($tar));

        $this->assertSame([$long, $split], array_column($result['entries'], 'path'));
        $this->assertSame(hash('sha256', 'x'), $result['entries'][0]['sha256']);
    }

    public function test_utf8_paths_and_an_archive_without_global_header_are_supported(): void
    {
        $path = 'public/img/синтетика-пример.png';
        $result = ReleaseTarReader::read($this->write(self::finish(self::file($path, 'z'))));

        $this->assertNull($result['global_comment']);
        $this->assertSame([$path], array_column($result['entries'], 'path'));
    }

    // ------------------------------------------------------------------------------------- headers

    public function test_header_checksum_is_verified(): void
    {
        $entry = self::file('a.txt', 'abc');
        $corrupt = $entry;
        $corrupt[0] = 'b';
        $this->assertTarRejected('tar_header_checksum', self::finish($corrupt));

        $malformed = self::header('a.txt', 3, '0', ['chksum_field' => "12345x7\0"]) . 'abc' . self::padding(3);
        $this->assertTarRejected('tar_header_checksum', self::finish($malformed));
    }

    public function test_numeric_fields_must_be_terminated_octal_within_range(): void
    {
        $cases = [
            'non-octal digit' => ['size_field' => "0000000000x\0"],
            'no terminator' => ['size_field' => '000000000003'],
            'empty' => ['size_field' => str_repeat("\0", 12)],
            'base-256' => ['size_field' => "\x80" . str_repeat("\0", 10) . "\x03"],
            'mode out of range' => ['mode_field' => "0017777\0"],
        ];
        foreach ($cases as $label => $override) {
            $this->assertTarRejected('tar_numeric', self::finish(self::header('a.txt', 3, '0', $override) . 'abc' . self::padding(3)), $label);
        }

        $huge = self::header('a.bin', 0, '0', ['size_field' => "77777777777\0"]);
        $this->assertTarRejected('tar_size', self::finish($huge), 'size above MAX_ENTRY_BYTES (no data is read)');
    }

    public function test_only_posix_ustar_headers_with_clean_fields_are_accepted(): void
    {
        $this->assertTarRejected('tar_header_format', self::finish(self::header('a', 0, '0', ['magic' => 'ustar ', 'version' => " \0"])));
        $this->assertTarRejected('tar_header_format', self::finish(self::header('a', 0, '0', ['pad' => "x" . str_repeat("\0", 11)])));
        $this->assertTarRejected('tar_header_format', self::finish(self::header("a\0b", 0, '0')));
        $this->assertTarRejected('tar_header_format', self::finish(self::header('a', 0, '0', ['linkname' => 'target'])));
    }

    // ------------------------------------------------------------------------------------- completion

    public function test_truncated_header_data_and_padding_are_rejected(): void
    {
        $entry = self::file('a.txt', str_repeat('a', 700));

        $this->assertTarRejected('tar_truncated', substr($entry, 0, 300), 'short header');
        $this->assertTarRejected('tar_truncated', substr($entry, 0, 512 + 100), 'short data');
        $this->assertTarRejected('tar_truncated', substr($entry, 0, 512 + 700 + 10), 'short padding');
    }

    public function test_padding_must_be_zero(): void
    {
        $entry = self::file('a.txt', 'abc');
        $entry[512 + 3] = 'x';

        $this->assertTarRejected('tar_padding', self::finish($entry));
    }

    public function test_two_zero_blocks_must_terminate_the_archive_with_nothing_but_zero_blocks_after(): void
    {
        $entry = self::file('a.txt', 'abc');
        $zero = str_repeat("\0", 512);

        $this->assertTarRejected('tar_termination', $entry, 'no terminator');
        $this->assertTarRejected('tar_termination', $entry . $zero, 'one zero block');
        $this->assertTarRejected('tar_termination', $entry . $zero . $zero . 'x' . str_repeat("\0", 511), 'nonzero trailing data');
        $this->assertTarRejected('tar_termination', $entry . $zero . $zero . str_repeat("\0", 100), 'partial trailing block');
        $this->assertTarRejected('tar_termination', $entry . str_repeat($zero, ReleaseTarReader::MAX_TRAILER_BLOCKS + 1), 'unbounded trailer');

        $this->assertCount(1, ReleaseTarReader::read($this->write($entry . $zero . $zero))['entries'], 'minimal trailer');
        $this->assertCount(1, ReleaseTarReader::read($this->write(self::finish($entry)))['entries'], 'git record padding');
    }

    // ------------------------------------------------------------------------------------- PAX

    public function test_malformed_pax_records_are_rejected(): void
    {
        $cases = [
            'length too long' => "99 path=a\n",
            'length too short' => "5 path=a\n",
            'leading zero length' => "010 path=a\n",
            'missing LF' => self::lengthPrefixed(' path=ab'),
            'no equals sign' => self::lengthPrefixed(" pathab\n"),
            'empty value' => self::lengthPrefixed(" path=\n"),
            'trailing bytes' => self::record('path', 'a') . 'zz',
        ];
        foreach ($cases as $label => $payload) {
            $tar = self::paxEntry('x', self::OID . '.paxheader', $payload) . self::file(self::OID . '.data', 'x');
            $this->assertTarRejected('tar_pax', self::finish($tar), $label);
        }
    }

    public function test_only_the_path_key_is_accepted_in_extended_headers(): void
    {
        foreach (['size' => '1', 'linkpath' => 'x', 'mtime' => '1', 'uid' => '0'] as $key => $value) {
            $tar = self::paxEntry('x', self::OID . '.paxheader', self::record('path', 'a') . self::record($key, $value))
                . self::file(self::OID . '.data', 'x');
            $this->assertTarRejected('tar_pax_key', self::finish($tar), $key);
        }

        $duplicate = self::paxEntry('x', self::OID . '.paxheader', self::record('path', 'a') . self::record('path', 'b'))
            . self::file(self::OID . '.data', 'x');
        $this->assertTarRejected('tar_pax_key', self::finish($duplicate), 'duplicate path');

        $global = self::paxEntry('g', 'pax_global_header', self::record('comment', self::COMMIT) . self::record('path', 'a'));
        $this->assertTarRejected('tar_pax_key', self::finish($global . self::file('a', 'x')), 'global header with path');
    }

    public function test_extended_headers_must_be_placed_unambiguously(): void
    {
        $extended = self::extended(self::OID, 'long/path.txt');
        $data = self::file(self::OID . '.data', 'x');

        $this->assertTarRejected('tar_pax_placement', self::finish($extended), 'dangling at end');
        $this->assertTarRejected('tar_pax_placement', self::finish($extended . $extended . $data), 'two pending');
        $this->assertTarRejected('tar_pax_placement', self::finish($extended . self::globalHeader(self::COMMIT) . $data), 'global after extended');
        $this->assertTarRejected('tar_pax_placement', self::finish($extended . self::file('other.data', 'x')), 'foreign entry after extended');
        $this->assertTarRejected('tar_pax_placement', self::finish(self::file('a', 'x') . self::globalHeader(self::COMMIT)), 'global not first');
        $this->assertTarRejected('tar_pax_placement', self::finish(self::globalHeader(self::COMMIT) . self::globalHeader(self::COMMIT)), 'two globals');
        $this->assertTarRejected('tar_header_format', self::finish(self::paxEntry('x', 'PaxHeader/a', self::record('path', 'a')) . self::file('a', 'x')), 'non-git name');
    }

    // ------------------------------------------------------------------------------------- paths

    public function test_unsafe_paths_are_rejected_including_pax_resolved_and_directory_paths(): void
    {
        $paths = ['../x', 'a/../b', 'a/./b', 'a//b', '/abs', 'C:/x', 'c:x', "a\\b", "a\x01b", "a\x7Fb", "a\xC2\x85b", "a\xFFb"];
        foreach ($paths as $path) {
            $this->assertTarRejected('tar_path', self::finish(self::file($path, 'x')), bin2hex($path));
        }

        $viaPax = self::extended(self::OID, 'public/../../etc/x') . self::file(self::OID . '.data', 'x');
        $this->assertTarRejected('tar_path', self::finish($viaPax), 'effective PAX path is validated');
        $this->assertTarRejected('tar_path', self::finish(self::dir('a/../')), 'directory path is validated');
        $this->assertTarRejected('tar_path', self::finish(self::header('dir', 0, '5')), 'directory without slash');
        $this->assertTarRejected('tar_path', self::finish(self::header('file/', 0, '0')), 'file with slash');
        $this->assertTarRejected('tar_size', self::finish(self::header('dir/', 5, '5') . 'abcde' . self::padding(5)), 'directory with data');
    }

    public function test_exact_and_case_only_duplicates_are_rejected(): void
    {
        $this->assertTarRejected('tar_duplicate', self::finish(self::file('a/x.txt', '1') . self::file('a/x.txt', '2')));
        $this->assertTarRejected('tar_duplicate', self::finish(self::dir('a/') . self::dir('a/')));
        $this->assertTarRejected('tar_case_collision', self::finish(self::file('a/File.txt', '1') . self::file('a/file.TXT', '2')));
        $this->assertTarRejected('tar_case_collision', self::finish(self::file('Пример.txt', '1') . self::file('пример.txt', '2')));
        $this->assertTarRejected('tar_case_collision', self::finish(self::dir('Docs/') . self::dir('docs/')));
    }

    public function test_file_and_directory_collisions_are_rejected(): void
    {
        $this->assertTarRejected('tar_collision', self::finish(self::file('a', '1') . self::dir('a/')), 'file then directory');
        $this->assertTarRejected('tar_collision', self::finish(self::dir('a/') . self::file('a', '1')), 'directory then file');
        $this->assertTarRejected('tar_collision', self::finish(self::file('a', '1') . self::file('a/b', '2')), 'entry below a file');
        $this->assertTarRejected('tar_collision', self::finish(self::file('x/y', '1') . self::file('X', '2')), 'file at an implicit directory');
    }

    public function test_links_devices_and_unsupported_types_are_rejected(): void
    {
        foreach (['1' => 'hard link', '2' => 'symbolic link', '3' => 'character device', '4' => 'block device', '6' => 'FIFO', '7' => 'contiguous', 'L' => 'GNU long name', "\0" => 'pre-POSIX'] as $type => $label) {
            $this->assertTarRejected('tar_type', self::finish(self::header('link', 0, (string) $type)), $label);
        }
    }

    public function test_prefix_is_enforced_and_its_root_entry_must_come_first(): void
    {
        $options = ['prefix' => self::ROOT . '/'];

        $this->assertTarRejected('tar_prefix', self::finish(self::dir(self::ROOT . '/') . self::file('elsewhere.txt', 'x')), 'outside', $options);
        $this->assertTarRejected('tar_prefix', self::finish(self::file(self::ROOT . '/a', 'x') . self::dir(self::ROOT . '/')), 'root not first', $options);
        $this->assertTarRejected('tar_prefix', self::finish(self::file(self::ROOT, 'x')), 'root as a file', $options);
        $this->assertTarRejected('tar_prefix', self::finish(self::file(self::ROOT . 'x/a', 'x')), 'sibling with the same stem', $options);

        $result = ReleaseTarReader::read($this->write(self::finish(self::file(self::ROOT . '/a', 'x'))), $options);
        $this->assertSame(['a'], array_column($result['entries'], 'relative'), 'the root entry is optional');
    }

    // ------------------------------------------------------------------------------------- Windows names, parents, limits

    public function test_names_windows_would_read_differently_are_rejected_not_normalized(): void
    {
        $paths = [
            'a./b', 'a /b', 'dir/name.', 'dir/name ', 'file.txt:stream', 'dir::$INDEX_ALLOCATION/x', 'x/C:/y', 'a<b', 'a>b',
            'a"b', 'a|b', 'a?b', 'a*b', 'CON', 'con.txt', 'public/Nul.tar.gz', 'aux/readme', 'prn .log', 'COM0', 'com1',
            'lpt9.png', "COM\u{00B9}", "lpt\u{00B3}.txt", 'CONIN$', 'conout$.txt',
        ];
        foreach ($paths as $path) {
            $this->assertNotNull(ReleaseTarReader::pathViolation($path), $path);
            $this->assertTarRejected('tar_path', self::finish(self::file($path, 'x')), $path);
        }

        $viaPax = self::extended(self::OID, 'public/img/logo.png:stream') . self::file(self::OID . '.data', 'x');
        $this->assertTarRejected('tar_path', self::finish($viaPax), 'PAX path');
        $this->assertTarRejected('tar_path', self::finish(self::dir('Docs./')), 'directory entry');

        $ordinary = [
            'console.js', 'auxiliary.css', 'com10.png', 'CON1.txt', 'lpt.txt', 'nul-value.js', '.htaccess', '.gitignore',
            'a b/c d.png', 'file..name', '~tilde.css', 'Пример выезда.pdf',
        ];
        $tar = '';
        foreach ($ordinary as $path) {
            $this->assertNull(ReleaseTarReader::pathViolation($path), $path);
            $tar .= self::file($path, 'x');
        }
        $this->assertCount(count($ordinary), ReleaseTarReader::read($this->write(self::finish($tar)))['entries']);
    }

    public function test_every_parent_directory_has_exactly_one_spelling(): void
    {
        $this->assertTarRejected('tar_case_collision',
            self::finish(self::file('public/Documents/first.pdf', '1') . self::file('public/documents/second.pdf', '2')), 'implied parents');
        $this->assertTarRejected('tar_case_collision',
            self::finish(self::file('Docs/a', '1') . self::dir('docs/')), 'implied then explicit');
        $this->assertTarRejected('tar_case_collision',
            self::finish(self::dir('docs/') . self::file('Docs/a', '1')), 'explicit then implied');
        $this->assertTarRejected('tar_case_collision',
            self::finish(self::file('a/B/c', '1') . self::file('a/b/d', '2')), 'deeper level');
        $this->assertTarRejected('tar_case_collision',
            self::finish(self::file('Пример/a', '1') . self::file('пример/b', '2')), 'Cyrillic parent');
        $viaPax = self::file('public/a', '1') . self::extended(self::OID, 'Public/b') . self::file(self::OID . '.data', '2');
        $this->assertTarRejected('tar_case_collision', self::finish($viaPax), 'PAX parent');

        $consistent = self::finish(self::file('docs/a', '1') . self::dir('docs/') . self::file('docs/b', '2'));
        $this->assertCount(3, ReleaseTarReader::read($this->write($consistent))['entries'], 'an explicit entry may follow');
    }

    public function test_aggregate_limits_bound_metadata_and_the_path_index(): void
    {
        $tar = self::finish(self::file('a/b/c.txt', '1') . self::file('a/b/d.txt', '2'));
        $exact = ['entries' => 2, 'depth' => 3, 'path_bytes' => 18, 'index_nodes' => 4, 'index_bytes' => 44];
        $this->assertCount(2, ReleaseTarReader::read($this->write($tar), ['limits' => $exact])['entries']);

        foreach ($exact as $name => $value) {
            $this->assertTarRejected('tar_limit', $tar, $name, ['limits' => [$name => $value - 1]]);
        }

        $deep = implode('/', array_fill(0, ReleaseTarReader::MAX_PATH_DEPTH + 1, 'a'));
        $this->assertTarRejected('tar_limit', self::finish(self::file($deep, 'x')), 'default depth');

        $file = $this->write($tar);
        foreach ([['entries' => ReleaseTarReader::MAX_ENTRIES + 1], ['depth' => 0], ['unknown' => 1], ['entries' => '2'], 'all'] as $limits) {
            try {
                ReleaseTarReader::read($file, ['limits' => $limits]);
                $this->fail('limits may only be lowered: ' . json_encode($limits));
            } catch (ReleaseTarException $e) {
                $this->assertSame('tar_options', $e->reason);
            }
        }
        $this->assertSame(
            [32, 4194304, 40000, 8388608],
            [ReleaseTarReader::MAX_PATH_DEPTH, ReleaseTarReader::MAX_TOTAL_PATH_BYTES, ReleaseTarReader::MAX_INDEX_NODES, ReleaseTarReader::MAX_INDEX_BYTES]
        );
    }

    public function test_alias_key_detects_spellings_a_host_could_open_as_the_same_name(): void
    {
        foreach ([
            'Resources./LEGAL-ARCHIVE :stream', 'resources\\legal-archive', 'resources/legal-archive::$INDEX_ALLOCATION',
        ] as $alias) {
            $this->assertSame('resources/legal-archive', ReleaseTarReader::aliasKey($alias), $alias);
        }
        $this->assertSame('resources/legal-archive-notes', ReleaseTarReader::aliasKey('resources/legal-archive-notes'));
    }

    // ------------------------------------------------------------------------------------- gzip stream contract

    public function test_gzip_verifier_accepts_exactly_one_complete_member(): void
    {
        $data = self::incompressible();
        ReleaseGzipVerifier::assertMatches($this->write(gzencode($data, 9)), hash('sha256', $data), strlen($data));

        $file = $this->write('');
        $gz = gzopen($file, 'wb9'); // the release builder writes through the same stream functions
        $this->assertSame(strlen($data), gzwrite($gz, $data));
        $this->assertTrue(gzclose($gz));
        ReleaseGzipVerifier::assertMatches($file, hash('sha256', $data), strlen($data));
        $this->addToAssertionCount(2);
    }

    public function test_gzip_verifier_rejects_truncation_extra_members_and_trailing_bytes(): void
    {
        $data = self::incompressible();
        $gz = gzencode($data, 9);
        $sha = hash('sha256', $data);
        $length = strlen($data);

        foreach ([1, 8, intdiv(strlen($gz), 2)] as $cut) {
            $this->assertGzipRejected('gzip_truncated', substr($gz, 0, -$cut), $sha, $length, "cut $cut");
        }
        $this->assertGzipRejected('gzip_trailing', $gz . 'x', $sha, $length, 'garbage');
        $this->assertGzipRejected('gzip_trailing', $gz . str_repeat("\0", 512), $sha, $length, 'zero padding');
        $this->assertGzipRejected('gzip_trailing', $gz . gzencode(''), $sha, $length, 'second member');

        foreach (['CRC' => strlen($gz) - 5, 'length' => strlen($gz) - 1] as $label => $at) {
            $corrupt = $gz;
            $corrupt[$at] = chr(ord($corrupt[$at]) ^ 0x01);
            $this->assertGzipRejected('gzip_corrupt', $corrupt, $sha, $length, $label);
        }

        $named = $gz;
        $named[3] = "\x08";
        $this->assertGzipRejected('gzip_header', $named, $sha, $length, 'file name flag');
        $this->assertGzipRejected('gzip_header', 'plain bytes, not gzip', $sha, $length, 'not gzip');

        $this->assertGzipRejected('gzip_content', $gz, hash('sha256', 'other'), $length, 'different content');
        $this->assertGzipRejected('gzip_content', $gz, $sha, $length - 1, 'longer than expected');
        $this->assertGzipRejected('gzip_content', $gz, $sha, $length + 1, 'shorter than expected');
    }

    // ------------------------------------------------------------------------------------- streaming

    public function test_large_entries_are_hashed_in_chunks_and_content_is_bounded(): void
    {
        $big = str_repeat('0123456789abcdef', 196_608) . 'tail'; // 3 MiB + 4 bytes: crosses chunk boundaries
        $limit = str_repeat('L', ReleaseTarReader::MAX_CONTENT_BYTES);
        $over = $limit . '+';
        $tar = self::finish(self::file('big.bin', $big) . self::file('limit.txt', $limit) . self::file('over.txt', $over));

        $delivered = [];
        $result = ReleaseTarReader::read($this->write($tar), [
            'want_content' => static fn (): bool => true,
            'visit' => static function (array $entry, ?string $content) use (&$delivered): void {
                $delivered[$entry['path']] = $content === null ? null : strlen($content);
            },
        ]);

        $this->assertSame(hash('sha256', $big), $result['entries'][0]['sha256']);
        $this->assertSame(strlen($big), $result['entries'][0]['size']);
        $this->assertSame(hash('sha256', $over), $result['entries'][2]['sha256'], 'hashing does not depend on the content limit');
        $this->assertSame(['big.bin' => null, 'limit.txt' => ReleaseTarReader::MAX_CONTENT_BYTES, 'over.txt' => null], $delivered);

        $small = [];
        ReleaseTarReader::read($this->write($tar), [
            'content_limit' => 10,
            'want_content' => static fn (): bool => true,
            'visit' => static function (array $entry, ?string $content) use (&$small): void {
                $small[] = $content;
            },
        ]);
        $this->assertSame([null, null, null], $small);
    }

    public function test_reader_options_are_validated(): void
    {
        $file = $this->write(self::finish(self::file('a', 'x')));

        foreach ([
            ['content_limit' => ReleaseTarReader::MAX_CONTENT_BYTES + 1],
            ['content_limit' => '10'],
            ['prefix' => 'no-slash'],
            ['visit' => 'not a callable function name'],
            ['unknown' => true],
        ] as $options) {
            try {
                ReleaseTarReader::read($file, $options);
                $this->fail('options must be rejected: ' . json_encode($options));
            } catch (ReleaseTarException $e) {
                $this->assertSame('tar_options', $e->reason);
            }
        }
        $this->assertSame(20000, ReleaseTarReader::MAX_ENTRIES);
        $this->assertSame(2000000, ReleaseTarReader::MAX_CONTENT_BYTES);
        $this->assertSame(1048576, ReleaseTarReader::CHUNK_BYTES);
    }

    public function test_diagnostics_are_printable_and_bounded(): void
    {
        $shown = ReleaseTarReader::describe("a\x01b " . str_repeat('z', 400));

        $this->assertStringStartsWith('a\\x01b\\x20', $shown);
        $this->assertLessThanOrEqual(ReleaseTarReader::MAX_DIAGNOSTIC_PATH + 3, strlen($shown));
        $this->assertDoesNotMatchRegularExpression('/[^\x21-\x7E]/', $shown);

        $this->assertLessThan(
            ReleaseTarException::MAX_DETAIL_BYTES + 64,
            strlen((new ReleaseTarException('tar_x', str_repeat('d', 100000)))->getMessage())
        );
    }

    // ------------------------------------------------------------------------------------- helpers

    private function assertGzipRejected(string $reason, string $gz, string $sha, int $length, string $label): void
    {
        try {
            ReleaseGzipVerifier::assertMatches($this->write($gz), $sha, $length);
        } catch (ReleaseTarException $e) {
            $this->assertSame($reason, $e->reason, "$label: " . $e->getMessage());

            return;
        }
        $this->fail("$label: gzip must be rejected with $reason");
    }

    /** Deterministic bytes that stay large when compressed, so verification crosses several input chunks. */
    private static function incompressible(): string
    {
        $data = '';
        for ($i = 0; $i < 2000; $i++) {
            $data .= hash('sha256', "synthetic $i", true);
        }

        return $data;
    }

    private function assertTarRejected(string $reason, string $tar, string $label = '', array $options = []): void
    {
        try {
            ReleaseTarReader::read($this->write($tar), $options);
        } catch (ReleaseTarException $e) {
            $this->assertSame($reason, $e->reason, "$label: " . $e->getMessage());

            return;
        }
        $this->fail("$label: archive must be rejected with $reason");
    }

    private function write(string $bytes): string
    {
        if ($this->dir === null) {
            $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avilona-s1a-tar-' . bin2hex(random_bytes(8));
            $this->assertTrue(mkdir($this->dir, 0700));
        }
        $file = $this->dir . DIRECTORY_SEPARATOR . count($this->created) . '.tar';
        $this->created[] = $file;
        $this->assertSame(strlen($bytes), file_put_contents($file, $bytes));

        return $file;
    }

    /** One 512-byte header in git's layout; overrides replace whole fields. */
    private static function header(string $name, int $size, string $type, array $o = []): string
    {
        $h = self::field($name, 100)
            . ($o['mode_field'] ?? sprintf('%07o', $type === '5' ? 0775 : 0664) . "\0")
            . sprintf('%07o', 0) . "\0"
            . sprintf('%07o', 0) . "\0"
            . ($o['size_field'] ?? sprintf('%011o', $size) . "\0")
            . sprintf('%011o', 1764000000) . "\0"
            . '        '
            . $type
            . self::field($o['linkname'] ?? '', 100)
            . ($o['magic'] ?? "ustar\0")
            . ($o['version'] ?? '00')
            . self::field('root', 32)
            . self::field('root', 32)
            . sprintf('%07o', 0) . "\0"
            . sprintf('%07o', 0) . "\0"
            . self::field($o['prefix'] ?? '', 155)
            . ($o['pad'] ?? str_repeat("\0", 12));
        if (strlen($h) !== 512) {
            throw new \LogicException('fixture header must be 512 bytes');
        }
        $checksum = $o['chksum_field'] ?? sprintf('%07o', array_sum(unpack('C*', $h))) . "\0";

        return substr_replace($h, $checksum, 148, 8);
    }

    private static function field(string $value, int $width): string
    {
        if (strlen($value) > $width) {
            throw new \LogicException('fixture field too long');
        }

        return str_pad($value, $width, "\0");
    }

    private static function file(string $path, string $data): string
    {
        return self::header($path, strlen($data), '0') . $data . self::padding(strlen($data));
    }

    private static function dir(string $path): string
    {
        return self::header($path, 0, '5');
    }

    private static function padding(int $size): string
    {
        return str_repeat("\0", (512 - $size % 512) % 512);
    }

    private static function record(string $key, string $value): string
    {
        return self::lengthPrefixed(' ' . $key . '=' . $value . "\n");
    }

    /** "LEN" + body where LEN counts itself (the PAX convention). */
    private static function lengthPrefixed(string $body): string
    {
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

    private static function globalHeader(string $commit): string
    {
        return self::paxEntry('g', 'pax_global_header', self::record('comment', $commit));
    }

    private static function extended(string $oid, string $path): string
    {
        return self::paxEntry('x', $oid . '.paxheader', self::record('path', $path));
    }

    /** Two zero blocks plus git's padding to a 10240-byte record. */
    private static function finish(string $body): string
    {
        $total = strlen($body) + 1024;

        return $body . str_repeat("\0", 1024 + (10240 - $total % 10240) % 10240);
    }
}
