<?php

namespace App\Services\Inquiries;

use RuntimeException;

/** Ожидаемый отказ назначения/передачи обращения: показывается как ошибка поля формы. */
class InquiryAssignmentException extends RuntimeException
{
    public const FIELD_STALE = 'expected_assignee_id';

    public function __construct(string $message, public readonly string $field = 'assignee_id')
    {
        parent::__construct($message);
    }

    /** Форма устарела: ответственный уже изменён другим администратором. */
    public function isStale(): bool
    {
        return $this->field === self::FIELD_STALE;
    }
}
