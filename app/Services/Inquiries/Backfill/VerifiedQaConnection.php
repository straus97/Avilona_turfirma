<?php

namespace App\Services\Inquiries\Backfill;

/**
 * Подключение, положительно признанное одноразовой QA-базой (AOA-03-I2).
 *
 * Объект можно получить только через verify(), который читает ТОЛЬКО конфигурацию и не
 * делает ни одного запроса к базе. Сервисы переноса принимают исключительно этот тип и
 * всегда работают через него, а не через подключение Laravel по умолчанию (в .env оно
 * может указывать на каноническую БД).
 *
 * Положительные признаки (все обязательны): явное имя подключения; маркер
 * `qa_disposable === true` (AOA_QA_DISPOSABLE); не production; драйвер из белого списка;
 * для MySQL — loopback, порт не 3306/3308 и не порт подключения по умолчанию, имя базы
 * `aoa03_qa_*`, не каноническая и не совпадающая ни с одним другим настроенным подключением;
 * для SQLite — `:memory:` либо файл `aoa03_qa_*.sqlite` во временном каталоге ОС.
 * Другое имя базы на том же экземпляре WAMP изоляцией не считается.
 */
final class VerifiedQaConnection
{
    public const CANONICAL_DATABASE = 'turfirma_rebuild_v4';

    /** Порты рабочих экземпляров WAMP — репетиция на них запрещена. */
    private const FORBIDDEN_PORTS = [3306, 3308];

    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    private function __construct(
        public readonly string $name,
        public readonly string $driver,
        public readonly string $database,
    ) {
    }

    /** @throws UnsafeBackfillConnection без обращения к базе данных */
    public static function verify(?string $name): self
    {
        $name = $name === null ? '' : trim($name);

        if ($name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new UnsafeBackfillConnection('An explicit QA connection name is required.');
        }

        $config = config('database.connections.' . $name);

        if (! is_array($config)) {
            throw new UnsafeBackfillConnection('The named connection is not configured.');
        }

        if (($config['qa_disposable'] ?? false) !== true) {
            throw new UnsafeBackfillConnection('The connection is not marked as a disposable QA database (AOA_QA_DISPOSABLE).');
        }

        if (app()->environment('production')) {
            throw new UnsafeBackfillConnection('Backfill rehearsal is refused in the production environment.');
        }

        $driver = (string) ($config['driver'] ?? '');

        $database = match ($driver) {
            'sqlite' => self::verifySqlite($config),
            'mysql', 'mariadb' => self::verifyNetwork($name, $config),
            default => throw new UnsafeBackfillConnection('Unsupported database driver for a QA backfill.'),
        };

        return new self($name, $driver, $database);
    }

    /** @param array<string, mixed> $config */
    private static function verifySqlite(array $config): string
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === ':memory:') {
            return $database;
        }

        $directory = $database !== '' ? realpath(dirname($database)) : false;
        $temp = realpath(sys_get_temp_dir());

        if (
            $directory === false || $temp === false
            || ! str_starts_with(strtolower($directory) . DIRECTORY_SEPARATOR, strtolower($temp) . DIRECTORY_SEPARATOR)
            || preg_match('/^aoa03_qa_[A-Za-z0-9_\-]+\.sqlite$/', basename($database)) !== 1
        ) {
            throw new UnsafeBackfillConnection('A SQLite QA database must be :memory: or aoa03_qa_*.sqlite inside the OS temp directory.');
        }

        return $database;
    }

    /** @param array<string, mixed> $config */
    private static function verifyNetwork(string $name, array $config): string
    {
        $host = strtolower((string) ($config['host'] ?? ''));
        $port = $config['port'] ?? null;
        $database = (string) ($config['database'] ?? '');

        if (! empty($config['url']) || ! empty($config['unix_socket'])) {
            throw new UnsafeBackfillConnection('A QA connection must not use a URL or a socket override.');
        }

        if (! in_array($host, self::LOOPBACK_HOSTS, true)) {
            throw new UnsafeBackfillConnection('A QA database must be on the loopback interface.');
        }

        if (! is_numeric($port) || (int) $port < 1024 || (int) $port > 65535) {
            throw new UnsafeBackfillConnection('A QA database requires an explicit unprivileged port.');
        }

        $port = (int) $port;

        if (in_array($port, self::FORBIDDEN_PORTS, true)) {
            throw new UnsafeBackfillConnection('The WAMP MySQL ports are never used for backfill verification.');
        }

        if (strcasecmp($database, self::CANONICAL_DATABASE) === 0
            || preg_match('/^aoa03_qa_[a-z0-9_]{1,40}$/', $database) !== 1) {
            throw new UnsafeBackfillConnection('The QA database name must match aoa03_qa_* and cannot be the canonical database.');
        }

        // Тот же сервер/база, что у другого настроенного подключения (в первую очередь у
        // подключения по умолчанию, т.е. потенциально канонического), — отказ.
        foreach ((array) config('database.connections') as $otherName => $other) {
            if ($otherName === $name || ! is_array($other)) {
                continue;
            }

            $isDefault = $otherName === config('database.default');
            $samePort = (int) ($other['port'] ?? 0) === $port;
            $sameDatabase = strcasecmp((string) ($other['database'] ?? ''), $database) === 0;

            if ($sameDatabase || ($isDefault && $samePort)) {
                throw new UnsafeBackfillConnection('The QA connection collides with another configured connection.');
            }
        }

        return $database;
    }
}
