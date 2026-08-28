# Avilona_turfirma

Веб-сайт и внутренняя система туристического агентства «Авилона» на Laravel.

## Текущий статус

- Project path: `C:\wamp\www\Avilona_turfirma`
- Branch: `db-rebuild-stage3`
- Authoritative E1 closure application commit: `08d0626311234faa06dedf2828cb878805241990` — `fix: close final public audit gaps`
- Предыдущий функциональный checkpoint (Stage 13): `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` — `fix: remove obsolete guest booking flow`
- Stage 0–13: ✅ CLOSED
- E1 Comprehensive Audit: ✅ **TECHNICALLY CLOSED**
- Следующая фаза: **E2 — Public UX / UI / Design Redesign**
- Последний independently verified full PHPUnit baseline: **1001 tests / 7013 assertions**, PHP 8.3.32, PHPUnit 11.5.56, SQLite `:memory:`
- Единственная PHPUnit deprecation — pre-existing XML schema deprecation, не функциональный/кодовый сбой
- Laravel: 12.65.0

Documentation/source checkpoint, содержащий этот файл, — docs-only commit поверх
E1-closure application commit `08d06263` и определяется текущим Git HEAD. Внешний
Project Sources checkpoint обновляется отдельным guarded шагом — см. `docs/README.md` §8.

## Что закрыто

**Stage 0–13** — recovery R0–R6D, жизненный цикл заявки, защищённый чат/документы,
единый role precedence (`admin > assigned manager > owner-facing tourist`),
local/read-only каталог туров, публичный контент/CMS/RSS, уведомления,
security/reliability/performance hardening, dependency modernization (Laravel 12,
Vite 7, vendor/node_modules не отслеживаются), review consent/moderation/withdrawal,
registration consent, password visibility UX, authenticated-only booking. Подробности
— `docs/README.md` §5.

**E1 Comprehensive Audit** — технически закрыт на `08d06263` (`docs/README.md` §5A):

- E1-A1 — canonical social image host, дубли ID публичной навигации, Google Maps consent gating;
- E1-A2 — `tel:` href сотрудника, актуальная копия оплаты/возврата, публичные реквизиты в письмах;
- E1-A3 — sitemap, robots.txt, regression coverage;
- E1-A4 — page-specific динамический OG/Twitter для detail-страниц;
- E1-A5 / RSS — санитизация HTML внешнего RSS (ingest + render-time), безопасные URL-схемы;
- E1-RPD — News listing XSS, публичные inner-cache TTL (один час), nullable image robustness, null-slug rendering;
- E1-FINAL — About slug-ссылки, Article HTML sanitisation (write + historical), About cache TTL, убраны hardcoded «55/12», reload-captcha вне cache, Awards regression; заявление про Cyrillic `Str::slug` опровергнуто runtime.

Намеренно отложенные пункты E1 (НЕ дефекты) — см. `docs/README.md` §5A.2, в т.ч.
`PENDING_BUSINESS_DECISION_OPENING_HOURS`.

## Roadmap после E1

| Фаза | Название | Статус |
|---|---|---|
| E1 | Comprehensive Audit | ✅ CLOSED |
| **E2** | **Public UX / UI / Design Redesign** | **NEXT** |
| E3 | Tourist / Manager / Admin Cabinet UX/UI Redesign | PENDING |
| E4 | Stabilization / Regression / Browser / Device QA | PENDING |
| E5 | Final Tour Search Solution | PENDING |
| E6 | Production Deployment / Operations Validation | PENDING |

E2 — не косметическая перекраска: information architecture, навигация, иерархия
главной, типографика, spacing, цветовая система, формы/кнопки/карточки, responsive,
мобильная навигация, страницы компании/сотрудников/awards/articles/news/reviews,
contacts, empty/error states, consent UI, accessibility, trust, conversion paths.
E2 начинается с READ-ONLY visual/UX inventory и design-system proposal. Финальная
механика поиска туров в E2 не переделывается (это E5) — текущий widget размещается
как временный компонент. Детали — `docs/roadmap.md`.

## Канонические факты компании

- Официальный публичный e-mail: `avilonatur@bk.ru`.
- Получатель входящей публичной формы: `straus97@mail.ru` (намеренный внутренний
  submission recipient; не заменять на публичный e-mail).
- Текущий офис / публичный адрес: `198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10`.
- Старый адрес на Звенигородской — не текущее физическое расположение; где он явно
  помечен как юридический/регистрационный адрес — это намеренно.
- Оплата: наличные; интернет-эквайринг; QR на расчётный счёт организации;
  эквайринговый терминал в офисе.
- Возвраты: на банковскую карту клиента; итоговая сумма зависит от условий/решения
  туроператора; при неподтверждённом отеле возможен полный возврат.

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
- [`docs/roadmap.md`](docs/roadmap.md) — текущий roadmap E1…E6.
- `docs/archive/` — исторические документы, не руководство к действию без сверки с текущим кодом.

## Жёсткие ограничения

Без отдельного утверждённого guarded plan не выполнять:

- `composer update`, `npm update`, `npm audit fix`;
- migrations/seed/import/reset/refresh/wipe;
- PHPUnit против canonical MySQL;
- реальные внешние provider integrations;
- деструктивные DB/repository операции;
- широкие refactor/batch-изменения вместо одного semantic slice.
