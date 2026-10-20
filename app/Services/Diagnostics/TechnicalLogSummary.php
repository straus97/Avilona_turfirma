<?php

namespace App\Services\Diagnostics;

use Carbon\CarbonImmutable;

/**
 * Безопасная сводка технического журнала приложения (AOA-01-I1, AO-04).
 *
 * Сырой текст laravel.log в веб-интерфейс не отдаётся: трассировки, контекст
 * (JSON), фрагменты SQL и значения параметров могут содержать абсолютные пути
 * сервера, адреса e-mail, токены и пароли. Сервис читает только хвост файла,
 * оставляет по каждой записи время, уровень и короткое очищенное сообщение.
 * Файл не изменяется, не усекается и не ротируется.
 */
class TechnicalLogSummary
{
    public const MAX_ENTRIES = 100;
    public const MAX_MESSAGE_LENGTH = 300;

    private const TAIL_BYTES = 512 * 1024;

    private const ENTRY_PATTERN = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.\d+)?(?:[+-]\d{2}:?\d{2})?\]\s+[\w.\-]+\.([A-Za-z]+):\s?(.*)$/';

    private const HIDDEN_PATH = '[путь скрыт]';
    private const HIDDEN_VALUE = '[скрыто]';

    /**
     * @return array{entries: list<array{time: string, level: string, message: string}>, counts: array<string, int>, available: bool}
     */
    public function summarize(?string $path = null): array
    {
        $path ??= storage_path('logs/laravel.log');

        $summary = ['entries' => [], 'counts' => [], 'available' => false];

        if (! is_file($path) || ! is_readable($path)) {
            return $summary;
        }

        $chunk = $this->readTail($path);

        if ($chunk === null) {
            return $summary;
        }

        $summary['available'] = true;

        $entries = [];

        foreach (preg_split("/\r\n|\r|\n/", $chunk) ?: [] as $line) {
            // Строки трассировки и продолжения не начинаются с метки времени — отбрасываем.
            if (preg_match(self::ENTRY_PATTERN, $line, $m) !== 1) {
                continue;
            }

            $level = strtoupper($m[2]);

            $entries[] = [
                'time' => CarbonImmutable::parse($m[1])->format('d.m.Y H:i:s'),
                'level' => $level,
                'message' => $this->sanitizeMessage($m[3]),
            ];
        }

        $entries = array_slice($entries, -self::MAX_ENTRIES);

        foreach ($entries as $entry) {
            $summary['counts'][$entry['level']] = ($summary['counts'][$entry['level']] ?? 0) + 1;
        }

        $summary['entries'] = array_reverse($entries);

        return $summary;
    }

    public function sanitizeMessage(string $raw): string
    {
        // Контекст Laravel дописывается как JSON-объект после сообщения — целиком отбрасываем.
        $text = preg_replace('/\s\{.*$/s', '', $raw) ?? '';
        $text = preg_replace('/\s\[\]\s*$/', '', $text) ?? '';

        // Фрагменты SQL и значения параметров запроса.
        $text = preg_replace('/\s*\((?:Connection|SQL):.*$/s', '', $text) ?? '';

        $text = preg_replace(
            '/\b(password|passwd|pwd|token|secret|api[_-]?key|authorization|cookie|session)\b(\s*[=:]\s*)\S+/i',
            '$1$2' . self::HIDDEN_VALUE,
            $text
        ) ?? '';
        $text = preg_replace('/\bbearer\s+\S+/i', 'Bearer ' . self::HIDDEN_VALUE, $text) ?? '';

        // Windows (в т. ч. с экранированными слэшами) и POSIX абсолютные пути.
        $text = preg_replace('~[A-Za-z]:(?:\\\\+|/)[^\s"\'<>|*?]*~', self::HIDDEN_PATH, $text) ?? '';
        $text = preg_replace('~(?<![\w:/.])/(?:[\w.\-]+/)+[\w.\-]*~', self::HIDDEN_PATH, $text) ?? '';

        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email скрыт]', $text) ?? '';
        $text = preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', self::HIDDEN_VALUE, $text) ?? '';

        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strimwidth($text, 0, self::MAX_MESSAGE_LENGTH, '…');
    }

    /** Хвост файла фиксированного размера; первая (возможно обрезанная) строка отбрасывается. */
    private function readTail(string $path): ?string
    {
        try {
            $size = filesize($path);
            $start = max(0, (int) $size - self::TAIL_BYTES);

            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return null;
            }

            fseek($handle, $start);
            $chunk = (string) stream_get_contents($handle);
            fclose($handle);
        } catch (\Throwable) {
            return null;
        }

        if ($start > 0) {
            $newline = strpos($chunk, "\n");
            $chunk = $newline === false ? '' : substr($chunk, $newline + 1);
        }

        return $chunk;
    }
}
