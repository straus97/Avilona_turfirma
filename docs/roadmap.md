# Avilona_turfirma — актуальная дорожная карта

Дата checkpoint: **2026-08-14**

Текущий функциональный checkpoint:

```text
d66035541e8ab96a801b011168663758181c1e25
feat: separate public personal data consent
```

Родительский commit текущего функционального checkpoint:

```text
6c5c33bede6530d3aae66a93a27c18e578c1330e
fix: align public company details
```

Предыдущий documentation/source checkpoint:

```text
e067124c4323e82ab5d10d8e6aa1491029fb767b
docs: close stage 12
```

Текущий репозиторный/документационный HEAD всегда определяется Git и
после docs-only commit, фиксирующего эти правки, станет новее
`d6603554` — это не меняет ни текущий функциональный checkpoint, ни его
test baseline.

Внешний source set (Project Sources / handoff / roadmap export /
new-chat prompt / manifest / source archive) в рамках этой
documentation-only задачи **не генерировался и не обновлялся**. Он должен
быть создан и независимо проверен отдельным guarded source-refresh шагом
из нового documentation HEAD.

Текущий проверенный тестовый baseline (Stage 13):

```text
Full:    704 tests / 3128 assertions, exit code 0
Focused (PublicPersonalDataConsentTest): 22 tests / 46 assertions
PHPUnit 11.5.56
SQLite :memory: (единственно допустимая БД для PHPUnit)
Canonical migrations: 53 Ran / 0 Pending (последняя независимо проверенная
  canonical-верификация; Stage 13 не добавлял миграций и не требовал
  нового canonical-прогона)
Canonical MySQL: turfirma_rebuild_v4, порт 3308
PHP: C:\wamp\bin\php\php8.3.32\php.exe (8.3.32)
```

Арифметика baseline независимо сверена: 682 tests / 3081 assertions
(проверенный baseline непосредственно перед слайсом personal-data-consent)
+ 22 tests / 46 assertions нового `PublicPersonalDataConsentTest`
+ ровно 1 assertion, добавленный новым публичным маршрутом к существующему
`PublicReviewDetailRouteConsistencyTest` (он выполняет по одному assertion
на каждый зарегистрированный маршрут) = 704 tests / 3128 assertions.

Подтверждённое окружение/toolchain (зафиксировано в Stage 12, файлы
зависимостей в Stage 13 не менялись): PHP 8.3.32; canonical PHP executable
`C:\wamp\bin\php\php8.3.32\php.exe`; Composer 2.8.3; Node.js 24.18.1;
npm 11.12.1; Laravel 12.65.0; Vite 7.3.6 + `laravel-vite-plugin` 2.1.0.

Известное non-blocking предупреждение: `phpunit.xml` использует deprecated
schema — backlog item, не исправлено. Зафиксированное в Stage 12
предупреждение Vite о пересечении `publicDir`/`outDir` относится к
конфигурации до `0316e7c7`: с этого коммита `publicDir` установлен в
`false`, поэтому пересечения в конфигурации больше нет; отдельный
подтверждающий production-build прогон в рамках Stage 13 документации не
выполнялся.

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
- Stage 11 — ✅ COMPLETE (17 slices; security, reliability, performance)
- Stage 12 — ✅ COMPLETE (dependency upgrades; repository hygiene, Composer/npm security modernization, Laravel 12, Vite 7, npm audit = 0, Composer advisories = 0)
- Stage 13 — 🚧 **IN PROGRESS** (production readiness / локальный runtime /
  публичная поверхность / подготовка к деплою). **НЕ завершён**: 8 слайсов
  завершено и запушено, разделы A–H остаются.

Все числовые baseline и checkpoint внутри разделов закрытых этапов
(Stage 5–12) сохранены **как исторические** и не описывают текущее
состояние.

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

## Stage 11. Security, reliability, performance — ✅ COMPLETE

Stage 11 завершён. Завершено 17 functional semantic slice, один линейный
commit chain. Финальный функциональный checkpoint —
`f3279a425458e69b64927f7fdb3b19c5e277ad9f`
(`fix: invalidate stale sessions after password changes`); его
непосредственный родитель — `2e6ed73e037f1714fa547a82b775f082ad3da973`
(`fix: make booking cancellation atomic`):

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
13. `c5f9cd77116e17f306efe02bd73a3be814be5042` — preserve manager data on deletion failure.
14. `17b177194eb8d9110359a00ff04f4f7652c52eca` — preserve personal document files on deletion failure.
15. `52e1c2e38eb25fe8bfda65bc52c4e8583cfeb62e` — eliminate admin chat unread count n plus one.
16. `2e6ed73e037f1714fa547a82b775f082ad3da973` — make booking cancellation atomic.
17. `f3279a425458e69b64927f7fdb3b19c5e277ad9f` — invalidate stale sessions after password changes.

Между слайсом 12 Stage 10 и слайсом 1 Stage 11 в истории есть отдельный
standalone commit `1a2aeef67fb80fbbe15a4b8ebda09728e5cd4f6e`
(`feat: update company logo`) — одобренное обновление визуального актива,
не относится к security/reliability/performance и **не входит** в счёт
17 слайсов Stage 11. Между слайсом 12 и слайсом 13 в истории есть отдельный
documentation-only commit `ebdb45bd2fd5b90e5ab7793d5c604df15ca75e2c`
(`docs: update stage 11 checkpoint`) — он также не входит в счёт
функциональных слайсов.

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

### Manager partial-failure reliability

Слайс 13 (`c5f9cd77`) убирает преждевременные мутации в manager
self-deletion flow — снят предварительный booking-unassignment и
предварительные мутации персональных документов до финального удаления
`User`:

- успешное удаление продолжает полагаться на существующее foreign-key-
  поведение;
- если удаление `User` бросает исключение, сохраняются: сам менеджер,
  связанное booking assignment, строка персонального документа и файл;
- широкая filesystem-транзакционность не заявляется.

### Personal-document deletion ordering

Слайс 14 (`17b17719`) меняет порядок удаления персонального документа:
удаление строки БД происходит до удаления физического файла:

- если удаление модели бросает исключение, сохраняются и строка, и файл;
- применяется к достижимым manager- и tourist-flow удаления персональных
  документов;
- успешное удаление по-прежнему удаляет и строку, и файл.

### Admin chat unread-count query efficiency

Слайс 15 (`52e1c2e3`) убирает per-booking запрос unread-count из списка
чатов администратора:

- unread-счётчики загружаются одним bounded batched-запросом;
- рост числа запросов покрыт отдельным focused-тестом;
- глобальная оптимизация всех chat/list endpoint не заявляется.

### Booking cancellation atomicity

Слайс 16 (`2e6ed73e`) оборачивает мутацию статуса заявки и восстановление
мест тура в одну database-транзакцию:

- исключение при восстановлении мест или `false`-результат проверки
  вместимости откатывает отмену целиком;
- успешная отмена обновляет статус и восстанавливает места ровно один раз;
- диспатч уведомления намеренно остаётся вне database-транзакции и
  устойчив к сбоям;
- полный авторитетный дизайн booking-inventory не заявляется.

### Password-change session invalidation

Слайс 17 (`f3279a42`) включает
`Illuminate\Session\Middleware\AuthenticateSession` один раз в глобальной
web middleware group:

- сессия, выполняющая аутентифицированную смену пароля, остаётся
  аутентифицированной;
- другая, независимо аутентифицированная и уже primed-сессия отклоняется
  на следующем web-запросе после смены пароля;
- forgot-password reset аналогично инвалидирует отдельно primed
  аутентифицированную сессию на следующем запросе;
- guest-маршруты не затронуты;
- изменений контроллеров, маршрутов, миграций, зависимостей,
  session-драйвера или схемы БД не потребовалось;
- принятое deployment-bootstrap ограничение: сессия, созданная до деплоя
  и ещё не прошедшая через `AuthenticateSession`, не имеет закэшированного
  password hash и на первом post-deployment запросе primed, а не отклонена.

### Верификация Stage 11 (финальный checkpoint `f3279a42`)

- focused suite финального слайса: 17 tests / 82 assertions;
- full PHPUnit: 644 tests / 2861 assertions;
- PHP `C:\wamp\bin\php\php8.3.32\php.exe` (8.3.32);
- PHPUnit 10.5.20, только SQLite `:memory:`;
- canonical migrations: 53 Ran / 0 Pending — последняя независимо
  проверенная canonical-верификация; в финальных слайсах миграции повторно
  не запускались и не проверялись против canonical MySQL заново;
- local, origin tracking и live origin совпадали на
  `f3279a425458e69b64927f7fdb3b19c5e277ad9f`;
- working tree clean, staging area пуста после commit и push.

Известное non-blocking предупреждение о deprecated PHPUnit XML schema
остаётся в общем backlog — конфигурация PHPUnit в рамках Stage 11 не
мигрировалась.

### Stage 11 — закрытие

Guarded closure audit Stage 11 рассмотрел оставшиеся ограниченные
кандидаты и выбрал финальным кандидатом password-session invalidation.
Кандидат реализован, протестирован, закоммичен и запушен (слайс 17,
`f3279a42`). Stage 11 **закрыт** — 17 функциональных semantic slice,
полный упорядоченный commit-инвентарь приведён выше.

Следующий продуктовый этап — **Stage 12. Dependency upgrades**, теперь
**завершён** — см. раздел ниже.

## Stage 12. Dependency upgrades — ✅ COMPLETE

Stage 12 начат отдельным изолированным slice после стабилизации
функционала (Stage 11 закрыт) и теперь завершён. Финальный dependency
checkpoint Stage 12 — `61d2fef9ee30ecae2bc1f1d948e16ac2879b1aae`
(`chore: update picomatch to 2.3.2`). Это dependency checkpoint, не
functional checkpoint; функциональным checkpoint на момент закрытия
Stage 12 оставался `f3279a42`. **Оба значения исторические** — текущий
функциональный checkpoint указан в начале документа.

Ledger (историческая хронология первых слайсов, затем закрывающая работа):

1. **Read-only dependency inventory — COMPLETE.** Прочитаны и сопоставлены
   `composer.json`, `composer.lock`, `package.json`, `package-lock.json`.
   Подтверждённое окружение: PHP 8.3.32; Composer 2.8.3 (через pinned PHP
   executable и `composer.phar`); Node.js 24.18.1; npm 11.12.1. Найдено
   118 locked Composer-пакетов (80 production / 38 development) и 203
   resolved npm package entries, конкретные Composer/npm advisory
   evidence; кандидаты классифицированы как security-critical,
   framework-major, minor/patch, transitive-only, informational/no-action.
   Обновление зависимостей во время инвентаризации не выполнялось.
2. **Первый кандидат — Guzzle.** Composer-only кандидат:
   `guzzlehttp/guzzle` 7.8.1 → 7.15.2, `guzzlehttp/promises` 2.0.2 → 2.5.1,
   `guzzlehttp/psr7` 2.6.2 → 2.13.0. Guarded dry-run: 0 installs / 3
   updates / 0 removals. Ограничение `composer.json` (`^7.2`) уже
   допускает 7.15.2 — изменение `composer.json` для будущего повтора не
   ожидается.
3. **Прерванное первое выполнение Guzzle-обновления — полностью
   откачено.** Одобренная команда `composer update` дала ту же
   трёхпакетную дельту, но выполнение было остановлено до валидации:
   `vendor/` оказался неожиданно отслеживаемым Git несмотря на `/vendor`
   в `.gitignore`. Временно менялись `composer.lock` и Guzzle-related
   файлы под `vendor/`; изменение guarded-откачено; итоговые версии
   остались 7.8.1 / 2.0.2 / 2.6.2. Обновление Guzzle **не завершено**.
4. **Аудит tracked-vendor политики.** 8657 отслеживаемых путей `vendor/`
   при уже присутствующем `/vendor` в `.gitignore`; намеренная
   committed-vendor deployment-политика в истории/документации не
   установлена; tracking классифицирован как случайный — новые файлы
   зависимостей могли скрываться `.gitignore`, пока старые оставались
   отслеживаемыми, что делало бы будущие committed vendor snapshots
   неполными.
5. **Vendor-untracking repository-hygiene slice — COMPLETE.** Commit
   `ec0b20c5` (родитель `85a7b22b`) убрал ровно 8657 существующих путей
   `vendor/` из Git index (только deletions под `vendor/`, ни один путь
   вне `vendor/` не затронут); рабочая копия `vendor/` осталась
   физически присутствующей и byte-identical; `.gitignore` уже покрывал
   `/vendor`; `composer.json`/`composer.lock` не менялись; версии
   Guzzle-семейства остались 7.8.1 / 2.0.2 / 2.6.2; local, origin-tracking
   и live remote HEAD совпали на `ec0b20c5`; working tree чист после
   push. PHPUnit не запускался — слайс менял только git tracking state.
6. **Git normalization recovery evidence (вспомогательное).** Прерванный
   rollback временно дал status discrepancy из-за `core.autocrlf=true`,
   LF blobs в индексе, CRLF working-tree представления и устаревших
   index stat-метаданных. Read-only аудит подтвердил для всех 82
   затронутых путей совпадение normalized working-tree object ID с
   индексом и индекса с HEAD. Metadata-only index refresh оставил
   репозиторий content-clean и status-clean до vendor-untracking слайса.
   Вспомогательное evidence, не отдельная product milestone.
7. **Composer dependency/security модернизация — COMPLETE.** Ранее
   откаченный слайс Guzzle (п. 2/3 выше) выполнен повторно и доведён до
   конца: `guzzlehttp/guzzle` обновлён до 7.15.2. `laravel/framework`
   проведён through guarded Laravel 10 patch maintenance → Laravel 11
   bridge → Laravel 12, финально `v12.65.0`. Carbon line модернизирована
   до Carbon 3.x. `phpunit/phpunit` обновлён до 11.5.56.
8. **Frontend dependency/security модернизация — COMPLETE.** webpack- и
   sass/sass-loader-инструменты удалены после подтверждения, что они не
   используются активной сборкой. Выполнена миграция на Vite 7.3.6 +
   `laravel-vite-plugin` 2.1.0 с адаптацией Laravel-совместимого manifest
   (`public/build/manifest.json`, содержит `resources/css/app.css` и
   `resources/js/app.js`). Tailwind обновлён до 3.4.19. Корневой уязвимый
   `picomatch` обновлён с 2.3.1 до 2.3.2 (dependency checkpoint
   `61d2fef9`).
9. **Repository hygiene — подтверждено.** `vendor` и `node_modules` не
   отслеживаются Git (tracked count = 0 для обоих).
10. **Финальная верификация безопасности — COMPLETE.** `npm audit`: 0
    total vulnerabilities. Composer locked audit под PHP 8.3.32: 0
    advisories, 0 abandoned packages. `composer check-platform-reqs
    --lock` под PHP 8.3.32: PASS.
11. **Production build validation — COMPLETE.** Production Vite build
    успешен; известное non-blocking предупреждение Vite из-за
    пересечения `publicDir`/`outDir` осталось не исправленным.
12. **Full PHPUnit validation — COMPLETE.** 644 tests / 2861 assertions,
    PHPUnit 11.5.56, SQLite `:memory:`, exit code 0. Известное
    non-blocking PHPUnit deprecation-предупреждение осталось не
    исправленным.

Финальные версии зависимостей: `laravel/framework` 12.65.0,
`guzzlehttp/guzzle` 7.15.2, `phpunit/phpunit` 11.5.56, Carbon 3.x,
`vite` 7.3.6, `laravel-vite-plugin` 2.1.0, `tailwindcss` 3.4.19,
корневой `picomatch` 2.3.2; webpack и sass/sass-loader удалены как
неиспользуемые.

Test/migration baseline **на момент закрытия Stage 12 (историческое
значение, не текущее)**: full PHPUnit 644 tests / 2861 assertions под
PHPUnit 11.5.56 (число тестов/assertions не изменилось при обновлении
PHPUnit), canonical migrations 53 Ran / 0 Pending (Stage 12
dependency-работа не требовала нового canonical-прогона). Текущий
проверенный baseline — 704 tests / 3128 assertions, см. начало документа.

Отложенное обслуживание (не блокеры закрытия Stage 12):

- Composer non-security maintenance: `fakerphp/faker` v1.23.1 →
  v1.24.1, `laravel/pint` v1.15.3 → v1.30.5;
- Composer major-миграции, намеренно отложенные: `guzzlehttp/guzzle`
  7.15.2 → 8.0.2, `laravel/framework` v12.65.0 → v13.24.0,
  `phpmailer/phpmailer` v6.9.1 → v7.1.1, `phpunit/phpunit` 11.5.56 →
  12.5.33 (под canonical PHP 8.3.32 Composer audit),
  `spatie/laravel-sluggable` 3.8.1 → 4.0.3;
- npm non-security same-major maintenance: `@popperjs/core` 2.11.6 →
  2.11.8, `@tailwindcss/forms` 0.5.3 → 0.5.11, `alpinejs` 3.12.0 →
  3.16.0, `autoprefixer` 10.4.14 → 10.5.4, `bootstrap` 5.3.0-alpha1 →
  5.3.8;
- npm major-миграции, намеренно отложенные: `laravel-vite-plugin`
  2.1.0 → 3.1.3, `tailwindcss` 3.4.19 → 4.3.3, `vite` 7.3.6 → 8.2.1.

Это отложенное обслуживание и future major-миграции, а не причина
считать Stage 12 незакрытым; верифицированные security-аудиты (npm и
Composer) на момент закрытия Stage 12 чисты.

Stage 12 **закрыт**; документационное закрытие зафиксировано commit
`e067124c` (`docs: close stage 12`).

## Stage 13. Production readiness и публичная поверхность — 🚧 IN PROGRESS

Stage 13 **начат и не закрыт**. Линейная цепочка коммитов от Stage 12
closure commit `e067124c` до текущего HEAD `d6603554`. Зависимости и
миграции в Stage 13 не менялись.

### Завершено и запушено (8 слайсов)

1. `0316e7c73b454ea59899deb3c57247853606a99c` — `build: stop tracking
   generated Vite output`. 2288 путей под `public/build/` убраны из Git
   index (`.gitignore` уже содержал `/public/build`); в `vite.config.js`
   `publicDir` изменён с `'public'` на `false`, устранив пересечение
   `publicDir`/`outDir` в конфигурации. Composer/npm не запускались.
2. `712ff965e98af3fff74068694436d0793e98aa20` — `fix: resolve public
   browser runtime errors`. `lazysizes` переведён с `async` на `defer`;
   присвоение `summary.textContent` в каталоге туров защищено проверкой
   существования элемента.
3. `1b66c5484ff8ccfe9cbcf07fb312e41fdbaf4774` — `fix: improve mobile
   responsive layout`. Формы «Контакты»/«Отзывы» переведены на
   `col-12 col-md-6`; блок отзыва — на `col-3` / `col-9` на мобильных;
   добавлен переносимый центрируемый блок `.pagination`.
4. `3000c2984ddb2a055ae95057356cbdd54c225e50` — `fix: refine tablet
   responsive layout`. Колонка счётчиков в подвале — `col-6 col-md-12
   col-xl-6` с `g-0`; блок отзыва уточнён до `col-md-2` / `col-md-10` и
   `col-lg-1` / `col-lg-11`.
5. `88d0fcc6689cbfb3091bb8eab523aee1950198c2` — `fix: clean up public
   development notices`. Удалено модальное окно «Важная информация» с
   текстом о том, что сайт находится в стадии активной разработки, вместе
   с личным email для сообщений об ошибках, и все три кнопки его вызова;
   из подвалов публичного и профильного layout убран персональный кредит
   разработчика со ссылкой на личный профиль; год копирайта стал
   динамическим (`{{ date('Y') }}`) вместо 2024 / 2023.
6. `a9bf145d32cacc9d3761a0265e32eb69e4c828df` — `feat: add cookie consent
   and analytics gating`. Стабильное поведение: cookie
   `avilona_cookie_consent` со значениями `v1_all` / `v1_necessary`
   (любое иное, отсутствующее или несовпадающее по версии значение
   нормализуется в `undecided`); аналитика (Яндекс.Метрика, LiveInternet,
   Top.Mail.Ru) рендерится сервером **только** при согласии на аналитику;
   публичная информационная страница `GET /cookies` (`cookies.info`);
   постоянная точка входа «Настройки cookie» в подвале для изменения
   выбора; `App\Support\CookieConsent` — единственный источник истины
   нормализации; `CacheResponse` изолирует кэш по согласию, добавляя
   нормализованное состояние в ключ, что ограничивает число вариантов
   кэша на URL тремя; ad-hoc кэши страниц «Контакты» и «Словарь
   путешественника» удалены; `avilona_cookie_consent` внесён в
   `EncryptCookies::$except` как единственное исключение.
7. `6c5c33bede6530d3aae66a93a27c18e578c1330e` — `fix: align public company
   details`. Фактический адрес офиса — 198261, Россия, Санкт-Петербург,
   ул. Генерала Симоняка, д. 10; юридический адрес — 191119, Россия,
   Санкт-Петербург, ул. Звенигородская, д. 22, литера А, офис 053,
   пом. 7Н; ИНН 7805502454; КПП 784001001; ОГРН 1097847289803.
   Юридический и фактический адреса **намеренно различаются**.
8. `d66035541e8ab96a801b011168663758181c1e25` — `feat: separate public
   personal data consent` (текущий HEAD). «Главная» и «Контакты» получили
   два независимых обязательных подтверждения: `agree` (Пользовательское
   соглашение) и `personal_data_consent` (отдельное согласие на обработку
   персональных данных); оба серверных правила используют `accepted`;
   добавлен публичный маршрут `GET /personal-data-consent`
   (`personal_data_consent.info`) с отдельной visitor-facing страницей,
   ссылающейся на существующую политику обработки персональных данных;
   evidence согласия (отметка времени, версия, объём, IP) **не
   сохраняется**; миграций не добавлялось; «Отзывы», «Регистрация» и
   «Бронирование» намеренно не менялись.

### Локальный runtime и браузерные evidence

Для Stage 13 настроен изолированный локальный WAMP-стенд браузерной
проверки Avilona: `avilona.local` → `127.0.0.2`, без изменения обычных
проектов на `localhost`. Приватные файлы evidence в репозиторий не
копируются.

Последняя браузерная QA раздельного согласия («Главная» + «Контакты»):
desktop1440 1440×1000 — PASS; mobile390 390×844 — PASS; проверены
маршруты `/`, `/contacts`, `/personal-data-consent`; два отдельных
обязательных элемента согласия подтверждены в реальном DOM; независимость
их состояний подтверждена; ссылка на Пользовательское соглашение
проверена; ссылка на страницу согласия проверена; страница согласия
доступна анонимно; ссылка на PDF политики проверена; горизонтального
переполнения нет; исключений локального runtime нет; ошибок консоли нет;
отправка формы не выполнялась; запросов к провайдерам не выполнялось;
репозиторий остался неизменным.

### Оставшийся путь Stage 13 до production и передачи

Ни один пункт ниже не реализован, и ни одно перечисленное решение не
является утверждённым.

**A. Согласие на обработку персональных данных / публикацию для отзывов.**
Известные границы: форма отзывов по-прежнему содержит старую объединённую
формулировку и правило `agree => required`; содержимое отзыва и имя могут
публиковаться публично; email требуется формой, но текущей реализацией не
сохраняется. Требуется отдельный рассмотренный slice. Финальные
юридические формулировки в этих канонических документах не фиксируются.

**B. Согласие и применимость политики для публичной регистрации.**
Известные границы: публичная регистрация собирает имя, email и пароль;
текущая документальная/политическая база не покрывает явно цель обработки
«создание аккаунта / личный кабинет». Требуется отдельное
контентное/юридическое/бизнес-решение до реализации.

**C. Решение о хранении evidence согласия.** Текущее согласие на
«Главной»/«Контактах» валидируется, но не сохраняется. Возможное будущее
хранение отметки времени, версии, объёма и факта отзыва **не реализовано**
и не должно представляться как принятое решение. Любая работа со
схемой/миграциями остаётся отдельно gated.

**D. Гостевой сценарий бронирования.** Отдельный известный функциональный
дефект: публичный UI бронирования и защищённый `auth` endpoint
бронирования не согласованы для реального анонимного гостевого потока.
Не исправляется документацией и не смешивается с работой по согласиям.

**E. Финальная локальная проверка production-readiness.**

**F. Деплой на хостинг по отдельному guarded операционному плану.**

**G. Production smoke-проверка после деплоя.**

**H. Финальный независимый аудит сайта после production-валидации:**
визуальное оформление; вёрстка/UX; контент; функциональность;
ошибки/незавершённые области; адаптивность; общее качество передачи.
Аудит **ещё не выполнялся**.

Ранее зафиксированные production-readiness пункты остаются в области
Stage 13 и распределяются по разделам E–G: deployment и rollback;
production backup; queues и cron (сейчас `QUEUE_CONNECTION=sync`, worker и
scheduler не привязаны к реальному cron / Task Scheduler); asset build;
`APP_DEBUG=false`; SSL; private storage; production smoke; monitoring.

### Граница по поиску туров / агрегатору

Текущий виджет поиска/подбора туров — **временная заглушка**. Он
намеренно не доводится до финального вида в рамках текущей Stage 13
работы по согласиям и юридическим материалам.

Итоговое решение — отдельное продуктовое решение более позднего этапа,
которое должно оценить: реалистичные доступные/бесплатные варианты
виджетов и агрегаторов; API/фиды туроператоров; собственный поиск и
агрегацию; и только после этого — скрапинг там, где это допустимо по
условиям использования и стабильно. Любое итоговое решение должно
укладываться в существующий процесс личного кабинета и заявок/заказов
клиента Avilona.

Реальные запросы к провайдерам и провайдерские интеграции в рамках
Stage 13 документационной работы не инициировались.

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
  `QUEUE_CONNECTION=sync`; относится к области Stage 13 (разделы E–F) и
  требует отдельного operational plan;
- scheduler operational wiring (production cron / Windows Task Scheduler
  для `schedule:run`) — команда `bookings:send-trip-reminders` уже
  реализована и запланирована в `app/Console/Kernel.php`, но не привязана
  к реальному cron/Task Scheduler в этом окружении;
- мёртвые/несовпадающие счётчики sidebar (`$pendingBookingsCount` нигде
  не устанавливается; manager sidebar проверяет `$unreadMessagesCount`,
  контроллер передаёт другие имена переменных) — code-hygiene cleanup,
  не связано с таблицей `notifications`, рабочее поведение туриста не
  трогать;
- N+1 unread-count поведение в manager chat-list, обнаруженное в ходе
  Stage 11 closure audit — подтверждённый ограниченный performance-
  кандидат, но не выбран в качестве финального Stage 11 слайса и **не**
  устраняется admin chat-list оптимизацией (слайс 15, `52e1c2e3`);
  требует отдельного рассмотрения;
- email в форме отзывов требуется валидацией, но не сохраняется текущей
  реализацией — рассматривать вместе со Stage 13, раздел A, а не отдельно;
- рассогласование публичного UI бронирования и защищённого `auth` endpoint
  бронирования для анонимного гостя — Stage 13, раздел D;
- available_seats как предполагаемый authoritative application-owned
  inventory counter для бронирований — Stage 11 closure audit не нашёл
  достаточных оснований классифицировать текущее поведение booking
  creation / `available_seats` как подтверждённый дефект; любая будущая
  работа здесь требует отдельного booking-inventory design decision.

## Жёсткие ограничения

Обязательные условия работы:

- единственный допустимый PHP executable проекта:
  `C:\wamp\bin\php\php8.3.32\php.exe` (глобальный `php` может указывать на
  PHP 8.4.13);
- PHPUnit запускается только с SQLite `:memory:`.

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

Stage 0–12 закрыты. **Stage 13 находится в работе** и не закрыт. Текущий
функциональный checkpoint — `d66035541e8ab96a801b011168663758181c1e25`
(`feat: separate public personal data consent`).

1. Завершённая часть Stage 13 (8 слайсов) перечислена в разделе
   «Stage 13 … — 🚧 IN PROGRESS» выше и уже запушена.
2. Ближайшее отдельное guarded действие — **Project Sources refresh** из
   нового documentation HEAD, создаваемого этими правками: новый handoff,
   roadmap export, new-chat prompt, manifest и source archive должны быть
   сгенерированы и независимо проверены. В рамках этой documentation-only
   задачи они **не генерировались**.
3. Затем — оставшаяся работа Stage 13 по разделам A–H. Разделы A
   (согласие для отзывов), B (согласие/применимость политики для
   регистрации) и C (хранение evidence согласия) требуют отдельных
   контентных/юридических/бизнес-решений до реализации и **не одобрены**.
4. Раздел D (гостевой сценарий бронирования) — отдельный функциональный
   дефект; его нельзя смешивать с работой по согласиям.
5. Разделы E–G (финальная локальная проверка production-readiness, деплой
   по отдельному guarded операционному плану, production smoke) выполняются
   строго в этом порядке.
6. Раздел H — финальный независимый аудит сайта — выполняется **после**
   production-валидации и ещё не проводился.
7. Итоговое решение по поиску/агрегации туров — отдельное продуктовое
   решение; текущий виджет остаётся временной заглушкой.
8. Как и для каждого предыдущего слайса и этапа — миграции, canonical DB
   операции, production-интеграции и деструктивные действия выполнять
   только по отдельному guarded плану.
