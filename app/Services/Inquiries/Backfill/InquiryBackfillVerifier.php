<?php

namespace App\Services\Inquiries\Backfill;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use Illuminate\Support\Facades\DB;

/**
 * Независимая сверка IncomingInquiry ↔ Inquiry (AOA-03-I2). Только чтение: ни одна из
 * таблиц не меняется. Использует тот же классификатор, что и перенос, поэтому «MATCHED» означает
 * ровно то, что перенос создал бы сам.
 *
 * Вердикты по источнику: MATCHED, BLOCKED, CONFLICT, UNSUPPORTED, NOT_PROCESSED.
 * Сверка считается полной (`fully_verified`) только если нет ни одного BLOCKED / CONFLICT /
 * NOT_PROCESSED и не найдено сироток, дублей источника или заявки. UNSUPPORTED (технические
 * состояния загрузки) учтены в отчёте и намеренно не переносятся.
 *
 * Сверка описывает состояние ДО переключения runtime: после того как Inquiry начнут меняться
 * (AOA-04/05), расхождение назначения или статуса станет законным и сверку надо будет заменить.
 */
final class InquiryBackfillVerifier
{
    private readonly InquiryBackfillClassifier $classifier;

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
        $this->classifier = new InquiryBackfillClassifier($qa);
    }

    /** @return array<string, mixed> */
    public function verify(int $chunkSize = InquiryBackfillService::DEFAULT_CHUNK): array
    {
        $report = new InquiryBackfillReport('verify', $this->qa);

        IncomingInquiry::on($this->qa->name)
            ->select(InquiryBackfillClassifier::SOURCE_COLUMNS)
            ->chunkById($chunkSize, function ($chunk) use ($report): void {
                foreach ($this->classifier->classify($chunk) as $decision) {
                    $report->record($decision);
                }
            });

        $result = $report->toArray();
        $result['global'] = $this->globalChecks();

        $incomplete = $report->verdictCount(InquiryBackfillDecision::BLOCKED)
            + $report->verdictCount(InquiryBackfillDecision::CONFLICT)
            + $report->verdictCount(InquiryBackfillDecision::NOT_PROCESSED);

        $anomalies = $result['global']['external_inquiries_without_source']
            + $result['global']['duplicate_source_links']
            + $result['global']['duplicate_booking_links'];

        $result['fully_verified'] = $incomplete === 0 && $anomalies === 0;

        return $result;
    }

    /** @return array<string, int> */
    private function globalChecks(): array
    {
        $db = DB::connection($this->qa->name);
        $inquiries = fn () => $db->table('inquiries');

        return [
            'inquiries_total' => (int) $inquiries()->count(),
            'inquiries_external_intake' => (int) $inquiries()->where('origin', Inquiry::ORIGIN_EXTERNAL_INTAKE)->count(),
            'inquiries_manual' => (int) $inquiries()->where('origin', '!=', Inquiry::ORIGIN_EXTERNAL_INTAKE)->count(),
            'external_inquiries_without_source' => (int) $inquiries()
                ->where('origin', Inquiry::ORIGIN_EXTERNAL_INTAKE)
                ->where(fn ($q) => $q->whereNull('incoming_inquiry_id')->orWhereNotExists(
                    fn ($sub) => $sub->selectRaw('1')->from('incoming_inquiries')->whereColumn('incoming_inquiries.id', 'inquiries.incoming_inquiry_id')
                ))
                ->count(),
            'duplicate_source_links' => $this->duplicateGroups('incoming_inquiry_id'),
            'duplicate_booking_links' => $this->duplicateGroups('booking_id'),
            'bookings_total' => (int) $db->table('bookings')->count(),
        ];
    }

    private function duplicateGroups(string $column): int
    {
        return DB::connection($this->qa->name)
            ->table('inquiries')
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('count(*) > 1')
            ->select($column)
            ->get()
            ->count();
    }
}
