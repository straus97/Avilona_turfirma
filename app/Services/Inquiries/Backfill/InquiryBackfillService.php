<?php

namespace App\Services\Inquiries\Backfill;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Контролируемый перенос IncomingInquiry → Inquiry (AOA-03-I2), только на проверенной
 * одноразовой QA-базе (VerifiedQaConnection). Ключ идентичности — incoming_inquiry_id.
 *
 * Свойства:
 *  - dry-run по умолчанию: ничего не пишется;
 *  - apply создаёт только недостающие Inquiry; существующие не изменяются и не заменяются;
 *  - одна запись = одна короткая транзакция (источник блокируется FOR UPDATE, переклассифицируется
 *    под блокировкой, затем один INSERT) — частично созданных Inquiry не остаётся;
 *  - идемпотентность и возобновление: повтор пропускает уже перенесённое (MATCHED → ALREADY_PRESENT);
 *    прерывание оставляет только полностью созданные записи;
 *  - гонка двух процессов решается UNIQUE (incoming_inquiry_id, booking_id) и повторной классификацией;
 *  - память ограничена размером порции (chunkById), в отчёте только счётчики и числовые id;
 *  - IncomingInquiry, Booking, назначения и audit_events не затрагиваются: перенос не создаёт Booking
 *    и не пишет бизнес-событий о действиях, которых история не фиксировала.
 *
 * Непредвиденное исключение (потеря соединения и т.п.) прерывает запуск целиком: уже
 * зафиксированные записи остаются корректными, повторный запуск продолжает с остатка.
 */
final class InquiryBackfillService
{
    public const DEFAULT_CHUNK = 200;

    private readonly InquiryBackfillClassifier $classifier;

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
        $this->classifier = new InquiryBackfillClassifier($qa);
    }

    /** @return array<string, mixed> */
    public function dryRun(int $chunkSize = self::DEFAULT_CHUNK): array
    {
        $report = new InquiryBackfillReport('dry_run', $this->qa);
        $before = $this->inquiryCount();

        $this->eachChunk($chunkSize, function ($chunk) use ($report): void {
            foreach ($this->classifier->classify($chunk) as $decision) {
                $report->record($decision);
            }
        });

        $result = $report->toArray();
        $result['writes_performed'] = $this->inquiryCount() - $before;

        return $result;
    }

    /** @return array<string, mixed> */
    public function apply(int $chunkSize = self::DEFAULT_CHUNK): array
    {
        $snapshot = new InquiryBackfillSnapshot($this->qa);
        $report = new InquiryBackfillReport('apply', $this->qa);

        $snapshotBefore = $snapshot->capture();
        $inquiriesBefore = $this->inquiryCount();

        $this->eachChunk($chunkSize, function ($chunk) use ($report): void {
            foreach ($this->classifier->classify($chunk) as $decision) {
                $this->applyDecision($decision, $report);
            }
        });

        $snapshotAfter = $snapshot->capture();
        $result = $report->toArray();
        // «Создано этим запуском» — по исходам; разница счётчиков таблицы включает записи параллельных процессов.
        $result['inquiries_created'] = $result['outcomes'][InquiryBackfillReport::OUTCOME_CREATED] ?? 0;
        $result['inquiries_table_delta'] = $this->inquiryCount() - $inquiriesBefore;
        $result['integrity'] = [
            'legacy_unchanged' => $snapshotBefore['legacy_fingerprint'] === $snapshotAfter['legacy_fingerprint'],
            'legacy_rows' => $snapshotAfter['legacy_rows'],
            'bookings_before' => $snapshotBefore['bookings'],
            'bookings_after' => $snapshotAfter['bookings'],
            'bookings_unchanged' => $snapshotBefore['bookings'] === $snapshotAfter['bookings'],
            'audit_events_unchanged' => $snapshotBefore['audit_events'] === $snapshotAfter['audit_events'],
        ];
        $result['integrity']['ok'] = $result['integrity']['legacy_unchanged']
            && $result['integrity']['bookings_unchanged']
            && $result['integrity']['audit_events_unchanged'];

        return $result;
    }

    private function applyDecision(InquiryBackfillDecision $decision, InquiryBackfillReport $report): void
    {
        $report->record($decision);

        if ($decision->verdict === InquiryBackfillDecision::MATCHED) {
            $report->outcome(InquiryBackfillReport::OUTCOME_ALREADY_PRESENT);

            return;
        }

        if ($decision->verdict !== InquiryBackfillDecision::NOT_PROCESSED) {
            // BLOCKED / CONFLICT / UNSUPPORTED: ничего не пишем, причина уже учтена.
            $report->outcome($decision->verdict);

            return;
        }

        $final = $this->createOne($decision->sourceId, $report);
        $report->outcome($final);
    }

    /** Создаёт ровно одно Inquiry в собственной транзакции; возвращает фактический исход. */
    private function createOne(int $sourceId, InquiryBackfillReport $report): string
    {
        try {
            return DB::connection($this->qa->name)->transaction(function () use ($sourceId): string {
                $source = IncomingInquiry::on($this->qa->name)
                    ->select(InquiryBackfillClassifier::SOURCE_COLUMNS)
                    ->lockForUpdate()
                    ->find($sourceId);

                if ($source === null) {
                    return InquiryBackfillDecision::UNSUPPORTED;
                }

                // Состояние источника и существующих данных перепроверяется под блокировкой.
                $decision = $this->classifier->classify(collect([$source]))[$sourceId];

                if ($decision->verdict === InquiryBackfillDecision::MATCHED) {
                    return InquiryBackfillReport::OUTCOME_ALREADY_PRESENT;
                }

                if ($decision->verdict !== InquiryBackfillDecision::NOT_PROCESSED) {
                    return $decision->verdict;
                }

                $inquiry = new Inquiry();
                $inquiry->setConnection($this->qa->name);
                // Исторические created_at/updated_at источника сохраняются как есть (в т.ч. NULL).
                $inquiry->timestamps = false;
                $inquiry->forceFill(array_merge($decision->expected ?? [], [
                    'created_at' => $source->getRawOriginal('created_at'),
                    'updated_at' => $source->getRawOriginal('updated_at'),
                ]))->save();

                return InquiryBackfillReport::OUTCOME_CREATED;
            });
        } catch (UniqueConstraintViolationException) {
            // Параллельный процесс успел раньше: решение принимает повторная классификация.
            $report->raceResolved();

            return $this->settled($sourceId);
        } catch (InquiryInvariantViolation) {
            return InquiryBackfillDecision::BLOCKED;
        }
    }

    private function settled(int $sourceId): string
    {
        $source = IncomingInquiry::on($this->qa->name)->select(InquiryBackfillClassifier::SOURCE_COLUMNS)->find($sourceId);

        if ($source === null) {
            return InquiryBackfillDecision::UNSUPPORTED;
        }

        $verdict = $this->classifier->classify(collect([$source]))[$sourceId]->verdict;

        return $verdict === InquiryBackfillDecision::MATCHED ? InquiryBackfillReport::OUTCOME_ALREADY_PRESENT : $verdict;
    }

    private function inquiryCount(): int
    {
        return (int) DB::connection($this->qa->name)->table('inquiries')->count();
    }

    private function eachChunk(int $chunkSize, callable $callback): void
    {
        if ($chunkSize < 1 || $chunkSize > 1000) {
            throw new InvalidArgumentException('The chunk size must be between 1 and 1000.');
        }

        IncomingInquiry::on($this->qa->name)
            ->select(InquiryBackfillClassifier::SOURCE_COLUMNS)
            ->chunkById($chunkSize, $callback);
    }
}
