<?php

namespace App\Services\Inquiries\Backfill;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Доказательство «ничего лишнего не изменено» (AOA-03-I2): отпечаток всех строк
 * incoming_inquiries и счётчики Booking / audit_events. Только чтение; строки хешируются
 * порциями (память ограничена), в отчёт идёт лишь SHA-256 и числа.
 */
final class InquiryBackfillSnapshot
{
    private const CHUNK = 200;

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
    }

    /** @return array{legacy_fingerprint: string, legacy_rows: int, bookings: int, audit_events: int|null} */
    public function capture(): array
    {
        $connection = DB::connection($this->qa->name);
        $context = hash_init('sha256');
        $rows = 0;

        $connection->table('incoming_inquiries')->orderBy('id')->chunkById(self::CHUNK, function ($chunk) use ($context, &$rows): void {
            foreach ($chunk as $row) {
                hash_update($context, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                $rows++;
            }
        });

        $schema = Schema::connection($this->qa->name);

        return [
            'legacy_fingerprint' => hash_final($context),
            'legacy_rows' => $rows,
            'bookings' => (int) $connection->table('bookings')->count(),
            'audit_events' => $schema->hasTable('audit_events') ? (int) $connection->table('audit_events')->count() : null,
        ];
    }
}
