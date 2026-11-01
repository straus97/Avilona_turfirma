<?php

namespace App\Services\Content\Legacy;

use App\Services\Seo\LegacyUrlClassifier;
use Illuminate\Support\Carbon;

/**
 * NOV-PUBLIC-04A: офлайн-конвейер подготовки публичного контента.
 *
 *   выгрузка .sql (как данные) → avilona-public-content/1 + отчёт качества
 *   архив изображений (без распаковки) → манифест ресурсов и список для владельца
 *   восстановленные slug + legacy-sitemap-paths.txt → сверка исторических URL
 *
 * Никаких соединений с базой данных. Выходные файлы пишутся только в приватный
 * каталог ВНЕ репозитория; исходная выгрузка и архив не копируются.
 */
final class LegacyPublicContentPipeline
{
    public function __construct(
        private readonly LegacyContentExtractor $extractor,
        private readonly LegacyImageArchiveInspector $inspector,
        private readonly LegacyUrlReconciler $reconciler,
    ) {
    }

    public static function make(): self
    {
        $reconciler = new LegacyUrlReconciler(app(LegacyUrlClassifier::class));
        $checks = new LegacyContentChecks(fn (string $path): string => $reconciler->classify($path), (int) Carbon::now()->year);

        return new self(
            new LegacyContentExtractor(new LegacySqlDumpParser(), $checks),
            new LegacyImageArchiveInspector(),
            $reconciler,
        );
    }

    /**
     * @param array<int,string> $acknowledgedUnknown
     *
     * @return array{report: array<string,mixed>, content: ?array<string,mixed>, manifest: ?array<string,mixed>}
     */
    public function run(string $dumpPath, ?string $zipPath, array $acknowledgedUnknown, string $legacyPathsFile): array
    {
        $size = filesize($dumpPath);
        if ($size === false || $size > LegacySqlDumpParser::MAX_BYTES) {
            throw new LegacySqlDumpException('file_too_large');
        }
        $sql = (string) file_get_contents($dumpPath);

        $extracted = $this->extractor->extract($sql, $acknowledgedUnknown);
        $source = ['bytes' => $size, 'sha256' => hash('sha256', $sql)] + ['header' => $extracted['header']];

        $report = [
            'slice' => 'NOV-PUBLIC-04A',
            'generated_at' => Carbon::now()->toIso8601String(),
            'source' => $source,
            'status' => $extracted['blocked'] ? 'BLOCKED_UNKNOWN_TABLES' : 'EXTRACTED',
            'tables_seen' => $extracted['tables_seen'],
            'tables_absent' => $extracted['tables_absent'],
            'statement_kinds' => $extracted['statement_kinds'],
            'unknown_tables' => $extracted['unknown_tables'],
            'unacknowledged_unknown_tables' => $extracted['unacknowledged_unknown_tables'],
            'rejected_acknowledgements' => $extracted['rejected_acknowledgements'],
        ];

        if ($extracted['blocked']) {
            return ['report' => $report, 'content' => null, 'manifest' => null];
        }

        $tablesPresent = array_keys($extracted['tables_seen']);
        $manifest = null;
        $images = ['status' => 'NOT_RUN: no archive supplied'];
        $imageMissing = [];

        if ($zipPath !== null) {
            $inspection = $this->redactPortraits($this->inspector->inspect($zipPath, $extracted['image_refs']));
            $images = $this->imageSection($inspection, $extracted['image_refs']);
            $imageMissing = $this->missingBySlug($inspection['references'], $extracted['image_refs']);
            $manifest = [
                'archive' => ['bytes' => (int) filesize($zipPath), 'sha256' => hash_file('sha256', $zipPath)],
                'entries' => $inspection['entries'],
                'references' => $inspection['references'],
            ];
        }

        $seo = $this->reconciler->reconcile(
            LegacyUrlReconciler::readPaths($legacyPathsFile),
            $extracted['slugs']['emitted'],
            $extracted['slugs']['held'],
            $imageMissing,
            $tablesPresent,
        );
        $seo['image_check'] = $zipPath === null ? 'not_run' : 'run';

        $report += [
            'extract' => $extracted['extract'],
            'held' => $extracted['held'],
            'schema_errors' => $extracted['schema_errors'],
            'unmapped_columns' => $extracted['unmapped_columns'],
            'quality' => $extracted['quality'],
            'inventory' => $extracted['inventory'],
            'images' => $images,
            'legacy_urls' => $seo,
            'owner_review' => $this->ownerReview($extracted, $images, $manifest),
        ];

        return ['report' => $report, 'content' => $extracted['content'], 'manifest' => $manifest];
    }

    /**
     * Запись результата в НОВЫЙ пустой каталог вне репозитория.
     *
     * @param array{report: array<string,mixed>, content: ?array<string,mixed>, manifest: ?array<string,mixed>} $result
     *
     * @return array<int,string> имена записанных файлов
     */
    public function write(array $result, string $outDir): array
    {
        self::assertOutsideRepository($outDir);

        if (file_exists($outDir) && (! is_dir($outDir) || count(array_diff(scandir($outDir) ?: [], ['.', '..'])) > 0)) {
            throw new \InvalidArgumentException('out_dir_not_empty');
        }
        if (! is_dir($outDir) && ! mkdir($outDir, 0770, true) && ! is_dir($outDir)) {
            throw new \RuntimeException('out_dir_unwritable');
        }

        $written = [];
        $put = function (string $name, string $data) use ($outDir, &$written): void {
            if (file_put_contents($outDir . DIRECTORY_SEPARATOR . $name, $data, LOCK_EX) === false) {
                throw new \RuntimeException('write_failed');
            }
            $written[] = $name;
        };

        $put('report.json', $this->json($result['report']));
        $put('report.md', $this->markdown($result['report']));

        if ($result['content'] !== null) {
            $content = $result['content'];
            $content['tables'] = $content['tables'] === [] ? new \stdClass() : $content['tables'];
            $put('public-content.json', $this->json($content));
        }
        if ($result['manifest'] !== null) {
            $put('asset-manifest.json', $this->json($result['manifest']));
        }

        return $written;
    }

    /** Отказ, если путь лежит внутри рабочей копии репозитория (чтобы выгрузки не попали в Git). */
    public static function assertOutsideRepository(string $path): void
    {
        $repo = realpath(base_path());
        $real = realpath($path);
        if ($real === false) {
            $parent = realpath(dirname($path));
            $real = $parent === false ? $path : $parent . DIRECTORY_SEPARATOR . basename($path);
        }

        $norm = fn (string $p): string => strtolower(rtrim(str_replace('\\', '/', $p), '/')) . '/';
        if ($repo !== false && str_starts_with($norm($real), $norm($repo))) {
            throw new \InvalidArgumentException('path_inside_repository');
        }
    }

    /** @return array<int, string> строки для консоли: только счётчики */
    public function summaryLines(array $report): array
    {
        $lines = ['status: ' . $report['status']];
        foreach ($report['tables_seen'] as $name => $t) {
            $lines[] = sprintf('table %-34s %-9s rows=%d', $name, $t['class'], $t['rows']);
        }
        foreach ($report['extract'] ?? [] as $name => $s) {
            $lines[] = sprintf('extract %-18s source=%d soft_deleted=%d emitted=%d held=%d', $name, $s['source_rows'], $s['soft_deleted'], $s['emitted'], $s['held']);
        }
        foreach ($report['legacy_urls']['counts'] ?? [] as $status => $count) {
            $lines[] = sprintf('legacy url %-28s %d', $status, $count);
        }
        if (isset($report['images']['summary'])) {
            $s = $report['images']['summary'];
            $lines[] = sprintf(
                'images: referenced=%d present=%d missing=%d case_mismatch=%d unreferenced=%d',
                $s['references_total'],
                $s['references_present'],
                $s['references_missing'],
                $s['references_case_mismatch'],
                $s['unreferenced_images'],
            );
        }

        return $lines;
    }

    // ------------------------------------------------------------------

    /**
     * Имена файлов портретов сотрудников/клиентов могут содержать персональные
     * данные: в отчётах и манифесте они заменяются на [portrait-<sha256>] и
     * каталог верхнего уровня. Владелец находит файл по sha256 / каталогу.
     * Файл, на который ссылается публичный контент, не скрывается.
     *
     * @param array<string,mixed> $inspection
     *
     * @return array<string,mixed>
     */
    private function redactPortraits(array $inspection): array
    {
        $publicEntries = [];
        foreach ($inspection['references'] as $info) {
            if (in_array('public_content', $info['groups'], true)) {
                foreach ($info['entries'] as $path) {
                    $publicEntries[$path] = true;
                }
            }
        }

        $alias = [];
        $entries = [];
        foreach ($inspection['entries'] as $path => $entry) {
            if (in_array('portrait_review', $entry['flags'], true) && ! isset($publicEntries[$path])) {
                $dir = implode('/', array_slice(explode('/', $path), 0, min(2, substr_count($path, '/'))));
                $alias[$path] = ($dir !== '' ? $dir . '/' : '') . '[portrait-' . substr((string) ($entry['sha256'] ?? md5($path)), 0, 12) . '].' . $entry['ext'];
            }
            $entries[$alias[$path] ?? $path] = $entry;
        }

        $references = [];
        foreach ($inspection['references'] as $ref => $info) {
            $info['entries'] = array_map(fn ($p) => $alias[$p] ?? $p, $info['entries']);
            if ($info['groups'] === ['staff_portrait'] || array_intersect($info['entries'], $alias) !== []) {
                $ref = '[portrait-ref-' . substr(md5($ref), 0, 12) . ']';
                unset($info['public_path']);
            }
            $references[$ref] = $info;
        }
        ksort($entries);
        ksort($references);

        return ['entries' => $entries, 'references' => $references] + $inspection;
    }

    /**
     * @param array<string,mixed> $inspection
     * @param array<int,array<string,mixed>> $refs
     *
     * @return array<string,mixed>
     */
    private function imageSection(array $inspection, array $refs): array
    {
        $references = $inspection['references'];
        $missing = $caseMismatch = $ambiguous = $suspiciousPresent = [];
        $missingByDirectory = [];

        foreach ($references as $ref => $info) {
            match ($info['status']) {
                'missing' => $missing[$ref] = $info['used_by'],
                'case_mismatch' => $caseMismatch[$ref] = $info['entries'],
                'ambiguous' => $ambiguous[$ref] = $info['entries'],
                default => null,
            };
            if ($info['status'] === 'present' && ($info['archive_status'] ?? 'image') !== 'image') {
                $suspiciousPresent[$ref] = $info['archive_status'];
            }
            if ($info['status'] === 'missing') {
                $dir = str_contains($ref, '/') ? dirname($ref) : '(root)';
                $missingByDirectory[$dir] = ($missingByDirectory[$dir] ?? 0) + 1;
            }
        }
        ksort($missingByDirectory);

        $problem = fn (string $status, string $flag) => array_keys(array_filter(
            $inspection['entries'],
            fn ($e) => $e['status'] === $status || in_array($flag, $e['flags'], true)
        ));

        return [
            'summary' => $inspection['summary'],
            'missing_references' => $missing,
            'missing_by_directory' => $missingByDirectory,
            'case_mismatch_references' => $caseMismatch,
            'ambiguous_references' => $ambiguous,
            'present_but_not_publishable' => $suspiciousPresent,
            'large_variants' => $this->largeVariants($refs, $references),
            'suspicious_entries' => $problem('suspicious', 'duplicate_name'),
            'unsafe_entries' => $problem('unsafe_path', ''),
            'editable_sources_not_publishable' => $problem('editable_source', ''),
            'portrait_review' => $problem('', 'portrait_review'),
        ];
    }

    /**
     * Записи, у которых большой вариант не найден: страница выводит image_large ?: image_small,
     * поэтому битая ссылка на large даёт битый <img> при исправной small. Данные не меняются.
     *
     * @param array<int,array<string,mixed>> $refs
     * @param array<string,array<string,mixed>> $references
     *
     * @return array<string,mixed>
     */
    private function largeVariants(array $refs, array $references): array
    {
        $rows = [];
        foreach ($refs as $r) {
            if ($r['group'] !== 'public_content' || ! in_array($r['column'], ['image_large', 'image_small'], true)) {
                continue;
            }
            $key = $r['table'] . '|' . ($r['slug'] ?? '');
            $rows[$key][$r['column']] = LegacyImageArchiveInspector::normalizeRef($r['ref']);
            $rows[$key]['table'] = $r['table'];
            $rows[$key]['slug'] = $r['slug'];
        }

        $affected = [];
        foreach ($rows as $row) {
            $large = $row['image_large'] ?? null;
            if ($large === null || ! isset($references[$large]) || $references[$large]['status'] === 'present') {
                continue;
            }
            $small = $row['image_small'] ?? null;
            $smallInfo = $small !== null ? ($references[$small] ?? null) : null;
            $affected[] = [
                'table' => $row['table'],
                'slug' => $row['slug'],
                'large_ref' => $large,
                'large_status' => $references[$large]['status'],
                'small_ref' => $small,
                'small_status' => $smallInfo['status'] ?? null,
                'small_width' => $smallInfo['width'] ?? null,
                'small_height' => $smallInfo['height'] ?? null,
            ];
        }

        return [
            'rows_with_missing_large' => count($affected),
            'with_small_present' => count(array_filter($affected, fn ($a) => $a['small_status'] === 'present')),
            'rows' => $affected,
            'note' => 'Small images are NOT upscaled. Owner decision: supply the original large photographs, or approve emptying image_large for these rows (the page then falls back to image_small).',
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $references
     * @param array<int,array<string,mixed>> $refs
     *
     * @return array<string, array<string,bool>> таблица => slug => true
     */
    private function missingBySlug(array $references, array $refs): array
    {
        $out = [];
        foreach ($refs as $r) {
            if ($r['group'] !== 'public_content' || $r['slug'] === null) {
                continue;
            }
            $normalized = LegacyImageArchiveInspector::normalizeRef($r['ref']);
            if ($normalized !== null && isset($references[$normalized]) && $references[$normalized]['status'] !== 'present') {
                $out[$r['table']][$r['slug']] = true;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $extracted
     * @param array<string,mixed> $images
     *
     * @return array<string,mixed>
     */
    private function ownerReview(array $extracted, array $images, ?array $manifest): array
    {
        $held = array_sum(array_map('count', $extracted['held']));

        $list = [
            'inventory_tables_not_published' => array_map(fn ($i) => ['rows' => $i['rows_total'], 'why' => $i['owner_review']], $extracted['inventory']),
            'held_records' => $held,
            'tables_with_schema_errors' => array_keys($extracted['schema_errors']),
            'unmapped_columns_dropped' => $extracted['unmapped_columns'],
        ];
        if (isset($images['summary'])) {
            $list['staff_or_customer_portraits'] = $images['portrait_review'];
            $list['editable_sources_not_publishable'] = $images['editable_sources_not_publishable'];
            $list['suspicious_or_unsafe_archive_entries'] = array_merge($images['suspicious_entries'], $images['unsafe_entries']);
            $list['large_variants_missing'] = $images['large_variants']['rows_with_missing_large'];
        }

        return $list;
    }

    private function json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    }

    /** Краткий отчёт: счётчики и коды, без текстов записей. */
    private function markdown(array $r): string
    {
        $md = ["# NOV-PUBLIC-04A offline extraction report", '', "Status: **{$r['status']}**", ''];
        $md[] = sprintf('Source: %d bytes, sha256 `%s`', $r['source']['bytes'], $r['source']['sha256']);
        foreach ($r['source']['header'] as $k => $v) {
            $md[] = "- {$k}: {$v}";
        }

        $md[] = '';
        $md[] = '## Tables';
        foreach ($r['tables_seen'] as $name => $t) {
            $md[] = sprintf('- `%s` — %s, %d rows', $name, $t['class'], $t['rows']);
        }
        if ($r['unacknowledged_unknown_tables'] !== []) {
            $md[] = '';
            $md[] = '**Unknown tables block all output:** ' . implode(', ', array_map(fn ($t) => "`{$t}`", $r['unacknowledged_unknown_tables']));
        }

        if (isset($r['extract'])) {
            $md[] = '';
            $md[] = '## Public content (extract)';
            foreach ($r['extract'] as $name => $s) {
                $md[] = sprintf('- `%s`: source %d, soft-deleted %d, emitted %d, held %d', $name, $s['source_rows'], $s['soft_deleted'], $s['emitted'], $s['held']);
            }
            foreach ($r['quality'] as $name => $flags) {
                foreach ($flags as $code => $q) {
                    $md[] = sprintf('  - %s: `%s` × %d', $name, $code, $q['rows']);
                }
            }
            foreach ($r['held'] as $name => $items) {
                $reasons = [];
                foreach ($items as $item) {
                    foreach ($item['reasons'] as $reason) {
                        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                    }
                }
                foreach ($reasons as $reason => $count) {
                    $md[] = sprintf('  - held %s: `%s` × %d', $name, $reason, $count);
                }
            }

            $md[] = '';
            $md[] = '## Inventory only (NOT published)';
            foreach ($r['inventory'] as $name => $i) {
                $md[] = sprintf('- `%s`: %d rows — %s', $name, $i['rows_total'], $i['owner_review']);
            }

            $md[] = '';
            $md[] = '## Legacy URL reconciliation';
            foreach ($r['legacy_urls']['counts'] as $status => $count) {
                $md[] = sprintf('- %s: %d', $status, $count);
            }

            $md[] = '';
            $md[] = '## Images';
            if (isset($r['images']['summary'])) {
                $s = $r['images']['summary'];
                $md[] = sprintf(
                    '- referenced %d, present %d, missing %d, case-mismatch %d, ambiguous %d, unreferenced images %d, editable sources %d, suspicious %d, unsafe %d',
                    $s['references_total'],
                    $s['references_present'],
                    $s['references_missing'],
                    $s['references_case_mismatch'],
                    $s['references_ambiguous'],
                    $s['unreferenced_images'],
                    $s['editable_sources'],
                    $s['suspicious'],
                    $s['unsafe_paths'],
                );
                $md[] = sprintf('- rows with missing large variant: %d (small present: %d)', $r['images']['large_variants']['rows_with_missing_large'], $r['images']['large_variants']['with_small_present']);
            } else {
                $md[] = '- ' . $r['images']['status'];
            }
        }

        return implode("\n", $md) . "\n";
    }
}
