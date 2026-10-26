<?php

namespace App\Services\Inquiries;

use RuntimeException;

/**
 * Ожидаемый отказ сохранения контакта клиента обращения (закрытое обращение, устаревшая версия).
 * Сообщение предназначено для показа сотруднику и не содержит значений контакта.
 */
class InquiryContactException extends RuntimeException
{
    public const FIELD_REVISION = 'revision';

    public function __construct(string $message, public readonly string $field = 'contact')
    {
        parent::__construct($message);
    }

    /** Форма устарела: контакт уже изменён (или очищен и введён заново). */
    public function isStale(): bool
    {
        return $this->field === self::FIELD_REVISION;
    }
}
