<?php

namespace App\Services\Inquiries;

use RuntimeException;

/** Ожидаемый отказ закрытия обращения: ошибка поля формы либо устаревшее состояние. */
class InquiryClosureException extends RuntimeException
{
    public const FIELD_STALE = 'expected_state';

    public function __construct(string $message, public readonly string $field = 'reason')
    {
        parent::__construct($message);
    }

    /** Форма устарела: статус или ответственный уже изменены (в том числе обращение уже закрыто). */
    public function isStale(): bool
    {
        return $this->field === self::FIELD_STALE;
    }
}
