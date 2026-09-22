<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E4-D3 — защитные контракты стабилизации кабинета (терминология,
 * доступные имена иконок-кнопок, ассоциация label/for, контраст
 * quick-action «В обработке», разметочные хуки адаптивных фиксов,
 * уведомление режима наблюдателя в чате администратора).
 *
 * Проверяются устойчивые контракты, а не точная разметка/цвета.
 */
class E4D3StabilizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeBooking(User $owner, ?int $managerId, string $status, array $overrides = []): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create(array_merge([
            'user_id'             => $owner->id,
            'manager_id'          => $managerId,
            'status'              => $status,
            'departure_city'      => 'Санкт-Петербург',
            'destination_country' => 'Турция',
            'destination_city'    => 'Анталья',
            'start_date'          => now()->addMonth()->toDateString(),
            'nights'              => 7,
            'adults'              => 2,
            'children'            => 0,
            'total_price'         => 100000,
            'paid_amount'         => 40000,
        ], $overrides)));
    }

    // ------------------------------------------------------------------
    // Terminology (item A/B)
    // ------------------------------------------------------------------

    public function test_manager_statistics_detail_card_and_chart_use_canonical_progress_wording(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        $this->assertStringContainsString('В обработке', $html);
        // The doughnut chart labels array must use the canonical single-status wording.
        $this->assertStringContainsString("labels: ['В обработке', 'Подтверждено', 'Завершено', 'Отменено']", $html);
    }

    public function test_manager_dashboard_chart_receives_canonical_label_directly_from_controller(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        // E4-D3: the controller now supplies 'В обработке' directly — no display-only
        // JS remap of a stale 'В работе' controller label should remain.
        $this->assertStringContainsString('В обработке', $html);
        $this->assertStringNotContainsString("'В работе' ? 'В обработке'", $html);
    }

    public function test_tourist_active_aggregate_labels_use_distinct_aggregate_wording(): void
    {
        // $activeBookings / $activeCount aggregate NEW + PROGRESS together; this is a
        // distinct concept from the single canonical PROGRESS status label and must not
        // be labeled with the bare legacy "В работе" wording, which is ambiguous against
        // the canonical "В обработке" single-status label used elsewhere on these pages.
        $tourist = $this->makeUser([Role::TOURIST]);
        $this->makeBooking($tourist, null, Booking::STATUS_NEW);

        $dashboard = $this->actingAs($tourist)->get(route('cabinet.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Активные заявки', $dashboard);
        $this->assertStringNotContainsString('>В работе<', $dashboard);

        $bookings = $this->actingAs($tourist)->get(route('cabinet.bookings'))->assertOk()->getContent();
        $this->assertStringContainsString('Активные', $bookings);
        $this->assertStringNotContainsString('>В работе<', $bookings);
    }

    public function test_manager_statistics_progress_confirmed_aggregate_uses_distinct_wording(): void
    {
        // The top summary card sums pending (progress) + confirmed bookings — a distinct
        // aggregate from the single canonical PROGRESS status shown in the detail section
        // below it, and must not reuse ambiguous bare "В работе" wording.
        $manager = $this->makeUser([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        $this->assertStringContainsString('Активные заявки', $html);
        $this->assertStringNotContainsString('>В работе<', $html);
    }

    // ------------------------------------------------------------------
    // F-10 — accessible names on icon-only controls
    // ------------------------------------------------------------------

    public function test_manager_dashboard_icon_only_row_actions_have_accessible_names(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $tourist = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Просмотреть заявку №' . $booking->id . '"', $html);
        $this->assertStringContainsString('aria-label="Открыть чат по заявке №' . $booking->id . '"', $html);
    }

    public function test_admin_users_row_actions_have_accessible_names(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $target = $this->makeUser([Role::TOURIST]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.users'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Карточка пользователя ' . e($target->name) . '"', $html);
        $this->assertStringContainsString('aria-label="Управление ролями пользователя ' . e($target->name) . '"', $html);
    }

    // ------------------------------------------------------------------
    // F-11 — label/for association
    // ------------------------------------------------------------------

    public function test_manager_profile_fields_have_associated_labels(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.profile'))->assertOk()->getContent();

        $this->assertStringContainsString('for="manager-profile-name"', $html);
        $this->assertStringContainsString('id="manager-profile-name"', $html);
        $this->assertStringContainsString('for="manager-profile-email"', $html);
        $this->assertStringContainsString('id="manager-profile-email"', $html);
    }

    public function test_admin_users_filter_fields_have_associated_labels(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.users'))->assertOk()->getContent();

        $this->assertStringContainsString('for="admin-users-search"', $html);
        $this->assertStringContainsString('id="admin-users-search"', $html);
        $this->assertStringContainsString('for="admin-users-role"', $html);
        $this->assertStringContainsString('id="admin-users-role"', $html);
    }

    // ------------------------------------------------------------------
    // F-16 — contrast fix source
    // ------------------------------------------------------------------

    public function test_cabinet_stylesheet_overrides_outline_warning_with_readable_palette_token(): void
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('.btn-outline-warning', $css);
        $this->assertMatchesRegularExpression(
            '/\.btn-outline-warning\s*\{[^}]*--bs-btn-color:\s*var\(--cabinet-warning\)/s',
            $css
        );
    }

    // ------------------------------------------------------------------
    // Booking-card lower row overlap fix
    // ------------------------------------------------------------------

    public function test_booking_card_detail_row_uses_wide_breakpoint_to_avoid_sidebar_cramped_overlap(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);
        $this->makeBooking($tourist, null, Booking::STATUS_NEW);

        $html = $this->actingAs($tourist)->get(route('cabinet.bookings'))->assertOk()->getContent();

        // Four detail columns must not force onto one row until >=1200px (col-xl-3);
        // at 992-1199px the fixed 264px cabinet sidebar makes col-md-3 (>=768px) overlap.
        $this->assertStringContainsString('col-6 col-xl-3', $html);
        $this->assertStringNotContainsString('col-6 col-md-3', $html);
    }

    // ------------------------------------------------------------------
    // Finance ₽ wrapping fix
    // ------------------------------------------------------------------

    public function test_manager_finance_money_cells_do_not_wrap(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $tourist = $this->makeUser([Role::TOURIST]);
        $this->makeBooking($tourist, $manager->id, Booking::STATUS_COMPLETED);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.finance'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<td class="text-nowrap">[\d\s]+&nbsp;?₽<\/td>|<td class="text-nowrap">[\d\s]+\s*₽<\/td>/u', $html);
    }

    // ------------------------------------------------------------------
    // Admin observer chat notice (P-09)
    // ------------------------------------------------------------------

    public function test_admin_observer_chat_shows_a_read_only_notice(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $manager = $this->makeUser([Role::MANAGER]);
        $tourist = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);

        Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $manager->id,
            'message' => 'Hello',
            'is_read' => false,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Режим просмотра', $html);
        $this->assertStringNotContainsString('data-chat-composer', $html);
    }

    public function test_admin_assigned_handler_chat_does_not_show_the_read_only_notice(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $tourist = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBooking($tourist, $admin->id, Booking::STATUS_PROGRESS);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Режим просмотра', $html);
        $this->assertStringContainsString('data-chat-composer', $html);
    }

    // ------------------------------------------------------------------
    // Console diagnostic cleanup
    // ------------------------------------------------------------------

    public function test_home_and_tours_pages_have_no_diagnostic_console_log(): void
    {
        $home = $this->get(route('home.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('console.log(', $home);

        $tours = $this->get(route('tours.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('console.log(', $tours);
        // console.error diagnostics for genuine failure paths must remain intact.
        $this->assertStringContainsString('console.error(', $tours);
    }
}
