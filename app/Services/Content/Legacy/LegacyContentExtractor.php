<?php

namespace App\Services\Content\Legacy;

use App\Services\Content\PublicContentImporter;
use Illuminate\Support\Carbon;

/**
 * NOV-PUBLIC-04A: преобразование исторической выгрузки в файл-обмен
 * avilona-public-content/1 — только для таблиц из строгого списка
 * (articles, countries_images, destination_images).
 *
 * Принципы:
 *   - таблицы inventory только учитываются, в публичный файл не попадают;
 *   - приватные/служебные таблицы не читаются (значения не материализуются);
 *   - неизвестная таблица блокирует весь вывод, пока владелец не назвал её явно;
 *   - ничего не придумывается и не переписывается: slug, заголовки, HTML и
 *     ссылки на изображения переносятся как есть; запись, которую импортёр бы
 *     отверг, не правится, а «удерживается» с кодом причины;
 *   - в отчёты попадают счётчики, коды и координаты записей (таблица, номер
 *     строки, slug), но не тексты и не значения из неразрешённых таблиц;
 *   - к базе данных обращений нет вообще.
 */
final class LegacyContentExtractor
{
    private const TIMESTAMPS = ['created_at', 'updated_at'];

    /** Столбцы, которые известны и намеренно не переносятся (id пересоздаётся, deleted_at отфильтровывает запись). */
    private const KNOWN_DROPPED = ['id', 'deleted_at'];

    /** Те же правила, что у PublicContentImporter (slug и длина строки). */
    private const SLUG_PATTERN = '#^[^\s/?\#\\\\%.][^\s/?\#\\\\%]{0,190}$#u';

    private const STRING_MAX = 255;

    private const MAX_COORDINATES = 500;

    /**
     * NOV-PUBLIC-04B: NOT a document URL, а путь к каталогу (директория без файла), который
     * старый сайт хранил как «заглушку» documents_url. Тот же известный шаблон исторически
     * уже превращался в NULL при переносе в v4 (ImportLegacyDataToV4::DOCUMENTS_URL_KNOWN_NULL).
     * Нормализация включается ТОЛЬКО явной опцией и сравнивает значение целиком и точно:
     * любое другое значение (в том числе реальный URL) переносится/удерживается как раньше.
     */
    public const DOCUMENTS_URL_DIRECTORY_PLACEHOLDERS = ['/documents/countries/'];

    public const OPTION_NORMALIZE_DOCUMENTS_URL = 'normalize_documents_url';

    private const INVENTORY_REVIEW = [
        'news' => 'RSS-sourced: freshness, attribution and redistribution rights need owner review',
        'employees' => 'staff personal data and portraits: owner approval required before any publication',
        'reviews' => 'customer reviews: consent evidence and owner approval required; values were not read',
        'our_clients' => 'special offers: owner review of prices/validity before publication',
        'best_offers' => 'home-page offers: owner review of prices/validity before publication',
        'partners' => 'partner logos/names: owner confirmation of permission to show',
        'awards' => 'award images: owner confirmation',
    ];

    public function __construct(
        private readonly LegacySqlDumpParser $parser,
        private readonly LegacyContentChecks $checks,
    ) {
    }

    /**
     * @param array<int,string> $acknowledgedUnknown таблицы, которые владелец явно исключил из обработки
     * @param array<string,bool> $options self::OPTION_* => включено
     *
     * @return array<string,mixed>
     */
    public function extract(string $sql, array $acknowledgedUnknown = [], array $options = []): array
    {
        $parsed = $this->parser->parse($sql, fn (string $table): ?array => LegacyTablePolicy::columnsToRead($table));

        $seen = [];
        $unknown = [];
        foreach ($parsed['tables'] as $name => $table) {
            $class = LegacyTablePolicy::classify($name);
            $seen[$name] = ['class' => $class, 'rows' => $table['row_count']];
            if ($class === 'unknown') {
                $seen[$name]['columns'] = $table['columns'];
                $unknown[] = $name;
            }
        }
        ksort($seen);
        sort($unknown);

        $ack = array_values(array_unique(array_map('strtolower', $acknowledgedUnknown)));
        $unacknowledged = array_values(array_diff($unknown, $ack));

        $report = [
            'header' => $parsed['header'],
            'statement_kinds' => $parsed['kinds'],
            'tables_seen' => $seen,
            'tables_absent' => array_values(array_diff(LegacyTablePolicy::EXTRACT, array_keys($parsed['tables']))),
            'unknown_tables' => $unknown,
            'unacknowledged_unknown_tables' => $unacknowledged,
            'rejected_acknowledgements' => array_values(array_diff($ack, $unknown)),
            'blocked' => $unacknowledged !== [],
        ];

        if ($report['blocked']) {
            return $report + ['content' => null];
        }

        $content = [];
        $tables = [];
        $held = [];
        $schemaErrors = [];
        $unmapped = [];
        $quality = [];
        $refs = [];
        $slugs = ['emitted' => [], 'held' => []];
        $normalizations = [];

        foreach (LegacyTablePolicy::EXTRACT as $name) {
            if (! isset($parsed['tables'][$name])) {
                continue;
            }
            $result = $this->processTable($name, $parsed['tables'][$name], $options);
            foreach ($result['normalizations'] as $item) {
                $normalizations[] = $item;
            }

            if ($result['schema_error'] !== null) {
                $schemaErrors[$name] = $result['schema_error'];
            }
            if ($result['unmapped'] !== []) {
                $unmapped[$name] = $result['unmapped'];
            }
            if ($result['rows'] !== []) {
                $content[$name] = $result['rows'];
            }
            $tables[$name] = $result['stats'];
            $held[$name] = $result['held'];
            $quality[$name] = $result['quality'];
            $refs = array_merge($refs, $result['refs']);
            $slugs['emitted'][$name] = $result['emitted_slugs'];
            $slugs['held'][$name] = $result['held_slugs'];
        }

        [$inventory, $inventoryRefs] = $this->inventory($parsed['tables']);

        return $report + [
            'content' => ['format' => PublicContentImporter::FORMAT, 'tables' => $content],
            'extract' => $tables,
            'held' => $held,
            'schema_errors' => $schemaErrors,
            'unmapped_columns' => $unmapped,
            'quality' => $quality,
            'inventory' => $inventory,
            'image_refs' => array_merge($refs, $inventoryRefs),
            'slugs' => $slugs,
            'normalizations' => $normalizations,
        ];
    }

    /**
     * @param array{columns: array<int,string>, row_count: int, rows: array<int, array<string,?string>>} $table
     * @param array<string,bool> $options
     *
     * @return array<string,mixed>
     */
    private function processTable(string $name, array $table, array $options = []): array
    {
        $schema = PublicContentImporter::SCHEMA[$name];
        $columns = $table['columns'];
        $known = array_merge($schema['required'], $schema['optional'], self::TIMESTAMPS, self::KNOWN_DROPPED);

        $missing = array_values(array_diff($schema['required'], $columns));
        $unmapped = array_values(array_diff($columns, $known));
        $stats = ['source_rows' => $table['row_count'], 'soft_deleted' => 0, 'emitted' => 0, 'held' => 0, 'zero_date_omitted' => 0];

        $blank = ['schema_error' => null, 'unmapped' => $unmapped, 'rows' => [], 'stats' => $stats, 'held' => [],
            'quality' => [], 'refs' => [], 'emitted_slugs' => [], 'held_slugs' => [], 'normalizations' => []];
        $normalizations = [];
        $normalizeDocs = ($options[self::OPTION_NORMALIZE_DOCUMENTS_URL] ?? false) === true;

        if ($missing !== []) {
            return ['schema_error' => ['missing_required_columns' => $missing]] + $blank;
        }

        $candidates = [];
        $held = [];
        $quality = [];

        foreach ($table['rows'] as $k => $source) {
            $ordinal = $k + 1;

            if ($this->isDeleted($source['deleted_at'] ?? null)) {
                $stats['soft_deleted']++;

                continue;
            }

            $row = [];
            $reasons = [];
            $flags = [];
            $refs = [];
            $pendingNormalizations = [];

            foreach (array_merge($schema['required'], $schema['optional']) as $col) {
                $value = $source[$col] ?? null;
                $required = in_array($col, $schema['required'], true);

                if ($value === null || ($required ? trim($value) === '' : $value === '')) {
                    if ($required) {
                        $reasons[] = "required_blank:{$col}";
                    }

                    continue;
                }
                if (! mb_check_encoding($value, 'UTF-8')) {
                    $reasons[] = "invalid_utf8:{$col}";

                    continue;
                }

                if (in_array($col, $schema['html'], true)) {
                    $row[$col] = $value;
                    $checked = $this->checks->html($value);
                    $flags = array_merge($flags, $checked['flags']);
                    foreach ($checked['images'] as $src) {
                        $refs[] = ['column' => "{$col}:img", 'ref' => $src];
                    }

                    continue;
                }

                $value = trim($value);
                if ($normalizeDocs && $col === 'documents_url' && in_array($value, self::DOCUMENTS_URL_DIRECTORY_PLACEHOLDERS, true)) {
                    // Поле опциональное (PublicContentImporter::SCHEMA): пустое значение допустимо и
                    // честно говорит «документа нет». Внешний URL не подставляется.
                    $pendingNormalizations[] = [
                        'table' => $name,
                        'column' => 'documents_url',
                        'rule' => 'documents_url_directory_placeholder_to_null',
                        'source_value' => $value,
                        'output_value' => null,
                        'rationale' => 'Value is a directory path, not a document URL; documents_url is optional in avilona-public-content/1; no replacement URL is invented.',
                    ];

                    continue;
                }
                if (mb_strlen($value) > self::STRING_MAX) {
                    $reasons[] = "too_long:{$col}";

                    continue;
                }
                $row[$col] = $value;

                if (in_array($col, $schema['images'], true)) {
                    if (! $this->imageRefValid($value)) {
                        $reasons[] = "invalid_image_ref:{$col}";
                    } else {
                        $refs[] = ['column' => $col, 'ref' => $value];
                    }
                }
                if ($col === 'documents_url' && preg_match('#^https://\S+$#i', $value) !== 1) {
                    $reasons[] = 'invalid_documents_url';
                }
                if ($col === 'title') {
                    $flags = array_merge($flags, $this->checks->title($value));
                }
            }

            if (isset($row['slug']) && preg_match(self::SLUG_PATTERN, $row['slug']) !== 1) {
                $reasons[] = 'invalid_slug';
            }

            foreach (self::TIMESTAMPS as $col) {
                $value = $source[$col] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (str_starts_with($value, '0000-00-00')) {
                    $stats['zero_date_omitted']++;

                    continue;
                }
                if (! $this->validTimestamp($value)) {
                    $reasons[] = "invalid_timestamp:{$col}";

                    continue;
                }
                $row[$col] = $value;
            }
            if (! isset($row['created_at'])) {
                $flags['missing_publication_date'] = true;
            }

            $slug = isset($row['slug']) && preg_match(self::SLUG_PATTERN, $row['slug']) === 1 ? $row['slug'] : null;

            if ($reasons !== []) {
                $held[] = ['ordinal' => $ordinal, 'slug' => $slug, 'reasons' => $reasons];

                continue;
            }

            foreach (array_keys($flags) as $code) {
                $quality[$code]['rows'] = ($quality[$code]['rows'] ?? 0) + 1;
                if (count($quality[$code]['coordinates'] ?? []) < self::MAX_COORDINATES) {
                    $quality[$code]['coordinates'][] = $slug ?? "#{$ordinal}";
                }
                if (is_array($flags[$code]) && ! isset($quality[$code]['detail'])) {
                    $quality[$code]['detail'] = $flags[$code];
                }
            }

            $candidates[] = ['ordinal' => $ordinal, 'slug' => $slug, 'row' => $row, 'refs' => $refs, 'normalizations' => $pendingNormalizations];
        }

        // Повтор slug (без учёта регистра: на MySQL уникальный индекс регистронезависим) — выбирать, какую
        // из записей оставить, редакционное решение; удерживаются ВСЕ записи группы.
        $groups = [];
        foreach ($candidates as $idx => $c) {
            $groups[mb_strtolower((string) $c['slug'])][] = $idx;
        }
        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            foreach ($indexes as $idx) {
                $held[] = ['ordinal' => $candidates[$idx]['ordinal'], 'slug' => $candidates[$idx]['slug'], 'reasons' => ['duplicate_slug']];
                unset($candidates[$idx]);
            }
        }
        usort($held, fn ($a, $b) => $a['ordinal'] <=> $b['ordinal']);

        $rows = [];
        $refsOut = [];
        $emitted = [];
        foreach ($candidates as $c) {
            $rows[] = $c['row'];
            $emitted[] = $c['slug'];
            foreach ($c['normalizations'] as $n) {
                $normalizations[] = ['ordinal' => $c['ordinal'], 'slug' => $c['slug']] + $n;
            }
            foreach ($c['refs'] as $ref) {
                $refsOut[] = ['group' => 'public_content', 'table' => $name, 'column' => $ref['column'], 'slug' => $c['slug'], 'ref' => $ref['ref']];
            }
        }

        $stats['emitted'] = count($rows);
        $stats['held'] = count($held);

        return [
            'schema_error' => null,
            'unmapped' => $unmapped,
            'rows' => $rows,
            'stats' => $stats,
            'held' => $held,
            'quality' => $quality,
            'refs' => $refsOut,
            'emitted_slugs' => $emitted,
            'held_slugs' => array_values(array_filter(array_column($held, 'slug'))),
            'normalizations' => $normalizations,
        ];
    }

    /**
     * Сводка по таблицам «только учёт». Значения читаются лишь для перечисленных
     * в LegacyTablePolicy::INVENTORY столбцов; тексты в отчёт не попадают.
     *
     * @param array<string, array{columns: array<int,string>, row_count: int, rows: array<int, array<string,?string>>}> $tables
     *
     * @return array{0: array<string,mixed>, 1: array<int,array<string,mixed>>}
     */
    private function inventory(array $tables): array
    {
        $out = [];
        $refs = [];
        $cutoff = Carbon::now()->subYear();

        foreach (LegacyTablePolicy::INVENTORY as $name => $cols) {
            if (! isset($tables[$name])) {
                continue;
            }
            $table = $tables[$name];
            $entry = [
                'rows_total' => $table['row_count'],
                'publish_status' => 'NOT_PUBLISHED_PENDING_OWNER_DECISION',
                'owner_review' => self::INVENTORY_REVIEW[$name],
            ];

            if ($cols !== null) {
                $active = array_values(array_filter($table['rows'], fn ($r) => ! $this->isDeleted($r['deleted_at'] ?? null)));
                $entry['soft_deleted'] = $table['row_count'] - count($active);
                $entry['rows_active'] = count($active);
                $group = $name === 'employees' ? 'staff_portrait' : 'unpublished_inventory';

                foreach ($active as $row) {
                    foreach (['image', 'logo_partner'] as $col) {
                        $value = isset($row[$col]) ? trim($row[$col]) : '';
                        if ($value !== '' && ! str_starts_with(strtolower($value), 'https://')) {
                            $refs[] = ['group' => $group, 'table' => $name, 'column' => $col, 'slug' => $row['slug'] ?? null, 'ref' => $value];
                        }
                    }
                }
                $entry['rows_with_image'] = count(array_filter($active, fn ($r) => trim((string) ($r['image'] ?? $r['logo_partner'] ?? '')) !== ''));
            }

            if ($name === 'news') {
                $entry += $this->newsSummary($table['rows'], $cutoff);
            }

            $out[$name] = $entry;
        }

        return [$out, $refs];
    }

    /**
     * @param array<int, array<string,?string>> $rows
     *
     * @return array<string,mixed>
     */
    private function newsSummary(array $rows, Carbon $cutoff): array
    {
        $active = array_filter($rows, fn ($r) => ! $this->isDeleted($r['deleted_at'] ?? null));
        $noLink = $old = $noDate = 0;
        $hosts = [];
        $min = $max = null;

        foreach ($active as $row) {
            $link = trim((string) ($row['link'] ?? ''));
            if ($link === '') {
                $noLink++;
            } elseif (($host = parse_url($link, PHP_URL_HOST)) !== null && $host !== false) {
                $host = strtolower($host);
                $hosts[$host] = ($hosts[$host] ?? 0) + 1;
            }

            $date = trim((string) ($row['pub_date'] ?? ''));
            if ($date === '' || str_starts_with($date, '0000-00-00')) {
                $noDate++;

                continue;
            }
            try {
                $time = (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->getTimestamp();
            } catch (\Exception) {
                $noDate++;

                continue;
            }
            $min = $min === null ? $time : min($min, $time);
            $max = $max === null ? $time : max($max, $time);
            if ($time < $cutoff->getTimestamp()) {
                $old++;
            }
        }
        arsort($hosts);

        return [
            'without_source_link' => $noLink,
            'without_pub_date' => $noDate,
            'older_than_12_months' => $old,
            'pub_date_min' => $min === null ? null : gmdate('Y-m-d', $min),
            'pub_date_max' => $max === null ? null : gmdate('Y-m-d', $max),
            'source_hosts' => array_slice($hosts, 0, 15, true),
        ];
    }

    private function isDeleted(?string $value): bool
    {
        return $value !== null && $value !== '' && ! str_starts_with($value, '0000-00-00');
    }

    /** То же правило, что PublicContentImporter::checkImage(). */
    private function imageRefValid(string $value): bool
    {
        if (preg_match('#^https://\S+$#i', $value) === 1) {
            return true;
        }

        return preg_match('#^/?[A-Za-z0-9_\-./]+$#', $value) === 1 && ! str_contains($value, '..');
    }

    private function validTimestamp(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
