<?php

namespace App\Services\Content\Legacy;

/**
 * NOV-PUBLIC-04A: классификация таблиц исторической выгрузки. Закрыто по умолчанию.
 *
 *   extract   — публичный контент, преобразуется в avilona-public-content/1
 *   inventory — только пересчёт и учёт; НЕ публикуется без решения владельца
 *   private   — аккаунты/клиенты/служебные данные: значения никогда не читаются
 *   excluded  — известные, но не входящие в этот срез таблицы: значения не читаются
 *   unknown   — всё остальное: вывод блокируется, пока владелец не назвал таблицу явно
 */
final class LegacyTablePolicy
{
    public const EXTRACT = ['articles', 'countries_images', 'destination_images'];

    /**
     * Таблицы только для учёта => столбцы, значения которых нужны для сводки
     * (null — значения не читаются вообще, только число строк).
     */
    public const INVENTORY = [
        'news' => ['slug', 'link', 'pub_date', 'image', 'deleted_at'],
        'our_clients' => ['slug', 'image', 'deleted_at'],
        'best_offers' => ['image', 'deleted_at'],
        'partners' => ['logo_partner', 'deleted_at'],
        'awards' => ['image', 'deleted_at'],
        'employees' => ['image', 'deleted_at'],
        'reviews' => null,
    ];

    /** Аккаунты, клиенты, обращения, документы, токены, уведомления. */
    public const PRIVATE = [
        'users', 'password_resets', 'password_reset_tokens', 'personal_access_tokens', 'role_user', 'roles',
        'notifications', 'profiles', 'bookings', 'booking_documents', 'booking_trip_reminder_deliveries',
        'bonus_accounts', 'bonus_transactions', 'client_verification_requests', 'incoming_inquiries',
        'inquiries', 'inquiry_contacts', 'messages', 'review_consents', 'user_documents',
        'user_registration_consents', 'audit_events', 'sessions',
    ];

    /** Известные служебные и непубликуемые в этом срезе таблицы. */
    public const EXCLUDED = [
        'migrations', 'failed_jobs', 'jobs', 'job_batches', 'cache', 'cache_locks',
        'tours', 'tour_operators', 'destination_cities',
    ];

    public static function classify(string $table): string
    {
        $table = strtolower($table);

        return match (true) {
            in_array($table, self::EXTRACT, true) => 'extract',
            array_key_exists($table, self::INVENTORY) => 'inventory',
            in_array($table, self::PRIVATE, true) => 'private',
            in_array($table, self::EXCLUDED, true) => 'excluded',
            default => 'unknown',
        };
    }

    /** Столбцы, значения которых читает разбор: ['*'] — все, null — только счёт строк. */
    public static function columnsToRead(string $table): ?array
    {
        $table = strtolower($table);

        return match (self::classify($table)) {
            'extract' => ['*'],
            'inventory' => self::INVENTORY[$table],
            default => null,
        };
    }
}
