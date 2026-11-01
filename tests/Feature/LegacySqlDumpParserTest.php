<?php

namespace Tests\Feature;

use App\Services\Content\Legacy\LegacySqlDumpException;
use App\Services\Content\Legacy\LegacySqlDumpParser;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsSyntheticLegacyDump;
use Tests\TestCase;

/**
 * NOV-PUBLIC-04A: разбор SQL-выгрузки как ДАННЫХ. Только синтетические строки.
 */
class LegacySqlDumpParserTest extends TestCase
{
    use BuildsSyntheticLegacyDump;

    protected function tearDown(): void
    {
        $this->tearDownSynthetic();
        parent::tearDown();
    }

    private function parse(string $sql, ?callable $want = null): array
    {
        return (new LegacySqlDumpParser())->parse($sql, $want ?? fn () => ['*']);
    }

    private function refusal(string $sql, ?callable $want = null): LegacySqlDumpException
    {
        try {
            $this->parse($sql, $want);
        } catch (LegacySqlDumpException $e) {
            return $e;
        }
        $this->fail('Ожидался отказ разбора.');
    }

    public function test_parses_escapes_nulls_numbers_multirow_and_semicolons_inside_strings(): void
    {
        $sql = $this->dumpHeader() . "CREATE TABLE `articles` (\n  `id` int(11) NOT NULL,\n  `title` varchar(255) COMMENT 'a, (b); c',\n  `content` text,\n  PRIMARY KEY (`id`),\n  KEY `k` (`title`)\n) ENGINE=InnoDB;\n"
            . "-- comment with 'quote\n"
            . "INSERT INTO `articles` (`id`, `title`, `content`) VALUES\n"
            . "(1, 'It''s; a \\'test\\'', 'line1\\nline2 \\\\ done'),\n"
            . "(2, NULL, '--not a comment /* nor this */'),\n"
            . "(-3, 'x', _utf8mb4'typed');\n" . $this->dumpFooter();

        $parsed = $this->parse($sql);
        $table = $parsed['tables']['articles'];

        $this->assertSame(['id', 'title', 'content'], $table['columns']);
        $this->assertSame(3, $table['row_count']);
        $this->assertSame("It's; a 'test'", $table['rows'][0]['title']);
        $this->assertSame("line1\nline2 \\ done", $table['rows'][0]['content']);
        $this->assertNull($table['rows'][1]['title']);
        $this->assertSame('--not a comment /* nor this */', $table['rows'][1]['content']);
        $this->assertSame('-3', $table['rows'][2]['id']);
        $this->assertSame('typed', $table['rows'][2]['content']);
        $this->assertSame('phpMyAdmin SQL Dump', $parsed['header']['generator']);
        $this->assertSame('Jun 24, 2026 at 04:45 PM', $parsed['header']['generation_time']);
    }

    public function test_values_are_materialised_only_for_requested_columns(): void
    {
        $sql = $this->table('users', ['id', 'email', 'password'], [['1', 'PRIVATE-MARKER-A', 'PRIVATE-MARKER-B'], ['2', 'PRIVATE-MARKER-C', 'PRIVATE-MARKER-D']])
            . $this->table('news', ['id', 'link', 'description'], [['1', 'https://x.invalid', 'body']]);

        $parsed = $this->parse($sql, fn (string $t) => $t === 'news' ? ['link'] : null);

        $this->assertSame(2, $parsed['tables']['users']['row_count']);
        $this->assertSame([], $parsed['tables']['users']['rows']);
        $this->assertSame([['link' => 'https://x.invalid']], $parsed['tables']['news']['rows']);
        $this->assertStringNotContainsString('PRIVATE-MARKER', json_encode($parsed));
    }

    public function test_sql_is_never_executed_and_no_database_is_touched(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->parse($this->dumpHeader() . "DROP TABLE IF EXISTS `users`;\nALTER TABLE `users` ADD COLUMN `x` int;\n" . $this->table('articles', ['id', 'title'], [['1', 'a']]));

        $this->assertSame([], $queries, 'разбор (в т.ч. DROP/ALTER из выгрузки) не должен обращаться к базе данных');
    }

    public function test_dangerous_or_unsupported_statements_are_refused(): void
    {
        $base = $this->table('articles', ['id', 'title'], [['1', 'a']]);
        $cases = [
            'UPDATE' => "UPDATE `articles` SET `title` = 'x';",
            'DELETE' => 'DELETE FROM `articles`;',
            'TRUNCATE' => 'TRUNCATE TABLE `articles`;',
            'GRANT' => "GRANT ALL ON *.* TO 'x'@'%';",
            'SELECT' => 'SELECT 1;',
            'DELIMITER' => 'DELIMITER $$',
            'TRIGGER' => 'CREATE TRIGGER t BEFORE INSERT ON articles FOR EACH ROW SET NEW.id = 1;',
            'VIEW' => 'CREATE VIEW v AS SELECT 1;',
            'USER' => "CREATE USER 'a'@'b';",
            'INSERT-SELECT' => 'INSERT INTO `articles` (`id`, `title`) SELECT 1, 2;',
            'DUPLICATE' => "INSERT INTO `articles` (`id`, `title`) VALUES (1, 'a') ON DUPLICATE KEY UPDATE `title` = 'b';",
            'FUNCTION' => 'INSERT INTO `articles` (`id`, `title`) VALUES (1, NOW());',
        ];

        foreach ($cases as $label => $statement) {
            $e = $this->refusal($base . $statement . "\n");
            $this->assertInstanceOf(LegacySqlDumpException::class, $e, $label);
        }
    }

    public function test_malformed_sources_are_refused_with_safe_messages(): void
    {
        $cols = "CREATE TABLE `articles` (`id` int, `title` text);\n";
        $cases = [
            'unterminated string' => $cols . "INSERT INTO `articles` (`id`, `title`) VALUES (1, 'PRIVATE-MARKER-OPEN);\n",
            'too few values' => $cols . "INSERT INTO `articles` (`id`, `title`) VALUES (1);\n",
            'too many values' => $cols . "INSERT INTO `articles` (`id`, `title`) VALUES (1, 'PRIVATE-MARKER-X', 3);\n",
            'unbalanced parenthesis' => $cols . "INSERT INTO `articles` (`id`, `title`) VALUES (1, 'a'\n",
            'no rows after VALUES' => $cols . "INSERT INTO `articles` (`id`, `title`) VALUES ;\n",
            'insert without columns' => "INSERT INTO `articles` VALUES (1, 'PRIVATE-MARKER-Y');\n",
            'unterminated comment' => $cols . "/* PRIVATE-MARKER-COMMENT\n",
            'bad identifier' => "INSERT INTO `a b;DROP` (`id`) VALUES (1);\n",
            'garbage' => "%%% PRIVATE-MARKER-GARBAGE %%%\n",
            'missing separator' => $cols . "INSERT INTO `articles` (`id`, `title`) VALUES (1, 'a') (2, 'b');\n",
            'unterminated create' => "CREATE TABLE `articles` (`id` int, `title` text\n",
        ];

        foreach ($cases as $label => $sql) {
            $e = $this->refusal($sql);
            $this->assertStringNotContainsString('PRIVATE-MARKER', $e->getMessage(), $label);
            $this->assertMatchesRegularExpression('/\(statement #\d+, line \d+/', $e->getMessage(), $label);
        }
    }

    public function test_file_size_limit_is_enforced(): void
    {
        $e = $this->refusal(str_repeat(' ', LegacySqlDumpParser::MAX_BYTES + 1));

        $this->assertSame('file_too_large', $e->reason);
    }

    public function test_hex_literals_and_bom_are_handled_and_odd_hex_is_refused(): void
    {
        $sql = "\xEF\xBB\xBF" . "CREATE TABLE `articles` (`id` int, `title` text);\nINSERT INTO `articles` VALUES (1, 0x616263);\n";
        $this->assertSame('abc', $this->parse($sql)['tables']['articles']['rows'][0]['title']);

        $this->assertSame('unsupported_value', $this->refusal("CREATE TABLE `a` (`id` int);\nINSERT INTO `a` VALUES (0xABC);\n")->reason);
    }
}
