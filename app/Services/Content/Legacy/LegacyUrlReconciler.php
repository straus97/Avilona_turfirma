<?php

namespace App\Services\Content\Legacy;

use App\Services\Seo\LegacyUrlClassifier;

/**
 * NOV-PUBLIC-04A: сопоставление исторических URL (resources/seo/legacy-sitemap-paths.txt)
 * с восстановленными записями. Только отчёт: редиректы не создаются, отсутствующие
 * страницы никуда не перенаправляются, числовые id не переводятся в slug.
 *
 * Статусы:
 *   exact_match                 slug восстановлен и пройдёт импорт: URL сохраняется без изменений
 *   exact_match_image_missing   то же, но изображение записи не найдено в архиве
 *   published_content_missing   маршрут есть, записи с таким slug в выгрузке нет
 *   held_by_quality_gate        запись найдена, но удержана проверками (см. held)
 *   unresolved_mapping          соответствие не доказано (id, регистр slug, таблицы вне среза, нет маршрута)
 *   static_or_list_page         страница не зависит от восстановленных записей
 */
final class LegacyUrlReconciler
{
    private const FAMILIES = [
        'countries.show_countries_image' => 'countries_images',
        'destinations.show_destinations_image' => 'destination_images',
        'helpful_information.show_interesting_news' => 'articles',
    ];

    /** Детальные страницы таблиц, которые в этом срезе только учитываются. */
    private const OUT_OF_SCOPE = [
        'helpful_news_id.index' => 'news',
        'helpful_information.show_special' => 'our_clients',
    ];

    private const PAGE_CLASSES = [
        'supported_page', 'supported_list', 'supported_pagination', 'redirect_first_page', 'page_param_ignored', 'tracking_param',
    ];

    public function __construct(private readonly LegacyUrlClassifier $classifier)
    {
    }

    /** Класс LegacyUrlClassifier для пути (используется проверкой устаревших ссылок). */
    public function classify(string $path): string
    {
        return $this->classifier->classify($path)['class'];
    }

    /** @return array<int,string> */
    public static function readPaths(string $file): array
    {
        $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

        return $lines === false ? [] : array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== '' && $l[0] !== '#'));
    }

    /**
     * @param array<int,string> $legacyPaths
     * @param array<string, array<int,string>> $emitted таблица => slug записей, попавших в файл-обмен
     * @param array<string, array<int,string>> $held таблица => slug удержанных записей
     * @param array<string, array<string,bool>> $imageMissing таблица => slug => true (изображение не найдено)
     * @param array<int,string> $tablesPresent таблицы, найденные в выгрузке
     *
     * @return array<string,mixed>
     */
    public function reconcile(array $legacyPaths, array $emitted, array $held, array $imageMissing, array $tablesPresent): array
    {
        $rows = [];
        $counts = [];
        $seenSlugs = [];

        foreach ($legacyPaths as $legacy) {
            $c = $this->classifier->classify($legacy);
            $route = $c['route'];
            $reason = null;

            if (isset(self::FAMILIES[$route])) {
                $table = self::FAMILIES[$route];
                $slug = (string) substr((string) strrchr($c['path'], '/'), 1);

                if ($c['class'] === 'legacy_numeric_id') {
                    [$status, $reason] = ['unresolved_mapping', 'legacy_numeric_id (no id-to-slug evidence; never redirected)'];
                } elseif (in_array($slug, $emitted[$table] ?? [], true)) {
                    $seenSlugs[$table][$slug] = true;
                    $status = ! empty($imageMissing[$table][$slug]) ? 'exact_match_image_missing' : 'exact_match';
                } elseif (in_array($slug, $held[$table] ?? [], true)) {
                    $status = 'held_by_quality_gate';
                } elseif ($this->foldedIn($slug, array_merge($emitted[$table] ?? [], $held[$table] ?? []))) {
                    [$status, $reason] = ['unresolved_mapping', 'slug_differs_only_by_case'];
                } else {
                    $status = 'published_content_missing';
                    $reason = in_array($table, $tablesPresent, true) ? null : "table {$table} absent from the dump";
                }
            } elseif (isset(self::OUT_OF_SCOPE[$route])) {
                [$status, $reason] = ['unresolved_mapping', 'table_not_in_this_slice:' . self::OUT_OF_SCOPE[$route]];
            } elseif (in_array($c['class'], self::PAGE_CLASSES, true)) {
                $status = 'static_or_list_page';
            } else {
                [$status, $reason] = ['unresolved_mapping', $c['class']];
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $rows[] = array_filter(['path' => $legacy, 'status' => $status, 'reason' => $reason], fn ($v) => $v !== null);
        }
        ksort($counts);

        $newOnly = [];
        foreach ($emitted as $table => $slugs) {
            $newOnly[$table] = count(array_filter($slugs, fn ($s) => ! isset($seenSlugs[$table][$s])));
        }

        return [
            'legacy_paths' => count($legacyPaths),
            'counts' => $counts,
            'rows' => $rows,
            'recovered_slugs_without_legacy_url' => $newOnly,
        ];
    }

    /** @param array<int,string> $slugs */
    private function foldedIn(string $slug, array $slugs): bool
    {
        $needle = mb_strtolower($slug);
        foreach ($slugs as $candidate) {
            if (mb_strtolower($candidate) === $needle) {
                return true;
            }
        }

        return false;
    }
}
