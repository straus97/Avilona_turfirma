<?php

namespace App\Services\Inquiries\Convergence;

use App\Services\Inquiries\Backfill\VerifiedQaConnection;

/**
 * Замеры изоляции офлайн-снимка (AOA-03-I6). Решает, ЧТО считается доказательством отсутствия
 * чужого писателя для конкретного движка, и больше ничего не придумывает:
 *
 *  - SQLite `:memory:` — база принадлежит одному дескриптору этого процесса, второго соединения с той
 *    же базой не бывает (другое подключение к `:memory:` создаёт ДРУГУЮ пустую базу). Чужих сессий и
 *    транзакций нет по построению; запись самим процессом ловят отпечатки манифеста;
 *  - MySQL/MariaDB — живые замеры DatabaseFreezeObserver (I5): чужие сессии, транзакции, счётчики
 *    записи, очередь. Это наблюдение, а не барьер;
 *  - файл SQLite и всё остальное — не наблюдается (null): другой процесс может открыть тот же файл,
 *    поэтому изоляция недоказуема и репетиция отклоняется (fail closed).
 */
final class OfflineIsolationObserver implements FreezeObserver
{
    private readonly DatabaseFreezeObserver $database;

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
        $this->database = new DatabaseFreezeObserver($qa);
    }

    public function isPrivateMemory(): bool
    {
        return $this->qa->driver === 'sqlite' && $this->qa->database === ':memory:';
    }

    public function observe(): FreezeObservation
    {
        $measured = $this->database->observe();

        if (! $this->isPrivateMemory()) {
            return $measured;
        }

        return new FreezeObservation(
            'sqlite_memory_private',
            0,
            0,
            $measured->importJobsQueued,
            $measured->importJobsReserved,
            $measured->importsInFlight,
            ['insert' => 0, 'update' => 0, 'delete' => 0],
        );
    }
}
