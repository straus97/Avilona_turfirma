<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * E3-A4 — защитные контракты редизайна кабинета менеджера.
 *
 * Проверяются устойчивые контракты (скоуп данных, честные пустые состояния,
 * канонические формулировки статуса, отсутствие N+1) — не точная разметка
 * или цвета. Глубокая проверка политик/безопасности не дублируется здесь:
 * см. BookingLifecycleAuthorizationTest, MessageParticipantAuthorizationTest,
 * ManagerClientListQueryEfficiencyTest.
 */
class ManagerCabinetE3RedesignTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function baseBookingAttributes(int $userId, ?int $managerId, string $status): array
    {
        return [
            'user_id' => $userId,
            'manager_id' => $managerId,
            'status' => $status,
            'departure_city' => 'Saint Petersburg',
            'destination_country' => 'Turkey',
            'destination_city' => 'Antalya',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => 100000,
            'paid_amount' => 0,
        ];
    }

    private function createBooking(array $attributes, \DateTimeInterface $createdAt): Booking
    {
        return Booking::withoutEvents(function () use ($attributes, $createdAt): Booking {
            $booking = new Booking();
            $booking->forceFill(array_merge($attributes, [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
            $booking->save();

            return $booking;
        });
    }

    private function makeMessage(Booking $booking, int $senderId, int $receiverId, bool $isRead): Message
    {
        return Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => 'Test message',
            'is_read' => $isRead,
        ]);
    }

    // ------------------------------------------------------------------
    // A. Dashboard
    // ------------------------------------------------------------------

    public function test_dashboard_attention_queue_excludes_other_managers_and_sorts_oldest_first(): void
    {
        $managerA = $this->createUserWithRoles([Role::MANAGER]);
        $managerB = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $newer = $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_NEW),
            now()->subDay()
        );
        $older = $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_PROGRESS),
            now()->subDays(5)
        );
        // Confirmed booking must not appear in the attention queue.
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_CONFIRMED),
            now()->subDays(10)
        );
        // Another manager's older new booking must never appear for manager A.
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerB->id, Booking::STATUS_NEW),
            now()->subDays(20)
        );

        $html = $this->actingAs($managerA)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Требует внимания', $html);

        $olderPos = strpos($html, '#' . $older->id);
        $newerPos = strpos($html, '#' . $newer->id);
        $this->assertNotFalse($olderPos);
        $this->assertNotFalse($newerPos);
        $this->assertLessThan($newerPos, $olderPos, 'Oldest attention booking must render first');
    }

    public function test_dashboard_attention_queue_honest_empty_state_when_nothing_pending(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_COMPLETED),
            now()->subDays(3)
        );

        $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))
            ->assertOk()
            ->assertSee('Нет заявок, требующих внимания');
    }

    public function test_dashboard_loads_chartjs_asset_for_status_and_bookings_charts(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('plugins/chart.js/Chart.min.js', $html);
        $this->assertStringContainsString('id="statusChart"', $html);
        $this->assertStringContainsString('id="bookingsChart"', $html);
    }

    public function test_dashboard_charts_use_bounded_height_wrapper_and_readable_count_axis(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        // Both canvases sit inside the namespaced wrapper that owns the responsive height,
        // instead of relying on Chart.js's unbounded intrinsic sizing.
        $this->assertMatchesRegularExpression(
            '/<div class="manager-dashboard-chart">\s*<canvas id="statusChart"><\/canvas>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<div class="manager-dashboard-chart">\s*<canvas id="bookingsChart"><\/canvas>/',
            $html
        );

        $this->assertStringContainsString('maintainAspectRatio: false', $html);
        $this->assertStringContainsString('responsive: true', $html);
    }

    public function test_dashboard_bookings_chart_axis_uses_bundled_chartjs_v2_syntax_for_whole_counts(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        // The bundled asset is Chart.js 2.x, which reads axis options from scales.yAxes[],
        // not the v3+ scales.y object — the latter is silently ignored by this runtime.
        $this->assertStringContainsString('yAxes:', $html);
        $this->assertStringNotContainsString('scales: {
                    y: {', $html);

        // Booking counts must render as whole numbers: begin at zero, step by 1, and
        // fall back to a tick callback that drops any non-integer value Chart.js 2.x
        // still generates, without hardcoding an upper bound.
        $this->assertStringContainsString('beginAtZero: true', $html);
        $this->assertStringContainsString('stepSize: 1', $html);
        $this->assertStringContainsString('Number.isInteger(value)', $html);
        $this->assertStringNotContainsString('max: 2', $html);
    }

    public function test_dashboard_status_chart_uses_established_progress_wording(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        // The status doughnut must display the same "В обработке" wording used
        // elsewhere on this dashboard. The controller now supplies the canonical
        // label directly (E4-D3), so no display-only JS remap is needed — the
        // json_encode()'d chart labels carry the \uXXXX-escaped form.
        $chartScriptEnd = strpos($html, 'new Chart(statusCtx');
        $this->assertNotFalse($chartScriptEnd);

        $labelSnippet = substr($html, $chartScriptEnd, 400);
        $this->assertStringContainsString(json_encode('В обработке'), $labelSnippet);
        $this->assertStringNotContainsString("'В работе' ? 'В обработке'", $html);
    }

    // ------------------------------------------------------------------
    // B. Booking queue
    // ------------------------------------------------------------------

    public function test_bookings_queue_excludes_foreign_manager_bookings(): void
    {
        $managerA = $this->createUserWithRoles([Role::MANAGER]);
        $managerB = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $own = $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_NEW),
            now()
        );
        $foreign = $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerB->id, Booking::STATUS_NEW),
            now()
        );

        $html = $this->actingAs($managerA)->get(route('cabinet.manager.bookings'))->assertOk()->getContent();

        $this->assertStringContainsString('#' . $own->id, $html);
        $this->assertStringNotContainsString('#' . $foreign->id, $html);
    }

    public function test_bookings_queue_shows_unread_chat_badge_with_correct_count(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $booking = $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
            now()
        );

        $this->makeMessage($booking, $client->id, $manager->id, false);
        $this->makeMessage($booking, $client->id, $manager->id, false);
        $this->makeMessage($booking, $client->id, $manager->id, true); // read — must not count

        $html = $this->actingAs($manager)->get(route('cabinet.manager.bookings'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/rounded-pill[^"]*">\s*2\s*</', $html);
    }

    public function test_bookings_queue_unread_query_count_does_not_grow_with_booking_count(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $countQueries = function () use ($manager): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            try {
                $this->actingAs($manager)->get(route('cabinet.manager.bookings'))->assertOk();
                $log = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }

            return collect($log)
                ->filter(fn (array $entry): bool => (bool) preg_match('/from\s+[`"]messages[`"]/i', $entry['query']))
                ->count();
        };

        $client = $this->createUserWithRoles([Role::TOURIST]);
        $bookingOne = $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
            now()
        );
        $this->makeMessage($bookingOne, $client->id, $manager->id, false);

        $countForOneBooking = $countQueries();

        for ($i = 0; $i < 5; $i++) {
            $booking = $this->createBooking(
                $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
                now()
            );
            $this->makeMessage($booking, $client->id, $manager->id, false);
        }

        $countForSixBookings = $countQueries();

        $this->assertSame($countForOneBooking, $countForSixBookings);
        // One grouped query for this slice's own unread aggregate, plus one
        // pre-existing fallback query the sidebar partial always runs for the
        // unread-messages badge — neither one grows with booking count.
        $this->assertLessThanOrEqual(2, $countForOneBooking);
    }

    // ------------------------------------------------------------------
    // C. Chat thread list
    // ------------------------------------------------------------------

    public function test_chat_thread_list_unread_counts_are_correct(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $clientA = $this->createUserWithRoles([Role::TOURIST]);
        $clientB = $this->createUserWithRoles([Role::TOURIST]);

        $bookingA = $this->createBooking(
            $this->baseBookingAttributes($clientA->id, $manager->id, Booking::STATUS_PROGRESS),
            now()
        );
        $bookingB = $this->createBooking(
            $this->baseBookingAttributes($clientB->id, $manager->id, Booking::STATUS_PROGRESS),
            now()
        );

        $this->makeMessage($bookingA, $clientA->id, $manager->id, false);
        $this->makeMessage($bookingA, $clientA->id, $manager->id, false);
        $this->makeMessage($bookingB, $clientB->id, $manager->id, false);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.chat'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/rounded-pill">2</', $html);
        $this->assertMatchesRegularExpression('/rounded-pill">1</', $html);
    }

    public function test_chat_thread_list_query_count_does_not_grow_with_booking_count(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $countQueries = function () use ($manager): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            try {
                $this->actingAs($manager)->get(route('cabinet.manager.chat'))->assertOk();
                $log = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }

            return collect($log)
                ->filter(fn (array $entry): bool => (bool) preg_match('/from\s+[`"]messages[`"]/i', $entry['query']))
                ->count();
        };

        $client = $this->createUserWithRoles([Role::TOURIST]);
        $bookingOne = $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_PROGRESS),
            now()
        );
        $this->makeMessage($bookingOne, $client->id, $manager->id, false);

        $countForOneBooking = $countQueries();

        for ($i = 0; $i < 5; $i++) {
            $booking = $this->createBooking(
                $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_PROGRESS),
                now()
            );
            $this->makeMessage($booking, $client->id, $manager->id, false);
        }

        $countForSixBookings = $countQueries();

        $this->assertSame($countForOneBooking, $countForSixBookings);
        // One grouped query for this slice's own unread aggregate, plus one
        // pre-existing fallback query the sidebar partial always runs for the
        // unread-messages badge — neither one grows with booking count.
        $this->assertLessThanOrEqual(2, $countForOneBooking);
    }

    // ------------------------------------------------------------------
    // D. Sidebar
    // ------------------------------------------------------------------

    public function test_sidebar_pending_bookings_badge_reflects_real_scoped_count(): void
    {
        $managerA = $this->createUserWithRoles([Role::MANAGER]);
        $managerB = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_NEW),
            now()
        );
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_PROGRESS),
            now()
        );
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerA->id, Booking::STATUS_COMPLETED),
            now()
        );
        // Belongs to another manager — must not inflate manager A's badge.
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $managerB->id, Booking::STATUS_NEW),
            now()
        );

        $html = $this->actingAs($managerA)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/menu-badge">\s*2\s*</', $html);
    }

    public function test_sidebar_pending_bookings_badge_hidden_when_zero(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/Мои заявки[\s\S]{0,80}menu-badge/', $html);
    }

    // ------------------------------------------------------------------
    // E. Statistics — canonical status text, honest revenue label, charts
    // ------------------------------------------------------------------

    public function test_statistics_uses_canonical_status_labels_and_honest_revenue_label(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        // One booking per status so each one's status-badge (recent
        // activities list) renders the canonical wording from
        // Booking::availableStatuses(), not an invented label.
        foreach (Booking::availableStatuses() as $status => $label) {
            $this->createBooking(
                $this->baseBookingAttributes($client->id, $manager->id, $status),
                now()
            );
        }

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        foreach (Booking::availableStatuses() as $label) {
            $this->assertStringContainsString($label, $html);
        }

        // Revenue label reflects what is actually summed (completed bookings'
        // total price), not an ambiguous personal-income framing.
        $this->assertStringContainsString('Выручка (завершено)', $html);
        $this->assertStringNotContainsString('Общий доход', $html);
    }

    public function test_statistics_loads_chartjs_asset(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        $this->assertStringContainsString('plugins/chart.js/Chart.min.js', $html);
        $this->assertStringContainsString('id="statusChart"', $html);
        $this->assertStringContainsString('id="monthlyChart"', $html);
    }

    public function test_statistics_charts_use_bounded_height_wrapper(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        // Both canvases sit inside the namespaced statistics wrapper that owns the
        // responsive height, instead of relying on Chart.js's unbounded intrinsic sizing.
        $this->assertMatchesRegularExpression(
            '/<div class="manager-statistics-chart">\s*<canvas id="statusChart"><\/canvas>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<div class="manager-statistics-chart">\s*<canvas id="monthlyChart"><\/canvas>/',
            $html
        );

        $this->assertStringContainsString('maintainAspectRatio: false', $html);
        $this->assertStringContainsString('responsive: true', $html);
    }

    public function test_statistics_monthly_chart_axis_uses_bundled_chartjs_v2_syntax_for_whole_counts(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        // The bundled asset is Chart.js 2.x, which reads axis options from scales.yAxes[],
        // not the v3+ scales.y object — the latter is silently ignored by this runtime.
        $this->assertStringContainsString('yAxes:', $html);
        $this->assertStringNotContainsString('scales: {
                    y: {', $html);

        // Booking counts must render as whole numbers: begin at zero, step by 1, and
        // fall back to a tick callback that drops any non-integer value Chart.js 2.x
        // still generates, without hardcoding an upper bound.
        $this->assertStringContainsString('beginAtZero: true', $html);
        $this->assertStringContainsString('stepSize: 1', $html);
        $this->assertStringContainsString('Number.isInteger(value)', $html);
        $this->assertStringNotContainsString('max: 2', $html);
    }

    public function test_statistics_revenue_card_amount_cannot_wrap(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(
            array_merge(
                $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_COMPLETED),
                ['total_price' => 433750]
            ),
            now()
        );

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        // A dedicated wrapper hook lets the card absorb a long formatted amount
        // without growing the shared stat-card component, and the amount itself
        // is joined with non-breaking spaces so it can never wrap onto two lines.
        $this->assertStringContainsString('manager-statistics-revenue-card', $html);
        $this->assertStringContainsString("433\u{00A0}750\u{00A0}₽", $html);
    }

    // ------------------------------------------------------------------
    // E4-D2 — адаптивная стабилизация (F-04, F-05, F-07)
    // ------------------------------------------------------------------

    public function test_money_stat_cards_opt_into_container_aware_sizing(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $financeHtml = $this->actingAs($manager)->get(route('cabinet.manager.finance'))->assertOk()->getContent();
        $this->assertSame(3, substr_count($financeHtml, 'stat-card__value--money'));

        $statisticsHtml = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($statisticsHtml, 'stat-card__value--money'));

        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);
        preg_match('/\.stat-card__value--money\s*\{([^}]*)\}/s', $css, $rule);
        $this->assertNotEmpty($rule, 'Could not locate the .stat-card__value--money rule.');
        $this->assertStringContainsString('white-space: nowrap;', $rule[1]);
        $this->assertStringContainsString('cqw', $rule[1], 'Money size must follow the card width, not the viewport.');
    }

    public function test_dashboard_and_statistics_stat_cards_use_two_columns_below_xl(): void
    {
        foreach (['dashboard', 'statistics'] as $view) {
            $source = file_get_contents(resource_path("views/manager/{$view}.blade.php"));
            $this->assertNotFalse($source);

            $this->assertStringContainsString('col-md-6 col-xl-3', $source, $view);
            $this->assertDoesNotMatchRegularExpression(
                '/<div class="col-md-3[ "]/',
                $source,
                "{$view}: a bare col-md-3 forces four ~150px stat-cards from 768px and the icon overlaps the label."
            );
        }
    }

    public function test_bookings_price_cannot_split_between_digit_groups_and_currency(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(
            array_merge(
                $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_PROGRESS),
                ['total_price' => 2450000]
            ),
            now()
        );

        $html = $this->actingAs($manager)->get(route('cabinet.manager.bookings'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<strong class="text-nowrap">\s*2 450 000 ₽\s*<\/strong>/u',
            $html
        );
    }

    public function test_profile_email_can_break_inside_its_card(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.profile'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<span class="fw-bold text-break[^"]*">\s*' . preg_quote($manager->email, '/') . '\s*<\/span>/',
            $html
        );
    }

    // ------------------------------------------------------------------
    // F. Clients — scoping remains intact
    // ------------------------------------------------------------------

    public function test_clients_list_excludes_clients_of_other_managers(): void
    {
        $managerA = $this->createUserWithRoles([Role::MANAGER]);
        $managerB = $this->createUserWithRoles([Role::MANAGER]);
        $ownClient = $this->createUserWithRoles([Role::TOURIST]);
        $foreignClient = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(
            $this->baseBookingAttributes($ownClient->id, $managerA->id, Booking::STATUS_NEW),
            now()
        );
        $this->createBooking(
            $this->baseBookingAttributes($foreignClient->id, $managerB->id, Booking::STATUS_NEW),
            now()
        );

        $html = $this->actingAs($managerA)->get(route('cabinet.manager.clients'))->assertOk()->getContent();

        $this->assertStringContainsString($ownClient->email, $html);
        $this->assertStringNotContainsString($foreignClient->email, $html);
    }

    // ------------------------------------------------------------------
    // G. Quick links point at existing shared booking/chat routes
    // ------------------------------------------------------------------

    public function test_dashboard_quick_actions_point_to_existing_shared_routes(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('bookings.create') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.chat') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.statistics') . '"', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.bookings') . '"', $html);
    }

    // ------------------------------------------------------------------
    // H. E3-A6-A — dashboard/query polish
    // ------------------------------------------------------------------

    /**
     * Extracts the numeric "data: [...]" series rendered for the statistics
     * monthly bar chart, in month order (index 0 = January).
     *
     * @return array<int, int>
     */
    private function extractStatisticsMonthlyChartData(string $html): array
    {
        $this->assertMatchesRegularExpression(
            "/label: 'Заявок',\\s*data: \\[(.*?)\\]/s",
            $html
        );

        preg_match("/label: 'Заявок',\\s*data: \\[(.*?)\\]/s", $html, $matches);

        return collect(explode(',', $matches[1]))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->map(fn (string $value): int => (int) $value)
            ->values()
            ->all();
    }

    /**
     * Extracts the numeric "data: [...]" series rendered for the dashboard
     * bookings-dynamics bar chart, in chronological (oldest-first) order.
     *
     * @return array<int, int>
     */
    private function extractDashboardBookingsChartData(string $html): array
    {
        $chartScriptStart = strpos($html, 'bookingsCtx');
        $this->assertNotFalse($chartScriptStart);
        $snippet = substr($html, $chartScriptStart);

        $this->assertMatchesRegularExpression("/data:\\s*\\[(.*?)\\]/s", $snippet);
        preg_match("/data:\\s*\\[(.*?)\\]/s", $snippet, $matches);

        return collect(explode(',', $matches[1]))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->map(fn (string $value): int => (int) $value)
            ->values()
            ->all();
    }

    public function test_statistics_monthly_breakdown_excludes_prior_year_bookings(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $currentYear = (int) date('Y');

        // Current-year booking in March must be counted in the March bucket.
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
            \Carbon\Carbon::create($currentYear, 3, 15, 12)
        );
        // Prior-year booking in the same calendar month must never leak into
        // the current-year monthly breakdown.
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
            \Carbon\Carbon::create($currentYear - 1, 3, 20, 12)
        );

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        $monthly = $this->extractStatisticsMonthlyChartData($html);

        $this->assertCount(12, $monthly);
        $this->assertSame(1, $monthly[2], 'March (index 2) must reflect only the current-year booking.');

        $othersSum = array_sum($monthly) - $monthly[2];
        $this->assertSame(0, $othersSum, 'No other month should contain bookings.');
    }

    public function test_statistics_zero_data_renders_valid_twelve_month_zero_series(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.statistics'))->assertOk()->getContent();

        $monthly = $this->extractStatisticsMonthlyChartData($html);

        $this->assertCount(12, $monthly);
        $this->assertSame(array_fill(0, 12, 0), $monthly);
        $this->assertStringContainsString('0', $html);
    }

    public function test_dashboard_zero_data_renders_valid_six_month_zero_series(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();

        $bookingsChart = $this->extractDashboardBookingsChartData($html);

        $this->assertCount(6, $bookingsChart);
        $this->assertSame(array_fill(0, 6, 0), $bookingsChart);
    }

    public function test_dashboard_reuses_precomputed_counts_and_avoids_duplicate_sidebar_queries(): void
    {
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $client = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_NEW),
            now()
        );
        $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_PROGRESS),
            now()
        );
        $confirmedBooking = $this->createBooking(
            $this->baseBookingAttributes($client->id, $manager->id, Booking::STATUS_CONFIRMED),
            now()
        );
        $this->makeMessage($confirmedBooking, $client->id, $manager->id, false);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $html = $this->actingAs($manager)->get(route('cabinet.manager.dashboard'))->assertOk()->getContent();
            $log = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        // Shape of the sidebar's fallback "pending bookings" COUNT: a bookings
        // COUNT scoped by manager and an IN (...) status list. If the sidebar's
        // fallback still ran alongside the dashboard's own identical query,
        // this shape would appear twice.
        $pendingCountQueries = collect($log)->filter(function (array $entry): bool {
            $sql = strtolower($entry['query']);

            return str_contains($sql, 'select count(*)')
                && str_contains($sql, 'from "bookings"')
                && str_contains($sql, 'manager_id')
                && str_contains($sql, ' in (');
        });

        // Shape of the sidebar's fallback "unread messages" COUNT: a messages
        // COUNT scoped by receiver_id and is_read = false.
        $unreadCountQueries = collect($log)->filter(function (array $entry): bool {
            $sql = strtolower($entry['query']);

            return str_contains($sql, 'select count(*)')
                && str_contains($sql, 'from "messages"')
                && str_contains($sql, 'receiver_id')
                && str_contains($sql, 'is_read');
        });

        $this->assertCount(
            1,
            $pendingCountQueries,
            'Pending bookings COUNT must run exactly once per dashboard load, not once for the controller and once for the sidebar fallback.'
        );
        $this->assertCount(
            1,
            $unreadCountQueries,
            'Unread messages COUNT must run exactly once per dashboard load, not once for the controller and once for the sidebar fallback.'
        );

        // Badge values still correctly reflect the scoped counts after reuse.
        $this->assertMatchesRegularExpression('/menu-badge">\s*2\s*</', $html);
    }
}
