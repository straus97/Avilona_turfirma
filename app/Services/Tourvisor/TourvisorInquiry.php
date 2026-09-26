<?php

namespace App\Services\Tourvisor;

use Carbon\CarbonImmutable;

/**
 * Нормализованная обычная заявка Tourvisor (результат авторитетной выгрузки).
 *
 * Все поля, кроме идентичности, необязательны: набор полей взят из публичного
 * примера документации экспорта и НЕ подтверждён живым ответом. Схема будет
 * сверена после E5-A2B (см. NORMALIZER_VERSION); до этого неизвестные поля
 * остаются только в ограниченном `payload`.
 */
final class TourvisorInquiry
{
    /** Версия правил нормализации; растёт при сверке с живым payload. */
    public const NORMALIZER_VERSION = 1;

    private const MAX_PAYLOAD_KEYS = 40;
    private const MAX_PAYLOAD_VALUE_LENGTH = 1000;
    private const FORBIDDEN_PAYLOAD_KEYS = ['authkey', 'key', 'token', 'password', 'secret'];

    /**
     * @param  array<string, string>  $payload  ограниченный снимок скалярных полей ответа
     */
    public function __construct(
        public readonly TourvisorInquiryType $type,
        public readonly string $externalId,
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $comment = null,
        public readonly ?string $price = null,
        public readonly ?string $currency = null,
        public readonly ?string $operator = null,
        public readonly ?string $departureCity = null,
        public readonly ?string $country = null,
        public readonly ?string $hotel = null,
        public readonly ?CarbonImmutable $flyDate = null,
        public readonly ?int $nights = null,
        public readonly array $payload = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $order  один элемент `orders.order` из ответа экспорта
     */
    public static function fromOrderArray(TourvisorInquiryType $type, array $order): self
    {
        $id = self::text($order['id'] ?? null, 64);

        if ($id === null) {
            throw new TourvisorExportException(TourvisorExportException::MALFORMED);
        }

        return new self(
            type: $type,
            externalId: $id,
            name: self::text($order['name'] ?? null, 255),
            phone: self::text($order['phone'] ?? null, 64),
            email: self::text($order['email'] ?? null, 255),
            comment: self::text($order['comments'] ?? null, 2000),
            price: self::price($order['price'] ?? null),
            currency: self::text($order['currency'] ?? null, 8),
            operator: self::text($order['operator'] ?? null, 128),
            departureCity: self::text($order['departure'] ?? null, 128),
            country: self::text($order['country'] ?? null, 128),
            hotel: self::text($order['hotel'] ?? null, 255),
            flyDate: self::date($order['flydate'] ?? null),
            nights: self::nights($order['nights'] ?? null),
            payload: self::boundedPayload($order),
        );
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function price(mixed $value): ?string
    {
        $value = self::text($value, 32);

        if ($value === null || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            return null;
        }

        return $value;
    }

    private static function nights(mixed $value): ?int
    {
        $value = self::text($value, 8);

        return ($value !== null && ctype_digit($value) && (int) $value <= 365) ? (int) $value : null;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        $value = self::text($value, 16);

        if ($value === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!d.m.Y', $value);

        return ($date !== false && $date->format('d.m.Y') === $value) ? $date : null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, string>
     */
    private static function boundedPayload(array $order): array
    {
        $payload = [];

        foreach ($order as $key => $value) {
            if (count($payload) >= self::MAX_PAYLOAD_KEYS) {
                break;
            }

            if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_]{1,40}$/', $key)) {
                continue;
            }

            if (in_array(strtolower($key), self::FORBIDDEN_PAYLOAD_KEYS, true)) {
                continue;
            }

            $text = self::text($value, self::MAX_PAYLOAD_VALUE_LENGTH);

            if ($text !== null) {
                $payload[$key] = $text;
            }
        }

        return $payload;
    }
}
