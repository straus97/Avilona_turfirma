<?php

namespace Tests\Feature;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\Backfill\InquiryBackfillClassifier;
use App\Services\Inquiries\Backfill\InquiryBackfillDecision;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\InquiryCatchUpPlanner as Planner;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I4 — чистый планировщик будущего catch-up: категория «что произошло с источником после
 * переноса» и предлагаемое действие. Ничего не пишет и не сканирует. Часть тестов — на готовых
 * решениях классификатора (без БД), часть — сквозные на синтетических данных через настоящий
 * классификатор I2.
 */
class InquiryCatchUpPlannerTest extends TestCase
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

    private function backfill(): void
    {
        (new InquiryBackfillService($this->qa))->apply();
    }

    /** @return array{category: string, action: string, reasons: list<string>} */
    private function planFor(IncomingInquiry $source): array
    {
        $loaded = IncomingInquiry::query()->select(InquiryBackfillClassifier::SOURCE_COLUMNS)->whereKey($source->id)->get();
        $decision = (new InquiryBackfillClassifier($this->qa))->classify($loaded)[(int) $source->id];

        return (new Planner())->plan($decision, Inquiry::query()->where('incoming_inquiry_id', $source->id)->value('status'));
    }

    private function legacy(IncomingInquiry $source, array $attributes): void
    {
        DB::table('incoming_inquiries')->where('id', $source->id)->update($attributes);
    }

    private function decision(string $verdict, array $reasons = [], ?string $workflow = 'in_progress'): InquiryBackfillDecision
    {
        return new InquiryBackfillDecision(1, $verdict, $reasons, true, $workflow, 'imported');
    }

    // ------------------------------------------------------------------ сквозные сценарии

    public function test_source_created_after_the_backfill_is_a_new_eligible_source(): void
    {
        $this->inquiryFor($this->a);
        $this->backfill();
        $arrived = $this->inquiryFor(null);

        $this->assertSame(
            ['category' => Planner::NEW_ELIGIBLE_SOURCE, 'action' => Planner::ACTION_CREATE_FROM_SOURCE, 'reasons' => []],
            $this->planFor($arrived)
        );
        $this->assertSame(1, Inquiry::query()->count(), 'планирование ничего не создаёт');
    }

    public function test_unchanged_pair_needs_nothing(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();

        $this->assertSame(Planner::UNCHANGED_LINKED, $this->planFor($source)['category']);
        $this->assertSame(Planner::ACTION_NONE, $this->planFor($source)['action']);
    }

    public function test_legacy_conversion_after_backfill_is_a_mirror_candidate(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $booking = $this->bookingOf($this->tourist, $this->a);
        $this->legacy($source, ['workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);

        $plan = $this->planFor($source);

        $this->assertSame(Planner::POST_BACKFILL_CONVERSION, $plan['category']);
        $this->assertSame(Planner::ACTION_MIRROR_FROM_SOURCE, $plan['action']);
        $this->assertContains('diverges:booking_id', $plan['reasons']);
    }

    public function test_legacy_closure_after_backfill_is_a_mirror_candidate(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacy($source, ['workflow_state' => 'closed', 'closed_at' => now(), 'closed_by' => $this->a->id, 'close_reason' => 'Клиент отказался']);

        $plan = $this->planFor($source);

        $this->assertSame(Planner::CLOSURE, $plan['category']);
        $this->assertSame(Planner::ACTION_MIRROR_FROM_SOURCE, $plan['action']);
    }

    public function test_legacy_client_link_after_backfill_is_a_mirror_candidate(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacy($source, ['client_user_id' => $this->tourist->id, 'client_linked_by' => $this->a->id, 'client_linked_at' => now()]);

        $plan = $this->planFor($source);

        $this->assertSame(Planner::CLIENT_LINK_CHANGE, $plan['category']);
        $this->assertSame(Planner::ACTION_MIRROR_FROM_SOURCE, $plan['action']);
    }

    public function test_legacy_reassignment_is_held_with_no_winner(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacyReassign($source, $this->b);

        $plan = $this->planFor($source);

        $this->assertSame(Planner::REASSIGNMENT, $plan['category']);
        $this->assertSame(Planner::ACTION_HOLD_FOR_DECISION, $plan['action']);
        $this->assertSame($this->a->id, (int) Inquiry::query()->value('assigned_to'), 'Inquiry не переписывается');
    }

    public function test_deleted_assignee_leaves_an_unmappable_pair_for_the_owner(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $this->a->id)->delete(); // внешние ключи обнуляют ответственного в обеих таблицах

        $plan = $this->planFor($source);

        $this->assertSame(Planner::REQUIRES_OWNER_DECISION, $plan['category']);
        $this->assertSame(Planner::ACTION_HOLD_FOR_DECISION, $plan['action']);
        $this->assertContains('invariant_in_progress_without_assignee', $plan['reasons']);
    }

    public function test_deleting_a_client_nulls_both_sides_and_stays_unchanged(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $this->tourist->id)->delete(); // сырой SQL, минуя охрану UserDeletion

        $this->assertSame(Planner::UNCHANGED_LINKED, $this->planFor($source)['category']);
    }

    public function test_booking_already_owned_by_another_inquiry_is_a_duplicate_booking_hold(): void
    {
        $source = $this->inquiryFor($this->a, $this->tourist);
        $this->backfill();
        $booking = $this->bookingOf($this->tourist, $this->a);
        $this->independentInquiry($this->a, null, $this->tourist, ['status' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);
        $this->legacy($source, ['workflow_state' => 'converted', 'booking_id' => $booking->id, 'converted_at' => now(), 'converted_by' => $this->a->id]);

        $plan = $this->planFor($source);

        $this->assertSame(Planner::DUPLICATE_BOOKING, $plan['category']);
        $this->assertSame(Planner::ACTION_HOLD_FOR_DECISION, $plan['action']);
    }

    public function test_inquiry_that_is_ahead_of_its_source_is_never_overwritten(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        DB::table('inquiries')->where('incoming_inquiry_id', $source->id)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $this->a->id]);

        $plan = $this->planFor($source);

        $this->assertSame(Planner::INQUIRY_AHEAD, $plan['category']);
        $this->assertSame(Planner::ACTION_HOLD_FOR_DECISION, $plan['action']);
    }

    public function test_technical_and_non_actionable_sources_are_not_candidates(): void
    {
        $failed = $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_FAILED]);
        $worked = $this->inquiryFor($this->a, null, ['state' => IncomingInquiry::STATE_FAILED]);

        $this->assertSame(['category' => Planner::UNSUPPORTED_TECHNICAL_SOURCE, 'action' => Planner::ACTION_NONE], array_intersect_key($this->planFor($failed), ['category' => 1, 'action' => 1]));
        $this->assertSame(Planner::REQUIRES_OWNER_DECISION, $this->planFor($worked)['category'], 'с обращением уже работали — исход придумывать нельзя');
        $this->assertSame(0, Inquiry::query()->count());
    }

    // ------------------------------------------------------------------ чистые правила

    public function test_unresolved_references_are_held_by_kind(): void
    {
        $planner = new Planner();

        $this->assertSame(Planner::UNRESOLVED_USER_REFERENCE, $planner->plan($this->decision(InquiryBackfillDecision::BLOCKED, ['unresolved_assignee']), null)['category']);
        $this->assertSame(Planner::UNRESOLVED_USER_REFERENCE, $planner->plan($this->decision(InquiryBackfillDecision::BLOCKED, ['unresolved_actor_reference']), null)['category']);
        $this->assertSame(Planner::UNRESOLVED_CLIENT_REFERENCE, $planner->plan($this->decision(InquiryBackfillDecision::BLOCKED, ['unresolved_client']), null)['category']);
        $this->assertSame(Planner::UNRESOLVED_CLIENT_REFERENCE, $planner->plan($this->decision(InquiryBackfillDecision::BLOCKED, ['client_not_tourist']), null)['category']);
        $this->assertSame(Planner::REQUIRES_OWNER_DECISION, $planner->plan($this->decision(InquiryBackfillDecision::BLOCKED, ['unresolved_booking']), null)['category']);
        $this->assertSame(Planner::REQUIRES_OWNER_DECISION, $planner->plan($this->decision(InquiryBackfillDecision::CONFLICT, ['duplicate_inquiries_for_source']), 'in_progress')['category']);
    }

    public function test_unknown_divergence_or_same_stage_difference_stops_for_the_owner(): void
    {
        $planner = new Planner();

        $unknownField = $this->decision(InquiryBackfillDecision::CONFLICT, ['diverges:origin']);
        $sameStage = $this->decision(InquiryBackfillDecision::CONFLICT, ['diverges:close_reason'], 'closed');

        $this->assertSame(Planner::REQUIRES_OWNER_DECISION, $planner->plan($unknownField, 'in_progress')['category']);
        $this->assertSame(Planner::REQUIRES_OWNER_DECISION, $planner->plan($sameStage, 'closed')['category']);
    }

    public function test_only_new_source_and_mirror_categories_ever_propose_a_write(): void
    {
        $planner = new Planner();
        $cases = [
            [InquiryBackfillDecision::NOT_PROCESSED, [], 'new', null, [Planner::ACTION_CREATE_FROM_SOURCE]],
            [InquiryBackfillDecision::MATCHED, [], 'in_progress', 'in_progress', [Planner::ACTION_NONE]],
            [InquiryBackfillDecision::UNSUPPORTED, ['external_type_not_supported'], 'new', null, [Planner::ACTION_NONE]],
            [InquiryBackfillDecision::BLOCKED, ['unresolved_assignee'], 'in_progress', null, [Planner::ACTION_HOLD_FOR_DECISION]],
            [InquiryBackfillDecision::CONFLICT, ['diverges:assigned_to'], 'in_progress', 'in_progress', [Planner::ACTION_HOLD_FOR_DECISION]],
            [InquiryBackfillDecision::CONFLICT, ['diverges:assigned_to', 'diverges:status'], 'closed', 'in_progress', [Planner::ACTION_HOLD_FOR_DECISION]],
            [InquiryBackfillDecision::CONFLICT, ['diverges:status'], 'in_progress', 'closed', [Planner::ACTION_HOLD_FOR_DECISION]],
            [InquiryBackfillDecision::CONFLICT, ['booking_owned_by_other_inquiry', 'diverges:booking_id'], 'converted', 'in_progress', [Planner::ACTION_HOLD_FOR_DECISION]],
            [InquiryBackfillDecision::CONFLICT, ['diverges:status', 'diverges:closed_at'], 'closed', 'in_progress', [Planner::ACTION_MIRROR_FROM_SOURCE]],
        ];

        foreach ($cases as [$verdict, $reasons, $workflow, $inquiryStatus, $allowed]) {
            $plan = $planner->plan($this->decision($verdict, $reasons, $workflow), $inquiryStatus);
            $this->assertContains($plan['action'], $allowed, $verdict . ' ' . implode(',', $reasons));
        }
    }

    public function test_planning_is_read_only(): void
    {
        $source = $this->inquiryFor($this->a);
        $this->backfill();
        $this->legacyReassign($source, $this->b);
        $arrived = $this->inquiryFor(null);
        $hash = fn () => hash('sha256', json_encode([
            DB::table('incoming_inquiries')->orderBy('id')->get()->all(),
            DB::table('inquiries')->orderBy('id')->get()->all(),
            DB::table('bookings')->orderBy('id')->get()->all(),
            DB::table('audit_events')->count(),
        ]));
        $before = $hash();

        $this->planFor($source);
        $this->planFor($arrived);

        $this->assertSame($before, $hash(), 'планирование не меняет ни таблицы, ни журнал');
        $this->assertSame(1, Inquiry::query()->count(), 'и не создаёт Inquiry для поступившего источника');
    }
}
