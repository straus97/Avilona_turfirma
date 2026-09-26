<?php

namespace App\Services\Tourvisor;

use App\Jobs\ImportTourvisorInquiry;
use App\Models\IncomingInquiry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Приём уведомления Tourvisor webhook: идемпотентно фиксирует «указатель» на
 * заявку и запускает контролируемую загрузку авторитетных данных.
 *
 * Здесь НЕ создаются Booking/пользователи и не меняются деньги или статусы.
 * Идемпотентность держится на уникальном индексе (provider, type, external_id),
 * а не на проверке «сначала выбрать, потом вставить».
 */
class TourvisorInquiryIntake
{
    /**
     * @return array{inquiry: IncomingInquiry, created: bool}
     */
    public function receive(TourvisorInquiryType $type, string $externalId): array
    {
        $now = now();

        try {
            $inquiry = new IncomingInquiry();
            $inquiry->forceFill([
                'provider' => IncomingInquiry::PROVIDER_TOURVISOR,
                'external_type' => $type->value,
                'external_id' => $externalId,
                'state' => $type->isImportSupported() ? IncomingInquiry::STATE_PENDING : IncomingInquiry::STATE_UNSUPPORTED,
                'webhook_count' => 1,
                'first_notified_at' => $now,
                'last_notified_at' => $now,
            ])->save();

            $created = true;
        } catch (UniqueConstraintViolationException) {
            // Повторная (или параллельная) доставка: запись уже есть — только считаем уведомление.
            IncomingInquiry::query()
                ->where('provider', IncomingInquiry::PROVIDER_TOURVISOR)
                ->where('external_type', $type->value)
                ->where('external_id', $externalId)
                ->update([
                    'webhook_count' => DB::raw('webhook_count + 1'),
                    'last_notified_at' => $now,
                ]);

            $inquiry = IncomingInquiry::query()
                ->where('provider', IncomingInquiry::PROVIDER_TOURVISOR)
                ->where('external_type', $type->value)
                ->where('external_id', $externalId)
                ->firstOrFail();

            $created = false;
        }

        if ($this->needsImport($inquiry)) {
            $this->dispatchImport($inquiry);

            // При синхронной очереди загрузка уже выполнена — вернуть актуальное состояние.
            $inquiry->refresh();
        }

        return ['inquiry' => $inquiry, 'created' => $created];
    }

    private function needsImport(IncomingInquiry $inquiry): bool
    {
        return in_array($inquiry->state, [
            IncomingInquiry::STATE_PENDING,
            IncomingInquiry::STATE_FAILED,
            IncomingInquiry::STATE_IMPORTING,
        ], true);
    }

    private function dispatchImport(IncomingInquiry $inquiry): void
    {
        try {
            ImportTourvisorInquiry::dispatch($inquiry->id);
        } catch (Throwable $e) {
            // Сбой постановки/выполнения не должен превращаться в 5xx для Tourvisor:
            // запись остаётся в pending/failed и будет повторена следующей доставкой.
            Log::error('Tourvisor inquiry import dispatch failed', [
                'incoming_inquiry_id' => $inquiry->id,
                'exception' => $e::class,
            ]);
        }
    }
}
