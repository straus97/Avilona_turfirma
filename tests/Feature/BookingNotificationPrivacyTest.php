<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Support\Notifications\MessageNotificationVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I2B, G8 — устаревшие уведомления о сообщениях по заявке не раскрывают содержимое
 * прежнему ответственному (превью, счётчик, открытие). Только синтетические данные.
 */
class BookingNotificationPrivacyTest extends TestCase
{
    use BuildsAoaI2bFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $a;
    private User $b;
    private User $client;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->client = $this->userWith('tourist', ['name' => 'Клиент Секретов']);
        $this->booking = $this->bookingOf($this->client, $this->a);
    }

    private function reassignToB(): void
    {
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->booking), [
            'manager_id' => $this->b->id, 'expected_manager_id' => $this->a->id, 'reason_code' => 'workload',
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    private function bell(User $user, string $route = 'cabinet.manager.profile'): string
    {
        return $this->actingAs($user)->get(route($route))->assertOk()->getContent();
    }

    public function test_current_manager_sees_preview_and_count_before_reassignment(): void
    {
        $this->messageNotice($this->booking, $this->client, $this->a, 'ПРЕВЬЮ-СЕКРЕТНОЕ-АЛЬФА');

        $html = $this->bell($this->a);
        $this->assertStringContainsString('ПРЕВЬЮ-СЕКРЕТНОЕ-АЛЬФА', $html);
        $this->assertStringContainsString('notification-badge">1</span>', $html);
    }

    public function test_former_manager_loses_preview_badge_and_open_after_reassignment(): void
    {
        $notice = $this->messageNotice($this->booking, $this->client, $this->a, 'ПРЕВЬЮ-СЕКРЕТНОЕ-АЛЬФА');
        $this->reassignToB();

        $html = $this->bell($this->a);
        $this->assertStringNotContainsString('ПРЕВЬЮ-СЕКРЕТНОЕ-АЛЬФА', $html);
        $this->assertStringNotContainsString('Клиент Секретов', $html);
        $this->assertStringNotContainsString('notification-badge', $html);
        $this->assertStringNotContainsString('/cabinet/notifications/' . $notice->id, $html);
        $this->assertStringContainsString('Нет новых уведомлений', $html);

        // Прямое открытие по id также не раскрывает заявку и не помечает запись прочитанной.
        $this->actingAs($this->a)->post(route('cabinet.notifications.open', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);

        // Запись не удалена (история сохранена).
        $this->assertNotNull($notice->fresh());
    }

    public function test_unread_messages_counter_is_consistent_with_current_access(): void
    {
        $this->messageNotice($this->booking, $this->client, $this->a, 'Сообщение 1');
        $this->actingAs($this->a)->getJson(route('messages.unread-count'))->assertOk()->assertJson(['count' => 1]);

        $this->reassignToB();

        $this->actingAs($this->a)->getJson(route('messages.unread-count'))->assertOk()->assertExactJson(['count' => 0]);
        // Администратор считает все свои непрочитанные, турист — по своим заявкам.
        $this->messageNotice($this->booking, $this->b, $this->client, 'Ответ клиенту');
        $this->actingAs($this->client)->getJson(route('messages.unread-count'))->assertOk()->assertJson(['count' => 1]);
    }

    public function test_new_responsible_manager_gets_proper_access_for_new_messages(): void
    {
        $this->messageNotice($this->booking, $this->client, $this->a, 'Старое для А');
        $this->reassignToB();

        $notice = $this->messageNotice($this->booking, $this->client, $this->b, 'ПРЕВЬЮ-НОВОМУ-БЕТА');

        $html = $this->bell($this->b);
        $this->assertStringContainsString('ПРЕВЬЮ-НОВОМУ-БЕТА', $html);
        $this->assertStringContainsString('notification-badge">1</span>', $html);
        $this->actingAs($this->b)->post(route('cabinet.notifications.open', $notice->id))
            ->assertRedirect(route('cabinet.manager.chat', ['bookingId' => $this->booking->id]));
    }

    public function test_notifications_for_other_still_owned_bookings_keep_working(): void
    {
        $other = $this->bookingOf($this->client, $this->a);
        $this->messageNotice($this->booking, $this->client, $this->a, 'ПЕРЕДАННАЯ-ЗАЯВКА-ТЕКСТ');
        $this->messageNotice($other, $this->client, $this->a, 'ОСТАЛАСЬ-У-АЛЬФА');
        $this->reassignToB();

        $html = $this->bell($this->a);
        $this->assertStringContainsString('ОСТАЛАСЬ-У-АЛЬФА', $html);
        $this->assertStringNotContainsString('ПЕРЕДАННАЯ-ЗАЯВКА-ТЕКСТ', $html);
        $this->assertStringContainsString('notification-badge">1</span>', $html);
    }

    public function test_admin_oversight_still_sees_notifications(): void
    {
        $this->messageNotice($this->booking, $this->client, $this->admin, 'ПРЕВЬЮ-ДЛЯ-АДМИНА');

        $html = $this->bell($this->admin, 'cabinet.admin.dashboard');
        $this->assertStringContainsString('ПРЕВЬЮ-ДЛЯ-АДМИНА', $html);
        $this->assertStringContainsString('notification-badge">1</span>', $html);
    }

    public function test_tourist_sees_own_booking_notifications_only(): void
    {
        $this->messageNotice($this->booking, $this->a, $this->client, 'ОТВЕТ-МЕНЕДЖЕРА-КЛИЕНТУ');

        $stranger = $this->userWith('tourist');
        // Уведомление чужого туриста, адресованное не-участнику (повреждённые данные): не показывается.
        $foreignNotice = $this->messageNotice($this->booking, $this->a, $stranger, 'ЧУЖОЕ-УВЕДОМЛЕНИЕ');

        $this->assertStringContainsString('ОТВЕТ-МЕНЕДЖЕРА-КЛИЕНТУ', $this->bell($this->client, 'cabinet.dashboard'));
        $html = $this->bell($stranger, 'cabinet.dashboard');
        $this->assertStringNotContainsString('ЧУЖОЕ-УВЕДОМЛЕНИЕ', $html);
        $this->actingAs($stranger)->post(route('cabinet.notifications.open', $foreignNotice->id))->assertNotFound();
    }

    public function test_notification_without_a_resolvable_booking_is_hidden_from_non_admins(): void
    {
        foreach ([null, 'abc', 0, 999999] as $badBookingId) {
            $this->a->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => \App\Notifications\NewMessageDatabaseNotification::class,
                'data' => ['type' => 'new_message', 'booking_id' => $badBookingId, 'sender_name' => 'X', 'preview' => 'БИТОЕ-УВЕДОМЛЕНИЕ'],
            ]);
        }

        $this->assertSame(0, MessageNotificationVisibility::unreadFor($this->a)->count());
        $this->assertStringNotContainsString('БИТОЕ-УВЕДОМЛЕНИЕ', $this->bell($this->a));
    }

    public function test_notification_returns_when_the_booking_is_assigned_back(): void
    {
        $this->messageNotice($this->booking, $this->client, $this->a, 'ВЕРНУЛОСЬ-ВМЕСТЕ-С-ЗАЯВКОЙ');
        $this->reassignToB();
        $this->assertSame(0, MessageNotificationVisibility::unreadFor($this->a)->count());

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->booking), [
            'manager_id' => $this->a->id, 'expected_manager_id' => $this->b->id, 'reason_code' => 'staff_change',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, MessageNotificationVisibility::unreadFor($this->a)->count());
    }

    public function test_direct_booking_and_chat_authorization_stays_intact_for_former_manager(): void
    {
        $this->messageNotice($this->booking, $this->client, $this->a, 'ЗАКРЫТО');
        $this->reassignToB();
        Message::query()->create(['booking_id' => $this->booking->id, 'sender_id' => $this->client->id, 'receiver_id' => $this->b->id, 'message' => 'ЛИЧНОЕ-СООБЩЕНИЕ-БЕТА', 'is_read' => false]);

        $this->actingAs($this->a)->get(route('bookings.show', $this->booking))->assertForbidden();
        $this->actingAs($this->a)->get(route('cabinet.manager.chat', ['bookingId' => $this->booking->id]))->assertDontSee('ЛИЧНОЕ-СООБЩЕНИЕ-БЕТА');
    }
}
