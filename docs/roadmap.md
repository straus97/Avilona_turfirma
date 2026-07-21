# Avilona_turfirma — актуальная дорожная карта

Дата checkpoint: **2026-07-21**

Функциональный checkpoint:

```text
bae264802a17bac3c796481da7f10096acfc3cb2
fix: remove unsupported public nonstop filter
```

Предыдущий maintenance checkpoint:

```text
49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f
fix: move news RSS sync out of public request
```

Текущий тестовый baseline:

```text
382 tests
1389 assertions
PHPUnit 10.5.20
SQLite :memory:
```

## Сводный статус

- Stage 0–4 — ✅ COMPLETE
- Stage 5 — ✅ COMPLETE
- Stage 6 — ✅ COMPLETE
- Emergency DB Recovery R0–R6D — ✅ COMPLETE
- RSS maintenance slice — ✅ COMPLETE
- Stage 7 — ✅ COMPLETE
- Stage 8 — ✅ COMPLETE
- Stage 9 — 📋 NEXT
- Stage 10–13 — 📋 PLANNED / ⏸ DEFERRED

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

1. `/helpful_information/news/rss` затенён более ранним `/{slug}`.
2. Автоматическое расписание `news:sync-rss` не настроено.
3. Реальный sync против canonical MySQL до отдельного operational plan не выполнять.

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

## Stage 9. Публичный контент и CMS — 📋 NEXT

Read-only discovery перед любой реализацией:

- статические страницы;
- новости и статьи;
- отзывы и модерация;
- SEO и метаданные;
- инвентаризация routes/controllers/models/views/tests;
- сравнение исторической документации и фактической текущей реализации;
- затенённый route `/helpful_information/news/rss`;
- операционное расписание для `news:sync-rss`;
- выбор одного маленького семантического slice до начала правок.

Реализация Stage 9 ещё не начата.

## Stage 10. Уведомления — 📋

- уведомления в кабинете;
- status-change email;
- optional Telegram/SMS/WebSocket после feasibility.

## Stage 11. Security, reliability, performance — 📋

- rate limiting;
- security logging;
- caching;
- performance/image optimization.

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

- shadowed route `/helpful_information/news/rss`;
- расписание для `news:sync-rss`;
- постоянный manager live account отсутствует; изолированная временная smoke-стратегия проверена;
- deprecated PHPUnit XML schema;
- recovery artifact retention policy;
- единый E2E lifecycle scenario;
- история и аудит заявки;
- WebSocket/unread counters для чата.

## Жёсткие ограничения

Без отдельного утверждённого плана запрещено:

- `composer update`;
- `npm update`;
- `npm audit fix`;
- migrations/seed/import/reset/wipe;
- `legacy:import-v4 --execute`;
- PHPUnit против canonical MySQL;
- реальный `news:sync-rss` против canonical MySQL;
- удаление recovery/rollback artifacts;
- реальные внешние интеграционные запросы к туроператорам (Sletat, Coral
  Travel и др.) до отдельного guarded operational плана.

## Ближайший следующий шаг

После docs-only commit/push и обновления Project Sources:

**read-only discovery и Plan для первого небольшого Stage 9 slice**.

Никаких изменений кода до выбора точного scope.
