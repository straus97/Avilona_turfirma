# Avilona_turfirma

Веб-сайт и внутренняя система туристического агентства «Авилона» на Laravel.

## Текущий статус

> **Единственный канонический источник статуса, порядка работ и решений владельца — [`docs/roadmap.md`](docs/roadmap.md).** Этот файл — краткая сводка; при расхождении прав `docs/roadmap.md`.

- Project path: `C:\wamp\www\Avilona_turfirma`
- Branch: `db-rebuild-stage3`
- **Application HEAD (последний код-коммит): `6cbdbcdab1df4b7d7605ed22cc469c53128163a3` — `fix: handle booking children ages in admin email (AO-01)`**; documentation checkpoint — docs-only commit поверх него (hash определяется Git).
- Stage 0–13, E1, E2, E3, E4 — ✅ CLOSED; **E5 — ✅ FULLY CLOSED** (application level); **E5.5 Agency Operations Automation — 🟢 ACTIVE** (закрыты только архитектурный checkpoint E5.5-A0 и AO-FIX-01; **остальное не реализовано**); E6 — ⬜ PENDING.
- **Production НЕ развёрнут** (используется текущий хостинг REG.RU). Закрытие приложения ≠ production-ready.
- Full PHPUnit baseline: **1403 tests / 9799 assertions**, 0 failures, 0 errors (AO-FIX-01; PHP 8.3.32, PHPUnit 11.5.56, Laravel 12.65.0, SQLite `:memory:`); 1 PHPUnit deprecation — XML-схема `phpunit.xml`, не функциональный сбой.
- Следующий шаг, одобренные решения владельца (доступ Manager/Admin, назначение обращений, финансовые правила), открытые решения и план AOA — **только в [`docs/roadmap.md`](docs/roadmap.md)**.

Внешние Project Sources / Handoff — версионированные снимки этого репозитория, а не отдельный источник истины. Набор от 2026-09-23 (`a1d72a40`) — **исторический**. Процесс refresh — `docs/README.md` §8.1.

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
| — | Screenshot Audit Pack + независимый анализ (Astra / ChatGPT Work) | ✅ выполнены вне репозитория; статус AO — в `docs/roadmap.md` §A7 |
| **E5.5** | Agency Operations Automation (AOA): Inquiry / OperatorBooking / finances / documents / Bridge / VK / e-mail | 🟢 **ACTIVE** — закрыты только E5.5-A0 (архитектура) и AO-FIX-01; остальное не реализовано; план и решения владельца — `docs/roadmap.md` §A3–§A9 |
| E6 | Production Deployment / Operations Validation | ⬜ PENDING (после E5.5 и финального независимого аудита) |

Таблица — краткая сводка. Канонический статус и план — `docs/roadmap.md`; исторические детали фаз — `docs/README.md` §9–§13.

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

- [`docs/README.md`](docs/README.md) — индекс документации, workflow/guards, checkpoint ledger (статус и NEXT — только в roadmap).
- [`docs/roadmap.md`](docs/roadmap.md) — **канонический roadmap**: статус этапов, решения владельца, план AOA, открытые решения, NEXT, production readiness.
- [`docs/E5.5-agency-operations-automation-architecture-2026-10-19.md`](docs/E5.5-agency-operations-automation-architecture-2026-10-19.md) — техническая архитектура и инварианты E5.5 (справочно; статус и план — в roadmap).
- `docs/archive/` — исторические документы, не руководство к действию без сверки с текущим кодом.

## Жёсткие ограничения

Без отдельного утверждённого guarded plan не выполнять:

- `composer update`, `npm update`, `npm audit fix`;
- migrations/seed/import/reset/refresh/wipe;
- PHPUnit против canonical MySQL;
- реальные внешние provider integrations;
- деструктивные DB/repository операции;
- широкие refactor/batch-изменения вместо одного semantic slice.
