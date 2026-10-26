<?php

namespace App\Services\Inquiries;

use Illuminate\Validation\ValidationException;

/**
 * Контракт и границы контакта клиента обращения (AOA-04-I4).
 *
 * Принимаются ровно три поля, утверждённых владельцем; любой другой ключ отклоняется целиком.
 * Значения нормализуются (обрезка, схлопывание пробелов), пустое значение = «нет». Управляющие
 * символы (в том числе NUL) и некорректный UTF-8 отклоняются. Сообщения об ошибках статичны и
 * НИКОГДА не повторяют введённое значение.
 *
 * Email, паспорт, дата рождения и адрес сюда не входят: для них нет полей.
 */
class InquiryContactData
{
    public const NAME_MAX_LENGTH = 120;
    public const PHONE_MAX_LENGTH = 20;
    public const MESSENGER_MAX_LENGTH = 120;

    public const PHONE_MIN_DIGITS = 7;
    public const PHONE_MAX_DIGITS = 15;

    public const FIELDS = ['contact_name', 'contact_phone', 'contact_messenger'];

    /**
     * @param  array<string, mixed>  $input  ровно ключи self::FIELDS (отсутствующий ключ = «нет»)
     * @return array{contact_name: ?string, contact_phone: ?string, contact_messenger: ?string}
     *
     * @throws ValidationException
     */
    public static function normalize(array $input): array
    {
        $unknown = array_diff(array_map('strval', array_keys($input)), self::FIELDS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'contact' => ['Форма содержит недопустимые поля. Сохраняются только имя, телефон и контакт в мессенджере.'],
            ]);
        }

        $errors = [];

        $name = self::text($input['contact_name'] ?? null, self::NAME_MAX_LENGTH, 'contact_name', 'Имя', $errors);
        $messenger = self::text($input['contact_messenger'] ?? null, self::MESSENGER_MAX_LENGTH, 'contact_messenger', 'Контакт в мессенджере', $errors);
        $phone = self::phone($input['contact_phone'] ?? null, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['contact_name' => $name, 'contact_phone' => $phone, 'contact_messenger' => $messenger];
    }

    /** @param array<string, list<string>> $errors */
    private static function text(mixed $value, int $max, string $key, string $label, array &$errors): ?string
    {
        $clean = self::clean($value, $key, $label, $errors);

        if ($clean === null) {
            return null;
        }

        if (mb_strlen($clean) > $max) {
            $errors[$key][] = "{$label}: не более {$max} символов.";

            return null;
        }

        return $clean;
    }

    /** @param array<string, list<string>> $errors */
    private static function phone(mixed $value, array &$errors): ?string
    {
        $clean = self::clean($value, 'contact_phone', 'Телефон', $errors);

        if ($clean === null) {
            return null;
        }

        $digits = strlen((string) preg_replace('/\D+/', '', $clean));

        if (
            mb_strlen($clean) > self::PHONE_MAX_LENGTH
            || preg_match('/^\+?[0-9()\-\s.]+$/', $clean) !== 1
            || $digits < self::PHONE_MIN_DIGITS
            || $digits > self::PHONE_MAX_DIGITS
        ) {
            $errors['contact_phone'][] = 'Телефон: от ' . self::PHONE_MIN_DIGITS . ' до ' . self::PHONE_MAX_DIGITS . ' цифр, допустимы пробелы, скобки, дефис, точка и «+» в начале; не более ' . self::PHONE_MAX_LENGTH . ' символов.';

            return null;
        }

        return $clean;
    }

    /**
     * Строка без управляющих символов, с обрезанными и схлопнутыми пробелами; пустая → null.
     *
     * @param array<string, list<string>> $errors
     */
    private static function clean(mixed $value, string $key, string $label, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            $errors[$key][] = "{$label}: укажите текст.";

            return null;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $errors[$key][] = "{$label}: недопустимые символы.";

            return null;
        }

        // Пробельные управляющие (табуляция, перевод строки) становятся пробелом; остальные Cc/Cf отклоняются.
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        if ($collapsed === null || preg_match('/[\p{Cc}\p{Cf}\x{2028}\x{2029}]/u', $collapsed) === 1) {
            $errors[$key][] = "{$label}: недопустимые символы.";

            return null;
        }

        $collapsed = trim($collapsed);

        return $collapsed === '' ? null : $collapsed;
    }
}
