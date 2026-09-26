<?php

namespace App\Jobs;

use App\Services\Tourvisor\TourvisorInquiryImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Загружает авторитетные данные одного входящего обращения Tourvisor.
 *
 * Несёт только локальный id записи — никаких URL, ключей или данных запроса.
 * Временные сбои (сеть, таймаут, 5xx, 429) откладываются на повтор; итог всегда
 * фиксируется в записи, поэтому job сам исключений наружу не бросает.
 */
class ImportTourvisorInquiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $incomingInquiryId)
    {
    }

    public function handle(TourvisorInquiryImporter $importer): void
    {
        $result = $importer->import($this->incomingInquiryId);

        if ($result === TourvisorInquiryImporter::RESULT_RETRY && $this->attempts() < $this->tries) {
            $this->release(60 * max(1, $this->attempts()));
        }
    }
}
