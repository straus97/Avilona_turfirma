<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryBackfillVerifier;
use App\Services\Inquiries\Backfill\InquiryReconciler;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\InquiryDraftStore;
use App\Support\Audit\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I4 — что именно разрешает аудит черновика в сверке IncomingInquiry ↔ Inquiry и что
 * он НЕ разрешает. Аудит доказывает только правки черновика; он не отменяет порчу связей,
 * спор об ответственном и «не доказано» по статусу/клиенту/заявке. Только чтение.
 */
class InquiryDraftEvidenceReconciliationTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private VerifiedQaConnection $qa;
    private User $a;
    private User $b;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('VerifiedQaConnection refuses the test default connection on MySQL; this scenario is run by the disposable-MySQL harness (docs/AOA-03-I4).');
        }

        Mail::fake();
        Notification::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $name = config('database.default');
        config(["database.connections.{$name}.qa_disposable" => true]);
        $this->qa = VerifiedQaConnection::verify($name);

        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
    }

    private function reconcile(): array
    {
        return (new InquiryReconciler($this->qa))->reconcile();
    }

    /** Перенесённая пара: источник назначен $this->a. */
    private function mappedPair(): array
    {
        $source = $this->inquiryFor($this->a);
        (new InquiryBackfillService($this->qa))->apply();

        return [$source, Inquiry::query()->where('incoming_inquiry_id', $source->id)->firstOrFail()];
    }

    private function stateHash(): string
    {
        return hash('sha256', json_encode([
            DB::table('incoming_inquiries')->orderBy('id')->get()->all(),
            DB::table('inquiries')->orderBy('id')->get()->all(),
            DB::table('bookings')->orderBy('id')->get()->all(),
            DB::table('audit_events')->orderBy('id')->get()->all(),
        ]));
    }

    // ------------------------------------------------------------------ что аудит доказывает

    public function test_draft_edits_made_through_the_store_are_proven_evolution(): void
    {
        [, $inquiry] = $this->mappedPair();
        $store = app(InquiryDraftStore::class);
        $store->save($inquiry, $this->a, ['destination_country' => 'Турция'], 0);
        $store->save($inquiry, $this->a, ['adults' => 2], 1);

        $result = $this->reconcile();

        $this->assertSame(['IN_SYNC' => 1], $result['verdicts']);
        $this->assertSame(1, $result['expected_evolution']['draft_edited']);
        $this->assertSame(['evidenced' => 1, 'unevidenced' => 0, 'inconsistent' => 0], $result['draft_audit_evidence']);
        $this->assertTrue($result['fully_reconciled']);
        $this->assertTrue((new InquiryBackfillVerifier($this->qa))->verify()['fully_verified'], 'контракт A (начальный перенос) не ослаблен');
    }

    public function test_untouched_inquiries_need_no_draft_evidence(): void
    {
        $this->mappedPair();
        $this->independentInquiry($this->a);

        $result = $this->reconcile();

        $this->assertSame(['evidenced' => 2, 'unevidenced' => 0, 'inconsistent' => 0], $result['draft_audit_evidence']);
        $this->assertTrue($result['fully_reconciled']);
    }

    public function test_independent_inquiry_draft_edits_are_proven_too(): void
    {
        $inquiry = $this->independentInquiry($this->a);
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);

        $result = $this->reconcile();

        $this->assertSame(['INDEPENDENT' => 1], $result['verdicts']);
        $this->assertSame(1, $result['draft_audit_evidence']['evidenced']);
        $this->assertTrue($result['fully_reconciled']);
    }

    // ------------------------------------------------------------------ что аудит НЕ выдумывает

    public function test_a_draft_revision_without_events_is_not_proven_and_not_called_corruption(): void
    {
        [, $inquiry] = $this->mappedPair();
        // Правка в обход сервиса: версия выросла, события нет.
        DB::table('inquiries')->where('id', $inquiry->id)->update(['draft_revision' => 3, 'adults' => 4]);

        $result = $this->reconcile();

        $this->assertSame(['IN_SYNC' => 1], $result['verdicts'], 'вердикт пары по изменчивым полям не меняется');
        $this->assertTrue($result['structurally_consistent']);
        $this->assertSame(['evidenced' => 0, 'unevidenced' => 1, 'inconsistent' => 0], $result['draft_audit_evidence']);
        $this->assertFalse($result['fully_reconciled'], 'без доказательства правка не считается законной эволюцией');
        $this->assertSame(['draft_without_audit_evidence' => 1], $result['incomplete_because']);
        $this->assertSame([$inquiry->id], $result['sample_inquiry_ids']['draft_unevidenced']);
    }

    public function test_missing_gap_and_foreign_events_are_inconsistent(): void
    {
        [, $inquiry] = $this->mappedPair();
        $store = app(InquiryDraftStore::class);
        $store->save($inquiry, $this->a, ['adults' => 2], 0);
        $store->save($inquiry, $this->a, ['adults' => 3], 1);
        $store->save($inquiry, $this->a, ['adults' => 4], 2);

        // Пропавшее событие (ручное вмешательство в журнал): цепочка 1→2→3 рвётся.
        DB::table('audit_events')->where('event_type', 'inquiry.draft_updated')->orderBy('id')->limit(1)->delete();

        $result = $this->reconcile();

        $this->assertSame(['evidenced' => 0, 'unevidenced' => 0, 'inconsistent' => 1], $result['draft_audit_evidence']);
        $this->assertFalse($result['fully_reconciled']);
    }

    public function test_events_for_a_zero_revision_draft_are_inconsistent(): void
    {
        [, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        // «Откат» версии в обход сервиса.
        DB::table('inquiries')->where('id', $inquiry->id)->update(['draft_revision' => 0]);

        $this->assertSame(1, $this->reconcile()['draft_audit_evidence']['inconsistent']);
    }

    public function test_a_last_editor_that_differs_from_the_audited_actor_is_inconsistent(): void
    {
        [, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['draft_updated_by' => $this->b->id]);

        $this->assertSame(1, $this->reconcile()['draft_audit_evidence']['inconsistent']);
    }

    public function test_events_of_other_subjects_or_types_are_not_draft_evidence(): void
    {
        [$source, $inquiry] = $this->mappedPair();
        DB::table('inquiries')->where('id', $inquiry->id)->update(['draft_revision' => 1]);
        $recorder = app(AuditRecorder::class);

        // Тот же числовой id, но другое событие/другой субъект — не доказательство.
        $recorder->record(AuditEventType::InquiryAssigned, $this->a, 'incoming_inquiry', (int) $inquiry->id, ['new_assignee_id' => $this->a->id]);
        $recorder->record(AuditEventType::InquiryDraftUpdated, $this->a, 'incoming_inquiry', (int) $inquiry->id, ['previous_revision' => 0, 'new_revision' => 1]);
        $recorder->record(AuditEventType::InquiryDraftUpdated, $this->a, 'inquiry', (int) $inquiry->id + 1000, ['previous_revision' => 0, 'new_revision' => 1]);

        $this->assertSame(1, $this->reconcile()['draft_audit_evidence']['unevidenced']);
    }

    // ------------------------------------------------------------------ аудит не отменяет прочие правила

    public function test_proven_draft_does_not_hide_structural_corruption(): void
    {
        [, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        // Источник пропал (ручное вмешательство) — отключаем проверку внешних ключей на время правки.
        $this->withoutForeignKeyChecks(fn () => DB::table('incoming_inquiries')->where('id', $inquiry->incoming_inquiry_id)->delete());

        $result = $this->reconcile();

        $this->assertSame(1, $result['draft_audit_evidence']['evidenced']);
        $this->assertSame(['STRUCTURAL' => 1], $result['verdicts']);
        $this->assertArrayHasKey('missing_source', $result['reasons']);
        $this->assertFalse($result['structurally_consistent']);
        $this->assertFalse($result['fully_reconciled']);
    }

    public function test_proven_draft_does_not_resolve_an_ownership_conflict(): void
    {
        [$source, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        $this->legacyReassign($source, $this->b);

        $result = $this->reconcile();

        $this->assertSame(['OWNERSHIP_CONFLICT' => 1], $result['verdicts']);
        $this->assertFalse($result['fully_reconciled']);
        $this->assertSame($this->b->id, (int) DB::table('incoming_inquiries')->where('id', $source->id)->value('assigned_to'), 'победитель не выбран, источник не переписан');
        $this->assertSame($this->a->id, (int) DB::table('inquiries')->where('id', $inquiry->id)->value('assigned_to'));
    }

    public function test_proven_draft_does_not_explain_a_status_difference(): void
    {
        [$source, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $this->a->id]);

        $result = $this->reconcile();

        $this->assertSame(['REQUIRES_EVIDENCE' => 1], $result['verdicts'], 'для статуса журнала правок нет: аудит черновика его не объясняет');
        $this->assertArrayHasKey('no_inquiry_change_log', $result['limits']);
        $this->assertStringContainsString('Only trip-draft edits are audited', $result['limits']['no_inquiry_change_log']);
        $this->assertFalse($result['fully_reconciled']);
    }

    public function test_unprocessed_sources_stay_unresolved_by_audit(): void
    {
        [, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        $this->inquiryFor($this->b); // поступило после переноса

        $result = $this->reconcile();

        $this->assertSame(1, $result['unprocessed_sources']['total']);
        $this->assertArrayHasKey('no_sync_marker', $result['limits']);
        $this->assertFalse($result['fully_reconciled']);
    }

    public function test_reconciliation_stays_read_only_with_audit_evidence(): void
    {
        [, $inquiry] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        DB::table('inquiries')->where('id', $this->independentInquiry($this->b)->id)->update(['draft_revision' => 2]);
        $before = $this->stateHash();
        $events = AuditEvent::query()->count();

        $this->reconcile();
        $this->reconcile();

        $this->assertSame($before, $this->stateHash());
        $this->assertSame($events, AuditEvent::query()->count());
    }

    public function test_evidence_check_uses_a_constant_number_of_queries_per_chunk(): void
    {
        [, $first] = $this->mappedPair();
        app(InquiryDraftStore::class)->save($first, $this->a, ['adults' => 2], 0);
        $count = function (): int {
            DB::connection()->flushQueryLog();
            DB::connection()->enableQueryLog();
            $this->reconcile();

            return count(DB::connection()->getQueryLog());
        };
        $small = $count();

        for ($i = 0; $i < 6; $i++) {
            $inquiry = $this->independentInquiry($this->a);
            app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
        }

        $this->assertSame($small, $count(), 'запросы не растут с числом обращений внутри одной порции');
    }

    private function withoutForeignKeyChecks(callable $callback): void
    {
        // Тест идёт внутри транзакции, поэтому внешние ключи откладываются, а не выключаются.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $callback();
    }
}
