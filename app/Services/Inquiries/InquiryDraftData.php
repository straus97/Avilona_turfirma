<?php

namespace App\Services\Inquiries;

use App\Models\Inquiry;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Контракт и границы черновика обращения (AOA-03-I1, остаточный риск R-D).
 *
 * Принимается патч: только известные ключи из Inquiry::DRAFT_FIELDS, лишний ключ
 * (owner, assigned_to, status, booking_id, client_user_id …) отклоняется целиком.
 * Явный null очищает поле. Кросс-проверки считаются по результату наложения патча
 * на текущий черновик. Границы согласованы с формой заявки (BookingController):
 * ночей 1–30, взрослых 1–10, детей 0–10, возраст 0–17.
 *
 * Контакты клиента, пароли, токены и паспортные данные в черновик не входят: для
 * них нет полей, а свободный текст ограничен по длине (содержимое заметок не
 * классифицируется — сотрудник отвечает за него сам).
 */
class InquiryDraftData
{
    public const NOTES_MAX_LENGTH = 2000;
    public const PREFERENCES_MAX_LENGTH = 1000;

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>  нормализованные изменения (только переданные ключи)
     *
     * @throws ValidationException
     */
    public static function validatePatch(array $patch, Inquiry $current): array
    {
        $unknown = array_diff(array_map('strval', array_keys($patch)), Inquiry::DRAFT_FIELDS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'draft' => ['Недопустимые поля черновика: ' . implode(', ', $unknown) . '.'],
            ]);
        }

        $normalized = [];
        foreach ($patch as $key => $value) {
            $normalized[$key] = self::normalizeValue($value);
        }

        $merged = array_merge(self::currentValues($current), $normalized);

        Validator::make($merged, self::rules($merged), [], self::attributes())->validate();

        $changes = [];
        foreach ($normalized as $key => $value) {
            $changes[$key] = self::castForStorage($key, $merged[$key]);
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private static function currentValues(Inquiry $inquiry): array
    {
        return [
            'destination_country' => $inquiry->destination_country,
            'destination_city' => $inquiry->destination_city,
            'departure_city' => $inquiry->departure_city,
            'date_from' => $inquiry->date_from?->toDateString(),
            'date_to' => $inquiry->date_to?->toDateString(),
            'nights_min' => $inquiry->nights_min,
            'nights_max' => $inquiry->nights_max,
            'adults' => $inquiry->adults,
            'children' => $inquiry->children,
            'children_ages' => $inquiry->children_ages,
            'preferences' => $inquiry->preferences,
            'internal_notes' => $inquiry->internal_notes,
        ];
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_array($value) && $value === []) {
            return null;
        }

        return $value;
    }

    private static function castForStorage(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($key) {
            'nights_min', 'nights_max', 'adults', 'children' => (int) $value,
            'children_ages' => array_map('intval', array_values($value)),
            default => $value,
        };
    }

    /** @return array<string, array<int, mixed>> */
    private static function rules(array $merged): array
    {
        $strictInt = static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_bool($value) || is_float($value) || is_array($value)) {
                $fail('Укажите целое число.');
            }
        };

        $children = isset($merged['children']) && is_numeric($merged['children']) ? (int) $merged['children'] : 0;

        return [
            'destination_country' => ['nullable', 'string', 'max:128'],
            'destination_city' => ['nullable', 'string', 'max:128'],
            'departure_city' => ['nullable', 'string', 'max:128'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'nights_min' => ['nullable', $strictInt, 'integer', 'between:1,30'],
            'nights_max' => ['nullable', $strictInt, 'integer', 'between:1,30', 'gte:nights_min'],
            'adults' => ['nullable', $strictInt, 'integer', 'between:1,10'],
            'children' => ['nullable', $strictInt, 'integer', 'between:0,10'],
            'children_ages' => ['nullable', 'array', 'list', 'max:10', 'size:' . $children],
            'children_ages.*' => [$strictInt, 'integer', 'between:0,17'],
            'preferences' => ['nullable', 'string', 'max:' . self::PREFERENCES_MAX_LENGTH, 'not_regex:/\x00/'],
            'internal_notes' => ['nullable', 'string', 'max:' . self::NOTES_MAX_LENGTH, 'not_regex:/\x00/'],
        ];
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'destination_country' => 'Страна',
            'destination_city' => 'Город назначения',
            'departure_city' => 'Город вылета',
            'date_from' => 'Дата начала',
            'date_to' => 'Дата окончания',
            'nights_min' => 'Ночей (от)',
            'nights_max' => 'Ночей (до)',
            'adults' => 'Взрослых',
            'children' => 'Детей',
            'children_ages' => 'Возраст детей',
            'children_ages.*' => 'Возраст ребёнка',
            'preferences' => 'Пожелания',
            'internal_notes' => 'Заметки',
        ];
    }
}
