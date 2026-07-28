# Документация Avilona_turfirma

## Текущий checkpoint

| Параметр | Значение |
|---|---|
| Ветка | `db-rebuild-stage3` |
| Последний функциональный checkpoint | `014470402984eaffd8c9888292ec51a2124de71d` |
| Commit | `feat: open new message notifications` |
| Родительский commit | `d572b11633e9fcd2ffce848e1633a1a9e8eaa546` |
| PHPUnit full baseline | 567 tests / 2391 assertions |
| PHPUnit focused baseline (notification-open slice) | 96 tests / 347 assertions |
| PHPUnit DB | SQLite `:memory:` — единственно допустимая для PHPUnit |
| Canonical schema | `turfirma_rebuild_v4`, порт 3308 |
| Canonical migrations | 53 Ran / 0 Pending (после guarded trip-reminder migration) |
| Recovery | COMPLETE |
| Stage 5 | COMPLETE |
| Stage 6 | COMPLETE |
| Stage 7 | COMPLETE |
| Stage 8 | COMPLETE |
| Stage 9 | COMPLETE |
| Stage 10 | IN PROGRESS |

Эта документация описывает функциональное состояние на момент последнего
функционального checkpoint `01447040`. Текущий репозиторный/документационный
HEAD, содержащий актуальную версию файлов, всегда определяется Git и после
docs-only commit, фиксирующего эти правки, станет новее `01447040` — это не
меняет сам последний функциональный checkpoint и его test baseline.
Stage 10 **не завершён** — см. раздел «Stage 10 — IN PROGRESS» ниже и
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

## Stage 10 — IN PROGRESS. Уведомления

Stage 10 **не завершён**. Всего завершено 9 маленьких semantic slice.
Последний функциональный commit — `014470402984eaffd8c9888292ec51a2124de71d`;
его непосредственный родитель — `d572b11633e9fcd2ffce848e1633a1a9e8eaa546`
(commit слайса 8, а не общий стартовый предок всех девяти слайсов):

1. `92e49fce` — new-message email preferences.
2. `4e18ca6d` — booking-status email preferences.
3. `926b8382` — manager-assignment email preferences.
4. `a92e1dbe` — booking-created email preferences.
5. `1180931a` — trip-reminder email preferences.
6. `d9279a18` — trip-reminder settings copy.
7. `a9dc356a` — trip-reminder idempotency.
8. `d572b116` — new-message database notification persistence.
9. `01447040` — open new-message notifications safely from the cabinet.

Слайс `01447040` добавляет:

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

Verification для этого слайса:

- focused: 96 tests / 347 assertions;
- full PHPUnit на `01447040`: 567 tests / 2391 assertions;
- PHP `C:\wamp\bin\php\php8.3.32\php.exe`, PHPUnit только SQLite `:memory:`.

Следующий Stage 10 slice **заранее не выбран**. Требуется отдельный
read-only discovery оставшейся notification-архитектуры и текущих
продуктовых пробелов (см. «Следующий шаг» ниже и `roadmap.md`) до внесения
новых правок.

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

Stage 9 закрыт. Stage 10 (уведомления) **в процессе**: завершено 9
semantic slice, последний функциональный checkpoint — `01447040` (см.
«Stage 10 — IN PROGRESS» выше). Публикация текущего docs-only checkpoint
не меняет ни один из этих фактов.

1. После публикации текущего docs-only checkpoint — завершить и
   независимо проверить новый Project Sources set (и связанные handoff,
   roadmap copy, new-chat prompt, source archive, manifest) для
   получившегося документационного HEAD.
2. Только после этого выполнить отдельный read-only discovery оставшейся
   notification-архитектуры: UI-поверхности кабинета для непрочитанных
   уведомлений, mail/queue delivery, admin/manager broadcast, тесты и
   отсутствующие operational contracts; отдельно сравнить историческую
   документацию (включая архив) и фактическую текущую реализацию.
3. Затем выбрать один маленький семантический Stage 10 slice и exact
   allowed paths до начала правок.

До выбора scope не начинать широкую реализацию notification UI, очередей,
broadcasting, WebSocket, Telegram/SMS или иных внешних интеграций. Полный
список жёстких ограничений — в [`roadmap.md`](roadmap.md).
