<?php
/**
 * NOV-PUBLIC-LEGAL-01 S1-A: active legal-package verification foundation.
 *
 * NOT wired into scripts/release/build-public-package.php: in S1-A there is no application legal registry and
 * the builder reports legal_archive=NOT_ACTIVE. This component is exercised only by unit tests with synthetic
 * archives. S1-B (separately authorized) replaces the builder's NOT_ACTIVE branch with this verification.
 *
 * Including this file declares LegalPackageCheck and loads its dependencies; nothing runs. It needs neither
 * the Composer autoloader nor a Laravel bootstrap.
 *
 * All evidence comes from one TAR input, in one pass of ReleaseTarReader:
 * 1. exactly one regular resources/legal-archive/registry.json, valid per LegalRegistry;
 * 2. the exact archive inventory: the registry plus the artifact of every version, and only their ancestor
 *    directories — nothing missing, nothing unexpected (case variants of the archive root included);
 * 3. byte size and SHA-256 of every artifact equal to its registry pin (streamed, any size);
 * 4. every fragment valid per LegalFragment with the placeholders of its version record;
 * 5. exactly the five registered public PDFs under public/documents/ and no other PDF there;
 * 6. each public PDF byte-identical (size and SHA-256) to the current archived version of its document.
 *
 * It never reads the working tree, never parses PDF contents (legal wording is not validated) and writes
 * nothing.
 *
 * Paths are judged by ReleaseTarReader's shared rules: the reader rejects unsafe and case-colliding names
 * before they reach this class, and anything that merely aliases the archive root is reported as unexpected.
 */
declare(strict_types=1);

use App\Services\Legal\LegalArchiveException;
use App\Services\Legal\LegalFragment;
use App\Services\Legal\LegalRegistry;

require_once __DIR__ . '/read-tar.php';
foreach (['LegalArchiveException', 'LegalFragment', 'LegalRegistry'] as $legalClass) {
    if (! class_exists('App\\Services\\Legal\\' . $legalClass)) {
        require_once dirname(__DIR__, 2) . '/app/Services/Legal/' . $legalClass . '.php';
    }
}
unset($legalClass);

final class LegalPackageCheck
{
    /** Entries at or below the archive root (or aliasing it), files and directories. */
    public const MAX_ARCHIVE_ENTRIES = 4096;
    /**
     * Fragment payloads retained until verification (each at most LegalFragment::MAX_BYTES, so 2 MiB in
     * total). Equal to the registry's limit on versions of its one page document, so a valid registry never
     * needs more.
     */
    public const MAX_RETAINED_FRAGMENTS = LegalRegistry::MAX_PAGE_VERSIONS;
    /** PDFs seen under public/documents/ (five are expected). */
    public const MAX_PUBLIC_PDFS = 64;
    public const PUBLIC_DOCUMENTS = 'public/documents/';

    private ?string $registryRaw = null;
    /** @var array<string, array{size: int, sha256: string}> archive-relative path => pin */
    private array $archiveFiles = [];
    /** @var array<string, true> archive-relative directory paths ('' = the archive root) */
    private array $archiveDirs = [];
    /** @var list<string> */
    private array $unexpected = [];
    /** @var array<string, ?string> archive-relative fragment path => content (null = not delivered) */
    private array $fragments = [];
    /** @var array<string, array{size: int, sha256: string}> repository path => observed */
    private array $publicPdfs = [];

    /**
     * Read the archive with ReleaseTarReader and verify it. Reader failures propagate as ReleaseTarException;
     * legal failures as LegalArchiveException.
     *
     * @return array{registry_sha256: string, current: array<string, string>, artifacts: int, public_files: int,
     *     archive_sha256: string}
     */
    public static function checkTar(string $tarFile, ?string $prefix): array
    {
        $check = new self();
        $result = ReleaseTarReader::read($tarFile, [
            'prefix' => $prefix,
            'want_content' => [$check, 'wantsContent'],
            'visit' => [$check, 'visit'],
        ]);

        return $check->verify() + ['archive_sha256' => $result['archive_sha256']];
    }

    /** Content is needed only for the registry and fragments; artifacts are verified by streamed hashes. */
    public function wantsContent(string $relative, int $size): bool
    {
        $root = LegalRegistry::ARCHIVE_ROOT . '/';

        return $relative === $root . LegalRegistry::REGISTRY_FILE
            || (str_starts_with($relative, $root . 'versions/') && str_ends_with($relative, '/fragment.html'));
    }

    /** @param array{relative: string, type: string, size: int, sha256: ?string} $entry */
    public function visit(array $entry, ?string $content): void
    {
        $path = $entry['relative'];
        $root = LegalRegistry::ARCHIVE_ROOT;
        $inside = $path === $root || str_starts_with($path, $root . '/');
        $alias = ReleaseTarReader::aliasKey($path);
        $aliasesRoot = ! $inside && ($alias === $root || str_starts_with($alias, $root . '/'));

        if (! $inside && ! $aliasesRoot) {
            if ($entry['type'] === 'file' && str_starts_with($alias, self::PUBLIC_DOCUMENTS) && str_ends_with($alias, '.pdf')) {
                if (count($this->publicPdfs) >= self::MAX_PUBLIC_PDFS) {
                    throw new LegalArchiveException('package_limit', 'too many public PDFs');
                }
                $this->publicPdfs[$path] = ['size' => $entry['size'], 'sha256' => (string) $entry['sha256']];
            }

            return;
        }

        if (count($this->archiveFiles) + count($this->archiveDirs) + count($this->unexpected) >= self::MAX_ARCHIVE_ENTRIES) {
            throw new LegalArchiveException('package_limit', 'too many legal archive entries');
        }
        if ($aliasesRoot) {
            $this->unexpected[] = $path; // for example a case variant of the archive root

            return;
        }
        $inner = $path === $root ? '' : substr($path, strlen($root) + 1);
        if ($entry['type'] === 'dir') {
            $this->archiveDirs[$inner] = true;

            return;
        }
        $this->archiveFiles[$inner] = ['size' => $entry['size'], 'sha256' => (string) $entry['sha256']];
        if ($inner === LegalRegistry::REGISTRY_FILE) {
            $this->registryRaw = $content;
        } elseif (str_ends_with($inner, '/fragment.html')) {
            if (count($this->fragments) >= self::MAX_RETAINED_FRAGMENTS) {
                throw new LegalArchiveException('package_limit', 'too many fragments');
            }
            $this->fragments[$inner] = $content !== null && strlen($content) <= LegalFragment::MAX_BYTES ? $content : null;
        }
    }

    /** Verify the collected evidence. Call only after ReleaseTarReader::read() returned (archive complete). */
    public function verify(): array
    {
        if ($this->unexpected !== []) {
            throw new LegalArchiveException('package_inventory_unexpected', self::sample($this->unexpected));
        }
        if (! isset($this->archiveFiles[LegalRegistry::REGISTRY_FILE])) {
            throw new LegalArchiveException('package_registry_missing', LegalRegistry::ARCHIVE_ROOT . '/' . LegalRegistry::REGISTRY_FILE);
        }
        if ($this->registryRaw === null) {
            throw new LegalArchiveException('package_registry_missing', 'registry content was not delivered (oversize)');
        }
        $registry = LegalRegistry::parse($this->registryRaw);

        $this->verifyInventory($registry);
        $this->verifyArtifacts($registry);
        $this->verifyPublicFiles($registry);

        $current = [];
        foreach ($registry->documentKeys() as $key) {
            $current[$key] = $registry->currentVersion($key)['version_id'];
        }

        return [
            'registry_sha256' => $registry->sha256(),
            'current' => $current,
            'artifacts' => count($registry->artifacts()),
            'public_files' => count($registry->publicFiles()),
        ];
    }

    private function verifyInventory(LegalRegistry $registry): void
    {
        $expected = [LegalRegistry::REGISTRY_FILE => true];
        $dirs = ['' => true];
        foreach ($registry->artifacts() as $artifact) {
            $expected[$artifact['path']] = true;
            $parts = explode('/', $artifact['path']);
            array_pop($parts);
            $dir = '';
            foreach ($parts as $part) {
                $dir = $dir === '' ? $part : $dir . '/' . $part;
                $dirs[$dir] = true;
            }
        }

        $missing = array_keys(array_diff_key($expected, $this->archiveFiles));
        if ($missing !== []) {
            throw new LegalArchiveException('package_inventory_missing', self::sample($missing));
        }
        $unexpected = array_merge(
            array_keys(array_diff_key($this->archiveFiles, $expected)),
            array_map(static fn (string $d): string => $d . '/', array_keys(array_diff_key($this->archiveDirs, $dirs)))
        );
        if ($unexpected !== []) {
            throw new LegalArchiveException('package_inventory_unexpected', self::sample($unexpected));
        }
    }

    private function verifyArtifacts(LegalRegistry $registry): void
    {
        foreach ($registry->artifacts() as $artifact) {
            $observed = $this->archiveFiles[$artifact['path']];
            if ($observed['size'] !== $artifact['bytes'] || ! hash_equals($artifact['sha256'], $observed['sha256'])) {
                throw new LegalArchiveException('package_artifact_mismatch', $artifact['repository_path']);
            }
        }
        foreach ($registry->artifacts() as $artifact) {
            if ($artifact['content_type'] !== LegalRegistry::CONTENT_HTML) {
                continue;
            }
            $content = $this->fragments[$artifact['path']] ?? null;
            if ($content === null) {
                throw new LegalArchiveException('package_artifact_mismatch', 'fragment content not delivered: ' . $artifact['repository_path']);
            }
            LegalFragment::parse($content, $registry->placeholders($artifact['document_key'], $artifact['version_id']));
        }
    }

    private function verifyPublicFiles(LegalRegistry $registry): void
    {
        $expected = [];
        foreach ($registry->publicFiles() as $key => $file) {
            $expected[$file['repository_path']] = $key;
        }
        $missing = array_keys(array_diff_key($expected, $this->publicPdfs));
        if ($missing !== []) {
            throw new LegalArchiveException('package_public_missing', self::sample($missing));
        }
        $unexpected = array_keys(array_diff_key($this->publicPdfs, $expected));
        if ($unexpected !== []) {
            throw new LegalArchiveException('package_public_unexpected', self::sample($unexpected));
        }
        foreach ($expected as $path => $key) {
            $current = $registry->currentVersion($key);
            $observed = $this->publicPdfs[$path];
            if ($observed['size'] !== $current['bytes'] || ! hash_equals($current['sha256'], $observed['sha256'])) {
                throw new LegalArchiveException('package_public_mismatch', "$path differs from $key/" . $current['version_id']);
            }
        }
    }

    /** @param list<string> $paths */
    private static function sample(array $paths): string
    {
        sort($paths, SORT_STRING);
        $shown = array_map([ReleaseTarReader::class, 'describe'], array_slice($paths, 0, 5));

        return implode(', ', $shown) . (count($paths) > 5 ? ' (+' . (count($paths) - 5) . ' more)' : '');
    }
}
