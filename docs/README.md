# Документация Avilona_turfirma

## Текущий checkpoint

| Параметр | Значение |
|---|---|
| Ветка | `db-rebuild-stage3` |
| Последний функциональный checkpoint | `f3279a425458e69b64927f7fdb3b19c5e277ad9f` |
| Commit | `fix: invalidate stale sessions after password changes` |
| Родительский commit | `2e6ed73e037f1714fa547a82b775f082ad3da973` |
| Текущий dependency checkpoint | `61d2fef9ee30ecae2bc1f1d948e16ac2879b1aae` (`chore: update picomatch to 2.3.2`) |
| Предыдущий documentation/source checkpoint | `85a7b22ba398c752c487cbaa505aa32014dc37ad` (`docs: close stage 11`) |
| PHPUnit full baseline | 644 tests / 2861 assertions (Stage 12 обновил PHPUnit до 11.5.56; число тестов/assertions не изменилось) |
| PHPUnit focused baseline (финальный Stage 11 slice) | 17 tests / 82 assertions |
| PHPUnit DB | SQLite `:memory:` — единственно допустимая для PHPUnit |
| Canonical schema | `turfirma_rebuild_v4`, порт 3308 |
| Canonical migrations | 53 Ran / 0 Pending (последняя независимо проверенная canonical-верификация; Stage 12 dependency-работа не требовала нового canonical-прогона) |
| Дата checkpoint | 2026-08-11 |
| Recovery | COMPLETE |
| Stage 5 | COMPLETE |
| Stage 6 | COMPLETE |
| Stage 7 | COMPLETE |
| Stage 8 | COMPLETE |
| Stage 9 | COMPLETE |
| Stage 10 | ✅ COMPLETE |
| Stage 11 | ✅ COMPLETE — 17 завершённых functional semantic slices |
| Stage 12 | ✅ COMPLETE — dependency upgrades: repository hygiene, Composer/npm security modernization, Laravel 12, Vite 7, npm audit = 0, Composer advisories = 0 |
| Stage 13 | 📋 PLANNED — next product stage: production readiness |

Эта документация описывает функциональное состояние на момент последнего
функционального checkpoint `f3279a42` (`fix: invalidate stale sessions
after password changes`), родитель — `2e6ed73e` (`fix: make booking
cancellation atomic`). Этот функциональный checkpoint не меняется Stage 12
dependency-работой. Текущий dependency checkpoint —
`61d2fef9` (`chore: update picomatch to 2.3.2`). Текущий
репозиторный/документационный HEAD, содержащий актуальную версию файлов,
всегда определяется Git и после этого docs-only commit станет новее
`61d2fef9` — это не меняет ни последний функциональный checkpoint, ни его
test baseline.
Stage 10 (уведомления) **завершён** — см. раздел «Stage 10 — ✅ COMPLETE»
ниже. Stage 11 (security, reliability, performance) **завершён**: 17
завершённых functional semantic slice — см. раздел «Stage 11 — ✅ COMPLETE»
ниже и [`roadmap.md`](roadmap.md). Stage 12 (dependency upgrades)
**завершён** — см. раздел «Stage 12 — ✅ COMPLETE» ниже. Следующий
продуктовый этап — Stage 13 (production readiness); перед его началом
должен быть выполнен отдельный guarded Project Sources refresh (см.
«Следующий шаг» ниже).

## Источники истины

Приоритет:

1. Текущий HEAD Git, исходный код и тесты.
2. Этот файл и [`roadmap.md`](roadmap.md).
3. Последние проверенные PHPUnit, migration, recovery и browser-smoke evidence.
4. Актуальный внешний handoff, roadmap и source archive для конкретного pushed HEAD.
5. Исторические документы, включая [`archive/README.md`](archive/README.md), и старые Project Sources.

Старые recovery-файлы со статусом `R5 pending` и Project Sources на `4b568d69`
являются историческими после завершения нового documentation/source refresh.

Текущий внешний Project Sources набор по-прежнему привязан к предыдущему
documentation/source checkpoint `85a7b22b` (`docs: close stage 11`) и
остаётся предыдущим source set до отдельного guarded source-refresh шага,
который выполняется после этого documentation commit.

`docs/archive/legacy-pre-rebuild/` содержит исторические pre-rebuild документы.
Они НЕ являются источником истины и не должны использоваться как
руководство к действию без сверки с текущим кодом и командами из этого
файла — подробности и предупреждения см. в [`archive/README.md`](archive/README.md).

## Подтверждённый стек

| Компонент | Значение |
|---|---|
| PHP CLI проекта | 8.3.32 |
| Laravel | 12.65.0 |
| Composer | 2.8.3 |
| Node.js | 24.18.1 |
| npm | 11.12.1 |
| PHPUnit | 11.5.56 |
| PHPUnit DB | SQLite `:memory:` |
| Canonical MySQL | 9.7.1, `turfirma_rebuild_v4`, port 3308 |
| UI | Blade + Bootstrap 5 |
| Build | Vite 7.3.6 + laravel-vite-plugin 2.1.0 |

Версии в этой таблице отражают завершённое Stage 12 dependency-обновление
(см. раздел «Stage 12 — ✅ COMPLETE» ниже) — `composer.json`/`composer.lock`
и `package.json`/`package-lock.json` были обновлены в рамках Stage 12.

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

## Stage 11 — ✅ COMPLETE. Security, reliability, performance

Stage 11 завершён. Финальный функциональный checkpoint — `f3279a42`
(`fix: invalidate stale sessions after password changes`). Завершено 17
functional semantic slice, один линейный commit chain:

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
не является security/reliability/performance semantic slice и **не входит**
в счёт 17 слайсов Stage 11. Между слайсом 12 и слайсом 13 в истории есть
отдельный documentation-only commit `ebdb45bd2fd5b90e5ab7793d5c604df15ca75e2c`
(`docs: update stage 11 checkpoint`) — он также не входит в счёт
функциональных слайсов.

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

### Manager partial-failure reliability

Слайс 13 (`c5f9cd77`) убирает преждевременные мутации в manager
self-deletion flow: снят предварительный booking-unassignment и
предварительные мутации персональных документов до финального удаления
`User`.

- успешное удаление продолжает полагаться на существующее
  foreign-key-поведение;
- если удаление `User` бросает исключение, сохраняются: сам менеджер,
  связанное booking assignment, строка персонального документа и файл;
- широкая filesystem-транзакционность не заявляется.

### Personal-document deletion ordering

Слайс 14 (`17b17719`) меняет порядок удаления персонального документа:
удаление строки БД происходит до удаления физического файла.

- если удаление модели бросает исключение, сохраняются и строка, и файл;
- применяется к достижимым manager- и tourist-flow удаления персональных
  документов;
- успешное удаление по-прежнему удаляет и строку, и файл.

### Admin chat unread-count query efficiency

Слайс 15 (`52e1c2e3`) убирает per-booking запрос unread-count из списка
чатов администратора.

- unread-счётчики загружаются одним bounded batched-запросом;
- рост числа запросов покрыт отдельным focused-тестом;
- глобальная оптимизация всех chat/list endpoint не заявляется.

### Booking cancellation atomicity

Слайс 16 (`2e6ed73e`) оборачивает мутацию статуса заявки и восстановление
мест тура в одну database-транзакцию.

- исключение при восстановлении мест или `false`-результат проверки
  вместимости откатывает отмену целиком;
- успешная отмена обновляет статус и восстанавливает места ровно один раз;
- диспатч уведомления намеренно остаётся вне database-транзакции и
  устойчив к сбоям;
- полный авторитетный дизайн booking-inventory не заявляется.

### Password-change session invalidation

Слайс 17 (`f3279a42`) включает
`Illuminate\Session\Middleware\AuthenticateSession` один раз в глобальной
web middleware group.

- сессия, выполняющая аутентифицированную смену пароля, остаётся
  аутентифицированной;
- другая, независимо аутентифицированная и уже primed-сессия отклоняется
  на следующем web-запросе после смены пароля;
- forgot-password reset аналогично инвалидирует отдельно primed
  аутентифицированную сессию на следующем запросе;
- guest-маршруты не затронуты;
- не потребовалось изменений контроллеров, маршрутов, миграций,
  зависимостей, session-драйвера или схемы БД;
- принятое deployment-bootstrap ограничение: сессия, созданная до
  деплоя и ещё не прошедшая через `AuthenticateSession`, не имеет
  закэшированного password hash и на первом post-deployment запросе
  primed, а не отклонена.

### Верификация Stage 11 (финальный checkpoint `f3279a42`)

- focused suite финального слайса: 17 tests / 82 assertions;
- full PHPUnit: 644 tests / 2861 assertions;
- PHP `C:\wamp\bin\php\php8.3.32\php.exe` (8.3.32);
- PHPUnit 10.5.20, только SQLite `:memory:`;
- canonical migrations: 53 Ran / 0 Pending — последняя независимо
  проверенная canonical-верификация; в финальных слайсах миграции
  повторно не запускались и не проверялись против canonical MySQL заново;
- local, origin tracking и live origin совпадали на
  `f3279a425458e69b64927f7fdb3b19c5e277ad9f`;
- working tree clean, staging area пуста после commit и push.

Известное non-blocking предупреждение о deprecated PHPUnit XML schema
остаётся в общем backlog — конфигурация PHPUnit в рамках Stage 11 не
мигрировалась.

### Stage 11 — закрытие

Guarded closure audit Stage 11 рассмотрел оставшиеся ограниченные
кандидаты и выбрал в качестве финального кандидата password-session
invalidation. Кандидат реализован, протестирован, закоммичен и запушен
(слайс 17, `f3279a42`). Stage 11 **закрыт** — 17 функциональных semantic
slice.

Следующий продуктовый этап — **Stage 12. Dependency upgrades**, теперь
**завершён** — см. раздел «Stage 12 — ✅ COMPLETE» ниже. Stage 13
остаётся production readiness и deployment; в рамках Stage 12 работы
deployment-планирование не выполнялось.

## Stage 12 — ✅ COMPLETE. Dependency upgrades

Stage 12 завершён. Ниже — ledger завершённой работы; подробный read-only
отчёт инвентаризации в документацию не копируется.

Текущий dependency checkpoint — `61d2fef9`
(`chore: update picomatch to 2.3.2`). Это dependency checkpoint, а не
functional checkpoint — последний функциональный checkpoint остаётся
`f3279a42` и не меняется Stage 12 работой.

Ledger (историческая хронология первых слайсов):

1. **Read-only dependency inventory — COMPLETE.** Прочитаны и сопоставлены
   `composer.json`, `composer.lock`, `package.json`, `package-lock.json`.
   Подтверждённое окружение: PHP 8.3.32; Composer 2.8.3 (через
   pinned PHP executable и `composer.phar`); Node.js 24.18.1; npm 11.12.1.
   Найдено 118 locked Composer-пакетов (80 production / 38 development) и
   203 resolved npm package entries, конкретные Composer/npm advisory
   evidence, кандидаты классифицированы как security-critical,
   framework-major, minor/patch, transitive-only, informational/no-action.
   Обновление зависимостей в ходе инвентаризации не выполнялось.
2. **Первый кандидат — Guzzle.** Предложен Composer-only кандидат:
   `guzzlehttp/guzzle` 7.8.1 → 7.15.2, `guzzlehttp/promises` 2.0.2 → 2.5.1,
   `guzzlehttp/psr7` 2.6.2 → 2.13.0. Guarded dry-run разрешил ровно 0
   installs / 3 updates / 0 removals. Существующее ограничение
   `composer.json` (`^7.2`) уже допускает 7.15.2 — изменение
   `composer.json` для будущего повтора не ожидается.
3. **Прерванное первое выполнение Guzzle-обновления — полностью
   откачено.** Одобренная команда `composer update` дала именно ту
   трёхпакетную дельту, но выполнение было остановлено до валидации,
   поскольку `vendor/` оказался неожиданно отслеживаемым Git несмотря на
   `/vendor` в `.gitignore`. Временно менялись `composer.lock` и
   Guzzle-related файлы под `vendor/`. Изменение было guarded-откачено;
   итоговые версии остались 7.8.1 / 2.0.2 / 2.6.2. Обновление Guzzle
   **не считается завершённым**.
4. **Аудит tracked-vendor политики.** Обнаружено 8657 отслеживаемых путей
   `vendor/` при уже присутствующем `/vendor` в `.gitignore`; история и
   документация не устанавливали намеренную committed-vendor
   deployment-политику; tracking классифицирован как случайный —
   новые файлы зависимостей могли скрываться `.gitignore`, пока старые
   оставались отслеживаемыми, что делало бы будущие committed vendor
   snapshots неполными.
5. **Vendor-untracking repository-hygiene slice — COMPLETE.** Commit
   `ec0b20c5` (родитель `85a7b22b`) убрал ровно 8657 существующих путей
   `vendor/` из Git index (только deletions, только под `vendor/`);
   рабочая копия `vendor/` осталась физически присутствующей и
   byte-identical; `.gitignore` уже покрывал `/vendor`; `composer.json` и
   `composer.lock` не менялись; версии Guzzle-семейства остались
   7.8.1 / 2.0.2 / 2.6.2; local/origin-tracking/live remote HEAD совпали
   на `ec0b20c5`; working tree чист после push. PHPUnit не запускался —
   слайс менял только git tracking state, не runtime-содержимое.
6. **Git normalization recovery evidence (вспомогательное).** Прерванный
   rollback временно показал status discrepancy из-за
   `core.autocrlf=true`, LF blobs в индексе, CRLF working-tree
   представления и устаревших index stat-метаданных. Read-only аудит
   подтвердил для всех 82 затронутых путей совпадение normalized
   working-tree object ID с индексом и индекса с HEAD — содержательных
   расхождений не было. Metadata-only index refresh оставил репозиторий
   content-clean и status-clean до vendor-untracking слайса. Это
   вспомогательное repository-hygiene evidence, не отдельная product
   milestone.
7. **Composer dependency/security модернизация — COMPLETE.** Guzzle
   family обновлена до `guzzlehttp/guzzle` 7.15.2 (ранее откаченный
   слайс 2/3 выше был выполнен повторно и доведён до конца).
   `laravel/framework` проведён through guarded Laravel 10 patch
   maintenance → Laravel 11 bridge → Laravel 12, финально `v12.65.0`.
   Carbon line модернизирована до Carbon 3.x. `phpunit/phpunit` обновлён
   до 11.5.56.
8. **Frontend dependency/security модернизация — COMPLETE.** Неиспользуемые
   webpack- и sass/sass-loader-инструменты удалены после подтверждения,
   что они не используются активной сборкой. Выполнена миграция на
   Vite 7.3.6 + `laravel-vite-plugin` 2.1.0 с адаптацией
   Laravel-совместимого manifest (`public/build/manifest.json`,
   содержит `resources/css/app.css` и `resources/js/app.js`). Tailwind
   обновлён до 3.4.19. Корневой уязвимый `picomatch` обновлён с 2.3.1 до
   2.3.2 (dependency checkpoint `61d2fef9`).
9. **Repository hygiene — подтверждено.** `vendor` и `node_modules` не
   отслеживаются Git (tracked count = 0 для обоих).
10. **Финальная верификация безопасности — COMPLETE.** `npm audit`: 0
    total vulnerabilities (0 info/low/moderate/high/critical). Composer
    locked audit под PHP 8.3.32: 0 advisories, 0 abandoned packages.
    `composer check-platform-reqs --lock` под PHP 8.3.32: PASS.
11. **Production build validation — COMPLETE.** Production Vite build
    успешен; остаётся известное non-blocking предупреждение Vite
    из-за пересечения `publicDir`/`outDir` — не исправлено.
12. **Full PHPUnit validation — COMPLETE.** 644 tests / 2861 assertions,
    PHPUnit 11.5.56, SQLite `:memory:`, exit code 0. Остаётся одно
    non-blocking PHPUnit deprecation-предупреждение — не исправлено.

Финальные версии зависимостей:

- `laravel/framework` 12.65.0;
- `guzzlehttp/guzzle` 7.15.2;
- `phpunit/phpunit` 11.5.56;
- Carbon line — 3.x;
- `vite` 7.3.6;
- `laravel-vite-plugin` 2.1.0;
- `tailwindcss` 3.4.19;
- корневой `picomatch` 2.3.2;
- webpack и sass/sass-loader удалены как неиспользуемые.

Test и migration baseline: full PHPUnit 644 tests / 2861 assertions под
PHPUnit 11.5.56 (число тестов/assertions не изменилось при обновлении
PHPUnit), focused финальный Stage 11 slice 17 tests / 82 assertions,
canonical migrations 53 Ran / 0 Pending (последняя независимо
проверенная canonical-верификация; Stage 12 dependency-работа не
требовала нового canonical-прогона).

Отложенное обслуживание (не блокеры закрытия Stage 12):

- Composer non-security maintenance: `fakerphp/faker` v1.23.1 → v1.24.1,
  `laravel/pint` v1.15.3 → v1.30.5;
- Composer major-миграции, намеренно отложенные: `guzzlehttp/guzzle`
  7.15.2 → 8.0.2, `laravel/framework` v12.65.0 → v13.24.0,
  `phpmailer/phpmailer` v6.9.1 → v7.1.1, `phpunit/phpunit` 11.5.56 →
  12.5.33 (под canonical PHP 8.3.32 Composer audit),
  `spatie/laravel-sluggable` 3.8.1 → 4.0.3;
- npm non-security same-major maintenance: `@popperjs/core` 2.11.6 →
  2.11.8, `@tailwindcss/forms` 0.5.3 → 0.5.11, `alpinejs` 3.12.0 →
  3.16.0, `autoprefixer` 10.4.14 → 10.5.4, `bootstrap` 5.3.0-alpha1 →
  5.3.8;
- npm major-миграции, намеренно отложенные: `laravel-vite-plugin` 2.1.0
  → 3.1.3, `tailwindcss` 3.4.19 → 4.3.3, `vite` 7.3.6 → 8.2.1.

Это отложенное обслуживание и будущие major-миграции, а не причина
считать Stage 12 незакрытым; не все пакеты находятся на самой новой
опубликованной версии, но верифицированные security-аудиты (npm и
Composer) на момент закрытия Stage 12 чисты.

Stage 12 **закрыт**.

Следующий шаг: отдельный guarded Project Sources refresh (не выполняется
в рамках этого documentation-only коммита) — это отдельное guarded
документационное/repository-management действие, предшествующее началу
Stage 13. Project Sources в рамках этой задачи не генерировались и не
обновлялись. После Project Sources refresh следующий продуктовый этап —
**Stage 13. Production readiness**; она не начата в рамках этой задачи.

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
- N+1 unread-count поведение в manager chat-list, обнаруженное в ходе
  Stage 11 closure audit — подтверждённый ограниченный performance-кандидат,
  но не выбран в качестве финального Stage 11 слайса и **не** устраняется
  admin chat-list оптимизацией (слайс 15, `52e1c2e3`); требует отдельного
  рассмотрения (см. `roadmap.md`).

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

Stage 9, Stage 10 и Stage 11 закрыты. Stage 11 (security, reliability,
performance) **завершён**: 17 функциональных semantic slice, финальный
функциональный checkpoint — `f3279a425458e69b64927f7fdb3b19c5e277ad9f` (см.
«Stage 11 — ✅ COMPLETE» выше). Guarded closure audit выбрал
password-session invalidation финальным кандидатом; он реализован,
протестирован, закоммичен и запушен (слайс 17).

Следующий продуктовый этап — **Stage 12. Dependency upgrades** — теперь
**завершён** (см. раздел «Stage 12 — ✅ COMPLETE» выше): repository
hygiene для `vendor`/`node_modules`, Composer dependency/security
модернизация, Laravel framework модернизация до Laravel 12, frontend
dependency/security модернизация, удаление неиспользуемых webpack/sass
инструментов, миграция на Vite 7 + `laravel-vite-plugin` 2, обновление
Tailwind до 3.4.19, security-исправление `picomatch` до 2.3.2, финальный
`npm audit` = 0, финальные Composer advisories = 0 и abandoned packages
= 0, production build validation и полная верификация PHPUnit
(644 tests / 2861 assertions) выполнены и подтверждены. Отложенное
non-security и major-обслуживание перечислено в разделе Stage 12 выше и
не считается блокером закрытия. Дальнейшее продвижение по этим
отложенным пунктам не выполнялось и не начиналось в рамках этой задачи.
Stage 13 остаётся следующим продуктовым этапом — production readiness и
deployment; она **не начата**, deployment-планирование в рамках этой
задачи не выполнялось. Новый post-closure handoff, roadmap export,
new-chat prompt, manifest и source archive должны быть сгенерированы и
независимо проверены отдельным guarded Project Sources refresh шагом
после этого Stage 12 documentation-only commit и до начала Stage 13 —
они ещё не сгенерированы в рамках этой задачи; текущий Project Sources
набор остаётся привязан к предыдущему checkpoint `85a7b22b`. Полный
список жёстких ограничений — в [`roadmap.md`](roadmap.md).
