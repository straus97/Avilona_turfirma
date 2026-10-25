<?php

namespace App\Services\Inquiries\Convergence;

use InvalidArgumentException;

/**
 * Заявления оператора о том, что НЕЛЬЗЯ проверить из базы (AOA-03-I5): остановлен ли входящий
 * трафик на уровне веб-сервера, остановлены ли воркеры очереди и планировщик, нет ли ручного SQL.
 * Это не доказательство, а письменно зафиксированное утверждение; в отчёте оно всегда помечено
 * как «attested», и оно никогда не заменяет машинные замеры.
 */
final class FreezeAttestation
{
    public const INGRESS_STOPPED = 'ingress_stopped';
    public const QUEUE_WORKERS_STOPPED = 'queue_workers_stopped';
    public const SCHEDULER_PAUSED = 'scheduler_paused';
    public const NO_MANUAL_SQL = 'no_manual_sql_or_maintenance_commands';

    public const KEYS = [self::INGRESS_STOPPED, self::QUEUE_WORKERS_STOPPED, self::SCHEDULER_PAUSED, self::NO_MANUAL_SQL];

    /** @var list<string> */
    private array $declared;

    /** @param list<string> $declared */
    public function __construct(array $declared = [])
    {
        foreach ($declared as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new InvalidArgumentException('Unknown freeze attestation: ' . $key);
            }
        }

        $this->declared = array_values(array_unique($declared));
    }

    public static function none(): self
    {
        return new self([]);
    }

    public static function all(): self
    {
        return new self(self::KEYS);
    }

    /** @return list<string> */
    public function declared(): array
    {
        return $this->declared;
    }

    /** @return list<string> */
    public function missing(): array
    {
        return array_values(array_diff(self::KEYS, $this->declared));
    }
}
