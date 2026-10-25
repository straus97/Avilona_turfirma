<?php

namespace Tests\Feature;

use App\Models\IncomingInquiry;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\Convergence\DatabaseFreezeObserver;
use App\Services\Inquiries\Convergence\FreezeAttestation;
use App\Services\Inquiries\Convergence\FreezeObservation;
use App\Services\Inquiries\Convergence\InquiryFreezeGate;
use App\Services\Inquiries\Convergence\WriterInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I5 — оценка окна заморозки, замеры из базы и перечень писателей.
 *
 * Важно, что здесь НЕ доказывается: SQLite не умеет показывать чужие сессии, транзакции и счётчики
 * записи, поэтому реальный DatabaseFreezeObserver проверяется здесь только в той части, где он
 * способен работать (очередь, зависшие импорты, честное «не наблюдается»). Его MySQL-часть
 * проверена отдельным прогоном на одноразовом MySQL (docs/AOA-03-I5). Логика самой оценки
 * (InquiryFreezeGate) — реальный код на заданных замерах.
 */
class InquiryFreezeGateTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private function clean(array $overrides = []): FreezeObservation
    {
        $fields = array_merge([
            'driver' => 'mysql',
            'otherSessions' => 0,
            'otherOpenTransactions' => 0,
            'importJobsQueued' => 0,
            'importJobsReserved' => 0,
            'importsInFlight' => 0,
            'sourceWriteCounters' => ['insert' => 5, 'update' => 9, 'delete' => 0],
        ], $overrides);

        return new FreezeObservation(...$fields);
    }

    private function evaluate(?FreezeObservation $before = null, ?FreezeObservation $after = null, ?FreezeAttestation $attestation = null, bool $unchanged = true): array
    {
        return (new InquiryFreezeGate())->evaluate($before ?? $this->clean(), $after ?? $this->clean(), $attestation ?? FreezeAttestation::all(), $unchanged);
    }

    public function test_a_clean_window_with_every_attestation_is_quiescent_but_never_authorizes_a_cutover(): void
    {
        $result = $this->evaluate();

        $this->assertTrue($result['window_quiescent']);
        $this->assertSame([], $result['blockers']);
        $this->assertSame([], $result['unverifiable']);
        $this->assertFalse($result['production_cutover_authorized']);
        $this->assertSame(array_keys(InquiryFreezeGate::CUTOVER_BLOCKERS), array_keys($result['cutover_blockers']));
        $this->assertArrayHasKey('lossless_intake_unproven', $result['cutover_blockers']);
        $this->assertArrayHasKey('no_write_barrier_in_application', $result['cutover_blockers']);
    }

    #[DataProvider('blockingObservations')]
    public function test_every_sign_of_activity_blocks_the_window(array $override, string $blocker): void
    {
        foreach (['before', 'after'] as $when) {
            $observation = $this->clean($override);
            $result = $when === 'before' ? $this->evaluate($observation) : $this->evaluate(null, $observation);

            $this->assertFalse($result['window_quiescent'], "activity at {$when} must block");
            $this->assertContains($blocker, $result['blockers']);
        }
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function blockingObservations(): array
    {
        return [
            'foreign session' => [['otherSessions' => 1], 'other_sessions_present'],
            'open transaction at freeze start' => [['otherOpenTransactions' => 1], 'open_transaction_present'],
            'queued import job' => [['importJobsQueued' => 2], 'import_jobs_queued_or_reserved'],
            'reserved import job' => [['importJobsQueued' => 1, 'importJobsReserved' => 1], 'import_jobs_queued_or_reserved'],
            'import in flight' => [['importsInFlight' => 1], 'import_in_flight'],
        ];
    }

    public function test_a_transaction_open_only_at_the_start_still_blocks_even_if_it_finished_before_the_second_sample(): void
    {
        $result = $this->evaluate($this->clean(['otherOpenTransactions' => 1]), $this->clean());

        $this->assertFalse($result['window_quiescent']);
        $this->assertContains('open_transaction_present', $result['blockers']);
    }

    #[DataProvider('writeDeltas')]
    public function test_any_write_counter_movement_in_the_window_blocks_it(array $after): void
    {
        $result = $this->evaluate(null, $this->clean(['sourceWriteCounters' => $after]));

        $this->assertFalse($result['window_quiescent']);
        $this->assertContains('source_write_observed_in_window', $result['blockers']);
    }

    /** @return array<string, array{0: array<string, int>}> */
    public static function writeDeltas(): array
    {
        return [
            'insert' => [['insert' => 6, 'update' => 9, 'delete' => 0]],
            'update (also what a cascaded SET NULL looks like)' => [['insert' => 5, 'update' => 10, 'delete' => 0]],
            'delete' => [['insert' => 5, 'update' => 9, 'delete' => 1]],
            'counter reset (server restart)' => [['insert' => 0, 'update' => 0, 'delete' => 0]],
        ];
    }

    #[DataProvider('unobservableFields')]
    public function test_whatever_cannot_be_observed_fails_closed_instead_of_counting_as_clean(string $field, string $reason): void
    {
        foreach (['before', 'after'] as $when) {
            $observation = $this->clean([$field => null]);
            $result = $when === 'before' ? $this->evaluate($observation) : $this->evaluate(null, $observation);

            $this->assertFalse($result['window_quiescent']);
            $this->assertContains($reason, $result['unverifiable']);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function unobservableFields(): array
    {
        return [
            'sessions' => ['otherSessions', 'sessions_not_observable'],
            'transactions' => ['otherOpenTransactions', 'transactions_not_observable'],
            'queue' => ['importJobsQueued', 'queue_not_observable'],
            'write counters' => ['sourceWriteCounters', 'write_counters_not_observable'],
        ];
    }

    public function test_a_source_that_changed_during_the_scan_blocks_the_window(): void
    {
        $result = $this->evaluate(unchanged: false);

        $this->assertFalse($result['window_quiescent']);
        $this->assertContains('source_changed_during_scan', $result['blockers']);
    }

    public function test_missing_operator_attestations_block_and_are_listed_as_attested_not_verified(): void
    {
        $partial = new FreezeAttestation([FreezeAttestation::INGRESS_STOPPED]);
        $result = $this->evaluate(attestation: $partial);

        $this->assertFalse($result['window_quiescent']);
        $this->assertContains('attestation_incomplete', $result['blockers']);
        $this->assertSame([FreezeAttestation::INGRESS_STOPPED], $result['attested']);
        $this->assertSame(
            [FreezeAttestation::QUEUE_WORKERS_STOPPED, FreezeAttestation::SCHEDULER_PAUSED, FreezeAttestation::NO_MANUAL_SQL],
            $result['attestation_missing']
        );
        $this->assertStringContainsString('not machine-verified', $result['attestation_note']);
        $this->assertFalse($this->evaluate(attestation: FreezeAttestation::none())['window_quiescent']);
    }

    public function test_an_unknown_attestation_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FreezeAttestation(['maintenance_mode_enabled']);
    }

    public function test_laravel_maintenance_mode_is_not_an_accepted_freeze_mechanism(): void
    {
        $this->assertNotContains('maintenance_mode_enabled', FreezeAttestation::KEYS);
        $this->assertNotContains('maintenance', array_map('strtolower', FreezeAttestation::KEYS));
    }

    // ---------------------------------------------------------------- перечень писателей

    public function test_every_writer_is_stopped_by_a_known_mechanism_and_every_mechanism_is_needed(): void
    {
        foreach (WriterInventory::WRITERS as $writer) {
            $this->assertContains($writer['stopped_by'], FreezeAttestation::KEYS, $writer['id']);
            $this->assertNotSame('', $writer['note']);
        }

        $required = WriterInventory::requiredAttestations();
        sort($required);
        $all = FreezeAttestation::KEYS;
        sort($all);

        $this->assertSame($all, $required, 'каждый заявляемый механизм нужен хотя бы одному писателю и наоборот');
    }

    public function test_the_inventory_names_the_documented_writer_families(): void
    {
        $ids = array_column(WriterInventory::WRITERS, 'id');

        foreach (['P1', 'P2-P4', 'P5-P11', 'P13a', 'P13b', 'P14', 'Q', 'S'] as $expected) {
            $this->assertContains($expected, $ids);
        }
    }

    /**
     * Защита от дрейфа: пользователь удаляется ровно в пяти документированных местах контроллеров
     * (каждое — ON DELETE SET NULL по incoming_inquiries без движения updated_at). Новое место
     * удаления должно попасть в перечень писателей и в заявление ingress_stopped.
     */
    public function test_user_hard_delete_exists_only_at_the_documented_call_sites(): void
    {
        $sites = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*|^\s*\*[^\n]*~ms', '', $file->getContents());
            $count = preg_match_all('/\$user->(delete|forceDelete)\(|User::(destroy|query\(\)[^;]*->delete)\(|table\(\'users\'\)[^;]*->delete\(/', $code);

            if ($count > 0) {
                $sites[str_replace([app_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname())] = $count;
            }
        }

        ksort($sites);

        $this->assertSame([
            'Http/Controllers/Admin/AdminController.php' => 2,
            'Http/Controllers/Cabinet/CabinetController.php' => 1,
            'Http/Controllers/Manager/ManagerController.php' => 1,
            'Http/Controllers/ProfileController.php' => 1,
        ], $sites);
    }

    // ---------------------------------------------------------------- DatabaseFreezeObserver (часть, доступная на SQLite)

    private function observer(): DatabaseFreezeObserver
    {
        $name = config('database.default');
        config(["database.connections.{$name}.qa_disposable" => true]);

        return new DatabaseFreezeObserver(VerifiedQaConnection::verify($name));
    }

    public function test_sqlite_cannot_show_sessions_transactions_or_write_counters_and_says_so(): void
    {
        $observation = $this->observer()->observe();

        $this->assertNull($observation->otherSessions);
        $this->assertNull($observation->otherOpenTransactions);
        $this->assertNull($observation->sourceWriteCounters);

        $gate = (new InquiryFreezeGate())->evaluate($observation, $observation, FreezeAttestation::all(), true);
        $this->assertFalse($gate['window_quiescent'], 'SQLite never yields a proven window');
        $this->assertContains('sessions_not_observable', $gate['unverifiable']);
    }

    public function test_import_jobs_in_the_database_queue_are_seen_queued_and_reserved(): void
    {
        $payload = json_encode(['displayName' => 'App\\Jobs\\ImportTourvisorInquiry', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']);
        $other = json_encode(['displayName' => 'App\\Jobs\\SomethingElse']);
        $now = now()->getTimestamp();

        DB::table('jobs')->insert([
            ['queue' => 'default', 'payload' => $payload, 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now, 'created_at' => $now],
            ['queue' => 'default', 'payload' => $payload, 'attempts' => 1, 'reserved_at' => $now, 'available_at' => $now + 60, 'created_at' => $now],
            ['queue' => 'default', 'payload' => $other, 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now, 'created_at' => $now],
        ]);

        $observation = $this->observer()->observe();

        $this->assertSame(2, $observation->importJobsQueued, 'unrelated jobs are not counted');
        $this->assertSame(1, $observation->importJobsReserved);
    }

    public function test_an_empty_queue_is_zero_not_unknown(): void
    {
        $observation = $this->observer()->observe();

        $this->assertSame(0, $observation->importJobsQueued);
        $this->assertSame(0, $observation->importJobsReserved);
    }

    public function test_only_a_live_import_claim_counts_as_in_flight(): void
    {
        $live = $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_IMPORTING, 'last_attempt_at' => now()->subMinute()]);
        $stale = $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_IMPORTING, 'last_attempt_at' => now()->subMinutes(IncomingInquiry::IMPORT_CLAIM_TTL_MINUTES + 5)]);
        $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_PENDING]);

        $this->assertSame(1, $this->observer()->observe()->importsInFlight);
        $this->assertNotSame($live->id, $stale->id);
    }

    public function test_observation_is_read_only(): void
    {
        $this->inquiryFor(null);
        $before = hash('sha256', json_encode([DB::table('incoming_inquiries')->get()->all(), DB::table('jobs')->get()->all()]));

        $this->observer()->observe();

        $this->assertSame($before, hash('sha256', json_encode([DB::table('incoming_inquiries')->get()->all(), DB::table('jobs')->get()->all()])));
    }

    public function test_an_observation_carries_numbers_only(): void
    {
        $this->inquiryFor(null);
        $json = json_encode($this->observer()->observe()->toArray());

        $this->assertStringNotContainsString('Синтетический', $json);
        $this->assertStringNotContainsString('example.test', $json);
        foreach ($this->observer()->observe()->toArray() as $value) {
            $this->assertTrue($value === null || is_int($value) || is_string($value) || is_array($value));
        }
    }
}
