# Avilona_turfirma

Веб-сайт и внутренняя система туристического агентства «Авилона» на Laravel.

## Текущий статус

- Project path: `C:\wamp\www\Avilona_turfirma`
- Branch: `db-rebuild-stage3`
- **Текущий authoritative application HEAD: `ad6e9c23986d479cbbbf6f511e96bc139ae26576` — `feat: redesign public News + Articles editorial experience (E2-A5-I1)`**
- Историческое E1-closure application commit: `08d0626311234faa06dedf2828cb878805241990` — `fix: close final public audit gaps` (это НЕ текущий HEAD)
- Предыдущий функциональный checkpoint (Stage 13): `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` — `fix: remove obsolete guest booking flow`
- Stage 0–13: ✅ CLOSED
- E1 Comprehensive Audit: ✅ **TECHNICALLY CLOSED**
- Текущая фаза: **E2 — Public UX / UI / Design Redesign — 🔄 IN PROGRESS** (E2-A1…E2-A5 завершены; следующий slice ещё не выбран)
- Последний independently verified full PHPUnit baseline: **1006 tests / 7037 assertions**, PHP 8.3.32, PHPUnit 11.5.56, Laravel 12.65.0, SQLite `:memory:`
  - до E2-A5 baseline был **1001 tests / 7013 assertions** (историческое E1-closure значение); E2-A5 намеренно добавил 5 regression-тестов (+24 assertions) — это не регрессия
- Единственная PHPUnit deprecation — pre-existing XML schema deprecation, не функциональный/кодовый сбой

Documentation/source checkpoint, содержащий этот файл, — docs-only commit поверх
application HEAD `ad6e9c23` и определяется текущим Git HEAD. Будущий docs commit
hash пока неизвестен и не зашивается заранее.

Активный внешний Project Sources набор — **исторический E1-closure набор для
`0be9044e35ec5be670c8f9bb33de020491214423`** (`docs: close E1 and prepare E2`).
Он **устарел (STALE)** относительно текущего application HEAD `ad6e9c23`, потому что
E2-A1…E2-A5 выполнены позже. Refresh обязателен ПОСЛЕ review/commit/push этого
docs-only slice и появления чистого нового documentation HEAD — см. `docs/README.md` §8.

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
`PENDING_BUSINESS_DECISION_OPENING_HOURS` (не выбирать значение — решается до E6).
Пункт per-page `og:type=article` закрыт в E2-A5.

## Roadmap E1…E6

| Фаза | Название | Статус |
|---|---|---|
| E1 | Comprehensive Audit | ✅ TECHNICALLY CLOSED |
| **E2** | **Public UX / UI / Design Redesign** | **🔄 IN PROGRESS** (E2-A1…E2-A5 завершены) |
| E3 | Tourist / Manager / Admin Cabinet UX/UI Redesign | PENDING |
| E4 | Post-redesign stabilization / regression / browser-device QA | PENDING |
| E5 | Final Tour Search Solution | PENDING (намеренно один из последних крупных блоков) |
| E6 | Production Deployment / Operations Validation | PENDING |

E2 — не косметическая перекраска: information architecture, навигация, иерархия
главной, типографика, spacing, цветовая система, формы/кнопки/карточки, responsive,
мобильная навигация, страницы компании/сотрудников/awards/articles/news/reviews,
contacts, empty/error states, consent UI, accessibility, trust, conversion paths.
Финальная механика поиска туров в E2 не переделывается (это E5) — текущий tour-search
widget визуально интегрируется как временный компонент.

### E2 прогресс (завершённые slices)

| Slice | Checkpoint | Область |
|---|---|---|
| E2-A1 | `43a073e676d441021445f73f38733fa70a0e1463` | Публичный header/навигация + первый экран главной, hero, один H1, CTA-иерархия, aria-current |
| E2-A2 | `72202ab7d35b064ab4b0c66147bfff21357e5343`, `eaa2093f5f406e7a5fbd73c6fe3a1897802852a` | Home below-the-fold, shared public shell, footer cleanup, единый manager-contact слой, устранён page-jump телефона, убран сломанный Yandex-информер |
| E2-A3 | `5e22e4b78ed6e8610d4c2b7f11043ff9e1336806` | Public travel discovery: Countries, Destinations, Specials |
| E2-A4 | `94aedad09468d50be45e8f11c4be0a8c41dbb474` | Company/trust: About Company, Employees, Awards |
| E2-A5 | `ad6e9c23986d479cbbbf6f511e96bc139ae26576` | News + Articles editorial experience (listing/detail, shared editorial card) |

E2 глобально **не завершён**. Оставшиеся публичные маршруты (Reviews, Contacts,
helper/content/error/empty surfaces) будут проверены read-only перед выбором
следующего slice. Детали — `docs/README.md` §9B и `docs/roadmap.md`.

## Канонические факты компании

- Официальный публичный e-mail: `avilonatur@bk.ru`.
- Получатель входящей публичной формы: `straus97@mail.ru` (намеренный внутренний
  submission recipient; не заменять на публичный e-mail).
- Текущий фактический офис / публичный адрес: `198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10`.
- Старый адрес на Звенигородской (`191119, ... ул. Звенигородская, д. 22, литера А,
  офис 053, пом. 7Н`) — не текущее физическое расположение; где он явно помечен как
  юридический/регистрационный адрес — это намеренно.
- Параллельно идёт регистрация юр. адреса `198302, Санкт-Петербург, ул. Морской
  Пехоты, д. 10, корп. 1, литера А, кв. 22`. Успешная регистрация в ФНС/ЕГРЮЛ
  пользователем **не подтверждена** — этот адрес НЕ документируется как текущий
  зарегистрированный/юридический (только как pending параллельный трек).
- Оплата: наличные; интернет-эквайринг; QR на расчётный счёт организации;
  эквайринговый терминал в офисе.
- Возвраты: на банковскую карту клиента; итоговая сумма зависит от условий/решения
  туроператора; при неподтверждённом отеле возможен полный возврат; отмена по
  инициативе клиента может быть ограничена условиями оператора.
- Передача документов: Авилона публично не рекламирует отдельную курьерскую
  доставку. Каноническая формулировка — `«Передача документов по договорённости»`.
- Рассрочка/кредит на поездки предлагаются, но только с общей формулировкой — без
  выдуманных банков, ставок, партнёров и финансовых условий.
- Часы работы: `PENDING_BUSINESS_DECISION_OPENING_HOURS` — конфликт копий (home
  «будни 10:00–20:00» vs contacts «будни 11:00–20:00, по записи»). Не выбирать;
  решается до E6.

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
