# Avilona_turfirma

Веб-сайт и внутренняя система бронирования туристического агентства «Авилона».
Приложение построено на Laravel 10 и обслуживает роли **турист**, **менеджер** и **администратор**.

## Текущее состояние

- Активная ветка: `db-rebuild-stage3`.
- Функциональный checkpoint этой документации:
  `9ba1b1293571ab30675919373c7480764cf0b61d`
  — `fix: align cabinet shared-route role redirects`.
- Предыдущий maintenance checkpoint:
  `49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f`
  — `fix: move news RSS sync out of public request`.
- Фактический текущий HEAD всегда определяется Git (`git rev-parse HEAD`), а не только значением в документации.
- Этапы 5 и 6 завершены.
- В Этапе 7 завершён первый изолированный slice — согласованность редиректов общих маршрутов кабинета. Широкий UX-аудит кабинетов продолжается отдельными небольшими slice.
- Аварийное восстановление canonical MySQL завершено и проверено.

## Подтверждённые возможности

- Аутентификация, email verification и роли admin / manager / tourist.
- Создание, просмотр, назначение и жизненный цикл бронирований.
- Правила переходов статусов и согласованный UI.
- Безопасное назначение активных менеджеров и администраторов.
- Приватные пользовательские документы и документы бронирования.
- Защищённый чат участников бронирования и приватные вложения.
- Post-persistence уведомления с изоляцией ошибок доставки.
- Согласованные ролевые редиректы общих маршрутов кабинета.
- Публичная страница новостей работает только на чтение:
  - не выполняет внешний RSS-запрос;
  - не изменяет базу данных;
  - RSS-синхронизация вынесена в явную команду `news:sync-rss`;
  - реальная команда не запускалась против canonical MySQL.

Полный перечень checkpoint и оставшихся этапов приведён в
[`docs/roadmap.md`](docs/roadmap.md).

## Технологический стек

| Слой | Технология |
|---|---|
| PHP CLI | 8.3.32 |
| Фреймворк | Laravel 10.48.10 |
| Composer | 2.8.3 |
| Node.js | 22.11.0 |
| npm | 11.12.1 |
| Сборка | Vite 4.x |
| UI | Blade + Bootstrap 5 |
| Canonical DB | MySQL 9.7.1, `turfirma_rebuild_v4`, port 3308 |
| Тесты | PHPUnit 10.5.20, SQLite `:memory:` |

## Canonical DB

Восстановление canonical DB завершено.

Подтверждено:

- 52 migrations Ran / 0 Pending;
- 28 таблиц InnoDB;
- users=7, role_user=7, roles=3;
- распределение ролей: admin=1, manager=0, tourist=6;
- application fingerprint:
  `fc449488f3d115713cfa0ee97b62a933dfa11393cdbc89c391816aa25d174784`;
- public smoke: 15/15;
- protected unauthenticated smoke: 14/14;
- fingerprint до и после HTTP-проверок не изменился.

Старый rollback/physical backup и recovery evidence пока не удалять без отдельного retention-решения.

## Локальная разработка

```bash
composer install
npm ci
```

Настройте локальный `.env`. Не публикуйте `.env`, SQL-дампы, токены, cookies и приватные документы.

Обычный локальный запуск допускается только после отдельной проверки текущего operational state.
Не запускайте команды записи в canonical DB без утверждённого плана.

## Тестирование

PHPUnit всегда должен использовать:

```text
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Текущий baseline:

```text
295 tests
951 assertions
```

```bash
php artisan test --no-ansi
```

Известное неблокирующее предупреждение: `phpunit.xml` использует устаревшую XML schema.
Не смешивать её обновление с функциональным slice.

## Запрещённые операции без отдельного плана

- `composer update`
- `npm update`
- `npm audit fix`
- `migrate`, `migrate:fresh`, `refresh`, `reset`, `db:wipe`, seed
- `legacy:import-v4 --execute`
- PHPUnit против canonical MySQL
- запуск `news:sync-rss` против canonical MySQL
- удаление recovery/rollback artifacts

## Документация

- [`docs/README.md`](docs/README.md) — источники истины и правила работы.
- [`docs/roadmap.md`](docs/roadmap.md) — актуальная дорожная карта.

## Текущий приоритет

Следующий функциональный шаг — **read-only аудит следующего небольшого slice Этапа 7**.
Не начинать широкий рефакторинг кабинетов целиком и не смешивать его с backlog-дефектами RSS/CMS.
