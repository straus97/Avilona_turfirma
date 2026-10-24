<?php

namespace Tests\Feature;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\InquiryWorkloadSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I3 — счётчики обращений: каждое бизнес-обращение учитывается один раз, права те же,
 * что у списков. Только синтетические данные.
 */
class InquiryWorkloadSummaryTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $a;
    private User $b;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);
        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
    }

    private function counts(User $actor): array
    {
        return InquiryWorkloadSummary::for($actor)['by_status'];
    }

    /** То, что показывает существующий список E5 (обрабатываемые обращения, видимые актору). */
    private function legacyList(User $actor): array
    {
        $rows = IncomingInquiry::query()->actionable()->visibleTo($actor)->pluck('workflow_state')->countBy()->all();

        return array_merge(array_fill_keys(Inquiry::statuses(), 0), $rows);
    }

    public function test_legacy_only_result_equals_the_existing_list_when_no_inquiries_exist(): void
    {
        $this->inquiryFor(null);
        $this->inquiryFor($this->a);
        $this->inquiryFor($this->b);
        $this->inquiryFor($this->a, null, ['workflow_state' => 'closed', 'closed_at' => now()]);
        $this->inquiryFor($this->b, null, ['workflow_state' => 'converted', 'booking_id' => $this->bookingOf($this->userWith('tourist'), $this->b)->id, 'converted_at' => now()]);
        // не бизнес-обращение: техническое состояние загрузки
        $this->inquiryFor(null, null, ['state' => IncomingInquiry::STATE_FAILED]);

        $this->assertSame(0, Inquiry::query()->count());

        foreach ([$this->admin, $this->a, $this->b] as $actor) {
            $summary = InquiryWorkloadSummary::for($actor);
            $this->assertSame($this->legacyList($actor), $summary['by_status']);
            $this->assertSame(0, $summary['independent']);
            $this->assertSame(0, $summary['ownership_conflicts']);
        }

        $this->assertSame(['new' => 1, 'in_progress' => 2, 'converted' => 1, 'closed' => 1], $this->counts($this->admin));
        $this->assertSame(['new' => 0, 'in_progress' => 1, 'converted' => 0, 'closed' => 1], $this->counts($this->a));
        $this->assertSame(['new' => 0, 'in_progress' => 1, 'converted' => 1, 'closed' => 0], $this->counts($this->b));
    }

    public function test_a_linked_dual_model_pair_is_counted_once(): void
    {
        $this->linkedPair($this->a, $this->a);
        $this->linkedPair(null, null);

        $this->assertSame(['new' => 1, 'in_progress' => 1, 'converted' => 0, 'closed' => 0], $this->counts($this->admin));
        $this->assertSame(2, InquiryWorkloadSummary::for($this->admin)['total']);
        $this->assertSame(2, InquiryWorkloadSummary::for($this->admin)['external']);
        $this->assertSame(['new' => 0, 'in_progress' => 1, 'converted' => 0, 'closed' => 0], $this->counts($this->a));
        $this->assertSame(0, array_sum($this->counts($this->b)));
    }

    public function test_independent_inquiries_are_counted_separately_and_by_current_owner(): void
    {
        $this->linkedPair($this->a, $this->a);
        $this->independentInquiry($this->a);
        $this->independentInquiry($this->b);
        $this->independentInquiry($this->admin);
        $closed = $this->independentInquiry($this->a);
        DB::table('inquiries')->where('id', $closed->id)->update(['status' => 'closed', 'closed_at' => now()]);

        $a = InquiryWorkloadSummary::for($this->a);
        $this->assertSame(['external' => 1, 'independent' => 2, 'total' => 3], ['external' => $a['external'], 'independent' => $a['independent'], 'total' => $a['total']]);
        $this->assertSame(['new' => 0, 'in_progress' => 2, 'converted' => 0, 'closed' => 1], $a['by_status']);

        $b = InquiryWorkloadSummary::for($this->b);
        $this->assertSame(['external' => 0, 'independent' => 1, 'total' => 1], ['external' => $b['external'], 'independent' => $b['independent'], 'total' => $b['total']]);

        $admin = InquiryWorkloadSummary::for($this->admin);
        $this->assertSame(1, $admin['external']);
        $this->assertSame(4, $admin['independent'], 'Admin видит все самостоятельные, включая собственные');
        $this->assertSame(5, $admin['total']);
    }

    public function test_reassignment_moves_the_count_to_the_new_owner(): void
    {
        [$source, $inquiry] = $this->linkedPair($this->a, $this->a);
        $own = $this->independentInquiry($this->a);

        $this->legacyReassign($source, $this->b);
        DB::table('inquiries')->where('id', $own->id)->update(['assigned_to' => $this->b->id]);

        $this->assertSame(0, InquiryWorkloadSummary::for($this->a)['total']);
        $b = InquiryWorkloadSummary::for($this->b);
        $this->assertSame(2, $b['total']);
        $this->assertSame(1, $b['external']);
        $this->assertSame(1, $b['independent']);
        // обе записи пары по-прежнему дают одно обращение для Admin
        $this->assertSame(2, InquiryWorkloadSummary::for($this->admin)['total']);
    }

    public function test_conflicting_assignments_are_counted_once_under_the_legacy_owner_and_flagged_for_admin(): void
    {
        $this->linkedPair($this->a, $this->b);    // legacy: A, снимок Inquiry: B
        $this->linkedPair($this->a, null);        // legacy: A, снимок Inquiry: ничей
        $this->linkedPair(null, $this->b);        // legacy: ничей, снимок Inquiry: B

        $a = InquiryWorkloadSummary::for($this->a);
        $this->assertSame(2, $a['total'], 'A видит и считает ровно то, что откроет в списке E5');
        $this->assertSame(0, InquiryWorkloadSummary::for($this->b)['total'], 'B не получает обращений через расхождение');
        $this->assertSame(0, $a['ownership_conflicts'], 'менеджеру конфликты не раскрываются');

        $admin = InquiryWorkloadSummary::for($this->admin);
        $this->assertSame(3, $admin['total'], 'каждая пара — одно обращение');
        $this->assertSame(3, $admin['ownership_conflicts']);
    }

    public function test_agreeing_pairs_and_independent_inquiries_are_not_conflicts(): void
    {
        $this->linkedPair($this->a, $this->a);
        $this->linkedPair(null, null);
        $this->independentInquiry($this->a);

        $this->assertSame(0, InquiryWorkloadSummary::for($this->admin)['ownership_conflicts']);
        $this->assertSame(0, InquiryWorkloadSummary::for($this->admin)['unresolved_provenance']);
    }

    public function test_malformed_provenance_is_never_counted_for_managers_and_reported_to_admin(): void
    {
        DB::table('inquiries')->insert([
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE, 'status' => 'in_progress', 'incoming_inquiry_id' => null,
            'assigned_to' => $this->a->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, InquiryWorkloadSummary::for($this->a)['total']);
        $this->assertSame(1, InquiryWorkloadSummary::for($this->admin)['unresolved_provenance']);
        $this->assertSame(0, InquiryWorkloadSummary::for($this->admin)['total'], 'не бизнес-обращение: ни legacy, ни самостоятельный');
    }

    public function test_tourist_and_unrolled_user_get_zeros(): void
    {
        $this->linkedPair($this->a, $this->a);
        $this->independentInquiry($this->a);

        foreach ([$this->userWith('tourist'), User::factory()->create()] as $actor) {
            $summary = InquiryWorkloadSummary::for($actor);
            $this->assertSame(0, $summary['total']);
            $this->assertSame(0, $summary['ownership_conflicts']);
        }
    }

    public function test_query_count_is_bounded_regardless_of_data_volume(): void
    {
        $measure = function (User $actor): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            InquiryWorkloadSummary::for($actor);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->linkedPair($this->a, $this->a);
        $small = [$measure($this->a), $measure($this->admin)];

        for ($i = 0; $i < 15; $i++) {
            $this->linkedPair($this->a, $i % 2 ? $this->a : $this->b);
            $this->independentInquiry($this->a);
        }

        $this->assertSame($small, [$measure($this->a), $measure($this->admin)], 'число запросов не зависит от объёма');
        $this->assertLessThanOrEqual(8, $small[0], 'менеджер: роли + два агрегата');
        $this->assertLessThanOrEqual(8, $small[1], 'администратор: роли + два агрегата + два счёта');
    }
}
