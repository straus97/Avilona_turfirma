# Avilona_turfirma — актуальная дорожная карта

Дата checkpoint: **2026-07-18**

Функциональный checkpoint:

```text
9ba1b1293571ab30675919373c7480764cf0b61d
fix: align cabinet shared-route role redirects
```

Предыдущий maintenance checkpoint:

```text
49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f
fix: move news RSS sync out of public request
```

Текущий тестовый baseline:

```text
295 tests
951 assertions
PHPUnit 10.5.20
SQLite :memory:
```

## Сводный статус

- Stage 0–4 — ✅ COMPLETE
- Stage 5 — ✅ COMPLETE
- Stage 6 — ✅ COMPLETE
- Emergency DB Recovery R0–R6D — ✅ COMPLETE
- RSS maintenance slice — ✅ COMPLETE
- Stage 7 — 🔄 IN PROGRESS
- Stage 8–13 — 📋 PLANNED

---

## Emergency DB Recovery — ✅ COMPLETE

Ошибочно пересозданная canonical DB `turfirma_rebuild_v4` восстановлена и promoted.

Подтверждено:

- 52 migrations Ran / 0 Pending;
- 28 таблиц InnoDB;
- 376 application rows;
- users=7, role_user=7, roles=3;
- admin=1, manager=0, tourist=6;
- all row-count, index, FK, CHECK TABLE и AUTO_INCREMENT проверки PASS;
- final canonical manifest:
  `78AD452573B4305897E20465F3B599F44C1E4C2636DE2DB321E695E4B70DB4B1`;
- promoted logical dump:
  `BDFAF8679622322495F47AE869AEFCE8614282227A49CA5BCD3BB28DCD1D8FA4`;
- application fingerprint:
  `fc449488f3d115713cfa0ee97b62a933dfa11393cdbc89c391816aa25d174784`;
- public smoke 15/15;
- protected unauthenticated smoke 14/14;
- admin/tourist credentials checked without login POST и без DB-write;
- fingerprint unchanged after live validation;
- SQLite gate: 278 tests / 879 assertions до RSS slice.

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
- full suite после RSS и Stage 7 worktree: 295 / 951.

Backlog:

1. `/helpful_information/news/rss` затенён более ранним `/{slug}`.
2. Автоматическое расписание `news:sync-rss` не настроено.
3. Реальный sync против canonical MySQL до отдельного operational plan не выполнять.

## Stage 7. Личные кабинеты и единый UX — 🔄 IN PROGRESS

### Завершённый slice

`9ba1b129` — `fix: align cabinet shared-route role redirects`

- общий role precedence: admin > manager > tourist;
- корректные переходы на bookings/chats/profile/settings;
- focused test: 8 tests / 43 assertions;
- commit содержит ровно два файла.

### Следующий порядок работы

1. Read-only аудит текущих кабинетов и ролевых потоков.
2. Выбрать один маленький UX/authorization slice.
3. Зафиксировать exact allowed paths.
4. Plan → manual edits → lint → targeted tests → full SQLite suite.
5. Отдельный commit/push.

Не начинать массовый редизайн кабинетов одним большим slice.

## Stage 8. Каталог и поиск туров — 📋

- публичный каталог;
- фильтрация и поиск;
- отдельный feasibility-анализ внешних интеграций.

## Stage 9. Публичный контент и CMS — 📋

- статические страницы;
- новости и статьи;
- отзывы и модерация;
- SEO;
- backlog RSS route;
- operational/scheduled RSS sync.

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
- manager live login smoke: сейчас в canonical нет manager account;
- deprecated PHPUnit XML schema;
- recovery artifact retention policy.

## Жёсткие ограничения

Без отдельного утверждённого плана запрещено:

- `composer update`;
- `npm update`;
- `npm audit fix`;
- migrations/seed/import/reset/wipe;
- `legacy:import-v4 --execute`;
- PHPUnit против canonical MySQL;
- реальный `news:sync-rss` против canonical MySQL;
- удаление recovery/rollback artifacts.

## Ближайший следующий slice

После docs-only refresh и обновления Project Sources:

**read-only аудит следующего небольшого Stage 7 slice**.

Никаких изменений кода до выбора точного scope.
