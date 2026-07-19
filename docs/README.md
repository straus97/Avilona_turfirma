# Документация Avilona_turfirma

## Текущий checkpoint

| Параметр | Значение |
|---|---|
| Ветка | `db-rebuild-stage3` |
| Функциональный checkpoint | `973975d6c01e4b22544defaa9c9ff67ffccbd4b3` |
| Commit | `fix: align booking show per-booking authorization` |
| Предыдущий maintenance commit | `49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f` |
| PHPUnit baseline | 352 tests / 1183 assertions |
| PHPUnit DB | SQLite `:memory:` |
| Canonical schema | `turfirma_rebuild_v4` |
| Canonical migrations | 52 Ran / 0 Pending |
| Recovery | COMPLETE |
| Stage 5 | COMPLETE |
| Stage 6 | COMPLETE |
| Stage 7 | COMPLETE |

Эта документация описывает функциональное состояние на checkpoint `973975d6`.
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

После documentation/source refresh — read-only discovery для Stage 8:

1. инвентаризировать текущие tour/catalog/search routes, models, views и tests;
2. проверить фактическую структуру canonical schema без изменений БД;
3. отдельно оценить feasibility внешних интеграций без реальных запросов;
4. выбрать один маленький первый Stage 8 slice и exact allowed paths.

До утверждения плана не начинать широкий каталог или интеграционную реализацию.
