<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E3 — кросс-ролевой срез «непрерывность чата».
 *
 * PHPUnit не исполняет реальный браузерный рантайм (переключение фрагмента,
 * localStorage-черновики, history API) — это уходит в browser-QA. Здесь
 * защищаются СТРУКТУРНЫЕ контракты, на которые опирается общий модуль
 * public/js/cabinet-chat.js:
 *   - прогрессивные ссылки веток (настоящие <a href> с ролевым URL);
 *   - наличие/отсутствие композера ровно там, где роль реально может писать;
 *   - общий data-контракт улучшения на всех трёх поверхностях;
 *   - минимальная идентичность пользователя в общей оболочке для неймспейса
 *     черновиков и очистки при выходе;
 *   - серверная авторизация/скоуп не ослаблены AJAX-переключением.
 */
class CabinetChatContinuityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    // ------------------------------------------------------------------
    // A. Прогрессивные ссылки веток на всех трёх поверхностях
    // ------------------------------------------------------------------

    public function test_tourist_chat_renders_progressive_thread_link(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-thread', $html);
        $this->assertStringContainsString('href="' . route('cabinet.chat', $booking->id) . '"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function test_manager_chat_renders_progressive_thread_link(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-thread', $html);
        $this->assertStringContainsString('href="' . route('cabinet.manager.chat', ['bookingId' => $booking->id]) . '"', $html);
    }

    public function test_admin_chat_renders_progressive_thread_link(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-thread', $html);
        $this->assertStringContainsString('href="' . route('cabinet.admin.chats', ['bookingId' => $booking->id]) . '"', $html);
    }

    // ------------------------------------------------------------------
    // B. Общий data-контракт улучшения
    // ------------------------------------------------------------------

    public function test_tourist_compose_surface_exposes_shared_chat_configuration(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-root', $html);
        $this->assertStringContainsString('data-chat-context="tourist"', $html);
        $this->assertStringContainsString('data-chat-user-id="' . $tourist->id . '"', $html);
        $this->assertStringContainsString('data-chat-current-booking-id="' . $booking->id . '"', $html);
        $this->assertStringContainsString('data-chat-messages-url="' . route('messages.index') . '"', $html);
        $this->assertStringContainsString('data-chat-unread-url="' . route('messages.unread-count') . '"', $html);

        // Композер присутствует и размечен для AJAX-отправки существующим маршрутом.
        $this->assertStringContainsString('data-chat-composer', $html);
        $this->assertStringContainsString('data-chat-input', $html);
        $this->assertStringContainsString('action="' . route('messages.store') . '"', $html);
        $this->assertStringContainsString('data-chat-attachment', $html);
    }

    public function test_manager_compose_surface_exposes_shared_chat_configuration(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-root', $html);
        $this->assertStringContainsString('data-chat-context="manager"', $html);
        $this->assertStringContainsString('data-chat-user-id="' . $manager->id . '"', $html);
        $this->assertStringContainsString('data-chat-messages-url="' . route('messages.index') . '"', $html);
        $this->assertStringContainsString('data-chat-composer', $html);
        $this->assertStringContainsString('data-chat-input', $html);
        $this->assertStringContainsString('action="' . route('messages.store') . '"', $html);
    }

    // ------------------------------------------------------------------
    // C. Админ остаётся read-only — композер не изобретается
    // ------------------------------------------------------------------

    public function test_admin_chat_is_smooth_switch_only_without_composer(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        // Получает конфигурацию плавного переключения…
        $this->assertStringContainsString('data-chat-root', $html);
        $this->assertStringContainsString('data-chat-context="admin"', $html);
        $this->assertStringContainsString('data-chat-messages', $html);

        // …но НЕ композер и НЕ форму отправки сообщений.
        $this->assertStringNotContainsString('data-chat-composer', $html);
        $this->assertStringNotContainsString('action="' . route('messages.store') . '"', $html);
        // И не запускает опрос: у админской разметки нет messages-url.
        $this->assertStringNotContainsString('data-chat-messages-url', $html);
    }

    // ------------------------------------------------------------------
    // D. Ролевые URL веток
    // ------------------------------------------------------------------

    public function test_role_aware_thread_urls_are_distinct(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $this->assertSame('/cabinet/chat/' . $booking->id, route('cabinet.chat', $booking->id, false));
        $this->assertSame('/cabinet/manager/chat/' . $booking->id, route('cabinet.manager.chat', ['bookingId' => $booking->id], false));
        $this->assertSame('/cabinet/admin/chats/' . $booking->id, route('cabinet.admin.chats', ['bookingId' => $booking->id], false));

        $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk();
        $this->actingAs($manager)->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertOk();
        $this->actingAs($admin)->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))->assertOk();
    }

    // ------------------------------------------------------------------
    // E. Авторизация/скоуп не ослаблены (AJAX-режим)
    // ------------------------------------------------------------------

    public function test_ajax_thread_fetch_still_enforces_ownership(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $foreignTourist = $this->makeUser(Role::TOURIST);

        // XHR-заголовок не даёт доступа к чужой заявке.
        $this->actingAs($foreignTourist)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('cabinet.chat', $booking->id))
            ->assertNotFound();
    }

    public function test_ajax_thread_fetch_for_manager_rejects_foreign_booking(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $foreignManager = $this->makeUser(Role::MANAGER);

        $this->actingAs($foreignManager)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // F. Паритет непрочитанных при открытии ветки (менеджер/админ)
    // ------------------------------------------------------------------

    public function test_manager_opening_thread_shows_post_read_unread_count(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $manager->id,
            'message' => 'Непрочитанное для менеджера',
            'is_read' => false,
        ]);

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        // Открытая ветка не показывает устаревший бейдж «1» в списке клиентов.
        $this->assertDoesNotMatchRegularExpression('/bg-danger rounded-pill">\s*1\s*</', $html);
    }

    public function test_admin_opening_thread_leaves_informational_counts_unchanged(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $toManager = Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $manager->id,
            'message' => 'Для менеджера',
            'is_read' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk();

        // Админ не является получателем — открытие ветки ничего не помечает прочитанным.
        $this->assertFalse((bool) $toManager->fresh()->is_read);
    }

    // ------------------------------------------------------------------
    // G. Общая оболочка отдаёт только минимальную идентичность пользователя
    // ------------------------------------------------------------------

    public function test_shared_layout_exposes_only_numeric_user_id_for_draft_namespacing(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="cabinet-user-id" content="' . $tourist->id . '">', $html);

        // Загружается общий модуль улучшения.
        $this->assertStringContainsString('js/cabinet-chat.js', $html);

        // Форма выхода в оболочке остаётся обычной POST-формой (хук очистки —
        // на submit, без раскрытия сессии/токенов сверх существующего CSRF).
        $this->assertStringContainsString('action="' . route('logout') . '"', $html);
    }

    public function test_chat_context_token_is_distinct_per_surface_for_draft_isolation(): void
    {
        // Ключ черновика — avilona:chat-draft:v1:<userId>:<context>:<bookingId>.
        // Токен <context> обязан различаться между поверхностями, иначе ветки
        // «протекли» бы между ролевыми контекстами одного браузера.
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $touristHtml = $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk()->getContent();
        $managerHtml = $this->actingAs($manager)->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertOk()->getContent();
        $adminHtml = $this->actingAs($admin)->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-context="tourist"', $touristHtml);
        $this->assertStringContainsString('data-chat-context="manager"', $managerHtml);
        $this->assertStringContainsString('data-chat-context="admin"', $adminHtml);
    }

    // ------------------------------------------------------------------
    // H. Менеджер — начальный SSR-бейдж личных непрочитанных в сайдбаре
    //    (browser-QA finding #1)
    // ------------------------------------------------------------------

    public function test_manager_sidebar_badge_reflects_personal_unread_on_initial_render(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $tourist = $this->makeTourist();

        // Три лично адресованных менеджеру непрочитанных на трёх ветках.
        foreach (range(1, 3) as $i) {
            $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);
            Message::create([
                'booking_id' => $booking->id,
                'sender_id' => $tourist->id,
                'receiver_id' => $manager->id,
                'message' => "Непрочитанное {$i}",
                'is_read' => false,
            ]);
        }

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat'))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/menu-badge">\s*3\s*</', $html);
    }

    public function test_manager_opening_one_thread_decrements_sidebar_badge_in_same_response(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $tourist = $this->makeTourist();

        $bookings = [];
        $messages = [];
        foreach (range(1, 3) as $i) {
            $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);
            $messages[] = Message::create([
                'booking_id' => $booking->id,
                'sender_id' => $tourist->id,
                'receiver_id' => $manager->id,
                'message' => "Непрочитанное {$i}",
                'is_read' => false,
            ]);
            $bookings[] = $booking;
        }

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $bookings[0]->id]))
            ->assertOk()->getContent();

        // Открытая ветка помечена прочитанной, остальные — нет.
        $this->assertTrue((bool) $messages[0]->fresh()->is_read);
        $this->assertFalse((bool) $messages[1]->fresh()->is_read);
        $this->assertFalse((bool) $messages[2]->fresh()->is_read);

        // Сайдбар в этом же ответе: 3 - 1 = 2.
        $this->assertMatchesRegularExpression('/menu-badge">\s*2\s*</', $html);
    }

    public function test_manager_sidebar_badge_ignores_another_managers_unread(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $otherManager = $this->makeUser(Role::MANAGER);
        $tourist = $this->makeTourist();

        $foreignBooking = $this->makeBooking($tourist, $otherManager->id, Booking::STATUS_PROGRESS);
        Message::create([
            'booking_id' => $foreignBooking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $otherManager->id,
            'message' => 'Чужому менеджеру',
            'is_read' => false,
        ]);

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat'))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('menu-badge', $html);
    }

    // ------------------------------------------------------------------
    // I. Админ как назначенный обработчик заявки (business contract)
    // ------------------------------------------------------------------

    public function test_observer_admin_opening_foreign_thread_changes_nothing(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $toManager = Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $manager->id,
            'message' => 'Для назначенного менеджера',
            'is_read' => false,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        // Просмотр наблюдателя ничего не помечает прочитанным.
        $this->assertFalse((bool) $toManager->fresh()->is_read);
        // Нет композера и опроса.
        $this->assertStringNotContainsString('data-chat-composer', $html);
        $this->assertStringNotContainsString('data-chat-messages-url', $html);
        // Чужие непрочитанные не попадают в личный бейдж администратора.
        $this->assertStringNotContainsString('menu-badge', $html);
    }

    public function test_assigned_admin_thread_exposes_composer_and_polling_and_marks_read(): void
    {
        $tourist = $this->makeTourist();
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($tourist, $admin->id, Booking::STATUS_PROGRESS);

        $m1 = Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $admin->id,
            'message' => 'Первое администратору',
            'is_read' => false,
        ]);
        $m2 = Message::create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $admin->id,
            'message' => 'Второе администратору',
            'is_read' => false,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        // Композер и опрос через существующие маршруты, контекст черновиков admin.
        $this->assertStringContainsString('data-chat-context="admin"', $html);
        $this->assertStringContainsString('data-chat-composer', $html);
        $this->assertStringContainsString('data-chat-messages-url="' . route('messages.index') . '"', $html);
        $this->assertStringContainsString('data-chat-poll-ms="5000"', $html);
        $this->assertStringContainsString('action="' . route('messages.store') . '"', $html);

        // Входящие администратору помечены прочитанными ДО рендера бейджей.
        $this->assertTrue((bool) $m1->fresh()->is_read);
        $this->assertTrue((bool) $m2->fresh()->is_read);

        // Личный бейдж администратора в этом же ответе очищен.
        $this->assertStringNotContainsString('menu-badge', $html);
        // Ветка помечена как «Мне», а не как чужой менеджерский inbox.
        $this->assertStringNotContainsString('Менеджер: ', $html);
    }

    public function test_assigned_admin_can_send_to_tourist_via_existing_store_contract(): void
    {
        $tourist = $this->makeTourist();
        $admin = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($tourist, $admin->id, Booking::STATUS_PROGRESS);

        $this->actingAs($admin)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $tourist->id,
            'message' => 'Здравствуйте, я веду вашу заявку',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'booking_id' => $booking->id,
            'sender_id' => $admin->id,
            'receiver_id' => $tourist->id,
        ]);
    }

    public function test_admin_personal_sidebar_badge_counts_only_messages_addressed_to_admin(): void
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $admin = $this->makeUser(Role::ADMIN);

        // Заявка другого менеджера с его непрочитанными — надзорная информация,
        // не личный бейдж администратора.
        $foreign = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);
        foreach (range(1, 4) as $i) {
            Message::create([
                'booking_id' => $foreign->id,
                'sender_id' => $tourist->id,
                'receiver_id' => $manager->id,
                'message' => "Менеджеру {$i}",
                'is_read' => false,
            ]);
        }

        $observerHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats'))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('menu-badge', $observerHtml);
        // Надзорный ярлык другого менеджера остаётся видимым в списке веток.
        $this->assertStringContainsString('Менеджер: 4', $observerHtml);

        // Заявка, которую ведёт лично администратор, с 2 непрочитанными ему.
        $mine = $this->makeBooking($tourist, $admin->id, Booking::STATUS_PROGRESS);
        Message::create([
            'booking_id' => $mine->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $admin->id,
            'message' => 'Админу 1',
            'is_read' => false,
        ]);
        Message::create([
            'booking_id' => $mine->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $admin->id,
            'message' => 'Админу 2',
            'is_read' => false,
        ]);

        $assignedHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats'))
            ->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/menu-badge">\s*2\s*</', $assignedHtml);
    }

    // ------------------------------------------------------------------
    // J. Очистка черновиков при явном выходе (browser-QA finding #2)
    // ------------------------------------------------------------------

    public function test_cabinet_shell_marks_the_real_logout_form(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="' . preg_quote(route('logout'), '/') . '"[^>]*data-cabinet-logout|<form[^>]*data-cabinet-logout[^>]*action="' . preg_quote(route('logout'), '/') . '"/',
            $html
        );
        $this->assertStringContainsString('js/cabinet-chat.js', $html);
    }

    public function test_shared_chat_module_clears_only_current_user_namespace_on_logout(): void
    {
        $source = file_get_contents(public_path('js/cabinet-chat.js'));
        $this->assertNotFalse($source);

        // Явная функция очистки существует и привязана к маркеру формы выхода.
        $this->assertStringContainsString('clearCurrentUserChatDrafts', $source);
        $this->assertStringContainsString('data-cabinet-logout', $source);

        // Неймспейс — под конкретного пользователя (prefix + userId + ':').
        $this->assertMatchesRegularExpression('/DRAFT_PREFIX\s*\+\s*userId\s*\+\s*\':\'/', $source);

        // Никогда не вызывается localStorage.clear().
        $this->assertStringNotContainsString('localStorage.clear', $source);
    }

    // ------------------------------------------------------------------
    // K. Прокрутка списка веток переживает подмену [data-chat-root]
    //    (E3-A6-B browser-QA finding: список веток прыгал вверх при
    //    переключении переписки)
    // ------------------------------------------------------------------

    public function test_tourist_chat_exposes_thread_list_scroll_hook(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-thread-scroll', $html);
    }

    public function test_manager_chat_exposes_thread_list_scroll_hook(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();

        $html = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-thread-scroll', $html);
    }

    public function test_admin_chat_exposes_thread_list_scroll_hook(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-thread-scroll', $html);
    }

    public function test_shared_chat_module_preserves_thread_list_scroll_around_root_swap(): void
    {
        $source = file_get_contents(public_path('js/cabinet-chat.js'));
        $this->assertNotFalse($source);

        // Захват прокрутки списка веток до подмены корня и восстановление
        // после — привязано к тому же стабильному хуку, что и Blade-разметка.
        $this->assertStringContainsString('data-chat-thread-scroll', $source);
        $this->assertMatchesRegularExpression(
            '/oldThreadScroll[\s\S]*?ctx\.root\.replaceWith\(newRoot\)[\s\S]*?newThreadScroll/',
            $source
        );

        // Восстановление не трогает прокрутку панели сообщений.
        $this->assertStringContainsString('scrollToBottom(messagesContainer())', $source);
    }

    // ------------------------------------------------------------------
    // L. Адаптивная стабилизация чата (E4-D2: F-02, F-09, F-15)
    // ------------------------------------------------------------------

    public function test_manager_and_admin_chat_panes_share_the_viewport_height_token_not_inline_calc(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $pages = [
            'manager' => $this->actingAs($manager)
                ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertOk()->getContent(),
            'admin' => $this->actingAs($admin)
                ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))->assertOk()->getContent(),
        ];

        foreach ($pages as $role => $html) {
            $this->assertStringContainsString('cabinet-chat-pane--list', $html, $role);
            $this->assertStringContainsString('cabinet-chat-pane--window', $html, $role);
            // Инлайновый calc(100vh - 200px) не учитывал margin карточки и заголовок
            // страницы — из-за него документ вылезал за окно на 8-28px (F-15).
            $this->assertStringNotContainsString('calc(100vh', $html, $role);
        }

        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression('/\.cabinet-chat-pane\s*\{[^}]*height:\s*var\(--cabinet-chat-height\);/s', $css);
        // Тот же токен у туристской панели — три роли не расходятся по высоте.
        $this->assertMatchesRegularExpression(
            '/\.tc-chat__panel\s*\{[^}]*height:\s*var\(--cabinet-chat-height\);/s',
            $css
        );
    }

    public function test_thread_status_row_has_a_layout_hook_so_narrow_lists_do_not_clip_it(): void
    {
        [$tourist, $manager, $booking] = $this->scenario();
        $admin = $this->makeUser(Role::ADMIN);

        $managerHtml = $this->actingAs($manager)
            ->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertOk()->getContent();
        $adminHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))->assertOk()->getContent();

        foreach (['manager' => $managerHtml, 'admin' => $adminHtml] as $role => $html) {
            $this->assertStringContainsString('chat-thread__row', $html, $role);
            $this->assertStringContainsString('chat-thread__body', $html, $role);
            $this->assertStringContainsString('chat-thread__status', $html, $role);
        }

        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);
        // На средних ширинах строка статусов занимает всю ширину ветки (F-09).
        $this->assertMatchesRegularExpression(
            '/\.chat-thread__body\s*>\s*\.chat-thread__status\s*\{\s*grid-column:\s*1\s*\/\s*-1;/s',
            $css
        );
    }

    public function test_tourist_chat_grid_can_shrink_below_its_content_on_narrow_screens(): void
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);

        // 1fr = minmax(auto, 1fr): nowrap-строки веток раздували колонку до
        // ~404px на вьюпорте 360px (F-02). Должно быть minmax(0, 1fr).
        $this->assertMatchesRegularExpression(
            '/@media \(max-width:\s*767\.98px\)\s*\{\s*\.tc-chat\s*\{\s*grid-template-columns:\s*minmax\(0,\s*1fr\);/s',
            $css
        );
        $this->assertMatchesRegularExpression('/\.tc-chat__panel\s*\{[^}]*min-width:\s*0;/s', $css);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array{0: User, 1: User, 2: Booking}
     */
    private function scenario(): array
    {
        $tourist = $this->makeTourist();
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($tourist, $manager->id, Booking::STATUS_PROGRESS);

        return [$tourist, $manager, $booking];
    }

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
        string $status = Booking::STATUS_PROGRESS
    ): Booking {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create([
            'user_id' => $owner->id,
            'manager_id' => $managerId,
            'status' => $status,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'start_date' => now()->addDays(30)->format('Y-m-d'),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
        ]));
    }
}
