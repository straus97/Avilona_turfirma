<?php

namespace App\Services\Content;

use App\Support\NewsHtmlSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * NOV-PUBLIC-02: строго публичный импорт контента из файла-обмена.
 *
 * Формат файла задаёт ПРИЛОЖЕНИЕ (docs/NOV-PUBLIC-02-...): мы не предполагаем
 * формат неизвестной исходной базы — выгрузку в этот формат готовит владелец /
 * исполнитель из одобренного публичного источника. Инструмент:
 *   - принимает только перечисленные таблицы публичного контента и только
 *     перечисленные столбцы (users, bookings, inquiries, messages, отзывы с
 *     согласиями и любые другие таблицы отвергаются целиком);
 *   - ничего не читает из других баз и не подключается к боевым источникам —
 *     единственный вход это локальный JSON-файл;
 *   - ничего не придумывает: slug обязателен там, где он есть в схеме
 *     (публичные URL не должны меняться), пустые поля не заполняются;
 *   - по умолчанию только проверяет (dry-run); запись — в одной транзакции и
 *     только в пустые целевые таблицы;
 *   - HTML-поля очищает NewsHtmlSanitizer (тот же allow-list, что на выводе).
 *
 * Отзывы намеренно НЕ входят: они требуют свидетельства согласия на публикацию
 * (review_consents) и решения владельца.
 */
class PublicContentImporter
{
    public const FORMAT = 'avilona-public-content/1';

    /**
     * table => [required[], optional[], htmlColumns[], imageColumns[], hasSlug]
     */
    public const SCHEMA = [
        'countries_images' => ['required' => ['title', 'slug', 'category', 'description'],
            'optional' => ['image_small', 'image_large', 'documents_url'], 'html' => ['description'],
            'images' => ['image_small', 'image_large'], 'slug' => true],
        'destination_images' => ['required' => ['title', 'slug', 'description'],
            'optional' => ['image_small', 'image_large'], 'html' => ['description'],
            'images' => ['image_small', 'image_large'], 'slug' => true],
        'articles' => ['required' => ['title', 'slug', 'content'],
            'optional' => ['image'], 'html' => ['content'], 'images' => ['image'], 'slug' => true],
        'news' => ['required' => ['title', 'slug', 'link', 'description'],
            'optional' => ['image', 'pub_date'], 'html' => ['description'], 'images' => ['image'], 'slug' => true],
        'our_clients' => ['required' => ['title', 'slug', 'content'],
            'optional' => ['image'], 'html' => ['content'], 'images' => ['image'], 'slug' => true],
        'employees' => ['required' => ['name', 'position', 'tel', 'email', 'whatsapp', 'vk'],
            'optional' => ['image'], 'html' => [], 'images' => ['image'], 'slug' => false],
        'awards' => ['required' => ['category'],
            'optional' => ['image'], 'html' => [], 'images' => ['image'], 'slug' => false],
        'partners' => ['required' => ['name_partner'],
            'optional' => ['logo_partner'], 'html' => [], 'images' => ['logo_partner'], 'slug' => false],
        'best_offers' => ['required' => ['title', 'content'],
            'optional' => ['image'], 'html' => ['content'], 'images' => ['image'], 'slug' => false],
    ];

    /** Метки времени сохраняются из исходника (для честных дат), но не обязательны. */
    private const TIMESTAMPS = ['created_at', 'updated_at'];

    /** Обязательные в схеме столбцы с NOT NULL, которые допускают пустую строку. */
    private const STRING_MAX = 255;

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $prepared = [];

    /**
     * @return array{ok: bool, errors: array<int,string>, warnings: array<int,string>, counts: array<string,int>}
     */
    public function validate(string $json): array
    {
        $this->errors = $this->warnings = $this->prepared = $this->counts = [];

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->result('Файл не является корректным JSON.');
        }

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT || ! is_array($data['tables'] ?? null)) {
            return $this->result('Ожидается {"format": "' . self::FORMAT . '", "tables": {...}}.');
        }

        foreach ($data['tables'] as $table => $rows) {
            if (! is_string($table) || ! isset(self::SCHEMA[$table])) {
                $this->errors[] = 'Таблица «' . (is_string($table) ? $table : '?') . '» не входит в список публичного контента.';
                continue;
            }
            if (! is_array($rows) || ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1))) {
                $this->errors[] = "{$table}: ожидается список записей.";
                continue;
            }
            $this->validateTable($table, $rows);
        }

        return $this->result();
    }

    /**
     * Запись проверенных данных. Только после успешного validate(); только в
     * пустые таблицы; одна транзакция.
     *
     * @return array<string, int> число вставленных записей по таблицам
     */
    public function commit(): array
    {
        if ($this->errors !== []) {
            throw new \LogicException('Импорт нельзя выполнять при ошибках проверки.');
        }

        return DB::transaction(function () {
            $inserted = [];

            foreach ($this->prepared as $table => $rows) {
                if ($rows === []) {
                    continue;
                }
                if (DB::table($table)->exists()) {
                    throw new \RuntimeException("Таблица {$table} не пуста: импорт только в пустые таблицы.");
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $inserted[$table] = count($rows);
            }

            return $inserted;
        });
    }

    /** @param array<int, mixed> $rows */
    private function validateTable(string $table, array $rows): void
    {
        $schema = self::SCHEMA[$table];
        $allowed = array_merge($schema['required'], $schema['optional'], self::TIMESTAMPS);
        $slugs = [];
        $out = [];
        $now = now()->toDateTimeString();

        foreach ($rows as $i => $row) {
            $where = "{$table}[{$i}]";

            if (! is_array($row)) {
                $this->errors[] = "{$where}: запись должна быть объектом.";
                continue;
            }
            if ($unknown = array_diff(array_keys($row), $allowed)) {
                $this->errors[] = "{$where}: недопустимые поля: " . implode(', ', $unknown) . '.';
                continue;
            }

            $clean = [];
            $rowOk = true;

            foreach ($schema['required'] as $col) {
                if (! isset($row[$col]) || ! is_string($row[$col]) || trim($row[$col]) === '') {
                    $this->errors[] = "{$where}: обязательное поле «{$col}» отсутствует или пусто.";
                    $rowOk = false;
                }
            }
            if (! $rowOk) {
                continue;
            }

            foreach (array_merge($schema['required'], $schema['optional']) as $col) {
                if (! array_key_exists($col, $row) || $row[$col] === null || $row[$col] === '') {
                    if (in_array($col, $schema['optional'], true)) {
                        $clean[$col] = null;
                    }
                    continue;
                }
                $value = $row[$col];
                if (! is_string($value)) {
                    $this->errors[] = "{$where}: поле «{$col}» должно быть строкой.";
                    $rowOk = false;
                    continue;
                }
                if (in_array($col, $schema['html'], true)) {
                    $clean[$col] = NewsHtmlSanitizer::sanitize($value);
                    continue;
                }
                if (mb_strlen($value) > self::STRING_MAX) {
                    $this->errors[] = "{$where}: поле «{$col}» длиннее " . self::STRING_MAX . ' символов.';
                    $rowOk = false;
                    continue;
                }
                $clean[$col] = trim($value);
            }

            if ($schema['slug'] && $rowOk) {
                $slug = $clean['slug'];
                if (! preg_match('#^[^\s/?\#\\\\%.][^\s/?\#\\\\%]{0,190}$#u', $slug)) {
                    $this->errors[] = "{$where}: недопустимый slug.";
                    $rowOk = false;
                } elseif (isset($slugs[$slug])) {
                    $this->errors[] = "{$where}: slug повторяется (также в [{$slugs[$slug]}]).";
                    $rowOk = false;
                }
                $slugs[$slug] = $i;
            }

            foreach ($schema['images'] as $col) {
                if (($clean[$col] ?? null) !== null) {
                    if (! $this->checkImage($clean[$col], "{$where}.{$col}")) {
                        $rowOk = false;
                    } elseif (! str_starts_with($clean[$col], 'https://')) {
                        // Шаблоны выводят значение как есть (src="{{ ... }}") или через asset();
                        // корневой путь «/img/...» работает в обоих случаях и на любой глубине URL,
                        // а «img/...» на странице /countries/x превратился бы в /countries/img/...
                        $clean[$col] = '/' . ltrim($clean[$col], '/');
                    }
                }
            }

            if (($clean['link'] ?? null) !== null && ! preg_match('#^https?://\S+$#i', $clean['link'])) {
                $this->errors[] = "{$where}: «link» должен быть http(s) URL.";
                $rowOk = false;
            }
            if (($clean['documents_url'] ?? null) !== null && ! preg_match('#^https://\S+$#i', $clean['documents_url'])) {
                $this->errors[] = "{$where}: «documents_url» должен быть https URL.";
                $rowOk = false;
            }
            if (isset($clean['pub_date']) && ! $this->validDate($clean['pub_date'])) {
                $this->errors[] = "{$where}: «pub_date» не является датой.";
                $rowOk = false;
            }
            foreach (self::TIMESTAMPS as $ts) {
                if (isset($row[$ts]) && ! $this->validDate((string) $row[$ts])) {
                    $this->errors[] = "{$where}: «{$ts}» не является датой.";
                    $rowOk = false;
                }
            }

            if (! $rowOk) {
                continue;
            }

            $clean['created_at'] = isset($row['created_at']) ? Carbon::parse($row['created_at'])->toDateTimeString() : $now;
            $clean['updated_at'] = isset($row['updated_at']) ? Carbon::parse($row['updated_at'])->toDateTimeString() : $now;
            $out[] = $clean;
        }

        if ($schema['slug'] && $slugs !== []) {
            $this->checkSlugCollisions($table, array_keys($slugs));
        }

        $this->prepared[$table] = $out;
        $this->counts[$table] = count($out);
    }

    private function checkImage(string $value, string $where): bool
    {
        if (preg_match('#^https://\S+$#i', $value)) {
            return true;
        }
        if (! preg_match('#^/?[A-Za-z0-9_\-./]+$#', $value) || str_contains($value, '..')) {
            $this->errors[] = "{$where}: недопустимая ссылка на изображение (разрешены https URL и пути относительно public/).";

            return false;
        }
        if (! is_file(public_path(ltrim($value, '/')))) {
            $this->warnings[] = "{$where}: файл «{$value}» не найден в public/ (страница покажет заглушку).";
        }

        return true;
    }

    private function validDate(string $value): bool
    {
        try {
            Carbon::parse($value);

            return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<int, string> $slugs */
    private function checkSlugCollisions(string $table, array $slugs): void
    {
        $existing = DB::table($table)->whereIn('slug', $slugs)->pluck('slug')->all();

        foreach ($existing as $slug) {
            $this->errors[] = "{$table}: slug «{$slug}» уже есть в целевой базе.";
        }
    }

    /**
     * @return array{ok: bool, errors: array<int,string>, warnings: array<int,string>, counts: array<string,int>}
     */
    private function result(?string $fatal = null): array
    {
        if ($fatal !== null) {
            $this->errors[] = $fatal;
        }

        return [
            'ok' => $this->errors === [],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'counts' => $this->counts,
        ];
    }
}
