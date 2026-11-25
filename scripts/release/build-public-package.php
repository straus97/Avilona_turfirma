<?php
/**
 * NOV-PUBLIC-03B-L3: reproducible, fail-closed public-only release package.
 * NOV-PUBLIC-LEGAL-01 S1-A: single-revision manifest, legal_archive=NOT_ACTIVE guard, strict TAR reading,
 * checked and non-overwriting output.
 *
 *   php scripts/release/build-public-package.php --out=<dir outside repo> [--rev=HEAD]
 *
 * Source is ONLY `git archive <commit>` limited to the manifest allowlist, so the working tree
 * (.env, logs, QA output, vendor, untracked files) can never leak in. The commit is resolved first; the
 * manifest is then read from that same commit (never from the working tree). The produced archive is parsed
 * entry by entry by the strict reader (scripts/release/read-tar.php) and rejected unless every entry matches
 * the allowlist and no denylist, dotfile, symlink, traversal, duplicate or content rule. Output is
 * byte-reproducible for a given commit (git archive uses the commit time; gzip header mtime is 0).
 * Local only: no network, no server.
 *
 * Legal archive (S1-A): no application legal registry exists. The selected commit's tree must contain
 * nothing at or below resources/legal-archive (in any letter case or Windows-equivalent spelling, whether or
 * not the manifest or export-ignore would package it); the build reports legal_archive=NOT_ACTIVE. A
 * successful package proves packaging integrity only — not legal approval, legal versioning or deployment
 * readiness. S1-B replaces this branch with mandatory verification (scripts/release/legal-package-check.php).
 *
 * Outputs: <base>.files.txt, <base>.tar.gz.sha256 and, last, <base>.tar.gz. Existing outputs are never
 * overwritten or deleted; every file is first written as an exclusively created *.partial, verified, then
 * published by hard link only (no rename fallback: the output filesystem must support hard links).
 * Exit 0 = package written and validated. Non-zero = this invocation removed what it created (cleanup
 * failures are reported).
 *
 * Limitations: no guarantee under external termination or filesystem failure, and no protection against
 * another process replacing or modifying these files while the build runs.
 *
 * git is started without a shell (git.exe on PATH); its output passes through temporary files in the system
 * temp directory, removed by this invocation.
 */
declare(strict_types=1);

require __DIR__ . '/read-tar.php';

const LEGAL_ARCHIVE_PATH = 'resources/legal-archive';
const MANIFEST_PATH = 'scripts/release/public-package-manifest.json';
const SCAN_LIMIT = 2_000_000;
const COPY_CHUNK = 1 << 20;
const MAX_REPORTED_PROBLEMS = 200;
const BINARY_EXTENSIONS = '~\.(png|jpe?g|gif|webp|ico|pdf|svg)$~i';
const MAX_PROBLEM_BYTES = 300;
/** Manifest blob (about 3 KiB today). */
const MAX_MANIFEST_BYTES = 65536;
const MAX_GIT_ERROR_BYTES = 4096;
/** Tree listing of the selected commit; the repository holds a few thousand entries today. */
const MAX_TREE_BYTES = 16777216;
const MAX_TREE_RECORDS = 100000;
const MAX_TREE_RECORD_BYTES = 4608;

/** @var array<string, true> files created by this invocation */
$GLOBALS['release_owned_files'] = [];
/** @var list<string> directories created by this invocation, outermost first */
$GLOBALS['release_owned_dirs'] = [];
/** @var array<int, resource> streams opened by this invocation and not yet closed */
$GLOBALS['release_open_handles'] = [];
$GLOBALS['release_completed'] = false;

final class ReleaseBuildFailure extends RuntimeException
{
}

/**
 * Abort the build. Thrown rather than exiting, so that open streams (the TAR reader's included) are closed
 * on the way out and Windows can then remove this invocation's files.
 */
function fail(string $m): never
{
    throw new ReleaseBuildFailure($m);
}

function abort_build(string $m): never
{
    fwrite(STDERR, "PACKAGE FAILED: $m\n");
    cleanup_and_report();
    $GLOBALS['release_completed'] = true; // cleanup has run and was reported
    exit(1);
}

function cleanup_and_report(): void
{
    foreach (cleanup_owned() as $left) {
        fwrite(STDERR, "CLEANUP FAILED: could not remove $left\n");
    }
}

/** Close this invocation's streams, then remove only what it created; returns what could not be removed. */
function cleanup_owned(): array
{
    foreach ($GLOBALS['release_open_handles'] as $handle) {
        if (is_resource($handle)) {
            @fclose($handle);
        }
    }
    $GLOBALS['release_open_handles'] = [];

    $left = [];
    foreach (array_keys($GLOBALS['release_owned_files']) as $file) {
        clearstatcache(true, $file);
        if (file_exists($file) && ! @unlink($file)) {
            $left[] = $file;
            continue;
        }
        unset($GLOBALS['release_owned_files'][$file]);
    }
    foreach (array_reverse($GLOBALS['release_owned_dirs']) as $dir) {
        if (is_dir($dir) && ! @rmdir($dir)) {
            $left[] = "$dir (directory not empty or not removable)";
        }
    }
    $GLOBALS['release_owned_dirs'] = [];

    return $left;
}

register_shutdown_function(static function (): void {
    if (! $GLOBALS['release_completed']) {
        cleanup_and_report(); // fatal error: do not leave this invocation's partial outputs behind
    }
});

/** @param resource $h @return resource */
function track($h)
{
    $GLOBALS['release_open_handles'][get_resource_id($h)] = $h;

    return $h;
}

/** @return resource */
function open_tracked(string $path, string $mode)
{
    $h = @fopen($path, $mode);
    if ($h === false) {
        fail("cannot open $path");
    }

    return track($h);
}

/** @param resource $h */
function close_tracked($h): bool
{
    unset($GLOBALS['release_open_handles'][get_resource_id($h)]);

    return fclose($h);
}

/** A temporary file in the system temp directory, owned (and removed) by this invocation. */
function temp_file(): string
{
    $path = tempnam(sys_get_temp_dir(), 'avr');
    if ($path === false) {
        fail('cannot create a temporary file');
    }
    $GLOBALS['release_owned_files'][$path] = true;

    return $path;
}

function release_temp(string $path): void
{
    if (! @unlink($path)) {
        fail("cannot remove temporary file $path");
    }
    unset($GLOBALS['release_owned_files'][$path]);
}

/**
 * Run git without a shell: an argument array, so nothing is re-quoted or interpreted. stdout and stderr go to
 * two temporary files instead of pipes, so neither can fill up and deadlock, and no pipe text mode applies.
 * Returns the stdout file; the caller removes it with release_temp().
 */
function git_to_file(array $args): string
{
    $out = temp_file();
    $err = temp_file();
    $process = proc_open(
        array_merge(['git'], $args),
        [0 => ['pipe', 'r'], 1 => ['file', $out, 'wb'], 2 => ['file', $err, 'wb']],
        $pipes
    );
    if (! is_resource($process)) {
        fail('cannot run git');
    }
    $closed = fclose($pipes[0]);
    $code = proc_close($process);
    if ($code !== 0) {
        $message = (string) @file_get_contents($err, false, null, 0, MAX_GIT_ERROR_BYTES);
        fail("git {$args[0]} failed (exit $code): " . trim($message));
    }
    if (! $closed) {
        fail('cannot close the git input pipe');
    }
    release_temp($err);

    return $out;
}

/** Complete stdout of a git command whose output must fit in $maxBytes. */
function git_output(array $args, int $maxBytes): string
{
    $out = git_to_file($args);
    $data = @file_get_contents($out, false, null, 0, $maxBytes + 1);
    if ($data === false) {
        fail("cannot read git {$args[0]} output");
    }
    release_temp($out);
    if (strlen($data) > $maxBytes) {
        fail("git {$args[0]} output is larger than $maxBytes bytes");
    }

    return $data;
}

function regex_match(string $pattern, string $subject): bool
{
    $r = preg_match($pattern, $subject);
    if ($r === false) {
        fail('regex evaluation failed (' . preg_last_error_msg() . '): ' . $pattern);
    }

    return $r === 1;
}

/** Manifest from the selected commit, with every field and regex checked before use. */
function load_manifest(string $sha): array
{
    try {
        $m = json_decode(git_output(['cat-file', 'blob', "$sha:" . MANIFEST_PATH], MAX_MANIFEST_BYTES), true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        fail('manifest at the selected commit is not valid JSON');
    }
    if (! is_array($m)) {
        fail('manifest at the selected commit is not an object');
    }
    $lists = [
        'archive_pathspecs' => null,
        'allow_regex' => '~%s~',
        'allowed_dotfile_basenames' => null,
        'forbidden_path_regex' => '~%s~i',
        'required_files' => null,
    ];
    foreach ($lists as $field => $form) {
        if (! isset($m[$field]) || ! is_array($m[$field]) || ! array_is_list($m[$field]) || $m[$field] === []) {
            fail("manifest field $field must be a non-empty list");
        }
        foreach ($m[$field] as $value) {
            if (! is_string($value) || $value === '') {
                fail("manifest field $field must contain non-empty strings");
            }
            if ($form !== null && @preg_match(sprintf($form, $value), '') === false) {
                fail("manifest field $field contains an invalid regex: $value");
            }
        }
    }
    $scan = $m['content_scan_regex'] ?? null;
    if (! is_array($scan) || $scan === [] || array_is_list($scan)) {
        fail('manifest field content_scan_regex must be a non-empty object');
    }
    foreach ($scan as $label => $re) {
        if (! is_string($label) || ! is_string($re) || $re === '' || @preg_match("~$re~mi", '') === false) {
            fail("manifest content_scan_regex '$label' is invalid");
        }
    }

    return $m;
}

/** True for resources/legal-archive and anything below it, or anything a host could open as one of those. */
function legal_archive_collides(string $path): bool
{
    $key = ReleaseTarReader::aliasKey($path);

    return $key === LEGAL_ARCHIVE_PATH || str_starts_with($key, LEGAL_ARCHIVE_PATH . '/');
}

/**
 * One tree record: no legal archive entry, and below resources/ only paths that satisfy the shared archive
 * path rules, so that no ambiguous spelling can stand in for the future archive directory.
 */
function inspect_tree_record(string $record): void
{
    $tab = strpos($record, "\t");
    if (strlen($record) > MAX_TREE_RECORD_BYTES || $tab === false || $tab === strlen($record) - 1
        || ! regex_match('/^[0-7]{6} (blob|tree|commit) [0-9a-f]{40}(?:[0-9a-f]{24})?$/D', substr($record, 0, $tab))) {
        fail('unexpected git ls-tree record for the selected commit');
    }
    $path = substr($record, $tab + 1);
    if (legal_archive_collides($path)) {
        fail('legal_archive=NOT_ACTIVE forbids any entry at or below ' . LEGAL_ARCHIVE_PATH
            . ' in the selected commit; found ' . ReleaseTarReader::describe($path));
    }
    $key = ReleaseTarReader::aliasKey($path);
    if ($key === 'resources' || str_starts_with($key, 'resources/')) {
        $violation = ReleaseTarReader::pathViolation($path);
        if ($violation !== null) {
            fail("unsafe path under resources/ in the selected commit ($violation): " . ReleaseTarReader::describe($path));
        }
    }
}

/**
 * S1-A pre-activation guard over the selected commit's complete tree (not the manifest-limited archive).
 * The listing is processed record by record under fixed byte, record and record-length limits.
 */
function assert_legal_archive_not_active(string $sha): void
{
    $listing = git_to_file(['ls-tree', '-r', '-t', '-z', '--full-tree', $sha]);
    $h = open_tracked($listing, 'rb');
    $buffer = '';
    $bytes = 0;
    $records = 0;
    while (! feof($h)) {
        $chunk = fread($h, 65536);
        if ($chunk === false) {
            fail('cannot read the tree listing');
        }
        if ($chunk === '') {
            if (feof($h)) {
                break;
            }
            fail('reading the tree listing stalled');
        }
        $bytes += strlen($chunk);
        if ($bytes > MAX_TREE_BYTES) {
            fail('tree listing of the selected commit exceeds ' . MAX_TREE_BYTES . ' bytes');
        }
        $parts = explode("\0", $buffer . $chunk);
        $buffer = (string) array_pop($parts);
        if (strlen($buffer) > MAX_TREE_RECORD_BYTES) {
            fail('tree record longer than ' . MAX_TREE_RECORD_BYTES . ' bytes');
        }
        foreach ($parts as $record) {
            if (++$records > MAX_TREE_RECORDS) {
                fail('tree of the selected commit has more than ' . MAX_TREE_RECORDS . ' entries');
            }
            inspect_tree_record($record);
        }
    }
    if (! close_tracked($h)) {
        fail('cannot close the tree listing');
    }
    release_temp($listing);
    if ($buffer !== '' || $records === 0) {
        fail('unexpected git ls-tree output for the selected commit');
    }
}

function inside_repository(string $path, string $repo): bool
{
    $p = rtrim(str_replace('\\', '/', $path), '/') . '/';
    $r = rtrim(str_replace('\\', '/', $repo), '/') . '/';
    if (PHP_OS_FAMILY === 'Windows') {
        $p = strtolower($p);
        $r = strtolower($r);
    }

    return str_starts_with($p, $r);
}

/** Resolve (and create, recording ownership) the output directory, refusing any location inside the repository. */
function prepare_output_dir(string $dir, string $repo): string
{
    if (str_contains($dir, "\0")) {
        fail('invalid output directory');
    }
    $missing = [];
    $probe = rtrim($dir, '/\\');
    while ($probe !== '' && ! is_dir($probe)) {
        if (file_exists($probe)) {
            fail('output path exists and is not a directory');
        }
        $base = basename($probe);
        if ($base === '' || $base === '.' || $base === '..') {
            fail('output directory must not end in "." or ".." segments that do not exist yet');
        }
        array_unshift($missing, $base);
        $parent = dirname($probe);
        if ($parent === $probe) {
            fail('cannot resolve the output directory');
        }
        $probe = $parent;
    }
    $existing = realpath($probe === '' ? '.' : $probe);
    if ($existing === false) {
        fail('cannot resolve the output directory');
    }
    $target = $existing;
    foreach ($missing as $segment) {
        $target .= DIRECTORY_SEPARATOR . $segment;
    }
    if (inside_repository($target, $repo)) {
        fail('output directory must be outside the repository');
    }
    $current = $existing;
    foreach ($missing as $segment) {
        $current .= DIRECTORY_SEPARATOR . $segment;
        if (@mkdir($current, 0700)) {
            $GLOBALS['release_owned_dirs'][] = $current;
        } elseif (! is_dir($current)) {
            fail('cannot create output directory');
        }
    }
    $real = realpath($target);
    if ($real === false || ! is_dir($real) || inside_repository($real, $repo)) {
        fail('output directory must be an existing directory outside the repository');
    }

    return $real;
}

/** @return resource */
function create_exclusive(string $path)
{
    $h = @fopen($path, 'xb');
    if ($h === false) {
        fail("cannot create $path exclusively (already exists or not writable); refusing to overwrite");
    }
    $GLOBALS['release_owned_files'][$path] = true;

    return track($h);
}

/** @param resource $h */
function write_all($h, string $data, string $what): void
{
    $done = 0;
    $length = strlen($data);
    while ($done < $length) {
        $n = fwrite($h, $done === 0 ? $data : substr($data, $done));
        if ($n === false || $n === 0) {
            fail("write failed: $what");
        }
        $done += $n;
    }
}

/** @param resource $h a handle that was written to */
function close_checked($h, string $what): void
{
    if (! fflush($h) || ! close_tracked($h)) {
        fail("cannot finalize $what");
    }
}

function write_partial(string $partial, string $content): void
{
    $h = create_exclusive($partial);
    write_all($h, $content, $partial);
    close_checked($h, $partial);
    clearstatcache(true, $partial);
    if (filesize($partial) !== strlen($content) || hash_file('sha256', $partial) !== hash('sha256', $content)) {
        fail("verification of $partial failed");
    }
}

/**
 * Publish without ever replacing: a hard link either creates $final or fails, also when $final already exists.
 * There is deliberately no rename or copy fallback — an output filesystem without hard links fails the build.
 */
function publish(string $partial, string $final): void
{
    if (! @link($partial, $final)) {
        clearstatcache(true, $final);
        fail(file_exists($final) || is_link($final)
            ? "refusing to overwrite existing output: $final"
            : "cannot publish $final: creating a hard link failed (the output filesystem must support hard links)");
    }
    $GLOBALS['release_owned_files'][$final] = true;
    if (! @unlink($partial)) {
        fail("cannot remove $partial after publishing");
    }
    unset($GLOBALS['release_owned_files'][$partial]);
}

/** Deterministic gzip (PHP writes mtime 0 and no file name) of exactly the validated TAR bytes. */
function compress(string $tar, string $gzPartial, string $sha256, int $bytes): void
{
    $in = open_tracked($tar, 'rb');
    close_checked(create_exclusive($gzPartial), $gzPartial);
    $gz = @gzopen($gzPartial, 'wb9');
    if ($gz === false) {
        fail('cannot open the compressed output');
    }
    track($gz);
    $hash = hash_init('sha256');
    $read = 0;
    while (! feof($in)) {
        $chunk = fread($in, COPY_CHUNK);
        if ($chunk === false) {
            fail('cannot read the validated TAR');
        }
        if ($chunk === '') {
            if (feof($in)) {
                break;
            }
            fail('reading the validated TAR stalled');
        }
        hash_update($hash, $chunk);
        $read += strlen($chunk);
        $written = gzwrite($gz, $chunk);
        if ($written === false || $written !== strlen($chunk)) {
            fail('compression write failed');
        }
    }
    if (! close_tracked($gz)) {
        fail('compression finalization failed');
    }
    if (! close_tracked($in)) {
        fail('cannot close the validated TAR');
    }
    if ($read !== $bytes || ! hash_equals($sha256, hash_final($hash))) {
        fail('the TAR changed between validation and compression');
    }
}

function add_problem(array &$problems, string $message): void
{
    $message = substr($message, 0, MAX_PROBLEM_BYTES);
    if (! isset($problems['list'][$message])) {
        $problems['total']++;
        if (count($problems['list']) < MAX_REPORTED_PROBLEMS) {
            $problems['list'][$message] = true;
        }
    }
}

function validate_entry(array $manifest, array $entry, ?string $data, array &$problems, array &$files): void
{
    $p = $entry['relative'];
    if ($p === '') {
        return; // the archive root directory; the reader enforces that every entry lies below it
    }
    if (legal_archive_collides($p)) {
        add_problem($problems, "legal archive path present while NOT_ACTIVE: $p");
    }
    if ($entry['type'] === 'dir') {
        // directories carry no content; their paths were validated by the reader and must not be denied
        foreach ($manifest['forbidden_path_regex'] as $re) {
            if (regex_match("~$re~i", "$p/")) {
                add_problem($problems, "forbidden path: $p/");
            }
        }

        return;
    }
    $matched = false;
    foreach ($manifest['allow_regex'] as $re) {
        if (regex_match("~$re~", $p)) {
            $matched = true;
            break;
        }
    }
    if (! $matched) {
        add_problem($problems, "not in allowlist: $p");
    }
    foreach ($manifest['forbidden_path_regex'] as $re) {
        if (regex_match("~$re~i", $p)) {
            add_problem($problems, "forbidden path: $p");
        }
    }
    $slash = strrpos($p, '/');
    $b = $slash === false ? $p : substr($p, $slash + 1);
    if ($b[0] === '.' && ! in_array($b, $manifest['allowed_dotfile_basenames'], true)) {
        add_problem($problems, "unexpected dotfile: $p");
    }
    if ($data !== null) {
        foreach ($manifest['content_scan_regex'] as $label => $re) {
            if (regex_match("~$re~mi", $data)) {
                add_problem($problems, "content rule '$label': $p");
            }
        }
    }
    $files[$p] = $entry['size'];
}

try {
    $opt = getopt('', ['out:', 'rev::']);
    if (! isset($opt['out']) || ! is_string($opt['out']) || $opt['out'] === '') {
        fail('usage: --out=<dir outside the repository> [--rev=HEAD]');
    }
    $rev = $opt['rev'] ?? 'HEAD';
    if (! is_string($rev) || $rev === '' || str_starts_with($rev, '-') || strlen($rev) > 256
        || regex_match('/[\x00-\x20\x7F]/', $rev)) {
        fail('invalid --rev');
    }
    if (! function_exists('mb_convert_case') || ! function_exists('gzopen') || ! function_exists('inflate_init')) {
        fail('the mbstring and zlib extensions are required');
    }
    $repo = realpath(__DIR__ . '/../..');
    if ($repo === false || ! chdir($repo)) {
        fail('cannot enter the repository');
    }

    // 1. One selected revision: resolved before anything else is read from it.
    $sha = trim(git_output(['rev-parse', '--verify', $rev . '^{commit}'], 256));
    if (! regex_match('/^[0-9a-f]{40}(?:[0-9a-f]{24})?$/D', $sha)) {
        fail('git rev-parse returned an unexpected commit id');
    }
    $short = substr($sha, 0, 12);
    $manifest = load_manifest($sha);
    assert_legal_archive_not_active($sha);

    // 2. Output locations: outside the repository, nothing pre-existing is touched.
    $outDir = prepare_output_dir($opt['out'], $repo);
    $base = "avilona-public-$short";
    $tar = "$outDir/$base.tar.partial";
    $final = "$outDir/$base.tar.gz";
    $gzPartial = "$final.partial";
    $list = "$outDir/$base.files.txt";
    $sum = "$final.sha256";
    foreach ([$tar, $gzPartial, "$list.partial", "$sum.partial", $list, $sum, $final] as $path) {
        clearstatcache(true, $path);
        if (file_exists($path) || is_link($path)) {
            fail("refusing to overwrite existing output: $path");
        }
    }

    // 3. Archive the selected commit into an intermediate this invocation owns.
    close_checked(create_exclusive($tar), $tar);
    release_temp(git_to_file(array_merge(['archive', '--format=tar', "--prefix=$base/", '-o', $tar, $sha, '--'], $manifest['archive_pathspecs'])));

    // 4. Strict, streamed validation of every entry.
    $problems = ['list' => [], 'total' => 0];
    $files = [];
    $archive = ReleaseTarReader::read($tar, [
        'prefix' => "$base/",
        'content_limit' => SCAN_LIMIT,
        'want_content' => static fn (string $p, int $size): bool => ! regex_match(BINARY_EXTENSIONS, $p),
        'visit' => static function (array $entry, ?string $data) use ($manifest, &$problems, &$files): void {
            validate_entry($manifest, $entry, $data, $problems, $files);
        },
    ]);
    if ($archive['global_comment'] !== $sha) {
        add_problem($problems, 'archive does not carry the selected commit id');
    }
    foreach ($manifest['required_files'] as $req) {
        if (! array_key_exists($req, $files)) {
            add_problem($problems, "required file missing: $req");
        }
    }
    if ($problems['total'] > 0) {
        $shown = array_keys($problems['list']);
        $more = $problems['total'] - count($shown);
        fwrite(STDERR, implode("\n", $shown) . "\n" . ($more > 0 ? "... and $more more\n" : ''));
        fail($problems['total'] . ' validation problem(s)');
    }

    // 5. Compress exactly the validated bytes and prove the round trip before anything is published.
    compress($tar, $gzPartial, $archive['archive_sha256'], $archive['archive_bytes']);
    ReleaseGzipVerifier::assertMatches($gzPartial, $archive['archive_sha256'], $archive['archive_bytes']);
    if (! @unlink($tar)) {
        fail('cannot remove the TAR intermediate');
    }
    unset($GLOBALS['release_owned_files'][$tar]);

    // 6. Sidecars first, the package last.
    ksort($files, SORT_STRING);
    write_partial("$list.partial", "commit $sha\nfiles " . count($files) . "\nlegal_archive NOT_ACTIVE\n" . implode("\n", array_keys($files)) . "\n");
    $hash = hash_file('sha256', $gzPartial);
    $bytes = filesize($gzPartial);
    if ($hash === false || $bytes === false) {
        fail('cannot hash the compressed output');
    }
    write_partial("$sum.partial", "$hash  $base.tar.gz\n");
    publish("$list.partial", $list);
    publish("$sum.partial", $sum);
    publish($gzPartial, $final);

    $GLOBALS['release_completed'] = true;
    printf("OK commit=%s files=%d bytes=%d sha256=%s legal_archive=NOT_ACTIVE\n%s\n", $sha, count($files), $bytes, $hash, $final);
} catch (ReleaseBuildFailure | ReleaseTarException $e) {
    abort_build($e->getMessage());
} catch (Throwable $e) {
    abort_build('unexpected ' . get_class($e) . ': ' . $e->getMessage());
}
