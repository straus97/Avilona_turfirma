# Avilona_turfirma

Веб-сайт и внутренняя система туристического агентства «Авилона» на Laravel.

## Текущий статус

- Project path: `C:\wamp\www\Avilona_turfirma`
- Branch: `db-rebuild-stage3`
- Текущий функциональный checkpoint: `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` — `fix: remove obsolete guest booking flow`
- Stage 0–12: ✅ COMPLETE
- Stage 13: ✅ **COMPLETE** — repository/local technical closure (production deployment и endgame audit/redesign — отдельные последующие этапы)
- Последний independently verified full PHPUnit baseline: **917 tests / 4012 assertions**, PHP 8.3.32, SQLite `:memory:`
- Laravel: 12.65.0; PHPUnit: 11.5.56

Documentation/source checkpoint, содержащий этот файл, является docs-only commit поверх функционального checkpoint `dba20e2c` и определяется текущим Git HEAD. Внешний Project Sources checkpoint пока не обновлён — см. `docs/README.md` §8.

## Что уже завершено в Stage 13

Помимо ранних production-readiness slice (Vite build hygiene, browser runtime fixes, responsive fixes, development-notice cleanup, cookie consent/analytics gating, company details и раздельного personal-data consent на «Главной»/«Контактах»), завершён большой review-flow:

- публичный review UGC безопасно экранируется;
- email удалён из публичного содержимого отзыва как legacy/public field;
- отзывы всегда уходят на модерацию, auto-publish нет;
- review-specific consent evidence хранится отдельно от публичного отзыва;
- три обязательных подтверждения разделены;
- private evidence-поля `consent_full_name` и `consent_email` не публикуются;
- public scope зафиксирован как `name + content`;
- публичный subject/title из review flow удалён, legacy DB field пока сохранён;
- добавлены отдельные legal pages review consent/publication consent;
- validation UX возвращает пользователя к форме и фокусирует первую ошибку;
- moderation foundation хранит `is_moderator_edited` и `moderator_edited_at`;
- Admin и Manager не могут менять публичное имя автора;
- условия/запреты автора должны быть явно подтверждены модератором перед публикацией;
- изменение content инвалидирует stale confirmation;
- moderator-edited marker sticky;
- Admin/Manager moderation UI имеет parity;
- на `/reviews` и homepage показывается точная публичная пометка только для реально отредактированного модератором текста:
  `Текст отзыва отредактирован модератором без изменения общего смысла.`
- `moderator_edited_at`, private consent/evidence и moderator identity публично не выводятся;
- publication-consent withdrawal (`withdrawn_at`) обеспечивает fail-safe публикации и не допускает Admin/Manager republish withdrawn review; дедикейтед operator workflow фиксирует уже полученный withdrawal request;
- регистрация требует двух раздельных подтверждений (User Agreement; consent на обработку персональных данных для регистрации/кабинета), evidence хранится в `UserRegistrationConsent`, создаётся атомарно вместе с пользователем и ролью Tourist;
- Пользовательское соглашение расширено §9 для регистрации/личного кабинета;
- добавлены independent password visibility toggles на login и registration (password + confirmation);
- анонимное бронирование не поддерживается: мёртвый anonymous booking flow и дублирующий `/tours` modal удалены, `/tours` CTA использует канонический `bookings.create`.

Подробности — `docs/README.md` §5.

## Непосредственно оставшаяся работа Stage 13

Stage 13 **закрыт** на уровне repository/local technical closure. Не закрытые смежные пункты:

- Manager review cache parity relevance re-check (read-only first; см. `docs/roadmap.md` S13-R2);
- внешний Project Sources refresh под новый docs closure HEAD (см. `docs/README.md` §8);
- comprehensive audit / production deploy — отдельные последующие этапы (см. ниже).

## Endgame после Stage 13

После функционального закрытия Stage 13 проект **не считается визуально финальным**.

Запланирован отдельный комплексный technical + UX/UI/design pass:

- аудит всех публичных страниц и пользовательских сценариев;
- выявление слабых мест, устаревших решений и грубой/неудачной компоновки;
- адаптивность, визуальная иерархия, spacing, сетки, типографика, состояния форм;
- проверка логичности расположения блоков и компонентов;
- оптимизация кода/структуры там, где она реально оправдана;
- системная модернизация дизайна публичного сайта и отдельных элементов;
- отдельный глубокий pass личных кабинетов: структура, навигация, карточки, таблицы, формы, статусы, иконки, цветовая схема, визуальная иерархия, mobile/desktop usability;
- решения по redesign принимаются после анализа, а не механически «перерисовать всё»;
- после redesign — полный regression/cross-device pass.

### Поиск туров — самый последний продуктовый блок

Текущий search/tours widget считается **временной заглушкой**. Его не доводить до финального решения раньше времени.

После стабилизации и design-pass отдельно выбрать архитектуру:

- подходящий готовый агрегатор/виджет;
- API/интеграции туроператоров;
- собственный поиск/агрегация, если это оправдано.

Финальный вариант должен учитывать публичный UX, кабинеты и будущую CRM-интеграцию. Реальные внешние provider-запросы — только по отдельному guarded operational plan.

## Технологический стек

| Компонент | Значение |
|---|---|
| PHP CLI проекта | 8.3.32 |
| Laravel | 12.65.0 |
| PHPUnit | 11.5.56 |
| PHPUnit DB | SQLite `:memory:` |
| UI | Blade + Bootstrap 5 |
| Canonical local DB | `turfirma_rebuild_v4`, port 3308 |
| Build | Vite 7.3.6 + laravel-vite-plugin 2.1.0 |

Для проекта использовать только:

```text
C:\wamp\bin\php\php8.3.32\php.exe
```

PHPUnit никогда не запускать против canonical MySQL.

## Документация

- [`docs/README.md`](docs/README.md) — operational source of truth и checkpoint ledger.
- [`docs/roadmap.md`](docs/roadmap.md) — текущий roadmap и endgame.
- `docs/archive/` — исторические документы, не руководство к действию без сверки с текущим кодом.

## Жёсткие ограничения

Без отдельного утверждённого guarded plan не выполнять:

- `composer update`, `npm update`, `npm audit fix`;
- migrations/seed/import/reset/refresh/wipe;
- PHPUnit против canonical MySQL;
- реальные внешние provider integrations;
- деструктивные DB/repository операции;
- широкие refactor/batch-изменения вместо одного semantic slice.
