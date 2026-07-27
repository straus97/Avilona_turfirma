<?php

namespace Tests\Feature;

use App\Events\NewMessageReceived;
use App\Listeners\SendNewMessageNotification;
use App\Mail\NewMessageReceived as NewMessageReceivedMail;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Проверяет контракт персистентного (database-канал) уведомления о новом
 * сообщении: SendNewMessageNotification сохраняет ровно одну запись в таблице
 * notifications для получателя независимо от email-настроек, изолирует сбой
 * database-канала от письма и наоборот, и формирует минимальный стабильный
 * payload без чувствительных данных (email, путь вложения, HTML, полный текст).
 *
 * Здесь НЕ используется Notification::fake() — тесты должны видеть реальные
 * строки в таблице notifications, а не перехваченные фейком объекты.
 */
class NewMessageDatabaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Обычный получатель: одна запись, корректные поля, письмо в очереди
    // -----------------------------------------------------------------------

    public function test_listener_creates_single_database_notification_with_expected_payload(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessage($booking, $owner, $manager, 'Hello manager');

        $this->dispatchListener($message);

        $this->assertDatabaseCount('notifications', 1);

        $notification = DatabaseNotification::query()->firstOrFail();

        $this->assertSame(User::class, $notification->notifiable_type);
        $this->assertSame((int) $manager->id, (int) $notification->notifiable_id);
        $this->assertSame(NewMessageDatabaseNotification::class, $notification->type);
        $this->assertNull($notification->read_at);

        $this->assertEqualsCanonicalizing(
            ['type', 'message_id', 'booking_id', 'sender_id', 'sender_name', 'preview', 'has_attachment'],
            array_keys($notification->data)
        );

        $this->assertSame('new_message', $notification->data['type']);
        $this->assertSame((int) $message->id, $notification->data['message_id']);
        $this->assertSame((int) $booking->id, $notification->data['booking_id']);
        $this->assertSame((int) $owner->id, $notification->data['sender_id']);
        $this->assertSame($owner->name, $notification->data['sender_name']);
        $this->assertSame('Hello manager', $notification->data['preview']);
        $this->assertFalse($notification->data['has_attachment']);

        // Отправитель уведомления не получает.
        $this->assertSame(
            0,
            DatabaseNotification::query()->where('notifiable_id', $owner->id)->count()
        );

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool => $mail->hasTo($manager->email)
        );
    }

    // -----------------------------------------------------------------------
    // 2. Персистентность не зависит от email_notifications=false
    // -----------------------------------------------------------------------

    public function test_database_notification_persists_when_email_notifications_disabled(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => false,
                'new_messages'         => true,
            ]),
        ])->save();

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        $this->assertDatabaseCount('notifications', 1);
        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 3. Персистентность не зависит от new_messages=false
    // -----------------------------------------------------------------------

    public function test_database_notification_persists_when_new_messages_disabled(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => true,
                'new_messages'         => false,
            ]),
        ])->save();

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        $this->assertDatabaseCount('notifications', 1);
        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 4. Персистентность не зависит от технического email получателя
    // -----------------------------------------------------------------------

    public function test_database_notification_persists_for_technical_receiver_email(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'email' => 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->assertTrue($manager->fresh()->hasTechnicalEmail());

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        $this->assertDatabaseCount('notifications', 1);
        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 5. Сообщение только с вложением: превью "Новое вложение", без пути файла
    // -----------------------------------------------------------------------

    public function test_attachment_only_message_preview_is_new_attachment_placeholder(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $message = Message::query()->create([
            'booking_id'     => $booking->id,
            'sender_id'      => $owner->id,
            'receiver_id'    => $manager->id,
            'message'        => '',
            'attachment_url' => 'private/messages/doc.pdf',
        ]);

        $this->dispatchListener($message);

        $notification = DatabaseNotification::query()->firstOrFail();

        $this->assertSame('Новое вложение', $notification->data['preview']);
        $this->assertTrue($notification->data['has_attachment']);
        $this->assertArrayNotHasKey('attachment_url', $notification->data);
        $this->assertArrayNotHasKey('attachment_path', $notification->data);
    }

    // -----------------------------------------------------------------------
    // 6. Длинное сообщение: превью ограничено через Str::limit(120)
    // -----------------------------------------------------------------------

    public function test_long_message_preview_is_limited_via_str_limit(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $longText = str_repeat('Привет менеджер, у меня вопрос по туру. ', 10);
        $this->assertGreaterThan(120, mb_strlen($longText));

        $message = $this->makeMessage($booking, $owner, $manager, $longText);

        $this->dispatchListener($message);

        $notification = DatabaseNotification::query()->firstOrFail();
        $expectedPreview = Str::limit(trim($longText), 120);

        $this->assertSame($expectedPreview, $notification->data['preview']);
        $this->assertNotSame($longText, $notification->data['preview']);
        $this->assertStringNotContainsString('<', $notification->data['preview']);
        $this->assertStringNotContainsString('>', $notification->data['preview']);
    }

    // -----------------------------------------------------------------------
    // 7. Сбой почты ПОСЛЕ персистенции не теряет запись в notifications
    // -----------------------------------------------------------------------

    public function test_mail_failure_after_persistence_leaves_database_notification_intact(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessage($booking, $owner, $manager);

        Mail::shouldReceive('to')
            ->with($manager->email)
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        try {
            $this->dispatchListener($message);
            $this->fail('Expected RuntimeException from mail queue failure was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('smtp down', $e->getMessage());
        }

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id'   => $manager->id,
            'type'            => NewMessageDatabaseNotification::class,
        ]);
    }

    // -----------------------------------------------------------------------
    // 8. Сбой database-уведомления не блокирует письмо
    // -----------------------------------------------------------------------

    public function test_database_notification_failure_does_not_block_email(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessage($booking, $owner, $manager);

        $creatingRan = false;

        DatabaseNotification::creating(function () use (&$creatingRan) {
            $creatingRan = true;
            throw new \RuntimeException('forced database notification failure');
        });

        try {
            $this->dispatchListener($message);
        } finally {
            DatabaseNotification::flushEventListeners();
        }

        // Тест не должен проходить вхолостую: убеждаемся, что хук отработал.
        $this->assertTrue($creatingRan, 'DatabaseNotification::creating must have executed (test not vacuous).');
        $this->assertDatabaseCount('notifications', 0);

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool => $mail->hasTo($manager->email)
        );
    }

    // -----------------------------------------------------------------------
    // 9. Нет получателя: ни записи, ни письма, ни исключения
    // -----------------------------------------------------------------------

    public function test_missing_receiver_relation_persists_no_notification_and_no_email(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, null);

        $message = new Message([
            'booking_id'  => $booking->id,
            'sender_id'   => $owner->id,
            'receiver_id' => null,
            'message'     => 'Hello, is anyone there?',
        ]);

        $this->assertNull($message->receiver);

        $this->dispatchListener($message);

        $this->assertDatabaseCount('notifications', 0);
        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeBookingFor(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => $status,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => '2026-08-15',
                'nights'              => 7,
                'adults'              => 2,
                'children'            => 0,
            ])
        );
    }

    private function makeMessage(
        Booking $booking,
        User $sender,
        User $receiver,
        string $text = 'Hello, is anyone there?'
    ): Message {
        return Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'message'     => $text,
        ]);
    }

    private function dispatchListener(Message $message): void
    {
        (new SendNewMessageNotification())->handle(new NewMessageReceived($message));
    }
}
