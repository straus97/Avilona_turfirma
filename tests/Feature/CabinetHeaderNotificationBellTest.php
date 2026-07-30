<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Проверяет минимальный колокольчик уведомлений в общем cabinet-хедере:
 * бейдж непрочитанных считает только записи NewMessageDatabaseNotification
 * текущего пользователя, действие показывает ровно одно (самое свежее)
 * уведомление и ведёт на уже существующий безопасный маршрут
 * cabinet.notifications.open — вся авторизация/валидация остаётся в
 * NotificationController, здесь она не дублируется.
 */
class CabinetHeaderNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1-3. Колокольчик присутствует на страницах кабинета каждой роли
    // -----------------------------------------------------------------------

    public function test_bell_is_present_for_tourist_cabinet_pages(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)
            ->get(route('cabinet.dashboard'))
            ->assertOk()
            ->assertSee('header-notifications', false);
    }

    public function test_bell_is_present_for_manager_cabinet_pages(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        // Не cabinet.manager.dashboard: ManagerController::dashboard() строит
        // помесячную статистику через selectRaw("DATE_FORMAT(created_at, ...)"),
        // а это MySQL-функция, которой нет в SQLite (обязательной БД для
        // PHPUnit) — под SQLite этот маршрут падает независимо от колокольчика
        // уведомлений. cabinet.manager.profile — тот же общий cabinet-layout,
        // но без единого запроса к БД, поэтому пригоден как якорь для этой
        // проверки без маскировки существующего, несвязанного дефекта.
        $this->actingAs($manager)
            ->get(route('cabinet.manager.profile'))
            ->assertOk()
            ->assertSee('header-notifications', false);
    }

    public function test_bell_is_present_for_admin_cabinet_pages(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)
            ->get(route('cabinet.admin.dashboard'))
            ->assertOk()
            ->assertSee('header-notifications', false);
    }

    // -----------------------------------------------------------------------
    // 4. Бейдж считает только свои непрочитанные NewMessageDatabaseNotification
    // -----------------------------------------------------------------------

    public function test_badge_count_includes_only_authenticated_users_unread_new_message_notifications(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        // Два подходящих непрочитанных.
        $this->notify($tourist, 'MATCH-UNREAD-ONE');
        $this->notify($tourist, 'MATCH-UNREAD-TWO');

        // Подходящее, но уже прочитанное — не считается.
        $read = $this->notify($tourist, 'MATCH-READ');
        $read->markAsRead();

        // Непрочитанное, но неподдерживаемого типа — не считается.
        $this->rawNotification($tourist, [
            'type'       => 'some_other_event',
            'booking_id' => 1,
        ], 'App\\Notifications\\SomeOtherNotification');

        // Непрочитанное подходящего типа, но чужое — не считается.
        $stranger = $this->makeUser([Role::TOURIST]);
        $this->notify($stranger, 'OTHER-USER-UNREAD');

        $this->actingAs($tourist)
            ->get(route('cabinet.dashboard'))
            ->assertOk()
            ->assertSee('notification-badge">2</span>', false);
    }

    // -----------------------------------------------------------------------
    // 5. Показывается только самое свежее подходящее непрочитанное
    // -----------------------------------------------------------------------

    public function test_only_most_recent_matching_unread_notification_is_displayed_as_action(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $older = $this->notify($tourist, 'OLDER-MARKER-TEXT');
        $newer = $this->notify($tourist, 'NEWER-MARKER-TEXT');

        $anchor = now();
        $older->forceFill(['created_at' => $anchor->copy()->subMinutes(10)])->save();
        $newer->forceFill(['created_at' => $anchor->copy()])->save();

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee('NEWER-MARKER-TEXT');
        $response->assertDontSee('OLDER-MARKER-TEXT');
    }

    // -----------------------------------------------------------------------
    // 6. Действие ведёт на существующий маршрут cabinet.notifications.open
    // -----------------------------------------------------------------------

    public function test_action_targets_existing_cabinet_notifications_open_url_for_latest_notification(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);
        $notification = $this->notify($tourist, 'TARGET-URL-MARKER');

        $expectedUrl = route('cabinet.notifications.open', ['notification' => $notification->id]);
        $senderName = $notification->data['sender_name'];

        $response = $this->actingAs($tourist)
            ->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee($expectedUrl, false);

        // Три чётких строки действия: жирная метка типа, строка отправителя
        // (с реальным, не fallback-именем) и превью сообщения.
        $response->assertSee('Новое сообщение');
        $response->assertSee('Отправитель: ' . $senderName);
        $response->assertSee('TARGET-URL-MARKER');
    }

    // -----------------------------------------------------------------------
    // 7. Действие — POST-форма с CSRF-токеном
    // -----------------------------------------------------------------------

    public function test_action_is_post_form_with_csrf_token(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);
        $this->notify($tourist, 'CSRF-FORM-MARKER');

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_token"', false);
    }

    // -----------------------------------------------------------------------
    // 8. Нет подходящих непрочитанных — плейсхолдер, без бейджа и без действия
    // -----------------------------------------------------------------------

    public function test_zero_unread_notifications_shows_placeholder_with_no_badge_or_action(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee('Нет новых уведомлений');
        // Проверяем отсутствие именно РЕНДЕРЕННОГО элемента бейджа, а не
        // голого имени класса: ".notification-badge" как имя CSS-класса
        // постоянно присутствует в <style> общего layout (строка стилей
        // .notification-badge {...}), поэтому bare-строка всегда "видна"
        // независимо от состояния — это ложное срабатывание, а не проверка.
        $response->assertDontSee('<span class="notification-badge">', false);
        $response->assertDontSee('/cabinet/notifications/', false);
    }

    // -----------------------------------------------------------------------
    // 9. Только неподдерживаемый тип уведомления — без бейджа и без действия
    // -----------------------------------------------------------------------

    public function test_unrelated_unread_notification_type_alone_shows_no_badge_or_action(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->rawNotification($tourist, [
            'type'       => 'some_other_event',
            'booking_id' => 1,
        ], 'App\\Notifications\\SomeOtherNotification');

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee('Нет новых уведомлений');
        // Проверяем отсутствие именно РЕНДЕРЕННОГО элемента бейджа, а не
        // голого имени класса: ".notification-badge" как имя CSS-класса
        // постоянно присутствует в <style> общего layout (строка стилей
        // .notification-badge {...}), поэтому bare-строка всегда "видна"
        // независимо от состояния — это ложное срабатывание, а не проверка.
        $response->assertDontSee('<span class="notification-badge">', false);
        $response->assertDontSee('/cabinet/notifications/', false);
    }

    // -----------------------------------------------------------------------
    // 10. Некорректный (не-массив) data — без 500, с безопасным fallback-текстом
    // -----------------------------------------------------------------------

    public function test_malformed_non_array_data_payload_does_not_error_and_shows_fallback_text(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        // Скалярный (не-массив) payload корректного типа — см. аналогичную
        // технику в CabinetNotificationOpenTest (проверка ветки !is_array($data)).
        $this->rawNotification($tourist, 'not-an-array-payload');

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee('notification-badge">1</span>', false);
        $response->assertSee('Новое сообщение');
        $response->assertSee('Отправитель: Пользователь');
    }

    // -----------------------------------------------------------------------
    // 11. Мульти-ролевой пользователь видит свой счётчик на итоговом dashboard
    // -----------------------------------------------------------------------

    public function test_multi_role_user_sees_own_unread_count_on_effective_dashboard(): void
    {
        $multiRoleUser = $this->makeUser([Role::ADMIN, Role::MANAGER]);
        $this->notify($multiRoleUser, 'MULTI-ROLE-MARKER');

        // '/cabinet' перенаправляет admin+manager на admin-дашборд — тот же
        // приоритет ролей, что уже покрыт CabinetDashboardRoleGuardConsistencyTest.
        $this->actingAs($multiRoleUser)
            ->get(route('cabinet.dashboard'))
            ->assertRedirect(route('cabinet.admin.dashboard'));

        $this->actingAs($multiRoleUser)
            ->get(route('cabinet.admin.dashboard'))
            ->assertOk()
            ->assertSee('notification-badge">1</span>', false);
    }

    // -----------------------------------------------------------------------
    // 12. Большой объём уведомлений — по-прежнему только одно действие
    // -----------------------------------------------------------------------

    public function test_larger_notification_volume_still_renders_only_one_actionable_notification(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        // Буквенные, полностью различающиеся маркеры вместо числовых суффиксов:
        // числовой маркер вида "VOL-MARKER-1" является буквальной подстрокой
        // "VOL-MARKER-11", поэтому assertDontSee("VOL-MARKER-1") ложно падал бы
        // на тексте самого свежего уведомления. Буквы A-K не являются
        // подстроками друг друга и не являются подстрокой отдельного слова
        // "VOLUME-LATEST-UNIQUE".
        $olderMarkerLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];

        $olderNotifications = [];
        foreach ($olderMarkerLetters as $letter) {
            $olderNotifications[] = $this->notify($tourist, "VOLUME-OLDER-{$letter}-UNIQUE");
        }

        $latestNotification = $this->notify($tourist, 'VOLUME-LATEST-UNIQUE');

        // Явные, заведомо различающиеся created_at: все "старые" — раньше
        // anchor, единственная "самая свежая" — позже anchor. Порядок
        // создания записей (rapid, в одном тесте) намеренно не используется
        // как источник истины об порядке — production сортирует исключительно
        // по created_at, и здесь тест это отражает напрямую.
        $anchor = now();
        foreach ($olderNotifications as $index => $notification) {
            $notification->forceFill([
                'created_at' => $anchor->copy()->subMinutes(count($olderNotifications) - $index),
            ])->save();
        }
        $latestNotification->forceFill(['created_at' => $anchor->copy()->addMinute()])->save();

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee('notification-badge">12</span>', false);

        // Самая свежая по created_at — единственная показанная.
        $response->assertSee('VOLUME-LATEST-UNIQUE');

        foreach ($olderMarkerLetters as $letter) {
            $response->assertDontSee("VOLUME-OLDER-{$letter}-UNIQUE");
        }
    }

    // -----------------------------------------------------------------------
    // 13. Длинное превью отображается в рамках существующего лимита в 60 символов
    // -----------------------------------------------------------------------

    public function test_long_preview_is_rendered_within_configured_concise_limit(): void
    {
        $tourist = $this->makeUser([Role::TOURIST]);

        // Длина текста > 60 (чтобы сработало отображаемое ограничение), но
        // ≤ 120 (чтобы Str::limit(...,120) при персистентности не обрезал его
        // раньше времени) — превью, дошедшее до Blade, остаётся полным.
        $longPreviewText = 'LONGPREVIEW-UNIQUE-MARKER-' . str_repeat('Z', 80);
        $this->assertGreaterThan(60, mb_strlen($longPreviewText));
        $this->assertLessThanOrEqual(120, mb_strlen($longPreviewText));

        $this->notify($tourist, $longPreviewText);

        // Тот же контракт лимита, что и в production-коде (Str::limit(...,60)),
        // а не отдельно вычисленная вручную строка.
        $expectedVisiblePreview = Str::limit($longPreviewText, 60);

        $response = $this->actingAs($tourist)->get(route('cabinet.dashboard'));

        $response->assertOk();
        $response->assertSee($expectedVisiblePreview, false);
        $response->assertDontSee($longPreviewText, false);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param string[] $roleNames
     */
    private function makeUser(array $roleNames): User
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
        User $sender,
        User $receiver,
        string $text
    ): Message {
        return Message::query()->create([
            'booking_id'  => $booking->id,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'message'     => $text,
        ]);
    }

    /**
     * Доставляет реальное database-уведомление о новом сообщении получателю
     * через стандартный API Notifiable и находит созданную запись по
     * встроенному в payload message_id — не по created_at, порядок создания
     * нескольких уведомлений подряд не влияет на однозначность выборки.
     */
    private function notify(User $owner, string $previewMarker): DatabaseNotification
    {
        $sender  = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBookingFor($sender, null);
        $message = $this->makeMessage($booking, $sender, $owner, $previewMarker);

        $owner->notify(new NewMessageDatabaseNotification($message));

        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $owner->id)
            ->get()
            ->first(function (DatabaseNotification $notification) use ($message): bool {
                return is_array($notification->data)
                    && ($notification->data['message_id'] ?? null) === $message->id;
            });
    }

    /**
     * Вставляет "сырую" запись уведомления напрямую, минуя канал доставки —
     * нужно для проверки неподдерживаемых/некорректных payload-ов, которые
     * реальный NewMessageDatabaseNotification никогда бы не сформировал.
     */
    private function rawNotification(
        User $owner,
        mixed $data,
        string $type = NewMessageDatabaseNotification::class
    ): DatabaseNotification {
        return DatabaseNotification::query()->create([
            'id'              => (string) Str::uuid(),
            'type'            => $type,
            'notifiable_type' => User::class,
            'notifiable_id'   => $owner->id,
            'data'            => $data,
            'read_at'         => null,
        ]);
    }
}
