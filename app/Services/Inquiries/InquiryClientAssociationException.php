<?php

namespace App\Services\Inquiries;

use RuntimeException;

/**
 * Ожидаемый отказ привязки/отвязки/создания клиента обращения (AOA-04-I4). Сообщение показывается
 * сотруднику. Для менеджера оно одинаково для «аккаунта нет», «чужого», «неактивного» и «не
 * туриста»: см. InquiryClientAssociation::CLIENT_UNAVAILABLE_MESSAGE.
 */
class InquiryClientAssociationException extends RuntimeException
{
    public const FIELD_STALE = 'expected_client_id';

    public function __construct(string $message, public readonly string $field = 'client')
    {
        parent::__construct($message);
    }

    /** Форма устарела: клиент обращения уже изменён. */
    public function isStale(): bool
    {
        return $this->field === self::FIELD_STALE;
    }
}
