<?php

namespace App\Services\Accounts;

use RuntimeException;

/**
 * Ожидаемый отказ в смене статуса или ролей учётной записи (AOA-05-I4). Типизированный отказ,
 * а не сбой: данные не менялись, контроллер показывает userMessage() как сообщение формы.
 *
 * Тексты не содержат имён, адресов и содержимого чужих записей.
 */
class AccountChangeRefused extends RuntimeException
{
    public const REASON_NOT_AUTHORIZED = 'not_authorized';
    public const REASON_SELF = 'self';
    public const REASON_LAST_ADMIN = 'last_admin';
    public const REASON_STALE = 'stale';
    public const REASON_INVALID_REASON = 'invalid_reason';

    public function __construct(
        public readonly string $reason,
        private readonly ?string $detail = null,
    ) {
        parent::__construct('Account change refused: ' . $reason);
    }

    /** Поле формы, к которому относится отказ (null — отказ относится ко всей операции). */
    public function field(): ?string
    {
        return $this->reason === self::REASON_INVALID_REASON ? 'reason' : null;
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            self::REASON_NOT_AUTHORIZED => 'Эта операция доступна только активному администратору.',
            self::REASON_SELF => 'Нельзя отключить собственную учётную запись. Попросите об этом другого администратора.',
            self::REASON_LAST_ADMIN => 'Нельзя оставить систему без активного администратора. '
                . 'Сначала назначьте или включите другого администратора.',
            self::REASON_STALE => 'Данные учётной записи уже изменились. Обновите страницу и повторите действие.',
            self::REASON_INVALID_REASON => $this->detail ?? 'Укажите причину.',
            default => 'Операцию выполнить нельзя.',
        };
    }
}
