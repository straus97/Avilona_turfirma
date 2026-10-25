<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\Convergence\FreezeAttestation;
use App\Services\Inquiries\Convergence\InquiryFreezeGate;
use App\Services\Inquiries\Convergence\OfflineConvergenceRehearsal as Offline;
use App\Services\Inquiries\Convergence\OfflineSnapshotContract;
use App\Services\Inquiries\InquiryDraftStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I6 — начальная офлайн-сходимость на запечатанном синтетическом снимке.
 *
 * Классификатор, перенос (I2), планировщик (I4), сверка (I3/I4), оркестратор I5 и контракт снимка здесь
 * настоящие. SQLite `:memory:` принадлежит одному процессу, поэтому «чужих писателей» у него нет по
 * построению; живое наблюдение сессий, транзакций и счётчиков записи проверено на одноразовом MySQL
 * (docs/AOA-03-I6) — этими тестами оно НЕ доказывается.
 */
class InquiryOfflineConvergenceRehearsalTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private VerifiedQaConnection $qa;
    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;
    private int $seq = 800000;

    /** @var array<string, IncomingInquiry> */
    private array $s = [];

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

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
    }

    // ---------------------------------------------------------------- фикстуры

    private function src(?User $assignee, ?User $client = null, array $overrides = []): IncomingInquiry
    {
        return $this->inquiryFor($assignee, $client, array_merge(['external_id' => (string) ++$this->seq], $overrides));
    }

    /**
     * Полный набор из задания. Ожидаемое разбиение 12 источников (после apply):
     * matched 6 (1 было + 5 создано), unsupported 2, assignee_missing 2, client 1, conflicting ownership 1.
     * С $duplicateBooking добавляется 13-й источник на уже занятую Booking (структурная аномалия).
     */
    private function scenario(bool $duplicateBooking = false): void
    {
        // Уже перенесённый ранее + законно отредактированный черновик (до запечатывания).
        $this->s['prebackfilled'] = $this->src($this->a);
        (new InquiryBackfillService($this->qa))->apply();
        $existing = Inquiry::query()->where('incoming_inquiry_id', $this->s['prebackfilled']->id)->firstOrFail();
        app(InquiryDraftStore::class)->save($existing, $this->a, ['adults' => 3, 'preferences' => 'SYNTH-DRAFT-EDIT'], 0);

        $this->s['new_unassigned'] = $this->src(null);
        $this->s['in_progress_null_client'] = $this->src($this->a);
        $this->s['in_progress_client'] = $this->src($this->b, $this->tourist, ['client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00']);
        $this->s['converted'] = $this->src($this->a, $this->tourist, [
            'workflow_state' => 'converted', 'client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00',
            'booking_id' => $this->bookingOf($this->tourist, $this->a)->id, 'converted_at' => '2026-02-03 12:00:00', 'converted_by' => $this->a->id,
        ]);
        $this->s['closed'] = $this->src(null, null, [
            'workflow_state' => 'closed', 'closed_at' => '2026-02-04 13:00:00', 'closed_by' => $this->admin->id, 'close_reason' => 'Синтетически',
        ]);

        $this->s['technical_failed'] = $this->src(null, null, ['state' => IncomingInquiry::STATE_FAILED]);
        $this->s['unsupported_type'] = $this->src(null, null, ['external_type' => 1]);

        DB::statement('PRAGMA defer_foreign_keys = ON');
        $this->s['missing_assignee'] = $this->src($this->a, null, ['assigned_to' => 987654]);
        $this->s['nulled_assignee'] = $this->src(null, null, ['workflow_state' => 'in_progress']);
        $this->s['client_not_tourist'] = $this->src($this->a, $this->b);

        if ($duplicateBooking) {
            // incoming_inquiries.booking_id is UNIQUE, so two sources can never share a Booking in this schema;
            // the reachable duplicate is a source whose Booking is already owned by another (manual) Inquiry.
            $shared = $this->bookingOf($this->tourist, $this->a);
            $this->independentInquiry($this->admin, $this->a, $this->tourist, [
                'status' => 'converted', 'booking_id' => $shared->id, 'converted_at' => '2026-02-05 09:00:00', 'converted_by' => $this->a->id,
            ]);
            $this->s['duplicate_booking'] = $this->src($this->a, $this->tourist, [
                'workflow_state' => 'converted', 'booking_id' => $shared->id, 'converted_at' => '2026-02-05 09:00:00', 'converted_by' => $this->a->id,
                'client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00',
            ]);
        }

        $this->s['ownership_conflict'] = $this->src($this->a);
        Inquiry::factory()->externalIntake($this->s['ownership_conflict'])->assignedTo($this->b)->create();

        $this->independentInquiry($this->a);
    }

    private function seal(?FreezeAttestation $attestation = null): array
    {
        $result = (new Offline($this->qa))->seal($attestation ?? FreezeAttestation::all());
        $this->assertTrue($result['sealed'], 'sealing refused: ' . implode(',', $result['refusals']));

        return $result['manifest'];
    }

    private function rehearse(?array $manifest, bool $apply, ?FreezeAttestation $attestation = null): array
    {
        return (new Offline($this->qa))->run($manifest, $attestation ?? FreezeAttestation::all(), $apply);
    }

    private function state(): string
    {
        return hash('sha256', json_encode([
            DB::table('incoming_inquiries')->orderBy('id')->get()->all(),
            DB::table('inquiries')->orderBy('id')->get()->all(),
            DB::table('bookings')->orderBy('id')->get()->all(),
            DB::table('users')->orderBy('id')->get()->all(),
            DB::table('audit_events')->orderBy('id')->get()->all(),
        ]));
    }

    /** @return array<int, array<string, mixed>> Inquiry по id источника (поля, которые могут разойтись) */
    private function linked(): array
    {
        return Inquiry::query()->whereNotNull('incoming_inquiry_id')->orderBy('incoming_inquiry_id')->get()
            ->mapWithKeys(fn (Inquiry $i): array => [(int) $i->incoming_inquiry_id => [
                'status' => $i->status, 'assigned_to' => $i->assigned_to, 'client_user_id' => $i->client_user_id,
                'booking_id' => $i->booking_id, 'closed_by' => $i->closed_by, 'draft_revision' => $i->draft_revision,
            ]])->all();
    }

    // ---------------------------------------------------------------- полный сценарий

    public function test_full_scenario_converges_the_safe_scope_and_holds_every_conflict(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $bookings = Booking::withTrashed()->count();
        $users = DB::table('users')->count();
        $sourcesBefore = json_encode(DB::table('incoming_inquiries')->orderBy('id')->get()->all());
        $audit = DB::table('audit_events')->count();

        $result = $this->rehearse($manifest, true);

        $this->assertSame(Offline::DECISIONS_REQUIRED, $result['status']);
        $this->assertFalse($result['production_cutover_authorized']);
        $this->assertSame('NOT_ESTABLISHED_BY_THIS_TOOL', $result['completeness_of_capture']);
        $this->assertSame(['B1' => 'OPEN', 'B2' => 'OPEN'], $result['b1_b2']);
        $this->assertSame([], $result['refusals']);
        $this->assertTrue($result['window']['quiescent']);

        $acc = $result['accounting'];
        $this->assertSame(['sealed_rows' => 12, 'examined' => 12, 'matches_seal' => true], $acc['source_population']);
        $this->assertTrue($acc['sources_by_verdict']['complete']);
        $this->assertTrue($acc['sources_by_category']['complete']);
        $this->assertTrue($acc['inquiries_by_verdict']['complete']);
        $this->assertSame(
            ['BLOCKED' => 3, 'CONFLICT' => 1, 'MATCHED' => 6, 'UNSUPPORTED' => 2],
            $acc['sources_by_verdict']['counts']
        );
        $this->assertSame(
            ['matched_source' => 6, 'new_eligible_source' => 0, 'requires_catch_up' => 0, 'conflicting_current_state' => 1,
             'assignee_missing_or_deleted' => 2, 'invalid_client_reference' => 1, 'duplicate_booking_linkage' => 0,
             'unsupported_technical_import' => 2, 'unresolved_manual_decision' => 0],
            $acc['sources_by_category']['counts']
        );
        $this->assertSame(4, $acc['decisions_required']);

        $named = $acc['requested_categories'];
        $this->assertSame(12, $named['source_records_examined']);
        $this->assertSame(10, $named['eligible_records']);
        $this->assertSame(6, $named['linked_consistent_total']);
        $this->assertSame(5, $named['linked_by_this_seal_lineage']);
        $this->assertSame(1, $named['already_linked_before_seal']);
        $this->assertSame(2, $named['unsupported_technical_imports']);
        $this->assertSame(3, $named['blocked_legacy_records']);
        $this->assertSame(3, $named['ownership_conflicts']);
        $this->assertSame(1, $named['client_reference_conflicts']);
        $this->assertSame(0, $named['booking_link_conflicts']);
        $this->assertSame(0, $named['missing_provenance_inquiries']);
        $this->assertSame(0, $named['structurally_invalid_inquiries']);
        $this->assertSame(1, $named['legitimately_changed_drafts']);
        $this->assertSame(0, $named['unresolved_evidence']);
        $this->assertSame(0, $named['records_not_processed']);
        $this->assertSame(1, $named['independent_inquiries']);

        // Область «пригодные источники» проверена не полностью: 5 расхождений ждут решения, и это видно.
        $this->assertFalse($acc['eligible_scope']['fully_verified']);
        $this->assertSame(4, $acc['eligible_scope']['not_verified']);

        // Защищённый набор не изменился; исходные строки идентичны; ни Booking, ни пользователь, ни аудит не созданы.
        $this->assertTrue($result['seal_after']['intact']);
        $this->assertSame($sourcesBefore, json_encode(DB::table('incoming_inquiries')->orderBy('id')->get()->all()));
        $this->assertSame($bookings, Booking::withTrashed()->count());
        $this->assertSame($users, DB::table('users')->count());
        $this->assertSame($audit, DB::table('audit_events')->count());
        $this->assertTrue($result['apply']['integrity']['ok']);
        $this->assertSame(5, $result['apply']['inquiries_created']);
    }

    public function test_full_scenario_reports_why_it_is_not_fully_converged(): void
    {
        $this->scenario();
        $result = $this->rehearse($this->seal(), true);

        $this->assertSame(['decisions_pending', 'reconciler_incomplete'], $result['status_reasons']);
    }

    public function test_a_booking_already_owned_by_another_inquiry_is_a_structural_anomaly_not_a_quiet_decision(): void
    {
        $this->scenario(true);
        $manifest = $this->seal();
        $bookings = Booking::withTrashed()->count();

        $result = $this->rehearse($manifest, true);

        $this->assertSame(Offline::NOT_CONVERGED, $result['status']);
        $this->assertContains('structural_anomalies', $result['status_reasons']);
        $this->assertSame(1, $result['accounting']['requested_categories']['booking_link_conflicts']);
        $this->assertSame(1, $result['accounting']['requested_categories']['structurally_invalid_inquiries']);
        $this->assertSame(5, $result['accounting']['decisions_required']);
        $this->assertSame($bookings, Booking::withTrashed()->count(), 'no Booking is created and none is re-pointed');
        $this->assertArrayNotHasKey((int) $this->s['duplicate_booking']->id, $this->linked(), 'the contested source gets no Inquiry');
        $this->assertTrue($result['accounting']['sources_by_category']['complete']);
    }

    public function test_created_inquiries_use_the_i2_mapping_and_copy_nothing_inferred(): void
    {
        $this->scenario();
        $this->rehearse($this->seal(), true);
        $linked = $this->linked();

        $expected = [
            'new_unassigned' => ['status' => 'new', 'assigned_to' => null, 'client_user_id' => null],
            'in_progress_null_client' => ['status' => 'in_progress', 'assigned_to' => $this->a->id, 'client_user_id' => null],
            'in_progress_client' => ['status' => 'in_progress', 'assigned_to' => $this->b->id, 'client_user_id' => $this->tourist->id],
            'converted' => ['status' => 'converted', 'assigned_to' => $this->a->id, 'client_user_id' => $this->tourist->id],
            'closed' => ['status' => 'closed', 'assigned_to' => null, 'client_user_id' => null, 'closed_by' => $this->admin->id],
        ];

        foreach ($expected as $key => $fields) {
            $row = $linked[(int) $this->s[$key]->id] ?? null;
            $this->assertNotNull($row, "$key must have been linked");
            foreach ($fields as $field => $value) {
                $this->assertSame($value, $row[$field], "$key.$field");
            }
        }

        $this->assertSame((int) $this->s['converted']->booking_id, $linked[(int) $this->s['converted']->id]['booking_id']);

        // Блокированные, неподдерживаемые и спорные источники не получили Inquiry.
        foreach (['technical_failed', 'unsupported_type', 'missing_assignee', 'nulled_assignee', 'client_not_tourist'] as $key) {
            $this->assertArrayNotHasKey((int) $this->s[$key]->id, $linked, $key);
        }
    }

    public function test_existing_inquiries_are_never_overwritten_and_the_draft_survives(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $draftBefore = Inquiry::query()->where('incoming_inquiry_id', $this->s['prebackfilled']->id)->firstOrFail()->getAttributes();
        $conflictBefore = Inquiry::query()->where('incoming_inquiry_id', $this->s['ownership_conflict']->id)->firstOrFail()->getAttributes();

        $this->rehearse($manifest, true);

        $this->assertSame($draftBefore, Inquiry::query()->where('incoming_inquiry_id', $this->s['prebackfilled']->id)->firstOrFail()->getAttributes());
        $this->assertSame(1, (int) $draftBefore['draft_revision']);
        $this->assertSame($conflictBefore, Inquiry::query()->where('incoming_inquiry_id', $this->s['ownership_conflict']->id)->firstOrFail()->getAttributes());
        $this->assertSame($this->b->id, (int) $conflictBefore['assigned_to'], 'the ownership dispute is held, not repaired');
    }

    public function test_conflicts_are_reported_by_reason_and_need_a_decision_not_a_repair(): void
    {
        $this->scenario();
        $result = $this->rehearse($this->seal(), true);

        $planner = $result['verification']['full_comparison_i5']['planner_categories'];
        $this->assertSame(1, $planner['REASSIGNMENT']);
        $this->assertArrayNotHasKey('DUPLICATE_BOOKING', $planner);
        $this->assertSame(1, $planner['UNRESOLVED_CLIENT_REFERENCE']);
        $this->assertSame(2, $planner['UNRESOLVED_USER_REFERENCE'] + ($planner['REQUIRES_OWNER_DECISION'] ?? 0));

        $actions = $result['verification']['full_comparison_i5']['proposed_actions'];
        $this->assertSame(4, $actions['HOLD_FOR_DECISION']);
        $this->assertArrayNotHasKey('MIRROR_FROM_SOURCE', $actions);

        $this->assertFalse($result['verification']['current_state_i3_i4']['fully_reconciled']);
        $this->assertTrue($result['verification']['current_state_i3_i4']['structurally_consistent']);
    }

    // ---------------------------------------------------------------- повторы и прерывания

    public function test_a_second_application_changes_nothing(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $first = $this->rehearse($manifest, true);
        $after = $this->state();

        $second = $this->rehearse($manifest, true);

        $this->assertSame($after, $this->state());
        $this->assertSame(0, $second['apply']['inquiries_created']);
        $this->assertSame($first['accounting']['sources_by_category'], $second['accounting']['sources_by_category']);
        $this->assertSame(Offline::DECISIONS_REQUIRED, $second['status']);
        $this->assertTrue($second['seal_after']['intact']);
    }

    public function test_an_interrupted_apply_is_reported_and_a_rerun_ends_in_the_same_state_as_an_uninterrupted_run(): void
    {
        $this->scenario();
        $manifest = $this->seal();

        $calls = 0;
        Inquiry::creating(function () use (&$calls): void {
            if (++$calls === 3) {
                throw new RuntimeException('SYNTH-INTERRUPT secret detail');
            }
        });

        $interrupted = $this->rehearse($manifest, true);

        $this->assertSame(Offline::INTERRUPTED, $interrupted['status']);
        $this->assertSame(RuntimeException::class, $interrupted['apply']['interrupted_by']);
        $this->assertStringNotContainsString('SYNTH-INTERRUPT', json_encode($interrupted), 'only the class is reported');
        $partial = $this->linked();
        $this->assertCount(2 + 2, $partial, 'prebackfilled + conflict + two completed creations; no half-written row');
        $this->assertTrue($interrupted['seal_after']['intact'], 'a failed apply does not touch the protected set');

        Inquiry::flushEventListeners();
        $rerun = $this->rehearse($manifest, true);

        $this->assertSame(Offline::DECISIONS_REQUIRED, $rerun['status']);
        $this->assertSame(3, $rerun['apply']['inquiries_created'], 'the rerun adds only what is missing');
        $afterRerun = $this->linked();

        // Тот же итог, что у непрерванного прогона: каждый пригодный источник связан ровно один раз.
        $this->assertCount(7, $afterRerun, 'prebackfilled + ownership conflict + 5 created');
        $this->assertSame(
            [
                'new_unassigned' => 'new', 'in_progress_null_client' => 'in_progress', 'in_progress_client' => 'in_progress',
                'converted' => 'converted', 'closed' => 'closed', 'prebackfilled' => 'in_progress', 'ownership_conflict' => 'in_progress',
            ],
            collect(['new_unassigned', 'in_progress_null_client', 'in_progress_client', 'converted', 'closed', 'prebackfilled', 'ownership_conflict'])
                ->mapWithKeys(fn (string $key): array => [$key => $afterRerun[(int) $this->s[$key]->id]['status']])->all()
        );
        $this->assertSame(array_keys($partial), array_values(array_intersect(array_keys($afterRerun), array_keys($partial))), 'rows from the interrupted run were kept, not recreated');
        $this->assertSame(4, $rerun['accounting']['decisions_required']);
    }

    // ---------------------------------------------------------------- неожиданный писатель

    public function test_a_writer_that_changes_a_protected_table_during_apply_makes_the_run_unsafe(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $before = $this->linked();

        Inquiry::creating(function (): void {
            DB::table('incoming_inquiries')->where('id', $this->s['new_unassigned']->id)->update(['client_comment' => 'WRITTEN BY SOMEONE ELSE']);
        });

        $result = $this->rehearse($manifest, true);

        $this->assertSame(Offline::UNSAFE_REHEARSAL, $result['status']);
        $this->assertTrue($result['provisional']);
        $this->assertContains('incoming_inquiries', $result['seal_after']['changed_tables']);
        $this->assertContains('source_changed_during_scan', $result['window']['blockers']);
        $this->assertNotSame($before, $this->linked());
        $this->assertFalse($result['production_cutover_authorized']);
    }

    public function test_a_changed_user_table_during_apply_is_also_unsafe(): void
    {
        $this->scenario();
        $manifest = $this->seal();

        Inquiry::creating(function (): void {
            DB::table('users')->where('id', $this->b->id)->update(['name' => 'Renamed by a stray writer']);
        });

        $result = $this->rehearse($manifest, true);

        $this->assertSame(Offline::UNSAFE_REHEARSAL, $result['status']);
        $this->assertContains('users', $result['seal_after']['changed_tables']);
    }

    // ---------------------------------------------------------------- отказ ДО записи

    public function test_every_preflight_refusal_happens_before_any_write(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $state = $this->state();

        // Источник изменился после запечатывания.
        DB::table('incoming_inquiries')->where('id', $this->s['closed']->id)->update(['close_reason' => 'changed after seal']);
        $changed = $this->state();

        $mismatch = $this->rehearse($manifest, true);
        $this->assertSame(Offline::UNSAFE_REHEARSAL, $mismatch['status']);
        $this->assertContains('snapshot_differs_from_manifest', $mismatch['refusals']);
        $this->assertFalse($mismatch['apply_attempted']);
        $this->assertSame($changed, $this->state(), 'no Inquiry was created');
        $this->assertNotSame($state, $changed);

        // Манифеста нет — область не определена.
        $none = $this->rehearse(null, true);
        $this->assertContains('snapshot_manifest_not_supplied', $none['refusals']);
        $this->assertSame($changed, $this->state());

        // Неполные заявления оператора — изоляция не доказана.
        $partial = $this->rehearse($manifest, true, new FreezeAttestation(['ingress_stopped']));
        $this->assertContains('isolation_not_proven', $partial['refusals']);
        $this->assertSame($changed, $this->state());
    }

    public function test_non_synthetic_data_is_refused_at_sealing_and_at_run(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        DB::table('users')->where('id', $this->b->id)->update(['email' => 'someone.real@yandex.ru']);
        $state = $this->state();

        $run = $this->rehearse($manifest, true);
        $this->assertContains('non_synthetic_data_present', $run['refusals']);
        $this->assertSame($state, $this->state());
        $this->assertStringNotContainsString('yandex', json_encode($run));

        $seal = (new Offline($this->qa))->seal(FreezeAttestation::all());
        $this->assertFalse($seal['sealed']);
        $this->assertNull($seal['manifest']);
    }

    public function test_dry_run_writes_nothing_and_never_reports_convergence(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $state = $this->state();

        $result = $this->rehearse($manifest, false);

        $this->assertSame($state, $this->state());
        $this->assertSame(Offline::NOT_CONVERGED, $result['status']);
        $this->assertSame(['records_not_processed'], $result['status_reasons']);
        $this->assertFalse($result['apply_attempted']);
        $this->assertSame(5, $result['accounting']['requested_categories']['records_not_processed']);
    }

    public function test_a_file_backed_sqlite_snapshot_is_refused_because_isolation_cannot_be_proven(): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aoa03_qa_i6_' . bin2hex(random_bytes(4)) . '.sqlite';
        touch($file);

        config(['database.connections.i6_file' => [
            'driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true, 'qa_disposable' => true,
        ]]);

        try {
            Artisan::call('migrate', ['--database' => 'i6_file', '--force' => true]);
            $qa = VerifiedQaConnection::verify('i6_file');
            $before = DB::connection('i6_file')->table('inquiries')->count();

            $result = (new Offline($qa))->run(null, FreezeAttestation::all(), true);

            $this->assertSame(Offline::UNSAFE_REHEARSAL, $result['status']);
            $this->assertContains('isolation_not_proven', $result['refusals']);
            $this->assertContains('sessions_not_observable', $result['preflight']['start_window']['unverifiable']);
            $this->assertFalse($result['apply_attempted']);
            $this->assertSame($before, DB::connection('i6_file')->table('inquiries')->count());
        } finally {
            DB::purge('i6_file');
            File::delete($file);
        }
    }

    // ---------------------------------------------------------------- чистый случай и границы

    public function test_a_snapshot_without_conflicts_is_converged_offline_and_unsupported_records_stay_separate(): void
    {
        $this->src(null);
        $this->src($this->a, $this->tourist, ['client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00']);
        $this->src(null, null, ['state' => IncomingInquiry::STATE_PENDING]);
        $manifest = $this->seal();

        $result = $this->rehearse($manifest, true);

        $this->assertSame(Offline::CONVERGED_OFFLINE, $result['status']);
        $this->assertSame(0, $result['accounting']['decisions_required']);
        $this->assertTrue($result['accounting']['eligible_scope']['fully_verified']);
        $this->assertSame(2, $result['accounting']['requested_categories']['linked_consistent_total']);
        $this->assertSame(1, $result['accounting']['requested_categories']['unsupported_technical_imports'], 'excluded, not counted as migrated');
        $this->assertSame(3, $result['accounting']['source_population']['examined']);
        $this->assertFalse($result['production_cutover_authorized']);
        $this->assertArrayHasKey('capture_completeness_not_established', $result['cutover_blockers']);
    }

    public function test_a_read_only_pass_after_apply_confirms_convergence_without_writing(): void
    {
        $this->src(null);
        $this->src($this->a);
        $manifest = $this->seal();
        $this->rehearse($manifest, true);
        $state = $this->state();

        $verify = $this->rehearse($manifest, false);

        $this->assertSame(Offline::CONVERGED_OFFLINE, $verify['status']);
        $this->assertFalse($verify['apply_attempted']);
        $this->assertSame($state, $this->state());
    }

    public function test_the_i5_cutover_blockers_are_carried_over_unchanged(): void
    {
        foreach (InquiryFreezeGate::CUTOVER_BLOCKERS as $key => $text) {
            $this->assertSame($text, Offline::CUTOVER_BLOCKERS[$key]);
        }
        $this->assertCount(count(InquiryFreezeGate::CUTOVER_BLOCKERS) + 2, Offline::CUTOVER_BLOCKERS);
    }

    public function test_a_source_row_added_after_sealing_is_a_population_mismatch(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $this->src(null);

        $result = $this->rehearse($manifest, true);

        $this->assertContains('snapshot_differs_from_manifest', $result['refusals']);
        $this->assertSame(13, $result['preflight']['manifest']['source_rows']);
        $this->assertSame(12, $manifest['tables']['incoming_inquiries']['rows']);
    }

    public function test_the_report_contains_no_personal_data_payload_or_secret(): void
    {
        $this->scenario();
        $manifest = $this->seal();
        $result = $this->rehearse($manifest, true);
        $json = json_encode(['manifest' => $manifest, 'result' => $result], JSON_UNESCAPED_UNICODE);

        foreach (['example', 'SYNTH-DRAFT-EDIT', 'Синтетический', '+7 (900)', 'password', 'vendor_payload', 'WRITTEN BY'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, $needle);
        }
    }

    public function test_public_contact_forms_persist_nothing_so_they_are_not_a_database_source(): void
    {
        // Фиксирует факт инвентаризации (источник A/C): формы только отправляют письма.
        foreach (['SendContactController', 'SendHomeController'] as $controller) {
            $source = File::get(app_path("Http/Controllers/Contact/{$controller}.php"));
            foreach (['DB::', 'IncomingInquiry', 'Inquiry::', '::create(', '->save('] as $needle) {
                $this->assertStringNotContainsString($needle, $source, "$controller must not persist: $needle");
            }
            $this->assertStringContainsString('Mail::to', $source);
        }
    }

    // ---------------------------------------------------------------- команда

    public function test_command_seal_then_rehearse_with_a_manifest_file_and_exit_codes(): void
    {
        $this->src(null);
        $this->src($this->a);
        $connection = $this->qa->name;
        $attest = implode(',', FreezeAttestation::KEYS);
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aoa03_qa_i6_manifest_' . bin2hex(random_bytes(4)) . '.json';

        try {
            $exit = Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--seal' => true, '--attest' => $attest]);
            $sealOutput = Artisan::output();
            $this->assertSame(0, $exit);
            $sealed = json_decode($sealOutput, true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue($sealed['sealed']);
            File::put($path, json_encode($sealed['manifest']));

            $state = $this->state();
            $dry = Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--manifest' => $path, '--attest' => $attest, '--json' => true]);
            $this->assertSame(1, $dry, 'a dry run is never convergence');
            $this->assertSame($state, $this->state());

            $apply = Artisan::call('inquiries:offline-rehearsal', [
                '--connection' => $connection, '--manifest' => $path, '--attest' => $attest, '--apply' => true,
                '--confirm-database' => $this->qa->database, '--json' => true,
            ]);
            $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(0, $apply);
            $this->assertSame(Offline::CONVERGED_OFFLINE, $report['status']);
            $this->assertSame(2, Inquiry::query()->count());
        } finally {
            File::delete($path);
        }
    }

    public function test_command_refusals(): void
    {
        $connection = $this->qa->name;

        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', []));
        $this->assertStringContainsString('REFUSED', Artisan::output());

        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--apply' => true]));
        $this->assertStringContainsString('--confirm-database', Artisan::output());

        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--seal' => true, '--apply' => true, '--confirm-database' => $this->qa->database]));
        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--manifest' => 'does-not-exist.json']));
        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--attest' => 'nonsense']));
        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--chunk' => 5000]));

        // Без манифеста запуск — отказ предпроверки, а не «успех на пустом».
        $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $connection, '--attest' => implode(',', FreezeAttestation::KEYS), '--json' => true]));
        $this->assertContains('snapshot_manifest_not_supplied', json_decode(Artisan::output(), true)['refusals']);
    }

    public function test_command_never_accepts_an_unmarked_or_wamp_or_canonical_connection(): void
    {
        config(['database.connections.i6_unmarked' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        config(['database.connections.i6_wamp' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'aoa03_qa_x', 'qa_disposable' => true,
        ]]);
        config(['database.connections.i6_canonical' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3412, 'database' => 'turfirma_rebuild_v4', 'qa_disposable' => true,
        ]]);
        config(['database.connections.i6_remote' => [
            'driver' => 'mysql', 'host' => 'db.example.com', 'port' => 3412, 'database' => 'aoa03_qa_x', 'qa_disposable' => true,
        ]]);

        foreach (['i6_unmarked', 'i6_wamp', 'i6_canonical', 'i6_remote'] as $name) {
            $this->assertSame(1, Artisan::call('inquiries:offline-rehearsal', ['--connection' => $name, '--seal' => true]), $name);
            $this->assertStringContainsString('REFUSED', Artisan::output(), $name);
        }
    }

    public function test_the_contract_never_runs_without_a_verified_qa_connection(): void
    {
        $this->expectException(\Error::class);

        // VerifiedQaConnection имеет закрытый конструктор: подделать «проверенное» подключение нельзя.
        new VerifiedQaConnection('x', 'sqlite', ':memory:');
    }

    public function test_the_default_connection_is_not_used_when_another_connection_is_named(): void
    {
        $this->assertInstanceOf(OfflineSnapshotContract::class, new OfflineSnapshotContract($this->qa));
        $this->assertSame($this->qa->name, config('database.default'), 'the PHPUnit database is the disposable :memory: one');
        $this->assertSame(':memory:', $this->qa->database);
    }
}
