<?php

namespace App\Services\Inquiries\Convergence;

use App\Models\IncomingInquiry;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Замеры заморозки через саму базу (AOA-03-I5), только на проверенной QA-базе.
 *
 * Что это даёт и чего не даёт:
 *  - чужие сессии и чужие транзакции видны только на MySQL/MariaDB (SQLite этого не умеет → null);
 *  - счётчики записи берутся из performance_schema.table_io_waits_summary_by_table: они считают
 *    INSERT/UPDATE/DELETE по таблице, включая каскад ON DELETE SET NULL (проверено на MySQL 9.7.1;
 *    триггеры его не видят) и включая откатанные попытки, поэтому нулевая разница за окно —
 *    доказательство отсутствия попыток записи, а ненулевая — «записывали» (консервативно);
 *  - счётчик накопительный и обнуляется при перезапуске сервера: сравнивать имеет смысл только два
 *    замера одного окна;
 *  - любой запрос, который не удался (нет прав, нет performance_schema), даёт null, а не 0.
 * Это замер, а не барьер: он ничего не останавливает и не доказывает, что запись не начнётся после.
 */
final class DatabaseFreezeObserver implements FreezeObserver
{
    private const IMPORT_JOB_PATTERN = '%ImportTourvisorInquiry%';

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
    }

    public function observe(): FreezeObservation
    {
        $db = DB::connection($this->qa->name);
        $network = in_array($this->qa->driver, ['mysql', 'mariadb'], true);

        [$jobsQueued, $jobsReserved] = $this->importJobs($db);

        return new FreezeObservation(
            $this->qa->driver,
            $network ? $this->safely(fn () => $this->otherSessions($db)) : null,
            $network ? $this->safely(fn () => $this->otherTransactions($db)) : null,
            $jobsQueued,
            $jobsReserved,
            $this->importsInFlight($db),
            $network ? $this->safely(fn () => $this->writeCounters($db)) : null,
        );
    }

    private function otherSessions(ConnectionInterface $db): int
    {
        return (int) $db->scalar(
            "select count(*) from performance_schema.threads where type = 'FOREGROUND' and processlist_id is not null"
            . " and processlist_id <> connection_id() and processlist_command <> 'Daemon'"
        );
    }

    private function otherTransactions(ConnectionInterface $db): int
    {
        return (int) $db->scalar('select count(*) from information_schema.innodb_trx where trx_mysql_thread_id <> connection_id()');
    }

    /** @return array{insert: int, update: int, delete: int}|null */
    private function writeCounters(ConnectionInterface $db): ?array
    {
        $row = $db->selectOne(
            'select count_insert as i, count_update as u, count_delete as d from performance_schema.table_io_waits_summary_by_table'
            . ' where object_schema = database() and object_name = ?',
            ['incoming_inquiries']
        );

        return $row === null ? null : ['insert' => (int) $row->i, 'update' => (int) $row->u, 'delete' => (int) $row->d];
    }

    /** @return array{0: int|null, 1: int|null} */
    private function importJobs(ConnectionInterface $db): array
    {
        $queued = $this->safely(function () use ($db): ?int {
            if (! Schema::connection($this->qa->name)->hasTable('jobs')) {
                return null;
            }

            return (int) $db->table('jobs')->where('payload', 'like', self::IMPORT_JOB_PATTERN)->count();
        });

        if ($queued === null) {
            return [null, null];
        }

        $reserved = $this->safely(fn () => (int) $db->table('jobs')->where('payload', 'like', self::IMPORT_JOB_PATTERN)->whereNotNull('reserved_at')->count());

        return [$queued, $reserved];
    }

    private function importsInFlight(ConnectionInterface $db): int
    {
        return (int) $db->table('incoming_inquiries')
            ->where('state', IncomingInquiry::STATE_IMPORTING)
            ->where('last_attempt_at', '>=', now()->subMinutes(IncomingInquiry::IMPORT_CLAIM_TTL_MINUTES))
            ->count();
    }

    private function safely(callable $probe): mixed
    {
        try {
            return $probe();
        } catch (Throwable) {
            return null;
        }
    }
}
