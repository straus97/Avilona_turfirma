# Avilona_turfirma

Веб-сайт и внутренняя система бронирования туристического агентства «Авилона».
Приложение построено на Laravel 10 и обслуживает роли **турист**, **менеджер** и **администратор**.

## Текущее состояние

- Активная ветка: `db-rebuild-stage3`.
- Функциональный checkpoint:
  `bae264802a17bac3c796481da7f10096acfc3cb2`
  — `fix: remove unsupported public nonstop filter`.
- Предыдущий maintenance checkpoint:
  `49fe6fbfee72972a274f1b2cd29db5aa2bc0d21f`
  — `fix: move news RSS sync out of public request`.
- Этапы 5, 6, 7 и 8 завершены.
- Аварийное восстановление canonical MySQL завершено и проверено.
- Документационный commit, содержащий эту версию файлов, всегда определяется Git.

## Подтверждённые возможности

- Аутентификация, email verification и роли admin / manager / tourist.
- Создание, просмотр, назначение и жизненный цикл бронирований.
- Правила переходов статусов и согласованный UI.
- Безопасное назначение активных менеджеров и администраторов.
- Приватные пользовательские документы и документы бронирования.
- Защищённый чат участников бронирования и приватные вложения.
- Post-persistence уведомления с изоляцией ошибок доставки.
- Единый role precedence: `admin > manager > tourist`.
- Согласованные ролевые переходы кабинета, header/sidebar links и booking UI.
- Booking-scoped staff authority:
  - admin всегда остаётся staff-facing;
  - manager получает staff-facing доступ только к назначенной ему заявке;
  - manager+tourist, владелец, но не назначенный менеджер, остаётся owner-facing;
  - owner-facing скачивание документа не открывает staff-only маршрут.
- Dual-role участники сохраняют корректный доступ к чату своей заявки.
- Публичная страница новостей работает только на чтение:
  - не выполняет внешний RSS-запрос;
  - не изменяет базу данных;
  - RSS-синхронизация вынесена в явную команду `news:sync-rss`;
  - реальная команда не запускалась против canonical MySQL.
- Каталог и поиск туров (Stage 8):
  - публичный каталог туров работает только на чтение, не изменяет базу
    данных и не выполняет внешние HTTP-запросы;
  - отображаются только активные и не удалённые туры;
  - публичные фильтры выровнены с внутренним JSON tour-search API
    (tour_operator, nights, rating, charter, direct_flight, hotel_name);
  - сортировка по рейтингу использует `hotel_rating`, а не `hotel_stars`;
  - канонические значения tour_operator и sort=`popular` используются формой;
  - неподдерживаемые контролы `nonstop` и `instant_confirmation` удалены,
    а не реализованы с придуманным поведением;
  - существующий код интеграции с Sletat/Coral Travel статически
    инвентаризирован; реальные внешние запросы и credentials не
    выполнялись и не сохранялись; операционная интеграция остаётся
    отдельным guarded-планом.

Полный перечень checkpoint и оставшихся этапов приведён в
[`docs/roadmap.md`](docs/roadmap.md).

## Технологический стек

| Слой | Технология |
|---|---|
| PHP CLI проекта | 8.3.32 |
| Фреймворк | Laravel 10.48.10 |
| Composer | 2.8.3 |
| Node.js | 22.11.0 |
| npm | 11.12.1 |
| Сборка | Vite 4.x |
| UI | Blade + Bootstrap 5 |
| Canonical DB | MySQL 9.7.1, `turfirma_rebuild_v4`, port 3308 |
| Тесты | PHPUnit 10.5.20, SQLite `:memory:` |

Глобальная команда `php` в текущей Windows-среде может указывать на PHP 8.4.13.
Для проекта использовать точный executable:

```text
C:\wamp\bin\php\php8.3.32\php.exe
```

## Canonical DB

Восстановление canonical DB завершено.

Подтверждено:

- 52 migrations Ran / 0 Pending;
- 28 таблиц InnoDB;
- users=7, role_user=7, roles=3;
- распределение ролей: admin=1, manager=0, tourist=6;
- bookings/messages/booking_documents/user_documents=0;
- recovery application fingerprint:
  `fc449488f3d115713cfa0ee97b62a933dfa11393cdbc89c391816aa25d174784`;
- Stage 7 read-only session fingerprint:
  `afb2cd19ce0401e82c8bc29f0446785906afd9cc31197f81cc573adb19f06cca`;
- Stage 7 browser-smoke fixtures полностью удалены;
- read-only session fingerprint после cleanup восстановлен без изменений;
- Apache service lifecycle probe: HTTP 200;
- canonical MySQL lifecycle probe: PASS.

Эти два fingerprint получены разными алгоритмами и не должны сравниваться друг с другом.

Старый rollback/physical backup и recovery evidence пока не удалять без отдельного retention-решения.

## Локальная разработка

```bash
composer install
npm ci
```

Настройте локальный `.env`. Не публикуйте `.env`, SQL-дампы, токены, cookies и приватные документы.

Не запускайте команды записи в canonical DB без утверждённого плана.

## Тестирование

PHPUnit всегда должен использовать:

```text
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Текущий baseline:

```text
382 tests
1389 assertions
```

Запускать через PHP 8.3.32:

```powershell
& 'C:\wamp\bin\php\php8.3.32\php.exe' `
    'vendor\bin\phpunit' `
    --colors=never
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

Stage 8 закрыт. Следующий функциональный шаг — **read-only discovery для Stage 9**
(публичный контент, CMS, новости/статьи, отзывы и SEO).

До выбора одного маленького семантического slice не начинать широкую реализацию
Stage 9 и не смешивать её с активацией внешних интеграций, обновлением зависимостей
или DB maintenance.
