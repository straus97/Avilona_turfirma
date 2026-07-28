# Avilona_turfirma — актуальная дорожная карта

Дата checkpoint: **2026-07-29**

Последний функциональный checkpoint:

```text
014470402984eaffd8c9888292ec51a2124de71d
feat: open new message notifications
```

Текущий репозиторный/документационный HEAD всегда определяется Git и
после docs-only commit, фиксирующего эти правки, станет новее указанного
функционального checkpoint — это не меняет сам функциональный checkpoint
и его test baseline.

Родительский commit:

```text
d572b11633e9fcd2ffce848e1633a1a9e8eaa546
```

Предыдущий documentation checkpoint:

```text
c1cc52aae63ecde4cec995dbc54cc6616ecbe870
docs: close stage 9 and plan stage 10
```

Текущий тестовый baseline:

```text
Full:    567 tests / 2391 assertions
Focused (notification-open slice): 96 tests / 347 assertions
PHPUnit 10.5.20
SQLite :memory: (единственно допустимая БД для PHPUnit)
Canonical migrations: 53 Ran / 0 Pending (после guarded trip-reminder migration)
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
- Stage 10 — 🚧 IN PROGRESS (9 slices done, next slice not yet selected)
- Stage 11–13 — 📋 PLANNED / ⏸ DEFERRED

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

## Stage 10. Уведомления — 🚧 IN PROGRESS

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
  функциональный checkpoint; local/origin HEAD совпадали на `01447040` на
  момент закрытия этого функционального слайса. Последующий docs-only
  commit меняет репозиторный HEAD, но не меняет сам функциональный
  checkpoint `01447040` и не меняет его test baseline.

### Следующий Stage 10 slice — не выбран

Никакая широкая реализация не запланирована заранее. Следующий маленький
Stage 10 slice выбирается только после отдельного read-only discovery:

- текущие UI-поверхности кабинета для непрочитанных уведомлений (bell,
  dropdown, список, unread-count) — есть ли они и в каком состоянии;
- mail/queue delivery, events/listeners, retries и failure handling для
  остальных типов уведомлений;
- broadcast-поверхности для admin/manager (все чаты, все заявки);
- наличие/отсутствие scheduler или queue worker operational contract;
- сравнение исторической документации (включая архив) и фактической
  реализации;
- продуктовые пробелы, не покрытые завершёнными 9 слайсами.

Предварительный (не гарантированный) scope после discovery может включать:

- уведомления в кабинете (bell/dropdown/список/unread-count), если ещё
  не реализованы;
- status-change email, если ещё не покрыт;
- optional Telegram/SMS/WebSocket только после отдельного feasibility.

До выбора scope и exact allowed paths — код не менять.

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

- расписание для `news:sync-rss`;
- реальный `news:sync-rss` против canonical MySQL только по отдельному
  operational plan;
- статический `sitemap.xml` с 272 устаревшими `lastmod` 2024 года;
- актуализация route contract в `robots.txt`;
- расширение page-specific title/meta description/Open Graph coverage;
- постоянный manager live account отсутствует; изолированная временная
  smoke-стратегия проверена;
- deprecated PHPUnit XML schema;
- recovery artifact retention policy;
- единый E2E lifecycle scenario;
- история и аудит заявки;
- WebSocket/unread counters для чата.

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

Stage 10 в процессе. Последний функциональный checkpoint — `01447040`
(см. раздел Stage 10 выше); он не меняется публикацией текущего docs-only
checkpoint.

1. После публикации текущего docs-only checkpoint — создать и независимо
   проверить новый handoff, roadmap copy, new-chat prompt, source archive,
   manifest и Project Sources set для получившегося документационного
   HEAD.
2. Только после этого выполнить отдельный **read-only discovery**
   оставшейся notification architecture и текущих продуктовых пробелов
   (см. раздел Stage 10 выше).
3. Затем выбрать один маленький следующий Stage 10 slice и exact allowed
   paths до начала любых новых правок.
4. До этого выбора широкую реализацию, миграции, canonical DB операции,
   production-интеграции и деструктивные действия не выполнять.
