<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryClientAssociation;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I4, Gate C — явная привязка клиента к самостоятельному обращению: выбор ВИДИМОГО клиента,
 * создание и отвязка администратором, отсутствие автосопоставления, перезаписи и утечек. Синтетические
 * данные, SQLite :memory:.
 */
class InquiryClientAssociationTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $admin2;
    private User $a;
    private User $b;
    private User $own;      // клиент менеджера A (его заявка)
    private User $foreign;  // клиент менеджера B

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->admin = $this->userWith('admin', ['name' => 'Админ Первый']);
        $this->admin2 = $this->userWith('admin', ['name' => 'Админ Второй']);
        $this->a = $this->userWith('manager', ['name' => 'Анна Менеджер']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Менеджер']);
        $this->own = $this->userWith('tourist', ['name' => 'Повторный Клиент', 'email' => 'repeat.client@example.test']);
        $this->foreign = $this->userWith('tourist', ['name' => 'Чужой Клиент', 'email' => 'foreign.client@example.test']);
        $this->bookingOf($this->own, $this->a);
        $this->bookingOf($this->foreign, $this->b);
    }

    private function mine(?User $manager = null): Inquiry
    {
        return Inquiry::factory()->managerCreated($manager ?? $this->a)->create()->refresh();
    }

    private function route(string $name, Inquiry $inquiry): string
    {
        return route("cabinet.manager.agency-inquiries.client.{$name}", $inquiry);
    }

    private function show(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private function select(User $actor, Inquiry $inquiry, int $userId, ?int $expected = null)
    {
        return $this->actingAs($actor)->post($this->route('select', $inquiry), ['user_id' => $userId, 'expected_client_id' => $expected]);
    }

    private function events(string $type)
    {
        return AuditEvent::query()->where('event_type', $type)->orderBy('id')->get();
    }

    private function failAudit(): void
    {
        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });
    }

    // ---- visible-client selection ------------------------------------------------------------

    public function test_manager_links_a_client_already_visible_to_them(): void
    {
        $inquiry = $this->mine();
        $users = User::query()->count();
        $bookings = Booking::query()->count();

        $this->select($this->a, $inquiry, $this->own->id)
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $inquiry->refresh();
        $this->assertSame($this->own->id, $inquiry->client_user_id);
        $this->assertSame($this->a->id, $inquiry->client_linked_by);
        $this->assertNotNull($inquiry->client_linked_at);

        $event = $this->events('inquiry.client_linked')->sole();
        $this->assertSame(['client_id' => $this->own->id, 'via' => 'selected', 'verification_id' => null], $event->metadata);
        $this->assertSame($this->a->id, (int) $event->actor_id);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($bookings, Booking::query()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->assertSee('Повторный Клиент');
    }

    public function test_unavailable_clients_are_indistinguishable_for_a_manager(): void
    {
        $inquiry = $this->mine();
        $inactive = $this->userWith('tourist', ['is_active' => false]);
        $inactiveOwn = $this->userWith('tourist', ['is_active' => false]);
        $this->bookingOf($inactiveOwn, $this->a);
        $staff = $this->userWith('manager');

        $messages = [];
        foreach ([$this->foreign->id, 999999, $inactive->id, $inactiveOwn->id, $staff->id, $this->admin->id, $this->b->id] as $id) {
            $response = $this->select($this->a, $inquiry, $id);
            $response->assertRedirect()->assertSessionHasErrors('user_id');
            $messages[] = session('errors')->get('user_id')[0];
        }

        $this->assertSame([InquiryClientAssociation::CLIENT_UNAVAILABLE_MESSAGE], array_values(array_unique($messages)));
        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertCount(0, $this->events('inquiry.client_linked'));
    }

    public function test_manager_search_covers_only_visible_clients_and_keeps_the_term_out_of_the_url(): void
    {
        $inquiry = $this->mine();

        $response = $this->actingAs($this->a)->post($this->route('search', $inquiry), ['client_q' => 'foreign.client']);
        $response->assertRedirect($this->show($inquiry));
        $this->assertStringNotContainsString('foreign', (string) $response->headers->get('Location'));

        $html = $this->actingAs($this->a)->withSession(['_old_input' => ['client_q' => 'foreign.client']])->get($this->show($inquiry))->assertOk()->getContent();
        $this->assertStringNotContainsString('foreign.client@example.test', $html);
        $this->assertStringNotContainsString('Чужой Клиент', $html);
        $this->assertStringContainsString('Среди ваших клиентов совпадений нет', $html);

        $html = $this->actingAs($this->a)->withSession(['_old_input' => ['client_q' => 'repeat.client']])->get($this->show($inquiry))->assertOk()->getContent();
        $this->assertStringContainsString('Повторный Клиент', $html);
        $this->assertStringNotContainsString('Чужой Клиент', $html);

        // Поиск — только POST: GET-маршрута нет, строка поиска не живёт в адресе.
        $this->actingAs($this->a)->get($this->route('search', $inquiry) . '?client_q=repeat')->assertStatus(405);
        // Слишком короткая строка отклоняется без обращения к справочнику.
        $this->actingAs($this->a)->post($this->route('search', $inquiry), ['client_q' => 'a'])->assertSessionHasErrors('client_q');
    }

    public function test_admin_selects_from_the_whole_directory_but_only_active_tourists(): void
    {
        $inquiry = $this->mine();
        $inactive = $this->userWith('tourist', ['is_active' => false]);
        $staff = $this->userWith('manager');

        foreach ([$inactive->id, $staff->id, $this->admin2->id, 999999] as $bad) {
            $this->select($this->admin, $inquiry, $bad)->assertSessionHasErrors('user_id');
        }
        $this->assertNull($inquiry->refresh()->client_user_id);

        $html = $this->actingAs($this->admin)->withSession(['_old_input' => ['client_q' => 'foreign.client']])->get($this->show($inquiry))->assertOk()->getContent();
        $this->assertStringContainsString('Чужой Клиент', $html);

        $this->select($this->admin, $inquiry, $this->foreign->id)->assertSessionHasNoErrors();
        $this->assertSame($this->foreign->id, $inquiry->refresh()->client_user_id);
        $this->assertSame($this->admin->id, $inquiry->client_linked_by);
        // Назначенный менеджер теперь видит клиента обращения, но не его заявку у коллеги.
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->foreign->id)->exists());
    }

    public function test_linking_never_matches_or_merges_by_contact_data(): void
    {
        $twin = $this->userWith('tourist', ['name' => 'Двойник Контакта', 'phone' => '+7 900 555-44-33', 'email' => 'twin@example.test']);
        $inquiry = $this->mine();

        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.contact.update', $inquiry), [
            'revision' => 0, 'contact_name' => 'Двойник Контакта', 'contact_phone' => '+7 900 555-44-33', 'contact_messenger' => 'Telegram @twin',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), [
            'client_name' => 'Двойник Контакта', 'client_email' => 'twin@example.test', 'client_phone' => '+7 900 555-44-33',
        ])->assertSessionHasNoErrors();

        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->a)->whereKey($twin->id)->exists());
        $this->assertCount(0, $this->events('inquiry.client_linked'));
    }

    // ---- no overwrite, replay, stale ---------------------------------------------------------

    public function test_linked_client_is_never_overwritten_and_same_client_replay_is_a_no_op(): void
    {
        $inquiry = $this->mine();
        $second = $this->userWith('tourist');
        $this->bookingOf($second, $this->a);

        $this->select($this->a, $inquiry, $this->own->id)->assertSessionHasNoErrors();

        // Тот же клиент (в том числе из устаревшей формы) — без записи и без второго события.
        $this->select($this->a, $inquiry, $this->own->id)->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertCount(1, $this->events('inquiry.client_linked'));

        // Другой клиент: из актуальной формы — отказ «уже привязан», из устаревшей — отказ по версии.
        $this->select($this->a, $inquiry, $second->id, $this->own->id)->assertSessionHasErrors('client');
        $this->select($this->a, $inquiry, $second->id, null)->assertSessionHas('error');

        $inquiry->refresh();
        $this->assertSame($this->own->id, $inquiry->client_user_id);
        $this->assertSame($this->a->id, $inquiry->client_linked_by);
        $this->assertCount(1, $this->events('inquiry.client_linked'));
    }

    public function test_two_admins_linking_different_clients_have_one_winner(): void
    {
        $inquiry = $this->mine();

        // Оба открыли форму «клиент не привязан».
        $this->select($this->admin, $inquiry, $this->own->id, null)->assertSessionHasNoErrors();
        $this->select($this->admin2, $inquiry, $this->foreign->id, null)->assertSessionHas('error');

        $this->assertSame($this->own->id, $inquiry->refresh()->client_user_id);
        $this->assertSame($this->admin->id, $inquiry->client_linked_by);
        $this->assertCount(1, $this->events('inquiry.client_linked'));
    }

    public function test_forged_fields_are_rejected_wholesale(): void
    {
        $inquiry = $this->mine();
        $other = $this->mine($this->b);

        foreach ([
            ['client_linked_by' => $this->b->id],
            ['client_user_id' => $this->foreign->id],
            ['inquiry_id' => $other->id],
            ['assigned_to' => $this->b->id],
            ['status' => 'closed'],
            ['booking_id' => 1],
        ] as $forged) {
            $this->actingAs($this->a)->post($this->route('select', $inquiry), ['user_id' => $this->own->id, 'expected_client_id' => null] + $forged)->assertSessionHasErrors('client');
            $this->actingAs($this->admin)->post($this->route('create', $inquiry), ['client_name' => 'Х', 'expected_client_id' => null] + $forged)->assertSessionHasErrors('client');
            $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => 1, 'reason' => 'Причина ошибки'] + $forged)->assertSessionHasErrors('client');
        }

        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertNull($other->refresh()->client_user_id);
        $this->assertSame($this->a->id, $inquiry->assigned_to);
    }

    // ---- authorization -----------------------------------------------------------------------

    public function test_only_staff_with_access_to_the_inquiry_may_link(): void
    {
        $inquiry = $this->mine();
        $tourist = $this->userWith('tourist');

        $this->select($this->b, $inquiry, $this->own->id)->assertNotFound();
        $this->select($tourist, $inquiry, $this->own->id)->assertForbidden();
        $this->actingAs($this->b)->post($this->route('search', $inquiry), ['client_q' => 'repeat'])->assertNotFound();
        auth()->logout();
        $this->post($this->route('select', $inquiry), ['user_id' => $this->own->id, 'expected_client_id' => null])->assertRedirect(route('login'));

        $unassigned = $this->mine();
        DB::table('inquiries')->where('id', $unassigned->id)->update(['assigned_to' => null]);
        $this->select($this->a, $unassigned, $this->own->id)->assertNotFound();

        [, $linked] = $this->linkedPair($this->a, $this->a);
        $this->select($this->a, $linked, $this->own->id)->assertNotFound();
        $this->select($this->admin, $linked, $this->own->id)->assertNotFound();

        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertNull($linked->refresh()->client_user_id);
    }

    public function test_former_manager_loses_every_association_action_after_reassignment(): void
    {
        $inquiry = $this->mine();
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ])->assertSessionHasNoErrors();

        $this->select($this->a, $inquiry, $this->own->id)->assertNotFound();
        $this->actingAs($this->a)->post($this->route('verification', $inquiry), ['client_name' => 'X'])->assertNotFound();
        $this->assertNull($inquiry->refresh()->client_user_id);
    }

    public function test_manager_cannot_create_unlink_or_use_admin_operations(): void
    {
        $inquiry = $this->mine();
        $outsider = $this->userWith('tourist');
        $this->select($this->a, $inquiry, $this->own->id)->assertSessionHasNoErrors();
        $users = User::query()->count();

        $this->actingAs($this->a)->post($this->route('create', $inquiry), ['client_name' => 'Новый', 'client_email' => 'new@example.test', 'expected_client_id' => $this->own->id])->assertForbidden();
        $this->actingAs($this->a)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Хочу снять сам'])->assertForbidden();
        $this->actingAs($this->b)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Хочу снять сам'])->assertNotFound();
        $this->actingAs($outsider)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Хочу снять сам'])->assertForbidden();

        $this->assertSame($users, User::query()->count());
        $this->assertSame($this->own->id, $inquiry->refresh()->client_user_id);
    }

    public function test_closed_inquiries_cannot_change_their_client(): void
    {
        $inquiry = $this->mine();
        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()]);

        $this->select($this->a, $inquiry, $this->own->id)->assertSessionHasErrors('client');
        $this->actingAs($this->admin)->post($this->route('create', $inquiry), ['client_name' => 'Новый', 'expected_client_id' => null])->assertSessionHasErrors('client');
        $this->assertNull($inquiry->refresh()->client_user_id);
    }

    // ---- Admin create / unlink ---------------------------------------------------------------

    public function test_admin_creates_a_safe_tourist_and_links_it(): void
    {
        $inquiry = $this->mine();
        $users = User::query()->count();

        $this->actingAs($this->admin)->post($this->route('create', $inquiry), [
            'client_name' => 'Новый  Клиент', 'client_email' => 'New.Client@Example.test', 'client_phone' => '+7 900 000-11-22', 'expected_client_id' => null,
        ])->assertSessionHas('success')->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'new.client@example.test')->firstOrFail();
        $this->assertSame($users + 1, User::query()->count());
        $this->assertSame('Новый Клиент', $created->name);
        $this->assertTrue($created->hasRole(User::ROLE_TOURIST));
        $this->assertFalse($created->hasRole(User::ROLE_MANAGER));
        $this->assertNull($created->email_verified_at);
        $this->assertNull($created->temp_password);
        $this->assertTrue((bool) $created->is_active);
        $this->assertNotEmpty($created->password);

        $this->assertSame($created->id, $inquiry->refresh()->client_user_id);
        $event = $this->events('inquiry.client_linked')->sole();
        $this->assertSame(['client_id' => $created->id, 'via' => 'created', 'verification_id' => null], $event->metadata);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_admin_create_with_a_registered_email_changes_nothing(): void
    {
        $inquiry = $this->mine();
        $users = User::query()->count();

        $this->actingAs($this->admin)->post($this->route('create', $inquiry), [
            'client_name' => 'Дубль', 'client_email' => 'REPEAT.client@example.test', 'expected_client_id' => null,
        ])->assertSessionHasErrors('client_email');

        $this->assertSame($users, User::query()->count());
        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertSame('Повторный Клиент', $this->own->refresh()->name, 'существующий аккаунт не изменяется');
    }

    public function test_admin_create_without_email_uses_a_technical_address_and_validates_input(): void
    {
        $inquiry = $this->mine();

        $this->actingAs($this->admin)->post($this->route('create', $inquiry), ['client_name' => '', 'expected_client_id' => null])->assertSessionHasErrors('client_name');
        $this->actingAs($this->admin)->post($this->route('create', $inquiry), ['client_name' => 'Х', 'client_email' => 'не-почта', 'expected_client_id' => null])->assertSessionHasErrors('client_email');
        $this->actingAs($this->admin)->post($this->route('create', $inquiry), ['client_name' => 'Х', 'client_phone' => str_repeat('9', 21), 'expected_client_id' => null])->assertSessionHasErrors('client_phone');
        $this->assertNull($inquiry->refresh()->client_user_id);

        $this->actingAs($this->admin)->post($this->route('create', $inquiry), ['client_name' => 'Без почты', 'expected_client_id' => null])->assertSessionHasNoErrors();
        $client = User::query()->findOrFail($inquiry->refresh()->client_user_id);
        $this->assertTrue($client->hasTechnicalEmail());
    }

    public function test_admin_create_refuses_when_a_client_is_already_linked(): void
    {
        $inquiry = $this->mine();
        $this->select($this->admin, $inquiry, $this->own->id)->assertSessionHasNoErrors();
        $users = User::query()->count();

        $this->actingAs($this->admin2)->post($this->route('create', $inquiry), ['client_name' => 'Лишний', 'expected_client_id' => null])->assertSessionHas('error');
        $this->actingAs($this->admin2)->post($this->route('create', $inquiry), ['client_name' => 'Лишний', 'expected_client_id' => $this->own->id])->assertSessionHasErrors('client');

        $this->assertSame($users, User::query()->count());
        $this->assertSame($this->own->id, $inquiry->refresh()->client_user_id);
    }

    public function test_admin_unlinks_with_a_validated_reason_and_the_next_link_is_explicit(): void
    {
        $inquiry = $this->mine();
        $this->select($this->a, $inquiry, $this->own->id)->assertSessionHasNoErrors();

        foreach (['', 'abc', str_repeat('я', 201), 'Звонить на client@example.test', 'Тел +7 900 123-45-67'] as $bad) {
            $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => $bad])->assertSessionHasErrors('reason');
        }
        $this->assertSame($this->own->id, $inquiry->refresh()->client_user_id);
        $this->assertCount(0, $this->events('inquiry.client_unlinked'));

        $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Привязан не тот клиент'])
            ->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertNull($inquiry->client_user_id);
        $this->assertNull($inquiry->client_linked_by);
        $this->assertNull($inquiry->client_linked_at);
        $this->assertSame($this->a->id, $inquiry->assigned_to, 'отвязка не меняет ответственного');

        $event = $this->events('inquiry.client_unlinked')->sole();
        $this->assertSame($this->admin->id, (int) $event->actor_id);
        $this->assertSame(['previous_client_id' => $this->own->id, 'reason' => 'Привязан не тот клиент'], $event->metadata);

        // Аккаунт клиента не тронут, а повторная привязка — новое явное действие со своим событием.
        $this->assertNotNull($this->own->fresh());
        $this->select($this->a, $inquiry, $this->own->id)->assertSessionHasNoErrors();
        $this->assertCount(2, $this->events('inquiry.client_linked'));
    }

    public function test_unlink_is_stale_safe_in_both_directions(): void
    {
        $inquiry = $this->mine();
        $this->select($this->admin, $inquiry, $this->own->id)->assertSessionHasNoErrors();

        // Устаревшая форма: клиент уже сменился.
        $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->foreign->id, 'reason' => 'Устаревшая форма'])->assertSessionHas('error');
        $this->assertSame($this->own->id, $inquiry->refresh()->client_user_id);

        $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Первый администратор'])->assertSessionHas('success');
        // Второй администратор нажимает «снять» по той же форме: привязки уже нет — отказ, события нет.
        $this->actingAs($this->admin2)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Второй администратор'])->assertSessionHas('error');
        $this->assertCount(1, $this->events('inquiry.client_unlinked'));
    }

    public function test_link_versus_unlink_race_is_resolved_by_versions(): void
    {
        $inquiry = $this->mine();
        $this->select($this->admin, $inquiry, $this->own->id)->assertSessionHasNoErrors();

        // Admin2 открыл форму «привязан own»; Admin снял привязку и привязал другого.
        $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Ошибочная привязка']);
        $this->select($this->admin, $inquiry, $this->foreign->id, null)->assertSessionHasNoErrors();
        $this->actingAs($this->admin2)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Старая форма снятия'])->assertSessionHas('error');

        $this->assertSame($this->foreign->id, $inquiry->refresh()->client_user_id);
    }

    public function test_pending_verification_becomes_stale_when_the_inquiry_is_linked_directly(): void
    {
        $inquiry = $this->mine();
        $this->actingAs($this->a)->post($this->route('verification', $inquiry), ['client_name' => 'Клиент', 'client_email' => 'someone@example.test'])->assertSessionHasNoErrors();
        $request = ClientVerificationRequest::query()->sole();

        $this->select($this->admin, $inquiry, $this->foreign->id)->assertSessionHasNoErrors();

        $this->actingAs($this->admin2)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $this->own->id])->assertSessionHasErrors();
        $this->assertSame($this->foreign->id, $inquiry->refresh()->client_user_id);
        $this->assertTrue($request->refresh()->isPending());
    }

    // ---- visibility semantics ----------------------------------------------------------------

    public function test_inquiry_link_grants_client_visibility_only_while_assigned_and_no_booking_access(): void
    {
        $inquiry = $this->mine();
        $this->select($this->admin, $inquiry, $this->foreign->id)->assertSessionHasNoErrors();
        $foreignBooking = Booking::query()->where('user_id', $this->foreign->id)->firstOrFail();

        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->foreign->id)->exists());
        // Видимость клиента не открывает чужую заявку клиента, её переписку и документы.
        $this->actingAs($this->a)->get(route('bookings.show', $foreignBooking))->assertForbidden();
        $this->actingAs($this->a)->get(route('cabinet.manager.chat', ['bookingId' => $foreignBooking->id]))->assertNotFound();

        // Передача: прежний менеджер теряет клиента сразу, новый получает.
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->foreign->id)->exists());

        // Отвязка снимает видимость и у нового ответственного, у которого клиента нет другим путём.
        $this->userWith('tourist');
        $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->foreign->id, 'reason' => 'Привязан по ошибке']);
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->b)->whereKey($this->foreign->id)->exists(), 'у B клиент остаётся по собственной заявке');
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->foreign->id)->exists());
    }

    public function test_user_data_is_never_serialised_with_the_inquiry(): void
    {
        $inquiry = $this->mine();
        $this->select($this->admin, $inquiry, $this->own->id);

        $array = $inquiry->refresh()->load(['clientUser', 'clientLinkedBy', 'assignee', 'creator', 'contact'])->toArray();
        $json = json_encode($array, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('repeat.client@example.test', $json);
        $this->assertStringNotContainsString('Повторный Клиент', $json);
        $this->assertArrayNotHasKey('client_user', $array);
    }

    // ---- atomicity ---------------------------------------------------------------------------

    public function test_audit_failure_rolls_back_link_create_and_unlink(): void
    {
        $linked = $this->mine();
        $this->select($this->admin, $linked, $this->own->id)->assertSessionHasNoErrors();
        $open = $this->mine();
        $users = User::query()->count();

        $this->failAudit();
        $service = app(InquiryClientAssociation::class);

        foreach ([
            fn () => $service->linkVisible($open, $this->a, $this->own->id, null),
            fn () => $service->createAndLink($open, $this->admin, 'Откат', 'rollback@example.test', null, null),
            fn () => $service->unlink($linked, $this->admin, $this->own->id, 'Проверка отката'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Сбой аудита должен прерывать операцию');
            } catch (RuntimeException $e) {
                $this->assertSame('audit store unavailable', $e->getMessage());
            }
        }

        $this->assertNull($open->refresh()->client_user_id);
        $this->assertSame($this->own->id, $linked->refresh()->client_user_id);
        $this->assertSame($linked->client_linked_by, $this->admin->id);
        $this->assertSame($users, User::query()->count(), 'созданный аккаунт откатывается вместе с привязкой');
    }

    public function test_service_rechecks_fresh_actor_and_provenance_under_lock(): void
    {
        $inquiry = $this->mine();
        $service = app(InquiryClientAssociation::class);

        $this->a->update(['is_active' => false]);
        try {
            $service->linkVisible($inquiry, $this->a, $this->own->id, null);
            $this->fail('Деактивированный менеджер не может привязывать клиента');
        } catch (AuthorizationException) {
        }
        $this->a->update(['is_active' => true]);

        [, $external] = $this->linkedPair($this->a, $this->a);
        $this->expectException(AuthorizationException::class);
        $service->linkVisible($external, $this->a, $this->own->id, null);
    }

    public function test_no_side_effects_beyond_the_inquiry_link(): void
    {
        $inquiry = $this->mine();
        $before = [
            'users' => User::query()->count(),
            'bookings' => Booking::query()->count(),
            'roles' => Role::query()->count(),
            'notifications' => DB::table('notifications')->count(),
            'incoming' => DB::table('incoming_inquiries')->count(),
        ];

        $this->select($this->a, $inquiry, $this->own->id);
        $this->actingAs($this->admin)->post($this->route('unlink', $inquiry), ['expected_client_id' => $this->own->id, 'reason' => 'Проверка побочных эффектов']);

        $after = [
            'users' => User::query()->count(),
            'bookings' => Booking::query()->count(),
            'roles' => Role::query()->count(),
            'notifications' => DB::table('notifications')->count(),
            'incoming' => DB::table('incoming_inquiries')->count(),
        ];
        $this->assertSame($before, $after);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }
}
