<?php

namespace App\Services\Tourvisor;

use App\Models\IncomingInquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Загрузка авторитетных данных обращения из Tourvisor Export API.
 *
 * Запись атомарно «захватывается» (pending/failed/зависшая importing →
 * importing) условным UPDATE, поэтому параллельные задачи по одной заявке не
 * дублируют загрузку, а уже загруженная заявка повторно не перезаписывается.
 */
class TourvisorInquiryImporter
{
    public const RESULT_IMPORTED = 'imported';
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_FAILED = 'failed';
    public const RESULT_RETRY = 'retry';

    public function __construct(private readonly TourvisorExportClient $client)
    {
    }

    /**
     * @return string  одна из констант RESULT_*
     */
    public function import(int $incomingInquiryId): string
    {
        if (! $this->claim($incomingInquiryId)) {
            return self::RESULT_SKIPPED;
        }

        $inquiry = IncomingInquiry::query()->findOrFail($incomingInquiryId);

        try {
            $data = $this->client->fetchInquiry(
                TourvisorInquiryType::from($inquiry->external_type),
                $inquiry->external_id,
            );
        } catch (TourvisorExportException $e) {
            $this->markFailed($inquiry, $e->reason);

            Log::warning('Tourvisor inquiry import failed', [
                'incoming_inquiry_id' => $inquiry->id,
                'reason' => $e->reason,
            ]);

            return $e->isRetryable() ? self::RESULT_RETRY : self::RESULT_FAILED;
        }

        DB::transaction(function () use ($inquiry, $data): void {
            $inquiry->forceFill([
                'state' => IncomingInquiry::STATE_IMPORTED,
                'imported_at' => now(),
                'last_error_code' => null,
                'last_error_at' => null,
                'client_name' => $data->name,
                'client_phone' => $data->phone,
                'client_email' => $data->email,
                'client_comment' => $data->comment,
                'price' => $data->price,
                'currency' => $data->currency,
                'operator_name' => $data->operator,
                'departure_city' => $data->departureCity,
                'destination_country' => $data->country,
                'hotel_name' => $data->hotel,
                'fly_date' => $data->flyDate,
                'nights' => $data->nights,
                'vendor_payload' => $data->payload,
                'normalizer_version' => TourvisorInquiry::NORMALIZER_VERSION,
            ])->save();
        });

        return self::RESULT_IMPORTED;
    }

    private function claim(int $id): bool
    {
        $staleBefore = now()->subMinutes(IncomingInquiry::IMPORT_CLAIM_TTL_MINUTES);

        return IncomingInquiry::query()
            ->whereKey($id)
            ->where('external_type', TourvisorInquiryType::Ordinary->value)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereIn('state', [IncomingInquiry::STATE_PENDING, IncomingInquiry::STATE_FAILED])
                    ->orWhere(function ($stale) use ($staleBefore): void {
                        $stale->where('state', IncomingInquiry::STATE_IMPORTING)
                            ->where('last_attempt_at', '<', $staleBefore);
                    });
            })
            ->update([
                'state' => IncomingInquiry::STATE_IMPORTING,
                'last_attempt_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
            ]) === 1;
    }

    private function markFailed(IncomingInquiry $inquiry, string $reason): void
    {
        $inquiry->forceFill([
            'state' => IncomingInquiry::STATE_FAILED,
            'last_error_code' => $reason,
            'last_error_at' => now(),
        ])->save();
    }
}
