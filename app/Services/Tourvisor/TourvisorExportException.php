<?php

namespace App\Services\Tourvisor;

use RuntimeException;

/**
 * Безопасная ошибка обращения к Tourvisor Export API.
 *
 * Сообщение — только фиксированный код причины. В него никогда не попадают
 * URL, query-параметры, тела ответов или исходные исключения HTTP-клиента:
 * ключ Tourvisor передаётся в query-строке, и его текст мог бы просочиться в
 * лог или ответ. Предыдущее исключение намеренно не сохраняется.
 */
class TourvisorExportException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';
    public const UNSUPPORTED_TYPE = 'unsupported_type';
    public const TIMEOUT = 'timeout';
    public const NETWORK = 'network';
    public const UNAUTHORIZED = 'unauthorized';
    public const RATE_LIMITED = 'rate_limited';
    public const HTTP_ERROR = 'http_error';
    public const HTTP_CLIENT_ERROR = 'http_client_error';
    public const MALFORMED = 'malformed';
    public const NOT_FOUND = 'not_found';
    public const ID_MISMATCH = 'id_mismatch';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Tourvisor export failure: ' . $reason);
    }

    /** Имеет ли смысл повторить попытку позже. */
    public function isRetryable(): bool
    {
        return in_array($this->reason, [self::TIMEOUT, self::NETWORK, self::RATE_LIMITED, self::HTTP_ERROR], true);
    }
}
