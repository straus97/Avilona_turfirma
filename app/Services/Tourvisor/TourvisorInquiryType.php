<?php

namespace App\Services\Tourvisor;

/**
 * Тип заявки в контракте экспорта Tourvisor (параметр `type` webhook).
 *
 * По публичной документации: 0 — обычная заявка, 1 — online-заявка.
 * Online-заявки содержат паспортные данные и в E5-A2A не обрабатываются:
 * тип принимается на границе webhook, но данные по нему не запрашиваются.
 */
enum TourvisorInquiryType: int
{
    case Ordinary = 0;
    case Online = 1;

    /** Поддерживается ли получение данных по этому типу в текущем срезе. */
    public function isImportSupported(): bool
    {
        return $this === self::Ordinary;
    }
}
