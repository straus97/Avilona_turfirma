<?php

namespace App\Services\Inquiries\Convergence;

use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Контракт «офлайн-снимок» для синтетической репетиции (AOA-03-I6). Только чтение.
 *
 * Отвечает на пять вопросов, которые VerifiedQaConnection (он читает ТОЛЬКО конфигурацию) не решает:
 *  1. identity()      — к какой базе подключение действительно привело (сервер сам называет базу,
 *                       порт, версию, каталог данных); расхождение с конфигурацией — отказ;
 *  2. schema()        — совместима ли схема (нужные таблицы, миграция Inquiry, отпечаток колонок);
 *  3. syntheticOnly() — нет ли в снимке адреса вне зарезервированных доменов (RFC 2606): реальные
 *                       клиентские данные в этой задаче недопустимы, проверка — счётчики, не значения;
 *  4. seal()          — манифест защищённого набора: отпечаток и число строк каждой исходной таблицы,
 *                       отпечаток схемы, идентичность и «ранее существовавшие» Inquiry;
 *  5. compare()       — что из манифеста изменилось с момента запечатывания.
 *
 * Защищённый набор — всё, что перенос читает и менять не вправе. Таблица `inquiries` в него не входит
 * (в неё перенос пишет), но Inquiry, существовавшие при запечатывании, обязаны остаться байт-в-байт
 * прежними: так проверяется «ни один черновик не перезаписан».
 *
 * Чего контракт НЕ доказывает: что в снимок попали ВСЕ обращения, когда-либо принятые внешними
 * системами; что чужое подключение не запишет после проверки; что манифест создан честно. Отпечаток
 * и счётчик строк сами по себе не доказывают отсутствия записи (ABA), поэтому на MySQL к ним добавлены
 * счётчики записи performance_schema (protectedWriteCounters), а на SQLite без :memory: изоляция
 * считается недоказуемой.
 */
final class OfflineSnapshotContract
{
    public const MANIFEST_VERSION = 1;

    /** Всё, что перенос читает и не имеет права менять. */
    public const PROTECTED_TABLES = ['incoming_inquiries', 'users', 'roles', 'role_user', 'bookings', 'audit_events'];

    /** Таблицы, без которых перенос невозможен (audit_events и jobs могут отсутствовать). */
    private const REQUIRED_TABLES = ['incoming_inquiries', 'inquiries', 'users', 'roles', 'role_user', 'bookings'];

    private const INQUIRY_MIGRATION = '2026_10_24_000000_create_inquiries_table';

    /** Домены, зарезервированные RFC 2606 / RFC 6761: настоящий клиент по такому адресу не живёт. */
    private const RESERVED_DOMAINS = ['example.com', 'example.org', 'example.net'];
    private const RESERVED_TLDS = ['test', 'invalid', 'example', 'localhost'];

    private const FORBIDDEN_PORTS = [3306, 3308];

    public function __construct(private readonly VerifiedQaConnection $qa)
    {
    }

    /**
     * Что сервер сообщает о самом себе. Ничего не бросает: расхождения возвращаются как `problems`.
     *
     * @return array{ok: bool, driver: string, database: string, port: int|null, version: string|null, digest: string, problems: list<string>}
     */
    public function identity(): array
    {
        $problems = [];
        $db = DB::connection($this->qa->name);
        $config = (array) config('database.connections.' . $this->qa->name);

        try {
            if ($this->qa->driver === 'sqlite') {
                [$database, $port, $version, $datadir, $uuid] = $this->sqliteIdentity($db, $config, $problems);
            } else {
                [$database, $port, $version, $datadir, $uuid] = $this->networkIdentity($db, $config, $problems);
            }
        } catch (Throwable) {
            return [
                'ok' => false, 'driver' => $this->qa->driver, 'database' => '', 'port' => null, 'version' => null,
                'digest' => '', 'problems' => ['identity_query_failed'],
            ];
        }

        return [
            'ok' => $problems === [],
            'driver' => $this->qa->driver,
            'database' => $database,
            'port' => $port,
            'version' => $version,
            'digest' => hash('sha256', json_encode([$this->qa->driver, $database, $port, $version, $datadir, $uuid], JSON_THROW_ON_ERROR)),
            'problems' => $problems,
        ];
    }

    /** @return array{0: string, 1: int|null, 2: string|null, 3: string, 4: string} */
    private function sqliteIdentity($db, array $config, array &$problems): array
    {
        $main = collect($db->select('pragma database_list'))->firstWhere('name', 'main');
        $file = $main === null ? null : (string) $main->file;
        $configured = (string) ($config['database'] ?? '');

        if ($configured === ':memory:') {
            if ($file !== '') {
                $problems[] = 'memory_database_is_backed_by_a_file';
            }
        } elseif ($file === null || $file === '' || strcasecmp((string) realpath($file), (string) realpath($configured)) !== 0) {
            $problems[] = 'sqlite_file_differs_from_configuration';
        }

        return [$configured === ':memory:' ? ':memory:' : basename((string) $file), null, (string) $db->scalar('select sqlite_version()'), (string) $file, ''];
    }

    /** @return array{0: string, 1: int|null, 2: string|null, 3: string, 4: string} */
    private function networkIdentity($db, array $config, array &$problems): array
    {
        $row = $db->selectOne('select database() as db, @@port as port, @@version as version, @@datadir as datadir');
        $uuid = '';
        try {
            $uuid = (string) $db->scalar('select @@server_uuid');
        } catch (Throwable) {
            // MariaDB не имеет server_uuid: идентичность тогда держится на остальных признаках.
        }

        $database = (string) ($row->db ?? '');
        $port = isset($row->port) ? (int) $row->port : null;

        if (strcasecmp($database, (string) ($config['database'] ?? '')) !== 0) {
            $problems[] = 'server_reports_a_different_database';
        }
        if (strcasecmp($database, VerifiedQaConnection::CANONICAL_DATABASE) === 0) {
            $problems[] = 'canonical_database_reported';
        }
        if ($port === null || $port !== (int) ($config['port'] ?? 0)) {
            // Туннель или проброс порта: конфигурация называет один сервер, отвечает другой.
            $problems[] = 'server_reports_a_different_port';
        }
        if ($port !== null && in_array($port, self::FORBIDDEN_PORTS, true)) {
            $problems[] = 'wamp_port_reported';
        }

        return [$database, $port, (string) ($row->version ?? ''), (string) ($row->datadir ?? ''), $uuid];
    }

    /**
     * @return array{ok: bool, missing_tables: list<string>, inquiry_migration_recorded: bool, fingerprint: string, problems: list<string>}
     */
    public function schema(): array
    {
        $schema = Schema::connection($this->qa->name);
        $missing = array_values(array_filter(self::REQUIRED_TABLES, fn (string $t): bool => ! $schema->hasTable($t)));

        $recorded = $schema->hasTable('migrations')
            && DB::connection($this->qa->name)->table('migrations')->where('migration', self::INQUIRY_MIGRATION)->exists();

        $problems = [];
        foreach ($missing as $table) {
            $problems[] = 'missing_table:' . $table;
        }
        if (! $recorded) {
            $problems[] = 'inquiry_migration_not_recorded';
        }

        $layout = [];
        if ($missing === []) {
            foreach (array_merge(self::PROTECTED_TABLES, ['inquiries']) as $table) {
                if ($schema->hasTable($table)) {
                    $layout[$table] = array_map(
                        static fn (array $c): array => [$c['name'], $c['type_name'] ?? $c['type']],
                        $schema->getColumns($table)
                    );
                }
            }
            ksort($layout);
        }

        return [
            'ok' => $problems === [],
            'missing_tables' => $missing,
            'inquiry_migration_recorded' => $recorded,
            'fingerprint' => hash('sha256', json_encode($layout, JSON_THROW_ON_ERROR)),
            'problems' => $problems,
        ];
    }

    /**
     * Положительное доказательство «синтетические данные»: ни один адрес не лежит вне
     * зарезервированных доменов. Возвращаются только числа.
     *
     * @return array{ok: bool, non_reserved_addresses: int, checked: array<string, int>}
     */
    public function syntheticOnly(): array
    {
        $db = DB::connection($this->qa->name);
        $schema = Schema::connection($this->qa->name);
        $offending = 0;
        $checked = [];

        foreach ([['users', 'email'], ['incoming_inquiries', 'client_email']] as [$table, $column]) {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, $column)) {
                continue;
            }

            $query = $db->table($table)->whereNotNull($column)->where($column, '!=', '');
            $checked[$table . '.' . $column] = (int) (clone $query)->count();

            $query->where(function ($q) use ($column): void {
                foreach (self::RESERVED_DOMAINS as $domain) {
                    $q->whereRaw("lower($column) not like ?", ['%@' . $domain]);
                }
                foreach (self::RESERVED_TLDS as $tld) {
                    $q->whereRaw("lower($column) not like ?", ['%.' . $tld]);
                }
            });

            $offending += (int) $query->count();
        }

        return ['ok' => $offending === 0, 'non_reserved_addresses' => $offending, 'checked' => $checked];
    }

    /**
     * Манифест защищённого набора. Только хеши и числа; без значений строк.
     *
     * @return array<string, mixed>
     */
    public function seal(): array
    {
        $identity = $this->identity();
        $schema = $this->schema();

        return [
            'version' => self::MANIFEST_VERSION,
            'driver' => $this->qa->driver,
            'database' => $this->qa->database,
            'identity_digest' => $identity['digest'],
            'schema_fingerprint' => $schema['fingerprint'],
            'tables' => $this->protectedState(),
            'inquiries_preexisting' => $this->preexistingInquiries(null),
        ];
    }

    /**
     * Сравнение текущего состояния с манифестом. `intact` — только если совпало ВСЁ.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{intact: bool, manifest_valid: bool, identity_unchanged: bool, schema_unchanged: bool, changed_tables: list<string>, preexisting_inquiries_unchanged: bool, source_rows: int|null}
     */
    public function compare(array $manifest): array
    {
        $valid = ($manifest['version'] ?? null) === self::MANIFEST_VERSION
            && is_array($manifest['tables'] ?? null)
            && isset($manifest['tables']['incoming_inquiries']['rows'], $manifest['tables']['incoming_inquiries']['fingerprint'])
            && is_array($manifest['inquiries_preexisting'] ?? null)
            && is_string($manifest['identity_digest'] ?? null)
            && is_string($manifest['schema_fingerprint'] ?? null)
            && ($manifest['driver'] ?? null) === $this->qa->driver
            && ($manifest['database'] ?? null) === $this->qa->database;

        if (! $valid) {
            return [
                'intact' => false, 'manifest_valid' => false, 'identity_unchanged' => false, 'schema_unchanged' => false,
                'changed_tables' => [], 'preexisting_inquiries_unchanged' => false, 'source_rows' => null,
            ];
        }

        $now = $this->protectedState();
        $changed = [];
        foreach (array_unique(array_merge(array_keys($manifest['tables']), array_keys($now))) as $table) {
            if (($manifest['tables'][$table] ?? null) !== ($now[$table] ?? null)) {
                $changed[] = (string) $table;
            }
        }
        sort($changed);

        $identityUnchanged = $this->identity()['digest'] === $manifest['identity_digest'];
        $schemaUnchanged = $this->schema()['fingerprint'] === $manifest['schema_fingerprint'];
        $preexisting = $this->preexistingInquiries((int) ($manifest['inquiries_preexisting']['max_id'] ?? 0)) === $manifest['inquiries_preexisting'];

        return [
            'intact' => $changed === [] && $identityUnchanged && $schemaUnchanged && $preexisting,
            'manifest_valid' => true,
            'identity_unchanged' => $identityUnchanged,
            'schema_unchanged' => $schemaUnchanged,
            'changed_tables' => $changed,
            'preexisting_inquiries_unchanged' => $preexisting,
            'source_rows' => $now['incoming_inquiries']['rows'] ?? null,
        ];
    }

    /**
     * Накопительные счётчики записи защищённых таблиц (performance_schema). null — не наблюдается
     * (не MySQL, нет прав, нет performance_schema): это не «0» и не «чисто».
     *
     * @return array<string, array{insert: int, update: int, delete: int}>|null
     */
    public function protectedWriteCounters(): ?array
    {
        if (! in_array($this->qa->driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        try {
            $db = DB::connection($this->qa->name);
            $counters = [];

            foreach (self::PROTECTED_TABLES as $table) {
                if (! Schema::connection($this->qa->name)->hasTable($table)) {
                    continue;
                }

                $row = $db->selectOne(
                    'select count_insert as i, count_update as u, count_delete as d from performance_schema.table_io_waits_summary_by_table'
                    . ' where object_schema = database() and object_name = ?',
                    [$table]
                );

                if ($row === null) {
                    return null;
                }

                $counters[$table] = ['insert' => (int) $row->i, 'update' => (int) $row->u, 'delete' => (int) $row->d];
            }

            return $counters;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, array{rows: int, fingerprint: string}> */
    private function protectedState(): array
    {
        $schema = Schema::connection($this->qa->name);
        $state = [];

        foreach (self::PROTECTED_TABLES as $table) {
            if ($schema->hasTable($table)) {
                $state[$table] = $this->fingerprint($table, null, null);
            }
        }

        ksort($state);

        return $state;
    }

    /** @return array{rows: int, max_id: int, fingerprint: string} */
    private function preexistingInquiries(?int $upToId): array
    {
        $upTo = $upToId ?? (int) DB::connection($this->qa->name)->table('inquiries')->max('id');
        $state = $this->fingerprint('inquiries', 'id', $upTo);

        return ['rows' => $state['rows'], 'max_id' => $upTo, 'fingerprint' => $state['fingerprint']];
    }

    /** @return array{rows: int, fingerprint: string} */
    private function fingerprint(string $table, ?string $limitColumn, ?int $limit): array
    {
        $schema = Schema::connection($this->qa->name);
        $query = DB::connection($this->qa->name)->table($table);

        if ($limitColumn !== null) {
            $query->where($limitColumn, '<=', $limit);
        }

        // Порядок — по первичному ключу (детерминирован и дёшев); без ключа — по всем колонкам.
        $primary = collect($schema->getIndexes($table))->firstWhere('primary', true);
        foreach ($primary['columns'] ?? $schema->getColumnListing($table) as $column) {
            $query->orderBy($column);
        }

        $context = hash_init('sha256');
        $rows = 0;

        foreach ($query->cursor() as $row) {
            hash_update($context, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $rows++;
        }

        return ['rows' => $rows, 'fingerprint' => hash_final($context)];
    }
}
