# Документация Avilona_turfirma

## Текущий checkpoint

| Параметр | Значение |
|---|---|
| Ветка | `db-rebuild-stage3` |
| Функциональный checkpoint | `9ba1b1293571ab30675919373c7480764cf0b61d` |
| Commit | `fix: align cabinet shared-route role redirects` |
| Предыдущий maintenance commit | `49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f` |
| PHPUnit baseline | 295 tests / 951 assertions |
| PHPUnit DB | SQLite `:memory:` |
| Canonical schema | `turfirma_rebuild_v4` |
| Canonical migrations | 52 Ran / 0 Pending |
| Recovery | COMPLETE |

Эта документация описывает функциональное состояние на checkpoint `9ba1b129`.
Документационный HEAD, содержащий текущую версию файлов, всегда определяется Git.

## Источники истины

Приоритет:

1. Текущий HEAD Git и исходный код.
2. Этот файл и [`roadmap.md`](roadmap.md).
3. Последние проверенные PHPUnit, migration и recovery evidence.
4. Актуальные внешний handoff, roadmap и source archive для конкретного pushed HEAD.
5. Исторические документы и старые Project Sources.

Старые recovery-файлы со статусом `R5 pending` являются историческими и не должны определять текущее состояние.

## Подтверждённый стек

| Компонент | Значение |
|---|---|
| PHP CLI | 8.3.32 |
| Laravel | 10.48.10 |
| Composer | 2.8.3 |
| Node.js | 22.11.0 |
| npm | 11.12.1 |
| PHPUnit | 10.5.20 |
| PHPUnit DB | SQLite `:memory:` |
| Canonical MySQL | 9.7.1, `turfirma_rebuild_v4`, port 3308 |
| UI | Blade + Bootstrap 5 |
| Build | Vite 4.x |

## Recovery checkpoint

Аварийное восстановление завершено.

Подтверждено:

- canonical DB promoted и проверена;
- application fingerprint:
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
- canonical fingerprint до/после live validation не изменился.

Recovery/rollback artifacts не удалять до отдельного retention-решения.

## Последние функциональные checkpoint

### RSS maintenance — `49fe6fbf`

- `GET /helpful_information/news` стал read-only.
- Внешний RSS-запрос и DB-write удалены из публичного GET.
- Добавлены `RssNewsSyncService` и команда `news:sync-rss`.
- Безопасный libxml, fakeable HTTP, транзакция, idempotency, SoftDeletes и slug collision handling.
- Команда не запускалась против canonical MySQL.
- Отдельный backlog: `/helpful_information/news/rss` затенён маршрутом `/{slug}`.

### Stage 7 slice — `9ba1b129`

Завершён slice `Cabinet shared-route role redirect consistency`:

- единый приоритет ролей `admin > manager > tourist`;
- корректные ролевые редиректы общих маршрутов;
- targeted coverage: 8 tests / 43 assertions;
- commit содержит только controller и focused test.

Этот commit не закрывает весь Stage 7.

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

После documentation/source refresh — read-only аудит следующего маленького Stage 7 slice.
До аудита не предлагать широкий UI-refactor и не начинать Stage 8–13.
