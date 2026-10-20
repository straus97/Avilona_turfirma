<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * AOA-01-I1 / AO-04 — Admin technical log screen must not leak raw log text.
 *
 * Before this slice the screen printed the last 200 raw lines of
 * storage/logs/laravel.log. A single logged exception carries absolute
 * server paths (stack trace), SQL fragments and context JSON, so the screen
 * disclosed deployment structure and possibly secrets even though the log
 * file path itself had already been removed from the page (E3-A5).
 *
 * The synthetic log below is written to a throw-away storage directory, never
 * to the real storage/logs.
 */
class AdminTechnicalLogSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStoragePath;
    private string $tempStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = $this->app->storagePath();
        $this->tempStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avilona-log-test-' . bin2hex(random_bytes(6));
        File::makeDirectory($this->tempStorage . DIRECTORY_SEPARATOR . 'logs', 0777, true);
        $this->app->useStoragePath($this->tempStorage);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->tempStorage);

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => Role::ADMIN], ['description' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function writeLog(string $contents): void
    {
        file_put_contents($this->tempStorage . '/logs/laravel.log', $contents);
    }

    private function syntheticExceptionLog(): string
    {
        return <<<'LOG'
[2026-10-20 09:15:01] local.ERROR: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'demo.missing' doesn't exist (Connection: mysql, SQL: select * from `missing` where `email` = victim@example.test) {"exception":"[object] (Illuminate\\Database\\QueryException(code: 42S02): SQLSTATE[42S02] at C:\\wamp\\www\\Avilona_turfirma\\vendor\\laravel\\framework\\src\\Illuminate\\Database\\Connection.php:822)
[stacktrace]
#0 C:\\wamp\\www\\Avilona_turfirma\\app\\Http\\Controllers\\SecretController.php(42): Illuminate\\Database\\Connection->run()
#1 /var/www/avilona/app/Services/Hidden.php(10): doThing()
"}
[2026-10-20 09:16:02] local.WARNING: Login throttled for token=abcDEF1234567890abcDEF1234567890abcDEF12 password=hunter2 {"ip":"203.0.113.9","path":"D:\\private\\folder"} []
[2026-10-20 09:17:03] local.INFO: Plain informational message
LOG;
    }

    public function test_admin_page_does_not_render_absolute_paths_from_stack_traces(): void
    {
        $this->writeLog($this->syntheticExceptionLog());

        $html = $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString('C:\\wamp', $html);
        $this->assertStringNotContainsString('C:\\\\wamp', $html);
        $this->assertStringNotContainsString('wamp\\www', $html);
        $this->assertStringNotContainsString('SecretController', $html);
        $this->assertStringNotContainsString('/var/www/avilona', $html);
        $this->assertStringNotContainsString('D:\\private', $html);
        $this->assertStringNotContainsString('[stacktrace]', $html);
        $this->assertStringNotContainsString(sys_get_temp_dir(), $html);
    }

    public function test_admin_page_does_not_render_secrets_emails_or_context_payloads(): void
    {
        $this->writeLog($this->syntheticExceptionLog());

        $html = $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString('hunter2', $html);
        $this->assertStringNotContainsString('abcDEF1234567890abcDEF1234567890abcDEF12', $html);
        $this->assertStringNotContainsString('victim@example.test', $html);
        $this->assertStringNotContainsString('203.0.113.9', $html);
        $this->assertStringNotContainsString('select * from', $html);
    }

    public function test_admin_page_still_summarises_entries_safely(): void
    {
        $this->writeLog($this->syntheticExceptionLog());

        $html = $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringContainsString('20.10.2026 09:15:01', $html);
        $this->assertStringContainsString('Plain informational message', $html);
        $this->assertStringContainsString('ERROR', $html);
        $this->assertStringContainsString('WARNING', $html);
        $this->assertStringContainsString('SQLSTATE[42S02]', $html);
    }

    public function test_message_text_is_escaped(): void
    {
        $this->writeLog("[2026-10-20 09:00:00] local.ERROR: <script>alert('x')</script> boom\n");

        $html = $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_missing_log_file_shows_empty_state(): void
    {
        $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))
            ->assertOk()
            ->assertSee('Записей в техническом журнале нет');
    }

    public function test_entries_are_bounded_and_newest_first(): void
    {
        $lines = '';
        for ($i = 1; $i <= 250; $i++) {
            $lines .= sprintf("[2026-10-20 09:%02d:%02d] local.INFO: entry-number-%03d\n", intdiv($i, 60), $i % 60, $i);
        }
        $this->writeLog($lines);

        $html = $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))->assertOk()->getContent();

        $this->assertStringContainsString('entry-number-250', $html);
        $this->assertStringNotContainsString('entry-number-001', $html);
        $this->assertLessThan(strpos($html, 'entry-number-249'), strpos($html, 'entry-number-250'));
        $this->assertLessThanOrEqual(100, substr_count($html, 'entry-number-'));
    }

    public function test_viewing_the_page_never_modifies_the_log_file(): void
    {
        $this->writeLog($this->syntheticExceptionLog());
        $before = file_get_contents($this->tempStorage . '/logs/laravel.log');

        $this->actingAs($this->admin())->get(route('cabinet.admin.logs'))->assertOk();

        $this->assertSame($before, file_get_contents($this->tempStorage . '/logs/laravel.log'));
    }
}
