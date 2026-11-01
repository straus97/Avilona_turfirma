<?php

namespace App\Services\Content\Legacy;

/**
 * NOV-PUBLIC-04A: отказ при разборе исторической SQL-выгрузки.
 *
 * Сообщение содержит только код причины, номер выражения, строку файла и имя
 * таблицы (имя проходит проверку идентификатора). Содержимое записей в
 * сообщение не попадает никогда.
 */
final class LegacySqlDumpException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $statement = 0,
        public readonly int $sourceLine = 0,
        public readonly ?string $table = null,
    ) {
        parent::__construct(sprintf(
            '%s (statement #%d, line %d%s)',
            $reason,
            $statement,
            $sourceLine,
            $table !== null ? ", table {$table}" : ''
        ));
    }
}
