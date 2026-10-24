<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use App\Services\IncomingInquiries\IncomingInquiryWorkflow;
use App\Services\Inquiries\Backfill\InquiryBackfillDecision;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryBackfillVerifier;
use App\Services\Inquiries\Backfill\UnsafeBackfillConnection;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AOA-03-I2 — контролируемый перенос IncomingInquiry → Inquiry и независимая сверка.
 * Только синтетические данные, SQLite :memory:. Канонической БД и MySQL здесь нет.
 */
class InquiryBackfillTest extends TestCase
{
    use RefreshDatabase;

    private VerifiedQaConnection $qa;
    private int $seq = 0;
    private User $admin;
    private User $manager;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        // Подключение теста (SQLite :memory:) положительно помечается как одноразовое.
        config(['database.connections.sqlite.qa_disposable' => true]);
        $this->qa = VerifiedQaConnection::verify('sqlite');

        $this->admin = $this->userWithRoles([Role::ADMIN]);
        $this->manager = $this->userWithRoles([Role::MANAGER]);
        $this->tourist = $this->userWithRoles([Role::TOURIST]);
    }

    // ---------------------------------------------------------------- helpers

    private function userWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function source(array $overrides = []): IncomingInquiry
    {
        $this->seq++;

        $source = new IncomingInquiry();
        $source->forceFill(array_merge([
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => sprintf('%09d', $this->seq),
            'state' => IncomingInquiry::STATE_IMPORTED,
            'workflow_state' => IncomingInquiry::WORKFLOW_NEW,
            'webhook_count' => 1,
            'client_name' => 'Синтетический Клиент',
            'client_phone' => '+7 (900) 111-22-33',
            'client_email' => 'synthetic' . $this->seq . '@example.test',
            'client_comment' => 'SYNTH-COMMENT-' . $this->seq,
            'vendor_payload' => ['secret' => 'SYNTH-PAYLOAD'],
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'fly_date' => '2026-12-01',
            'nights' => 7,
            'created_at' => '2026-01-10 09:00:00',
            'updated_at' => '2026-01-11 09:00:00',
        ], $overrides))->save();

        return $source->refresh();
    }

    private function inProgress(array $overrides = []): IncomingInquiry
    {
        return $this->source(array_merge([
            'workflow_state' => IncomingInquiry::WORKFLOW_IN_PROGRESS,
            'assigned_to' => $this->manager->id,
            'claimed_at' => '2026-02-01 10:00:00',
        ], $overrides));
    }

    private function booking(?User $client = null): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create([
            'user_id' => ($client ?? $this->tourist)->id,
            'manager_id' => $this->manager->id,
            'status' => Booking::STATUS_PROGRESS,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => 150000,
        ]));
    }

    private function converted(?Booking $booking = null, array $overrides = []): IncomingInquiry
    {
        return $this->inProgress(array_merge([
            'workflow_state' => IncomingInquiry::WORKFLOW_CONVERTED,
            'client_user_id' => $this->tourist->id,
            'client_linked_by' => $this->admin->id,
            'client_linked_at' => '2026-02-02 11:00:00',
            'booking_id' => ($booking ?? $this->booking())->id,
            'converted_at' => '2026-02-03 12:00:00',
            'converted_by' => $this->manager->id,
        ], $overrides));
    }

    private function closed(array $overrides = []): IncomingInquiry
    {
        return $this->source(array_merge([
            'workflow_state' => IncomingInquiry::WORKFLOW_CLOSED,
            'closed_at' => '2026-02-04 13:00:00',
            'closed_by' => $this->admin->id,
            'close_reason' => 'Клиент не ответил',
        ], $overrides));
    }

    /** Четыре бизнес-состояния + одно техническое: типовой набор. */
    private function seedAllStatuses(): array
    {
        return [
            'new' => $this->source(),
            'in_progress' => $this->inProgress([
                'client_user_id' => $this->tourist->id,
                'client_linked_by' => $this->admin->id,
                'client_linked_at' => '2026-02-02 11:00:00',
            ]),
            'converted' => $this->converted(),
            'closed' => $this->closed(),
        ];
    }

    private function service(): InquiryBackfillService
    {
        return new InquiryBackfillService($this->qa);
    }

    private function verifier(): InquiryBackfillVerifier
    {
        return new InquiryBackfillVerifier($this->qa);
    }

    private function inquiryFor(IncomingInquiry $source): ?Inquiry
    {
        return Inquiry::query()->where('incoming_inquiry_id', $source->id)->first();
    }

    /** SQLite внутри транзакции теста не умеет отключать FK; отложенная проверка допускает «битую» ссылку. */
    private function deferForeignKeys(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }

    private function legacyHash(): string
    {
        return hash('sha256', json_encode(DB::table('incoming_inquiries')->orderBy('id')->get()->all()));
    }

    private function ts(mixed $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    // ---------------------------------------------------------------- mapping

    public function test_each_direct_status_mapping_preserves_status_and_genuine_fields(): void
    {
        $s = $this->seedAllStatuses();

        $report = $this->service()->apply();

        $this->assertSame(4, $report['inquiries_created']);
        $this->assertSame(['CREATED' => 4], $report['outcomes']);
        $this->assertSame(4, $report['inquiries_table_delta']);

        foreach ($s as $workflow => $source) {
            $inquiry = $this->inquiryFor($source);
            $this->assertNotNull($inquiry, $workflow);
            $this->assertSame($workflow, $inquiry->status, $workflow);
            $this->assertSame(Inquiry::ORIGIN_EXTERNAL_INTAKE, $inquiry->origin);
            $this->assertNull($inquiry->created_by);
            $this->assertSame($source->assigned_to, $inquiry->assigned_to);
            $this->assertSame($this->ts($source->claimed_at), $this->ts($inquiry->assigned_at));
            $this->assertSame($source->client_user_id, $inquiry->client_user_id);
            $this->assertSame($source->client_linked_by === null ? null : (int) $source->client_linked_by, $inquiry->client_linked_by === null ? null : (int) $inquiry->client_linked_by);
            $this->assertSame($this->ts($source->client_linked_at), $this->ts($inquiry->client_linked_at));
            $this->assertSame($source->booking_id, $inquiry->booking_id);
            $this->assertSame($this->ts($source->converted_at), $this->ts($inquiry->converted_at));
            $this->assertSame($this->ts($source->closed_at), $this->ts($inquiry->closed_at));
            $this->assertSame($source->close_reason, $inquiry->close_reason);
        }

        $closed = $this->inquiryFor($s['closed']);
        $this->assertSame('Клиент не ответил', $closed->close_reason);
        $this->assertSame((int) $this->admin->id, (int) $closed->closed_by);
        $this->assertNull($this->inquiryFor($s['new'])->assigned_to);
        $this->assertSame((int) $s['converted']->booking_id, $this->inquiryFor($s['converted'])->booking_id);
    }

    public function test_historical_timestamps_are_copied_not_invented(): void
    {
        $source = $this->source();

        $this->service()->apply();

        $inquiry = $this->inquiryFor($source);
        $this->assertSame('2026-01-10 09:00:00', $this->ts($inquiry->created_at));
        $this->assertSame('2026-01-11 09:00:00', $this->ts($inquiry->updated_at));
        $this->assertNull($inquiry->assigned_at);
        $this->assertNull($inquiry->closed_at);
        $this->assertNull($inquiry->converted_at);
    }

    public function test_closed_from_new_keeps_null_assignment_and_closed_in_progress_keeps_the_assignee(): void
    {
        $fromNew = $this->closed();
        $fromProgress = $this->closed(['assigned_to' => $this->manager->id, 'claimed_at' => '2026-02-01 10:00:00']);

        $this->service()->apply();

        $this->assertNull($this->inquiryFor($fromNew)->assigned_to);
        $this->assertSame((int) $this->manager->id, $this->inquiryFor($fromProgress)->assigned_to);
        $this->assertSame('2026-02-01 10:00:00', $this->ts($this->inquiryFor($fromProgress)->assigned_at));
    }

    public function test_in_progress_without_client_and_new_without_assignment_are_valid_nullable_cases(): void
    {
        $noClient = $this->inProgress();
        $unassigned = $this->source();

        $this->service()->apply();

        $this->assertNull($this->inquiryFor($noClient)->client_user_id);
        $this->assertNull($this->inquiryFor($unassigned)->assigned_to);
        $this->assertSame(Inquiry::STATUS_NEW, $this->inquiryFor($unassigned)->status);
    }

    public function test_copied_fields_exclude_contacts_payload_provider_identity_and_trip_draft(): void
    {
        $source = $this->inProgress();

        $this->service()->apply();

        $row = (array) DB::table('inquiries')->where('incoming_inquiry_id', $source->id)->first();
        $serialized = json_encode($row, JSON_UNESCAPED_UNICODE);

        foreach (['SYNTH-PAYLOAD', 'SYNTH-COMMENT', 'synthetic', '+7 (900)', 'Синтетический', '000000001', 'tourvisor'] as $needle) {
            $this->assertStringNotContainsString($needle, $serialized, $needle);
        }

        foreach (Inquiry::DRAFT_FIELDS as $field) {
            $this->assertNull($row[$field], $field);
        }
        $this->assertSame(0, (int) $row['draft_revision']);
        $this->assertNull($row['draft_updated_by']);
    }

    // ---------------------------------------------------------------- converted / invariants

    public function test_converted_source_links_the_existing_booking_and_creates_no_booking(): void
    {
        $booking = $this->booking();
        $source = $this->converted($booking);
        $bookings = Booking::withTrashed()->count();

        $this->service()->apply();

        $this->assertSame($bookings, Booking::withTrashed()->count());
        $this->assertSame((int) $booking->id, $this->inquiryFor($source)->booking_id);
    }

    public function test_soft_deleted_booking_is_still_a_valid_existing_link(): void
    {
        $booking = $this->booking();
        $source = $this->converted($booking);
        $booking->delete();

        $report = $this->service()->apply();

        $this->assertSame(1, $report['outcomes']['CREATED']);
        $this->assertSame((int) $booking->id, $this->inquiryFor($source)->booking_id);
    }

    public function test_converted_without_booking_is_blocked_and_not_rewritten(): void
    {
        $source = $this->converted();
        DB::table('incoming_inquiries')->where('id', $source->id)->update(['booking_id' => null]);
        $before = $this->legacyHash();

        $report = $this->service()->dryRun();

        $this->assertSame(1, $report['verdicts'][InquiryBackfillDecision::BLOCKED]);
        $this->assertSame(1, $report['reasons']['invariant_converted_without_booking']);
        $this->assertSame(1, $report['summary']['blocked_by_model_invariants']);

        $apply = $this->service()->apply();
        $this->assertSame(0, $apply['inquiries_created']);
        $this->assertSame(['BLOCKED' => 1], $apply['outcomes']);
        $this->assertSame($before, $this->legacyHash());
        $this->assertSame('converted', IncomingInquiry::query()->find($source->id)->workflow_state);
    }

    public function test_in_progress_without_assignee_is_blocked_with_the_status_untouched(): void
    {
        $source = $this->inProgress();
        // Удаление сотрудника обнуляет assigned_to (nullOnDelete) у ещё «в работе» обращения.
        $this->manager->delete();

        $report = $this->service()->apply();

        $this->assertSame(['BLOCKED' => 1], $report['outcomes']);
        $this->assertSame(1, $report['reasons']['invariant_in_progress_without_assignee']);
        $this->assertNull($this->inquiryFor($source));
        $this->assertSame('in_progress', IncomingInquiry::query()->find($source->id)->workflow_state);
    }

    public function test_other_invariant_violations_are_reported_with_distinct_codes(): void
    {
        $newAssigned = $this->source();
        DB::table('incoming_inquiries')->where('id', $newAssigned->id)->update(['assigned_to' => $this->manager->id]);
        $closedNoTime = $this->closed();
        DB::table('incoming_inquiries')->where('id', $closedNoTime->id)->update(['closed_at' => null]);
        $bookingOnProgress = $this->inProgress(['booking_id' => $this->booking()->id]);
        $convertedNoTime = $this->converted();
        DB::table('incoming_inquiries')->where('id', $convertedNoTime->id)->update(['converted_at' => null]);
        $closureOnNew = $this->source(['closed_by' => $this->admin->id]);

        $report = $this->service()->dryRun();

        $this->assertSame(5, $report['verdicts'][InquiryBackfillDecision::BLOCKED]);
        foreach (['invariant_assignee_on_new', 'invariant_closed_without_timestamp', 'invariant_booking_on_non_converted',
                     'invariant_converted_without_timestamp', 'closure_fields_on_non_closed'] as $code) {
            $this->assertSame(1, $report['reasons'][$code], $code);
        }
        $this->assertSame(0, $report['summary']['proposed_new_inquiries']);
        $this->assertNull($this->inquiryFor($newAssigned));
        unset($bookingOnProgress, $closureOnNew);
    }

    // ---------------------------------------------------------------- references / sources

    public function test_unresolved_employee_and_client_references_are_reported_without_fabricating_accounts(): void
    {
        $users = User::query()->count();
        $badAssignee = $this->inProgress();
        $badClient = $this->inProgress(['client_user_id' => $this->tourist->id]);
        $nonTourist = $this->inProgress(['client_user_id' => $this->manager->id]);

        $this->deferForeignKeys();
        DB::table('incoming_inquiries')->where('id', $badAssignee->id)->update(['assigned_to' => 99999]);
        DB::table('incoming_inquiries')->where('id', $badClient->id)->update(['client_user_id' => 99998]);

        $report = $this->service()->apply();

        $this->assertSame(3, $report['verdicts'][InquiryBackfillDecision::BLOCKED]);
        $this->assertSame(1, $report['reasons']['unresolved_assignee']);
        $this->assertSame(1, $report['reasons']['unresolved_client']);
        $this->assertSame(1, $report['reasons']['client_not_tourist']);
        $this->assertSame(2 + 1, $report['summary']['unresolved_references']);
        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame($users, User::query()->count());
        $this->assertSame(99999, (int) IncomingInquiry::query()->find($badAssignee->id)->assigned_to);
        unset($nonTourist);
    }

    public function test_technical_states_stay_distinguishable_and_are_not_turned_into_business_outcomes(): void
    {
        $this->source(['state' => IncomingInquiry::STATE_FAILED]);
        $this->source(['state' => IncomingInquiry::STATE_UNSUPPORTED, 'external_type' => 1]);
        $this->source(['state' => IncomingInquiry::STATE_PENDING]);
        $this->source(['state' => IncomingInquiry::STATE_IMPORTED, 'external_type' => 1]);
        $this->source(); // единственное пригодное

        $report = $this->service()->apply();

        $this->assertSame(4, $report['verdicts'][InquiryBackfillDecision::UNSUPPORTED]);
        $this->assertSame(1, $report['reasons']['technical_state_failed']);
        $this->assertSame(1, $report['reasons']['technical_state_unsupported']);
        $this->assertSame(1, $report['reasons']['technical_state_pending']);
        $this->assertSame(1, $report['reasons']['external_type_not_supported']);
        $this->assertSame(5, $report['source_total']);
        $this->assertSame(1, $report['eligible']);
        $this->assertSame(1, $report['inquiries_created']);
        $this->assertSame(['failed' => 1, 'imported' => 2, 'pending' => 1, 'unsupported' => 1], $report['technical_state_distribution']);
    }

    public function test_non_actionable_source_with_business_progress_is_blocked_not_skipped(): void
    {
        $this->inProgress(['state' => IncomingInquiry::STATE_FAILED]);

        $report = $this->service()->apply();

        $this->assertSame(['BLOCKED' => 1], $report['outcomes']);
        $this->assertSame(1, $report['reasons']['business_progress_on_non_actionable_source']);
        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_unknown_workflow_or_import_state_is_unsupported_and_never_normalised(): void
    {
        $workflow = $this->source();
        $import = $this->source();
        DB::table('incoming_inquiries')->where('id', $workflow->id)->update(['workflow_state' => 'archived']);
        DB::table('incoming_inquiries')->where('id', $import->id)->update(['state' => 'mystery']);

        $report = $this->service()->apply();

        $this->assertSame(2, $report['verdicts'][InquiryBackfillDecision::UNSUPPORTED]);
        $this->assertSame(1, $report['reasons']['unknown_workflow_state']);
        $this->assertSame(1, $report['reasons']['unknown_import_state']);
        $this->assertSame(['archived' => 1, 'new' => 1], $report['status_distribution']);
        $this->assertSame('archived', IncomingInquiry::query()->find($workflow->id)->workflow_state);
        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_duplicate_inquiries_for_one_source_are_a_conflict_and_nothing_is_added(): void
    {
        $source = $this->source();
        Schema::table('inquiries', fn ($table) => $table->dropUnique(['incoming_inquiry_id']));
        foreach ([1, 2] as $_) {
            DB::table('inquiries')->insert(['origin' => 'external_intake', 'status' => 'new', 'incoming_inquiry_id' => $source->id,
                'created_at' => '2026-01-10 09:00:00', 'updated_at' => '2026-01-11 09:00:00']);
        }

        $report = $this->service()->apply();

        $this->assertSame(['CONFLICT' => 1], $report['outcomes']);
        $this->assertSame(1, $report['summary']['duplicate_source_conflicts']);
        $this->assertSame(2, Inquiry::query()->count());

        $verify = $this->verifier()->verify();
        $this->assertSame(1, $verify['global']['duplicate_source_links']);
        $this->assertFalse($verify['fully_verified']);
    }

    public function test_two_sources_referencing_one_booking_are_both_conflicts_and_no_winner_is_picked(): void
    {
        $booking = $this->booking();
        Schema::table('incoming_inquiries', fn ($table) => $table->dropUnique(['booking_id']));
        $a = $this->converted($booking);
        $b = $this->converted($booking);
        $bookings = Booking::withTrashed()->count();

        $report = $this->service()->apply();

        $this->assertSame(['CONFLICT' => 2], $report['outcomes']);
        $this->assertSame(2, $report['reasons']['duplicate_booking_reference_in_sources']);
        $this->assertSame(2, $report['summary']['booking_linkage_conflicts']);
        $this->assertNull($this->inquiryFor($a));
        $this->assertNull($this->inquiryFor($b));
        $this->assertSame($bookings, Booking::withTrashed()->count());
    }

    public function test_existing_booking_owner_is_preserved_and_the_second_reference_is_a_conflict(): void
    {
        $booking = $this->booking();
        $source = $this->converted($booking);
        // Заявку уже занимает посторонний Inquiry (в I2 такого нет в норме; защита от перезаписи владения).
        DB::table('inquiries')->insert([
            'origin' => Inquiry::ORIGIN_MANAGER_CREATED, 'status' => Inquiry::STATUS_CONVERTED,
            'created_by' => $this->manager->id, 'assigned_to' => $this->manager->id,
            'booking_id' => $booking->id, 'converted_at' => '2026-03-01 10:00:00',
            'created_at' => '2026-03-01 09:00:00', 'updated_at' => '2026-03-01 09:00:00',
        ]);
        $manual = (array) DB::table('inquiries')->first();

        $report = $this->service()->apply();

        $this->assertSame(['CONFLICT' => 1], $report['outcomes']);
        $this->assertSame(1, $report['reasons']['booking_owned_by_other_inquiry']);
        $this->assertNull($this->inquiryFor($source));
        $this->assertSame($manual, (array) DB::table('inquiries')->first());
    }

    // ---------------------------------------------------------------- dry-run / apply / idempotency

    public function test_dry_run_reports_counts_without_writes_or_personal_data(): void
    {
        $this->seedAllStatuses();
        $this->source(['state' => IncomingInquiry::STATE_FAILED]);
        $before = $this->legacyHash();

        $report = $this->service()->dryRun();
        $json = json_encode($report, JSON_UNESCAPED_UNICODE);

        $this->assertSame('dry_run', $report['mode']);
        $this->assertSame(0, $report['writes_performed']);
        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame($before, $this->legacyHash());
        $this->assertSame(5, $report['source_total']);
        $this->assertSame(4, $report['eligible']);
        $this->assertSame(4, $report['summary']['proposed_new_inquiries']);
        $this->assertSame(0, $report['summary']['already_linked']);
        $this->assertSame(1, $report['summary']['unsupported_sources']);
        $this->assertSame(['closed' => 1, 'converted' => 1, 'in_progress' => 1, 'new' => 2], $report['status_distribution']);

        foreach (['Синтетический', 'synthetic', '+7 (900)', 'SYNTH-', 'passport', 'vendor_payload', 'example.test'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, $needle);
        }
    }

    public function test_first_apply_then_identical_second_apply_creates_nothing_more(): void
    {
        $this->seedAllStatuses();
        $this->source(['state' => IncomingInquiry::STATE_FAILED]);

        $first = $this->service()->apply();
        $snapshot = DB::table('inquiries')->orderBy('id')->get()->all();
        $second = $this->service()->apply();

        $this->assertSame(4, $first['inquiries_created']);
        $this->assertSame(['CREATED' => 4, 'UNSUPPORTED' => 1], $first['outcomes']);
        $this->assertSame(0, $second['inquiries_created']);
        $this->assertSame(['ALREADY_PRESENT' => 4, 'UNSUPPORTED' => 1], $second['outcomes']);
        $this->assertEquals($snapshot, DB::table('inquiries')->orderBy('id')->get()->all());
        $this->assertTrue($first['integrity']['ok']);
        $this->assertTrue($second['integrity']['ok']);
    }

    public function test_apply_changes_neither_legacy_rows_nor_bookings_nor_audit_events(): void
    {
        $this->seedAllStatuses();
        $legacy = $this->legacyHash();
        $bookings = Booking::withTrashed()->count();
        $audit = AuditEvent::query()->count();
        $assignments = DB::table('incoming_inquiries')->pluck('assigned_to', 'id')->all();

        $report = $this->service()->apply();

        $this->assertTrue($report['integrity']['legacy_unchanged']);
        $this->assertTrue($report['integrity']['bookings_unchanged']);
        $this->assertTrue($report['integrity']['audit_events_unchanged']);
        $this->assertSame($legacy, $this->legacyHash());
        $this->assertSame($bookings, Booking::withTrashed()->count());
        $this->assertSame($audit, AuditEvent::query()->count());
        $this->assertSame($assignments, DB::table('incoming_inquiries')->pluck('assigned_to', 'id')->all());
    }

    public function test_preexisting_matching_inquiry_is_left_untouched(): void
    {
        $source = $this->inProgress();
        $this->service()->apply();
        $inquiry = $this->inquiryFor($source);
        // Редактирование черновика не считается расхождением и не затрагивается повтором.
        $inquiry->forceFill(['internal_notes' => 'rehearsal note', 'draft_revision' => 3])->save();
        $row = (array) DB::table('inquiries')->where('id', $inquiry->id)->first();

        $report = $this->service()->apply();

        $this->assertSame(['ALREADY_PRESENT' => 1], $report['outcomes']);
        $this->assertSame($row, (array) DB::table('inquiries')->where('id', $inquiry->id)->first());
    }

    public function test_conflicting_preexisting_inquiry_is_reported_and_never_replaced(): void
    {
        $source = $this->inProgress();
        $other = $this->userWithRoles([Role::MANAGER]);
        DB::table('inquiries')->insert([
            'origin' => 'external_intake', 'status' => 'in_progress', 'incoming_inquiry_id' => $source->id,
            'assigned_to' => $other->id, 'created_at' => '2026-01-10 09:00:00', 'updated_at' => '2026-01-11 09:00:00',
        ]);
        $row = (array) DB::table('inquiries')->first();

        $dry = $this->service()->dryRun();
        $apply = $this->service()->apply();

        $this->assertSame(['CONFLICT' => 1], $apply['outcomes']);
        $this->assertSame(1, $dry['reasons']['diverges:assigned_to']);
        $this->assertSame(1, $dry['reasons']['diverges:assigned_at']);
        $this->assertSame($row, (array) DB::table('inquiries')->first());
        $this->assertSame(1, Inquiry::query()->count());
        $this->assertFalse($this->verifier()->verify()['fully_verified']);
    }

    public function test_manual_inquiries_are_never_touched(): void
    {
        $this->source();
        DB::table('inquiries')->insert([
            'origin' => Inquiry::ORIGIN_MANAGER_CREATED, 'status' => Inquiry::STATUS_IN_PROGRESS,
            'created_by' => $this->manager->id, 'assigned_to' => $this->manager->id,
            'created_at' => '2026-03-01 09:00:00', 'updated_at' => '2026-03-01 09:00:00',
        ]);
        $manual = (array) DB::table('inquiries')->where('origin', Inquiry::ORIGIN_MANAGER_CREATED)->first();

        $this->service()->apply();

        $this->assertSame($manual, (array) DB::table('inquiries')->where('origin', Inquiry::ORIGIN_MANAGER_CREATED)->first());
        $this->assertSame(2, Inquiry::query()->count());
        $verify = $this->verifier()->verify();
        $this->assertSame(1, $verify['global']['inquiries_manual']);
        $this->assertTrue($verify['fully_verified']);
    }

    public function test_interruption_leaves_only_complete_rows_and_retry_finishes_without_duplicates(): void
    {
        $sources = [$this->source(), $this->source(), $this->source(), $this->source(), $this->source()];
        $created = 0;
        $armed = true;

        Inquiry::creating(function () use (&$created, &$armed): void {
            if ($armed && ++$created === 3) {
                throw new \RuntimeException('simulated crash');
            }
        });

        try {
            $this->service()->apply(2);
            $this->fail('The simulated crash must abort the run.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated crash', $e->getMessage());
        }

        $this->assertSame(2, Inquiry::query()->count());
        $this->assertSame(0, DB::table('inquiries')->whereNull('origin')->count());

        $armed = false;
        $retry = $this->service()->apply(2);

        $this->assertSame(3, $retry['inquiries_created']);
        $this->assertSame(['ALREADY_PRESENT' => 2, 'CREATED' => 3], $retry['outcomes']);
        $this->assertSame(5, Inquiry::query()->count());
        $this->assertSame(5, Inquiry::query()->distinct()->count('incoming_inquiry_id'));
        $this->assertTrue($this->verifier()->verify()['fully_verified']);
        unset($sources);
    }

    public function test_a_lost_unique_race_is_counted_and_settled_by_reclassification(): void
    {
        $source = $this->source();
        $armed = true;

        Inquiry::creating(function () use (&$armed): void {
            if ($armed) {
                $armed = false;
                throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \Exception('duplicate'));
            }
        });

        $report = $this->service()->apply();

        $this->assertSame(1, $report['race_resolved']);
        $this->assertSame(0, Inquiry::query()->count());

        $retry = $this->service()->apply();
        $this->assertSame(['CREATED' => 1], $retry['outcomes']);
        $this->assertNotNull($this->inquiryFor($source));
    }

    public function test_chunk_boundaries_do_not_change_results_and_cross_chunk_booking_duplicates_are_found(): void
    {
        $booking = $this->booking();
        Schema::table('incoming_inquiries', fn ($table) => $table->dropUnique(['booking_id']));
        $first = $this->converted($booking);
        $this->source();
        $this->inProgress();
        $this->closed();
        $this->source();
        $this->source(['state' => IncomingInquiry::STATE_FAILED]);
        $last = $this->converted($booking);

        $small = $this->service()->dryRun(2);
        $large = $this->service()->dryRun(1000);

        $this->assertSame($large, $small);
        $this->assertSame(2, $small['reasons']['duplicate_booking_reference_in_sources']);
        $this->assertSame(4, $small['summary']['proposed_new_inquiries']);

        $this->service()->apply(2);
        $this->assertSame(4, Inquiry::query()->count());
        $this->assertNull($this->inquiryFor($first));
        $this->assertNull($this->inquiryFor($last));
    }

    public function test_invalid_chunk_sizes_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->dryRun(0);
    }

    // ---------------------------------------------------------------- verification

    public function test_verifier_reports_not_processed_before_apply_and_fully_verified_after(): void
    {
        $this->seedAllStatuses();
        $this->source(['state' => IncomingInquiry::STATE_FAILED]);

        $before = $this->verifier()->verify();
        $this->assertFalse($before['fully_verified']);
        $this->assertSame(4, $before['verdicts'][InquiryBackfillDecision::NOT_PROCESSED]);
        $this->assertSame(1, $before['verdicts'][InquiryBackfillDecision::UNSUPPORTED]);
        $this->assertSame(0, Inquiry::query()->count(), 'verification must not write');

        $this->service()->apply();
        $after = $this->verifier()->verify();

        $this->assertTrue($after['fully_verified']);
        $this->assertSame(4, $after['verdicts'][InquiryBackfillDecision::MATCHED]);
        $this->assertSame(1, $after['verdicts'][InquiryBackfillDecision::UNSUPPORTED]);
        $this->assertSame(4, $after['global']['inquiries_total']);
        $this->assertSame(4, $after['global']['inquiries_external_intake']);
        $this->assertSame(0, $after['global']['external_inquiries_without_source']);
        $this->assertSame(0, $after['global']['duplicate_booking_links']);
    }

    public function test_a_blocked_source_prevents_full_verification_even_after_apply(): void
    {
        $this->source();
        $blocked = $this->converted();
        DB::table('incoming_inquiries')->where('id', $blocked->id)->update(['booking_id' => null]);

        $this->service()->apply();
        $verify = $this->verifier()->verify();

        $this->assertFalse($verify['fully_verified']);
        $this->assertSame(1, $verify['verdicts'][InquiryBackfillDecision::BLOCKED]);
        $this->assertSame(1, $verify['verdicts'][InquiryBackfillDecision::MATCHED]);
    }

    public function test_verifier_finds_orphan_external_inquiries(): void
    {
        $this->source();
        $this->service()->apply();
        $this->deferForeignKeys();
        DB::table('inquiries')->insert([
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE, 'status' => 'new', 'incoming_inquiry_id' => 99999,
            'created_at' => '2026-01-10 09:00:00', 'updated_at' => '2026-01-11 09:00:00',
        ]);

        $verify = $this->verifier()->verify();

        $this->assertSame(1, $verify['global']['external_inquiries_without_source']);
        $this->assertFalse($verify['fully_verified']);
    }

    public function test_verifier_flags_drift_after_the_legacy_workflow_moves_on(): void
    {
        $manager = $this->manager;
        $client = $this->tourist;
        $source = $this->inProgress(['client_user_id' => $client->id, 'client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00']);
        $this->service()->apply();

        app(IncomingInquiryWorkflow::class)->close($source, $manager, 'Клиент отказался');

        $verify = $this->verifier()->verify();
        $this->assertFalse($verify['fully_verified']);
        $this->assertSame(1, $verify['verdicts'][InquiryBackfillDecision::CONFLICT]);
        $this->assertSame(1, $verify['reasons']['diverges:status']);
        $this->assertSame('in_progress', $this->inquiryFor($source)->status, 'the verifier must not repair anything');
    }

    // ---------------------------------------------------------------- legacy E5 + visibility

    public function test_e5_conversion_stays_idempotent_after_backfill(): void
    {
        $booking = $this->booking();
        $source = $this->converted($booking);
        $this->service()->apply();
        $bookings = Booking::withTrashed()->count();

        $result = app(IncomingInquiryWorkflow::class)->convert($source, $this->manager, []);

        $this->assertFalse($result['created']);
        $this->assertSame((int) $booking->id, (int) $result['booking']->id);
        $this->assertSame($bookings, Booking::withTrashed()->count());
        $this->assertSame((int) $booking->id, (int) IncomingInquiry::query()->find($source->id)->booking_id);
    }

    public function test_e5_workflow_still_converts_one_booking_for_a_backfilled_in_progress_source(): void
    {
        $source = $this->inProgress(['client_user_id' => $this->tourist->id, 'client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00']);
        $this->service()->apply();
        $payload = [
            'departure_city' => 'Москва', 'destination_country' => 'Турция', 'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(), 'nights' => 7, 'adults' => 2, 'children' => 0, 'total_price' => 100000,
        ];

        $first = app(IncomingInquiryWorkflow::class)->convert($source, $this->manager, $payload);
        $second = app(IncomingInquiryWorkflow::class)->convert($source, $this->manager, $payload);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, Booking::withTrashed()->count());
        $this->assertSame(1, IncomingInquiry::query()->whereNotNull('booking_id')->count());
    }

    public function test_backfilled_inquiries_grant_no_new_client_visibility(): void
    {
        $outsider = $this->userWithRoles([Role::MANAGER]);
        $other = $this->userWithRoles([Role::TOURIST]);
        $this->inProgress(['client_user_id' => $other->id, 'client_linked_by' => $this->admin->id, 'client_linked_at' => '2026-02-02 11:00:00']);
        $this->converted();

        $visible = fn (User $u): array => ClientVisibility::touristsVisibleTo($u)->orderBy('users.id')->pluck('users.id')->all();
        $before = [$visible($this->manager), $visible($outsider), $visible($this->admin)];

        $this->service()->apply();

        $this->assertSame($before, [$visible($this->manager), $visible($outsider), $visible($this->admin)]);
        $this->assertNotContains($other->id, $visible($outsider));
    }

    public function test_no_runtime_listener_creates_inquiries_automatically(): void
    {
        $this->source();
        $this->assertSame(0, Inquiry::query()->count());
    }

    // ---------------------------------------------------------------- database safety

    private function configureQa(array $overrides = []): void
    {
        config(['database.connections.aoa_qa' => array_merge([
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3397',
            'database' => 'aoa03_qa_rehearsal', 'username' => 'qa', 'password' => 'qa',
            'unix_socket' => '', 'qa_disposable' => true,
        ], $overrides)]);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function unsafeConnections(): array
    {
        return [
            'no marker' => [['qa_disposable' => false]],
            'marker as string' => [['qa_disposable' => 'true']],
            'wamp port 3306' => [['port' => '3306']],
            'wamp port 3308' => [['port' => 3308]],
            'no port' => [['port' => null]],
            'privileged port' => [['port' => 80]],
            'canonical database' => [['database' => 'turfirma_rebuild_v4']],
            'canonical database upper' => [['database' => 'TURFIRMA_REBUILD_V4']],
            'unprefixed database' => [['database' => 'rehearsal']],
            'missing database' => [['database' => null]],
            'remote host' => [['host' => 'db.example.test']],
            'url override' => [['url' => 'mysql://root@db.example.test/turfirma_rebuild_v4']],
            'socket override' => [['unix_socket' => '/tmp/mysql.sock']],
            'unsupported driver' => [['driver' => 'pgsql']],
        ];
    }

    #[DataProvider('unsafeConnections')]
    public function test_unsafe_connections_are_refused_before_any_query(array $overrides): void
    {
        $this->configureQa($overrides);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $connections = count(DB::getConnections());

        try {
            VerifiedQaConnection::verify('aoa_qa');
            $this->fail('The connection must be refused.');
        } catch (UnsafeBackfillConnection) {
            $this->assertSame(0, $queries);
            $this->assertCount($connections, DB::getConnections());
        }
    }

    public function test_a_connection_equal_to_the_default_server_is_refused(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.port' => '3397']);
        $this->configureQa();

        try {
            VerifiedQaConnection::verify('aoa_qa');
            $this->fail('The connection must be refused.');
        } catch (UnsafeBackfillConnection) {
            $this->addToAssertionCount(1);
        } finally {
            config(['database.default' => 'sqlite']);
        }
    }

    public function test_a_valid_isolated_mysql_configuration_is_accepted_without_connecting(): void
    {
        $this->configureQa();
        $connections = count(DB::getConnections());

        $qa = VerifiedQaConnection::verify('aoa_qa');

        $this->assertSame('aoa03_qa_rehearsal', $qa->database);
        $this->assertCount($connections, DB::getConnections());
    }

    public function test_name_defaults_and_unknown_connections_and_production_are_refused(): void
    {
        foreach ([null, '', '  ', 'no such', 'missing_connection'] as $name) {
            try {
                VerifiedQaConnection::verify($name);
                $this->fail('refused: ' . var_export($name, true));
            } catch (UnsafeBackfillConnection) {
                $this->addToAssertionCount(1);
            }
        }

        // Подключение по умолчанию без маркера QA не годится.
        config(['database.connections.sqlite.qa_disposable' => false]);
        $this->expectException(UnsafeBackfillConnection::class);
        VerifiedQaConnection::verify('sqlite');
    }

    public function test_production_environment_is_refused(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(UnsafeBackfillConnection::class);
        VerifiedQaConnection::verify('sqlite');
    }

    public function test_sqlite_files_must_be_aoa03_qa_files_in_the_temp_directory(): void
    {
        $temp = sys_get_temp_dir();
        config(['database.connections.qa_file' => ['driver' => 'sqlite', 'database' => $temp . DIRECTORY_SEPARATOR . 'aoa03_qa_ok.sqlite', 'qa_disposable' => true]]);
        $this->assertSame('sqlite', VerifiedQaConnection::verify('qa_file')->driver);

        foreach ([database_path('database.sqlite'), $temp . DIRECTORY_SEPARATOR . 'other.sqlite', $temp . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'aoa03_qa_x.sqlite', ''] as $path) {
            config(['database.connections.qa_file.database' => $path]);
            try {
                VerifiedQaConnection::verify('qa_file');
                $this->fail('refused: ' . $path);
            } catch (UnsafeBackfillConnection) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------------------------------------------------------------- command

    public function test_command_defaults_to_dry_run_and_requires_an_explicit_verified_connection(): void
    {
        $this->seedAllStatuses();

        $this->artisan('inquiries:backfill')->expectsOutputToContain('REFUSED')->assertExitCode(1);
        $this->artisan('inquiries:backfill', ['--connection' => 'mysql'])->expectsOutputToContain('REFUSED')->assertExitCode(1);
        $this->assertSame(0, Inquiry::query()->count());

        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite'])->expectsOutputToContain('NO WRITES')->assertExitCode(0);
        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_command_apply_requires_confirmation_of_the_database_name(): void
    {
        $this->source();

        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite', '--apply' => true])->assertExitCode(1);
        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite', '--apply' => true, '--confirm-database' => 'turfirma_rebuild_v4'])->assertExitCode(1);
        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite', '--apply' => true, '--verify' => true, '--confirm-database' => ':memory:'])->assertExitCode(1);
        $this->assertSame(0, Inquiry::query()->count());

        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite', '--apply' => true, '--confirm-database' => ':memory:'])->assertExitCode(0);
        $this->assertSame(1, Inquiry::query()->count());

        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite', '--verify' => true])->assertExitCode(0);
    }

    public function test_command_verify_fails_when_the_backfill_is_incomplete_and_json_has_no_personal_data(): void
    {
        $this->seedAllStatuses();

        $this->artisan('inquiries:backfill', ['--connection' => 'sqlite', '--verify' => true, '--json' => true])->assertExitCode(1);

        \Illuminate\Support\Facades\Artisan::call('inquiries:backfill', ['--connection' => 'sqlite', '--json' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('dry_run', $decoded['mode']);
        $this->assertSame(4, $decoded['summary']['proposed_new_inquiries']);
        foreach (['Синтетический', 'synthetic', 'example.test', 'SYNTH-', '+7 (900)'] as $needle) {
            $this->assertStringNotContainsString($needle, $output);
        }
    }

    public function test_command_refuses_the_canonical_database_before_any_query(): void
    {
        config(['database.connections.aoa_qa' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306',
            'database' => 'turfirma_rebuild_v4', 'username' => 'x', 'password' => 'x', 'qa_disposable' => true]]);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->artisan('inquiries:backfill', ['--connection' => 'aoa_qa', '--apply' => true, '--confirm-database' => 'turfirma_rebuild_v4'])
            ->expectsOutputToContain('REFUSED')->assertExitCode(1);

        $this->assertSame(0, $queries);
    }
}
