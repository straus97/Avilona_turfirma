<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryBackfillVerifier;
use App\Services\Inquiries\Backfill\InquiryReconciler;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\InquiryDraftStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I3 — переходная сверка IncomingInquiry ↔ Inquiry (контракт B), отдельная от
 * начальной проверки целостности переноса (контракт A, InquiryBackfillVerifier). Только
 * синтетические данные, SQLite :memory:.
 */
class InquiryReconcilerTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private VerifiedQaConnection $qa;
    private string $name;
    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->name = config('database.default');
        config(["database.connections.{$this->name}.qa_disposable" => true]);
        $this->qa = VerifiedQaConnection::verify($this->name);

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
    }

    private function backfill(): array
    {
        return (new InquiryBackfillService($this->qa))->apply();
    }

    private function reconcile(): array
    {
        return (new InquiryReconciler($this->qa))->reconcile();
    }

    private function mapped(IncomingInquiry $source): Inquiry
    {
        return Inquiry::query()->where('incoming_inquiry_id', $source->id)->firstOrFail();
    }

    private function withoutForeignKeyChecks(callable $callback): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
            $callback();

            return;
        }

        Schema::disableForeignKeyConstraints();
        $callback();
        Schema::enableForeignKeyConstraints();
    }

    /** Состояние обеих таблиц, заявок и аудита — для проверки «только чтение». */
    private function stateHash(): string
    {
        return hash('sha256', json_encode([
            DB::table('incoming_inquiries')->orderBy('id')->get()->all(),
            DB::table('inquiries')->orderBy('id')->get()->all(),
            DB::table('bookings')->orderBy('id')->get()->all(),
            AuditEvent::query()->count(),
        ]));
    }

    // ---------------------------------------------------------------- начальное совпадение

    public function test_fully_matched_initial_backfill_is_reconciled_by_both_contracts(): void
    {
        $this->inquiryFor(null);
        $this->inquiryFor($this->a, $this->tourist);
        $this->inquiryFor($this->b, null, ['workflow_state' => 'closed', 'closed_at' => '2026-03-01 10:00:00', 'closed_by' => $this->b->id, 'close_reason' => 'Синтетически']);
        $this->backfill();

        $result = $this->reconcile();

        $this->assertSame(3, $result['inquiries_total']);
        $this->assertSame(['IN_SYNC' => 3], $result['verdicts']);
        $this->assertTrue($result['structurally_consistent']);
        $this->assertTrue($result['fully_reconciled']);
        $this->assertSame([], $result['incomplete_because']);
        $this->assertSame(0, $result['unprocessed_sources']['total']);
        $this->assertTrue((new InquiryBackfillVerifier($this->qa))->verify()['fully_verified'], 'контракт A не ослаблен');
    }

    public function test_independent_inquiries_are_not_compared_with_any_source(): void
    {
        $this->independentInquiry($this->a);
        $this->independentInquiry($this->admin);

        $result = $this->reconcile();

        $this->assertSame(['INDEPENDENT' => 2], $result['verdicts']);
        $this->assertSame(['independent' => 2], $result['provenance']);
        $this->assertTrue($result['fully_reconciled']);
    }

    // ---------------------------------------------------------------- не перенесено / новые

    public function test_a_source_without_inquiry_is_unprocessed_and_the_report_is_incomplete(): void
    {
        $this->inquiryFor($this->a);

        $result = $this->reconcile();

        $this->assertSame(1, $result['unprocessed_sources']['total']);
        $this->assertSame(['in_progress' => 1], $result['unprocessed_sources']['by_workflow_state']);
        $this->assertFalse($result['fully_reconciled']);
        $this->assertTrue($result['structurally_consistent'], 'незавершённый перенос — не порча');
        $this->assertSame(['unprocessed_sources' => 1], $result['incomplete_because']);
        $this->assertArrayHasKey('no_sync_marker', $result['limits'], 'невозможность отличить «новое» от «пропущенного» названа честно');
    }

    public function test_newly_arrived_incoming_inquiry_after_backfill_is_reported_not_created(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();
        $late = $this->inquiryFor(null);

        $before = Inquiry::query()->count();
        $result = $this->reconcile();

        $this->assertSame($before, Inquiry::query()->count(), 'сверка не создаёт Inquiry');
        $this->assertSame(1, $result['unprocessed_sources']['total']);
        $this->assertSame([$late->id], $result['unprocessed_sources']['sample_source_ids']);
        $this->assertSame(1, $result['verdicts']['IN_SYNC']);
    }

    public function test_unsupported_technical_sources_without_inquiry_are_not_unprocessed(): void
    {
        $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_FAILED]);
        $this->inquiryFor(null, null, ['external_type' => 1]);

        $this->assertSame(0, $this->reconcile()['unprocessed_sources']['total']);
    }

    // ---------------------------------------------------------------- структурная порча

    public function test_missing_source_is_structural(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->withoutForeignKeyChecks(fn () => DB::table('incoming_inquiries')->where('id', $source->id)->delete());

        $result = $this->reconcile();

        $this->assertSame(['STRUCTURAL' => 1], $result['verdicts']);
        $this->assertArrayHasKey('missing_source', $result['reasons']);
        $this->assertFalse($result['structurally_consistent']);
        $this->assertFalse($result['fully_reconciled']);
    }

    public function test_external_inquiry_without_a_source_reference_and_manual_inquiry_with_one_are_structural(): void
    {
        DB::table('inquiries')->insert(['origin' => 'external_intake', 'status' => 'new', 'incoming_inquiry_id' => null,
            'created_at' => now(), 'updated_at' => now()]);
        $source = $this->inquiryFor($this->a);
        DB::table('inquiries')->insert(['origin' => 'manager_created', 'status' => 'in_progress', 'incoming_inquiry_id' => $source->id,
            'created_by' => $this->a->id, 'assigned_to' => $this->a->id, 'created_at' => now(), 'updated_at' => now()]);

        $result = $this->reconcile();

        $this->assertSame(['STRUCTURAL' => 2], $result['verdicts']);
        $this->assertSame(1, $result['reasons']['external_without_source_reference']);
        $this->assertSame(1, $result['reasons']['malformed_provenance']);
        $this->assertSame(['malformed' => 2], $result['provenance']);
    }

    public function test_duplicate_source_links_are_reported(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dropping an index is DDL (implicit commit on MySQL); the duplicate-link case is exercised on SQLite only.');
        }

        $source = $this->inquiryFor($this->a);
        Schema::table('inquiries', fn ($table) => $table->dropUnique(['incoming_inquiry_id']));
        foreach ([1, 2] as $_) {
            DB::table('inquiries')->insert(['origin' => 'external_intake', 'status' => 'in_progress', 'incoming_inquiry_id' => $source->id,
                'assigned_to' => $this->a->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $result = $this->reconcile();

        $this->assertSame(1, $result['global']['duplicate_source_links']);
        $this->assertFalse($result['structurally_consistent']);
    }

    public function test_booking_reference_conflict_between_pair_members_is_structural(): void
    {
        $client = $this->tourist;
        $one = $this->bookingOf($client, $this->a);
        $two = $this->bookingOf($client, $this->a);
        $source = $this->inquiryFor($this->a, $client, ['workflow_state' => 'converted', 'booking_id' => $one->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $this->backfill();
        DB::table('inquiries')->where('incoming_inquiry_id', $source->id)->update(['booking_id' => $two->id]);

        $result = $this->reconcile();

        $this->assertSame(['STRUCTURAL' => 1], $result['verdicts']);
        $this->assertArrayHasKey('booking_reference_conflict', $result['reasons']);
    }

    public function test_one_booking_referenced_by_two_different_business_inquiries_is_structural(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->a);
        $this->inquiryFor($this->a, $this->tourist, ['workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $this->independentInquiry($this->a, null, $this->tourist, ['status' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now()]);

        $result = $this->reconcile();

        $this->assertSame(1, $result['reasons']['booking_shared_between_records']);
        $this->assertFalse($result['structurally_consistent']);
    }

    public function test_a_pair_sharing_one_booking_is_not_a_conflict(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->a);
        $this->inquiryFor($this->a, $this->tourist, ['workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $this->backfill();

        $result = $this->reconcile();

        $this->assertArrayNotHasKey('booking_shared_between_records', $result['reasons']);
        $this->assertSame(['IN_SYNC' => 1], $result['verdicts']);
    }

    public function test_inquiry_for_an_unsupported_source_and_invariant_violations_are_structural(): void
    {
        $unsupported = $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_FAILED]);
        DB::table('inquiries')->insert(['origin' => 'external_intake', 'status' => 'new', 'incoming_inquiry_id' => $unsupported->id,
            'created_at' => now(), 'updated_at' => now()]);
        // назначенный сотрудник удалён (assigned_to SET NULL) — in_progress без ответственного
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        DB::table('inquiries')->where('incoming_inquiry_id', $source->id)->update(['assigned_to' => null]);

        $result = $this->reconcile();

        $this->assertArrayHasKey('inquiry_for_unsupported_source', $result['reasons']);
        $this->assertArrayHasKey('inquiry_invariant_violation', $result['reasons']);
        $this->assertSame(2, $result['verdicts']['STRUCTURAL']);
    }

    // ---------------------------------------------------------------- эволюция

    public function test_legitimate_draft_evolution_keeps_the_pair_in_sync(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        app(InquiryDraftStore::class)->save($this->mapped($source), $this->a, ['destination_country' => 'Турция', 'adults' => 2], 0);

        $result = $this->reconcile();

        $this->assertSame(['IN_SYNC' => 1], $result['verdicts']);
        $this->assertSame(1, $result['expected_evolution']['draft_edited']);
        $this->assertTrue($result['fully_reconciled']);
        $this->assertTrue((new InquiryBackfillVerifier($this->qa))->verify()['fully_verified'], 'черновик в сверку начального переноса не входит');
    }

    public function test_inquiry_side_status_change_is_not_corruption_but_requires_evidence(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $inquiry = $this->mapped($source);
        $inquiry->forceFill(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $this->a->id, 'close_reason' => 'Правка на стороне Inquiry'])->save();

        $result = $this->reconcile();

        $this->assertSame(['REQUIRES_EVIDENCE' => 1], $result['verdicts']);
        $this->assertTrue($result['structurally_consistent'], 'законная эволюция не объявляется порчей');
        $this->assertFalse($result['fully_reconciled'], 'но и не объявляется сверенной без доказательств');
        $this->assertSame(['inquiry_ahead' => 1], $result['direction_of_difference']);
        foreach (['status', 'closed_at', 'closed_by', 'close_reason'] as $field) {
            $this->assertArrayHasKey('diverges:' . $field, $result['reasons']);
        }
        $this->assertArrayHasKey('no_inquiry_change_log', $result['limits']);
        $this->assertSame(['requires_evidence' => 1], $result['incomplete_because']);
    }

    public function test_post_backfill_legacy_conversion_requires_evidence_with_source_ahead_direction(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();

        // E5 оформляет заявку уже после переноса
        $booking = $this->bookingOf($this->tourist, $this->a);
        DB::table('incoming_inquiries')->where('id', $source->id)->update([
            'workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id,
        ]);

        $result = $this->reconcile();

        $this->assertSame(['REQUIRES_EVIDENCE' => 1], $result['verdicts']);
        $this->assertSame(['source_ahead' => 1], $result['direction_of_difference']);
        $this->assertArrayNotHasKey('booking_reference_conflict', $result['reasons']);
        $this->assertTrue($result['structurally_consistent']);
        // контракт A действительно фиксирует расхождение (он не ослаблен)
        $this->assertFalse((new InquiryBackfillVerifier($this->qa))->verify()['fully_verified']);
    }

    public function test_post_backfill_legacy_closure_is_the_same_class_of_difference(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        DB::table('incoming_inquiries')->where('id', $source->id)->update(['workflow_state' => 'closed', 'closed_at' => now(), 'closed_by' => $this->a->id, 'close_reason' => 'x']);

        $result = $this->reconcile();

        $this->assertSame(['REQUIRES_EVIDENCE' => 1], $result['verdicts']);
        $this->assertSame(['source_ahead' => 1], $result['direction_of_difference']);
    }

    // ---------------------------------------------------------------- владение

    public function test_stale_assignment_after_legacy_reassignment_is_an_ownership_conflict_without_a_winner(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacyReassign($source, $this->b);
        $before = $this->stateHash();

        $result = $this->reconcile();

        $this->assertSame(['OWNERSHIP_CONFLICT' => 1], $result['verdicts']);
        $this->assertArrayHasKey('assignee_differs', $result['reasons']);
        $this->assertSame($before, $this->stateHash(), 'победитель не выбран, ничего не переписано');
        $this->assertSame($this->a->id, $this->mapped($source)->assigned_to);
        $this->assertSame($this->b->id, $source->fresh()->assigned_to);
        $this->assertSame(['ownership_conflicts' => 1], $result['incomplete_because']);
        $this->assertTrue($result['structurally_consistent']);
    }

    public function test_assigned_versus_unassigned_is_also_an_ownership_conflict(): void
    {
        $this->linkedPair($this->a, null);
        $this->linkedPair(null, $this->b);

        $this->assertSame(['OWNERSHIP_CONFLICT' => 2], $this->reconcile()['verdicts']);
    }

    // ---------------------------------------------------------------- только чтение / отчёт

    public function test_reconciliation_is_strictly_read_only_in_every_scenario(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->inquiryFor(null);
        $this->backfill();
        $this->legacyReassign($source, $this->b);
        $this->inquiryFor($this->b);                       // поступило после переноса
        $this->independentInquiry($this->admin);
        $booking = $this->bookingOf($this->tourist, $this->a);
        DB::table('incoming_inquiries')->where('id', $source->id)->update(['booking_id' => $booking->id]);

        $before = $this->stateHash();
        $result = $this->reconcile();
        $again = $this->reconcile();

        $this->assertSame($before, $this->stateHash());
        $this->assertSame($result, $again, 'повторная сверка даёт тот же отчёт');
    }

    public function test_report_contains_no_personal_data_or_payload(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist, ['client_name' => 'СЕКРЕТНОЕ-ИМЯ', 'client_email' => 'secret.person@example.test', 'vendor_payload' => ['k' => 'СЕКРЕТНЫЙ-PAYLOAD']]);
        $this->backfill();
        $this->legacyReassign($source, $this->b);

        $json = json_encode($this->reconcile(), JSON_UNESCAPED_UNICODE);

        foreach (['СЕКРЕТНОЕ-ИМЯ', 'secret.person@example.test', 'СЕКРЕТНЫЙ-PAYLOAD', $this->tourist->email] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_samples_are_bounded(): void
    {
        for ($i = 0; $i < InquiryReconciler::SAMPLE_LIMIT + 5; $i++) {
            $this->linkedPair($this->a, $this->b);
        }

        $result = $this->reconcile();

        $this->assertSame(InquiryReconciler::SAMPLE_LIMIT + 5, $result['verdicts']['OWNERSHIP_CONFLICT']);
        $this->assertCount(InquiryReconciler::SAMPLE_LIMIT, $result['sample_inquiry_ids']['assignee_differs']);
    }

    public function test_chunking_does_not_change_the_result(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->linkedPair($this->a, $i % 2 ? $this->a : $this->b);
        }
        $this->independentInquiry($this->a);

        $this->assertSame($this->reconcile(), (new InquiryReconciler($this->qa))->reconcile(2));
    }

    // ---------------------------------------------------------------- защита подключения

    public function test_command_reconcile_runs_on_a_verified_qa_connection_and_reports_incomplete(): void
    {
        $this->linkedPair($this->a, $this->b);

        $exit = Artisan::call('inquiries:backfill', ['--connection' => $this->name, '--reconcile' => true, '--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exit, 'неполная сверка — ненулевой код');
        $this->assertSame('reconcile', $report['mode']);
        $this->assertFalse($report['fully_reconciled']);
    }

    public function test_command_reconcile_keeps_every_connection_guard_and_runs_no_query_when_refused(): void
    {
        config(["database.connections.{$this->name}.qa_disposable" => false]);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->artisan('inquiries:backfill', ['--connection' => $this->name, '--reconcile' => true])->expectsOutputToContain('REFUSED')->assertExitCode(1);
        $this->artisan('inquiries:backfill', ['--reconcile' => true])->expectsOutputToContain('REFUSED')->assertExitCode(1);
        $this->assertSame(0, $queries);
    }

    public function test_command_refuses_to_combine_reconcile_with_apply_or_verify(): void
    {
        $this->artisan('inquiries:backfill', ['--connection' => $this->name, '--reconcile' => true, '--verify' => true])
            ->expectsOutputToContain('Use only one')->assertExitCode(1);
        $this->artisan('inquiries:backfill', ['--connection' => $this->name, '--reconcile' => true, '--apply' => true, '--confirm-database' => $this->qa->database])
            ->expectsOutputToContain('Use only one')->assertExitCode(1);
    }
}
