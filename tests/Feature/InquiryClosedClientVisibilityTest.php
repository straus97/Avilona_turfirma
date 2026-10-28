<?php

namespace Tests\Feature;

use App\Models\ClientVerificationRequest;
use App\Models\Inquiry;
use App\Models\InquiryContact;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use App\Services\IncomingInquiries\IncomingInquiryClientMatcher;
use App\Services\Inquiries\InquiryAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I6 — решение владельца: ЗАКРЫТОЕ самостоятельное обращение больше не даёт ответственному
 * менеджеру доступа к профилю клиента; открытое — даёт. Другие источники видимости (заявка, окно
 * подтверждения администратора, другое открытое обращение, legacy IncomingInquiry, надзор администратора)
 * не меняются, а карточка закрытого обращения остаётся читаемой. Только синтетические данные.
 */
class InquiryClosedClientVisibilityTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const CLIENT_NAME = 'Синтетический Клиент Закрытый';
    private const CLIENT_EMAIL = 'closed.client@example.test';
    private const CLIENT_PHONE = '+7 900 555-01-02';
    private const REASON = 'Клиент решил не ехать в этом сезоне';

    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->admin = $this->userWith('admin', ['name' => 'Админ Синтетический']);
        $this->a = $this->userWith('manager', ['name' => 'Анна Синтетическая']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Синтетический']);
        $this->tourist = $this->userWith('tourist', ['name' => 'Турист Посторонний']);
        $this->client = $this->userWith('tourist', [
            'name' => self::CLIENT_NAME, 'email' => self::CLIENT_EMAIL, 'phone' => self::CLIENT_PHONE,
        ]);
    }

    private function sees(User $actor, ?User $client = null): bool
    {
        return ClientVisibility::touristsVisibleTo($actor)->whereKey(($client ?? $this->client)->id)->exists();
    }

    private function inquiryOf(User $manager, string $status = Inquiry::STATUS_IN_PROGRESS, ?User $client = null): Inquiry
    {
        $inquiry = $this->independentInquiry($manager, null, $client ?? $this->client);

        if ($status === Inquiry::STATUS_CLOSED) {
            DB::table('inquiries')->where('id', $inquiry->id)->update([
                'status' => 'closed', 'closed_at' => now(), 'closed_by' => $manager->id, 'close_reason' => self::REASON,
            ]);
        }

        return $inquiry->refresh();
    }

    private function show(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private function approvedWindow(User $manager, User $client, \DateTimeInterface $expires): void
    {
        (new ClientVerificationRequest())->forceFill([
            'requested_by' => $manager->id, 'context_type' => 'booking_form', 'client_name' => 'Синтетический',
            'status' => ClientVerificationRequest::STATUS_APPROVED, 'resolved_user_id' => $client->id,
            'resolved_by' => $this->admin->id, 'resolved_at' => now(), 'access_expires_at' => $expires,
        ])->save();
    }

    /** id клиентов из выпадающего списка формы заявки. */
    private function dropdownIds(User $actor): array
    {
        $html = $this->actingAs($actor)->get(route('bookings.create'))->assertOk()->getContent();
        preg_match('/<select[^>]*name="client_id".*?<\/select>/s', $html, $select);
        preg_match_all('/<option value="(\d+)"/', $select[0] ?? '', $m);

        return array_map('intval', $m[1]);
    }

    // ---- the approved policy -------------------------------------------------------------------

    public function test_open_independent_inquiry_grants_and_closed_one_alone_does_not(): void
    {
        $inquiry = $this->inquiryOf($this->a);
        $this->assertTrue($this->sees($this->a));
        $this->assertFalse($this->sees($this->b));

        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => 'closed', 'closed_at' => now()]);

        $this->assertFalse($this->sees($this->a), 'закрытое обращение профиль не раскрывает');
        $this->assertSame([], ClientVisibility::scopeTo(User::query(), $this->a)->pluck('users.id')->all());
    }

    public function test_closing_through_the_route_revokes_derived_access_but_keeps_all_records(): void
    {
        $inquiry = $this->inquiryOf($this->a);
        (new InquiryContact())->forceFill(['inquiry_id' => $inquiry->id, 'contact_name' => 'Контакт Синтетический', 'revision' => 1])->save();
        $this->assertTrue($this->sees($this->a));

        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.close', $inquiry), [
            'expected_status' => $inquiry->status, 'expected_owner_id' => $inquiry->assigned_to, 'reason' => self::REASON,
        ])->assertRedirect();

        $this->assertSame('closed', $inquiry->refresh()->status);
        $this->assertFalse($this->sees($this->a));

        // История не стирается: привязка клиента, создатель, ответственный и контакт на месте.
        $this->assertSame($this->client->id, $inquiry->client_user_id);
        $this->assertSame($this->a->id, $inquiry->assigned_to);
        $this->assertSame($this->a->id, $inquiry->created_by);
        $this->assertSame(1, InquiryContact::query()->where('inquiry_id', $inquiry->id)->count());
    }

    public function test_another_open_independent_inquiry_preserves_access(): void
    {
        $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);
        $this->assertFalse($this->sees($this->a));

        $this->inquiryOf($this->a);
        $this->assertTrue($this->sees($this->a));
    }

    public function test_owned_booking_preserves_access_after_closure(): void
    {
        $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);
        $this->assertFalse($this->sees($this->a));

        $this->bookingOf($this->client, $this->a);
        $this->assertTrue($this->sees($this->a));
        $this->assertContains($this->client->id, $this->dropdownIds($this->a));
    }

    public function test_approved_verification_window_is_preserved_and_expiry_still_applies(): void
    {
        $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);
        $this->approvedWindow($this->a, $this->client, now()->addDays(10));
        $this->assertTrue($this->sees($this->a), 'действующее окно подтверждения не затронуто закрытием');

        ClientVerificationRequest::query()->update(['access_expires_at' => now()->subMinute()]);
        $this->assertFalse($this->sees($this->a), 'истёкшее окно остаётся истёкшим');
    }

    public function test_reassignment_revokes_former_assignee_and_new_assignee_gets_only_open_rights(): void
    {
        $inquiry = $this->inquiryOf($this->a);
        $this->assertTrue($this->sees($this->a));

        app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, 'Передача синтетического обращения');

        $this->assertFalse($this->sees($this->a), 'прежний ответственный');
        $this->assertTrue($this->sees($this->b), 'новый ответственный — по открытому обращению');

        // Новый ответственный закрыл — его производный доступ тоже заканчивается.
        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => 'closed', 'closed_at' => now()]);
        $this->assertFalse($this->sees($this->b));
        $this->assertFalse($this->sees($this->a));
    }

    public function test_admin_oversight_is_unchanged(): void
    {
        $inquiry = $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);

        $this->assertTrue($this->sees($this->admin));
        $this->actingAs($this->admin)->get(route('cabinet.manager.clients'))->assertOk()->assertSee(self::CLIENT_NAME);
        $page = $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk();
        $page->assertSee(self::CLIENT_NAME)->assertSee(route('cabinet.admin.user-show', $this->client->id), false);
        $this->assertContains($this->client->id, $this->dropdownIds($this->admin));
    }

    public function test_legacy_incoming_inquiry_visibility_is_unchanged(): void
    {
        $source = $this->inquiryFor($this->a, $this->client);
        $this->assertTrue($this->sees($this->a));

        // Закрытый legacy-источник по-прежнему раскрывает клиента (E5 — прежние правила).
        \DB::table('incoming_inquiries')->where('id', $source->id)->update(['workflow_state' => 'closed']);
        $this->assertTrue($this->sees($this->a));

        // Привязанный Inquiry сам клиента не раскрывает ни открытым, ни закрытым.
        [, $linked] = $this->linkedPair($this->b, $this->b, null, $this->tourist);
        $this->assertFalse($this->sees($this->b, $this->tourist));
        DB::table('inquiries')->where('id', $linked->id)->update(['status' => 'closed']);
        $this->assertFalse($this->sees($this->b, $this->tourist));
    }

    // ---- consumers of the rule -----------------------------------------------------------------

    public function test_manager_lists_search_and_selectors_do_not_enumerate_through_a_closed_inquiry(): void
    {
        $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);

        $this->actingAs($this->a)->get(route('cabinet.manager.clients'))->assertOk()
            ->assertDontSee(self::CLIENT_NAME)->assertDontSee(self::CLIENT_EMAIL)->assertDontSee(self::CLIENT_PHONE);
        $this->assertNotContains($this->client->id, $this->dropdownIds($this->a));
        $this->assertTrue(app(IncomingInquiryClientMatcher::class)->search('Закрытый', $this->a)->isEmpty());
        $this->assertTrue(app(IncomingInquiryClientMatcher::class)->search(self::CLIENT_EMAIL, $this->a)->isEmpty());
    }

    public function test_forged_client_id_is_rejected_without_leaking_account_details(): void
    {
        $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);

        $this->actingAs($this->a)->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'departure_city' => 'Москва', 'destination_country' => 'Турция', 'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(), 'nights' => 7, 'adults' => 2, 'children' => 0,
        ])->assertSessionHasErrors('client_id');

        $this->assertSame(0, \App\Models\Booking::count());
        $text = implode(' ', session('errors')->all());
        $this->assertStringNotContainsString('Закрытый', $text);
        $this->assertStringNotContainsString(self::CLIENT_EMAIL, $text);
    }

    public function test_unrelated_booking_authorization_is_unchanged(): void
    {
        $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);
        $foreignBooking = $this->bookingOf($this->client, $this->b);

        // Закрытое обращение не даёт доступа к чужой заявке этого клиента — как и открытое.
        $this->actingAs($this->a)->get(route('bookings.show', $foreignBooking))->assertStatus(403);
        $this->actingAs($this->b)->get(route('bookings.show', $foreignBooking))->assertOk();
        $this->actingAs($this->client)->get(route('bookings.show', $foreignBooking))->assertOk();
    }

    // ---- the historical card ------------------------------------------------------------------

    public function test_closed_card_stays_readable_without_an_actionable_link_or_client_details(): void
    {
        $inquiry = $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);

        $page = $this->actingAs($this->a)->get($this->show($inquiry))->assertOk();
        $page->assertSee(self::REASON)
            ->assertSee('Аккаунт привязан (профиль недоступен)')
            ->assertSee('agency-inquiry-client-archived', false)
            ->assertDontSee(self::CLIENT_NAME)
            ->assertDontSee(self::CLIENT_EMAIL)
            ->assertDontSee(self::CLIENT_PHONE)
            ->assertDontSee('карточка клиента')
            ->assertDontSee(route('cabinet.admin.user-show', $this->client->id), false)
            ->assertDontSee('Привязан клиент:');
        // Привязка не превращается во «вновь доступную для выбора»: форм поиска/создания/снятия нет.
        $page->assertDontSee('Выбрать из ваших клиентов')->assertDontSee('Снять привязку клиента');
    }

    public function test_open_card_still_shows_the_client_and_the_updated_notice(): void
    {
        $inquiry = $this->inquiryOf($this->a);

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()
            ->assertSee(self::CLIENT_NAME)
            ->assertSee('После закрытия обращения этот доступ прекращается');
    }

    public function test_closed_card_keeps_client_visible_when_another_entitlement_exists(): void
    {
        $inquiry = $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);
        $this->bookingOf($this->client, $this->a);

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()
            ->assertSee(self::CLIENT_NAME)->assertDontSee('agency-inquiry-client-archived', false);
    }

    public function test_former_assignee_foreign_manager_tourist_and_guest_behaviour_is_unchanged(): void
    {
        $inquiry = $this->inquiryOf($this->a, Inquiry::STATUS_CLOSED);

        $this->actingAs($this->b)->get($this->show($inquiry))->assertNotFound();
        $this->actingAs($this->tourist)->get($this->show($inquiry))->assertForbidden();
        $this->actingAs($this->client)->get($this->show($inquiry))->assertForbidden();

        auth()->logout();
        $this->get($this->show($inquiry))->assertRedirect();

        // Бывший ответственный после передачи открытого обращения: 404, клиента не видит.
        $open = $this->inquiryOf($this->a);
        app(InquiryAssignment::class)->assign($open, $this->admin, $this->b->id, $this->a->id, 'Передача синтетического обращения');
        $this->actingAs($this->a)->get($this->show($open))->assertNotFound();
        $this->assertFalse($this->sees($this->a));
    }
}
