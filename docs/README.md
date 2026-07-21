# Документация Avilona_turfirma

## Текущий checkpoint

| Параметр | Значение |
|---|---|
| Ветка | `db-rebuild-stage3` |
| Функциональный checkpoint | `bae264802a17bac3c796481da7f10096acfc3cb2` |
| Commit | `fix: remove unsupported public nonstop filter` |
| Предыдущий maintenance commit | `49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f` |
| PHPUnit baseline | 382 tests / 1389 assertions |
| PHPUnit DB | SQLite `:memory:` |
| Canonical schema | `turfirma_rebuild_v4` |
| Canonical migrations | 52 Ran / 0 Pending |
| Recovery | COMPLETE |
| Stage 5 | COMPLETE |
| Stage 6 | COMPLETE |
| Stage 7 | COMPLETE |
| Stage 8 | COMPLETE |

Эта документация описывает функциональное состояние на checkpoint `bae26480`.
Документационный HEAD, содержащий текущую версию файлов, всегда определяется Git.

## Источники истины

Приоритет:

1. Текущий HEAD Git и исходный код.
2. Этот файл и [`roadmap.md`](roadmap.md).
3. Последние проверенные PHPUnit, migration, recovery и browser-smoke evidence.
4. Актуальные внешний handoff, roadmap и source archive для конкретного pushed HEAD.
5. Исторические документы и старые Project Sources.

Старые recovery-файлы со статусом `R5 pending` и Project Sources на `4b568d69`
являются историческими после завершения нового documentation/source refresh.

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

## Карта документации

| Файл | Назначение |
|---|---|
| [`../README.md`](../README.md) | Точка входа |
| [`README.md`](README.md) | Источники истины и правила работы |
| [`roadmap.md`](roadmap.md) | Актуальные этапы и backlog |

Остальные документы в `docs/` могут быть историческими и требуют сверки с текущим кодом.

## Следующий шаг

Stage 8 закрыт. После documentation/source refresh — read-only discovery
для Stage 9 (публичный контент и CMS):

1. инвентаризировать текущие public-content/CMS/news/article/review/SEO
   routes, controllers, models, migrations, views, assets и tests;
2. отдельно сравнить историческую документацию и фактическую текущую
   реализацию;
3. включить в backlog-инвентаризацию затенённый route
   `/helpful_information/news/rss` и отсутствие операционного расписания
   для `news:sync-rss`;
4. выбрать один маленький семантический Stage 9 slice и exact allowed
   paths до начала правок.

До выбора scope не начинать широкую реализацию CMS или SEO.
