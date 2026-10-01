<?php

namespace App\Services\Tourvisor;

use RuntimeException;

/**
 * Безопасная ошибка регистрации/снятия webhook Tourvisor.
 *
 * Сообщение — только фиксированный код причины. В него никогда не попадают
 * authkey, callback-URL (содержит TOURVISOR_WEBHOOK_TOKEN), тела запроса/ответа
 * или исходные исключения HTTP-клиента. Предыдущее исключение намеренно не
 * сохраняется (см. TourvisorExportException — тот же принцип).
 */
class TourvisorWebhookRegistrationException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';
    public const TIMEOUT = 'timeout';
    public const NETWORK = 'network';
    public const UNAUTHORIZED = 'unauthorized';
    public const RATE_LIMITED = 'rate_limited';
    public const HTTP_ERROR = 'http_error';
    public const HTTP_CLIENT_ERROR = 'http_client_error';
    public const MALFORMED = 'malformed';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Tourvisor webhook registration failure: ' . $reason);
    }

    /** Имеет ли смысл повторить попытку позже. */
    public function isRetryable(): bool
    {
        return in_array($this->reason, [self::TIMEOUT, self::NETWORK, self::RATE_LIMITED, self::HTTP_ERROR], true);
    }
}
