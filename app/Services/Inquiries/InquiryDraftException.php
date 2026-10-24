<?php

namespace App\Services\Inquiries;

use RuntimeException;

/**
 * Ожидаемый отказ сохранения черновика обращения (закрытое обращение, устаревшая
 * версия). Сообщение предназначено для показа сотруднику.
 */
class InquiryDraftException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'draft')
    {
        parent::__construct($message);
    }
}
