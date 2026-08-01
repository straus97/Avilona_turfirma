# Документация Avilona_turfirma

## Текущий checkpoint

| Параметр | Значение |
|---|---|
| Ветка | `db-rebuild-stage3` |
| Последний функциональный checkpoint | `f1a0fff0b1f0b93c654b836e93dd5049eae2569f` |
| Commit | `fix: preserve tourist data on deletion failure` |
| Родительский commit | `d17255e74cc77ce56bd42bcbeaa47d590aa46f1d` |
| Предыдущий documentation checkpoint | `f6d0e119ac22353bd411914db508eb6e45109de8` (`docs: close stage 10 and plan stage 11`) |
| PHPUnit full baseline | 632 tests / 2777 assertions |
| PHPUnit focused baseline (последний Stage 11 slice) | 15 tests / 106 assertions |
| PHPUnit DB | SQLite `:memory:` — единственно допустимая для PHPUnit |
| Canonical schema | `turfirma_rebuild_v4`, порт 3308 |
| Canonical migrations | 53 Ran / 0 Pending |
| Дата checkpoint | 2026-08-02 |
| Recovery | COMPLETE |
| Stage 5 | COMPLETE |
| Stage 6 | COMPLETE |
| Stage 7 | COMPLETE |
| Stage 8 | COMPLETE |
| Stage 9 | COMPLETE |
| Stage 10 | ✅ COMPLETE |
| Stage 11 | 🟡 IN PROGRESS — 12 завершённых semantic slices |
| Stage 12 | ⏸ DEFERRED |
| Stage 13 | 📋 PLANNED |

Эта документация описывает функциональное состояние на момент последнего
функционального checkpoint `f1a0fff0` (`fix: preserve tourist data on
deletion failure`), родитель — `d17255e7` (`fix: align tourist deletion
session teardown`). Текущий репозиторный/документационный HEAD, содержащий
актуальную версию файлов, всегда определяется Git и после docs-only commit,
фиксирующего эти правки, станет новее `f1a0fff0` — это не меняет сам
последний функциональный checkpoint и его test baseline.
Stage 10 (уведомления) **завершён** — см. раздел «Stage 10 — ✅ COMPLETE»
ниже. Stage 11 (security, reliability, performance) **в процессе**: 12
завершённых semantic slice — см. раздел «Stage 11 — 🟡 IN PROGRESS» ниже и
[`roadmap.md`](roadmap.md).

## Источники истины

Приоритет:

1. Текущий HEAD Git, исходный код и тесты.
2. Этот файл и [`roadmap.md`](roadmap.md).
3. Последние проверенные PHPUnit, migration, recovery и browser-smoke evidence.
4. Актуальный внешний handoff, roadmap и source archive для конкретного pushed HEAD.
5. Исторические документы, включая [`archive/README.md`](archive/README.md), и старые Project Sources.

Старые recovery-файлы со статусом `R5 pending` и Project Sources на `4b568d69`
являются историческими после завершения нового documentation/source refresh.

`docs/archive/legacy-pre-rebuild/` содержит исторические pre-rebuild документы.
Они НЕ являются источником истины и не должны использоваться как
руководство к действию без сверки с текущим кодом и командами из этого
файла — подробности и предупреждения см. в [`archive/README.md`](archive/README.md).

## Подтверждённый стек

| Компонент | Значение |
|---|---|
| PHP CLI проекта | 8.3.32 |
| Laravel | 10.48.10 |
| Composer | 2.8.3 |
| Node.js | 22.11.0 |
| npm | 11.12.1 |
| PHPUnit | 10.5.20 |
| PHPUnit DB | SQLite `:memory:` |
| Canonical MySQL | 9.7.1, `turfirma_rebuild_v4`, port 3308 |
| UI | Blade + Bootstrap 5 |
| Build | Vite 4.x |

Глобальный `php` может указывать на PHP 8.4.13. Скрипты и PHPUnit проекта должны
использовать `C:\wamp\bin\php\php8.3.32\php.exe`.

## Recovery checkpoint

Аварийное восстановление завершено.

Подтверждено:

- canonical DB promoted и проверена;
- recovery application fingerprint:
  `fc449488f3d115713cfa0ee97b62a933dfa11393cdbc89c391816aa25d174784`;
- final canonical manifest:
  `78AD452573B4305897E20465F3B599F44C1E4C2636DE2DB321E695E4B70DB4B1`;
- promoted logical dump:
  `BDFAF8679622322495F47AE869AEFCE8614282227A49CA5BCD3BB28DCD1D8FA4`;
- 52 migrations Ran / 0 Pending;
- public smoke 15/15;
- protected unauthenticated smoke 14/14;
- R6D application validation:
  `PASS_WITH_KNOWN_PREEXISTING_RSS_DEFECT`;
- RSS-дефект после этого исправлен commit `49fe6fbf`;
- canonical fingerprint до/после recovery live validation не изменился.

Recovery/rollback artifacts не удалять до отдельного retention-решения.

## Stage 7 — COMPLETE

Role precedence для общих и booking-scoped потоков:

```text
admin > assigned manager > owner-facing tourist
```

Завершённые Stage 7 checkpoint:

- `9ba1b129` — cabinet shared-route role redirects.
- `73ea7ed4` — public cabinet menu role precedence.
- `47507b85` — cabinet header role links.
- `7cf9381a` — booking view sidebar role precedence.
- `2ba8f22d` — booking edit effective role.
- `842e0603` — booking show effective role.
- `271ca168` — booking cancel effective role.
- `6d23812d` — cabinet dashboard role guard.
- `1bc2c9ea` — booking document effective role.
- `34a03ee9` — dual-role message participant coverage.
- `973975d6` — booking show per-booking authorization.

Подтверждённые правила:

- admin+tourist остаётся staff-facing;
- manager+tourist owner+assigned остаётся staff-facing;
- manager+tourist owner-not-assigned становится owner-facing;
- owner-facing пользователь не видит manager notes, uploader identity,
  staff download/delete/upload и staff lifecycle controls;
- owner-facing document route работает только для реального владельца без
  booking-scoped staff authority;
- staff-only document route для owner-not-assigned возвращает 403;
- shared message routes сохраняют owner-based participant access.

## Stage 7 closure evidence

Финальное подтверждение:

- PHP 8.3.32;
- PHPUnit SQLite `:memory:`;
- focused booking-show/document regression: 79 tests / 267 assertions;
- full PHPUnit at committed HEAD: 352 tests / 1183 assertions;
- Apache syntax, service lifecycle и HTTP 200: PASS;
- canonical MySQL 9.7.1, port 3308, 28 InnoDB tables: PASS;
- Stage 7 read-only session fingerprint:
  `afb2cd19ce0401e82c8bc29f0446785906afd9cc31197f81cc573adb19f06cca`;
- browser smoke для трёх mixed-role сценариев: PASS;
- временные fixtures и документы удалены;
- fingerprint после cleanup восстановлен;
- local/origin HEAD совпадают;
- working tree clean;
- `STAGE7_FINAL_CLOSURE_VERIFICATION=PASS`.

Stage 7 session fingerprint не является заменой recovery application fingerprint:
алгоритмы различаются.

## Stage 8 — COMPLETE

Завершённые commit (17), сгруппированные по локальному API и публичному каталогу.

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

Подтверждённые гарантии:

- публичный каталог read-only, не изменяет базу данных и не выполняет
  внешние HTTP-запросы;
- отображаются только активные и не удалённые туры;
- выровненные контракты фильтров между публичной формой и внутренним
  JSON tour-search API: tour_operator (один или несколько), nights
  (точное значение → nights_min/nights_max), rating (минимальный порог
  по `hotel_rating`), charter (`is_charter`), direct (`is_direct`),
  hotel_name (частичное совпадение);
- сортировка по рейтингу использует `hotel_rating`, а не `hotel_stars`;
- канонический sort-value `popular`;
- публичная форма не обязана выставлять каждый API-only параметр;
- внутренние имена фильтров не протекают в публичный контракт ответа;
- неподдерживаемые `nonstop` и `instant_confirmation` удалены, а не
  реализованы с придуманным backend-поведением.

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
- ни один реальный запрос к Sletat, Coral Travel или другому туроператору
  не выполнялся;
- ни один реальный credential не добавлялся, не выводился, не проверялся
  и не сохранялся;
- существующий интеграционный код не подтверждён как рабочий;
- операционная интеграция, обработка credentials, проверка контракта
  провайдера, retries, rate limits, observability, безопасность
  синхронизации и canonical-импорт данных остаются отложенными и
  требуют отдельного guarded feasibility/operational плана.

Closure verification:

- focused public catalog suite: 18 tests / 82 assertions;
- full PHPUnit at committed HEAD: 382 tests / 1389 assertions;
- local/origin HEAD равны на `bae26480`;
- working tree clean, staged path count 0.

Stage 9 (публичный контент и CMS) завершён отдельно; полная хронология,
завершённые checkpoint и read-only SEO backlog — в
[`roadmap.md`](roadmap.md).

## Stage 10 — ✅ COMPLETE. Уведомления

Stage 10 завершён: 12 маленьких semantic slice, один линейный commit chain
(с одним docs-only коммитом `f351ae9e` между слайсами 9 и 10, не входящим
в счёт функциональных слайсов). Последний функциональный checkpoint —
`9ef3c8e96411c11dd4f725f607bf3dc8f56b5a0c` (`feat: add cabinet notification
bell`):

1. `92e49fceb9d6832d2516092ce13c3eccd328a011` — honor new message email preferences.
2. `4e18ca6dfd24bc413f72a61311643d337b5c6de6` — honor booking status email preferences.
3. `926b8382926d5e9b112843d14d4b5e241a02c600` — honor manager assignment email preferences.
4. `a92e1dbe2882e996b43de0f93354a0c0e9e636ba` — honor booking creation email preferences.
5. `1180931adfc0e4081281ca9b797920496431c5bd` — honor trip reminder email preferences.
6. `d9279a18b474952a3cacb875a209eb3336263a58` — align trip reminder settings copy.
7. `a9dc356a4d43b3aded40444532bdf4e1e9d87a29` — prevent duplicate trip reminders.
8. `d572b11633e9fcd2ffce848e1633a1a9e8eaa546` — persist new message database notifications.
9. `014470402984eaffd8c9888292ec51a2124de71d` — open new message notifications safely from the cabinet.
10. `8012e1c236dc13cb304281d704e1e60c30273266` — protect booking status notification dispatch.
11. `04c6c41035d9e421944ee1135fc95cd6fe9c2fd4` — log public contact mail failures.
12. `9ef3c8e96411c11dd4f725f607bf3dc8f56b5a0c` — add cabinet notification bell.

Слайс 9 (`01447040`) добавляет:

- POST route `cabinet.notifications.open` внутри authenticated cabinet
  role middleware (`auth`, `password.change`, `role:tourist,manager,admin`);
- поиск уведомления строго через связь текущего пользователя (без
  предварительного глобального разрешения по id);
- строгую проверку типа `NewMessageDatabaseNotification` и
  `data.type === new_message`;
- валидацию `booking_id` как положительного integer либо числовой строки;
- загрузку заявки до какого-либо изменения `read_at`;
- effective-role приоритет `admin > manager > tourist`, без отката с
  manager-ветки на tourist-ветку;
- редирект на соответствующий admin/manager/tourist chat route с
  `bookingId`;
- идемпотентную обработку уже прочитанных уведомлений;
- 404 без пометки прочитанным для чужих, некорректных (payload/тип/data),
  неподдерживаемых, недоступных по роли, отсутствующих или мягко
  удалённых заявок;
- 21 feature-тест в `CabinetNotificationOpenTest`.

Слайс 10 (`8012e1c2`) оборачивает диспатч `BookingStatusChanged` внутри
`Booking::transitionTo()` в try/catch с `Log::error(...)`, симметрично
трём уже защищённым сайтам диспатча (`BookingCreated`, `ManagerAssigned`,
`NewMessageReceived`) — сбой почтового слушателя больше не превращает
успешный переход статуса в HTTP 500.

Слайс 11 (`04c6c410`) добавляет `Log::error(...)` в существующие
generic-catch блоки `SendContactController` и `SendHomeController` —
сбой отправки публичной формы обратной связи теперь логируется, а не
проглатывается молча.

Слайс 12 (`9ef3c8e9`) добавляет минимальный колокольчик уведомлений в
общий cabinet-хедер (`resources/views/cabinet/layouts/app.blade.php`),
общий для tourist/manager/admin:

- owner-scoped, type-filtered (`NewMessageDatabaseNotification`) bounded
  запрос: одна `count()` для бейджа и один `latest()->first()` для самого
  свежего непрочитанного;
- ровно одно действие — POST-форма на уже существующий
  `cabinet.notifications.open` с `@csrf`, без новых маршрутов, без
  дублирования авторизации/валидации `NotificationController`;
- три строки текста: жирная метка «Новое сообщение», строка
  «Отправитель: {имя}» (с безопасным fallback), превью с лимитом 60
  символов;
- zero-state «Нет новых уведомлений» без бейджа и без действия;
- 13 feature-тестов в `CabinetHeaderNotificationBellTest`.

Verification для Stage 10 (финальный слайс `9ef3c8e9`):

- PHP `C:\wamp\bin\php\php8.3.32\php.exe`, PHPUnit только SQLite `:memory:`;
- focused notification-bell suite: 57 tests / 216 assertions;
- full PHPUnit: 588 tests / 2486 assertions;
- canonical migrations: 53 Ran / 0 Pending;
- ручное browser QA: колокольчик виден для tourist/manager/admin; бейдж
  непрочитанных корректно переходит 0 → 1 → 0; отображается самое свежее
  уведомление; POST-действие открывает правильный чат по заявке; открытое
  уведомление больше не учитывается в счётчике непрочитанных; длинное
  превью остаётся ограниченным; zero-state показывает «Нет новых
  уведомлений».
- локальные QA-фикстуры (тестовый менеджер, тестовая заявка,
  browser-QA сообщения и database-уведомления) не являются состоянием
  репозитория, не закоммичены и не входят в changed-path inventory.

Известные пере-оценённые находки прошлого discovery (подробности —
`roadmap.md` → «Общий backlog»):

- несовпадающие/мёртвые счётчики sidebar (`$pendingBookingsCount`,
  manager `$unreadMessagesCount`) — не связаны с таблицей `notifications`,
  не блокируют Stage 10, отнесены в code-hygiene backlog;
- разница в ключах notification preferences у manager/admin (нет
  `trip_reminders`/`promotions`) — не дефект: trip-reminder письма
  отправляются только владельцу заявки (`SendTripReminders.php`),
  `promotions` пока не используется ни для одной роли;
- очередь/scheduler (sync queue, отсутствие worker/cron wiring) —
  Stage 13 production readiness, не Stage 10;
- WebSocket/Echo, Telegram/SMS, полноценный notification center,
  mark-all-read — явно отложенный backlog, не блокеры закрытия Stage 10.

## Stage 11 — 🟡 IN PROGRESS. Security, reliability, performance

Stage 11 не завершён. На текущий функциональный checkpoint `f1a0fff0`
завершено 12 маленьких semantic slice, один линейный commit chain:

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
не является security/reliability/performance semantic slice и **не входит**
в счёт 12 слайсов Stage 11.

### Rate limiting

- слайс 1 (`8c23646b`) добавляет `throttle:6,1` на публичный
  `POST register` (`routes/auth.php`);
- слайс 2 (`32dd86a1`) добавляет `throttle:6,1` на
  `POST forgot-password` (`routes/auth.php`, `password.email`);
- существующее поведение (успешная регистрация, успешный запрос
  password-reset ссылки) сохранено без изменений;
- зависимости и миграции не менялись — используется штатный Laravel
  `throttle`-middleware.

### Security audit logging

Слайсы 3, 4, 6, 7, 8, 9, 10 добавляют `Log::warning(...)` со связкой
actor/target и (где применимо) списком ролей строго **после**
подтверждённой успешной мутации (внутри соответствующего `if`-блока
успеха, например `if ($deleted)`):

- `baedfac7` — `Admin deleted user account` (`AdminController::deleteUser`);
- `0a000e1a` — `Admin removed user role`;
- `a3231a06` — `Admin updated user role` (`added_roles`/`removed_roles`/`resulting_roles`);
- `c5aad3a4` — `Admin assigned user role`;
- `dfec2733` — `Admin deleted own account` (`AdminController`);
- `e72aaaca` — `User deleted own account via manager settings` (`ManagerController`);
- `ab80e8e2` — `Tourist deleted own account` (`CabinetController`).

Подтверждённые гарантии:

- логирование срабатывает только после успешного вызова `delete()`/мутации
  роли, не до и не при ошибке валидации;
- неуспешная попытка (например, самоудаление собственного аккаунта
  администратором) не создаёт audit-запись;
- централизованной audit-подсистемы (единой таблицы, сервиса или UI для
  просмотра логов) не существует — используется стандартный Laravel `Log`
  facade поверх существующего log-канала.

### Performance

- слайс 5 (`b5923a86`) устраняет N+1 в `ManagerController` client-list:
  заменяет запросы `active_bookings`/`latest_booking` на клиента циклом на
  один коррелированный подзапрос (`addSelect` + `latest_booking_id`) и один
  batched `whereIn`-запрос для гидратации;
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
работа закрыта; повторно открывать её в разделе «Следующий шаг» не нужно.

### Tourist partial-failure reliability

Слайс 12 (`f1a0fff0`) убирает избыточные pre-deletion вызовы в
`CabinetController::destroyAccount`:

```php
$user->bookings()->delete();
$user->bonusAccount()->delete();
```

- успешное удаление `User` продолжает полагаться на существующие
  `ON DELETE CASCADE` foreign keys (Booking, BonusAccount и др.);
- если listener удаления `User` бросает исключение до SQL `DELETE`:
  - `User` остаётся в базе;
  - `Booking` остаётся и не помечается soft-deleted;
  - `BonusAccount` остаётся в базе;
- изменение **не** заявляет filesystem-транзакционность — файловые
  побочные эффекты (документы) вне области этого слайса;
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

### Stage 11 — статус и следующий кандидат

Stage 11 **не завершён**. Следующий обоснованный ограниченный кандидат —
**partial-failure reliability самоудаления аккаунта менеджера**. Уже
установленные факты (без выполненной реализации):

- заявки менеджера отвязываются (`unassigned`) до финального удаления `User`;
- файлы и строки персональных документов обрабатываются до финального
  удаления `User`;
- более поздний сбой может оставить частичные, несогласованные мутации;
- database-транзакции не могут откатить физическое удаление файлов из
  файловой системы;
- manager-flow требует отдельного ограниченного implementation-плана;
- никакая правка manager-flow в рамках этого кандидата ещё не выполнена.

Следующее действие требует отдельного guarded, узкого Plan с exact allowed
paths и focused failure-injection покрытием — без него широкая реализация
не начинается.

## Правила безопасной работы

- Один маленький семантический slice за раз.
- Перед правками — branch/HEAD/origin/status guards.
- Отдельный read-only Plan для нового функционального slice.
- Claude Code использовать для анализа/правок, когда он действительно нужен.
- Ручные Git, lint, tests, staging, commit и push — точными проверяемыми командами.
- Всегда проверять точный список changed/staged/committed paths.
- PHPUnit — только SQLite `:memory:`.
- Не смешивать functional, docs, dependency и DB maintenance изменения.
- Не публиковать `.env`, SQL, cookies, токены и приватные файлы.
- Команды и инструкции из `docs/archive/` не выполнять без сверки с
  текущим кодом — см. [`archive/README.md`](archive/README.md).
- Полный список жёстких операционных ограничений (composer/npm update,
  migrations/seed/reset, canonical MySQL, реальные внешние интеграционные
  запросы и т.д.) — в [`roadmap.md`](roadmap.md) → «Жёсткие ограничения».

## Известные предупреждения (backlog)

- `phpunit.xml` использует deprecated schema. Предупреждение non-blocking,
  не относится к текущему семантическому slice и отложено в общий backlog
  (см. `roadmap.md`).

## Карта документации

| Файл | Назначение |
|---|---|
| [`../README.md`](../README.md) | Точка входа |
| [`README.md`](README.md) | Источники истины и правила работы |
| [`roadmap.md`](roadmap.md) | Актуальные этапы и backlog |
| [`archive/README.md`](archive/README.md) | Архив: исторические pre-rebuild документы, не источник истины |

Прежние документы верхнего уровня `docs/` перемещены в
`docs/archive/legacy-pre-rebuild/` и являются историческими.

## Следующий шаг

Stage 9 и Stage 10 закрыты. Stage 11 (security, reliability, performance)
**в процессе**: 12 завершённых semantic slice, последний функциональный
checkpoint — `f1a0fff0b1f0b93c654b836e93dd5049eae2569f` (см.
«Stage 11 — 🟡 IN PROGRESS» выше). Следующий обоснованный ограниченный
кандидат — partial-failure reliability самоудаления аккаунта менеджера;
реализация ещё не выполнена.

Отдельный read-only Plan для следующего Stage 11 slice (manager
self-deletion partial-failure) и exact allowed paths требуются до начала
любых новых правок, как и для каждого предыдущего слайса и этапа. До
утверждения scope не начинать широкую реализацию manager-flow правок или
иных ещё не выбранных Stage 11 механизмов. Полный список жёстких
ограничений — в [`roadmap.md`](roadmap.md).
