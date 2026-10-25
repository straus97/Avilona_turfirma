<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\Convergence\OfflineIsolationObserver;
use App\Services\Inquiries\Convergence\OfflineSnapshotContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I6 — контракт офлайн-снимка на синтетических данных (SQLite :memory:).
 *
 * Здесь проверяются те части, которые SQLite способен показать: схема, отпечатки, синтетичность,
 * сравнение с манифестом и честные границы (отпечаток не видит ABA). Ответ сервера о собственной
 * идентичности проверяется на подставных ответах; настоящий MySQL — отдельным прогоном на
 * одноразовом экземпляре (docs/AOA-03-I6).
 */
class InquiryOfflineSnapshotContractTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private VerifiedQaConnection $qa;
    private OfflineSnapshotContract $contract;
    private User $a;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('VerifiedQaConnection refuses the test default connection on MySQL; covered by the disposable-MySQL harness (docs/AOA-03-I6).');
        }

        Mail::fake();
        Notification::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $name = config('database.default');
        config(["database.connections.{$name}.qa_disposable" => true]);
        $this->qa = VerifiedQaConnection::verify($name);
        $this->contract = new OfflineSnapshotContract($this->qa);

        $this->a = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
    }

    private function populate(): array
    {
        $source = $this->inquiryFor($this->a, $this->tourist, ['external_id' => '700001']);
        $inquiry = Inquiry::factory()->externalIntake($source)->assignedTo($this->a)->create();
        $independent = $this->independentInquiry($this->a);
        $booking = $this->bookingOf($this->tourist, $this->a);

        return compact('source', 'inquiry', 'independent', 'booking');
    }

    // ---------------------------------------------------------------- идентичность

    public function test_identity_is_confirmed_for_the_private_memory_database_and_is_stable(): void
    {
        $first = $this->contract->identity();

        $this->assertTrue($first['ok']);
        $this->assertSame([], $first['problems']);
        $this->assertSame(':memory:', $first['database']);
        $this->assertNull($first['port']);
        $this->assertSame($first['digest'], $this->contract->identity()['digest']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first['digest']);
    }

    #[DataProvider('serverAnswers')]
    public function test_a_server_answer_that_disagrees_with_the_configuration_is_refused(array $answer, array $config, array $expectedProblems): void
    {
        $db = new class($answer) {
            public function __construct(private array $answer)
            {
            }

            public function selectOne(string $sql): object
            {
                return (object) $this->answer;
            }

            public function scalar(string $sql): string
            {
                return 'uuid-1';
            }
        };

        $method = new ReflectionMethod(OfflineSnapshotContract::class, 'networkIdentity');
        $problems = [];
        $args = [$db, $config, &$problems];
        $result = $method->invokeArgs($this->contract, $args);

        $this->assertSame($expectedProblems, $args[2]);
        $this->assertSame((string) $answer['db'], $result[0]);
    }

    public static function serverAnswers(): array
    {
        $good = ['db' => 'aoa03_qa_a', 'port' => 3412, 'version' => '9.7.1', 'datadir' => 'D:/qa/data/'];
        $config = ['database' => 'aoa03_qa_a', 'port' => 3412];

        return [
            'consistent server' => [$good, $config, []],
            'server names another database' => [array_merge($good, ['db' => 'aoa03_qa_b']), $config, ['server_reports_a_different_database']],
            'port forwarded from elsewhere' => [array_merge($good, ['port' => 3499]), $config, ['server_reports_a_different_port']],
            'wamp server behind the configured port' => [array_merge($good, ['port' => 3306]), $config, ['server_reports_a_different_port', 'wamp_port_reported']],
            'canonical database behind the configured name' => [array_merge($good, ['db' => 'turfirma_rebuild_v4']), array_merge($config, ['database' => 'turfirma_rebuild_v4']), ['canonical_database_reported']],
            'no database selected' => [array_merge($good, ['db' => '']), $config, ['server_reports_a_different_database']],
        ];
    }

    // ---------------------------------------------------------------- схема

    public function test_schema_is_compatible_and_fingerprint_follows_the_layout(): void
    {
        $before = $this->contract->schema();

        $this->assertTrue($before['ok']);
        $this->assertTrue($before['inquiry_migration_recorded']);

        Schema::table('bookings', fn ($table) => $table->string('i6_probe_column', 8)->nullable());

        $this->assertNotSame($before['fingerprint'], $this->contract->schema()['fingerprint']);
    }

    public function test_missing_inquiry_migration_record_or_table_is_a_schema_problem(): void
    {
        DB::table('migrations')->where('migration', '2026_10_24_000000_create_inquiries_table')->delete();

        $schema = $this->contract->schema();

        $this->assertFalse($schema['ok']);
        $this->assertContains('inquiry_migration_not_recorded', $schema['problems']);
    }

    // ---------------------------------------------------------------- синтетичность

    public function test_synthetic_check_accepts_reserved_domains_only_and_reports_numbers(): void
    {
        $this->populate();
        DB::table('users')->where('id', $this->a->id)->update(['email' => 'Mixed.Case@EXAMPLE.ORG']);

        $result = $this->contract->syntheticOnly();

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['non_reserved_addresses']);
        $this->assertGreaterThan(0, $result['checked']['users.email']);
    }

    public function test_a_real_looking_address_makes_the_snapshot_non_synthetic_without_echoing_it(): void
    {
        $this->populate();
        DB::table('users')->where('id', $this->a->id)->update(['email' => 'real.person.sentinel@gmail.com']);
        DB::table('incoming_inquiries')->update(['client_email' => 'client.sentinel@mail.ru']);

        $result = $this->contract->syntheticOnly();

        $this->assertFalse($result['ok']);
        $this->assertSame(2, $result['non_reserved_addresses']);
        $json = json_encode($result);
        $this->assertStringNotContainsString('sentinel', $json);
        $this->assertStringNotContainsString('gmail', $json);
    }

    // ---------------------------------------------------------------- манифест

    public function test_untouched_snapshot_matches_its_manifest_and_manifest_holds_only_hashes_and_numbers(): void
    {
        $this->populate();
        $manifest = $this->contract->seal();

        $compare = $this->contract->compare($manifest);

        $this->assertTrue($compare['intact']);
        $this->assertSame([], $compare['changed_tables']);
        $this->assertSame(1, $compare['source_rows']);
        $this->assertSame(['audit_events', 'bookings', 'incoming_inquiries', 'role_user', 'roles', 'users'], array_keys($manifest['tables']));
        $this->assertStringNotContainsString('example', json_encode($manifest));
    }

    #[DataProvider('protectedMutations')]
    public function test_any_change_of_a_protected_table_is_detected_by_name(string $table, callable $mutate): void
    {
        $fixture = $this->populate();
        $manifest = $this->contract->seal();

        $mutate($this, $fixture);
        $compare = $this->contract->compare($manifest);

        $this->assertFalse($compare['intact']);
        $this->assertContains($table, $compare['changed_tables']);
    }

    public static function protectedMutations(): array
    {
        return [
            'source field updated' => ['incoming_inquiries', fn () => DB::table('incoming_inquiries')->update(['close_reason' => 'x'])],
            'source row added' => ['incoming_inquiries', fn (self $t) => $t->inquiryFor(null, null, ['external_id' => '700099'])],
            'source row deleted' => ['incoming_inquiries', function (): void {
                DB::table('inquiries')->delete();
                DB::table('incoming_inquiries')->delete();
            }],
            'user updated' => ['users', fn (self $t) => DB::table('users')->where('id', $t->managerId())->update(['name' => 'Changed'])],
            'role link removed' => ['role_user', fn () => DB::table('role_user')->limit(1)->delete()],
            'booking updated' => ['bookings', fn () => DB::table('bookings')->update(['nights' => 9])],
            'audit event added' => ['audit_events', function (): void {
                DB::table('audit_events')->insert([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(), 'event_type' => 'synthetic.probe', 'subject_type' => 'x',
                    'subject_id' => 1, 'occurred_at' => now(),
                ]);
            }],
        ];
    }

    public function managerId(): int
    {
        return $this->a->id;
    }

    public function test_fingerprint_alone_does_not_see_an_aba_write(): void
    {
        $this->populate();
        $manifest = $this->contract->seal();

        $original = DB::table('incoming_inquiries')->value('close_reason');
        DB::table('incoming_inquiries')->update(['close_reason' => 'temporary']);
        DB::table('incoming_inquiries')->update(['close_reason' => $original]);

        // Документируемая граница: содержимое то же, значит отпечаток равен. На MySQL этот случай
        // ловят счётчики записи performance_schema (OfflineSnapshotContract::protectedWriteCounters).
        $this->assertTrue($this->contract->compare($manifest)['intact']);
        $this->assertNull($this->contract->protectedWriteCounters(), 'SQLite offers no write counters: unobservable, not zero');
    }

    public function test_preexisting_inquiries_must_stay_byte_identical_but_new_ones_are_allowed(): void
    {
        $fixture = $this->populate();
        $manifest = $this->contract->seal();

        $newSource = $this->inquiryFor(null, null, ['external_id' => '700050']);
        // новый Inquiry меняет и защищённый набор (источник), поэтому сравниваем только «ранее существовавшие».
        Inquiry::factory()->externalIntake($newSource)->create();
        $this->assertTrue($this->contract->compare($manifest)['preexisting_inquiries_unchanged']);

        DB::table('inquiries')->where('id', $fixture['inquiry']->id)->update(['internal_notes' => 'edited behind the back']);
        $this->assertFalse($this->contract->compare($manifest)['preexisting_inquiries_unchanged']);
        $this->assertFalse($this->contract->compare($manifest)['intact']);
    }

    public function test_deleting_a_preexisting_inquiry_is_detected(): void
    {
        $fixture = $this->populate();
        $manifest = $this->contract->seal();

        DB::table('inquiries')->where('id', $fixture['independent']->id)->delete();

        $this->assertFalse($this->contract->compare($manifest)['preexisting_inquiries_unchanged']);
    }

    public function test_schema_change_after_sealing_breaks_the_manifest(): void
    {
        $this->populate();
        $manifest = $this->contract->seal();

        Schema::table('inquiries', fn ($table) => $table->string('i6_probe_column', 8)->nullable());

        $compare = $this->contract->compare($manifest);
        $this->assertFalse($compare['schema_unchanged']);
        $this->assertFalse($compare['intact']);
    }

    #[DataProvider('invalidManifests')]
    public function test_a_foreign_or_malformed_manifest_never_counts_as_intact(callable $tamper): void
    {
        $this->populate();
        $manifest = $tamper($this->contract->seal());

        $compare = $this->contract->compare($manifest);

        $this->assertFalse($compare['intact']);
        $this->assertFalse($compare['manifest_valid']);
    }

    public static function invalidManifests(): array
    {
        return [
            'unknown version' => [fn (array $m) => array_merge($m, ['version' => 99])],
            'other engine' => [fn (array $m) => array_merge($m, ['driver' => 'mysql'])],
            'other database' => [fn (array $m) => array_merge($m, ['database' => 'aoa03_qa_other'])],
            'no source table entry' => [function (array $m): array {
                unset($m['tables']['incoming_inquiries']);

                return $m;
            }],
            'no digest' => [function (array $m): array {
                unset($m['identity_digest']);

                return $m;
            }],
            'empty' => [fn () => []],
        ];
    }

    // ---------------------------------------------------------------- наблюдатель изоляции

    public function test_private_memory_is_reported_as_isolated_by_construction_and_is_labelled_as_such(): void
    {
        $observer = new OfflineIsolationObserver($this->qa);

        $this->assertTrue($observer->isPrivateMemory());
        $observation = $observer->observe();

        $this->assertSame('sqlite_memory_private', $observation->driver);
        $this->assertSame(0, $observation->otherSessions);
        $this->assertSame(0, $observation->otherOpenTransactions);
    }

    public function test_no_booking_user_or_source_is_created_by_sealing(): void
    {
        $this->populate();
        $counts = fn () => [Booking::count(), User::count(), IncomingInquiry::count(), Inquiry::count(), DB::table('audit_events')->count()];
        $before = $counts();

        $this->contract->seal();
        $this->contract->identity();
        $this->contract->schema();
        $this->contract->syntheticOnly();

        $this->assertSame($before, $counts());
    }
}
