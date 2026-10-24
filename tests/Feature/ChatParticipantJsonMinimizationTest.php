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
 * SEC-FIX-01: JSON-ответы чата (messages.index, messages.store) не должны
 * сериализовать полные модели User участников переписки.
 *
 * Клиентский чат (public/js/cabinet-chat.js) использует только поля самого
 * сообщения; имя собеседника берётся из серверной разметки. Поэтому в JSON
 * остаётся минимальный явный контракт участника: id и name. Всё остальное
 * (контакты, паспортные данные, адрес, настройки, служебные поля, avatar_path)
 * не должно попадать в ответ ни под каким видом.
 *
 * Данные — только синтетические уникальные маркеры, не реальные.
 */
class ChatParticipantJsonMinimizationTest extends TestCase
{
    use RefreshDatabase;

    /** Единственные допустимые ключи участника в JSON чата. */
    private const PARTICIPANT_KEYS = ['id', 'name'];

    /** Допустимые ключи сообщения (контракт до исправления, без User-данных). */
    private const MESSAGE_KEYS = [
        'id', 'booking_id', 'sender_id', 'receiver_id', 'message', 'is_read',
        'read_at', 'created_at', 'updated_at', 'attachment_download_url',
        'sender', 'receiver',
    ];

    /** Все приватные поля User, которых не должно быть в участнике. */
    private const FORBIDDEN_USER_KEYS = [
        'email', 'email_verified_at', 'phone', 'birth_date', 'gender', 'address',
        'passport_number', 'passport_issued_date', 'passport_issued_by',
        'notification_settings', 'is_active', 'password_change_required',
        'avatar_path', 'last_login_at', 'created_at', 'updated_at',
        'password', 'remember_token', 'temp_password', 'roles',
    ];

    /** @var array<string, array<string, string>> sentinel-значения по ролям */
    private array $sentinels = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Storage::fake('local');
    }

    // -----------------------------------------------------------------------
    // messages.index (первичная загрузка и опрос)
    // -----------------------------------------------------------------------

    public function test_tourist_poll_does_not_leak_manager_profile_as_sender_or_receiver(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        $fromManager = $this->makeMessage($booking, $manager->id, $tourist->id);
        $toManager   = $this->makeMessage($booking, $tourist->id, $manager->id);

        $response = $this->actingAs($tourist)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $this->assertSafeMessages($response->json(), [$fromManager->id, $toManager->id]);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_manager_poll_does_not_leak_tourist_profile_as_sender_or_receiver(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        $fromTourist = $this->makeMessage($booking, $tourist->id, $manager->id);
        $toTourist   = $this->makeMessage($booking, $manager->id, $tourist->id);

        $response = $this->actingAs($manager)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $this->assertSafeMessages($response->json(), [$fromTourist->id, $toTourist->id]);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_supervising_admin_poll_does_not_leak_either_participant_profile(): void
    {
        [$tourist, $manager, $admin, $booking] = $this->scenario();
        $a = $this->makeMessage($booking, $tourist->id, $manager->id);
        $b = $this->makeMessage($booking, $manager->id, $tourist->id);

        $response = $this->actingAs($admin)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $this->assertSafeMessages($response->json(), [$a->id, $b->id]);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_assigned_admin_poll_does_not_leak_tourist_profile(): void
    {
        [$tourist, , $admin] = $this->scenario();
        $booking = $this->makeBooking($tourist, $admin->id);
        $a = $this->makeMessage($booking, $tourist->id, $admin->id);
        $b = $this->makeMessage($booking, $admin->id, $tourist->id);

        $response = $this->actingAs($admin)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $this->assertSafeMessages($response->json(), [$a->id, $b->id]);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_repeated_polling_keeps_the_minimal_contract(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        $first = $this->makeMessage($booking, $manager->id, $tourist->id);

        $url = route('messages.index', ['booking_id' => $booking->id]);

        $one = $this->actingAs($tourist)->getJson($url)->assertOk();
        $second = $this->makeMessage($booking, $manager->id, $tourist->id);
        $two = $this->actingAs($tourist)->getJson($url)->assertOk();

        $this->assertSafeMessages($one->json(), [$first->id]);
        $this->assertSafeMessages($two->json(), [$first->id, $second->id]);
        $this->assertNoSentinelsInBody($one->getContent());
        $this->assertNoSentinelsInBody($two->getContent());
    }

    public function test_participant_display_fields_remain_available(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        $this->makeMessage($booking, $manager->id, $tourist->id);

        $row = $this->actingAs($tourist)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk()
            ->json(0);

        $this->assertSame($manager->id, $row['sender']['id']);
        $this->assertSame($manager->name, $row['sender']['name']);
        $this->assertSame($tourist->id, $row['receiver']['id']);
        $this->assertSame($tourist->name, $row['receiver']['name']);
        $this->assertSame($manager->id, $row['sender_id']);
        $this->assertSame($tourist->id, $row['receiver_id']);
        $this->assertSame('Plain message', $row['message']);
    }

    public function test_attachment_contract_is_preserved_without_raw_path(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        Storage::disk('local')->put('messages/sentinel-private.pdf', 'x');
        $with = Message::query()->create([
            'booking_id' => $booking->id, 'sender_id' => $manager->id,
            'receiver_id' => $tourist->id, 'message' => 'with file',
            'attachment_url' => 'messages/sentinel-private.pdf',
        ]);
        $without = $this->makeMessage($booking, $manager->id, $tourist->id);

        $response = $this->actingAs($tourist)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $rows = collect($response->json())->keyBy('id');
        $this->assertSame(route('messages.attachment', $with), $rows[$with->id]['attachment_download_url']);
        $this->assertNull($rows[$without->id]['attachment_download_url']);
        $this->assertStringNotContainsString('sentinel-private', $response->getContent());
        $this->assertArrayNotHasKey('attachment_url', $rows[$with->id]);
    }

    public function test_polling_still_marks_only_callers_unread_messages_as_read(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        $incoming = $this->makeMessage($booking, $manager->id, $tourist->id);
        $outgoing = $this->makeMessage($booking, $tourist->id, $manager->id);

        $this->actingAs($tourist)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $this->assertTrue($incoming->fresh()->is_read);
        $this->assertNotNull($incoming->fresh()->read_at);
        $this->assertFalse($outgoing->fresh()->is_read);
    }

    // -----------------------------------------------------------------------
    // messages.store (JSON-ответ после отправки)
    // -----------------------------------------------------------------------

    public function test_tourist_fresh_send_response_does_not_leak_manager_profile(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();

        $response = $this->actingAs($tourist)
            ->postJson(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $manager->id,
                'message'     => 'Hello manager',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $message = $response->json('message');
        $this->assertSafeMessage($message, true);
        $this->assertSame('Hello manager', $message['message']);
        $this->assertSame($tourist->id, $message['sender']['id']);
        $this->assertSame($manager->id, $message['receiver']['id']);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_manager_fresh_send_response_does_not_leak_tourist_profile(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();

        $response = $this->actingAs($manager)
            ->postJson(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $tourist->id,
                'message'     => 'Hello tourist',
            ])
            ->assertOk();

        $this->assertSafeMessage($response->json('message'), true);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_assigned_admin_fresh_send_response_does_not_leak_tourist_profile(): void
    {
        [$tourist, , $admin] = $this->scenario();
        $booking = $this->makeBooking($tourist, $admin->id);

        $response = $this->actingAs($admin)
            ->postJson(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $tourist->id,
                'message'     => 'Hello from admin',
            ])
            ->assertOk();

        $this->assertSafeMessage($response->json('message'), true);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    public function test_send_response_attachment_contract_is_preserved(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();

        $response = $this->actingAs($tourist)
            ->post(route('messages.store'), [
                'booking_id'  => $booking->id,
                'receiver_id' => $manager->id,
                'attachment'  => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $message = $response->json('message');
        $this->assertSafeMessage($message, true);
        $this->assertNotNull($message['attachment_download_url']);
        $this->assertArrayNotHasKey('attachment_url', $message);
        $this->assertNoSentinelsInBody($response->getContent());
    }

    // -----------------------------------------------------------------------
    // Авторизация не изменилась
    // -----------------------------------------------------------------------

    public function test_previous_manager_is_denied_after_reassignment_and_gets_no_profiles(): void
    {
        [$tourist, $manager, , $booking] = $this->scenario();
        $newManager = $this->makeUser(Role::MANAGER);
        $this->makeMessage($booking, $tourist->id, $manager->id);
        $booking->forceFill(['manager_id' => $newManager->id])->saveQuietly();

        $response = $this->actingAs($manager)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertForbidden();

        $this->assertNoSentinelsInBody($response->getContent());

        $this->actingAs($manager)
            ->postJson(route('messages.store'), [
                'booking_id' => $booking->id, 'receiver_id' => $tourist->id, 'message' => 'late',
            ])
            ->assertForbidden();
    }

    public function test_foreign_booking_is_denied_for_foreign_tourist_and_manager(): void
    {
        [, , , $booking] = $this->scenario();
        $foreignTourist = $this->makeUser(Role::TOURIST);
        $foreignManager = $this->makeUser(Role::MANAGER);

        foreach ([$foreignTourist, $foreignManager] as $stranger) {
            $this->actingAs($stranger)
                ->getJson(route('messages.index', ['booking_id' => $booking->id]))
                ->assertForbidden();
        }
    }

    public function test_supervising_admin_may_read_but_not_write(): void
    {
        [$tourist, $manager, $admin, $booking] = $this->scenario();

        $this->actingAs($admin)
            ->getJson(route('messages.index', ['booking_id' => $booking->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('messages.store'), [
                'booking_id' => $booking->id, 'receiver_id' => $tourist->id, 'message' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_message_model_default_serialization_is_untouched_for_internal_use(): void
    {
        // Исправление ограничено границей ответа чата: модель User не менялась.
        [, $manager] = $this->scenario();

        $this->assertArrayHasKey('passport_number', $manager->toArray());
    }

    // -----------------------------------------------------------------------
    // Утверждения
    // -----------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $expectedIds
     */
    private function assertSafeMessages(array $rows, array $expectedIds): void
    {
        $this->assertSame($expectedIds, array_column($rows, 'id'));

        foreach ($rows as $row) {
            $this->assertSafeMessage($row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertSafeMessage(array $row, bool $freshlyCreated = false): void
    {
        $keys = array_keys($row);
        sort($keys);
        $allowed = self::MESSAGE_KEYS;

        if ($freshlyCreated) {
            // Только что созданная модель не содержит значений по умолчанию БД
            // (is_read/read_at) — как и до исправления; лишних ключей быть не должно.
            $allowed = array_values(array_diff($allowed, ['is_read', 'read_at']));
        }

        sort($allowed);
        $this->assertSame($allowed, $keys, 'Unexpected message JSON keys');

        foreach (['sender', 'receiver'] as $relation) {
            $this->assertIsArray($row[$relation]);
            $participant = array_keys($row[$relation]);
            sort($participant);
            $this->assertSame(self::PARTICIPANT_KEYS, $participant, "Unexpected {$relation} keys");

            foreach (self::FORBIDDEN_USER_KEYS as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $row[$relation]);
            }
        }
    }

    private function assertNoSentinelsInBody(string $body): void
    {
        foreach ($this->sentinels as $role => $values) {
            foreach ($values as $field => $value) {
                $this->assertStringNotContainsString(
                    $value,
                    $body,
                    "{$role}.{$field} sentinel leaked into the JSON body"
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Фикстуры
    // -----------------------------------------------------------------------

    /**
     * @return array{0: User, 1: User, 2: User, 3: Booking} турист, менеджер, админ-наблюдатель, заявка
     */
    private function scenario(): array
    {
        $tourist = $this->makeUser(Role::TOURIST, 'tourist');
        $manager = $this->makeUser(Role::MANAGER, 'manager');
        $admin   = $this->makeUser(Role::ADMIN, 'admin');

        return [$tourist, $manager, $admin, $this->makeBooking($tourist, $manager->id)];
    }

    private function makeUser(string $roleName, ?string $tag = null): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        if ($tag !== null) {
            $this->sentinels[$tag] = [
                'email'           => "sentinel-{$tag}-mail@leak.invalid",
                'phone'           => "+000SENT{$tag}PHONE",
                'address'         => "SENTINEL-ADDRESS-{$tag}",
                'passport_number' => "SENTPASS{$tag}0001",
                'passport_by'     => "SENTINEL-ISSUER-{$tag}",
                'avatar'          => "avatars/sentinel-{$tag}.png",
                'settings'        => "sentinel-setting-{$tag}",
            ];
            $v = $this->sentinels[$tag];

            $user->forceFill([
                'email'                 => $v['email'],
                'phone'                 => $v['phone'],
                'address'               => $v['address'],
                'birth_date'            => '1901-02-03',
                'passport_number'       => $v['passport_number'],
                'passport_issued_date'  => '1902-03-04',
                'passport_issued_by'    => $v['passport_by'],
                'avatar_path'           => $v['avatar'],
                'notification_settings' => [$v['settings'] => true],
                'last_login_at'         => '1903-04-05 06:07:08',
            ])->save();
        }

        return $user->fresh();
    }

    private function makeBooking(User $owner, ?int $managerId): Booking
    {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => Booking::STATUS_PROGRESS,
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

    private function makeMessage(Booking $booking, int $senderId, int $receiverId): Message
    {
        return Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'message'     => 'Plain message',
        ]);
    }
}
