<?php

namespace Tests\Feature;

use App\Events\NewMessageReceived;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Проверяет, что реальный поток создания сообщения диспатчит доменное событие
 * NewMessageReceived ровно один раз при успешном сохранении и никогда — при
 * провале валидации/авторизации/персистенции. Также фиксирует контракт:
 * сбой уведомления после успешного сохранения не откатывает запись, не удаляет
 * приватное вложение и не портит успешный HTTP-ответ.
 *
 * Важно: здесь НЕ используется глобальный Event::fake(). Он подменяет диспетчер
 * событий, через который проходят и события модели Eloquent (Message::creating),
 * поэтому глобальная подмена заглушила бы хук, которым тест намеренно валит
 * Message::create(). Используется точечная подмена только NewMessageReceived.
 */
class NewMessageNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Успешное сообщение диспатчит событие ровно один раз
    // -----------------------------------------------------------------------

    public function test_valid_message_dispatches_new_message_received_once(): void
    {
        Event::fake([NewMessageReceived::class]);

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello manager',
        ])->assertOk();

        Event::assertDispatched(NewMessageReceived::class, 1);
    }

    // -----------------------------------------------------------------------
    // 2. Событие несёт именно сохранённое сообщение
    // -----------------------------------------------------------------------

    public function test_dispatched_event_carries_persisted_message(): void
    {
        Event::fake([NewMessageReceived::class]);

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello manager',
        ])->assertOk();

        $persisted = Message::query()->firstOrFail();

        Event::assertDispatched(
            NewMessageReceived::class,
            function (NewMessageReceived $event) use ($persisted, $booking, $owner, $manager): bool {
                return $event->message->exists
                    && $event->message->id === $persisted->id
                    && (int) $event->message->booking_id === (int) $booking->id
                    && (int) $event->message->sender_id === (int) $owner->id
                    && (int) $event->message->receiver_id === (int) $manager->id;
            }
        );
    }

    // -----------------------------------------------------------------------
    // 3. Невалидный получатель — никакого события
    // -----------------------------------------------------------------------

    public function test_invalid_receiver_dispatches_no_event(): void
    {
        Event::fake([NewMessageReceived::class]);

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $other   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $other->id,
            'message'     => 'Hello',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('receiver_id');

        Event::assertNotDispatched(NewMessageReceived::class);
        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 4. Чужой участник (403) — никакого события
    // -----------------------------------------------------------------------

    public function test_foreign_participant_dispatches_no_event(): void
    {
        Event::fake([NewMessageReceived::class]);

        $owner          = $this->makeUser(Role::TOURIST);
        $manager        = $this->makeUser(Role::MANAGER);
        $foreignTourist = $this->makeUser(Role::TOURIST);
        $booking        = $this->makeBookingFor($owner, $manager->id);

        $this->actingAs($foreignTourist)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'Hello',
        ])->assertForbidden();

        Event::assertNotDispatched(NewMessageReceived::class);
        $this->assertDatabaseCount('messages', 0);
    }

    // -----------------------------------------------------------------------
    // 5. Провал персистенции — никакого события (и тест не «пустой»)
    // -----------------------------------------------------------------------

    public function test_persistence_failure_dispatches_no_event(): void
    {
        // Точечная подмена: события модели Eloquent остаются активными, чтобы
        // хук Message::creating реально выполнился и бросил исключение.
        Event::fake([NewMessageReceived::class]);

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $creatingRan = false;

        Message::creating(function () use (&$creatingRan) {
            $creatingRan = true;
            throw new \RuntimeException('forced persistence failure');
        });

        try {
            $this->actingAs($owner)->postJson(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $manager->id,
                'message'     => 'Boom',
            ])->assertStatus(500);
        } finally {
            Message::flushEventListeners();
        }

        // Тест не должен проходить вхолостую: убеждаемся, что хук отработал.
        $this->assertTrue($creatingRan, 'Message::creating must have executed (test not vacuous).');
        $this->assertDatabaseCount('messages', 0);
        Event::assertNotDispatched(NewMessageReceived::class);
    }

    // -----------------------------------------------------------------------
    // 6. Сбой почты ПОСЛЕ сохранения не теряет запись/вложение и не рушит ответ
    // -----------------------------------------------------------------------

    public function test_mail_failure_after_persistence_preserves_message_and_response(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Реальное событие и реальный слушатель: подменяем только почтовый путь,
        // заставляя его бросить исключение независимо от диспетчера событий.
        // Ожидание привязано к email получателя и вызывается ровно один раз —
        // это доказывает, что реальный слушатель действительно дошёл до Mail::to().
        Mail::shouldReceive('to')
            ->with($manager->email)
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'See attached',
            'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertOk()
          ->assertJson(['success' => true]);

        // (1) сообщение осталось сохранённым
        $this->assertDatabaseHas('messages', [
            'booking_id'  => $booking->id,
            'sender_id'   => $owner->id,
            'receiver_id' => $manager->id,
        ]);

        // (2) приватное вложение осталось на диске
        $message = Message::query()->firstOrFail();
        $this->assertNotEmpty($message->attachment_url);
        Storage::disk('local')->assertExists($message->attachment_url);
    }

    // -----------------------------------------------------------------------
    // 7. Контракт JSON-ответа не изменился из-за диспатча события
    // -----------------------------------------------------------------------

    public function test_store_json_response_shape_unchanged_with_dispatch(): void
    {
        Event::fake([NewMessageReceived::class]);
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $response = $this->actingAs($owner)->postJson(route('messages.store'), [
            'booking_id'  => $booking->id,
            'receiver_id' => $manager->id,
            'message'     => 'See attached',
            'attachment'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertOk()
          ->assertJson(['success' => true]);

        $message = Message::query()->firstOrFail();

        $response->assertJsonFragment([
            'attachment_download_url' => route('messages.attachment', $message),
        ]);

        // Приватный путь вложения не утекает в ответ.
        $this->assertArrayNotHasKey('attachment_url', $response->json('message'));
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
}
