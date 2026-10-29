<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use App\Services\Inquiries\InquiryAuthority;
use App\Services\Inquiries\InquiryDraftStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I3 — доступ к Inquiry и клиентам через Inquiry. Только синтетические данные.
 *
 * Модель полномочий переходного периода: пока E5 работает на IncomingInquiry, для
 * ПРИВЯЗАННОЙ пары (Inquiry.incoming_inquiry_id) полномочия определяет legacy-запись, а
 * Inquiry-сторона даёт доступ только когда её ответственный совпадает с ответственным
 * источника. Самостоятельный Inquiry (без источника) определяет собственный assigned_to.
 */
class InquiryAwareAccessTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);
    }

    private function clientIds(User $actor): array
    {
        return ClientVisibility::scopeTo(User::query(), $actor)->orderBy('users.id')->pluck('users.id')->map(fn ($v) => (int) $v)->all();
    }

    private function can(User $user, string $ability, Inquiry $inquiry): bool
    {
        return Gate::forUser($user)->allows($ability, $inquiry);
    }

    // ---------------------------------------------------------------- воспроизведение дефекта

    public function test_former_manager_loses_inquiry_access_when_the_legacy_source_is_reassigned(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        [$source, $inquiry] = $this->linkedPair($a, $a);

        $this->assertTrue($this->can($a, 'view', $inquiry), 'согласованная пара: ответственный видит');

        // E5 переназначает обращение; снимок Inquiry (после переноса) ещё называет прежнего менеджера.
        $this->legacyReassign($source, $b);

        $this->assertFalse($this->can($a, 'view', $inquiry->refresh()), 'прежний менеджер больше не ответственный');
        $this->assertFalse($this->can($a, 'updateDraft', $inquiry));
        $this->assertSame(0, Inquiry::query()->visibleTo($a)->count());
    }

    public function test_conflicting_pair_grants_nothing_to_the_inquiry_side_assignee(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        // legacy ведёт A, снимок Inquiry назвал B
        [, $inquiry] = $this->linkedPair($a, $b);

        $this->assertFalse($this->can($b, 'view', $inquiry), 'на Inquiry-стороне конфликт — доступа нет');
        $this->assertFalse($this->can($a, 'view', $inquiry), 'A не назначен на Inquiry-стороне — доступа к Inquiry нет');
        $this->assertSame(0, Inquiry::query()->visibleTo($b)->count());
        $this->assertSame(0, Inquiry::query()->visibleTo($a)->count());
    }

    public function test_draft_save_by_a_former_manager_is_refused_after_legacy_reassignment(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        [$source, $inquiry] = $this->linkedPair($a, $a);
        $this->legacyReassign($source, $b);

        $this->expectException(AuthorizationException::class);
        app(InquiryDraftStore::class)->save($inquiry, $a, ['destination_country' => 'Турция'], 0);
    }

    // ---------------------------------------------------------------- матрица доступа

    public function test_access_matrix_for_independent_and_linked_inquiries(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $tourist = $this->userWith('tourist');

        $own = $this->independentInquiry($a);
        $foreign = $this->independentInquiry($b);
        $adminOwned = $this->independentInquiry($admin);
        [, $linked] = $this->linkedPair($a, $a);
        [, $unassigned] = $this->linkedPair(null, null);

        foreach (['view', 'updateDraft'] as $ability) {
            $this->assertTrue($this->can($a, $ability, $own));
            $this->assertFalse($this->can($b, $ability, $own));
            $this->assertFalse($this->can($a, $ability, $foreign));
            $this->assertFalse($this->can($a, $ability, $adminOwned), 'Admin-owned недоступен менеджеру');
            $this->assertFalse($this->can($a, $ability, $unassigned), 'ничьё внешнее недоступно менеджеру');
            $this->assertTrue($this->can($a, $ability, $linked));
            $this->assertFalse($this->can($b, $ability, $linked));
            $this->assertFalse($this->can($tourist, $ability, $own));
            foreach ([$own, $foreign, $adminOwned, $linked, $unassigned] as $any) {
                $this->assertTrue($this->can($admin, $ability, $any), 'надзор Admin сохраняется');
            }
        }

        $this->assertEqualsCanonicalizing([$own->id, $linked->id], Inquiry::query()->visibleTo($a)->pluck('id')->all());
        $this->assertSame(0, Inquiry::query()->visibleTo($tourist)->count());
        $this->assertSame(5, Inquiry::query()->visibleTo($admin)->count());
    }

    public function test_scope_and_policy_agree_on_every_combination(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $people = [null, $a, $b];
        $rows = [];

        foreach ($people as $legacy) {
            foreach ($people as $snapshot) {
                [, $row] = $this->linkedPair($legacy, $snapshot);
                $rows[] = $row;
            }
        }

        $rows[] = $this->independentInquiry($a);
        $rows[] = $this->independentInquiry($b);
        // сироты и нарушения происхождения (в реальной базе возможны только ручной правкой)
        $orphanId = DB::table('inquiries')->insertGetId([
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE, 'status' => 'in_progress', 'incoming_inquiry_id' => null,
            'assigned_to' => $a->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $manualWithSource = $this->inquiryFor($a);
        $mwsId = DB::table('inquiries')->insertGetId([
            'origin' => Inquiry::ORIGIN_MANAGER_CREATED, 'status' => 'in_progress', 'incoming_inquiry_id' => $manualWithSource->id,
            'created_by' => $a->id, 'assigned_to' => $a->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rows[] = Inquiry::query()->findOrFail($orphanId);
        $rows[] = Inquiry::query()->findOrFail($mwsId);

        foreach ([$a, $b] as $manager) {
            $byScope = Inquiry::query()->visibleTo($manager)->orderBy('id')->pluck('id')->all();
            $byPolicy = collect($rows)->filter(fn (Inquiry $r) => $this->can($manager, 'view', $r->fresh()))->pluck('id')->sort()->values()->all();
            $this->assertSame($byScope, $byPolicy, 'scope и policy обязаны давать один результат');
            $this->assertSame($byScope, InquiryAuthority::responsibleInquiryIds($manager)->orderBy('inquiries.id')->pluck('inquiries.id')->all());
        }

        // Некорректное происхождение не даёт доступа никому, кроме Admin.
        $this->assertNotContains($orphanId, Inquiry::query()->visibleTo($a)->pluck('id')->all());
        $this->assertNotContains($mwsId, Inquiry::query()->visibleTo($a)->pluck('id')->all());
    }

    // ---------------------------------------------------------------- ClientVisibility

    public function test_independent_inquiry_grants_its_client_to_the_current_assignee_only(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $this->independentInquiry($a, null, $client);

        $this->assertSame([$client->id], $this->clientIds($a));
        $this->assertSame([], $this->clientIds($b));
        $this->assertSame([], $this->clientIds($this->userWith('tourist')));
    }

    public function test_reassigning_an_independent_inquiry_revokes_client_visibility(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $inquiry = $this->independentInquiry($a, null, $client);

        DB::table('inquiries')->where('id', $inquiry->id)->update(['assigned_to' => $b->id]);

        $this->assertSame([], $this->clientIds($a), 'прежний ответственный клиента больше не видит');
        $this->assertSame([$client->id], $this->clientIds($b));
    }

    public function test_creator_and_actor_columns_never_substitute_for_current_responsibility(): void
    {
        $creator = $this->userWith('manager');
        $current = $this->userWith('manager');
        $converter = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $inquiry = $this->independentInquiry($creator, null, $client);

        DB::table('inquiries')->where('id', $inquiry->id)->update([
            'assigned_to' => $current->id, 'converted_by' => $converter->id, 'closed_by' => $converter->id,
            'client_linked_by' => $creator->id, 'draft_updated_by' => $creator->id,
        ]);

        $this->assertSame([], $this->clientIds($creator), 'создатель');
        $this->assertSame([], $this->clientIds($converter), 'автор конверсии/закрытия');
        $this->assertSame([$client->id], $this->clientIds($current));
    }

    public function test_closed_and_converted_independent_inquiries_grant_no_client_the_booking_does(): void
    {
        $a = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $inquiry = $this->independentInquiry($a, null, $client, ['status' => 'closed', 'closed_at' => now()]);
        $this->assertSame([], $this->clientIds($a), 'AOA-04-I6: закрытое обращение профиль клиента не раскрывает');

        $booking = $this->bookingOf($client, $this->userWith('manager'));
        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => 'converted', 'closed_at' => null, 'booking_id' => $booking->id, 'converted_at' => now()]);
        // AOA-05-I1: a converted Inquiry no longer grants the Tourist profile by itself; the Booking does.
        $this->assertSame([], $this->clientIds($a), 'AOA-05-I1: оформленное обращение профиль клиента не раскрывает');

        DB::table('bookings')->where('id', $booking->id)->update(['manager_id' => $a->id]);
        $this->assertSame([$client->id], $this->clientIds($a), 'доступ даёт заявка, которую ведёт менеджер');
    }

    public function test_inquiry_client_must_be_a_tourist(): void
    {
        $a = $this->userWith('manager');
        $otherStaff = $this->userWith('admin');
        $this->independentInquiry($a, null, $otherStaff);

        $this->assertSame([], $this->clientIds($a), 'не-турист не раскрывается через Inquiry');
    }

    public function test_linked_pair_grants_nothing_beyond_the_legacy_rule(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $legacyClient = $this->userWith('tourist');
        $snapshotClient = $this->userWith('tourist');

        // клиент Inquiry-снимка отличается от клиента legacy → расхождение не раскрывает «лишнего» клиента
        $this->linkedPair($a, $a, $legacyClient, $snapshotClient);
        // снимок называет B, legacy ведёт A
        $this->linkedPair($a, $b, $legacyClient, $snapshotClient);

        $this->assertSame([$legacyClient->id], $this->clientIds($a), 'доступ только по legacy-записи');
        $this->assertSame([], $this->clientIds($b), 'Inquiry-сторона при конфликте ничего не даёт');
    }

    public function test_dual_model_client_is_not_duplicated(): void
    {
        $a = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $this->linkedPair($a, $a, $client, $client);
        $this->independentInquiry($a, null, $client);
        $this->bookingOf($client, $a);

        $ids = ClientVisibility::scopeTo(User::query(), $a)->pluck('users.id')->all();
        $this->assertSame([$client->id], $ids, 'клиент через Booking + legacy + Inquiry — одна запись');
        $this->assertSame(1, ClientVisibility::touristsVisibleTo($a)->count());
    }

    public function test_admin_oversight_is_unchanged(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $c1 = $this->userWith('tourist');
        $c2 = $this->userWith('tourist');
        $this->independentInquiry($a, null, $c1);
        $this->assertEqualsCanonicalizing([$c1->id, $c2->id], ClientVisibility::touristsVisibleTo($admin)->pluck('users.id')->all());
    }

    public function test_tourist_and_unrolled_user_see_no_clients_through_inquiries(): void
    {
        $a = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $this->independentInquiry($a, null, $client);

        $this->assertSame([], $this->clientIds($client));
        $this->assertSame([], $this->clientIds(User::factory()->create()));
    }

    public function test_legacy_only_client_visibility_is_unchanged_without_any_inquiry(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $viaBooking = $this->userWith('tourist');
        $viaLegacy = $this->userWith('tourist');
        $foreign = $this->userWith('tourist');
        $this->bookingOf($viaBooking, $a);
        $this->inquiryFor($a, $viaLegacy);
        $this->inquiryFor($b, $foreign);

        $this->assertSame(0, Inquiry::query()->count());
        $this->assertEqualsCanonicalizing([$viaBooking->id, $viaLegacy->id], $this->clientIds($a));
        $this->assertSame([$foreign->id], $this->clientIds($b));
    }

    public function test_foreign_client_is_not_revealed_through_the_clients_page_or_the_booking_dropdown(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $mine = $this->userWith('tourist', ['name' => 'Клиент Через Мой Inquiry']);
        $foreign = $this->userWith('tourist', ['name' => 'Клиент Чужого Inquiry']);
        $this->independentInquiry($a, null, $mine);
        $this->independentInquiry($b, null, $foreign);

        $this->actingAs($a)->get(route('cabinet.manager.clients'))
            ->assertOk()->assertSee('Клиент Через Мой Inquiry')->assertDontSee('Клиент Чужого Inquiry');

        $html = $this->actingAs($a)->get(route('bookings.create'))->assertOk()->getContent();
        preg_match('/<select[^>]*name="client_id".*?<\/select>/s', $html, $select);
        preg_match_all('/<option value="(\d+)"/', $select[0] ?? '', $m);
        $this->assertSame([$mine->id], array_map('intval', $m[1]));
    }

    public function test_booking_access_is_preserved_by_inquiry_data(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $booking = $this->bookingOf($client, $b);
        // Inquiry менеджера A на того же клиента не открывает чужую заявку.
        $this->independentInquiry($a, null, $client);

        $this->actingAs($a)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($b)->get(route('bookings.show', $booking))->assertOk();
        $this->assertSame(1, Booking::query()->where('manager_id', $b->id)->count());
    }
}
