<?php

namespace App\Support\Audit;

use InvalidArgumentException;

/**
 * Проверка метаданных бизнес-события (AOA-01-I1).
 *
 * Метаданные — короткая плоская карта «ключ → скаляр». Проверка отказывает
 * (исключением), а не молча обрезает: небезопасный вызов — ошибка разработчика,
 * и она должна быть заметна в тестах, а не теряться.
 *
 * Не допускается: вложенные структуры (запросы, ответы провайдеров, переписка),
 * ключи, похожие на секреты/персональные данные, e-mail, абсолютные пути,
 * токеноподобные строки, слишком длинные значения.
 */
final class AuditMetadata
{
    public const MAX_KEYS = 20;
    public const MAX_STRING_LENGTH = 500;

    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    private const FORBIDDEN_KEY_PATTERN = '/(passw|passcode|passphrase|pwd|token|secret|cookie|authoriz|credential|api_?key|session|passport|payload|request|response|header|body|message|correspond|content|card|cvv|iban)/';

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, scalar|null>
     */
    public static function validate(array $metadata): array
    {
        if (count($metadata) > self::MAX_KEYS) {
            throw new InvalidArgumentException('Audit metadata has too many keys.');
        }

        $clean = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw new InvalidArgumentException('Audit metadata keys must be snake_case strings.');
            }

            if (preg_match(self::FORBIDDEN_KEY_PATTERN, $key) === 1) {
                throw new InvalidArgumentException("Audit metadata key [{$key}] is not allowed.");
            }

            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Audit metadata value for [{$key}] must be a scalar.");
            }

            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                throw new InvalidArgumentException("Audit metadata value for [{$key}] is not finite.");
            }

            if (is_string($value)) {
                self::assertSafeString($key, $value);
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private static function assertSafeString(string $key, string $value): void
    {
        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            throw new InvalidArgumentException("Audit metadata value for [{$key}] is too long.");
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value) === 1) {
            throw new InvalidArgumentException("Audit metadata value for [{$key}] must not contain an e-mail address.");
        }

        if (preg_match('~[A-Za-z]:[\\\\/]|(?<![\w:/])/(?:[\w.\-]+/)+[\w.\-]*~', $value) === 1) {
            throw new InvalidArgumentException("Audit metadata value for [{$key}] must not contain a filesystem path.");
        }

        if (self::containsPhoneNumber($value)) {
            throw new InvalidArgumentException("Audit metadata value for [{$key}] must not contain a phone number.");
        }

        if (preg_match('/\b[A-Za-z0-9_\-]{32,}\b/', $value) === 1 || preg_match('/\bbearer\s+\S+/i', $value) === 1) {
            throw new InvalidArgumentException("Audit metadata value for [{$key}] looks like a secret.");
        }
    }

    /**
     * Телефонный номер в свободном тексте (AOA-01-I2B): 10 и более цифр подряд,
     * допустимы разделители (до двух подряд) «пробел . - ( )» и ведущий «+». Даты (2026-10-21,
     * 21.10.2026) предварительно вырезаются, чтобы не давать ложных срабатываний.
     * Это узкая защита, а не система обнаружения персональных данных.
     */
    private static function containsPhoneNumber(string $value): bool
    {
        $withoutDates = (string) preg_replace('/\b\d{4}-\d{2}-\d{2}\b|\b\d{1,2}\.\d{1,2}\.\d{2,4}\b/', ' ', $value);

        return preg_match('/(?<!\d)\+?\d(?:[\s().\-]{0,2}\d){9,}(?!\d)/', $withoutDates) === 1;
    }
}
