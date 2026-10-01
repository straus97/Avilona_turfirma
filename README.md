# Avilona_turfirma

Веб-сайт и внутренняя система туристического агентства «Авилона» на Laravel.

## Текущий статус

- Project path: `C:\wamp\www\Avilona_turfirma`
- Branch: `db-rebuild-stage3`
- **Текущий authoritative application HEAD: `c56e330685ffa8953b22c803d6dce5334c69e81b` — `fix: complete final tour search polish (E5-A4)`**
- Stage 0–13: ✅ CLOSED; E1 — ✅ TECHNICALLY CLOSED; E2 — ✅ CLOSED; E3 — ✅ CLOSED; E4 — ✅ CLOSED; **E5 — ✅ CLOSED на уровне приложения** (E5-A1…E5-A4)
- **Приложение закрыто (E5), но production НЕ развёрнут.** Закрытие E5 ≠ production-ready.
- **Следующий шаг: Screenshot Audit Pack + независимый полный аудит (Astra / ChatGPT Work) → утверждённые исправления (если есть) → E6.**
- Full PHPUnit baseline: **1393 tests / 9759 assertions**, 0 failures, 0 errors (PHP 8.3.32, PHPUnit 11.5.56, Laravel 12.65.0, SQLite `:memory:`); 1 PHPUnit deprecation — XML-схема `phpunit.xml`, не функциональный сбой

Documentation/source checkpoint, содержащий этот файл, — docs-only commit поверх
application HEAD `c56e3306` и определяется текущим Git HEAD. Будущий docs commit
hash заранее не зашивается.

Внешний Project Sources набор от 2026-09-23 (`a1d72a40`) — **исторический**,
предшествует всей реализации E5; авторитетны текущий репозиторий и docs на pushed
HEAD. Свежий набор генерируется из нового чистого docs HEAD — см. `docs/README.md` §8.1.

Подробности — `docs/README.md` (§1 checkpoint, §10 E5, §12 E6, §13 инфраструктура).

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
| E2 | Public UX / UI / Design Redesign | ✅ CLOSED (E2-A1…E2-A7) |
| E3 | Tourist / Manager / Admin Cabinet UX/UI Redesign | ✅ CLOSED (E3-A1…E3-A6) |
| E4 | Post-redesign stabilization / regression / browser-device QA | ✅ CLOSED (E4-A…E4-E1) |
| E5 | Final Tour Search Solution (Tourvisor module → IncomingInquiry → нативная Booking) | ✅ CLOSED на уровне приложения (E5-A1…E5-A4); **production НЕ развёрнут** |
| — | Screenshot Audit Pack + независимый полный аудит (Astra / ChatGPT Work) | ⬜ **NEXT** — до E6 |
| E6 | Production Deployment / Operations Validation | ⬜ PENDING |

Детали каждой фазы — `docs/README.md` §9–§13 и `docs/roadmap.md`.

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
