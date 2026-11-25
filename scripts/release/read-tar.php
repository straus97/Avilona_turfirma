<?php
/**
 * NOV-PUBLIC-LEGAL-01 S1-A: strict, side-effect-free reader for the TAR stream written by
 * `git archive --format=tar` (used by scripts/release/build-public-package.php).
 *
 * Including this file only declares ReleaseTarException, ReleaseTarReader and ReleaseGzipVerifier; nothing
 * runs, nothing is extracted and nothing is written. No Composer autoloader or Laravel bootstrap is needed.
 *
 * Supported format (exactly what git writes; everything else is rejected):
 * - POSIX ustar headers (magic "ustar\0", version "00"), verified unsigned header checksum, octal numeric
 *   fields terminated by NUL/space (no base-256), NUL-terminated string fields with NUL-only remainder,
 *   zero linkname and zero header padding;
 * - entry types: regular file '0' and directory '5' only (symlink, hardlink, device, FIFO, GNU and any other
 *   type is rejected); directories have size 0 and a trailing slash, files have none;
 * - PAX subset: one optional global header ('g', named pax_global_header) as the very first header, holding
 *   exactly one `comment` record with the commit id; per-entry extended headers ('x', named
 *   <oid>.paxheader) holding exactly one `path` record, immediately followed by their entry named <oid>.data.
 *   Records are length-checked "LEN key=value\n"; `size`, `linkpath`, `mtime` and every other key are
 *   rejected, as are duplicate, dangling, consecutive or misplaced extended headers;
 * - completion: entry data and padding are read exactly (padding must be zero); the archive ends with at least
 *   two zero blocks; any further bytes must be whole zero blocks (git pads to its 10240-byte record), at most
 *   MAX_TRAILER_BLOCKS zero blocks in total; premature EOF is an error, never success.
 *
 * Path contract (pathViolation(), shared with the release builder and the legal package checker): the
 * effective (PAX-resolved) path of every entry, directories included, must be valid UTF-8 of at most
 * MAX_PATH_BYTES bytes with no control characters (C0, DEL, C1), no backslash, no leading slash, no empty,
 * "." or ".." segment, and no segment Windows would read differently: a trailing dot or space, any of
 * ':' '<' '>' '"' '|' '?' '*' (drive letters and alternate data streams included) or a reserved device name
 * (CON, PRN, AUX, NUL, CONIN$, CONOUT$, COM0-9, LPT0-9 and the superscript forms, with any extension).
 * Such paths are rejected, never rewritten.
 *
 * Collisions: every directory level has one spelling. Exact duplicates, case-only collisions of an entry or
 * of any parent directory (explicit or implied, PAX paths included) and file/directory collisions are
 * rejected. "Case-only" means Unicode simple case folding as implemented by mbstring (required — the reader
 * refuses to run without it). This approximates, but is not, the NTFS upcase table; NFC/NFD normalization
 * equivalence, Windows 8.3 short names and other filesystems' equivalences are NOT detected. No claim of
 * universal filesystem equivalence is made; the targets are the current Windows and Linux hosts.
 *
 * With the `prefix` option every entry must lie below that prefix, whose own directory entry, if present,
 * must be the first non-PAX entry.
 *
 * Memory: entry data is streamed in CHUNK_BYTES chunks; every regular file is SHA-256-hashed over its exact
 * bytes. Content is passed to the visitor only for entries the caller asks for and only up to `content_limit`
 * (at most MAX_CONTENT_BYTES, the release content-scan threshold), and is not retained by the reader.
 * Returned metadata and the path index are bounded in aggregate (MAX_ENTRIES, MAX_PATH_DEPTH,
 * MAX_TOTAL_PATH_BYTES, MAX_INDEX_NODES, MAX_INDEX_BYTES), checked before anything is added. The `limits`
 * option can only lower these (used by tests).
 */
declare(strict_types=1);

final class ReleaseTarException extends RuntimeException
{
    public const MAX_DETAIL_BYTES = 512;

    public function __construct(public readonly string $reason, string $detail)
    {
        parent::__construct("tar rejected: $reason (" . substr($detail, 0, self::MAX_DETAIL_BYTES) . ')');
    }
}

final class ReleaseTarReader
{
    public const BLOCK_BYTES = 512;
    public const CHUNK_BYTES = 1048576;
    /** Entries (files and directories) per archive; the public package holds about 800 files. */
    public const MAX_ENTRIES = 20000;
    /** Effective path length; git paths in this repository are far shorter. */
    public const MAX_PATH_BYTES = 4096;
    /** Segments per path, prefix included; the package is about a third as deep at most. */
    public const MAX_PATH_DEPTH = 32;
    /** Sum of all entry path lengths (retained in the returned metadata). */
    public const MAX_TOTAL_PATH_BYTES = 4194304;
    /** Indexed names: entries plus implied parent directories. */
    public const MAX_INDEX_NODES = 40000;
    /** Sum of folded and exact spellings held by the collision index. */
    public const MAX_INDEX_BYTES = 8388608;
    /** One PAX payload: a 4096-byte path record plus framing fits easily. */
    public const MAX_PAX_BYTES = 8192;
    /** One entry's data; far above any asset in the package, below the 11-digit ustar size limit. */
    public const MAX_ENTRY_BYTES = 1073741824;
    /** Content handed to the visitor; equals the release builder's content-scan threshold. */
    public const MAX_CONTENT_BYTES = 2000000;
    /** Zero blocks after the last entry (2 required; git adds up to one 10240-byte record). */
    public const MAX_TRAILER_BLOCKS = 64;
    /** Length of a shown path in diagnostics. */
    public const MAX_DIAGNOSTIC_PATH = 160;

    private const OPTIONS = ['prefix', 'want_content', 'content_limit', 'visit', 'limits'];
    private const LIMITS = [
        'entries' => self::MAX_ENTRIES,
        'depth' => self::MAX_PATH_DEPTH,
        'path_bytes' => self::MAX_TOTAL_PATH_BYTES,
        'index_nodes' => self::MAX_INDEX_NODES,
        'index_bytes' => self::MAX_INDEX_BYTES,
    ];
    private const RESERVED_NAME = '/^(?:CON|PRN|AUX|NUL|CONIN\$|CONOUT\$|(?:COM|LPT)[0-9\x{00B9}\x{00B2}\x{00B3}])$/iuD';

    /** @var resource */
    private $handle;
    private HashContext $archiveHash;
    private int $offset = 0;
    private ?string $prefix;
    /** @var callable|null */
    private $wantContent;
    private int $contentLimit;
    /** @var callable|null */
    private $visit;

    private ?string $globalComment = null;
    /** @var list<array<string, mixed>> */
    private array $entries = [];
    /** @var array<string, string> folded name => kind ("f" file, "d" directory, "i" implied) . exact spelling */
    private array $index = [];
    private int $indexNodes = 0;
    private int $indexBytes = 0;
    private int $pathBytes = 0;
    /** @var array<string, int> */
    private array $limits;

    /**
     * Why a "/"-separated relative path is unacceptable on the Windows/Linux targets, or null if it is
     * acceptable. The path is judged as written; nothing is normalized into an accepted alternative.
     */
    public static function pathViolation(string $path): ?string
    {
        if ($path === '' || strlen($path) > self::MAX_PATH_BYTES) {
            return 'empty or overlong path';
        }
        if (preg_match('//u', $path) !== 1) {
            return 'path is not valid UTF-8';
        }
        if (self::matches('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $path)) {
            return 'control character in path';
        }
        if (str_contains($path, '\\') || str_starts_with($path, '/')) {
            return 'backslash or absolute path';
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return 'empty, "." or ".." segment';
            }
            if (str_ends_with($segment, '.') || str_ends_with($segment, ' ')) {
                return 'segment with a trailing dot or space';
            }
            if (strpbrk($segment, ':<>"|?*') !== false) {
                return 'character Windows does not accept in a name (drive or stream syntax included)';
            }
            if (self::matches(self::RESERVED_NAME, rtrim(explode('.', $segment, 2)[0], ' '))) {
                return 'reserved Windows device name';
            }
        }

        return null;
    }

    /** Case-folded form used for collision detection. */
    public static function fold(string $path): string
    {
        return mb_convert_case($path, MB_CASE_FOLD_SIMPLE, 'UTF-8');
    }

    /**
     * Detection key only: what a Windows or case-insensitive host could open as the same name (backslash as a
     * separator, ":stream" suffixes and trailing dots/spaces dropped, case folded). Used to reject, never to
     * accept or rewrite a path.
     */
    public static function aliasKey(string $path): string
    {
        $segments = array_map(
            static fn (string $s): string => rtrim(explode(':', $s, 2)[0], ' .'),
            explode('/', str_replace('\\', '/', $path))
        );

        return self::fold(implode('/', $segments));
    }

    /**
     * Read and validate a complete archive.
     *
     * Options:
     * - prefix (?string): required archive root ending with "/", e.g. "avilona-public-0123456789ab/";
     * - want_content (?callable(string $relativePath, int $size): bool): ask for a regular file's content;
     * - content_limit (int, default MAX_CONTENT_BYTES): larger files are hashed but never delivered;
     * - visit (?callable(array $entry, ?string $content): void): called after each entry's data and padding
     *   were read; the archive is not yet known to be complete, so callers must act only after read() returns;
     * - limits (array): lower values for entries, depth, path_bytes, index_nodes, index_bytes.
     *
     * @return array{global_comment: ?string, entries: list<array{path: string, relative: string, type: string,
     *     size: int, sha256: ?string, mode: int, mtime: int}>, archive_bytes: int, archive_sha256: string}
     */
    public static function read(string $file, array $options = []): array
    {
        $reader = new self($options);
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            throw new ReleaseTarException('tar_io', 'cannot open the archive');
        }
        $reader->handle = $handle;
        try {
            $result = $reader->readArchive();
        } catch (Throwable $e) {
            fclose($handle);
            throw $e;
        }
        if (! fclose($handle)) {
            throw new ReleaseTarException('tar_io', 'cannot close the archive');
        }

        return $result;
    }

    /** Printable, bounded rendering of a path for diagnostics. */
    public static function describe(string $path): string
    {
        $path = substr($path, 0, self::MAX_DIAGNOSTIC_PATH + 1);
        $shown = preg_replace_callback('/[^\x21-\x7E]/', static fn (array $m): string => sprintf('\\x%02X', ord($m[0])), $path);
        if ($shown === null) {
            return '(unprintable)';
        }

        return strlen($shown) > self::MAX_DIAGNOSTIC_PATH ? substr($shown, 0, self::MAX_DIAGNOSTIC_PATH) . '...' : $shown;
    }

    /** preg_match that never turns an engine error into "no match". */
    private static function matches(string $pattern, string $subject): bool
    {
        $result = preg_match($pattern, $subject);
        if ($result === false) {
            throw new ReleaseTarException('tar_regex', preg_last_error_msg());
        }

        return $result === 1;
    }

    private function __construct(array $options)
    {
        if (PHP_INT_SIZE < 8) {
            throw new ReleaseTarException('tar_environment', '64-bit PHP is required');
        }
        if (! function_exists('mb_convert_case')) {
            throw new ReleaseTarException('tar_environment', 'mbstring is required for case-collision protection');
        }
        $unknown = array_diff(array_keys($options), self::OPTIONS);
        if ($unknown !== []) {
            throw new ReleaseTarException('tar_options', 'unknown option');
        }
        $prefix = $options['prefix'] ?? null;
        if ($prefix !== null) {
            if (! is_string($prefix) || ! str_ends_with($prefix, '/')) {
                throw new ReleaseTarException('tar_options', 'prefix must end with "/"');
            }
            self::assertSafePath(substr($prefix, 0, -1));
        }
        $limit = $options['content_limit'] ?? self::MAX_CONTENT_BYTES;
        if (! is_int($limit) || $limit < 0 || $limit > self::MAX_CONTENT_BYTES) {
            throw new ReleaseTarException('tar_options', 'content_limit must be 0..' . self::MAX_CONTENT_BYTES);
        }
        foreach (['want_content', 'visit'] as $name) {
            if (isset($options[$name]) && ! is_callable($options[$name])) {
                throw new ReleaseTarException('tar_options', "$name must be callable");
            }
        }
        $limits = $options['limits'] ?? [];
        if (! is_array($limits)) {
            throw new ReleaseTarException('tar_options', 'limits must be an array');
        }
        foreach ($limits as $name => $value) {
            if (! isset(self::LIMITS[$name]) || ! is_int($value) || $value < 1 || $value > self::LIMITS[$name]) {
                throw new ReleaseTarException('tar_options', 'limits may only lower the built-in limits');
            }
        }
        $this->limits = $limits + self::LIMITS;
        $this->prefix = $prefix;
        $this->contentLimit = $limit;
        $this->wantContent = $options['want_content'] ?? null;
        $this->visit = $options['visit'] ?? null;
        $this->archiveHash = hash_init('sha256');
    }

    private function readArchive(): array
    {
        $pending = null;
        while (true) {
            $header = $this->readHeaderBlock();
            if ($header === str_repeat("\0", self::BLOCK_BYTES)) {
                if ($pending !== null) {
                    throw new ReleaseTarException('tar_pax_placement', 'extended header without a following entry');
                }
                $this->readTrailer();
                break;
            }
            $h = $this->parseHeader($header);
            switch ($h['type']) {
                case 'g':
                    $this->readGlobalHeader($h, $pending);
                    break;
                case 'x':
                    $pending = $this->readExtendedHeader($h, $pending);
                    break;
                case '0':
                case '5':
                    $this->readEntry($h, $pending);
                    $pending = null;
                    break;
                default:
                    throw new ReleaseTarException('tar_type', self::typeName($h['type']) . ': ' . self::describe($h['raw_path']));
            }
        }

        return [
            'global_comment' => $this->globalComment,
            'entries' => $this->entries,
            'archive_bytes' => $this->offset,
            'archive_sha256' => hash_final($this->archiveHash),
        ];
    }

    // ----------------------------------------------------------------------------------------- headers

    private function parseHeader(string $b): array
    {
        if (preg_match('/^([0-7]{1,7})[ \0]+$/D', substr($b, 148, 8), $m) !== 1) {
            throw new ReleaseTarException('tar_header_checksum', 'malformed checksum field at offset ' . ($this->offset - self::BLOCK_BYTES));
        }
        $sum = 8 * 32;
        foreach ([substr($b, 0, 148), substr($b, 156)] as $part) {
            $sum += array_sum(unpack('C*', $part));
        }
        if (intval($m[1], 8) !== $sum) {
            throw new ReleaseTarException('tar_header_checksum', 'checksum mismatch at offset ' . ($this->offset - self::BLOCK_BYTES));
        }
        if (substr($b, 257, 6) !== "ustar\0" || substr($b, 263, 2) !== '00') {
            throw new ReleaseTarException('tar_header_format', 'not a POSIX ustar header');
        }
        if (substr($b, 500, 12) !== str_repeat("\0", 12)) {
            throw new ReleaseTarException('tar_header_format', 'nonzero header padding');
        }

        $name = self::cString(substr($b, 0, 100), 'name');
        $prefix = self::cString(substr($b, 345, 155), 'prefix');
        $type = $b[156];
        $rawPath = $prefix !== '' ? $prefix . '/' . $name : $name;
        if (! in_array($type, ['0', '5', 'x', 'g'], true)) {
            throw new ReleaseTarException('tar_type', self::typeName($type) . ': ' . self::describe($rawPath));
        }
        if (substr($b, 157, 100) !== str_repeat("\0", 100)) {
            throw new ReleaseTarException('tar_header_format', 'unexpected link name: ' . self::describe($rawPath));
        }
        self::cString(substr($b, 265, 32), 'uname');
        self::cString(substr($b, 297, 32), 'gname');
        foreach ([[329, 'devmajor'], [337, 'devminor']] as [$at, $what]) {
            $field = substr($b, $at, 8);
            if ($field !== str_repeat("\0", 8)) {
                self::octal($field, $what, 07777777);
            }
        }

        return [
            'name' => $name,
            'prefix' => $prefix,
            'raw_path' => $rawPath,
            'type' => $type,
            'mode' => self::octal(substr($b, 100, 8), 'mode', 07777),
            'uid' => self::octal(substr($b, 108, 8), 'uid', 07777777),
            'gid' => self::octal(substr($b, 116, 8), 'gid', 07777777),
            'size' => self::octal(substr($b, 124, 12), 'size', 077777777777),
            'mtime' => self::octal(substr($b, 136, 12), 'mtime', 077777777777),
        ];
    }

    private function readGlobalHeader(array $h, ?array $pending): void
    {
        if ($this->offset !== self::BLOCK_BYTES || $pending !== null) {
            throw new ReleaseTarException('tar_pax_placement', 'global header is allowed only as the first header, once');
        }
        if ($h['name'] !== 'pax_global_header' || $h['prefix'] !== '') {
            throw new ReleaseTarException('tar_header_format', 'unexpected global header name');
        }
        $records = $this->readPaxPayload($h['size']);
        if (count($records) !== 1 || $records[0][0] !== 'comment') {
            throw new ReleaseTarException('tar_pax_key', 'the global header may carry only one comment record');
        }
        if (preg_match('/^[0-9a-f]{40}(?:[0-9a-f]{24})?$/D', $records[0][1]) !== 1) {
            throw new ReleaseTarException('tar_pax', 'global comment is not a commit id');
        }
        $this->globalComment = $records[0][1];
    }

    /** @return array{oid: string, path: string} */
    private function readExtendedHeader(array $h, ?array $pending): array
    {
        if ($pending !== null) {
            throw new ReleaseTarException('tar_pax_placement', 'consecutive extended headers');
        }
        if ($h['prefix'] !== '' || preg_match('/^([0-9a-f]{40}(?:[0-9a-f]{24})?)\.paxheader$/D', $h['name'], $m) !== 1) {
            throw new ReleaseTarException('tar_header_format', 'unexpected extended header name');
        }
        $records = $this->readPaxPayload($h['size']);
        $path = null;
        foreach ($records as [$key, $value]) {
            if ($key !== 'path') {
                throw new ReleaseTarException('tar_pax_key', 'unsupported extended header key ' . self::describe($key));
            }
            if ($path !== null) {
                throw new ReleaseTarException('tar_pax_key', 'duplicate path record');
            }
            $path = $value;
        }

        return ['oid' => $m[1], 'path' => (string) $path];
    }

    /** @return list<array{0: string, 1: string}> */
    private function readPaxPayload(int $size): array
    {
        if ($size < 1 || $size > self::MAX_PAX_BYTES) {
            throw new ReleaseTarException('tar_pax', "payload size $size outside 1.." . self::MAX_PAX_BYTES);
        }
        $payload = $this->readExact($size, 'extended header payload');
        $this->readPadding($size);

        $records = [];
        $pos = 0;
        while ($pos < $size) {
            if (preg_match('/\G([1-9][0-9]{0,4}) /', $payload, $m, 0, $pos) !== 1) {
                throw new ReleaseTarException('tar_pax', 'malformed record length');
            }
            $length = (int) $m[1];
            $head = strlen($m[1]) + 1;
            if ($length < $head + 3 || $length > $size - $pos) {
                throw new ReleaseTarException('tar_pax', 'record length outside the payload');
            }
            $record = substr($payload, $pos, $length);
            if ($record[$length - 1] !== "\n") {
                throw new ReleaseTarException('tar_pax', 'record is not terminated by LF');
            }
            $body = substr($record, $head, $length - $head - 1);
            $eq = strpos($body, '=');
            if ($eq === false || $eq === 0) {
                throw new ReleaseTarException('tar_pax', 'record without key');
            }
            $key = substr($body, 0, $eq);
            $value = substr($body, $eq + 1);
            if (preg_match('/^[a-z]+$/D', $key) !== 1) {
                throw new ReleaseTarException('tar_pax_key', 'malformed key');
            }
            if ($value === '' || preg_match('//u', $value) !== 1 || strpbrk($value, "\0\n") !== false) {
                throw new ReleaseTarException('tar_pax', 'invalid value for key ' . self::describe($key));
            }
            $records[] = [$key, $value];
            if (count($records) > 8) {
                throw new ReleaseTarException('tar_pax', 'too many records');
            }
            $pos += $length;
        }

        return $records;
    }

    // ----------------------------------------------------------------------------------------- entries

    private function readEntry(array $h, ?array $pending): void
    {
        if ($pending !== null) {
            if ($h['prefix'] !== '' || $h['name'] !== $pending['oid'] . '.data') {
                throw new ReleaseTarException('tar_pax_placement', 'extended header is not followed by its own entry');
            }
            $path = $pending['path'];
        } else {
            $path = $h['raw_path'];
        }

        $isDir = $h['type'] === '5';
        if ($isDir) {
            if ($h['size'] !== 0) {
                throw new ReleaseTarException('tar_size', 'directory with data: ' . self::describe($path));
            }
            if (! str_ends_with($path, '/')) {
                throw new ReleaseTarException('tar_path', 'directory without trailing slash: ' . self::describe($path));
            }
            $path = substr($path, 0, -1);
        } elseif (str_ends_with($path, '/')) {
            throw new ReleaseTarException('tar_path', 'regular file with trailing slash: ' . self::describe($path));
        }
        if ($h['size'] > self::MAX_ENTRY_BYTES) {
            throw new ReleaseTarException('tar_size', 'entry larger than ' . self::MAX_ENTRY_BYTES . ' bytes: ' . self::describe($path));
        }
        self::assertSafePath($path);
        $relative = $this->relative($path, $isDir);
        if (count($this->entries) >= $this->limits['entries']) {
            throw new ReleaseTarException('tar_limit', 'more than ' . $this->limits['entries'] . ' entries');
        }
        if (substr_count($path, '/') >= $this->limits['depth']) {
            throw new ReleaseTarException('tar_limit', 'path deeper than ' . $this->limits['depth'] . ' segments');
        }
        $this->pathBytes += strlen($path);
        if ($this->pathBytes > $this->limits['path_bytes']) {
            throw new ReleaseTarException('tar_limit', 'total path bytes above ' . $this->limits['path_bytes']);
        }
        $this->index($path, $isDir);

        $sha = null;
        $content = null;
        if (! $isDir) {
            $want = $this->wantContent !== null && $h['size'] <= $this->contentLimit
                && ($this->wantContent)($relative, $h['size']) === true;
            [$sha, $content] = $this->streamData($h['size'], $want);
        }

        $entry = [
            'path' => $path,
            'relative' => $relative,
            'type' => $isDir ? 'dir' : 'file',
            'size' => $h['size'],
            'sha256' => $sha,
            'mode' => $h['mode'],
            'mtime' => $h['mtime'],
        ];
        $this->entries[] = $entry;
        if ($this->visit !== null) {
            ($this->visit)($entry, $content);
        }
    }

    /** @return array{0: string, 1: ?string} */
    private function streamData(int $size, bool $retain): array
    {
        $hash = hash_init('sha256');
        $content = $retain ? '' : null;
        $remaining = $size;
        while ($remaining > 0) {
            $chunk = $this->readExact(min($remaining, self::CHUNK_BYTES), 'entry data');
            hash_update($hash, $chunk);
            if ($retain) {
                $content .= $chunk;
            }
            $remaining -= strlen($chunk);
        }
        $this->readPadding($size);

        return [hash_final($hash), $content];
    }

    private function relative(string $path, bool $isDir): string
    {
        if ($this->prefix === null) {
            return $path;
        }
        if ($path === substr($this->prefix, 0, -1)) {
            if (! $isDir || $this->entries !== []) {
                throw new ReleaseTarException('tar_prefix', 'the archive root must be the first entry and a directory');
            }

            return '';
        }
        if (! str_starts_with($path, $this->prefix)) {
            throw new ReleaseTarException('tar_prefix', 'entry outside the archive root: ' . self::describe($path));
        }

        return substr($path, strlen($this->prefix));
    }

    /** Records the entry and each of its parent directories under one exact spelling per folded name. */
    private function index(string $path, bool $isDir): void
    {
        $exact = explode('/', $path);
        $folded = explode('/', self::fold($path)); // "/" is invariant, so both have the same segments
        $last = count($exact) - 1;
        $name = '';
        $key = '';
        foreach ($exact as $i => $segment) {
            $name = $i === 0 ? $segment : $name . '/' . $segment;
            $key = $i === 0 ? $folded[0] : $key . '/' . $folded[$i];
            $kind = $i < $last ? 'i' : ($isDir ? 'd' : 'f');
            $known = $this->index[$key] ?? null;
            if ($known === null) {
                $this->indexNodes++;
                $this->indexBytes += strlen($key) + strlen($name);
                if ($this->indexNodes > $this->limits['index_nodes'] || $this->indexBytes > $this->limits['index_bytes']) {
                    throw new ReleaseTarException('tar_limit', 'path index limit exceeded at ' . self::describe($path));
                }
                $this->index[$key] = $kind . $name;
                continue;
            }
            if (($known[0] === 'f') !== ($kind === 'f')) {
                throw new ReleaseTarException('tar_collision', 'file/directory collision: ' . self::describe($path));
            }
            if (substr($known, 1) !== $name) {
                throw new ReleaseTarException('tar_case_collision', 'case-only collision: ' . self::describe($path));
            }
            if ($i === $last) {
                if ($known[0] !== 'i') {
                    throw new ReleaseTarException('tar_duplicate', 'duplicate path: ' . self::describe($path));
                }
                $this->index[$key] = 'd' . $name; // an implied parent now has its explicit entry
            }
        }
    }

    private static function assertSafePath(string $path): void
    {
        $violation = self::pathViolation($path);
        if ($violation !== null) {
            throw new ReleaseTarException('tar_path', $violation . ': ' . self::describe($path));
        }
    }

    // ----------------------------------------------------------------------------------------- blocks

    private function readHeaderBlock(): string
    {
        $block = $this->readUpTo(self::BLOCK_BYTES);
        if ($block === '') {
            throw new ReleaseTarException('tar_termination', 'archive ends without terminating zero blocks');
        }
        if (strlen($block) !== self::BLOCK_BYTES) {
            throw new ReleaseTarException('tar_truncated', 'short header at offset ' . ($this->offset - strlen($block)));
        }

        return $block;
    }

    private function readTrailer(): void
    {
        $zero = str_repeat("\0", self::BLOCK_BYTES);
        $blocks = 1;
        while (true) {
            $block = $this->readUpTo(self::BLOCK_BYTES);
            if ($block === '') {
                break;
            }
            if (strlen($block) !== self::BLOCK_BYTES) {
                throw new ReleaseTarException('tar_termination', 'partial block after the end of the archive');
            }
            if ($block !== $zero) {
                throw new ReleaseTarException('tar_termination', 'nonzero data after the end of the archive');
            }
            if (++$blocks > self::MAX_TRAILER_BLOCKS) {
                throw new ReleaseTarException('tar_termination', 'too many trailing blocks');
            }
        }
        if ($blocks < 2) {
            throw new ReleaseTarException('tar_termination', 'only one terminating zero block');
        }
    }

    private function readPadding(int $size): void
    {
        $padding = (self::BLOCK_BYTES - $size % self::BLOCK_BYTES) % self::BLOCK_BYTES;
        if ($padding > 0 && $this->readExact($padding, 'padding') !== str_repeat("\0", $padding)) {
            throw new ReleaseTarException('tar_padding', 'nonzero padding');
        }
    }

    private function readExact(int $length, string $what): string
    {
        $data = $this->readUpTo($length);
        if (strlen($data) !== $length) {
            throw new ReleaseTarException('tar_truncated', "short $what at offset " . ($this->offset - strlen($data)));
        }

        return $data;
    }

    /** Reads up to $length bytes, fewer only at end of file. */
    private function readUpTo(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->handle, $length - strlen($data));
            if ($chunk === false) {
                throw new ReleaseTarException('tar_io', 'read error');
            }
            if ($chunk === '') {
                if (feof($this->handle)) {
                    break;
                }
                throw new ReleaseTarException('tar_io', 'read stalled');
            }
            $data .= $chunk;
        }
        hash_update($this->archiveHash, $data);
        $this->offset += strlen($data);

        return $data;
    }

    // ----------------------------------------------------------------------------------------- fields

    private static function octal(string $field, string $what, int $max): int
    {
        if ((ord($field[0]) & 0x80) !== 0) {
            throw new ReleaseTarException('tar_numeric', "unsupported base-256 $what");
        }
        if (preg_match('/^([0-7]+)[ \0]+$/D', $field, $m) !== 1) {
            throw new ReleaseTarException('tar_numeric', "invalid octal $what");
        }
        $value = intval($m[1], 8);
        if ($value > $max) {
            throw new ReleaseTarException('tar_numeric', "$what out of range");
        }

        return $value;
    }

    private static function cString(string $field, string $what): string
    {
        $end = strpos($field, "\0");
        if ($end === false) {
            return $field;
        }
        if (strspn($field, "\0", $end) !== strlen($field) - $end) {
            throw new ReleaseTarException('tar_header_format', "data after the terminator of $what");
        }

        return substr($field, 0, $end);
    }

    private static function typeName(string $type): string
    {
        return match ($type) {
            '1' => 'hard link',
            '2' => 'symbolic link',
            '3' => 'character device',
            '4' => 'block device',
            '6' => 'FIFO',
            "\0" => 'pre-POSIX regular file',
            default => 'unsupported entry type 0x' . bin2hex($type),
        };
    }
}

/**
 * Proves that a gzip file is exactly one complete member, written without file name or time stamp, whose
 * content is exactly the expected bytes, with nothing after it. zlib itself checks the CRC32 and length
 * trailer. Compressed input is fed in INPUT_CHUNK_BYTES pieces, so one step expands to a few MiB at most.
 */
final class ReleaseGzipVerifier
{
    public const INPUT_CHUNK_BYTES = 4096;

    public static function assertMatches(string $file, string $sha256, int $bytes): void
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            throw new ReleaseTarException('gzip_io', 'cannot open the compressed file');
        }
        try {
            self::verify($handle, $sha256, $bytes);
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private static function verify($handle, string $sha256, int $bytes): void
    {
        $stat = fstat($handle);
        $head = fread($handle, 10);
        if ($stat === false || $head === false || fseek($handle, 0) !== 0) {
            throw new ReleaseTarException('gzip_io', 'cannot read the compressed file');
        }
        if (strlen($head) !== 10 || substr($head, 0, 8) !== "\x1f\x8b\x08\0\0\0\0\0") {
            throw new ReleaseTarException('gzip_header', 'not a gzip member without flags and time stamp');
        }
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        if ($context === false) {
            throw new ReleaseTarException('gzip_io', 'cannot start decompression');
        }

        $hash = hash_init('sha256');
        $total = 0;
        while (inflate_get_status($context) !== ZLIB_STREAM_END) {
            $chunk = fread($handle, self::INPUT_CHUNK_BYTES);
            if ($chunk === false) {
                throw new ReleaseTarException('gzip_io', 'read error');
            }
            if ($chunk === '') {
                throw new ReleaseTarException('gzip_truncated', 'the stream ends before its trailer');
            }
            $plain = @inflate_add($context, $chunk, ZLIB_SYNC_FLUSH);
            if ($plain === false) {
                throw new ReleaseTarException('gzip_corrupt', 'invalid compressed data or trailer');
            }
            $total += strlen($plain);
            if ($total > $bytes) {
                throw new ReleaseTarException('gzip_content', 'decompressed data is longer than expected');
            }
            hash_update($hash, $plain);
        }
        if (inflate_get_read_len($context) !== $stat['size']) {
            throw new ReleaseTarException('gzip_trailing', 'data follows the first gzip member');
        }
        if ($total !== $bytes || ! hash_equals($sha256, hash_final($hash))) {
            throw new ReleaseTarException('gzip_content', 'decompressed data differs from the expected bytes');
        }
    }
}
