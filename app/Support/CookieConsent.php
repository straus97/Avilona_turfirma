<?php

namespace App\Support;

/**
 * Stage 13: единственный источник истины для нормализации согласия на
 * cookie. Используется одновременно resources/views/layouts/main.blade.php
 * (серверный гейтинг аналитики) и App\Http\Middleware\CacheResponse (ключ
 * кэша), чтобы эти два места не могли разойтись в трактовке согласия.
 */
class CookieConsent
{
    public const COOKIE_NAME = 'avilona_cookie_consent';

    public const STATE_ANALYTICS = 'analytics';
    public const STATE_NECESSARY = 'necessary';
    public const STATE_UNDECIDED = 'undecided';

    private const VALUE_ALL = 'v1_all';
    private const VALUE_NECESSARY = 'v1_necessary';

    /**
     * Приводит сырое значение cookie к одному из трёх состояний. Любое
     * отсутствующее, пустое, неизвестное или несовпадающее по версии
     * значение (например v0_all, v2_all) нормализуется в undecided.
     */
    public static function normalize(?string $rawValue): string
    {
        return match ($rawValue) {
            self::VALUE_ALL => self::STATE_ANALYTICS,
            self::VALUE_NECESSARY => self::STATE_NECESSARY,
            default => self::STATE_UNDECIDED,
        };
    }

    /**
     * true только когда сырое значение — валидный v1_all.
     */
    public static function allowsAnalytics(?string $rawValue): bool
    {
        return self::normalize($rawValue) === self::STATE_ANALYTICS;
    }

    /**
     * true для любого валидного состояния (analytics или necessary) —
     * баннер согласия не должен показываться автоматически.
     */
    public static function isValid(?string $rawValue): bool
    {
        return self::normalize($rawValue) !== self::STATE_UNDECIDED;
    }
}
