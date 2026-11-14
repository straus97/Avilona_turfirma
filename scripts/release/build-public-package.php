<?php
/**
 * NOV-PUBLIC-03B-L3: reproducible, fail-closed public-only release package.
 *
 *   php scripts/release/build-public-package.php --out=<dir outside repo> [--rev=HEAD]
 *
 * Source is ONLY `git archive <commit>` limited to the manifest allowlist, so the working tree
 * (.env, logs, QA output, vendor, untracked files) can never leak in. The produced archive is then
 * parsed entry by entry and rejected unless every entry matches the allowlist and no denylist,
 * dotfile, symlink, traversal or content rule. Output is byte-reproducible for a given commit
 * (git archive uses the commit time; gzip header mtime is 0). Local only: no network, no server.
 * Exit 0 = package written and validated; non-zero = no package is left behind.
 */
declare(strict_types=1);

const TAR_BLOCK = 512;

function fail(string $m): never
{
    fwrite(STDERR, "PACKAGE FAILED: $m\n");
    exit(1);
}

function git(array $args): string
{
    $cmd = 'git ' . implode(' ', array_map('escapeshellarg', $args));
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) {
        fail('cannot run git');
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    if (proc_close($p) !== 0) {
        fail("git failed: $err");
    }
    return trim($out);
}

/** Minimal ustar/pax reader. Returns list of [path, typeflag, size, data|null]. */
function read_tar(string $file, int $scanLimit): array
{
    $h = fopen($file, 'rb');
    $entries = [];
    $pathOverride = null;
    while (!feof($h)) {
        $hdr = fread($h, TAR_BLOCK);
        if ($hdr === false || strlen($hdr) < TAR_BLOCK || trim($hdr, "\0") === '') {
            break;
        }
        $name = rtrim(substr($hdr, 0, 100), "\0");
        $prefix = rtrim(substr($hdr, 345, 155), "\0");
        if ($prefix !== '') {
            $name = $prefix . '/' . $name;
        }
        $size = (int) octdec(trim(substr($hdr, 124, 12), "\0 "));
        $type = $hdr[156] === "\0" ? '0' : $hdr[156];
        $padded = (int) (ceil($size / TAR_BLOCK) * TAR_BLOCK);
        $isPax = ($type === 'x' || $type === 'g');
        $data = null;
        if ($isPax || ($type === '0' && $size <= $scanLimit)) {
            $data = $size > 0 ? fread($h, $size) : '';
            if ($padded > $size) {
                fseek($h, $padded - $size, SEEK_CUR);
            }
        } elseif ($padded > 0) {
            fseek($h, $padded, SEEK_CUR);
        }
        if ($isPax) {
            if ($type === 'x' && preg_match('/\d+ path=([^\n]*)\n/', (string) $data, $m)) {
                $pathOverride = $m[1];
            }
            continue;
        }
        if ($pathOverride !== null) {
            $name = $pathOverride;
            $pathOverride = null;
        }
        $entries[] = [$name, $type, $size, $data];
    }
    fclose($h);
    return $entries;
}

$opt = getopt('', ['out:', 'rev::']);
if (empty($opt['out'])) {
    fail('usage: --out=<dir outside the repository> [--rev=HEAD]');
}
$repo = realpath(__DIR__ . '/../..');
chdir($repo);
$manifest = json_decode(file_get_contents(__DIR__ . '/public-package-manifest.json'), true, 512, JSON_THROW_ON_ERROR);

$sha = git(['rev-parse', '--verify', ($opt['rev'] ?? 'HEAD') . '^{commit}']);
$short = substr($sha, 0, 12);
$outDir = $opt['out'];
if (!is_dir($outDir) && !mkdir($outDir, 0700, true)) {
    fail('cannot create output directory');
}
$outDir = realpath($outDir);
if (str_starts_with(rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR, $repo . DIRECTORY_SEPARATOR)) {
    fail('output directory must be outside the repository');
}

$base = "avilona-public-$short";
$tar = "$outDir/$base.tar.partial";
$final = "$outDir/$base.tar.gz";
@unlink($tar);
register_shutdown_function(static function () use ($tar): void {
    @unlink($tar); // never leave an unvalidated intermediate behind
});

git(array_merge(['archive', '--format=tar', "--prefix=$base/", '-o', $tar, $sha, '--'], $manifest['archive_pathspecs']));

$problems = [];
$files = [];
foreach (read_tar($tar, 2_000_000) as [$full, $type, $size, $data]) {
    if (!str_starts_with($full, "$base/")) {
        $problems[] = "outside prefix: $full";
        continue;
    }
    $p = rtrim(substr($full, strlen($base) + 1), '/');
    if ($p === '' || $type === '5') {
        continue; // directories carry no content
    }
    if ($type !== '0') {
        $problems[] = "non-regular entry (type $type): $p"; // symlink, hardlink, device
        continue;
    }
    if (str_contains($p, "\0") || str_contains($p, '\\') || str_starts_with($p, '/')
        || preg_match('~(^|/)\.\.?(/|$)~', $p) || preg_match('~^[A-Za-z]:~', $p)) {
        $problems[] = "unsafe path: $p";
        continue;
    }
    $matched = false;
    foreach ($manifest['allow_regex'] as $re) {
        if (preg_match("~$re~", $p)) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $problems[] = "not in allowlist: $p";
    }
    foreach ($manifest['forbidden_path_regex'] as $re) {
        if (preg_match("~$re~i", $p)) {
            $problems[] = "forbidden path: $p";
        }
    }
    $b = basename($p);
    if ($b[0] === '.' && !in_array($b, $manifest['allowed_dotfile_basenames'], true)) {
        $problems[] = "unexpected dotfile: $p";
    }
    if ($data !== null && !preg_match('~\.(png|jpe?g|gif|webp|ico|pdf|svg)$~i', $p)) {
        foreach ($manifest['content_scan_regex'] as $label => $re) {
            if (preg_match("~$re~mi", $data)) {
                $problems[] = "content rule '$label': $p";
            }
        }
    }
    $files[$p] = $size;
}
foreach ($manifest['required_files'] as $req) {
    if (!array_key_exists($req, $files)) {
        $problems[] = "required file missing: $req";
    }
}
if ($problems) {
    @unlink($tar);
    $problems = array_unique($problems);
    fwrite(STDERR, implode("\n", $problems) . "\n");
    fail(count($problems) . ' validation problem(s)');
}

// Deterministic gzip (PHP writes mtime 0 and no file name) + sidecar listing and checksum.
$in = fopen($tar, 'rb');
$gzh = gzopen($final, 'wb9');
while (!feof($in)) {
    gzwrite($gzh, (string) fread($in, 1 << 20));
}
gzclose($gzh);
fclose($in);
unlink($tar);
ksort($files);
$lines = [];
foreach ($files as $p => $size) {
    $lines[] = $p;
}
file_put_contents("$outDir/$base.files.txt", "commit $sha\nfiles " . count($files) . "\n" . implode("\n", $lines) . "\n");
$hash = hash_file('sha256', $final);
file_put_contents("$outDir/$base.tar.gz.sha256", "$hash  $base.tar.gz\n");
printf("OK commit=%s files=%d bytes=%d sha256=%s\n%s\n", $sha, count($files), filesize($final), $hash, $final);
