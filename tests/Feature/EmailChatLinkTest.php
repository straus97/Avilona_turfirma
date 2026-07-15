<?php

namespace Tests\Feature;

use App\Mail\BookingStatusChanged;
use App\Mail\ManagerAssigned;
use App\Mail\NewMessageReceived;
use App\Mail\TripReminder;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Активные email-шаблоны раньше ссылались на несуществующий именованный маршрут
 * profile.chat / profile.documents и падали с RouteNotFoundException при рендеринге.
 *
 * Ссылка на чат теперь выбирается по конкретному получателю письма через
 * Booking::chatRouteFor(): владелец → cabinet.chat, текущий ответственный
 * (менеджер ИЛИ админ в manager_id) → cabinet.manager.chat, любой другой —
 * fail-closed (исключение).
 */
class EmailChatLinkTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Помощник выбора маршрута (Booking::chatRouteFor) — напрямую
    // -----------------------------------------------------------------------

    public function test_chat_route_for_owner_returns_cabinet_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBookingFor($owner);

        $this->assertSame(
            route('cabinet.chat', $booking->id),
            $booking->chatRouteFor($owner)
        );
    }

    public function test_chat_route_for_manager_assignee_returns_manager_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $this->assertSame(
            route('cabinet.manager.chat', ['bookingId' => $booking->id]),
            $booking->chatRouteFor($manager)
        );
    }

    public function test_chat_route_for_admin_assignee_returns_manager_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBookingFor($owner, $admin->id);

        // Админ, сохранённый в manager_id, ведёт заявку лично → чат менеджера.
        $this->assertSame(
            route('cabinet.manager.chat', ['bookingId' => $booking->id]),
            $booking->chatRouteFor($admin)
        );
    }

    public function test_chat_route_for_previous_assignee_throws_logic_exception_after_reassignment(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $managerA = $this->makeUser(Role::MANAGER);
        $managerB = $this->makeUser(Role::MANAGER);
        $booking  = $this->makeBookingFor($owner, $managerA->id);

        // Заявка переназначена другому менеджеру; текущий manager_id — managerB.
        $booking->manager_id = $managerB->id;
        $booking->saveQuietly();

        $this->expectException(\LogicException::class);

        // Прежний ответственный (managerA) больше не должен получать ссылку на чат.
        $booking->chatRouteFor($managerA);
    }

    // -----------------------------------------------------------------------
    // 2. Рендеринг писем
    // -----------------------------------------------------------------------

    public function test_manager_assigned_email_links_tourist_to_cabinet_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $html = (new ManagerAssigned($booking, $owner))->render();

        $this->assertStringContainsString(route('cabinet.chat', $booking->id), $html);
    }

    public function test_manager_assigned_email_links_manager_to_manager_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        $html = (new ManagerAssigned($booking, $manager))->render();

        $this->assertStringContainsString(
            route('cabinet.manager.chat', ['bookingId' => $booking->id]),
            $html
        );
    }

    public function test_status_changed_email_links_tourist_to_cabinet_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $html = (new BookingStatusChanged($booking, Booking::STATUS_PROGRESS, $owner))->render();

        $this->assertStringContainsString(route('cabinet.chat', $booking->id), $html);
    }

    public function test_status_changed_email_links_manager_to_manager_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $html = (new BookingStatusChanged($booking, Booking::STATUS_PROGRESS, $manager))->render();

        $this->assertStringContainsString(
            route('cabinet.manager.chat', ['bookingId' => $booking->id]),
            $html
        );
    }

    public function test_new_message_email_links_receiver_to_role_aware_chat(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id);

        // Получатель письма — ответственный менеджер → чат менеджера.
        $message = $this->makeMessage($booking, $owner, $manager);

        $html = (new NewMessageReceived($message))->render();

        $this->assertStringContainsString(
            route('cabinet.manager.chat', ['bookingId' => $booking->id]),
            $html
        );
    }

    public function test_trip_reminder_email_links_tourist_to_cabinet_chat_and_documents(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $html = (new TripReminder($booking, 7))->render();

        $this->assertStringContainsString(route('cabinet.chat', $booking->id), $html);
        $this->assertStringContainsString(route('cabinet.documents.personal'), $html);
    }

    // -----------------------------------------------------------------------
    // 3. Регрессия: ни одно активное письмо не ссылается на profile.chat/documents
    // -----------------------------------------------------------------------

    public function test_active_chat_emails_do_not_reference_profile_routes(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBookingFor($owner, $manager->id, Booking::STATUS_CONFIRMED);
        $message = $this->makeMessage($booking, $owner, $manager);

        $rendered = [
            (new ManagerAssigned($booking, $owner))->render(),
            (new BookingStatusChanged($booking, Booking::STATUS_PROGRESS, $owner))->render(),
            (new TripReminder($booking, 7))->render(),
            (new NewMessageReceived($message))->render(),
        ];

        foreach ($rendered as $html) {
            $this->assertStringNotContainsString('profile/chat', $html);
            $this->assertStringNotContainsString('profile/documents', $html);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $roleName, bool $active = true): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create(['is_active' => $active]);
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
            'message'     => 'Добрый день, подскажите по заявке.',
            'is_read'     => false,
        ]);
    }
}
