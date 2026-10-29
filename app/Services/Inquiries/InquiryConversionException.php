<?php

namespace App\Services\Inquiries;

use RuntimeException;

/**
 * Ожидаемый отказ оформления обращения в заявку (AOA-05-I1). Сообщение безопасно для показа сотруднику:
 * оно не содержит данных клиента, чужой заявки или внутренних идентификаторов.
 */
class InquiryConversionException extends RuntimeException
{
    /** Форма устарела: статус, ответственный или клиент изменились после её показа. */
    public const FIELD_STALE = 'stale';

    /** Не выполнено обязательное условие личности клиента (нет привязки, неактивен, ожидает проверки). */
    public const FIELD_CLIENT = 'conversion_client';

    public function __construct(string $message, public readonly string $field = 'conversion')
    {
        parent::__construct($message);
    }

    public function isStale(): bool
    {
        return $this->field === self::FIELD_STALE;
    }
}
