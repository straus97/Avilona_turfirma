<?php

namespace App\Services\Inquiries\Convergence;

/**
 * Один замер того, что о заморозке E5 можно узнать ТОЛЬКО из самой базы (AOA-03-I5).
 * null означает «движок/конфигурация не позволяют это проверить» — это не «0» и не «чисто».
 * Только числа: никаких идентификаторов сессий, запросов, адресов или данных.
 */
final class FreezeObservation
{
    /**
     * @param  int|null  $otherSessions  чужие клиентские сессии на сервере БД
     * @param  int|null  $otherOpenTransactions  чужие открытые транзакции InnoDB
     * @param  int|null  $importJobsQueued  задания ImportTourvisorInquiry в таблице jobs (в очереди, в т.ч. отложенные)
     * @param  int|null  $importJobsReserved  из них уже взятые воркером
     * @param  int  $importsInFlight  записи в состоянии importing с живым захватом
     * @param  array{insert: int, update: int, delete: int}|null  $sourceWriteCounters  накопительные счётчики записи таблицы incoming_inquiries
     */
    public function __construct(
        public readonly string $driver,
        public readonly ?int $otherSessions,
        public readonly ?int $otherOpenTransactions,
        public readonly ?int $importJobsQueued,
        public readonly ?int $importJobsReserved,
        public readonly int $importsInFlight,
        public readonly ?array $sourceWriteCounters,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'other_sessions' => $this->otherSessions,
            'other_open_transactions' => $this->otherOpenTransactions,
            'import_jobs_queued' => $this->importJobsQueued,
            'import_jobs_reserved' => $this->importJobsReserved,
            'imports_in_flight' => $this->importsInFlight,
            'source_write_counters' => $this->sourceWriteCounters,
        ];
    }
}
