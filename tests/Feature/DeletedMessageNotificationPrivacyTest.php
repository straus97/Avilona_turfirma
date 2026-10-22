<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewMessageDatabaseNotification;
use App\Support\Notifications\MessageNotificationVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I6 (I5-F1) — уведомление о сообщении не хранит и не показывает текст сообщения,
 * которое уже удалено. Удаление сообщений жёсткое (без soft-delete), поэтому «удалено» = строки
 * в messages нет; доступность уведомления проверяется по ТЕКУЩЕМУ состоянию сообщения.
 * Только синтетические данные.
 */
class DeletedMessageNotificationPrivacyTest extends TestCase
{
    use BuildsAoaI2bFixtures;
    use RefreshDatabase;

    private const SECRET = 'УДАЛЁННЫЙ-ТЕКСТ-СЕКРЕТ-7731';

    private User $admin;
    private User $manager;
    private User $client;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->admin = $this->userWith('admin');
        $this->manager = $this->userWith('manager');
        $this->client = $this->userWith('tourist', ['name' => 'Клиент Секретов']);
        $this->booking = $this->bookingOf($this->client, $this->manager);
    }

    private function bell(User $user, string $route): string
    {
        return $this->actingAs($user)->get(route($route))->assertOk()->getContent();
    }

    private function messageOf(DatabaseNotification $notice): ?Message
    {
        return Message::query()->find($notice->data['message_id']);
    }

    private function assertBellClean(User $user, string $route, string $needle = self::SECRET): void
    {
        $html = $this->bell($user, $route);
        $this->assertStringNotContainsString($needle, $html);
        $this->assertStringNotContainsString('notification-badge', $html);
        $this->assertStringContainsString('Нет новых уведомлений', $html);
    }

    private function rawNotice(User $to, mixed $data, string $type = NewMessageDatabaseNotification::class): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $to->id,
            'data' => $data,
            'read_at' => null,
        ]);
    }

    // ------------------------------------------------------------------ live

    public function test_live_notification_is_visible_counted_and_openable(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);

        $html = $this->bell($this->manager, 'cabinet.manager.profile');
        $this->assertStringContainsString(self::SECRET, $html);
        $this->assertStringContainsString('notification-badge">1</span>', $html);
        $this->assertSame(1, MessageNotificationVisibility::unreadFor($this->manager)->count());

        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $notice->id))
            ->assertRedirect(route('cabinet.manager.chat', ['bookingId' => $this->booking->id]));
        $this->assertNotNull($notice->fresh()->read_at);
    }

    // --------------------------------------------------------- delete → hidden

    public function test_delete_before_read_hides_preview_badge_and_open_for_manager(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertNull($this->messageOf($notice));
        $this->assertBellClean($this->manager, 'cabinet.manager.profile');
        $this->assertSame(0, MessageNotificationVisibility::unreadFor($this->manager)->count());

        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at, 'a refused open must not mark the notification as read');
        $this->assertNotNull($notice->fresh(), 'the notification row itself is kept');
    }

    public function test_delete_after_the_notification_was_read_blocks_reopening(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $notice->id))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $notice->id))->assertNotFound();
        $this->assertStringNotContainsString(self::SECRET, $this->bell($this->manager, 'cabinet.manager.profile'));
    }

    public function test_tourist_recipient_loses_preview_when_manager_deletes_own_message(): void
    {
        $notice = $this->messageNotice($this->booking, $this->manager, $this->client, self::SECRET);
        $this->assertStringContainsString(self::SECRET, $this->bell($this->client, 'cabinet.dashboard'));

        $this->actingAs($this->manager)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        $this->assertBellClean($this->client, 'cabinet.dashboard');
        $this->actingAs($this->client)->post(route('cabinet.notifications.open', $notice->id))->assertNotFound();
    }

    public function test_admin_recipient_does_not_keep_deleted_content(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->admin, self::SECRET);
        $this->assertStringContainsString(self::SECRET, $this->bell($this->admin, 'cabinet.admin.dashboard'));

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        $this->assertBellClean($this->admin, 'cabinet.admin.dashboard');
        $this->actingAs($this->admin)->post(route('cabinet.notifications.open', $notice->id))->assertNotFound();
    }

    public function test_admin_may_delete_any_message_and_recipient_loses_the_preview(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);

        $this->actingAs($this->admin)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        $this->assertBellClean($this->manager, 'cabinet.manager.profile');
    }

    // ------------------------------------------------ deletion authorization

    public function test_non_sender_cannot_delete_and_preview_stays_for_the_recipient(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $id = $notice->data['message_id'];

        // Получатель-менеджер не отправитель и не админ → 403 (политика удаления не изменена).
        $this->actingAs($this->manager)->deleteJson(route('messages.destroy', $id))->assertForbidden();

        $other = $this->userWith('tourist');
        $this->actingAs($other)->deleteJson(route('messages.destroy', $id))->assertForbidden();

        $this->assertNotNull(Message::query()->find($id));
        $stored = DatabaseNotification::query()->findOrFail($notice->id)->data;
        $this->assertSame(self::SECRET, $stored['preview'], 'a refused deletion must not redact anything');
        $this->assertStringContainsString(self::SECRET, $this->bell($this->manager, 'cabinet.manager.profile'));
    }

    // ------------------------------------------------------ stored snapshot

    public function test_deleting_a_message_redacts_the_stored_preview_but_keeps_the_row(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        $this->assertSame(1, DatabaseNotification::query()->count());
        $raw = (string) DB::table('notifications')->where('id', $notice->id)->value('data');
        $this->assertStringNotContainsString(self::SECRET, $raw, 'deleted text must not stay in the notifications table');

        $data = DatabaseNotification::query()->findOrFail($notice->id)->data;
        $this->assertSame('new_message', $data['type']);
        $this->assertSame($notice->data['message_id'], $data['message_id']);
        $this->assertSame($this->booking->id, $data['booking_id']);
        $this->assertSame('Сообщение удалено', $data['preview']);
        $this->assertFalse($data['has_attachment']);
    }

    public function test_attachment_only_message_deletion_clears_attachment_flag(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('messages/x.pdf', 'synthetic');
        $message = Message::query()->create([
            'booking_id' => $this->booking->id, 'sender_id' => $this->client->id, 'receiver_id' => $this->manager->id,
            'message' => '', 'attachment_url' => 'messages/x.pdf', 'is_read' => false,
        ]);
        $this->manager->notify(new NewMessageDatabaseNotification($message->load('sender')));
        $notice = DatabaseNotification::query()->firstOrFail();
        $this->assertTrue($notice->data['has_attachment']);

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $message->id))->assertOk();

        Storage::disk('local')->assertMissing('messages/x.pdf');
        $this->assertFalse($notice->fresh()->data['has_attachment']);
        $this->assertSame(0, MessageNotificationVisibility::unreadFor($this->manager)->count());
    }

    public function test_failed_redaction_rolls_the_deletion_back(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $id = $notice->data['message_id'];

        DatabaseNotification::saving(function (): void {
            throw new \RuntimeException('synthetic redaction failure');
        });

        try {
            $this->withoutExceptionHandling();
            try {
                $this->actingAs($this->client)->deleteJson(route('messages.destroy', $id));
                $this->fail('the synthetic failure must surface');
            } catch (\RuntimeException $e) {
                $this->assertSame('synthetic redaction failure', $e->getMessage());
            }
        } finally {
            DatabaseNotification::flushEventListeners();
        }

        // Всё или ничего: сообщение не удалено, а значит и уведомление остаётся согласованным.
        $this->assertNotNull(Message::query()->find($id));
        $this->assertSame(self::SECRET, DatabaseNotification::query()->findOrFail($notice->id)->data['preview']);
    }

    // ------------------------------------------------ legacy / foreign records

    public function test_legacy_snapshots_of_missing_messages_are_hidden_for_every_role(): void
    {
        $bookingId = $this->booking->id;
        foreach ([$this->admin, $this->manager, $this->client] as $recipient) {
            $this->rawNotice($recipient, [
                'type' => 'new_message', 'message_id' => 987654, 'booking_id' => $bookingId,
                'sender_id' => $this->client->id, 'sender_name' => 'X', 'preview' => self::SECRET, 'has_attachment' => false,
            ]);
            // Формат без message_id и с нечитаемым message_id: нечего сверять → закрыто.
            foreach ([null, 'abc', 0, -3, ['x'], 1.5] as $bad) {
                $this->rawNotice($recipient, [
                    'type' => 'new_message', 'message_id' => $bad, 'booking_id' => $bookingId,
                    'sender_name' => 'X', 'preview' => self::SECRET,
                ]);
            }
            $this->rawNotice($recipient, [
                'type' => 'new_message', 'booking_id' => $bookingId, 'sender_name' => 'X', 'preview' => self::SECRET,
            ]);
        }

        $this->assertBellClean($this->admin, 'cabinet.admin.dashboard');
        $this->assertBellClean($this->manager, 'cabinet.manager.profile');
        $this->assertBellClean($this->client, 'cabinet.dashboard');

        foreach ([$this->admin, $this->manager, $this->client] as $recipient) {
            $this->assertSame(0, MessageNotificationVisibility::unreadFor($recipient)->count());
            foreach ($recipient->notifications()->get() as $legacy) {
                $this->actingAs($recipient)->post(route('cabinet.notifications.open', $legacy->id))->assertNotFound();
                $this->assertNull($legacy->fresh()->read_at);
            }
        }
    }

    public function test_legacy_snapshot_of_a_live_message_stays_visible(): void
    {
        $message = Message::query()->create([
            'booking_id' => $this->booking->id, 'sender_id' => $this->client->id,
            'receiver_id' => $this->manager->id, 'message' => 'живое', 'is_read' => false,
        ]);
        // message_id в JSON может прийти строкой (исторические записи/другая СУБД).
        $this->rawNotice($this->manager, [
            'type' => 'new_message', 'message_id' => (string) $message->id, 'booking_id' => (string) $this->booking->id,
            'sender_name' => 'Клиент', 'preview' => 'СТАРОЕ-ЖИВОЕ-ПРЕВЬЮ',
        ]);

        $this->assertStringContainsString('СТАРОЕ-ЖИВОЕ-ПРЕВЬЮ', $this->bell($this->manager, 'cabinet.manager.profile'));
    }

    public function test_other_notification_types_are_neither_shown_nor_redacted(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $foreign = $this->rawNotice($this->manager, [
            'type' => 'something_else', 'message_id' => $notice->data['message_id'], 'preview' => 'ЧУЖОЙ-ТИП-ПРЕВЬЮ',
        ], 'App\\Notifications\\SomeOtherNotification');

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        $this->assertSame('ЧУЖОЙ-ТИП-ПРЕВЬЮ', $foreign->fresh()->data['preview'], 'only message notifications are redacted');
        $this->assertStringNotContainsString('ЧУЖОЙ-ТИП-ПРЕВЬЮ', $this->bell($this->manager, 'cabinet.manager.profile'));
    }

    // ----------------------------------------------- unrelated + counts + JSON

    public function test_unrelated_notifications_remain_and_counts_stay_consistent(): void
    {
        $first = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $second = $this->messageNotice($this->booking, $this->client, $this->manager, 'ЖИВОЕ-ВТОРОЕ-СООБЩЕНИЕ');

        $this->actingAs($this->manager)->getJson(route('messages.unread-count'))->assertExactJson(['count' => 2]);
        $this->assertStringContainsString('notification-badge">2</span>', $this->bell($this->manager, 'cabinet.manager.profile'));

        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $first->data['message_id']))->assertOk();

        $this->actingAs($this->manager)->getJson(route('messages.unread-count'))->assertExactJson(['count' => 1]);
        $html = $this->bell($this->manager, 'cabinet.manager.profile');
        $this->assertStringContainsString('notification-badge">1</span>', $html);
        $this->assertStringContainsString('ЖИВОЕ-ВТОРОЕ-СООБЩЕНИЕ', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertSame(1, MessageNotificationVisibility::unreadFor($this->manager)->count());

        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $second->id))
            ->assertRedirect(route('cabinet.manager.chat', ['bookingId' => $this->booking->id]));
        $this->assertSame(0, MessageNotificationVisibility::unreadFor($this->manager)->count());
    }

    public function test_json_endpoints_never_return_deleted_content(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();

        foreach ([$this->manager, $this->client, $this->admin] as $viewer) {
            $json = $this->actingAs($viewer)->getJson(route('messages.index', ['booking_id' => $this->booking->id]))
                ->assertOk()->getContent();
            $this->assertStringNotContainsString(self::SECRET, $json);
            $this->assertStringNotContainsString(self::SECRET, $this->actingAs($viewer)->getJson(route('messages.unread-count'))->getContent());
        }
    }

    // ---------------------------------------------------------- reassignment

    public function test_reassignment_and_deletion_combine_without_widening_access(): void
    {
        $b = $this->userWith('manager');
        $notice = $this->messageNotice($this->booking, $this->client, $this->manager, self::SECRET);
        $live = $this->messageNotice($this->booking, $this->client, $this->manager, 'ЖИВОЕ-ДО-ПЕРЕНАЗНАЧЕНИЯ');

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->booking), [
            'manager_id' => $b->id, 'expected_manager_id' => $this->manager->id, 'reason_code' => 'workload',
        ])->assertSessionHasNoErrors();
        auth()->logout();

        // Прежний ответственный не видит ни удалённое, ни живое (G8).
        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $notice->data['message_id']))->assertOk();
        $this->assertBellClean($this->manager, 'cabinet.manager.profile', 'ЖИВОЕ-ДО-ПЕРЕНАЗНАЧЕНИЯ');

        // Возврат заявки возвращает только живое уведомление, удалённое не воскресает.
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->booking), [
            'manager_id' => $this->manager->id, 'expected_manager_id' => $b->id, 'reason_code' => 'staff_change',
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $html = $this->bell($this->manager, 'cabinet.manager.profile');
        $this->assertStringContainsString('ЖИВОЕ-ДО-ПЕРЕНАЗНАЧЕНИЯ', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertSame(1, MessageNotificationVisibility::unreadFor($this->manager)->count());
        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $notice->id))->assertNotFound();
        $this->actingAs($this->manager)->post(route('cabinet.notifications.open', $live->id))->assertRedirect();
    }
}
