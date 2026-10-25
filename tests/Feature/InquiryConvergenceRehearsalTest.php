<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryCatchUpPlanner;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\Convergence\FreezeAttestation;
use App\Services\Inquiries\Convergence\FreezeObservation;
use App\Services\Inquiries\Convergence\FreezeObserver;
use App\Services\Inquiries\Convergence\InquiryConvergenceRehearsal as Rehearsal;
use App\Services\Inquiries\InquiryDraftStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I5 — репетиция полного сравнения внутри окна «без записи» на синтетических данных.
 *
 * Оркестратор, классификатор, планировщик, сверка и снимок здесь — настоящий код. Подставлен только
 * источник ЗАМЕРОВ заморозки (SQLite не показывает чужие сессии и счётчики записи): ScriptedObserver
 * возвращает «чисто» или заданную картину, а реальный MySQL-наблюдатель проверен отдельно на
 * одноразовом MySQL. Поэтому здесь доказывается поведение отчёта и отказов, а не сам барьер записи.
 */
class InquiryConvergenceRehearsalTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private VerifiedQaConnection $qa;
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

        $name = config('database.default');
        config(["database.connections.{$name}.qa_disposable" => true]);
        $this->qa = VerifiedQaConnection::verify($name);

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
    }

    private function clean(array $overrides = []): FreezeObservation
    {
        return new FreezeObservation(...array_merge([
            'driver' => 'mysql', 'otherSessions' => 0, 'otherOpenTransactions' => 0, 'importJobsQueued' => 0,
            'importJobsReserved' => 0, 'importsInFlight' => 0, 'sourceWriteCounters' => ['insert' => 1, 'update' => 1, 'delete' => 0],
        ], $overrides));
    }

    /** @param list<FreezeObservation>|null $sequence */
    private function rehearse(?array $sequence = null, ?FreezeAttestation $attestation = null, int $chunk = 200): array
    {
        $observer = new ScriptedObserver($sequence ?? [$this->clean(), $this->clean()]);

        return (new Rehearsal($this->qa, $observer))->run($attestation ?? FreezeAttestation::all(), $chunk);
    }

    private function backfill(): array
    {
        return (new InquiryBackfillService($this->qa))->apply();
    }

    private function legacy(IncomingInquiry $source, array $attributes): void
    {
        DB::table('incoming_inquiries')->where('id', $source->id)->update($attributes);
    }

    private function state(): string
    {
        return hash('sha256', json_encode([
            DB::table('incoming_inquiries')->orderBy('id')->get()->all(),
            DB::table('inquiries')->orderBy('id')->get()->all(),
            DB::table('bookings')->orderBy('id')->get()->all(),
            DB::table('users')->orderBy('id')->get()->all(),
            AuditEvent::query()->count(),
        ]));
    }

    private function assertAccounted(array $result): void
    {
        $this->assertSame($result['sources']['total'], array_sum($result['sources']['report']), 'no source may disappear from the counts');
        $this->assertTrue($result['sources']['accounting_complete']);
    }

    private function nonZero(array $result): array
    {
        return array_filter($result['sources']['report']);
    }

    // ---------------------------------------------------------------- сходимость и идемпотентность

    public function test_initial_backfill_is_converged_in_a_proven_window_and_a_second_run_is_identical(): void
    {
        $this->inquiryFor(null);
        $this->inquiryFor($this->a, $this->tourist);
        $this->inquiryFor($this->b, null, ['workflow_state' => 'closed', 'closed_at' => '2026-03-01 10:00:00', 'closed_by' => $this->b->id, 'close_reason' => 'Синтетически']);
        $this->backfill();
        $hash = $this->state();

        $first = $this->rehearse();
        $second = $this->rehearse();

        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $first['status']);
        $this->assertFalse($first['provisional']);
        $this->assertSame(['matched_source' => 3], $this->nonZero($first));
        $this->assertAccounted($first);
        $this->assertTrue($first['reconciler']['fully_reconciled']);
        $this->assertSame($first, $second, 'repeat reconciliation is idempotent');
        $this->assertSame($hash, $this->state(), 'the rehearsal writes nothing');
        $this->assertSame(3, Inquiry::query()->count(), 'no Inquiry duplicated');
    }

    public function test_convergence_never_authorizes_a_production_cutover(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();

        $result = $this->rehearse();

        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $result['status']);
        $this->assertFalse($result['production_cutover_authorized']);
        $this->assertFalse($result['freeze']['production_cutover_authorized']);
        $this->assertNotEmpty($result['freeze']['cutover_blockers']);
        $this->assertTrue($result['read_only']);
    }

    public function test_an_empty_database_converges_trivially_and_reports_zero_sources(): void
    {
        $result = $this->rehearse();

        $this->assertSame(0, $result['sources']['total']);
        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $result['status']);
    }

    // ---------------------------------------------------------------- новые источники и прерванный перенос

    public function test_a_new_source_after_the_initial_backfill_is_reported_then_converged_by_the_existing_backfill(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();
        $fresh = $this->inquiryFor(null);
        $bookings = Booking::withTrashed()->count();

        $result = $this->rehearse();

        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
        $this->assertSame(['matched_source' => 1, 'new_eligible_source' => 1], $this->nonZero($result));
        $this->assertSame([$fresh->id], $result['sources']['sample_source_ids']['new_eligible_source']);
        $this->assertSame(1, $result['sources']['proposed_actions'][InquiryCatchUpPlanner::ACTION_CREATE_FROM_SOURCE]);
        $this->assertAccounted($result);

        $this->backfill();
        $this->backfill();

        $after = $this->rehearse();
        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $after['status']);
        $this->assertSame(2, Inquiry::query()->count(), 'repeated backfill did not duplicate');
        $this->assertSame($bookings, Booking::withTrashed()->count());
    }

    public function test_an_interrupted_backfill_leaves_a_resumable_remainder_that_the_rehearsal_reports(): void
    {
        foreach (range(1, 4) as $_) {
            $this->inquiryFor(null);
        }

        $inserts = 0;
        DB::listen(function ($query) use (&$inserts): void {
            if (str_starts_with(strtolower($query->sql), 'insert into "inquiries"') && ++$inserts === 3) {
                throw new RuntimeException('simulated interruption');
            }
        });

        try {
            $this->backfill();
            $this->fail('the backfill was meant to be interrupted');
        } catch (RuntimeException) {
            // expected
        }

        $partial = $this->rehearse();
        $this->assertSame(Rehearsal::NOT_CONVERGED, $partial['status']);
        $this->assertSame(2, $partial['sources']['report']['matched_source']);
        $this->assertSame(2, $partial['sources']['report']['new_eligible_source']);
        $this->assertAccounted($partial);

        $this->backfill();

        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $this->rehearse()['status']);
        $this->assertSame(4, Inquiry::query()->count());
    }

    // ---------------------------------------------------------------- изменения E5 после переноса

    public function test_an_e5_reassignment_after_backfill_is_a_conflict_without_a_winner(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacyReassign($source, $this->b);
        $hash = $this->state();

        $result = $this->rehearse();

        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
        $this->assertSame(['conflicting_current_state' => 1], $this->nonZero($result));
        $this->assertSame(['REASSIGNMENT' => 1], $result['sources']['planner_categories']);
        $this->assertSame(1, $result['sources']['proposed_actions'][InquiryCatchUpPlanner::ACTION_HOLD_FOR_DECISION]);
        $this->assertSame($this->a->id, (int) Inquiry::query()->value('assigned_to'), 'the Inquiry is not rewritten');
        $this->assertSame($hash, $this->state());
        $this->assertFalse($result['reconciler']['fully_reconciled']);
    }

    public function test_an_e5_conversion_after_backfill_needs_catch_up_and_creates_no_booking(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $booking = $this->bookingOf($this->tourist, $this->a);
        $this->legacy($source, ['workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $bookings = Booking::withTrashed()->count();

        $result = $this->rehearse();

        $this->assertSame(['requires_catch_up' => 1], $this->nonZero($result));
        $this->assertSame(['POST_BACKFILL_CONVERSION' => 1], $result['sources']['planner_categories']);
        $this->assertSame(1, $result['sources']['proposed_actions'][InquiryCatchUpPlanner::ACTION_MIRROR_FROM_SOURCE]);
        $this->assertNull(Inquiry::query()->value('booking_id'), 'nothing is mirrored by the rehearsal');
        $this->assertSame($bookings, Booking::withTrashed()->count());
        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
    }

    public function test_an_e5_closure_after_backfill_needs_catch_up(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacy($source, ['workflow_state' => 'closed', 'closed_at' => now(), 'closed_by' => $this->a->id, 'close_reason' => 'Синтетически']);

        $result = $this->rehearse();

        $this->assertSame(['requires_catch_up' => 1], $this->nonZero($result));
        $this->assertSame(['CLOSURE' => 1], $result['sources']['planner_categories']);
    }

    public function test_a_client_reassociation_after_backfill_needs_catch_up(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $other = $this->userWith('tourist');
        $this->legacy($source, ['client_user_id' => $other->id]);

        $result = $this->rehearse();

        $this->assertSame(['requires_catch_up' => 1], $this->nonZero($result));
        $this->assertSame(['CLIENT_LINK_CHANGE' => 1], $result['sources']['planner_categories']);
    }

    // ---------------------------------------------------------------- ссылки, потерянные удалением

    public function test_a_hard_deleted_responsible_employee_is_reported_even_though_updated_at_never_moved(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $updatedAt = DB::table('incoming_inquiries')->where('id', $source->id)->value('updated_at');

        $this->a->delete();

        $this->assertNull(DB::table('incoming_inquiries')->where('id', $source->id)->value('assigned_to'), 'FK SET NULL happened');
        $this->assertSame($updatedAt, DB::table('incoming_inquiries')->where('id', $source->id)->value('updated_at'), 'a timestamp cursor would not see this');

        $result = $this->rehearse();

        $this->assertSame(['assignee_missing_or_deleted' => 1], $this->nonZero($result));
        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
        $this->assertSame(1, $result['unresolved_manual_decisions']);
        $this->assertAccounted($result);
    }

    public function test_an_invalid_client_reference_is_reported_separately(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $this->legacy($source, ['client_user_id' => 987654]);

        $result = $this->rehearse();

        $this->assertSame(['invalid_client_reference' => 1], $this->nonZero($result));
        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
    }

    public function test_deleting_a_client_nulls_both_sides_and_stays_matched(): void
    {
        $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $this->tourist->delete();

        $result = $this->rehearse();

        $this->assertSame(['matched_source' => 1], $this->nonZero($result));
        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $result['status']);
    }

    public function test_a_booking_already_owned_by_another_inquiry_is_a_duplicate_booking_linkage(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $booking = $this->bookingOf($this->tourist, $this->a);
        $this->independentInquiry($this->a, null, $this->tourist, ['status' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $this->legacy($source, ['workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $bookings = Booking::withTrashed()->count();

        $result = $this->rehearse();

        $this->assertSame(['duplicate_booking_linkage' => 1], $this->nonZero($result));
        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
        $this->assertSame($bookings, Booking::withTrashed()->count());
        $this->assertSame(1, $result['inquiries']['independent']);
    }

    // ---------------------------------------------------------------- технические источники и эволюция Inquiry

    public function test_unsupported_technical_imports_are_counted_and_do_not_prevent_convergence(): void
    {
        $this->inquiryFor(null);
        $this->backfill();
        $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_PENDING, 'imported_at' => null]);
        $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_FAILED]);
        $this->inquiryFor(null, null, ['external_type' => 1]);

        $result = $this->rehearse();

        $this->assertSame(['matched_source' => 1, 'unsupported_technical_import' => 3], $this->nonZero($result));
        $this->assertAccounted($result);
        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $result['status']);
    }

    public function test_an_audited_draft_edit_is_legitimate_inquiry_side_evolution_and_not_a_conflict(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $inquiry = Inquiry::query()->where('incoming_inquiry_id', $source->id)->firstOrFail();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2, 'destination_country' => 'Турция'], 0);

        $result = $this->rehearse();

        $this->assertSame(1, $result['inquiries']['legitimate_inquiry_side_evolution']);
        $this->assertSame(['matched_source' => 1], $this->nonZero($result));
        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $result['status']);
    }

    public function test_an_unaudited_draft_revision_is_not_legitimate_and_prevents_convergence(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        DB::table('inquiries')->where('incoming_inquiry_id', $source->id)->update(['draft_revision' => 3]);

        $result = $this->rehearse();

        $this->assertSame(0, $result['inquiries']['legitimate_inquiry_side_evolution']);
        $this->assertSame(Rehearsal::NOT_CONVERGED, $result['status']);
        $this->assertArrayHasKey('draft_without_audit_evidence', $result['reconciler']['incomplete_because']);
    }

    // ---------------------------------------------------------------- метки времени и порядок коммитов не нужны

    public function test_same_second_and_out_of_order_changes_are_found_because_nothing_depends_on_timestamps(): void
    {
        $first = $this->inquiryFor($this->a);
        $second = $this->inquiryFor($this->a);
        $this->backfill();
        $stamp = '2026-03-01 10:00:00';
        DB::table('incoming_inquiries')->update(['updated_at' => $stamp]);

        // Обе правки без движения updated_at (как «медленная транзакция», зафиксированная позже отметки).
        $this->legacy($first, ['assigned_to' => $this->b->id]);
        $this->legacy($second, ['workflow_state' => 'closed', 'closed_at' => $stamp, 'closed_by' => $this->a->id, 'close_reason' => 'Синтетически']);

        $result = $this->rehearse();

        $this->assertSame(['requires_catch_up' => 1, 'conflicting_current_state' => 1], $this->nonZero($result));
        $this->assertSame(2, DB::table('incoming_inquiries')->where('updated_at', $stamp)->count(), 'updated_at never moved');
    }

    // ---------------------------------------------------------------- небезопасное и неполное окно

    public function test_a_transaction_open_at_freeze_start_makes_the_run_unsafe_and_the_counts_provisional(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();

        $result = $this->rehearse([$this->clean(['otherOpenTransactions' => 1]), $this->clean()]);

        $this->assertSame(Rehearsal::UNSAFE_FREEZE, $result['status']);
        $this->assertTrue($result['provisional']);
        $this->assertContains('open_transaction_present', $result['freeze']['blockers']);
        $this->assertSame(['matched_source' => 1], $this->nonZero($result), 'counts are still shown, flagged provisional');
    }

    public function test_a_clean_database_without_the_operator_attestations_is_not_a_proven_window(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();

        $result = $this->rehearse(attestation: new FreezeAttestation([FreezeAttestation::INGRESS_STOPPED]));

        $this->assertSame(Rehearsal::UNSAFE_FREEZE, $result['status']);
        $this->assertContains('attestation_incomplete', $result['freeze']['blockers']);
    }

    public function test_an_engine_that_cannot_observe_the_window_never_reports_convergence(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();

        $result = (new Rehearsal($this->qa, new \App\Services\Inquiries\Convergence\DatabaseFreezeObserver($this->qa)))->run(FreezeAttestation::all());

        $this->assertSame(Rehearsal::UNSAFE_FREEZE, $result['status']);
        $this->assertContains('sessions_not_observable', $result['freeze']['unverifiable']);
    }

    public function test_a_source_write_during_the_scan_turns_a_clean_database_into_an_unsafe_freeze(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->inquiryFor($this->a);
        $this->backfill();

        $fired = false;
        DB::listen(function ($query) use (&$fired, $source): void {
            if (! $fired && str_contains($query->sql, 'select "status", "incoming_inquiry_id" from "inquiries"')) {
                $fired = true;
                DB::table('incoming_inquiries')->where('id', $source->id)->update(['webhook_count' => DB::raw('webhook_count + 1')]);
            }
        });

        $result = $this->rehearse(chunk: 1);

        $this->assertTrue($fired, 'the concurrent writer ran inside the scan');
        $this->assertFalse($result['integrity']['source_unchanged']);
        $this->assertSame(Rehearsal::UNSAFE_FREEZE, $result['status']);
        $this->assertContains('source_changed_during_scan', $result['freeze']['blockers']);
    }

    public function test_an_inquiry_side_write_during_the_scan_is_also_detected(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();

        $fired = false;
        DB::listen(function ($query) use (&$fired, $source): void {
            if (! $fired && str_contains($query->sql, 'select "status", "incoming_inquiry_id" from "inquiries"')) {
                $fired = true;
                DB::table('inquiries')->where('incoming_inquiry_id', $source->id)->update(['draft_revision' => 1]);
            }
        });

        $result = $this->rehearse();

        $this->assertFalse($result['integrity']['inquiries_unchanged']);
        $this->assertSame(Rehearsal::UNSAFE_FREEZE, $result['status']);
    }

    public function test_a_failed_rehearsal_changes_nothing_and_the_next_run_is_clean(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();
        $hash = $this->state();

        try {
            (new Rehearsal($this->qa, new ThrowingObserver()))->run(FreezeAttestation::all());
            $this->fail('the observer failure must surface');
        } catch (RuntimeException $e) {
            $this->assertSame('observer failed', $e->getMessage());
        }

        $this->assertSame($hash, $this->state());
        $this->assertSame(Rehearsal::CONVERGED_IN_WINDOW, $this->rehearse()['status']);
    }

    // ---------------------------------------------------------------- вывод и безопасность

    public function test_the_report_contains_no_personal_data_and_only_numeric_identifiers(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $this->legacyReassign($source, $this->b);

        $json = json_encode($this->rehearse(), JSON_UNESCAPED_UNICODE);

        foreach (['Синтетический Заявитель', 'inquiry.person@example.test', '+7 (900)', $this->a->email, $this->b->email, $this->tourist->email, $this->tourist->name, 'Sunmar', 'Таиланд'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }

        $decoded = json_decode($json, true);
        foreach ($decoded['sources']['sample_source_ids'] as $ids) {
            foreach ($ids as $id) {
                $this->assertIsInt($id);
            }
        }
    }

    public function test_the_rehearsal_leaves_staff_access_rights_and_routes_unchanged(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $inquiry = Inquiry::query()->where('incoming_inquiry_id', $source->id)->firstOrFail();
        $rights = fn (): array => [
            $this->a->can('view', $inquiry), $this->b->can('view', $inquiry), $this->admin->can('view', $inquiry), $this->tourist->can('view', $inquiry),
            $this->a->can('view', $source), $this->b->can('view', $source),
        ];
        $before = $rights();
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->sort()->values()->all();

        $this->rehearse();

        $this->assertSame($before, $rights());
        $this->assertSame([true, false, true, false, true, false], $before);
        $this->assertSame($routes, collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->sort()->values()->all());
        $this->assertEmpty(array_filter($routes, fn (string $uri): bool => str_contains($uri, 'converge')));
    }

    // ---------------------------------------------------------------- команда

    public function test_the_command_refuses_without_a_verified_connection_before_touching_a_database(): void
    {
        $this->assertSame(1, Artisan::call('inquiries:convergence-rehearsal'));
        $this->assertStringContainsString('REFUSED', Artisan::output());

        config(['database.connections.sqlite.qa_disposable' => false]);
        $this->assertSame(1, Artisan::call('inquiries:convergence-rehearsal', ['--connection' => config('database.default')]));
        $this->assertStringContainsString('REFUSED', Artisan::output());
    }

    public function test_the_command_refuses_an_unknown_attestation_and_has_no_write_mode(): void
    {
        $this->assertSame(1, Artisan::call('inquiries:convergence-rehearsal', ['--connection' => config('database.default'), '--attest' => 'maintenance_mode_enabled']));
        $this->assertStringContainsString('REFUSED', Artisan::output());

        $options = array_keys(Artisan::all()['inquiries:convergence-rehearsal']->getDefinition()->getOptions());
        $this->assertNotContains('apply', $options);
        $this->assertNotContains('confirm-database', $options);
    }

    public function test_the_command_reports_json_and_exits_non_zero_unless_converged(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();

        // SQLite не умеет наблюдать окно, поэтому честный итог — UNSAFE_FREEZE и код 1.
        $exit = Artisan::call('inquiries:convergence-rehearsal', [
            '--connection' => config('database.default'),
            '--attest' => implode(',', FreezeAttestation::KEYS),
            '--json' => true,
        ]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exit);
        $this->assertSame(Rehearsal::UNSAFE_FREEZE, $report['status']);
        $this->assertFalse($report['production_cutover_authorized']);
        $this->assertSame(['matched_source' => 1], array_filter($report['sources']['report']));
    }
}

/** Возвращает заданные замеры по очереди; последний повторяется. */
final class ScriptedObserver implements FreezeObserver
{
    /** @param list<FreezeObservation> $sequence */
    public function __construct(private array $sequence)
    {
    }

    public function observe(): FreezeObservation
    {
        return count($this->sequence) > 1 ? array_shift($this->sequence) : $this->sequence[0];
    }
}

final class ThrowingObserver implements FreezeObserver
{
    public function observe(): FreezeObservation
    {
        throw new RuntimeException('observer failed');
    }
}
