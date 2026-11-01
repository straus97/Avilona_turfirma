<?php

namespace App\Services\Content\Legacy;

/**
 * NOV-PUBLIC-04A: инвентаризация архива изображений БЕЗ распаковки на диск.
 *
 * Записи читаются в память ограниченными порциями через ZipArchive и сразу
 * проверяются; на файловую систему ничего не пишется, имена записей не
 * используются для построения путей. Небезопасные имена (.., абсолютные пути,
 * буква диска, обратный слэш, NUL, управляющие символы, символические ссылки,
 * шифрование) помечаются и НЕ читаются и не сопоставляются со ссылками.
 *
 * Изображения не изменяются и не увеличиваются: отсутствующий «large»-вариант
 * остаётся отсутствующим.
 */
final class LegacyImageArchiveInspector
{
    public const MAX_ENTRIES = 20000;

    public const MAX_ENTRY_BYTES = 25 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024;

    public const MAX_PIXELS = 100_000_000;

    private const IMAGE_TYPES = [
        'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG,
        'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP,
    ];

    /** Редактируемые исходники дизайнера: не публикуются. */
    private const EDITABLE = ['psd', 'psb', 'ai', 'xcf', 'tif', 'tiff', 'indd', 'sketch', 'cdr', 'eps', 'raw'];

    /** Каталоги, где ожидаются портреты сотрудников/клиентов. */
    private const PORTRAIT_DIRS = ['personal', 'employees', 'employee', 'staff', 'team', 'reviews', 'review', 'users', 'avatars', 'avatar'];

    public function __construct(
        private readonly int $maxEntryBytes = self::MAX_ENTRY_BYTES,
        private readonly int $maxEntries = self::MAX_ENTRIES,
        private readonly int $maxTotalBytes = self::MAX_TOTAL_BYTES,
    ) {
    }

    /**
     * @param array<int, array{group: string, table: string, column: string, slug: ?string, ref: string}> $refs
     *
     * @return array<string,mixed>
     */
    public function inspect(string $zipPath, array $refs): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('zip_extension_missing');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('zip_unreadable');
        }

        try {
            if ($zip->numFiles > $this->maxEntries) {
                throw new \RuntimeException('zip_too_many_entries');
            }

            $entries = $this->readEntries($zip);
        } finally {
            $zip->close();
        }

        $references = $this->match($refs, $entries);

        $referenced = [];
        foreach ($references as $info) {
            foreach ($info['entries'] as $path) {
                $referenced[$path] = true;
            }
        }
        foreach ($entries as $path => &$entry) {
            $entry['referenced'] = isset($referenced[$path]);
            if ($entry['status'] === 'image' && ! $entry['referenced']) {
                $entry['flags'][] = 'unreferenced';
            }
            if ($entry['status'] === 'image' && $this->portraitPath($path)) {
                $entry['flags'][] = 'portrait_review';
            }
        }
        unset($entry);

        foreach ($references as $ref => $info) {
            if ($info['status'] === 'present' && in_array('staff_portrait', $info['groups'], true)) {
                foreach ($info['entries'] as $path) {
                    $entries[$path]['flags'][] = 'portrait_review';
                }
            }
            if ($info['status'] === 'present' && ! in_array('public_content', $info['groups'], true)) {
                foreach ($info['entries'] as $path) {
                    $entries[$path]['flags'][] = 'referenced_only_by_unpublished_content';
                }
            }
        }
        foreach ($entries as &$entry) {
            $entry['flags'] = array_values(array_unique($entry['flags']));
        }
        unset($entry);

        return [
            'summary' => $this->summary($entries, $references),
            'entries' => $entries,
            'references' => $references,
        ];
    }

    /** @return array<string, array<string,mixed>> */
    private function readEntries(\ZipArchive $zip): array
    {
        $entries = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = (string) $stat['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }

            $path = $this->displayName($name);
            if (isset($entries[$path])) {
                $entries[$path]['status'] = 'suspicious';
                $entries[$path]['flags'][] = 'duplicate_name';

                continue;
            }
            $size = (int) $stat['size'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $entry = ['bytes' => $size, 'ext' => $ext, 'status' => 'image', 'flags' => [], 'width' => null, 'height' => null, 'sha256' => null];

            $total += $size;
            if ($total > $this->maxTotalBytes) {
                throw new \RuntimeException('zip_total_size_exceeded');
            }

            if (! $this->safeName($name)) {
                $entry['status'] = 'unsafe_path';
                $entries[$path] = $entry;

                continue;
            }
            if ($this->isSymlink($zip, $i) || (($stat['encryption_method'] ?? 0) !== 0)) {
                $entry['status'] = 'suspicious';
                $entry['flags'][] = ($stat['encryption_method'] ?? 0) !== 0 ? 'encrypted' : 'symlink';
                $entries[$path] = $entry;

                continue;
            }
            if (in_array($ext, self::EDITABLE, true)) {
                $entry['status'] = 'editable_source';
                $entries[$path] = $entry;

                continue;
            }
            if (! isset(self::IMAGE_TYPES[$ext])) {
                $entry['status'] = 'suspicious';
                $entry['flags'][] = in_array($ext, ['php', 'phtml', 'phar', 'html', 'htm', 'js', 'exe', 'bat', 'cmd', 'sh', 'svg', 'htaccess'], true)
                    ? 'executable_or_markup' : 'unsupported_type';
                $entries[$path] = $entry;

                continue;
            }
            if ($size > $this->maxEntryBytes) {
                $entry['status'] = 'suspicious';
                $entry['flags'][] = 'oversize';
                $entries[$path] = $entry;

                continue;
            }
            if ($size > 1024 * 1024 && ($stat['comp_size'] ?? 0) > 0 && $size / $stat['comp_size'] > 200) {
                $entry['flags'][] = 'extreme_compression_ratio';
            }

            $bytes = $zip->getFromIndex($i, $this->maxEntryBytes + 1);
            if ($bytes === false || strlen($bytes) > $this->maxEntryBytes) {
                $entry['status'] = 'suspicious';
                $entry['flags'][] = $bytes === false ? 'unreadable' : 'oversize';
                $entries[$path] = $entry;

                continue;
            }

            $entry['bytes'] = strlen($bytes);
            $entry['sha256'] = hash('sha256', $bytes);
            $info = @getimagesizefromstring($bytes);

            if ($info === false) {
                $entry['status'] = 'suspicious';
                $entry['flags'][] = 'not_a_valid_image';
            } else {
                $entry['width'] = (int) $info[0];
                $entry['height'] = (int) $info[1];
                if ($info[2] !== self::IMAGE_TYPES[$ext]) {
                    $entry['status'] = 'suspicious';
                    $entry['flags'][] = 'content_does_not_match_extension';
                }
                if ($entry['width'] * $entry['height'] > self::MAX_PIXELS) {
                    $entry['status'] = 'suspicious';
                    $entry['flags'][] = 'excessive_pixels';
                }
            }
            if (str_contains($bytes, '<?php') || str_contains($bytes, '<?=')) {
                $entry['status'] = 'suspicious';
                $entry['flags'][] = 'embedded_script';
            }

            $entries[$path] = $entry;
        }

        ksort($entries);

        return $entries;
    }

    /**
     * @param array<int, array{group: string, table: string, column: string, slug: ?string, ref: string}> $refs
     * @param array<string, array<string,mixed>> $entries
     *
     * @return array<string, array<string,mixed>>
     */
    private function match(array $refs, array $entries): array
    {
        $usable = [];
        foreach ($entries as $path => $entry) {
            if (in_array($entry['status'], ['image', 'suspicious', 'editable_source'], true) && $this->safeName($path)) {
                $usable[$path] = strtolower($path);
            }
        }

        $out = [];
        foreach ($refs as $ref) {
            $normalized = self::normalizeRef($ref['ref']);
            if ($normalized === null) {
                continue;
            }
            $out[$normalized] ??= ['status' => null, 'entries' => [], 'groups' => [], 'used_by' => [], 'public_path' => '/' . $normalized];
            $out[$normalized]['groups'][] = $ref['group'];
            $key = "{$ref['table']}.{$ref['column']}";
            $out[$normalized]['used_by'][$key] = ($out[$normalized]['used_by'][$key] ?? 0) + 1;
        }

        foreach ($out as $normalized => &$info) {
            $info['groups'] = array_values(array_unique($info['groups']));
            sort($info['groups']);
            ksort($info['used_by']);

            $candidates = array_values(array_unique([
                $normalized,
                str_starts_with($normalized, 'img/') ? substr($normalized, 4) : 'img/' . $normalized,
            ]));

            $exact = array_values(array_filter(array_keys($usable), fn ($p) => in_array($p, $candidates, true)));
            if ($exact === []) {
                $exact = array_values(array_filter(array_keys($usable), function ($p) use ($candidates) {
                    foreach ($candidates as $c) {
                        if (str_ends_with($p, '/' . $c)) {
                            return true;
                        }
                    }

                    return false;
                }));
            }

            if (count($exact) === 1) {
                $info['status'] = 'present';
                $info['entries'] = $exact;
            } elseif (count($exact) > 1) {
                $info['status'] = 'ambiguous';
                $info['entries'] = $exact;
            } else {
                $lower = array_map('strtolower', $candidates);
                $folded = array_values(array_filter(array_keys($usable), function ($p) use ($usable, $lower) {
                    foreach ($lower as $c) {
                        if ($usable[$p] === $c || str_ends_with($usable[$p], '/' . $c)) {
                            return true;
                        }
                    }

                    return false;
                }));
                $info['status'] = $folded === [] ? 'missing' : 'case_mismatch';
                $info['entries'] = $folded;
            }

            if ($info['status'] === 'present') {
                $entry = $entries[$info['entries'][0]];
                $info['width'] = $entry['width'];
                $info['height'] = $entry['height'];
                $info['archive_status'] = $entry['status'];
            }
        }
        unset($info);
        ksort($out);

        return $out;
    }

    /** Приведение ссылки из контента к виду «путь относительно public/», null — внешний URL. */
    public static function normalizeRef(string $ref): ?string
    {
        $ref = trim($ref);
        if ($ref === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $ref) === 1) {
            return null;
        }
        $ref = ltrim(str_replace('\\', '/', $ref), '/');
        if (str_starts_with($ref, 'public/')) {
            $ref = substr($ref, 7);
        }

        return $ref === '' ? null : $ref;
    }

    /**
     * @param array<string, array<string,mixed>> $entries
     * @param array<string, array<string,mixed>> $references
     *
     * @return array<string,mixed>
     */
    private function summary(array $entries, array $references): array
    {
        $by = fn (string $status) => count(array_filter($references, fn ($r) => $r['status'] === $status));
        $statusCount = [];
        $extensions = [];
        foreach ($entries as $entry) {
            $statusCount[$entry['status']] = ($statusCount[$entry['status']] ?? 0) + 1;
            $extensions[$entry['ext'] ?: '(none)'] = ($extensions[$entry['ext'] ?: '(none)'] ?? 0) + 1;
        }
        ksort($statusCount);
        ksort($extensions);

        return [
            'files' => count($entries),
            'by_status' => $statusCount,
            'by_extension' => $extensions,
            'references_total' => count($references),
            'references_present' => $by('present'),
            'references_missing' => $by('missing'),
            'references_case_mismatch' => $by('case_mismatch'),
            'references_ambiguous' => $by('ambiguous'),
            'unreferenced_images' => count(array_filter($entries, fn ($e) => in_array('unreferenced', $e['flags'], true))),
            'portrait_review' => count(array_filter($entries, fn ($e) => in_array('portrait_review', $e['flags'], true))),
            'editable_sources' => $statusCount['editable_source'] ?? 0,
            'suspicious' => $statusCount['suspicious'] ?? 0,
            'unsafe_paths' => $statusCount['unsafe_path'] ?? 0,
        ];
    }

    private function portraitPath(string $path): bool
    {
        foreach (array_slice(explode('/', strtolower($path)), 0, -1) as $segment) {
            if (in_array($segment, self::PORTRAIT_DIRS, true)) {
                return true;
            }
        }

        return false;
    }

    private function safeName(string $name): bool
    {
        return $name !== ''
            && strlen($name) <= 260
            && ! str_starts_with($name, '/')
            && ! str_contains($name, '\\')
            && ! str_contains($name, "\0")
            && preg_match('/^[A-Za-z]:/', $name) !== 1
            && preg_match('/[\x00-\x1F\x7F]/', $name) !== 1
            && ! in_array('..', explode('/', $name), true)
            && ! in_array('.', explode('/', $name), true);
    }

    /** Имя для отчёта: управляющие символы заменяются, чтобы вывод оставался безопасным. */
    private function displayName(string $name): string
    {
        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '?', $name) ?? '?', 0, 260);
    }

    private function isSymlink(\ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attr = 0;
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }

        return $opsys === \ZipArchive::OPSYS_UNIX && ((($attr >> 16) & 0170000) === 0120000);
    }
}
