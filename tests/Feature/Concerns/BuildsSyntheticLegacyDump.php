<?php

namespace Tests\Feature\Concerns;

/**
 * NOV-PUBLIC-04A: синтетические выгрузки и архивы для проверки офлайн-конвейера.
 *
 * Все данные выдуманы (маркер SYNTHETIC). В «приватных» таблицах стоят
 * узнаваемые маркеры PRIVATE-MARKER-*: тесты проверяют, что они не попадают ни в
 * файл-обмен, ни в отчёты, ни в консоль. Реальных выгрузок здесь нет.
 */
trait BuildsSyntheticLegacyDump
{
    /** @var array<int,string> */
    private array $tmpPaths = [];

    protected function tearDownSynthetic(): void
    {
        foreach (array_reverse($this->tmpPaths) as $path) {
            if (is_dir($path)) {
                foreach (glob($path . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        $this->tmpPaths = [];
    }

    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'legacy04a-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->tmpPaths[] = $dir;

        return $dir;
    }

    protected function tempFile(string $contents, string $suffix = '.sql'): string
    {
        $path = $this->tempDir() . DIRECTORY_SEPARATOR . 'f' . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }

    /** Строковый литерал MySQL с экранированием. */
    protected function q(?string $value): string
    {
        return $value === null ? 'NULL' : "'" . str_replace(["\\", "'", "\n", "\r", "\0"], ["\\\\", "\\'", '\\n', '\\r', '\\0'], $value) . "'";
    }

    /**
     * @param array<int,string> $columns
     * @param array<int, array<int, ?string>> $rows
     */
    protected function table(string $name, array $columns, array $rows): string
    {
        $defs = array_map(fn ($c) => "  `{$c}` text", $columns);
        $sql = "CREATE TABLE `{$name}` (\n" . implode(",\n", $defs) . ",\n  PRIMARY KEY (`{$columns[0]}`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";

        if ($rows !== []) {
            $tuples = array_map(fn ($row) => '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) ? (string) $v : $this->q($v)), $row)) . ')', $rows);
            $sql .= "INSERT INTO `{$name}` (`" . implode('`, `', $columns) . "`) VALUES\n" . implode(",\n", $tuples) . ";\n\n";
        }

        return $sql;
    }

    protected function dumpHeader(): string
    {
        return "-- phpMyAdmin SQL Dump\n-- version SYNTHETIC\n-- Generation Time: Jun 24, 2026 at 04:45 PM\n-- Server version: 5.7.0-synthetic\n\n"
            . "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n\n";
    }

    protected function dumpFooter(): string
    {
        return "COMMIT;\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    }

    /** @return array<int,string> */
    protected function countryColumns(): array
    {
        return ['id', 'title', 'slug', 'category', 'description', 'image_small', 'image_large', 'documents_url', 'created_at', 'updated_at', 'deleted_at'];
    }

    /** @return array<int, ?string> */
    protected function country(string $slug, array $over = []): array
    {
        $base = [
            'id' => '1', 'title' => "SYNTHETIC {$slug}", 'slug' => $slug, 'category' => 'Азия',
            'description' => "<p>SYNTHETIC описание {$slug}.</p>",
            'image_small' => "img/countries_image/{$slug}.png", 'image_large' => "img/countries_image/large/{$slug}.png",
            'documents_url' => null, 'created_at' => '2024-05-01 10:00:00', 'updated_at' => '2024-05-02 10:00:00', 'deleted_at' => null,
        ];

        return array_values(array_merge($base, $over));
    }

    /** Полная синтетическая выгрузка: публичные, inventory, приватные таблицы. */
    protected function fullDump(string $extra = ''): string
    {
        return $this->dumpHeader()
            . $this->table('countries_images', $this->countryColumns(), [
                $this->country('egipet', ['id' => '1']),
                $this->country('kipr', ['id' => '2', 'image_large' => 'img/countries_image/large/kipr.png']),
                $this->country('removed-country', ['id' => '3', 'deleted_at' => '2025-01-01 00:00:00']),
            ])
            . $this->table('destination_images', ['id', 'title', 'slug', 'description', 'image_small', 'image_large', 'created_at', 'updated_at', 'deleted_at'], [
                ['1', 'SYNTHETIC Морские круизы', 'morskie-kruizy', '<p>SYNTHETIC круизы.</p>', 'img/destanation_image/morskie-kruizy.png', null, '2024-05-01 10:00:00', null, null],
            ])
            . $this->table('articles', ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'], [
                ['1', 'SYNTHETIC Кипр: где отдохнуть', 'kipr-gde-otdoxnut-molodezi', '<p>SYNTHETIC статья.</p><img src="/img/interesting-articles/a1.png" alt="">', null, '2024-06-01 09:00:00', null, null],
            ])
            . $this->table('news', ['id', 'title', 'slug', 'link', 'description', 'image', 'pub_date', 'deleted_at'], [
                ['1', 'SYNTHETIC новость', 'synthetic-news-1', 'https://news.example.invalid/1', '<p>SYNTHETIC</p>', 'img/news/n1.png', '2024-01-01 00:00:00', null],
                ['2', 'SYNTHETIC новость 2', 'synthetic-news-2', '', '<p>SYNTHETIC</p>', null, '2099-01-01 00:00:00', null],
            ])
            . $this->table('our_clients', ['id', 'title', 'slug', 'content', 'image', 'deleted_at'], [
                ['1', 'SYNTHETIC спецпредложение', 'synthetic-special', '<p>SYNTHETIC</p>', 'img/special/s1.png', null],
            ])
            . $this->table('best_offers', ['id', 'title', 'content', 'image'], [['1', 'SYNTHETIC', 'SYNTHETIC', 'img/special/b1.png']])
            . $this->table('partners', ['id', 'name_partner', 'logo_partner'], [['1', 'SYNTHETIC партнёр', 'img/item_partner/p1.png']])
            . $this->table('awards', ['id', 'category', 'image'], [['1', 'SYNTHETIC награда', 'img/licence/a1.png']])
            . $this->table('employees', ['id', 'name', 'position', 'tel', 'email', 'whatsapp', 'vk', 'image'], [
                ['1', 'PRIVATE-MARKER-STAFF-NAME', 'Менеджер', 'PRIVATE-MARKER-STAFF-TEL', 'PRIVATE-MARKER-STAFF-EMAIL', 'x', 'y', 'img/personal/PRIVATE-MARKER-portrait.png'],
            ])
            . $this->table('reviews', ['id', 'name', 'text', 'image', 'email'], [
                ['1', 'PRIVATE-MARKER-REVIEWER', 'PRIVATE-MARKER-REVIEW-TEXT', 'img/reviews/PRIVATE-MARKER-avatar.png', 'PRIVATE-MARKER-REVIEW-EMAIL'],
            ])
            . $this->table('users', ['id', 'name', 'email', 'password', 'phone'], [
                ['1', 'PRIVATE-MARKER-USER-NAME', 'PRIVATE-MARKER-USER-EMAIL', 'PRIVATE-MARKER-HASH', 'PRIVATE-MARKER-PHONE'],
            ])
            . $this->table('password_resets', ['email', 'token'], [['PRIVATE-MARKER-RESET-EMAIL', 'PRIVATE-MARKER-RESET-TOKEN']])
            . $this->table('personal_access_tokens', ['id', 'token'], [['1', 'PRIVATE-MARKER-API-TOKEN']])
            . $this->table('role_user', ['user_id', 'role_id'], [['1', '1']])
            . $this->table('notifications', ['id', 'data'], [['n1', 'PRIVATE-MARKER-NOTIFICATION-BODY']])
            . $extra
            . $this->dumpFooter();
    }

    /** Все маркеры приватных значений (не должны встречаться нигде в результате). */
    protected function privateMarkers(): array
    {
        return [
            'PRIVATE-MARKER-STAFF-NAME', 'PRIVATE-MARKER-STAFF-TEL', 'PRIVATE-MARKER-STAFF-EMAIL', 'PRIVATE-MARKER-REVIEWER',
            'PRIVATE-MARKER-REVIEW-TEXT', 'PRIVATE-MARKER-REVIEW-EMAIL', 'PRIVATE-MARKER-USER-NAME', 'PRIVATE-MARKER-USER-EMAIL',
            'PRIVATE-MARKER-HASH', 'PRIVATE-MARKER-PHONE', 'PRIVATE-MARKER-RESET-EMAIL', 'PRIVATE-MARKER-RESET-TOKEN',
            'PRIVATE-MARKER-API-TOKEN', 'PRIVATE-MARKER-NOTIFICATION-BODY',
        ];
    }

    // ------------------------------------------------------------------ images

    /** Валидный PNG заданных размеров (оттенки серого, без GD). */
    protected function png(int $width, int $height): string
    {
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00" . str_repeat("\x80", $width);
        }
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0))
            . $chunk('IDAT', gzcompress($raw))
            . $chunk('IEND', '');
    }

    /** @param array<string,string> $files путь внутри архива => содержимое */
    protected function makeZip(array $files, ?callable $tweak = null): string
    {
        $path = $this->tempDir() . DIRECTORY_SEPARATOR . 'img.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $data) {
            $zip->addFromString($name, $data);
        }
        if ($tweak !== null) {
            $tweak($zip);
        }
        $zip->close();

        return $path;
    }
}
