<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BonusAccount;
use App\Models\BonusTransaction;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * E3-A2 — защитные контракты редизайна туристического кабинета.
 *
 * Проверяются устойчивые контракты (наличие данных/маршрутов/полей форм и
 * честность плейсхолдерных разделов), а не точная разметка, цвета или
 * пиксельные размеры. Безопасность владения по-прежнему покрывается
 * профильными тестами (PersonalDocumentSecurityTest, MessagePollingIsolationTest
 * и т. д.) — здесь она не дублируется.
 */
class TouristCabinetE3RedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    // ------------------------------------------------------------------
    // A. Dashboard
    // ------------------------------------------------------------------

    public function test_dashboard_shows_three_summary_counts_and_honest_empty_state(): void
    {
        $tourist = $this->makeTourist();

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'))->assertOk();

        // Три сводных показателя туриста.
        $response->assertSee('Всего заявок');
        $response->assertSee('В работе');
        $response->assertSee('Завершённых поездок');

        // Нет заявок — честный пустой стейт с ведущим действием, без выдуманной поездки.
        $response->assertSee(route('bookings.create'), false);
        $response->assertDontSee('linear-gradient(135deg, #667eea', false);
    }

    public function test_dashboard_highlights_upcoming_confirmed_trip(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $trip = $this->makeBooking($tourist, $manager->id, Booking::STATUS_CONFIRMED, now()->addDays(20));

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'))->assertOk();

        $response->assertSee('Ближайшая поездка');
        $response->assertSee($trip->destination_country);
        $response->assertSee(route('bookings.show', $trip->id), false);
    }

    /**
     * E4-D2 (F-08): шапка карточки заявки — переносимая строка с колонкой
     * названия min-width:0, а не жёсткий d-flex justify-content-between, из-за
     * которого бейдж статуса вылезал за край карточки на 992-1200px.
     */
    public function test_dashboard_booking_card_header_wraps_status_badge_instead_of_overflowing(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);

        $html = $this->actingAs($tourist)->get(route('cabinet.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('booking-card__head', $html);
        $this->assertStringContainsString('booking-card__title', $html);

        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression('/\.booking-card__head\s*\{[^}]*flex-wrap:\s*wrap;/s', $css);
        $this->assertMatchesRegularExpression('/\.booking-card__title\s*\{[^}]*min-width:\s*0;/s', $css);
    }

    // ------------------------------------------------------------------
    // B. Список заявок
    // ------------------------------------------------------------------

    public function test_bookings_list_keeps_filter_inputs_and_get_semantics(): void
    {
        $tourist = $this->makeTourist();
        $this->makeBooking($tourist, null, Booking::STATUS_NEW);

        $response = $this->actingAs($tourist)
            ->get(route('cabinet.bookings', [
                'status' => 'new',
                'country' => 'Turk',
                'date_from' => '2026-01-01',
            ]))
            ->assertOk();

        // Поля фильтра сохраняют GET-имена.
        $response->assertSee('name="status"', false);
        $response->assertSee('name="country"', false);
        $response->assertSee('name="date_from"', false);

        // Значения активного фильтра видны обратно пользователю.
        $response->assertSee('value="Turk"', false);
        $response->assertSee('value="2026-01-01"', false);

        // Сброс использует существующую GET-семантику (базовый маршрут без query).
        $response->assertSee('href="' . route('cabinet.bookings') . '"', false);
    }

    public function test_empty_filtered_result_differs_from_empty_account(): void
    {
        $tourist = $this->makeTourist();
        $this->makeBooking($tourist, null, Booking::STATUS_NEW);

        // Заявки есть, но фильтр по статусу ничего не даёт.
        $filtered = $this->actingAs($tourist)
            ->get(route('cabinet.bookings', ['status' => 'cancelled']))
            ->assertOk();
        $filtered->assertSee('Под выбранные фильтры ничего не нашлось');

        // Аккаунт без заявок — другой текст.
        $emptyTourist = $this->makeTourist();
        $empty = $this->actingAs($emptyTourist)->get(route('cabinet.bookings'))->assertOk();
        $empty->assertSee('У вас пока нет заявок');
        $empty->assertDontSee('Под выбранные фильтры ничего не нашлось');
    }

    // ------------------------------------------------------------------
    // C. Избранное — честный стейт, маршрут жив
    // ------------------------------------------------------------------

    public function test_wishlist_route_renders_without_mock_product_grid(): void
    {
        $tourist = $this->makeTourist();

        $response = $this->actingAs($tourist)->get(route('cabinet.wishlist'))->assertOk();

        $response->assertSee('в разработке');
        $response->assertDontSee('via.placeholder.com', false);
        $response->assertDontSee('45 000');
        $response->assertDontSee('@for');
    }

    // ------------------------------------------------------------------
    // D. Бонусы — реальные данные, без фейкового реферального результата
    // ------------------------------------------------------------------

    public function test_bonus_page_shows_real_balance_and_transactions(): void
    {
        $tourist = $this->makeTourist();
        $account = BonusAccount::create([
            'user_id' => $tourist->id,
            'balance' => 1200,
            'level' => 'silver',
            'total_earned' => 3000,
            'total_spent' => 1800,
            'referral_code' => 'TESTCODE',
        ]);
        BonusTransaction::create([
            'bonus_account_id' => $account->id,
            'type' => 'earn',
            'amount' => 500,
            'reason' => 'Начисление за тур',
            'balance_after' => 1200,
        ]);

        $response = $this->actingAs($tourist)->get(route('cabinet.bonus'))->assertOk();

        $response->assertSee('1200');
        $response->assertSee('Начисление за тур');

        // Никаких выдуманных реферальных счётчиков/наград и мёртвой /ref/-ссылки.
        $response->assertDontSee('Приглашено друзей');
        $response->assertDontSee('/ref/', false);
        $response->assertDontSee('500 баллов за друга');
    }

    public function test_bonus_page_renders_for_account_without_transactions(): void
    {
        $tourist = $this->makeTourist();

        $this->actingAs($tourist)->get(route('cabinet.bonus'))
            ->assertOk()
            ->assertSee('Операций по бонусному счёту пока не было');
    }

    // ------------------------------------------------------------------
    // E. Профиль / Настройки — общий флэш, контракты действий
    // ------------------------------------------------------------------

    public function test_profile_update_success_shows_single_shared_flash(): void
    {
        $tourist = $this->makeTourist();

        $response = $this->actingAs($tourist)
            ->from(route('cabinet.profile'))
            ->patch(route('cabinet.profile.update'), [
                'name' => 'Новое Имя',
                'email' => $tourist->email,
            ])
            ->assertRedirect(route('cabinet.profile'));

        $page = $this->actingAs($tourist)->get(route('cabinet.profile'))->assertOk()->getContent();

        // Сообщение об успехе рендерится ровно один раз общей оболочкой.
        $this->assertSame(1, substr_count($page, 'Профиль успешно обновлен!'));
        $this->assertStringContainsString('cabinet-flash', $page);
    }

    public function test_profile_page_keeps_action_route_and_method_contracts(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->actingAs($tourist)->get(route('cabinet.profile'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('cabinet.profile.update') . '"', $html);
        $this->assertMatchesRegularExpression('/name="_method"\s+value="PATCH"/', $html);
        $this->assertStringContainsString('action="' . route('cabinet.profile.update-passport') . '"', $html);
        $this->assertStringContainsString('action="' . route('cabinet.profile.upload-avatar') . '"', $html);
    }

    public function test_settings_page_keeps_security_notification_and_danger_contracts(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->actingAs($tourist)->get(route('cabinet.settings'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('password.update') . '"', $html);
        $this->assertStringContainsString('action="' . route('cabinet.settings.notifications') . '"', $html);
        $this->assertStringContainsString('action="' . route('cabinet.settings.destroy-account') . '"', $html);
        $this->assertMatchesRegularExpression('/name="_method"\s+value="DELETE"/', $html);

        // Переключатель напоминаний сохраняет порядок атрибутов id -> name -> checked.
        $this->assertMatchesRegularExpression('/id="tripReminders"[^>]*name="trip_reminders"[^>]*checked/', $html);

        // 2FA остаётся выключенной (не реализована в этом срезе).
        $this->assertMatchesRegularExpression('/Двухфакторная аутентификация[\s\S]*?Скоро/', $html);
    }

    public function test_settings_does_not_double_render_status_flash(): void
    {
        $tourist = $this->makeTourist();

        $page = $this->actingAs($tourist)
            ->withSession(['status' => 'Пароль успешно изменён.'])
            ->get(route('cabinet.settings'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($page, 'Пароль успешно изменён.'));
    }

    // ------------------------------------------------------------------
    // F. Документы — представительный рендер
    // ------------------------------------------------------------------

    public function test_personal_documents_page_renders_with_upload_contract(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->actingAs($tourist)->get(route('cabinet.documents.personal'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('cabinet.documents.personal.upload') . '"', $html);
        $this->assertStringContainsString('Документов пока нет', $html);
    }

    public function test_booking_documents_page_renders(): void
    {
        $tourist = $this->makeTourist();

        $this->actingAs($tourist)->get(route('cabinet.documents.bookings'))
            ->assertOk()
            ->assertSee('Документы по заявкам');
    }

    // ------------------------------------------------------------------
    // G. Чат — контракты polling / store сохранены
    // ------------------------------------------------------------------

    public function test_chat_page_retains_polling_and_store_route_contract(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);
        Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $manager->id,
            'receiver_id' => $tourist->id,
            'message' => 'Здравствуйте!',
            'is_read' => false,
        ]);

        $html = $this->actingAs($tourist)
            ->get(route('cabinet.chat', $booking->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('action="' . route('messages.store') . '"', $html);
        // Опрос по-прежнему идёт через messages.index с прежней каденцией 5000 мс;
        // реализация вынесена в общий модуль public/js/cabinet-chat.js, а страница
        // передаёт контракт через data-атрибуты прогрессивного улучшения.
        $this->assertStringContainsString('data-chat-messages-url="' . route('messages.index') . '"', $html);
        $this->assertStringContainsString('data-chat-poll-ms="5000"', $html);
        $this->assertStringContainsString('js/cabinet-chat.js', $html);
        $this->assertStringContainsString('name="booking_id"', $html);
        $this->assertStringContainsString('name="receiver_id"', $html);
        $this->assertStringContainsString('id="chatMessages"', $html);
        $this->assertStringContainsString('data-chat-messages', $html);
    }

    public function test_chat_without_bookings_shows_empty_state(): void
    {
        $tourist = $this->makeTourist();

        $this->actingAs($tourist)->get(route('cabinet.chat'))
            ->assertOk()
            ->assertSee('Пока не с кем переписываться');
    }

    // ------------------------------------------------------------------
    // H. Сайдбар — бейдж непрочитанных консистентен между страницами
    // ------------------------------------------------------------------

    public function test_unread_message_badge_is_consistent_across_tourist_pages(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);
        Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $manager->id,
            'receiver_id' => $tourist->id,
            'message' => 'Непрочитанное',
            'is_read' => false,
        ]);

        foreach ([
            route('cabinet.dashboard'),
            route('cabinet.bookings'),
            route('cabinet.documents.personal'),
            route('cabinet.settings'),
        ] as $url) {
            $html = $this->actingAs($tourist)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '/menu-badge">\s*1\s*</',
                $html,
                "Бейдж непрочитанных не отрисован на {$url}"
            );
        }
    }

    // ------------------------------------------------------------------
    // I. Полировка после статического ревью (E3-A2)
    // ------------------------------------------------------------------

    /** Fix #1 — имя документа не попадает в JS-источник inline-обработчика. */
    public function test_document_delete_confirm_does_not_embed_document_name_in_javascript(): void
    {
        Storage::fake('local');

        $tourist = $this->makeTourist();
        $hostileName = 'ZZHOSTILEZZ "; alert(1)//<img src=x>';

        UserDocument::query()->create([
            'user_id' => $tourist->id,
            'name' => $hostileName,
            'document_type' => 'passport',
            'file_path' => 'documents/personal/hostile.pdf',
            'file_type' => 'pdf',
            'file_size' => 10,
        ]);

        $html = $this->actingAs($tourist)
            ->get(route('cabinet.documents.personal'))
            ->assertOk()
            ->getContent();

        // Статичное подтверждение без интерполяции имени.
        $this->assertStringContainsString('Удалить документ? Это действие нельзя отменить.', $html);

        // Ни один inline-обработчик не содержит имя документа.
        preg_match_all('/on\w+="[^"]*"/', $html, $handlers);
        foreach ($handlers[0] as $handler) {
            $this->assertStringNotContainsString('ZZHOSTILEZZ', $handler);
        }

        // Имя по-прежнему отображается в обычном экранированном HTML.
        $this->assertStringContainsString('ZZHOSTILEZZ', $html);
    }

    /** Fix #2 — искажённый date_from не роняет рендер активных фильтров. */
    public function test_malformed_date_from_does_not_crash_bookings_filter_presentation(): void
    {
        $tourist = $this->makeTourist();
        $this->makeBooking($tourist, null, Booking::STATUS_NEW);

        $malformed = 'не-дата-<img>';

        $html = $this->actingAs($tourist)
            ->get(route('cabinet.bookings', ['country' => 'Турция', 'date_from' => $malformed]))
            ->assertOk()
            ->getContent();

        // Искажённое значение деградирует безопасно — показывается экранированным в чипе.
        $this->assertStringContainsString(e($malformed), $html);
        $this->assertStringContainsString('name="date_from"', $html);

        // Но невалидная строка НЕ попадает в value нативного input[type=date].
        preg_match('/<input[^>]*id="filter-date-from"[^>]*>/', $html, $dateInput);
        $this->assertNotEmpty($dateInput);
        $this->assertStringContainsString('type="date"', $dateInput[0]);
        $this->assertStringContainsString('value=""', $dateInput[0]);
        $this->assertStringNotContainsString('не-дата', $dateInput[0]);

        // Валидное значение по-прежнему форматируется как dd.mm.YYYY и остаётся в input.
        $valid = $this->actingAs($tourist)
            ->get(route('cabinet.bookings', ['date_from' => '2026-01-05']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('05.01.2026', $valid);
        preg_match('/<input[^>]*id="filter-date-from"[^>]*>/', $valid, $validInput);
        $this->assertNotEmpty($validInput);
        $this->assertStringContainsString('value="2026-01-05"', $validInput[0]);
    }

    /** Browser-QA #1 — открытие переписки не оставляет устаревший бейдж непрочитанных. */
    public function test_opening_chat_thread_clears_stale_unread_badge_in_same_response(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);

        $m1 = Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $manager->id,
            'receiver_id' => $tourist->id,
            'message' => 'Первое непрочитанное',
            'is_read' => false,
        ]);
        $m2 = Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $manager->id,
            'receiver_id' => $tourist->id,
            'message' => 'Второе непрочитанное',
            'is_read' => false,
        ]);

        $html = $this->actingAs($tourist)
            ->get(route('cabinet.chat', $booking->id))
            ->assertOk()
            ->getContent();

        // Сообщения помечены прочитанными в БД.
        $this->assertTrue($m1->fresh()->is_read);
        $this->assertTrue($m2->fresh()->is_read);

        // Сайдбар в этом же ответе не показывает устаревший счётчик.
        $this->assertStringNotContainsString('menu-badge', $html);

        // Карточка открытой переписки не показывает устаревший бейдж «2».
        $this->assertStringNotContainsString('rounded-pill">2<', $html);
        $this->assertDoesNotMatchRegularExpression('/badge[^"]*rounded-pill">\s*[12]\s*</', $html);
    }

    /** Fix #3 — дробные бонусные значения не усечены. */
    public function test_bonus_page_preserves_decimal_values(): void
    {
        $tourist = $this->makeTourist();
        $account = BonusAccount::create([
            'user_id' => $tourist->id,
            'balance' => 1200.50,
            'level' => 'silver',
            'total_earned' => 3000.75,
            'total_spent' => 1800.25,
            'referral_code' => 'FRACCODE',
        ]);
        BonusTransaction::create([
            'bonus_account_id' => $account->id,
            'type' => 'earn',
            'amount' => 500.25,
            'reason' => 'Начисление за тур',
            'balance_after' => 1200.50,
        ]);

        $response = $this->actingAs($tourist)->get(route('cabinet.bonus'))->assertOk();

        $response->assertSee('1200.50');
        $response->assertSee('3000.75');
        $response->assertSee('1800.25');
        $response->assertSee('500.25');
    }

    /** Fix #4 — неподтверждённая формулировка о начислении менеджером убрана. */
    public function test_bonus_page_has_neutral_copy_without_manager_accrual_claim(): void
    {
        $tourist = $this->makeTourist();

        $response = $this->actingAs($tourist)->get(route('cabinet.bonus'))->assertOk();

        $response->assertDontSee('начисляет и списывает менеджер');
        $response->assertDontSee('при оформлении туров');
        $response->assertSee(route('cabinet.chat'), false);
    }

    /** Fix #5 — выбор вложения в чате доступен с клавиатуры. */
    public function test_chat_attachment_input_is_keyboard_reachable(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);

        $html = $this->actingAs($tourist)
            ->get(route('cabinet.chat', $booking->id))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="attachmentInput"[^>]*>/', $html);
        preg_match('/<input[^>]*id="attachmentInput"[^>]*>/', $html, $input);
        $this->assertStringContainsString('type="file"', $input[0]);
        $this->assertStringContainsString('name="attachment"', $input[0]);
        $this->assertStringNotContainsString('d-none', $input[0]);

        // Видимый связанный триггер-label.
        $this->assertMatchesRegularExpression('/<label[^>]*for="attachmentInput"/', $html);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeTourist(): User
    {
        return $this->makeUser(Role::TOURIST);
    }

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

    private function makeBooking(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW,
        ?\DateTimeInterface $startDate = null
    ): Booking {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create([
            'user_id' => $owner->id,
            'manager_id' => $managerId,
            'status' => $status,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'start_date' => ($startDate ?? now()->addDays(30))->format('Y-m-d'),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
        ]));
    }
}
