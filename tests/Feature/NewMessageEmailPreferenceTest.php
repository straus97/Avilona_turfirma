<?php

namespace Tests\Feature;

use App\Events\NewMessageReceived;
use App\Listeners\SendNewMessageNotification;
use App\Mail\NewMessageReceived as NewMessageReceivedMail;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Проверяет контракт SendNewMessageNotification: письмо о новом сообщении
 * ставится в очередь только получателю, который (1) присутствует,
 * (2) не имеет технического email, (3) явно не отключил email_notifications
 * и (4) явно не отключил new_messages. Настройки по умолчанию (null, пустой
 * JSON, отсутствующие ключи, повреждённый JSON) трактуются как «включено».
 *
 * Слушатель вызывается напрямую (без диспетчера событий и без HTTP-слоя),
 * чтобы не дублировать уже существующие тесты авторизации/диспатча события
 * в NewMessageNotificationDispatchTest.
 */
class NewMessageEmailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    // -----------------------------------------------------------------------
    // 1. Настройки по умолчанию / null — письмо ставится в очередь
    // -----------------------------------------------------------------------

    public function test_default_null_settings_queue_mail_to_the_exact_receiver(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $manager  = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $manager->id);

        $this->assertNull($manager->notification_settings);

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool =>
                $mail->hasTo($manager->email)
                && $mail->message->id === $message->id
        );
    }

    // -----------------------------------------------------------------------
    // 2. email_notifications=false — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_explicit_email_notifications_false_queues_no_mail(): void
    {
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

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 3. new_messages=false — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_explicit_new_messages_false_queues_no_mail(): void
    {
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

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 4. email_notifications=true и new_messages=true — письмо ставится в очередь
    // -----------------------------------------------------------------------

    public function test_both_flags_explicitly_true_queue_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'notification_settings' => json_encode([
                'email_notifications' => true,
                'new_messages'         => true,
            ]),
        ])->save();

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool => $mail->hasTo($manager->email)
        );
    }

    // -----------------------------------------------------------------------
    // 5. booking_updates=false сам по себе не подавляет письмо о сообщении
    // -----------------------------------------------------------------------

    public function test_booking_updates_false_alone_does_not_suppress_new_message_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'notification_settings' => json_encode([
                'booking_updates' => false,
            ]),
        ])->save();

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool => $mail->hasTo($manager->email)
        );
    }

    // -----------------------------------------------------------------------
    // 6. Текущий сгенерированный технический email — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_current_generated_technical_email_queues_no_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill([
            'email' => 'temp_' . \Illuminate\Support\Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN,
        ])->save();

        $this->assertTrue($manager->fresh()->hasTechnicalEmail());

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 7. Устаревший (legacy) технический email — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    public function test_legacy_technical_email_queues_no_mail(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $manager->forceFill(['email' => 'temp_1736600000@avilona.ru'])->save();

        $this->assertTrue($manager->fresh()->hasTechnicalEmail());

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertNothingQueued();
    }

    // -----------------------------------------------------------------------
    // 8. Повреждённый notification_settings — поведение по умолчанию (включено)
    // -----------------------------------------------------------------------

    public function test_malformed_notification_settings_preserves_default_on_behavior(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Не валидный JSON.
        $manager->forceFill(['notification_settings' => '{invalid-json'])->save();

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool => $mail->hasTo($manager->email)
        );
    }

    public function test_decoded_non_array_notification_settings_preserves_default_on_behavior(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Валидный JSON, но не объект/массив (просто булево значение).
        $manager->forceFill(['notification_settings' => 'true'])->save();

        $message = $this->makeMessage($booking, $owner, $manager);

        $this->dispatchListener($message);

        Mail::assertQueued(
            NewMessageReceivedMail::class,
            fn (NewMessageReceivedMail $mail): bool => $mail->hasTo($manager->email)
        );
    }

    // -----------------------------------------------------------------------
    // 9. Нет получателя — письмо не ставится в очередь
    // -----------------------------------------------------------------------

    /**
     * receiver_id в таблице messages объявлен как NOT NULL foreign key,
     * поэтому персистентную запись без получателя создать нельзя. Чтобы
     * не нарушать ограничения базы данных, здесь используется не сохранённая
     * в БД модель Message: слушатель вызывается напрямую с событием, несущим
     * такой объект, и никогда не пытается его сохранить.
     */
    public function test_no_receiver_queues_no_mail(): void
    {
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

    private function makeMessage(Booking $booking, User $sender, User $receiver): Message
    {
        return Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'message'     => 'Hello, is anyone there?',
        ]);
    }

    private function dispatchListener(Message $message): void
    {
        (new SendNewMessageNotification())->handle(new NewMessageReceived($message));
    }
}
