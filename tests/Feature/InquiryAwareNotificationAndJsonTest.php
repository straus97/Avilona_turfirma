<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\InquiryAuthority;
use App\Support\Notifications\MessageNotificationVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I3 — чтение уведомлений и JSON в присутствии Inquiry. Новых уведомлений об Inquiry
 * НЕ отправляется: проверяется, что существующая серверная фильтрация закрыта для любых
 * неизвестных типов и что подготовленное правило (InquiryAuthority::responsibleInquiryIds)
 * работает по ТЕКУЩЕЙ ответственности. Только синтетические данные.
 */
class InquiryAwareNotificationAndJsonTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const FUTURE_TYPE = 'App\\Notifications\\InquiryAssignedDatabaseNotification';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Event::fake([\Illuminate\Mail\Events\MessageSent::class]);
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);
    }

    /** Синтетическое уведомление «будущего» типа с превью-маркером (так запись выглядела бы в базе). */
    private function futureNotice(User $to, int $inquiryId, string $preview = 'SECRET-PREVIEW-MARKER'): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => self::FUTURE_TYPE,
            'notifiable_type' => User::class,
            'notifiable_id' => $to->id,
            'data' => ['type' => 'inquiry_assigned', 'inquiry_id' => $inquiryId, 'preview' => $preview, 'sender_name' => 'Синтетический Автор'],
            'read_at' => null,
        ]);
    }

    public function test_existing_filter_hides_unknown_inquiry_notification_types_from_every_role(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $independent = $this->independentInquiry($a);

        foreach ([$admin, $a] as $user) {
            $this->futureNotice($user, $independent->id);
            $this->assertSame(0, MessageNotificationVisibility::unreadFor($user)->count());
            $this->assertNull(MessageNotificationVisibility::unreadFor($user)->first());
        }
    }

    public function test_unknown_notification_type_is_not_rendered_in_the_header_and_cannot_be_opened(): void
    {
        $a = $this->userWith('manager');
        $independent = $this->independentInquiry($a);
        $notice = $this->futureNotice($a, $independent->id);

        $this->actingAs($a)->get(route('cabinet.manager.dashboard'))
            ->assertOk()
            ->assertDontSee('SECRET-PREVIEW-MARKER')
            ->assertDontSee($notice->id);

        // прямой адрес уведомления: тот же серверный фильтр, 404 без раскрытия
        $this->actingAs($a)->post(route('cabinet.notifications.open', ['notification' => $notice->id]))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at, 'неоткрываемое уведомление не помечается прочитанным');
    }

    public function test_someone_elses_notification_id_is_a_plain_404_even_for_admin(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $notice = $this->futureNotice($a, $this->independentInquiry($a)->id);

        $this->actingAs($admin)->post(route('cabinet.notifications.open', ['notification' => $notice->id]))->assertNotFound();
        $this->actingAs($this->userWith('manager'))->post(route('cabinet.notifications.open', ['notification' => $notice->id]))->assertNotFound();
    }

    /** Фильтр, каким его добавит будущая отправка уведомлений об Inquiry (правило подготовлено, не подключено). */
    private function visibleFutureNotices(User $manager): array
    {
        return $manager->notifications()
            ->where('type', self::FUTURE_TYPE)
            ->whereIn('data->inquiry_id', InquiryAuthority::responsibleInquiryIds($manager))
            ->pluck('id')
            ->all();
    }

    public function test_prepared_rule_follows_current_responsibility_for_future_notifications(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $inquiry = $this->independentInquiry($a);
        $notice = $this->futureNotice($a, $inquiry->id);

        $this->assertSame([$notice->id], $this->visibleFutureNotices($a));

        // переназначено: прежнему менеджеру снимок больше не виден
        DB::table('inquiries')->where('id', $inquiry->id)->update(['assigned_to' => $b->id]);
        $this->assertSame([], $this->visibleFutureNotices($a), 'устаревшее уведомление прежнего ответственного скрыто');

        // вернулось — уведомление снова видно (записи не удалялись)
        DB::table('inquiries')->where('id', $inquiry->id)->update(['assigned_to' => $a->id]);
        $this->assertSame([$notice->id], $this->visibleFutureNotices($a));
    }

    public function test_prepared_rule_hides_admin_unassigned_foreign_orphan_and_conflicting_objects(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');

        $adminOwned = $this->independentInquiry($admin);
        [, $unassigned] = $this->linkedPair(null, null);
        $foreign = $this->independentInquiry($b);
        [, $conflict] = $this->linkedPair($b, $a);   // legacy ведёт B, снимок называет A
        [$source, $stale] = $this->linkedPair($a, $a);
        $this->legacyReassign($source, $b);          // снимок устарел
        $ghostId = 987654;                            // Inquiry не существует (сирота)

        foreach ([$adminOwned->id, $unassigned->id, $foreign->id, $conflict->id, $stale->id, $ghostId] as $id) {
            $this->futureNotice($a, $id);
        }

        $this->assertSame([], $this->visibleFutureNotices($a));

        // и наоборот — свой, согласованный объект виден
        [, $mine] = $this->linkedPair($a, $a);
        $own = $this->futureNotice($a, $mine->id);
        $this->assertSame([$own->id], $this->visibleFutureNotices($a));
    }

    public function test_client_visible_through_an_inquiry_does_not_reveal_foreign_booking_message_notifications(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $foreignBooking = $this->bookingOf($client, $b);
        $this->independentInquiry($a, null, $client);   // A видит клиента через свой Inquiry
        $notice = $this->messageNotice($foreignBooking, $client, $b, 'Приватное сообщение клиента менеджеру B');

        $this->assertSame(0, MessageNotificationVisibility::unreadFor($a)->count());
        $this->actingAs($a)->post(route('cabinet.notifications.open', ['notification' => $notice->id]))->assertNotFound();
        $this->assertSame(1, MessageNotificationVisibility::unreadFor($b)->count());
    }

    // ------------------------------------------------------------ JSON / lookup

    public function test_chat_json_for_a_foreign_booking_stays_closed_to_a_manager_who_sees_the_client_via_inquiry(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist', ['email' => 'client.sentinel@example.test', 'phone' => '+7 900 000-00-99']);
        $booking = $this->bookingOf($client, $b);
        Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $client->id, 'receiver_id' => $b->id, 'message' => 'Секрет клиента', 'is_read' => false]);
        $this->independentInquiry($a, null, $client);

        $this->actingAs($a)->getJson(route('messages.index', ['booking_id' => $booking->id]))->assertForbidden();
        $this->actingAs($a)->getJson(route('messages.unread-count'))->assertOk()->assertExactJson(['count' => 0]);
    }

    public function test_chat_json_keeps_the_minimal_participant_contract_for_the_responsible_manager(): void
    {
        $a = $this->userWith('manager');
        $client = $this->userWith('tourist', ['email' => 'client.sentinel2@example.test', 'phone' => '+7 900 000-00-98']);
        $booking = $this->bookingOf($client, $a);
        $this->independentInquiry($a, null, $client);
        Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $client->id, 'receiver_id' => $a->id, 'message' => 'Привет', 'is_read' => false]);

        $response = $this->actingAs($a)->getJson(route('messages.index', ['booking_id' => $booking->id]))->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('client.sentinel2@example.test', $body);
        $this->assertStringNotContainsString('+7 900 000-00-98', $body);
        foreach ($response->json() as $message) {
            $this->assertSame(['id', 'name'], array_keys($message['sender']));
        }
    }

    public function test_pages_that_list_clients_never_serialize_inquiry_or_user_internals(): void
    {
        $a = $this->userWith('manager');
        $client = $this->userWith('tourist', ['name' => 'Клиент Для Страницы', 'email' => 'page.client@example.test']);
        $inquiry = $this->independentInquiry($a, null, $client);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['internal_notes' => 'ВНУТРЕННЯЯ-ЗАМЕТКА-МАРКЕР']);

        $page = $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertOk()->assertSee('Клиент Для Страницы');
        $page->assertDontSee('ВНУТРЕННЯЯ-ЗАМЕТКА-МАРКЕР');

        // JSON-представление Inquiry никогда не содержит заметок и связанных пользователей
        $json = json_encode($inquiry->fresh()->load('clientUser', 'assignee', 'creator')->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('ВНУТРЕННЯЯ-ЗАМЕТКА-МАРКЕР', $json);
        $this->assertStringNotContainsString('page.client@example.test', $json);
    }

    public function test_inquiry_client_candidates_do_not_reveal_a_foreign_independent_inquiry_client(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $foreign = $this->userWith('tourist', ['name' => 'Уникум Чужой-Inquiry', 'email' => 'unikum.inq@example.test']);
        $this->independentInquiry($b, null, $foreign);
        $legacy = $this->inquiryFor($a, null, ['client_email' => 'unikum.inq@example.test']);

        $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', [$legacy, 'client_q' => 'Уникум']))
            ->assertOk()->assertDontSee('Выбрать клиента Уникум Чужой-Inquiry');
    }
}
