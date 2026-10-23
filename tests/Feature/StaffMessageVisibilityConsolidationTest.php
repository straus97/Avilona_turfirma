<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-02 — unread-message counts and message deletion follow the CURRENT booking
 * assignment on every staff surface, not only on the JSON counter and the bell
 * (AOA-01-I2B G8 / I6). Only synthetic data.
 */
class StaffMessageVisibilityConsolidationTest extends TestCase
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
        $this->client = $this->userWith('tourist');
        $this->booking = $this->bookingOf($this->client, $this->a);
    }

    private function unread(Booking $booking, User $from, User $to, string $text = 'Синтетическое сообщение'): Message
    {
        return Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $from->id,
            'receiver_id' => $to->id,
            'message' => $text,
            'is_read' => false,
        ]);
    }

    private function reassignToB(): void
    {
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->booking), [
            'manager_id' => $this->b->id, 'expected_manager_id' => $this->a->id, 'reason_code' => 'workload',
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    private function chatNavBadge(string $html): ?string
    {
        return preg_match('/data-chat-nav-unread.*?<span class="menu-badge">(\d+)<\/span>/s', $html, $m) === 1 ? $m[1] : null;
    }

    public function test_current_manager_sees_unread_count_on_dashboard_and_sidebar(): void
    {
        $this->unread($this->booking, $this->client, $this->a);

        $this->actingAs($this->a)->get(route('cabinet.manager.dashboard'))
            ->assertOk()->assertViewHas('unreadMessagesCount', 1);

        $html = $this->actingAs($this->a)->get(route('cabinet.manager.profile'))->assertOk()->getContent();
        $this->assertSame('1', $this->chatNavBadge($html));
    }

    public function test_former_manager_dashboard_does_not_count_messages_of_a_reassigned_booking(): void
    {
        $this->unread($this->booking, $this->client, $this->a);
        $this->reassignToB();

        $this->actingAs($this->a)->get(route('cabinet.manager.dashboard'))
            ->assertOk()
            ->assertViewHas('unreadMessages', 0)
            ->assertViewHas('unreadMessagesCount', 0);
    }

    public function test_former_manager_sidebar_badge_does_not_count_messages_of_a_reassigned_booking(): void
    {
        $this->unread($this->booking, $this->client, $this->a);
        $this->reassignToB();

        foreach (['cabinet.manager.profile', 'cabinet.manager.chat', 'cabinet.manager.bookings'] as $route) {
            $html = $this->actingAs($this->a)->get(route($route))->assertOk()->getContent();
            $this->assertNull($this->chatNavBadge($html), $route);
        }
    }

    public function test_new_manager_counts_only_messages_addressed_to_them(): void
    {
        $this->unread($this->booking, $this->client, $this->a);
        $this->reassignToB();
        $this->unread($this->booking, $this->client, $this->b);

        $html = $this->actingAs($this->b)->get(route('cabinet.manager.profile'))->assertOk()->getContent();
        $this->assertSame('1', $this->chatNavBadge($html));
    }

    public function test_unread_counts_still_include_each_own_booking_for_admin_and_tourist(): void
    {
        $adminOwned = $this->bookingOf($this->client, $this->admin);
        $this->unread($adminOwned, $this->client, $this->admin);
        $this->unread($this->booking, $this->a, $this->client);

        $html = $this->actingAs($this->admin)->get(route('cabinet.admin.profile'))->assertOk()->getContent();
        $this->assertSame('1', $this->chatNavBadge($html));

        $this->actingAs($this->client)->get(route('cabinet.dashboard'))
            ->assertOk()->assertViewHas('unreadMessagesCount', 1);
    }

    public function test_all_unread_surfaces_agree_for_a_reassigned_booking(): void
    {
        $this->unread($this->booking, $this->client, $this->a);
        $this->reassignToB();

        $this->actingAs($this->a)->getJson(route('messages.unread-count'))->assertExactJson(['count' => 0]);
        $this->actingAs($this->a)->get(route('cabinet.manager.dashboard'))->assertViewHas('unreadMessagesCount', 0);
    }

    public function test_former_manager_cannot_delete_their_own_message_after_reassignment(): void
    {
        $message = $this->unread($this->booking, $this->a, $this->client, 'Моё прежнее сообщение');
        $this->reassignToB();

        $this->actingAs($this->a)->deleteJson(route('messages.destroy', $message))->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_current_manager_can_delete_their_own_message(): void
    {
        $message = $this->unread($this->booking, $this->a, $this->client);

        $this->actingAs($this->a)->deleteJson(route('messages.destroy', $message))->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_manager_without_the_manager_role_cannot_delete_their_old_message(): void
    {
        $message = $this->unread($this->booking, $this->a, $this->client);
        $this->a->roles()->detach();

        $this->actingAs($this->a)->deleteJson(route('messages.destroy', $message))->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_new_manager_cannot_delete_the_former_managers_message(): void
    {
        $message = $this->unread($this->booking, $this->a, $this->client);
        $this->reassignToB();

        $this->actingAs($this->b)->deleteJson(route('messages.destroy', $message))->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_admin_deletion_contract_is_unchanged(): void
    {
        $message = $this->unread($this->booking, $this->a, $this->client);

        $this->actingAs($this->admin)->deleteJson(route('messages.destroy', $message))->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_tourist_can_delete_their_own_message_and_not_a_foreign_one(): void
    {
        $own = $this->unread($this->booking, $this->client, $this->a);
        $foreign = $this->unread($this->booking, $this->a, $this->client);
        $stranger = $this->userWith('tourist');

        $this->actingAs($stranger)->deleteJson(route('messages.destroy', $own))->assertForbidden();
        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $foreign))->assertForbidden();
        $this->actingAs($this->client)->deleteJson(route('messages.destroy', $own))->assertOk();

        $this->assertDatabaseHas('messages', ['id' => $foreign->id]);
        $this->assertDatabaseMissing('messages', ['id' => $own->id]);
    }

    public function test_former_manager_deletion_attempt_leaves_notification_untouched(): void
    {
        $notice = $this->messageNotice($this->booking, $this->a, $this->client, 'Сообщение для проверки');
        $messageId = $notice->data['message_id'];
        $this->reassignToB();

        $this->actingAs($this->a)->deleteJson(route('messages.destroy', $messageId))->assertForbidden();

        $this->assertSame('Сообщение для проверки', $notice->fresh()->data['preview']);
    }

    public function test_staff_requests_to_the_legacy_tourist_booking_list_never_return_foreign_bookings(): void
    {
        $other = $this->userWith('tourist');
        $foreign = $this->bookingOf($other, $this->b, Booking::STATUS_PROGRESS, ['destination_country' => 'СинтетическаяСтрана']);

        foreach ([$this->a, $this->b, $this->admin] as $staff) {
            $response = $this->actingAs($staff)->get(route('cabinet.bookings'));
            $response->assertRedirect();
            $this->assertStringNotContainsString('СинтетическаяСтрана', (string) $response->getContent());
        }

        $this->actingAs($other)->get(route('cabinet.bookings'))->assertOk()
            ->assertSee('СинтетическаяСтрана');
        $this->actingAs($this->client)->get(route('cabinet.bookings'))->assertOk()
            ->assertDontSee('СинтетическаяСтрана');
        $this->assertNotNull($foreign->id);
    }
}
