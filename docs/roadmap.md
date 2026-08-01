# Avilona_turfirma — актуальная дорожная карта

Дата checkpoint: **2026-08-02**

Последний функциональный checkpoint:

```text
f1a0fff0b1f0b93c654b836e93dd5049eae2569f
fix: preserve tourist data on deletion failure
```

Текущий репозиторный/документационный HEAD всегда определяется Git и
после docs-only commit, фиксирующего эти правки, станет новее указанного
функционального checkpoint — это не меняет сам функциональный checkpoint
и его test baseline.

Родительский commit:

```text
d17255e74cc77ce56bd42bcbeaa47d590aa46f1d
fix: align tourist deletion session teardown
```

Предыдущий documentation checkpoint:

```text
f6d0e119ac22353bd411914db508eb6e45109de8
docs: close stage 10 and plan stage 11
```

Текущий тестовый baseline:

```text
Full:    632 tests / 2777 assertions
Focused (последний Stage 11 slice — tourist partial-failure): 15 tests / 106 assertions
PHPUnit 10.5.20
SQLite :memory: (единственно допустимая БД для PHPUnit)
Canonical migrations: 53 Ran / 0 Pending
Canonical MySQL: turfirma_rebuild_v4, порт 3308
PHP: C:\wamp\bin\php\php8.3.32\php.exe (8.3.32)
```

Известное non-blocking предупреждение: `phpunit.xml` использует deprecated
schema — backlog item, не часть текущего semantic slice.

## Сводный статус

- Stage 0–4 — ✅ COMPLETE
- Stage 5 — ✅ COMPLETE
- Stage 6 — ✅ COMPLETE
- Emergency DB Recovery R0–R6D — ✅ COMPLETE
- RSS maintenance slice — ✅ COMPLETE
- Stage 7 — ✅ COMPLETE
- Stage 8 — ✅ COMPLETE
- Stage 9 — ✅ COMPLETE
- Stage 10 — ✅ COMPLETE (12 slices)
- Stage 11 — 🟡 IN PROGRESS (12 completed slices; security, reliability, performance)
- Stage 12 — ⏸ DEFERRED (dependency upgrades)
- Stage 13 — 📋 PLANNED (production readiness)

---

## Emergency DB Recovery — ✅ COMPLETE

Ошибочно пересозданная canonical DB `turfirma_rebuild_v4` восстановлена и promoted.

Подтверждено:

- 52 migrations Ran / 0 Pending;
- 28 таблиц InnoDB;
- users=7, role_user=7, roles=3;
- admin=1, manager=0, tourist=6;
- bookings/messages/booking_documents/user_documents=0;
- all row-count, index, FK, CHECK TABLE и AUTO_INCREMENT проверки PASS;
- final canonical manifest:
  `78AD452573B4305897E20465F3B599F44C1E4C2636DE2DB321E695E4B70DB4B1`;
- promoted logical dump:
  `BDFAF8679622322495F47AE869AEFCE8614282227A49CA5BCD3BB28DCD1D8FA4`;
- recovery application fingerprint:
  `fc449488f3d115713cfa0ee97b62a933dfa11393cdbc89c391816aa25d174784`;
- public smoke 15/15;
- protected unauthenticated smoke 14/14;
- fingerprint unchanged after recovery live validation.

Старый rollback/physical backup и каталоги `D:\Avilona_recovery\20260716`
пока сохраняются.

---

## Stage 5. Полный жизненный цикл заявки — ✅ COMPLETE

Ключевые checkpoint:

- `70c7d44d` — lifecycle authorization.
- `d8ac1a1d` — status transition rules.
- `cb5d8744` — transition UI.
- `ddad32d9` — creation ownership and atomicity.
- `9d4f8e3f` — assignee eligibility.
- `e9603e7e` — reassignment notification.
- `e71d8cda` — initial assignment notification guard.

Отложено:

- история/аудит заявки;
- единый E2E lifecycle scenario;
- продуктовое решение по reassignment в терминальном статусе;
- уведомление прежнего исполнителя о снятии назначения.

## Stage 6. Чат туриста и менеджера — ✅ COMPLETE

Ключевые checkpoint:

- `8879139b` — participant and recipient authorization.
- `15e7e996` — secure message attachments.
- `bd202d0b` — active chat links.
- `5fbe2bef` — NewMessageReceived dispatch.
- `61975e0d` — polling isolation coverage.

Отложено:

- live polling администратора;
- WebSocket/unread counters;
- parity `read_at` в initial view controllers;
- editing/history/audit.

## RSS maintenance — ✅ COMPLETE

Commit:

```text
49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f
```

Результат:

- публичный news GET стал read-only;
- RSS sync вынесен в явную command/service архитектуру;
- `content:encoded` сохраняется в `description`;
- миграции не создавались;
- учтены SoftDeletes, slug collisions, duplicate links и rollback;
- targeted 17 tests / 72 assertions;
- реальный sync против canonical MySQL не выполнялся.

Backlog:

1. Автоматическое расписание `news:sync-rss` не настроено.
2. Реальный sync против canonical MySQL до отдельного operational plan не выполнять.

Затенение `/helpful_information/news/rss` устранено в Stage 9 commit
`72c030d5`.

## Stage 7. Личные кабинеты и единый UX — ✅ COMPLETE

Завершённые checkpoint:

- `9ba1b129` — shared-route role redirects.
- `73ea7ed4` — public cabinet menu precedence.
- `47507b85` — cabinet header role links.
- `7cf9381a` — booking sidebar precedence.
- `2ba8f22d` — booking edit effective role.
- `842e0603` — booking show effective role.
- `271ca168` — booking cancel effective role.
- `6d23812d` — cabinet dashboard role guard.
- `1bc2c9ea` — booking document effective role.
- `34a03ee9` — dual-role message participant coverage.
- `973975d6` — booking show per-booking authorization.

Итоговое правило:

```text
admin > assigned manager > owner-facing tourist
```

Closure verification:

- full PHPUnit: 352 tests / 1183 assertions;
- Apache service lifecycle and HTTP 200: PASS;
- canonical MySQL lifecycle: PASS;
- Stage 7 read-only session fingerprint:
  `afb2cd19ce0401e82c8bc29f0446785906afd9cc31197f81cc573adb19f06cca`;
- mixed-role browser smoke: PASS;
- temporary fixtures removed;
- session fingerprint restored after cleanup;
- local/origin HEAD equal;
- working tree clean;
- `STAGE7_FINAL_CLOSURE_VERIFICATION=PASS`.

## Stage 8. Каталог и поиск туров — ✅ COMPLETE

Локальный JSON tour-search API:

- `0cabcda8` — baseline локального tour index.
- `fcacf2fe` — выравнивание значений фильтра оператора.
- `8b4ee060` — выравнивание фильтра оператора в поиске.
- `495c4cb2` — выравнивание фильтра ночей.
- `ca683837` — выравнивание фильтра рейтинга.
- `7ba16072` — выравнивание фильтра charter.
- `38abdbb2` — выравнивание фильтра прямого рейса.
- `6bd36434` — удаление неподдерживаемого фильтра instant confirmation.
- `2599ff83` — выравнивание сортировки по рейтингу.
- `975a2879` — добавление фильтра по названию отеля.

Публичный каталог туров:

- `9ba1b070` — выравнивание сортировки по рейтингу.
- `a3c9ce23` — добавление фильтра рейтинга.
- `7a09a2b6` — добавление фильтра линии пляжа.
- `c53febfe` — добавление фильтра charter.
- `459aa7dd` — выравнивание значения сортировки popular.
- `4f9eb366` — добавление фильтра прямого рейса.
- `bae26480` — удаление неподдерживаемого фильтра nonstop.

Итог:

- публичный каталог read-only, не изменяет базу данных и не выполняет
  внешние HTTP-запросы;
- отображаются только активные и не удалённые туры;
- контракты фильтров выровнены между публичной формой и внутренним
  JSON tour-search API (tour_operator, nights, rating, charter,
  direct_flight, hotel_name);
- сортировка по рейтингу использует `hotel_rating`;
- канонический sort-value `popular`;
- неподдерживаемые `nonstop` и `instant_confirmation` удалены, а не
  реализованы с придуманным поведением.

External integration assessment (только статическая read-only
инвентаризация):

- поверхности: `routes/api.php` (Sletat routes),
  `App\Http\Controllers\Api\SletatController`,
  `App\Services\Sletat\SletatApiService`,
  `App\Console\Commands\TestSletatConnectionCommand`,
  `App\Console\Commands\SyncToursCommand`,
  `App\Services\TourOperators\BaseTourOperatorService`,
  `App\Services\TourOperators\CoralTravelService`,
  хранение `api_key` в `TourOperator` и placeholder seed-значения;
- реальные запросы к Sletat/Coral Travel и другим туроператорам не
  выполнялись, credentials не добавлялись, не выводились, не
  проверялись и не сохранялись;
- существующий интеграционный код не подтверждён как рабочий;
- операционная интеграция, credentials, provider contract verification,
  retries, rate limits, observability, safe synchronization и
  canonical-импорт остаются отдельным guarded operational планом.

Closure verification:

- focused public catalog suite: 18 tests / 82 assertions;
- full PHPUnit: 382 tests / 1389 assertions;
- local/origin HEAD равны на `bae26480`, working tree clean.

## Stage 9. Публичный контент и CMS — ✅ COMPLETE

Завершённые checkpoint:

- `72c030d5` — локальный публичный RSS 2.0 для новостей и устранение
  затенения `/helpful_information/news/rss`;
- `cd15e5c5` — удаление неподдерживаемого публичного detail route отзыва;
- `fa0cfc7d` — валидация итоговых generated article slug для admin/manager
  create/update;
- `02bfa6da` — актуальность публичных страниц статей без устаревшего
  application cache;
- `06f6035c` — актуальность публичных news index/detail/RSS;
- `6bd3db9c` — актуальность публичного списка отзывов и homepage reviews;
- `136ae5eb` — актуальность блока новостей на главной;
- `4cdb3d72` — единый query-free canonical URL и `og:url` для публичного
  layout с безопасным override/escaping.

Итог:

- публичный RSS формируется локально из `News`, исключает soft-deleted
  записи и больше не затеняется slug-маршрутом;
- неподдерживаемая публичная detail-страница отзыва удалена, при этом
  список, отправка и manager/admin moderation сохранены;
- generated article slug валидируется до записи, включая пустой/пробельный
  slug и collision для create/update;
- публичные статьи, новости, RSS, отзывы и homepage news не зависят от
  cache entries, которые могли оставаться устаревшими после CMS-изменений;
- `home_best_offers` и `home_partners` оставлены намеренно: read-only
  inventory не обнаружила обычных runtime mutation paths, поэтому
  подтверждённого cache invalidation defect нет;
- shared public layout содержит ровно один canonical и один `og:url`,
  использующие одно query-free значение `url()->current()` либо
  `canonical_url` override;
- двойное экранирование inline Blade section устранено без raw `{!! !!}`;
- routes/controllers/models/config/robots/sitemap не менялись в
  canonical metadata slice.

Read-only SEO inventory зафиксировала отдельный backlog, не включённый в
широкую реализацию Stage 9:

- статический `sitemap.xml`: 272 URL и все 272 `lastmod` датированы 2024
  годом;
- `robots.txt` содержит устаревшие allow-paths, не полностью совпадающие
  с текущими маршрутами;
- page-specific title/meta description/Open Graph coverage остаётся
  неполной;
- автоматическое расписание `news:sync-rss` не настроено.

Closure verification:

- Stage 9 commits: 8;
- совокупно изменённых путей: 21;
- финальный focused canonical suite: 7 tests / 50 assertions;
- full PHPUnit: 466 tests / 2014 assertions;
- PHP 8.3.32, SQLite `:memory:`;
- local/origin HEAD равны на `4cdb3d72`;
- working tree clean;
- `STAGE9_PUBLIC_CANONICAL_METADATA_COMMIT_PUSH=PASS`;
- `STAGE9_CLOSURE_INVENTORY=PASS`.

## Stage 10. Уведомления — ✅ COMPLETE

Stage 10 завершён: 12 маленьких semantic slice, один линейный commit chain.
Последний функциональный checkpoint —
`9ef3c8e96411c11dd4f725f607bf3dc8f56b5a0c` (`feat: add cabinet notification
bell`); его непосредственный родитель —
`04c6c41035d9e421944ee1135fc95cd6fe9c2fd4` (слайс 11):

1. `92e49fceb9d6832d2516092ce13c3eccd328a011` — new-message email preferences.
2. `4e18ca6dfd24bc413f72a61311643d337b5c6de6` — booking-status email preferences.
3. `926b8382926d5e9b112843d14d4b5e241a02c600` — manager-assignment email preferences.
4. `a92e1dbe2882e996b43de0f93354a0c0e9e636ba` — booking-created email preferences.
5. `1180931adfc0e4081281ca9b797920496431c5bd` — trip-reminder email preferences.
6. `d9279a18b474952a3cacb875a209eb3336263a58` — trip-reminder settings copy.
7. `a9dc356a4d43b3aded40444532bdf4e1e9d87a29` — trip-reminder idempotency.
8. `d572b11633e9fcd2ffce848e1633a1a9e8eaa546` — new-message database notification persistence.
9. `014470402984eaffd8c9888292ec51a2124de71d` — open new-message notifications safely from the cabinet.
10. `8012e1c236dc13cb304281d704e1e60c30273266` — protect booking-status notification dispatch.
11. `04c6c41035d9e421944ee1135fc95cd6fe9c2fd4` — log public contact-form mail failures.
12. `9ef3c8e96411c11dd4f725f607bf3dc8f56b5a0c` — add cabinet notification bell.

Между слайсом 9 (`01447040`) и слайсом 10 (`8012e1c2`) в истории есть ровно
один docs-only commit — `f351ae9e86092e7be9bf5215a63477822b097d3a`
(`docs: archive legacy docs and update stage 10`). Он документационный, а
не функциональный, и не входит в счёт 12 слайсов.

Trip-reminder слайсы (5–7) добавили guarded migration; canonical baseline
после неё — 53 Ran / 0 Pending.

### Слайс 8 — `d572b116`. Database-персистентность нового сообщения

- `App\Notifications\NewMessageDatabaseNotification` с database-каналом;
- payload: `type` (всегда `new_message`), `message_id`, `booking_id`,
  `sender_id`, `sender_name`, `preview`, `has_attachment`;
- запись в стандартную Laravel-таблицу `notifications`.

### Слайс 9 — `01447040`. Открытие уведомления из кабинета

- POST route `cabinet.notifications.open` внутри authenticated cabinet
  role middleware (`auth`, `password.change`, `role:tourist,manager,admin`);
- уведомление ищется строго через связь текущего пользователя — без
  предварительного глобального разрешения по id;
- принимается только контракт `NewMessageDatabaseNotification` +
  `data.type === new_message`;
- `booking_id` валидируется как строго положительный integer либо
  числовая строка;
- заявка загружается до какого-либо изменения `read_at`;
- effective-role приоритет `admin > manager > tourist`, без отката с
  manager-ветки на tourist-ветку у мульти-ролевых пользователей;
- редирект на `cabinet.chat` / `cabinet.manager.chat` / `cabinet.admin.chats`
  с `bookingId`;
- уже прочитанное уведомление обрабатывается идемпотентно
  (`read_at` не переписывается повторно);
- 404 без пометки прочитанным для: чужого уведомления, случайного
  несуществующего UUID, неподдерживаемого класса уведомления, `data.type`
  ≠ `new_message`, отсутствующего/некорректного (0, отрицательного,
  нечислового) `booking_id`, отсутствующей или мягко удалённой заявки,
  заявки другого туриста/менеджера, ролевого пользователя без доступа;
  роль без прав отклоняется `role`-middleware (403) до контроллера;
- 21 feature-тест в `CabinetNotificationOpenTest`.

Verification для слайса `01447040`:

- focused: 96 tests / 347 assertions;
- full PHPUnit на `01447040`: 567 tests / 2391 assertions;
- PHP `C:\wamp\bin\php\php8.3.32\php.exe`, PHPUnit только SQLite `:memory:`;
- независимая review подтвердила соответствие реализации контракту;
- `01447040` был закоммичен, запушен и проверен как последний
  функциональный checkpoint на момент закрытия этого слайса (позже
  заменён слайсами 10–12).

### Слайс 10 — `8012e1c2`. Защита диспатча BookingStatusChanged

`Booking::transitionTo()` оборачивает `event(new BookingStatusChanged(...))`
в try/catch с `Log::error('BookingStatusChanged dispatch failed', [...])`,
симметрично трём уже защищённым сайтам диспатча (`BookingCreated` в
`BookingController::store()`, `ManagerAssigned` в
`BookingController::dispatchManagerAssigned()`, `NewMessageReceived` в
`MessageController`). Сбой синхронного почтового слушателя (`sync` queue)
больше не превращает уже успешный переход статуса заявки в HTTP 500.
Новый тест-файл `tests/Feature/BookingStatusChangeDispatchResilienceTest.php`.

### Слайс 11 — `04c6c410`. Логирование сбоев публичной формы обратной связи

`SendContactController` и `SendHomeController` логируют сбой отправки
письма (`Log::error('Public contact form mail send failed', ...)` /
`'Home contact form mail send failed'`) внутри уже существующего
generic-catch блока — без изменения перехватываемого типа исключения,
получателей писем или пользовательских сообщений. Новый тест-файл
`tests/Feature/PublicContactMailFailureLoggingTest.php`.

### Слайс 12 — `9ef3c8e9`. Колокольчик уведомлений в кабинете

Минимальный UI поверх уже существующего backend-контракта (слайсы 8–9),
без новых маршрутов, контроллеров или моделей:

- добавлен в `resources/views/cabinet/layouts/app.blade.php`, общий для
  tourist/manager/admin cabinet-layout;
- owner-scoped, type-filtered (`NewMessageDatabaseNotification`) bounded
  запрос: `count()` для бейджа + `latest()->first()` для самого свежего
  непрочитанного — без запросов к `Booking`/`Message`/`User` из Blade;
- ровно одно действие — POST-форма на уже существующий
  `cabinet.notifications.open` с `@csrf`, повторяющая существующий
  паттерн logout-формы; вся авторизация/валидация остаётся в
  `NotificationController::open()`, не дублируется;
- три строки текста действия: жирная метка «Новое сообщение», строка
  «Отправитель: {имя}» (fallback `Пользователь`), превью с лимитом 60
  символов (fallback `Новое сообщение`), без raw HTML;
- zero-state «Нет новых уведомлений» без бейджа и без действия;
- 13 feature-тестов в `tests/Feature/CabinetHeaderNotificationBellTest.php`
  (роль-присутствие для всех трёх ролей, owner/type-scoped счётчик,
  только самое свежее уведомление, целевой URL, POST+CSRF, zero-state,
  неподдерживаемый тип, некорректный data-payload, мульти-роль, объём
  уведомлений, длинное превью).

### Итоговая verification Stage 10 (финальный слайс `9ef3c8e9`)

- PHP `C:\wamp\bin\php\php8.3.32\php.exe`, PHPUnit только SQLite `:memory:`;
- focused notification-bell suite: 57 tests / 216 assertions;
- full PHPUnit: 588 tests / 2486 assertions;
- canonical migrations: 53 Ran / 0 Pending;
- local/origin HEAD равны на `9ef3c8e9`, working tree clean, staged path
  count 0 (на момент закрытия функционального слайса, до этого docs-only
  commit);
- ручное browser QA:
  - колокольчик виден в хедере для tourist, manager и admin;
  - бейдж непрочитанных корректно переходит 0 → 1 → 0;
  - отображается самое свежее уведомление (превью и отправитель);
  - POST-действие открывает правильный чат по заявке;
  - открытое уведомление больше не учитывается в счётчике непрочитанных;
  - длинное превью остаётся ограниченным (concise, не выводится целиком);
  - zero-state показывает «Нет новых уведомлений»;
  - локальные QA-фикстуры (тестовый менеджер, тестовая заявка,
    browser-QA сообщения и database-уведомления) не являются состоянием
    репозитория, не закоммичены и не входят в changed-path inventory;
    QA-пароль в документации не фиксируется.

### Пере-оценённые находки прошлого discovery — закрытие Stage 10

- **Мёртвые/несовпадающие счётчики sidebar** (`$pendingBookingsCount`
  нигде не устанавливается; manager-сайдбар проверяет
  `$unreadMessagesCount`, а `ManagerController::dashboard()` передаёт
  `$unreadMessages`/`$pendingBookings` — другие имена) — не связаны с
  таблицей `notifications`, предшествуют Stage 10 и не регрессировали от
  него. Классификация: code-hygiene backlog (см. «Общий backlog» ниже),
  не Stage 10 blocker. Рабочее поведение туриста (`unreadMessagesCount`
  на его dashboard) не трогать ради симметрии.
- **Разница в ключах notification preferences у manager/admin**
  (`ManagerController::updateNotifications()` и
  `AdminController::updateNotifications()` сохраняют 3 ключа против 5 у
  туриста, без `trip_reminders`/`promotions`) — не дефект: trip-reminder
  письма (`SendTripReminders.php`) отправляются только владельцу заявки,
  manager/admin никогда не являются получателями; `promotions` не
  проверяется вообще нигде в `app/` ни для одной роли. Отсутствующие
  контролы не могут ничем управлять — UI для несуществующих у этих ролей
  типов уведомлений не требуется.
- **Очередь и scheduler** (`QUEUE_CONNECTION=sync`, отсутствие
  Supervisor/cron/Task Scheduler wiring) — Stage 13 production readiness,
  не Stage 10 функциональный blocker; runtime queue-конфигурация не
  менялась.
- **WebSocket/Echo, Telegram/SMS, полноценный notification center,
  mark-all-read** — явно отложенный backlog (см. ниже), не блокируют
  закрытие Stage 10.

Следующий этап после Stage 10 — **Stage 11. Security, reliability,
performance**, в процессе (см. раздел ниже).

## Stage 11. Security, reliability, performance — 🟡 IN PROGRESS

Stage 11 не завершён. На текущий функциональный checkpoint `f1a0fff0`
завершено 12 маленьких semantic slice, один линейный commit chain.
Последний функциональный checkpoint —
`f1a0fff0b1f0b93c654b836e93dd5049eae2569f`
(`fix: preserve tourist data on deletion failure`); его непосредственный
родитель — `d17255e74cc77ce56bd42bcbeaa47d590aa46f1d`
(`fix: align tourist deletion session teardown`):

1. `8c23646b575d6a17636509edc1051ae901b84d96` — throttle public registration.
2. `32dd86a1f12e884cd0a52930b606b6631aff139c` — throttle password reset link requests.
3. `baedfac72cd9e1ce65ce60e86e5d80bc6e33e97f` — audit admin user deletion.
4. `0a000e1aa50e7a33b34bf69805e67fd4ed916a13` — audit admin role removal.
5. `b5923a868b0057c720b780ee773ce11e7159131d` — eliminate manager client list n+1 queries.
6. `a3231a06d6aeaa846b1cd6af4a0e7f878f53bc6e` — audit admin role replacement.
7. `c5aad3a4ebd9bde5b713df030326e3b22a00460d` — audit admin role assignment.
8. `dfec2733bdb7ba6fc08e410e88be13382ea77aae` — audit admin self-account deletion.
9. `e72aaaca7dc2bdd1c0933f401ef8df8b87e25228` — audit manager settings self-deletion.
10. `ab80e8e23ae51554423a20ae783f21be141ecc65` — audit tourist self-account deletion.
11. `d17255e74cc77ce56bd42bcbeaa47d590aa46f1d` — align tourist deletion session teardown.
12. `f1a0fff0b1f0b93c654b836e93dd5049eae2569f` — preserve tourist data on deletion failure.

Между слайсом 12 Stage 10 и слайсом 1 Stage 11 в истории есть отдельный
standalone commit `1a2aeef67fb80fbbe15a4b8ebda09728e5cd4f6e`
(`feat: update company logo`) — одобренное обновление визуального актива,
не относится к security/reliability/performance и **не входит** в счёт
12 слайсов Stage 11.

### Rate limiting

- слайс 1 (`8c23646b`) добавляет `throttle:6,1` на публичный
  `POST register` (`routes/auth.php`);
- слайс 2 (`32dd86a1`) добавляет `throttle:6,1` на
  `POST forgot-password` (`routes/auth.php`, маршрут `password.email`);
- существующее поведение успешной регистрации и успешного запроса
  password-reset ссылки сохранено без изменений;
- зависимости и миграции не менялись — используется штатный Laravel
  `throttle`-middleware.

### Security audit logging

Слайсы 3, 4, 6, 7, 8, 9, 10 добавляют `Log::warning(...)` со связкой
actor/target (и, где применимо, списком ролей) строго **после**
подтверждённой успешной мутации:

- `baedfac7` — `Admin deleted user account` (`AdminController::deleteUser`);
- `0a000e1a` — `Admin removed user role`;
- `a3231a06` — `Admin updated user role` (`added_roles`/`removed_roles`/`resulting_roles`);
- `c5aad3a4` — `Admin assigned user role`;
- `dfec2733` — `Admin deleted own account` (`AdminController`);
- `e72aaaca` — `User deleted own account via manager settings` (`ManagerController`);
- `ab80e8e2` — `Tourist deleted own account` (`CabinetController`).

Подтверждённые гарантии:

- логирование срабатывает только после успешного `delete()`/мутации роли,
  не до и не при ошибке валидации (проверка внутри `if`-блока успешной
  мутации, например `if ($deleted)`);
- неуспешная попытка (например, отклонённое самоудаление собственного
  аккаунта администратором до мутации) не создаёт audit-запись;
- централизованной audit-подсистемы (единой таблицы, сервиса или UI для
  просмотра логов) не существует — используется стандартный Laravel `Log`
  facade поверх существующего log-канала.

### Performance

- слайс 5 (`b5923a86`) устраняет N+1 в `ManagerController` client-list:
  вместо запроса `active_bookings`/`latest_booking` на каждого клиента в
  цикле используется один коррелированный подзапрос (`addSelect` +
  `latest_booking_id`) и один batched `whereIn`-запрос для гидратации;
- область подтверждена именно для manager client-list; широкая
  application-wide оптимизация запросов не выполнялась и не заявляется.

### Session teardown

Достижимый application-owned logout-flow audit завершён с вердиктом:

```text
NO_REMAINING_LOGOUT_TEARDOWN_DEFECT_FOUND
```

Достижимые обычные logout, email-change и admin/manager/tourist
self-deletion потоки используют требуемую последовательность logout,
session invalidation и CSRF-token rotation. Слайс 11 (`d17255e7`) добавил
недостающие `$request->session()->invalidate()` и
`$request->session()->regenerateToken()` в tourist self-deletion flow
(`CabinetController::destroyAccount`) следом за `Auth::logout()`. Эта
работа закрыта и не переоткрывается в разделе «Ближайший следующий шаг».

### Tourist partial-failure reliability

Слайс 12 (`f1a0fff0`) убирает избыточные pre-deletion вызовы в
`CabinetController::destroyAccount`:

```php
$user->bookings()->delete();
$user->bonusAccount()->delete();
```

- успешное удаление `User` продолжает полагаться на существующие
  `ON DELETE CASCADE` foreign keys;
- если listener удаления `User` бросает исключение до SQL `DELETE`:
  - `User` остаётся в базе;
  - `Booking` остаётся и не помечается soft-deleted;
  - `BonusAccount` остаётся в базе;
- изменение не заявляет filesystem-транзакционность;
- порядок logout/session teardown в этом слайсе не менялся (сохранён
  результат слайса 11);
- manager self-deletion flow в этом слайсе не менялся.

### Верификация Stage 11 (текущий срез, checkpoint `f1a0fff0`)

- focused suite последнего слайса: 15 tests / 106 assertions;
- full PHPUnit: 632 tests / 2777 assertions;
- PHP `C:\wamp\bin\php\php8.3.32\php.exe` (8.3.32);
- PHPUnit 10.5.20, только SQLite `:memory:`;
- canonical migrations: 53 Ran / 0 Pending;
- local, origin tracking и live origin совпадали на
  `f1a0fff0b1f0b93c654b836e93dd5049eae2569f`;
- working tree clean после commit и push.

Известное non-blocking предупреждение о deprecated PHPUnit XML schema
остаётся в общем backlog — конфигурация PHPUnit в рамках Stage 11 не
мигрировалась.

### Stage 11 — следующий кандидат

Stage 11 не завершён. Следующий обоснованный ограниченный кандидат —
**partial-failure reliability самоудаления аккаунта менеджера**. Уже
установленные факты (реализация ещё не выполнена):

- заявки менеджера отвязываются (`unassigned`) до финального удаления
  `User`;
- файлы и строки персональных документов обрабатываются до финального
  удаления `User`;
- более поздний сбой может оставить частичные, несогласованные мутации;
- database-транзакции не могут откатить физическое удаление файлов из
  файловой системы;
- manager-flow требует отдельного ограниченного implementation-плана;
- никакая правка manager-flow в рамках этого кандидата ещё не выполнена.

Следующее действие требует отдельного guarded, узкого Plan с exact
allowed paths и focused failure-injection покрытием — без него широкая
реализация не начинается.

## Stage 12. Dependency upgrades — ⏸ DEFERRED

Только после стабилизации функционала и отдельным изолированным slice.

## Stage 13. Production readiness — 📋

- deployment и rollback;
- production backup;
- queues и cron;
- asset build;
- `APP_DEBUG=false`;
- SSL;
- private storage;
- production smoke;
- monitoring.

---

## Общий backlog

- расписание для `news:sync-rss`;
- реальный `news:sync-rss` против canonical MySQL только по отдельному
  operational plan;
- статический `sitemap.xml` с 272 устаревшими `lastmod` 2024 года;
- актуализация route contract в `robots.txt`;
- расширение page-specific title/meta description/Open Graph coverage;
- постоянный manager live account отсутствует; изолированная временная
  smoke-стратегия проверена;
- deprecated PHPUnit XML schema (non-blocking, не менялось в Stage 10);
- recovery artifact retention policy;
- единый E2E lifecycle scenario;
- история и аудит заявки;
- WebSocket/unread counters для чата;
- WebSocket/Echo real-time delivery для уведомлений;
- полноценный notification center (список/пагинация всех уведомлений);
- mark-all-read действие;
- Telegram/SMS-доставка уведомлений;
- queue-worker deployment configuration (Supervisor/systemd) — сейчас
  `QUEUE_CONNECTION=sync`, отдельный operational plan до Stage 13;
- scheduler operational wiring (production cron / Windows Task Scheduler
  для `schedule:run`) — команда `bookings:send-trip-reminders` уже
  реализована и запланирована в `app/Console/Kernel.php`, но не привязана
  к реальному cron/Task Scheduler в этом окружении;
- мёртвые/несовпадающие счётчики sidebar (`$pendingBookingsCount` нигде
  не устанавливается; manager sidebar проверяет `$unreadMessagesCount`,
  контроллер передаёт другие имена переменных) — code-hygiene cleanup,
  не связано с таблицей `notifications`, рабочее поведение туриста не
  трогать;
- **Stage 11, текущий пункт (не завершён):** partial-failure reliability
  самоудаления аккаунта менеджера — manager bookings отвязываются и
  personal-document файлы/строки обрабатываются до финального удаления
  `User`, более поздний сбой может оставить частичные мутации,
  database-транзакции не откатывают удаление файлов; требуется отдельный
  guarded, узкий implementation-план с exact allowed paths и focused
  failure-injection покрытием — реализация ещё не начата.

## Жёсткие ограничения

Без отдельного утверждённого плана запрещено:

- широкая реализация нового функционала за пределами одного выбранного
  маленького semantic slice;
- `composer update` и другие обновления зависимостей;
- `npm update`;
- `npm audit fix`;
- migrations/seed/import/reset/wipe;
- `legacy:import-v4 --execute`;
- PHPUnit против canonical MySQL;
- любые прямые операции над canonical MySQL вне guarded operational плана;
- реальный `news:sync-rss` против canonical MySQL;
- удаление recovery/rollback artifacts;
- production-интеграции и реальные внешние запросы к провайдерам (Sletat,
  Coral Travel, email/SMS/Telegram-провайдеры и др.) до отдельного guarded
  operational плана;
- деструктивные операции над данными, ветками или репозиторием без
  явного запроса пользователя.

## Ближайший следующий шаг

Stage 10 закрыт. Stage 11 (security, reliability, performance) **в
процессе** — 12 завершённых semantic slice. Последний функциональный
checkpoint — `f1a0fff0b1f0b93c654b836e93dd5049eae2569f`
(`fix: preserve tourist data on deletion failure`, см. раздел Stage 11
выше); он не меняется публикацией текущего docs-only checkpoint.

1. Следующий обоснованный ограниченный кандидат Stage 11 —
   **partial-failure reliability самоудаления аккаунта менеджера**;
   реализация ещё не выполнена.
2. Как и для каждого предыдущего слайса и этапа — отдельный read-only Plan
   и exact allowed paths требуются до начала любых новых правок для этого
   кандидата.
3. До утверждения scope не начинать широкую реализацию manager-flow правок
   или иных ещё не выбранных Stage 11 механизмов; миграции, canonical DB
   операции, production-интеграции и деструктивные действия выполнять
   только по отдельному guarded плану.
