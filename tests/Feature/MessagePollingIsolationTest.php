<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Покрытие эндпоинта опроса/чтения сообщений MessageController::index
 * (маршрут messages.index) — «механизм обновления (polling)» и «изоляция
 * переписок по заявке» из Этапа 6.
 *
 * Слой строго тестовый: исходный код не меняется. Проверяется фактический
 * текущий контракт:
 *  - авторизация участников (владелец, текущий менеджер, назначенный админ,
 *    надзорный админ) и отказы (гость, чужой турист, чужой менеджер, прежний
 *    ответственный после переназначения, отсутствие booking_id);
 *  - изоляция по booking_id и восходящий порядок по created_at;
 *  - пометка прочитанным только сообщений вызывающего (is_read + read_at),
 *    и отсутствие побочных пометок для чужих сообщений/надзорного админа;
 *  - JSON-контракт вложений: attachment_download_url присутствует для каждого
 *    сообщения (null без вложения), приватный attachment_url не раскрывается.
 */
class MessagePollingIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Изолируем логику эндпоинта от слушателей (mail/notifications).
        Event::fake();
    }

    // -----------------------------------------------------------------------
    // Allowed roles can poll (200)
    // -----------------------------------------------------------------------

    public function test_owner_tourist_can_poll_messages(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessage($booking, $manager->id, $owner->id);

        $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $message->id]);
    }

    public function test_current_assigned_manager_can_poll_messages(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessage($booking, $owner->id, $manager->id);

        $this->actingAs($manager)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $message->id]);
    }

    public function test_assigned_admin_can_poll_messages(): void
    {
        $owner = $this->makeUser(Role::TOURIST);
        $admin = $this->makeUser(Role::ADMIN);
        // Администратор лично ведёт заявку: его id в manager_id.
        $booking = $this->makeBookingFor($owner, $admin->id, Booking::STATUS_PROGRESS);
        $message = $this->makeMessage($booking, $owner->id, $admin->id);

        $this->actingAs($admin)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $message->id]);
    }

    public function test_supervising_admin_can_poll_messages(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN); // не назначен на заявку
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessage($booking, $owner->id, $manager->id);

        $this->actingAs($admin)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $message->id]);
    }

    // -----------------------------------------------------------------------
    // Denials
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_poll_messages(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertUnauthorized();
    }

    public function test_foreign_tourist_cannot_poll_messages(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $manager        = $this->makeUser(Role::MANAGER);
        $foreignTourist = $this->makeUser(Role::TOURIST);
        $booking        = $this->makeBookingFor($owner, $manager->id);
        $this->makeMessage($booking, $owner->id, $manager->id);

        $this->actingAs($foreignTourist)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertForbidden();
    }

    public function test_foreign_manager_cannot_poll_messages(): void
    {
        $owner          = $this->makeUser(Role::TOURIST);
        $manager        = $this->makeUser(Role::MANAGER);
        $foreignManager = $this->makeUser(Role::MANAGER);
        $booking        = $this->makeBookingFor($owner, $manager->id);
        $this->makeMessage($booking, $owner->id, $manager->id);

        $this->actingAs($foreignManager)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertForbidden();
    }

    public function test_previous_assignee_cannot_poll_after_reassignment(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id);
        $this->makeMessage($booking, $managerA->id, $owner->id);

        // Реальное переназначение manager_id на другого менеджера.
        $booking->manager_id = $managerB->id;
        $booking->saveQuietly();

        // Прежний ответственный (managerA) больше не участник — 403.
        $this->actingAs($managerA)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertForbidden();
    }

    public function test_missing_booking_id_returns_400(): void
    {
        $owner = $this->makeUser(Role::TOURIST);

        $this->actingAs($owner)
            ->getJson(route('messages.index'))
            ->assertStatus(400);
    }

    // -----------------------------------------------------------------------
    // Isolation / ordering
    // -----------------------------------------------------------------------

    public function test_poll_returns_only_requested_booking_messages(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);

        $bookingA = $this->makeBookingFor($owner, $manager->id);
        $bookingB = $this->makeBookingFor($owner, $manager->id);

        $inA1 = $this->makeMessage($bookingA, $manager->id, $owner->id);
        $inA2 = $this->makeMessage($bookingA, $owner->id, $manager->id);
        $inB1 = $this->makeMessage($bookingB, $manager->id, $owner->id);
        $inB2 = $this->makeMessage($bookingB, $owner->id, $manager->id);

        $response = $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $bookingA->id]))
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($inA1->id, $ids);
        $this->assertContains($inA2->id, $ids);
        $this->assertNotContains($inB1->id, $ids);
        $this->assertNotContains($inB2->id, $ids);
        $this->assertCount(2, $ids, 'Poll must return exactly the requested booking messages.');
    }

    public function test_poll_orders_messages_ascending(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Порядок вставки намеренно НЕ совпадает с хронологией created_at.
        $newest = $this->makeMessage($booking, $manager->id, $owner->id); // now-1m
        $newest->forceFill(['created_at' => now()->subMinutes(1)])->saveQuietly();

        $oldest = $this->makeMessage($booking, $owner->id, $manager->id); // now-10m
        $oldest->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

        $middle = $this->makeMessage($booking, $manager->id, $owner->id); // now-5m
        $middle->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

        $response = $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        // Ожидаемый порядок по возрастанию created_at: oldest, middle, newest.
        $this->assertSame([$oldest->id, $middle->id, $newest->id], $ids);
    }

    // -----------------------------------------------------------------------
    // Read-marking semantics
    // -----------------------------------------------------------------------

    public function test_poll_marks_callers_unread_as_read_and_sets_read_at(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Сообщение адресовано владельцу и пока не прочитано.
        $message = $this->makeMessage($booking, $manager->id, $owner->id, false);
        $this->assertFalse((bool) $message->is_read);
        $this->assertNull($message->read_at);

        $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $message->refresh();
        $this->assertTrue((bool) $message->is_read);
        $this->assertNotNull($message->read_at);
    }

    public function test_poll_does_not_mark_other_participants_messages_read(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Сообщение адресовано менеджеру, а опрашивает владелец.
        $toManager = $this->makeMessage($booking, $owner->id, $manager->id, false);

        $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $toManager->refresh();
        $this->assertFalse((bool) $toManager->is_read);
        $this->assertNull($toManager->read_at);
    }

    public function test_supervising_admin_poll_marks_nothing_read(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN); // ни отправитель, ни получатель
        $booking = $this->makeBookingFor($owner, $manager->id);

        $toOwner   = $this->makeMessage($booking, $manager->id, $owner->id, false);
        $toManager = $this->makeMessage($booking, $owner->id, $manager->id, false);

        $this->actingAs($admin)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $toOwner->refresh();
        $toManager->refresh();

        $this->assertFalse((bool) $toOwner->is_read);
        $this->assertNull($toOwner->read_at);
        $this->assertFalse((bool) $toManager->is_read);
        $this->assertNull($toManager->read_at);
    }

    // -----------------------------------------------------------------------
    // JSON attachment contract
    // -----------------------------------------------------------------------

    public function test_every_polled_message_includes_attachment_download_url_present_or_null(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $withAttachment = $this->makeMessageWithAttachment($booking, $manager->id, $owner->id);
        $withoutAttachment = $this->makeMessage($booking, $manager->id, $owner->id);

        $response = $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $byId = collect($response->json())->keyBy('id');

        // Ключ присутствует у КАЖДОГО сообщения.
        $this->assertArrayHasKey('attachment_download_url', $byId[$withAttachment->id]);
        $this->assertArrayHasKey('attachment_download_url', $byId[$withoutAttachment->id]);

        // Со вложением — защищённый маршрут; без вложения — null.
        $this->assertSame(
            route('messages.attachment', $withAttachment),
            $byId[$withAttachment->id]['attachment_download_url']
        );
        $this->assertNull($byId[$withoutAttachment->id]['attachment_download_url']);
    }

    public function test_polled_messages_never_expose_raw_attachment_path(): void
    {
        Storage::fake('local');

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);
        $message = $this->makeMessageWithAttachment($booking, $manager->id, $owner->id);

        $response = $this->actingAs($owner)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $payload = $response->json();
        $this->assertNotEmpty($payload);
        $this->assertArrayNotHasKey('attachment_url', $payload[0]);
        $this->assertStringNotContainsString('/storage/', $response->getContent());
        $this->assertStringNotContainsString($message->attachment_url, $response->getContent());
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
        int $senderId,
        int $receiverId,
        bool $isRead = false
    ): Message {
        return Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'message'     => 'Plain message',
            'is_read'     => $isRead,
        ]);
    }

    /**
     * Сообщение с вложением на приватном (local) диске.
     */
    private function makeMessageWithAttachment(
        Booking $booking,
        int $senderId,
        int $receiverId
    ): Message {
        $path = 'messages/' . uniqid('att_', true) . '.pdf';
        Storage::disk('local')->put($path, 'private-attachment-content');

        return Message::query()->create([
            'booking_id'     => $booking->id,
            'sender_id'      => $senderId,
            'receiver_id'    => $receiverId,
            'message'        => 'Attachment message',
            'attachment_url' => $path,
        ]);
    }
}
