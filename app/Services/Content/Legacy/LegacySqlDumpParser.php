<?php

namespace App\Services\Content\Legacy;

/**
 * NOV-PUBLIC-04A: детерминированный разбор phpMyAdmin/mysqldump-выгрузки как ДАННЫХ.
 *
 * Ничего не выполняется: нет соединения с базой, нет eval, нет восстановления.
 * Текст выгрузки только сканируется. Поддерживаются CREATE TABLE (для имён
 * столбцов) и INSERT ... VALUES; безвредные служебные выражения (SET, LOCK,
 * ALTER, DROP …) пропускаются без исполнения; всё остальное (UPDATE, DELETE,
 * DELIMITER, триггеры, GRANT, INSERT ... SELECT, функции в значениях) —
 * отказ целиком: разбор «закрыт по умолчанию».
 *
 * Значения материализуются только для столбцов, которые запросил вызывающий код
 * ($want). У остальных таблиц строки лишь пересчитываются и сразу отбрасываются,
 * поэтому приватные данные не попадают в память разбора, в логи и в сообщения.
 */
final class LegacySqlDumpParser
{
    public const MAX_BYTES = 64 * 1024 * 1024;

    /** Выражения, которые сканируются до «;» и не исполняются. */
    private const IGNORED = ['SET', 'START', 'BEGIN', 'COMMIT', 'LOCK', 'UNLOCK', 'USE', 'ALTER', 'DROP'];

    private string $s = '';

    private int $n = 0;

    private int $i = 0;

    private int $stmt = 0;

    private ?string $table = null;

    /**
     * @param callable(string): ?array<int,string> $want столбцы, значения которых нужны для таблицы
     *        (['*'] — все; null — только пересчитать строки)
     *
     * @return array{header: array<string,string>, kinds: array<string,int>,
     *         tables: array<string, array{columns: array<int,string>, row_count: int, rows: array<int, array<string,?string>>}>}
     */
    public function parse(string $sql, callable $want): array
    {
        if (strlen($sql) > self::MAX_BYTES) {
            throw new LegacySqlDumpException('file_too_large');
        }
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }

        $this->s = $sql;
        $this->n = strlen($sql);
        $this->i = 0;
        $this->stmt = 0;
        $tables = [];
        $kinds = [];

        while (true) {
            $this->skipTrivia();
            if ($this->i >= $this->n) {
                break;
            }
            if ($this->s[$this->i] === ';') {
                $this->i++;

                continue;
            }

            $this->stmt++;
            $this->table = null;
            $keyword = $this->word();
            if ($keyword === '') {
                throw $this->fail('unexpected_token');
            }
            $kinds[$keyword] = ($kinds[$keyword] ?? 0) + 1;

            if ($keyword === 'CREATE') {
                $this->createStatement($tables);
            } elseif ($keyword === 'INSERT') {
                $this->insertStatement($tables, $want);
            } elseif (in_array($keyword, self::IGNORED, true)) {
                $this->skipStatement();
            } else {
                throw $this->fail('unsupported_statement' . (preg_match('/^[A-Z]{2,12}$/', $keyword) ? ':' . $keyword : ''));
            }
        }

        return ['header' => $this->header($sql), 'kinds' => $kinds, 'tables' => $tables];
    }

    /** @return array<string,string> */
    private function header(string $sql): array
    {
        $head = substr($sql, 0, 4096);
        $out = [];
        $map = [
            'generator' => '/^-- (phpMyAdmin SQL Dump|MySQL dump[^\r\n]*)/mi',
            'generation_time' => '/^-- Generation Time: ([^\r\n]{1,60})/mi',
            'server_version' => '/^-- Server version: ([^\r\n]{1,40})/mi',
        ];
        foreach ($map as $key => $pattern) {
            if (preg_match($pattern, $head, $m) === 1) {
                $out[$key] = trim(preg_replace('/[^\x20-\x7E]/', '', $m[1]) ?? '');
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ statements

    /** @param array<string, array<string,mixed>> $tables */
    private function createStatement(array &$tables): void
    {
        $this->skipTrivia();
        $what = $this->word();

        if ($what === 'DATABASE' || $what === 'SCHEMA') {
            $this->skipStatement();

            return;
        }
        if ($what !== 'TABLE') {
            throw $this->fail('unsupported_create' . (preg_match('/^[A-Z]{3,12}$/', $what) ? ':' . $what : ''));
        }

        $this->skipTrivia();
        $save = $this->i;
        if ($this->word() === 'IF') {
            $this->skipTrivia();
            if ($this->word() !== 'NOT') {
                throw $this->fail('create_table_form');
            }
            $this->skipTrivia();
            if ($this->word() !== 'EXISTS') {
                throw $this->fail('create_table_form');
            }
        } else {
            $this->i = $save;
        }

        $name = strtolower($this->identifier());
        $this->table = $name;
        $this->skipTrivia();
        if (($this->s[$this->i] ?? '') !== '(') {
            throw $this->fail('create_table_form');
        }

        $close = $this->matchParen($this->i);
        $columns = $this->columnsOfCreate($this->i + 1, $close);
        $this->i = $close + 1;
        $this->skipStatement();

        $tables[$name] = ['columns' => $columns, 'row_count' => 0, 'rows' => []];
    }

    /**
     * @param array<string, array<string,mixed>> $tables
     * @param callable(string): ?array<int,string> $want
     */
    private function insertStatement(array &$tables, callable $want): void
    {
        $this->skipTrivia();
        $word = $this->word();
        if ($word === 'IGNORE') {
            $this->skipTrivia();
            $word = $this->word();
        }
        if ($word !== 'INTO') {
            throw $this->fail('insert_form');
        }

        $table = strtolower($this->identifier());
        $this->table = $table;
        $this->skipTrivia();

        $columns = null;
        if (($this->s[$this->i] ?? '') === '(') {
            $close = $this->matchParen($this->i);
            $columns = $this->columnList($this->i + 1, $close);
            $this->i = $close + 1;
            $this->skipTrivia();
        }

        $word = $this->word();
        if ($word !== 'VALUES' && $word !== 'VALUE') {
            throw $this->fail('insert_form');
        }

        $columns ??= $tables[$table]['columns'] ?? null;
        if ($columns === null || $columns === []) {
            throw $this->fail('insert_without_columns');
        }
        $tables[$table] ??= ['columns' => $columns, 'row_count' => 0, 'rows' => []];

        $keep = $want($table);
        $index = [];
        if ($keep !== null) {
            foreach ($columns as $k => $column) {
                if ($keep === ['*'] || in_array($column, $keep, true)) {
                    $index[$k] = $column;
                }
            }
        }
        $width = count($columns);

        while (true) {
            $this->skipTrivia();
            if (($this->s[$this->i] ?? '') !== '(') {
                throw $this->fail('values_expected');
            }
            $this->i++;

            $row = [];
            $k = 0;
            while (true) {
                $this->skipTrivia();
                $keepThis = isset($index[$k]);
                $value = $this->readValue($keepThis);
                if ($keepThis) {
                    $row[$index[$k]] = $value;
                }
                $k++;
                $this->skipTrivia();
                $c = $this->s[$this->i] ?? '';
                if ($c === ',') {
                    $this->i++;

                    continue;
                }
                if ($c === ')') {
                    $this->i++;

                    break;
                }
                throw $this->fail('values_syntax');
            }

            if ($k !== $width) {
                throw $this->fail('column_count_mismatch');
            }
            $tables[$table]['row_count']++;
            if ($keep !== null) {
                $tables[$table]['rows'][] = $row;
            }

            $this->skipTrivia();
            $c = $this->s[$this->i] ?? '';
            if ($c === ',') {
                $this->i++;

                continue;
            }
            if ($c === ';') {
                $this->i++;

                return;
            }
            if ($c === '') {
                return;
            }
            throw $this->fail('values_syntax');
        }
    }

    // ------------------------------------------------------------------ values

    private function readValue(bool $keep): ?string
    {
        $c = $this->s[$this->i] ?? '';

        if ($c === "'" || $c === '"') {
            return $this->readString($keep);
        }

        if ($c === '_' && preg_match('/\G_[A-Za-z0-9]+/', $this->s, $m, 0, $this->i) === 1) {
            $this->i += strlen($m[0]);
            $this->skipTrivia();
            $next = $this->s[$this->i] ?? '';
            if ($next === "'" || $next === '"') {
                return $this->readString($keep);
            }
            if (str_starts_with(substr($this->s, $this->i, 2), '0x')) {
                return $this->readHex($keep);
            }
            throw $this->fail('unsupported_value');
        }

        if (($c === 'N' || $c === 'n') && ($this->s[$this->i + 1] ?? '') === "'") {
            $this->i++;

            return $this->readString($keep);
        }

        if ($c === '0' && in_array($this->s[$this->i + 1] ?? '', ['x', 'X'], true)) {
            return $this->readHex($keep);
        }

        if (($c === 'X' || $c === 'x') && ($this->s[$this->i + 1] ?? '') === "'") {
            if (preg_match("/\\GX'([0-9A-Fa-f]*)'/i", $this->s, $m, 0, $this->i) !== 1 || strlen($m[1]) % 2 !== 0) {
                throw $this->fail('unsupported_value');
            }
            $this->i += strlen($m[0]);

            return $keep ? (string) hex2bin($m[1]) : null;
        }

        if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $this->s, $m, 0, $this->i) === 1) {
            $this->i += strlen($m[0]);

            return $keep ? $m[0] : null;
        }

        if (preg_match('/\G(NULL|TRUE|FALSE)(?![A-Za-z0-9_(])/i', $this->s, $m, 0, $this->i) === 1) {
            $this->i += strlen($m[0]);

            return match (strtoupper($m[0])) {
                'NULL' => null,
                'TRUE' => '1',
                default => '0',
            };
        }

        throw $this->fail('unsupported_value');
    }

    private function readHex(bool $keep): ?string
    {
        if (preg_match('/\G0[xX]([0-9A-Fa-f]+)/', $this->s, $m, 0, $this->i) !== 1 || strlen($m[1]) % 2 !== 0) {
            throw $this->fail('unsupported_value');
        }
        $this->i += strlen($m[0]);

        return $keep ? (string) hex2bin($m[1]) : null;
    }

    private function readString(bool $keep): ?string
    {
        if (! $keep) {
            $this->i = $this->skipQuoted($this->i);

            return null;
        }

        $q = $this->s[$this->i];
        $out = '';
        $j = $this->i + 1;

        while (true) {
            if ($j >= $this->n) {
                throw $this->fail('unterminated_quote');
            }
            $len = strcspn($this->s, $q . '\\', $j);
            $out .= substr($this->s, $j, $len);
            $j += $len;
            if ($j >= $this->n) {
                throw $this->fail('unterminated_quote');
            }

            if ($this->s[$j] === '\\') {
                $e = $this->s[$j + 1] ?? '';
                if ($e === '') {
                    throw $this->fail('unterminated_quote');
                }
                $out .= match ($e) {
                    '0' => "\0",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'Z' => "\x1A",
                    '%' => '\\%',
                    '_' => '\\_',
                    default => $e,
                };
                $j += 2;

                continue;
            }

            if (($this->s[$j + 1] ?? '') === $q) {
                $out .= $q;
                $j += 2;

                continue;
            }

            $this->i = $j + 1;

            return $out;
        }
    }

    // ------------------------------------------------------------------ scanning helpers

    private function word(): string
    {
        if (preg_match('/\G[A-Za-z_]+/', $this->s, $m, 0, $this->i) !== 1) {
            return '';
        }
        $this->i += strlen($m[0]);

        return strtoupper($m[0]);
    }

    private function identifier(): string
    {
        $name = '';

        while (true) {
            $this->skipTrivia();
            if (($this->s[$this->i] ?? '') === '`') {
                $end = strpos($this->s, '`', $this->i + 1);
                if ($end === false) {
                    throw $this->fail('unterminated_quote');
                }
                $name = substr($this->s, $this->i + 1, $end - $this->i - 1);
                $this->i = $end + 1;
            } else {
                if (preg_match('/\G[A-Za-z0-9_$]+/', $this->s, $m, 0, $this->i) !== 1) {
                    throw $this->fail('bad_identifier');
                }
                $name = $m[0];
                $this->i += strlen($name);
            }

            if (($this->s[$this->i] ?? '') === '.') {
                $this->i++;

                continue;
            }

            break;
        }

        if (preg_match('/^[A-Za-z0-9_$]{1,64}$/', $name) !== 1) {
            throw $this->fail('bad_identifier');
        }

        return $name;
    }

    /** Пропуск пробелов и комментариев (в т.ч. условных /*!40101 … *\/ — они не исполняются). */
    private function skipTrivia(): void
    {
        while ($this->i < $this->n) {
            $this->i += strspn($this->s, " \t\r\n", $this->i);
            if ($this->i >= $this->n) {
                return;
            }
            $c = $this->s[$this->i];

            if ($c === '#' || ($c === '-' && ($this->s[$this->i + 1] ?? '') === '-' && $this->isCommentGap($this->i + 2))) {
                $this->i = $this->endOfLine($this->i);
            } elseif ($c === '/' && ($this->s[$this->i + 1] ?? '') === '*') {
                $end = strpos($this->s, '*/', $this->i + 2);
                if ($end === false) {
                    throw $this->fail('unterminated_comment');
                }
                $this->i = $end + 2;
            } else {
                return;
            }
        }
    }

    private function isCommentGap(int $at): bool
    {
        return $at >= $this->n || in_array($this->s[$at], [' ', "\t", "\r", "\n"], true);
    }

    private function endOfLine(int $from): int
    {
        $nl = strpos($this->s, "\n", $from);

        return $nl === false ? $this->n : $nl + 1;
    }

    /** Индекс символа после закрывающей кавычки; $i указывает на открывающую (', " или `). */
    private function skipQuoted(int $i): int
    {
        $q = $this->s[$i];
        $stop = $q === '`' ? '`' : $q . '\\';
        $j = $i + 1;

        while (true) {
            if ($j >= $this->n) {
                throw $this->fail('unterminated_quote');
            }
            $j += strcspn($this->s, $stop, $j);
            if ($j >= $this->n) {
                throw $this->fail('unterminated_quote');
            }
            if ($this->s[$j] === '\\') {
                $j += 2;

                continue;
            }
            if (($this->s[$j + 1] ?? '') === $q) {
                $j += 2;

                continue;
            }

            return $j + 1;
        }
    }

    /** Пропуск выражения до «;» вне кавычек и комментариев (или до конца файла). */
    private function skipStatement(): void
    {
        $j = $this->i;

        while ($j < $this->n) {
            $j += strcspn($this->s, ";'\"`-#/", $j);
            if ($j >= $this->n) {
                break;
            }
            $c = $this->s[$j];

            if ($c === ';') {
                $this->i = $j + 1;

                return;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $this->skipQuoted($j);
            } elseif ($c === '#' || ($c === '-' && ($this->s[$j + 1] ?? '') === '-' && $this->isCommentGap($j + 2))) {
                $j = $this->endOfLine($j);
            } elseif ($c === '/' && ($this->s[$j + 1] ?? '') === '*') {
                $end = strpos($this->s, '*/', $j + 2);
                if ($end === false) {
                    $this->i = $j;
                    throw $this->fail('unterminated_comment');
                }
                $j = $end + 2;
            } else {
                $j++;
            }
        }

        $this->i = $this->n;
    }

    /** Индекс парной «)» для «(» в позиции $open. */
    private function matchParen(int $open): int
    {
        $depth = 0;
        $j = $open;

        while ($j < $this->n) {
            $j += strcspn($this->s, "()'\"`", $j);
            if ($j >= $this->n) {
                break;
            }
            $c = $this->s[$j];
            if ($c === '(') {
                $depth++;
                $j++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $j;
                }
                $j++;
            } else {
                $j = $this->skipQuoted($j);
            }
        }

        $this->i = $open;
        throw $this->fail('unbalanced_parenthesis');
    }

    /**
     * Имена столбцов из тела CREATE TABLE: определения верхнего уровня, которые
     * начинаются с «`имя`» (PRIMARY KEY / KEY / CONSTRAINT пропускаются).
     *
     * @return array<int,string>
     */
    private function columnsOfCreate(int $from, int $to): array
    {
        $columns = [];
        $depth = 0;
        $segmentStart = $from;
        $j = $from;

        $flush = function (int $end) use (&$columns, &$segmentStart): void {
            $segment = ltrim(substr($this->s, $segmentStart, $end - $segmentStart));
            if (($segment[0] ?? '') === '`' && ($close = strpos($segment, '`', 1)) !== false) {
                $name = substr($segment, 1, $close - 1);
                if (preg_match('/^[A-Za-z0-9_$]{1,64}$/', $name) !== 1) {
                    throw $this->fail('bad_identifier');
                }
                $columns[] = $name;
            }
        };

        while ($j < $to) {
            $j += strcspn($this->s, "(),'\"`", $j);
            if ($j >= $to) {
                break;
            }
            $c = $this->s[$j];
            if ($c === '(') {
                $depth++;
                $j++;
            } elseif ($c === ')') {
                $depth--;
                $j++;
            } elseif ($c === ',' && $depth === 0) {
                $flush($j);
                $segmentStart = ++$j;
            } elseif ($c === ',') {
                $j++;
            } else {
                $j = $this->skipQuoted($j);
            }
        }
        $flush($to);

        return $columns;
    }

    /** @return array<int,string> */
    private function columnList(int $from, int $to): array
    {
        $names = [];
        foreach (explode(',', substr($this->s, $from, $to - $from)) as $part) {
            $name = trim(trim($part), '`');
            if (preg_match('/^[A-Za-z0-9_$]{1,64}$/', $name) !== 1) {
                throw $this->fail('bad_identifier');
            }
            $names[] = $name;
        }

        return $names;
    }

    private function fail(string $reason): LegacySqlDumpException
    {
        $line = 1 + substr_count($this->s, "\n", 0, min($this->i, $this->n));

        return new LegacySqlDumpException($reason, $this->stmt, $line, $this->table);
    }
}
