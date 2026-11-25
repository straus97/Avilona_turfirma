<?php

namespace App\Services\Legal;

use JsonException;

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: strict validator of the legal document registry
 * (`resources/legal-archive/registry.json`, introduced only at S1-B).
 *
 * Framework-independent: no Laravel helpers, container, configuration, environment, database or file access.
 * The input is the exact registry bytes; the result is an immutable validated view.
 *
 * Contract (Design Revision 2 as adjusted by the S1 scope amendment):
 * - canonical bytes: ASCII only, LF only, no BOM, exactly one final LF, at most 64 KiB, and byte-identical to
 *   json_encode(decoded, PRETTY_PRINT | UNESCAPED_SLASHES | UNESCAPED_UNICODE) . "\n" — this rejects repeated
 *   member names (also escaped spellings), reordering of raw bytes, alternative whitespace and escapes;
 * - exact field sets in exact order, strict types, no coercion;
 * - exactly the six documents in key order, bound to the existing consent route and the five existing public PDFs;
 * - versions rev-0001.. in sequence, locked, approved by reference, deterministic artifact paths, pinned
 *   SHA-256 and byte size; current version = last activation; supersession is derived; restrictions forbid
 *   a version from being current; the current consent version links the current version of every target.
 *
 * Limits of proof: validation covers one registry only. It does not establish legal approval (a reference is
 * structure, not authority — DR-08 stays open), append-only history across revisions, or that the artifacts
 * exist; the legal package checker verifies artifacts. There is no registry writer and no runtime activation.
 */
final class LegalRegistry
{
    public const FORMAT = 1;
    public const HASH_ALGORITHM = 'sha256';
    public const ARCHIVE_ROOT = 'resources/legal-archive';
    public const REGISTRY_FILE = 'registry.json';
    public const MAX_BYTES = 65536;
    public const MAX_VERSIONS = 999;
    /** Versions of a page (fragment) document; the package checker retains at most this many fragments. */
    public const MAX_PAGE_VERSIONS = 32;
    public const MAX_HISTORY = 999;
    public const MAX_LINKS = 16;
    public const MAX_PDF_BYTES = 67108864;
    public const MAX_FRAGMENT_BYTES = 65536;

    public const CONTENT_PDF = 'application/pdf';
    public const CONTENT_HTML = 'text/html';

    public const PLACEHOLDER_PATTERN = '/^[A-Z][A-Z0-9_]{0,31}$/D';

    /** Every page (consent) version must declare this placeholder, bound to this document and to no other name. */
    public const POLICY_PLACEHOLDER = 'POLICY';
    public const POLICY_DOCUMENT = 'personal-data-policy';

    /** Required document keys, in the only accepted order. */
    public const DOCUMENT_KEYS = [
        'inquiry-consent-text',
        'personal-data-policy',
        'separate-services-terms',
        'tour-product-terms',
        'travel-memo-general',
        'user-agreement',
    ];

    /** Page surfaces: document key => existing route name. */
    public const PAGE_BINDINGS = [
        'inquiry-consent-text' => 'personal_data_consent.info',
    ];

    /** Public-file surfaces: document key => existing asset path relative to the web root (URLs do not change). */
    public const PUBLIC_FILE_BINDINGS = [
        'personal-data-policy' => 'documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
        'separate-services-terms' => 'documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf',
        'tour-product-terms' => 'documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf',
        'travel-memo-general' => 'documents/Reminder_for_those_traveling_abroad_general.pdf',
        'user-agreement' => 'documents/User_Agreement.pdf',
    ];

    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    private const TOP_FIELDS = ['format', 'hash_algorithm', 'documents'];
    private const DOCUMENT_FIELDS = ['key', 'surface', 'versions', 'activations', 'restrictions'];
    private const PAGE_SURFACE_FIELDS = ['type', 'route_name'];
    private const FILE_SURFACE_FIELDS = ['type', 'asset_path', 'repository_path'];
    private const VERSION_FIELDS = ['version_id', 'locked', 'approval_reference', 'content_type', 'path', 'sha256', 'bytes', 'links'];
    private const ACTIVATION_FIELDS = ['version_id', 'reference', 'effective_from'];
    private const RESTRICTION_FIELDS = ['version_id', 'reference'];
    private const LINK_FIELDS = ['placeholder', 'document_key', 'version_id'];

    private const VERSION_ID_PATTERN = '/^rev-[0-9]{4}$/D';
    private const REFERENCE_PATTERN = '~^[A-Za-z0-9](?:[A-Za-z0-9._:/-]{0,126}[A-Za-z0-9])?$~D';
    private const ASSET_PATH_PATTERN = '/^documents\/[A-Za-z0-9_]+\.pdf$/D';
    private const SHA256_PATTERN = '/^[0-9a-f]{64}$/D';
    private const DATE_PATTERN = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D';

    /**
     * @param array<string, array{surface: array<string, string>, versions: array<string, array<string, mixed>>,
     *     activations: list<array<string, string>>, restricted: array<string, string>, current: string}> $documents
     */
    private function __construct(private readonly array $documents, private readonly string $sha256)
    {
    }

    /** Validate exact registry bytes. Throws LegalArchiveException with a `registry_*` reason. */
    public static function parse(string $raw): self
    {
        self::assertCanonicalBytes($raw);

        $top = self::fields(json_decode($raw, true, 64, JSON_THROW_ON_ERROR), self::TOP_FIELDS, 'registry');
        if ($top['format'] !== self::FORMAT || $top['hash_algorithm'] !== self::HASH_ALGORITHM) {
            throw new LegalArchiveException('registry_format', 'format/hash_algorithm');
        }

        $rawDocuments = self::listOf($top['documents'], 'documents', 64);
        $keys = [];
        foreach ($rawDocuments as $i => $document) {
            $document = self::fields($document, self::DOCUMENT_FIELDS, "documents[$i]");
            $keys[] = $document['key'];
            $rawDocuments[$i] = $document;
        }
        if ($keys !== self::DOCUMENT_KEYS) {
            throw new LegalArchiveException('registry_documents', 'the six required documents in key order');
        }

        $documents = [];
        foreach ($rawDocuments as $document) {
            $documents[$document['key']] = self::validateDocument($document);
        }
        self::assertLinks($documents);
        self::assertUniquePaths($documents);

        return new self($documents, hash('sha256', $raw));
    }

    /** Archive-relative artifact path derived from key, version and content type. */
    public static function artifactPath(string $key, string $versionId, string $contentType): string
    {
        return 'versions/' . $key . '/' . $versionId . '/' . ($contentType === self::CONTENT_HTML ? 'fragment.html' : 'document.pdf');
    }

    /** Repository-relative path of an archive-relative path. */
    public static function repositoryPath(string $archivePath): string
    {
        return self::ARCHIVE_ROOT . '/' . $archivePath;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    /** @return list<string> */
    public function documentKeys(): array
    {
        return array_keys($this->documents);
    }

    /** @return array<string, string> */
    public function surface(string $key): array
    {
        return $this->document($key)['surface'];
    }

    /** @return list<array<string, mixed>> */
    public function versions(string $key): array
    {
        return array_values($this->document($key)['versions']);
    }

    /** @return array<string, mixed> */
    public function version(string $key, string $versionId): array
    {
        $versions = $this->document($key)['versions'];
        if (! isset($versions[$versionId])) {
            throw new LegalArchiveException('registry_version_id', "unknown version $key/$versionId");
        }

        return $versions[$versionId];
    }

    /** @return array<string, mixed> the version record named by the last activation */
    public function currentVersion(string $key): array
    {
        return $this->version($key, $this->document($key)['current']);
    }

    /** @return list<array<string, string>> */
    public function activations(string $key): array
    {
        return $this->document($key)['activations'];
    }

    public function isRestricted(string $key, string $versionId): bool
    {
        return isset($this->document($key)['restricted'][$versionId]);
    }

    /**
     * Every archived artifact of every version (not only current ones).
     *
     * @return list<array{document_key: string, version_id: string, content_type: string, path: string,
     *     repository_path: string, sha256: string, bytes: int}>
     */
    public function artifacts(): array
    {
        $artifacts = [];
        foreach ($this->documents as $key => $document) {
            foreach ($document['versions'] as $id => $version) {
                $artifacts[] = [
                    'document_key' => $key,
                    'version_id' => $id,
                    'content_type' => $version['content_type'],
                    'path' => $version['path'],
                    'repository_path' => self::repositoryPath($version['path']),
                    'sha256' => $version['sha256'],
                    'bytes' => $version['bytes'],
                ];
            }
        }

        return $artifacts;
    }

    /**
     * Public-file bindings. The repository path is for reading and verification only; the asset path is the
     * web-root-relative input of URL resolution, which the caller performs (no URL is produced here).
     *
     * @return array<string, array{asset_path: string, repository_path: string}>
     */
    public function publicFiles(): array
    {
        $files = [];
        foreach ($this->documents as $key => $document) {
            if ($document['surface']['type'] === 'public-file') {
                $files[$key] = [
                    'asset_path' => $document['surface']['asset_path'],
                    'repository_path' => $document['surface']['repository_path'],
                ];
            }
        }

        return $files;
    }

    /** @return list<string> placeholder names declared by a version, in record order */
    public function placeholders(string $key, string $versionId): array
    {
        return array_column($this->version($key, $versionId)['links'], 'placeholder');
    }

    /**
     * @return array<string, array{document_key: string, version_id: string, asset_path: string}> placeholder => target
     */
    public function linkTargets(string $key, string $versionId): array
    {
        $targets = [];
        foreach ($this->version($key, $versionId)['links'] as $link) {
            $targets[$link['placeholder']] = [
                'document_key' => $link['document_key'],
                'version_id' => $link['version_id'],
                'asset_path' => $this->document($link['document_key'])['surface']['asset_path'],
            ];
        }

        return $targets;
    }

    private function document(string $key): array
    {
        if (! isset($this->documents[$key])) {
            throw new LegalArchiveException('registry_documents', "unknown document $key");
        }

        return $this->documents[$key];
    }

    private static function assertCanonicalBytes(string $raw): void
    {
        $length = strlen($raw);
        if ($length === 0 || $length > self::MAX_BYTES) {
            throw new LegalArchiveException('registry_size', "$length bytes");
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            throw new LegalArchiveException('registry_encoding', 'BOM');
        }
        if (self::matches('/[^\x20-\x7E\n]/', $raw)) {
            throw new LegalArchiveException('registry_encoding', 'only printable ASCII and LF are allowed');
        }
        if (! str_ends_with($raw, "\n") || str_ends_with($raw, "\n\n")) {
            throw new LegalArchiveException('registry_encoding', 'exactly one final LF');
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            $canonical = json_encode($decoded, self::JSON_FLAGS) . "\n";
        } catch (JsonException) {
            throw new LegalArchiveException('registry_json', 'malformed JSON');
        }
        if (! hash_equals($canonical, $raw)) {
            throw new LegalArchiveException('registry_noncanonical', 'bytes differ from the canonical encoding');
        }
    }

    /** @return array{surface: array, versions: array, activations: list, restricted: array, current: string} */
    private static function validateDocument(array $document): array
    {
        $key = $document['key'];
        $surface = self::validateSurface($key, $document['surface']);
        $contentType = $surface['type'] === 'page' ? self::CONTENT_HTML : self::CONTENT_PDF;

        $versions = [];
        $approvals = [];
        if ($contentType === self::CONTENT_HTML && is_array($document['versions']) && count($document['versions']) > self::MAX_PAGE_VERSIONS) {
            throw new LegalArchiveException('registry_limit', "$key has more than " . self::MAX_PAGE_VERSIONS . ' versions');
        }
        $rawVersions = self::listOf($document['versions'], "$key.versions", self::MAX_VERSIONS);
        if ($rawVersions === []) {
            throw new LegalArchiveException('registry_version_id', "$key has no versions");
        }
        foreach ($rawVersions as $i => $version) {
            $version = self::fields($version, self::VERSION_FIELDS, "$key.versions[$i]");
            $id = $version['version_id'];
            if (! is_string($id) || ! self::matches(self::VERSION_ID_PATTERN, $id) || $id !== sprintf('rev-%04d', $i + 1)) {
                throw new LegalArchiveException('registry_version_id', "$key.versions[$i] must be " . sprintf('rev-%04d', $i + 1));
            }
            if ($version['locked'] !== true) {
                throw new LegalArchiveException('registry_version_locked', "$key/$id");
            }
            $approval = self::reference($version['approval_reference'], 'registry_approval_reference', "$key/$id");
            if (isset($approvals[$approval])) {
                throw new LegalArchiveException('registry_approval_reference', "$key/$id repeats an approval reference");
            }
            $approvals[$approval] = true;
            if ($version['content_type'] !== $contentType) {
                throw new LegalArchiveException('registry_content_type', "$key/$id");
            }
            if ($version['path'] !== self::artifactPath($key, $id, $contentType)) {
                throw new LegalArchiveException('registry_path', "$key/$id must use " . self::artifactPath($key, $id, $contentType));
            }
            if (! is_string($version['sha256']) || ! self::matches(self::SHA256_PATTERN, $version['sha256'])) {
                throw new LegalArchiveException('registry_hash', "$key/$id");
            }
            $max = $contentType === self::CONTENT_HTML ? self::MAX_FRAGMENT_BYTES : self::MAX_PDF_BYTES;
            if (! is_int($version['bytes']) || $version['bytes'] < 1 || $version['bytes'] > $max) {
                throw new LegalArchiveException('registry_bytes', "$key/$id");
            }
            $links = self::listOf($version['links'], "$key/$id.links", self::MAX_LINKS);
            if ($contentType === self::CONTENT_PDF && $links !== []) {
                throw new LegalArchiveException('registry_links', "$key/$id: PDF records carry no links");
            }
            $version['links'] = $links;
            $versions[$id] = $version;
        }

        [$activations, $current, $activationReferences] = self::validateActivations($key, $document['activations'], $versions);
        $restricted = self::restrictions($key, $document['restrictions'], $versions, $activationReferences);
        if (isset($restricted[$current])) {
            throw new LegalArchiveException('registry_current_restricted', "$key/$current");
        }

        return [
            'surface' => $surface,
            'versions' => $versions,
            'activations' => $activations,
            'restricted' => $restricted,
            'current' => $current,
        ];
    }

    /** @return array<string, string> */
    private static function validateSurface(string $key, mixed $value): array
    {
        if (isset(self::PAGE_BINDINGS[$key])) {
            $surface = self::fields($value, self::PAGE_SURFACE_FIELDS, "$key.surface");
            if ($surface['type'] !== 'page' || $surface['route_name'] !== self::PAGE_BINDINGS[$key]) {
                throw new LegalArchiveException('registry_surface', "$key must bind the existing consent route");
            }

            return $surface;
        }

        $surface = self::fields($value, self::FILE_SURFACE_FIELDS, "$key.surface");
        $asset = $surface['asset_path'];
        if ($surface['type'] !== 'public-file' || ! is_string($asset) || ! self::matches(self::ASSET_PATH_PATTERN, $asset)) {
            throw new LegalArchiveException('registry_surface', "$key.surface.asset_path");
        }
        if ($surface['repository_path'] !== 'public/' . $asset) {
            throw new LegalArchiveException('registry_surface', "$key.surface.repository_path must be public/ + asset_path");
        }
        if ($asset !== self::PUBLIC_FILE_BINDINGS[$key]) {
            throw new LegalArchiveException('registry_surface', "$key must bind its existing public file");
        }

        return $surface;
    }

    /**
     * @param array<string, array> $versions
     * @return array{0: list<array<string, string>>, 1: string, 2: array<string, true>}
     */
    private static function validateActivations(string $key, mixed $value, array $versions): array
    {
        $activations = self::listOf($value, "$key.activations", self::MAX_HISTORY);
        if ($activations === []) {
            throw new LegalArchiveException('registry_activation', "$key has no current version");
        }

        $references = [];
        $firstSeen = [];
        $previousDate = null;
        $previousVersion = null;
        foreach ($activations as $i => $activation) {
            $activation = self::fields($activation, self::ACTIVATION_FIELDS, "$key.activations[$i]");
            $id = $activation['version_id'];
            if (! is_string($id) || ! isset($versions[$id])) {
                throw new LegalArchiveException('registry_activation', "$key.activations[$i] names an unknown version");
            }
            $reference = self::reference($activation['reference'], 'registry_reference', "$key.activations[$i]");
            if (isset($references[$reference])) {
                throw new LegalArchiveException('registry_reference', "$key.activations[$i] repeats a reference");
            }
            $references[$reference] = true;
            $date = self::date($activation['effective_from'], "$key.activations[$i]");
            if ($previousDate !== null && strcmp($date, $previousDate) < 0) {
                throw new LegalArchiveException('registry_date', "$key.activations[$i] is earlier than the previous activation");
            }
            if ($id === $previousVersion) {
                throw new LegalArchiveException('registry_activation', "$key.activations[$i] repeats the current version");
            }
            if (! isset($firstSeen[$id])) {
                $expected = sprintf('rev-%04d', count($firstSeen) + 1);
                if ($id !== $expected) {
                    throw new LegalArchiveException('registry_activation', "$key: $expected must be activated before $id");
                }
                $firstSeen[$id] = true;
            }
            $previousDate = $date;
            $previousVersion = $id;
            $activations[$i] = $activation;
        }
        if (count($firstSeen) !== count($versions)) {
            throw new LegalArchiveException('registry_activation', "$key has a version that was never activated");
        }

        return [$activations, $previousVersion, $references];
    }

    /**
     * @param array<string, array> $versions
     * @param array<string, true> $activationReferences
     * @return array<string, string> restricted version id => reference
     */
    private static function restrictions(string $key, mixed $value, array $versions, array $activationReferences): array
    {
        $restricted = [];
        $references = [];
        foreach (self::listOf($value, "$key.restrictions", self::MAX_HISTORY) as $i => $restriction) {
            $restriction = self::fields($restriction, self::RESTRICTION_FIELDS, "$key.restrictions[$i]");
            $id = $restriction['version_id'];
            if (! is_string($id) || ! isset($versions[$id])) {
                throw new LegalArchiveException('registry_restriction', "$key.restrictions[$i] names an unknown version");
            }
            if (isset($restricted[$id])) {
                throw new LegalArchiveException('registry_restriction', "$key/$id is restricted twice");
            }
            $reference = self::reference($restriction['reference'], 'registry_reference', "$key.restrictions[$i]");
            if (isset($references[$reference]) || isset($activationReferences[$reference])) {
                throw new LegalArchiveException('registry_reference', "$key.restrictions[$i] reuses a reference");
            }
            $references[$reference] = true;
            $restricted[$id] = $reference;
        }

        return $restricted;
    }

    private static function assertLinks(array $documents): void
    {
        foreach ($documents as $key => $document) {
            foreach ($document['versions'] as $id => $version) {
                $placeholders = [];
                foreach ($version['links'] as $i => $link) {
                    $link = self::fields($link, self::LINK_FIELDS, "$key/$id.links[$i]");
                    $placeholder = $link['placeholder'];
                    if (! is_string($placeholder) || ! self::matches(self::PLACEHOLDER_PATTERN, $placeholder) || isset($placeholders[$placeholder])) {
                        throw new LegalArchiveException('registry_links', "$key/$id.links[$i].placeholder");
                    }
                    $placeholders[$placeholder] = $link['document_key'];
                    $target = $link['document_key'];
                    if (! is_string($target) || ! isset(self::PUBLIC_FILE_BINDINGS[$target])) {
                        throw new LegalArchiveException('registry_links', "$key/$id.links[$i] must target a public-file document");
                    }
                    if (! is_string($link['version_id']) || ! isset($documents[$target]['versions'][$link['version_id']])) {
                        throw new LegalArchiveException('registry_links', "$key/$id.links[$i] names an unresolved version");
                    }
                    if ($id === $document['current'] && $link['version_id'] !== $documents[$target]['current']) {
                        throw new LegalArchiveException('registry_current_link', "$key/$id.links[$i] must link the current $target version");
                    }
                }
                if ($document['surface']['type'] === 'page') {
                    self::assertPolicyBinding("$key/$id", $placeholders);
                }
            }
        }
    }

    /**
     * Mandatory Policy link of every consent version (historical ones included): POLICY targets the policy,
     * and no other placeholder does. Further links to other public-file documents are permitted.
     *
     * @param array<string, string> $targets placeholder => target document key
     */
    private static function assertPolicyBinding(string $where, array $targets): void
    {
        $policyLinks = array_keys($targets, self::POLICY_DOCUMENT, true);
        if ($policyLinks !== [self::POLICY_PLACEHOLDER]) {
            throw new LegalArchiveException('registry_policy_link',
                "$where must link " . self::POLICY_DOCUMENT . ' exactly once, through ' . self::POLICY_PLACEHOLDER);
        }
    }

    /** Defence in depth: every repository path is unique, also ignoring ASCII case. */
    private static function assertUniquePaths(array $documents): void
    {
        $seen = [];
        foreach ($documents as $document) {
            $paths = [];
            if ($document['surface']['type'] === 'public-file') {
                $paths[] = $document['surface']['repository_path'];
            }
            foreach ($document['versions'] as $version) {
                $paths[] = self::repositoryPath($version['path']);
            }
            foreach ($paths as $path) {
                $folded = strtolower($path);
                if (isset($seen[$folded])) {
                    throw new LegalArchiveException('registry_path', "case-insensitive duplicate $path");
                }
                $seen[$folded] = true;
            }
        }
    }

    /** preg_match that never turns an engine error into "no match". */
    private static function matches(string $pattern, string $subject, ?array &$found = null): bool
    {
        $result = preg_match($pattern, $subject, $found);
        if ($result === false) {
            throw new LegalArchiveException('registry_regex', preg_last_error_msg());
        }

        return $result === 1;
    }

    private static function reference(mixed $value, string $reason, string $where): string
    {
        if (! is_string($value) || ! self::matches(self::REFERENCE_PATTERN, $value)) {
            throw new LegalArchiveException($reason, $where);
        }

        return $value;
    }

    private static function date(mixed $value, string $where): string
    {
        if (! is_string($value) || ! self::matches(self::DATE_PATTERN, $value, $m)
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new LegalArchiveException('registry_date', "$where.effective_from");
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function fields(mixed $value, array $fields, string $where): array
    {
        if (! is_array($value) || array_keys($value) !== $fields) {
            throw new LegalArchiveException('registry_fields', "$where must have exactly: " . implode(', ', $fields));
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function listOf(mixed $value, string $where, int $max): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > $max) {
            throw new LegalArchiveException('registry_type', "$where must be a list of at most $max entries");
        }

        return $value;
    }
}
