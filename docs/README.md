# Документация Avilona_turfirma

Дата актуализации содержания: **2026-10-01**

## 1. Текущий checkpoint

| Параметр | Значение |
|---|---|
| Project | `C:\wamp\www\Avilona_turfirma` |
| Branch | `db-rebuild-stage3` |
| **Текущий authoritative application HEAD** | **`c56e330685ffa8953b22c803d6dce5334c69e81b` (`fix: complete final tour search polish (E5-A4)`)** — финальный application-коммит E5 |
| Прямой parent текущего application HEAD | `00ea2f0440f0a25b6ecd161aadedb804ce9e8ac2` (`fix: remove plaintext staff client credentials (E5-A3.1)`) |
| Documentation checkpoint для этого application HEAD | **ЕЩЁ НЕ СУЩЕСТВУЕТ.** Будет создан отдельным docs-only commit **поверх** `c56e3306` (этот файл + `docs/roadmap.md` + корневой `README.md`). Тот будущий HEAD определяется Git, не известен заранее и не зашивается в этот файл. |
| Documentation/source checkpoint после закрытия E4 / входа в E5 (предыдущий docs-only commit; база **ИСТОРИЧЕСКОГО** набора Project Sources от 2026-09-23) | `a1d72a4034b1d76b35e0033f4c1e2a69121ee06b` (`docs: close E4 and prepare E5`) — предшествует ВСЕЙ реализации E5, НЕ текущий HEAD, НЕ авторитетен над текущим репозиторием |
| Application HEAD на момент закрытия E4 | `55a9fcaf6371ef0c749bb668c9b27783d1df358c` (`fix: close final E4 application polish`) — НЕ текущий HEAD |
| Documentation/source checkpoint после E3-A6 / входа в E4 (историческое) | `c89e923f966f5aaa8bb972e2d6944018f8dce4a8` (`docs: close E3-A6 and advance to E4`) — предшествует E4-A…E4-E1, НЕ текущий HEAD |
| Application HEAD на момент закрытия E3-A6 | `ed550df8989b44e7305bdce6b6f5f063b82a2616` (`fix: polish cross-role chat switching (E3-A6-B)`) — НЕ текущий HEAD |
| Documentation/source checkpoint после E3-A5 (историческое) | `20cde21dda2c38682c796214fbb2401e3f1f7804` (`docs: close E3-A5 and refresh roadmap`) — предшествует E3-A6-A/B, НЕ текущий HEAD |
| Application HEAD на момент закрытия E3-A5 | `9fee7dfb990c7a6c18fc9dcf9205e3db3dca24e6` (`feat: modernize admin cabinet (E3-A5)`) — НЕ текущий HEAD |
| Documentation/source checkpoint после закрытия E2 (историческое) | `886bde9813a088d56d7db1e6b963f6f1d05ab4b2` (`docs: close E2 public redesign`) — НЕ текущий HEAD |
| Documentation/source checkpoint после E2-A5 (историческое) | `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa` (`docs: checkpoint E2 through E2-A5`) — предшествует E2-A6/E2-A7/E3, НЕ текущий HEAD |
| Application HEAD на момент закрытия E2 (E2-A7) | `35f91b9e270cf68654877d42fc8b0d0d59d12458` (`feat: finalize public visual system palette (E2-A7)`) — НЕ текущий HEAD |
| Историческое E1 closure application commit | `08d0626311234faa06dedf2828cb878805241990` (`fix: close final public audit gaps`) — НЕ текущий HEAD |
| Предыдущий функциональный checkpoint (Stage 13) | `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` (`fix: remove obsolete guest booking flow`) |
| Активный внешний documentation/source (Project Sources) checkpoint | пока что — **ИСТОРИЧЕСКИЙ набор от 2026-09-23 на `a1d72a40`** (до E5) — **STALE**; свежий набор строится из нового чистого pushed docs HEAD после этого docs-only коммита (см. §8.1) |
| Full PHPUnit baseline | **1393 tests / 9759 assertions**, 0 failures, 0 errors (финальный E5 baseline, checkpoint `c56e3306`; SQLite `:memory:`; 1 PHPUnit deprecation — XML-конфигурация `phpunit.xml` валидируется против deprecated schema, подтверждено выводом PHPUnit `--display-phpunit-deprecations`). Исторически: на закрытии E5-A3 — 1383 / 9682; на закрытии E4 — 1286 / 8628; на входе в E4 — 1242 / 8056; на закрытии E3-A5 — 1233 / 8023; после закрытия E2 — 1051 / 7180; E1-closure: 1001 / 7013 |
| PHP | `C:\wamp\bin\php\php8.3.32\php.exe` (8.3.32) |
| PHPUnit DB | SQLite `:memory:` only |
| Laravel | 12.65.0 |
| PHPUnit | 11.5.56 |
| Stage 0–13 | ✅ CLOSED |
| E1 Comprehensive Audit | ✅ **TECHNICALLY CLOSED** (см. §5A) |
| E2 — Public UX / UI / Design Redesign | ✅ **COMPLETE / CLOSED на уровне приложения** (E2-A1…E2-A7; см. §9B) |
| E3 — Cabinet UX/UI/Design Modernization | ✅ **CLOSED на уровне приложения** — E3-A1…E3-A5 (Foundation, Tourist, Shared Booking, Manager, Admin) + **E3-A6 point-polish (A + B) ✅ CLOSED**; E3-A6-C не требуется (см. §9C) |
| S13-R2 (Manager review cache parity relevance check) | ✅ **CLOSED** — нет живого public review cache layer, parity не требуется (см. §5.7) |
| **E4 — Post-redesign stabilization / resilience QA** | ✅ **CLOSED** (E4-A…E4-E1; см. §9.4). Закрыт ранее, документационный checkpoint `a1d72a40`. |
| **E5 — Final Tour Search / Aggregation Product Block** | ✅ **CLOSED на уровне приложения** — E5-A1 … E5-A4 закрыты и запушены (см. §10). **Приложение закрыто, но production НЕ развёрнут:** production-деплоя не было, проект **не production-ready** до закрытия E6. |
| **Следующий шаг** | **Screenshot Audit Pack → независимый полный аудит (Astra / ChatGPT Work) → утверждённые исправления по аудиту (если есть) → E6.** E5 больше не NEXT. См. §10.11. |
| E6 | ⬜ PENDING — final production / operations stage; **production НЕ развёрнут**, чеклист — §12; инфраструктурное состояние — §13 |

**ПРИЛОЖЕНИЕ ЗАКРЫТО (E5) — НО PRODUCTION НЕ РАЗВЁРНУТ.** E5 закрыт на уровне
приложения; production-деплоя не было, E5-миграции (как минимум
`2026_10_01…` и `2026_10_02…`) не применены к canonical MySQL,
production-секреты/webhook Tourvisor не настроены. Закрытие E5 **не**
означает production-readiness — это задача E6 (§12).

Единственная PHPUnit deprecation (1 штука) — предупреждение PHPUnit test runner:
«Your XML configuration validates against a deprecated schema» (`phpunit.xml`;
вывод подтверждён запуском `--display-phpunit-deprecations`). Это не
функциональный/кодовый сбой.

Baseline 1393 / 9759 — финальный верифицированный E5 baseline, получен
повторным полным прогоном в этом docs-slice на checkpoint `c56e3306`
(`C:\wamp\bin\php\php8.3.32\php.exe -d xdebug.mode=off -d memory_limit=2048M
vendor/bin/phpunit`, SQLite `:memory:`): **1393 tests / 9759 assertions, 0
failures, 0 errors**. Дефолтный 128M CLI `memory_limit` для `php artisan test`
на полном наборе недостаточен — это ограничение локального runner'а, не дефект
приложения. PHPUnit против canonical MySQL остаётся запрещён; canonical MySQL
`turfirma_rebuild_v4` тестами/этим slice не затрагивалась.

Application checkpoint = `c56e330685ffa8953b22c803d6dce5334c69e81b`. Этот файл,
`docs/roadmap.md` и корневой `README.md` фиксируются отдельным docs-only commit
**поверх** этого application HEAD. Documentation closure HEAD после того commit
будет новее application commit и определяется Git — он **не** известен заранее
и не зашивается в этот файл.

Project Sources: набор от 2026-09-23 (`a1d72a40`) **ИСТОРИЧЕСКИЙ** — он
предшествует всей реализации E5 и **не** авторитетен над текущим репозиторием.
Авторитетны текущее дерево, `git log`, тесты, миграции и docs на pushed HEAD.
Свежий набор генерируется из нового чистого pushed docs HEAD — см. §8.1.

## 2. Источники истины

Приоритет:

1. current Git HEAD, source code и tests;
2. `README.md`, этот файл и `docs/roadmap.md`;
3. independently verified test/browser/DB evidence;
4. внешний Project Sources set, созданный из clean pushed documentation HEAD;
5. исторические материалы под `docs/archive/` и старые Project Sources.

Исторический внешний source set (основан на `a1d72a40`, до E5; см. §8.1) после нового refresh сохраняется в archive, а не удаляется.

## 3. Workflow / guards

- Ответы пользователю — на русском.
- Claude Code нужен только для реального анализа/правки; prompts для Claude — на английском.
- Пользователь сам выполняет terminal/Git/test/browser QA.
- Один маленький semantic slice за раз.
- Перед кодом — exact allowed paths.
- Перед commit/push — exact changed/staged/committed paths.
- После успешного focused/full verification и final diff review отдельного разрешения на commit/push не требуется, если scope уже утверждён и нет опасного нового действия.
- Любой новый крупный independent slice / review — новый Claude chat; tiny correction текущего slice может продолжать текущий чат.
- Экономить Claude credits: не использовать дорогие модели без необходимости.

### PHPUnit guard

Использовать только:

```text
C:\wamp\bin\php\php8.3.32\php.exe
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Глобальный `php` не считать проектным PHP. PHPUnit против canonical MySQL запрещён.

## 4. Stage 0–12

Stage 0–12 полностью завершены. Не возвращаться к recovery/Stage 5–12 без нового конкретного дефекта.

Ключевые ранее закрытые области:

- recovery canonical DB;
- booking lifecycle;
- protected chat/documents;
- role precedence `admin > assigned manager > owner-facing tourist`;
- local/public tour catalog filtering;
- public content/CMS/RSS;
- notifications;
- security/reliability/performance hardening;
- dependency modernization, Laravel 12 / Vite 7, repository hygiene.

## 5. Stage 13 — ✅ COMPLETE

Repository/local technical closure at functional HEAD `dba20e2c6e2e66b6f69f33710b2626b3fe181e31`. Это не заявление о production deployment или о завершении последующего финального аудита/redesign (см. §9, §10).

### 5.1 Ранние production-readiness slices

До review work завершены и pushed:

- generated Vite output untracking;
- public browser runtime fixes;
- mobile/tablet responsive corrections;
- cleanup development-facing public notices;
- cookie consent + analytics gating;
- public company details alignment;
- separate personal-data consent на «Главной» и «Контактах».

### 5.2 Reviews — завершённая текущая цепочка

Ниже перечислены функционально завершённые review slices после старого source checkpoint `aca92d5c`.

| Slice | Commit / prefix | Состояние |
|---|---|---|
| Public review submission/UGC hardening | `709e699…`, `385b6b23…` | ✅ pushed |
| Cookie UX follow-up | `5369b398…` | ✅ pushed |
| C1 review consent evidence foundation | `ccb4b841…` | ✅ pushed; local migration applied |
| C2 review legal pages | `63a9bc6e…` | ✅ pushed |
| C3 form + evidence integration | `8f2f51c…` | ✅ pushed |
| C3 validation UX | `0d8d051c…` | ✅ pushed |
| C4A moderation state foundation | `2a48951c639eac77c3dedf3e43009c7ccca54ef6` | ✅ pushed; local migration applied |
| C4B1 server-side moderation enforcement | `ebbc60e46b27f2aa9e0faab0efbe5c15e465cbc7` | ✅ pushed |
| C4B2 Admin/Manager moderation UI | `149bce99d9928022d10747e8d686c1880388d1f8` | ✅ pushed + browser QA |
| C4C public moderator-edit disclosure | `15bd01a29cdb17c8bda3e3812027343971d6bd80` | ✅ pushed + browser QA |

#### Финальный review contract, уже реализованный

- Reviews публичны только после moderation.
- Auto-publish отсутствует.
- Public identity — `Reviews.name`; anonymous review не используется.
- Public scope — только `name + content`.
- `consent_full_name` и `consent_email` — evidence/private only.
- Три required confirmations:
  1. User Agreement;
  2. review-specific personal data processing consent;
  3. review publication/distribution consent.
- Review subject/title удалён из будущей public form и public output; legacy DB field пока сохранён.
- ReviewConsent хранится отдельно, one-per-review.
- Не собираются IP/user-agent/device/session evidence для review consent.
- Условия/запреты автора могут быть сохранены как `publication_conditions`.
- Если conditions непустые, публикация требует fresh explicit moderator confirmation.
- Content edit сбрасывает stale confirmation; same request edit + fresh confirmation может публиковать.
- Moderator не может менять public author name.
- Реальный content edit ставит sticky `is_moderator_edited=true` и обновляет `moderator_edited_at`.
- `moderator_edited_at` публично не показывается.
- Public disclosure только при marker=true и ровно с текстом:
  `Текст отзыва отредактирован модератором без изменения общего смысла.`
- Admin/Manager UI parity подтверждён.
- Conditions escaped; private consent identity в moderation UI не показывается.
- Public review UGC escaping сохранён.

#### C4C evidence

- focused: 13 / 81;
- review/moderation regression: 113 / 709;
- full: **839 / 3718**;
- browser QA `/reviews`: edited marker visible only on edited C4B2 Admin QA; unedited Manager QA without marker;
- browser QA homepage: same isolation PASS;
- private evidence not visible;
- local/tracking/live origin after push: `15bd01a29cdb17c8bda3e3812027343971d6bd80`;
- working tree clean after push.

### 5.3 Публикационное согласие — отзыв (withdrawal)

Commits: `e2a7ce0637f146b77c1ce1fcbc13008c18a50fb2` (`feat: enforce withdrawn review publication`), `ac11dc3237272999dbb1a31d1371b384d5971e97` (`feat: add review consent withdrawal workflow`).

- `withdrawn_at` enforced на публичном пути: withdrawn review не публикуется, даже если stale `is_published=true`;
- Admin/Manager не могут публиковать/republish withdrawn review;
- explicit unpublish остаётся возможным;
- отдельный operator workflow фиксирует уже полученный/проверенный withdrawal request (не self-service);
- первый timestamp сохраняется при повторном действии;
- ReviewConsent не фабрикуется;
- `publication_conditions_satisfied_at` сохраняется;
- private consent identity показывается только на dedicated withdrawal confirmation экране;
- публичный self-service withdrawal не вводился.

### 5.4 Registration consent — S13-R3

Commits: `1cef8d2642b3785e3ab759d5eedbc1ddd65b9cf9` (`feat: add registration consent evidence`), `a3824554033f92c0ef8723c6ab1cdc2a5c6eaa0f` (`docs: extend user agreement for registration`).

Финальные два раздельных обязательных подтверждения при регистрации:

1. «Я принимаю условия Пользовательского соглашения.»
2. «Я даю согласие на обработку моих персональных данных в целях регистрации учётной записи и использования личного кабинета в соответствии с Согласием на обработку персональных данных.»

- отдельная страница registration personal-data consent;
- `UserRegistrationConsent` — one-to-one evidence;
- server-side timestamps и SHA256 document versions;
- один общий acceptance instant;
- client не может подделать evidence timestamps/versions;
- user + роль Tourist + consent evidence создаются атомарно (одна транзакция);
- `Registered` event / Auth login только после успешного commit транзакции;
- не собираются marketing/publication/IP/UA/device/session/geolocation evidence.

**R3A — User Agreement.** Пользовательское соглашение расширено §9 для регистрации/использования личного кабинета. Канонические артефакты (не изменялись в этой правке):

- `public/documents/Пользовательское соглашение.docx`, SHA256 `24322d36383b8ed61599b0f1f2f087a88d11ac1c6286da31bc6f2221d7084ba4`;
- `public/documents/User_Agreement.pdf`, SHA256 `a686c44c3a38529422734ba4ea54dcc10ecda3d57664db54a7d120bd21de87d9`.

### 5.5 Password visibility UX

Commit: `7818c54ee3315e34f26fc8c1e9796b9b6417e79c` (`feat: add password visibility toggles`).

- login password visibility toggle;
- независимые toggle для registration password и password-confirmation;
- accessible show/hide controls;
- default hidden state;
- browser QA пройдено успешно.

Это UX-улучшение внутри Stage 13, отдельным нумерованным этапом не считается.

### 5.6 S13-R4 — Authenticated-only booking cleanup (Stage 13)

Commit: `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` (`fix: remove obsolete guest booking flow`).

- анонимное бронирование не поддерживается;
- удалён мёртвый anonymous `StoreController`;
- удалены недостижимые booking `@guest` form/layout остатки;
- удалено сломанное дублирующее `/tours` booking modal;
- `/tours` CTA использует канонический `bookings.create` с `tour_id`;
- переиспользуется существующее поведение prefill `tour_id`;
- граница unauthenticated create/store route покрыта тестами;
- Tourist ownership и Admin/Manager new-client flow сохранены;
- изменений booking schema/migrations не было.

### 5.7 S13-R2 — Manager review cache parity relevance check — ✅ CLOSED

Историческая обеспокоенность: у Admin и Manager когда-то было асимметричное
legacy-очищение кэша отзывов. Пункт намеренно нёс статус READ-ONLY FIRST и был
перенесён через E1/E2 в E3 (cabinet pass) как relevance check, а не как
подтверждённый дефект.

**Закрыт в рамках E3-A5** после повторной проверки текущей реализации:

- `AdminController::updateReview()` действительно содержит
  `Cache::forget('home_reviews')` и цикл `Cache::forget('reviews_page_'.$page)`
  (по 10 страниц) при сохранении отзыва — это и есть исторический источник
  асимметрии, `ManagerController::updateReview()` эквивалентного вызова не
  делает;
- однако ни один контроллер приложения нигде не выполняет
  `Cache::remember('home_reviews', ...)` или
  `Cache::remember('reviews_page_...', ...)` — эти ключи нигде не
  заполняются, а публичные Reviews/Home читают отзывы напрямую из БД без
  кэширования;
- следовательно вызовы `Cache::forget(...)` в Admin — это очистка
  никогда не существующих записей кэша (no-op), а не работающий live
  cache layer;
- публичные изменения отзывов (Admin или Manager) видны немедленно вне
  зависимости от того, какая роль сохранила отзыв;
- **вывод: живого дефекта нет, parity-реализация не требуется.** Это
  завершённый relevance check / устаревшая историческая обеспокоенность, а
  не открытый дефект.

Не вводить новый review-cache код исключительно ради симметрии. Не путать
это закрытие с решением "добавить кэш отзывов" — кэш отзывов сознательно не
существует на публичном пути на этом checkpoint; если он появится позже, эту
проверку нужно будет провести заново.

## 5A. E1 Comprehensive Audit — ✅ TECHNICALLY CLOSED

Authoritative E1 closure application commit:
`08d0626311234faa06dedf2828cb878805241990` (`fix: close final public audit gaps`).

Authoritative PHPUnit baseline на закрытии E1:

```text
PHP 8.3.32
PHPUnit 11.5.56
SQLite :memory:
full: 1001 tests / 7013 assertions
```

Единственная PHPUnit deprecation — pre-existing XML schema deprecation; это не
функциональный/кодовый сбой.

### 5A.1 Закрытые области E1

**E1-A1**
- канонический host для social image;
- дубли ID в публичной навигации;
- gating Google Maps за cookie consent.

**E1-A2**
- `tel:` href сотрудника;
- актуальная копия по оплате/возврату;
- публичные реквизиты компании в транзакционных письмах;
- disposition устаревшего публичного profile/dashboard вопроса.

**E1-A3**
- sitemap;
- robots.txt;
- regression-покрытие.

**E1-A4**
- page-specific динамический OG/Twitter title/description для detail-страниц.

**E1-A5 / RSS**
- санитизация HTML внешнего RSS News при ингесте;
- render-time санитизация для исторических News-строк;
- безопасная обработка URL-схем;
- RSS-related security regressions.

**E1-RPD (consolidated package)**
- News listing decode-then-raw XSS исправлен;
- публичные inner-cache TTL исправлены на задуманный один час;
- Destination nullable image robustness;
- Specials nullable images;
- Destination null-slug rendering.

**E1-FINAL**
- About page country links используют slug;
- Article rich HTML санитизируется на Admin/Manager write;
- Article detail пересанитизируется для исторических строк;
- Article listing excerpt — plain/escaped;
- article/special listing robustness;
- About cache TTL исправлен;
- убраны hardcoded SEO-заявления «55/12»;
- reload-captcha убран из response cache;
- Awards public regression coverage;
- заявление аудита про Cyrillic `Str::slug` ОПРОВЕРГНУТО runtime-проверкой:
  `«Путешествие по Азии» -> putesestvie-po-azii`; лишний slug fallback не добавлялся.

### 5A.2 Намеренно отложенные / pending пункты E1

Эти пункты **не «баг»** и не блокировали техническое закрытие E1. Последующие
агенты не должны «чинить» их случайно.

- **`PENDING_BUSINESS_DECISION_OPENING_HOURS`** — конфликт копий часов работы:
  home «будни 10:00–20:00» vs contacts «будни 11:00–20:00, по предварительной
  записи». Авторитетного решения нет. **Не выбирать значение.** Должно быть
  решено до финального production-релиза (E6). Не технический блокер. Остаётся
  нерешённым.
- **OG type** — ✅ закрыто в E2-A5 (§9B): News detail и Article detail объявляют
  `og:type=article`; `layouts/main` использует `@yield('og_type', 'website')`.
- **Tour search** — существующий публичный tour-search widget остаётся
  ВРЕМЕННЫМ. В E2 он только визуально интегрируется в новые surfaces; механика/
  архитектура не финальны. Не переделывать/заменять до E5 (выделенная фаза
  финального решения по поиску туров).
  **[Историческая запись E1/E2; ✅ РЕШЕНО в E5 — временный поиск заменён модулем Tourvisor, см. §10.]**
- **News RSS scheduling** — не добавлять Laravel scheduling вслепую; внешний
  production cron может уже существовать. E2-A5 добавил только HTML autodiscovery
  на listing новостей; production scheduling НЕ верифицирован. Проверить реальный
  production-механизм в рамках E6 (operations/deployment).
- **Future-risk raw HTML** — `Best_offer` / `OurClient` / `Countries_image` /
  `Destination_image` raw-контент сейчас не имеет untrusted web write path. Не
  переоткрывать как текущие XSS-дефекты, пока не появится CMS/write path.

## 6. Local DB notes

Review schema/evidence work introduced migrations after the old Stage 12 migration count.

Known guarded local-dev state:

- C1 review consent schema was applied locally under its guarded plan;
- C4A moderation-state migration was applied locally under its guarded plan;
- pre-C4A backup:
  `C:\Avilona_private\DB_backups\Avilona_turfirma\20260818-002802_turfirma_rebuild_v4_pre_C4A_moderation_state.sql`;
- backup SHA256:
  `5bc2b008be72c02c775f8e380775be35b2786ecd928471057dacbcfdfbaa9ad8`.

Не переносить local migration assumptions на production. Перед production migration нужен отдельный guarded deploy/migration plan с independent inventory.

### 6.1 Полный Stage 13 migration inventory

Оригинальная таблица `reviews` / основа `is_published` предшествует Stage 13. Stage 13 добавил ровно четыре миграции:

- `2026_08_16_000000_create_review_consents_table.php`;
- `2026_08_17_000000_add_moderation_state_to_reviews_table.php`;
- `2026_08_17_000001_add_publication_conditions_satisfied_at_to_review_consents_table.php`;
- `2026_08_20_000000_create_user_registration_consents_table.php`.

Closure audit на canonical local MySQL (`127.0.0.1:3308`, `turfirma_rebuild_v4`): все четыре Stage 13 миграции = **Ran**, pending Stage 13 миграций = **0**. Это local/canonical-dev статус, не production DB migration статус.

## 7. Известные browser-QA fixtures / local QA data

В local dev DB намеренно могут оставаться review QA rows, включая:

- C3 Browser QA;
- C4B2 Admin QA;
- C4B2 Manager QA.

C4B2 Admin QA был реально изменён при C4C browser QA и поэтому имеет moderator-edited marker. Эти локальные QA rows не являются production data. Cleanup — отдельная local-only операция, если она понадобится.

Closure audit зафиксировал, что канонические local MySQL evidence-таблицы структурно корректны, но на момент closure пусты: `reviews = 0`, `review_consents = 0`, `user_registration_consents = 0`, `tours = 0`. Это не является дефектом приложения — automated Stage 13 coverage полный, а local QA evidence rows на момент closure не сохранены.

## 8. E1 closure / E2 closure / E3 (A1…A6) closure и Project Sources статус

Stage 13 закрыт на уровне repository/local technical closure на функциональном HEAD `dba20e2c6e2e66b6f69f33710b2626b3fe181e31`. E1 Comprehensive Audit технически закрыт на историческом application commit `08d0626311234faa06dedf2828cb878805241990` (§5A). E2 закрыт на application HEAD `35f91b9e270cf68654877d42fc8b0d0d59d12458` (§9B), documentation-checkpoint `886bde98` (`docs: close E2 public redesign`). После E2 closure repository прошёл E3-A1…E3-A6 и находится на application HEAD `ed550df8989b44e7305bdce6b6f5f063b82a2616` (§9C):

- все Stage 13 миграции применены, pending = 0 (§6.1);
- Stage 13 code/schema/legal/test reconciliation — PASS;
- E1 closure baseline: **1001 tests / 7013 assertions**; после E2 closure: **1051 tests / 7180 assertions**; baseline на закрытии E3-A5: **1233 tests / 8023 assertions**; текущий baseline после E3-A6: **1242 tests / 8056 assertions** (§9C.5);
- функциональных блокеров Stage 13 / E1 не осталось (см. §5A.2 про намеренно отложенные пункты);
- E2 **завершён на уровне приложения** (E2-A1…E2-A7, §9B). Временный блок поиска/виджета `/tours` (`resources/views/tours/index.blade.php`) намеренно исключён из E2-A7 и оставался временным до E5 *(историческая запись E2; заменён модулем Tourvisor в E5, §10)*;
- E3 **Cabinet UX/UI/Design Modernization завершён на уровне приложения через E3-A5** (Foundation → Tourist → Shared Booking → Manager → Admin, §9C), точечная полировка **E3-A6 (A + B) закрыта**, E3-A6-C не требуется. S13-R2 (Manager review cache parity relevance check) закрыт как часть E3-A5 (§5.7);
- (историческое состояние на момент E3) тогда следующим шагом был E4; с тех пор E4 и E5 закрыты на уровне приложения — актуальный статус и следующий шаг см. §1 и §10.11.

### 8.1 Project Sources — refresh после E5 docs closure

- Набор Project Sources от 2026-09-23 (`a1d72a40`, `docs: close E4 and prepare E5`) — **ИСТОРИЧЕСКИЙ**: он предшествует всей реализации E5 (E5-A1…E5-A4) и не авторитетен над текущим репозиторием;
- свежий набор строится только из **нового чистого pushed documentation HEAD** (поверх application checkpoint `c56e3306`) после docs-only commit/push и выравнивания local = tracking = live origin; имя архива, timestamp, SHA256 и размер определяются самой генерацией и не выдумываются заранее;
- предыдущий активный набор сохраняется в archive (не удаляется);
- механизм — существующий guarded PowerShell refresh (per-checkpoint wrapper + shared `Create-Avilona-ChatGPT-SourceArchive.ps1`, вне репозитория, `C:\Avilona_private\`): для нового checkpoint копируется предыдущий `_v1` wrapper-пакет (последний — `Avilona_E4_Closure_Project_Sources_Refresh_v1`) в новую `_v1`-директорию и переписывается (config-блок, canonical blob-хэши, шаблоны, self-digest); исторические wrapper-пакеты не редактируются; shared helper не модифицируется;
- архив — только tracked-источники проекта: без `.claude/`, `.env`, `vendor/`, `node_modules/`, приватных артефактов E5 (`C:\Avilona_private\E5\…`), секретов.

## 9. Endgame roadmap — E1…E6

Канонический roadmap после Stage 13 (детали — `docs/roadmap.md`):

| Фаза | Название | Статус |
|---|---|---|
| E1 | Comprehensive Audit | ✅ TECHNICALLY CLOSED (§5A) |
| E2 | Public UX / UI / Design Redesign | ✅ **COMPLETE / CLOSED на уровне приложения** — E2-A1…E2-A7 (§9B) |
| **E3** | **Cabinet UX/UI/Design Modernization** (Tourist / Manager / Admin) | ✅ **E3-A1…E3-A5 CLOSED на уровне приложения** (§9C); **E3-A6 point-polish (A + B) ✅ CLOSED** |
| **E4** | Post-redesign stabilization / regression / browser-device / resilience QA | ✅ **CLOSED** (E4-A…E4-E1; §9.4) |
| **E5** | Final Tour Search / Aggregation Product Block | ✅ **CLOSED на уровне приложения** — E5-A1…E5-A4 (§10); production НЕ развёрнут |
| — | Screenshot Audit Pack + независимый полный аудит (Astra / ChatGPT Work) | ⬜ **NEXT** — перед E6 (§10.11) |
| E6 | Final Release / Deploy / Production Smoke | ⬜ PENDING, после аудита (§12) |

Текущий статус: **E1–E5 закрыты на уровне приложения** (E5 — E5-A1…E5-A4, §10).
Следующий шаг — **Screenshot Audit Pack → независимый полный аудит → E6** (§10.11,
§12). Production не развёрнут.

### 9.0 E2 — стартовые принципы

E2 — это не просто косметическая перекраска. Публичный сайт рассматривается как
цельный современный туристический веб-сайт: information architecture,
header/navigation, иерархия главной страницы, типографика, spacing, цветовая
система, buttons/forms, cards, responsive behavior, мобильная навигация,
визуальная консистентность, destinations/countries, страницы компании,
сотрудники, awards, articles/news/special offers/reviews, contacts,
empty/error states, consent UI, accessibility, trust/credibility, conversion
paths, CTA consistency, image treatment, desktop/tablet/mobile.

E2 началась с READ-ONLY visual/UX inventory и design-system proposal, затем шла
согласованными slices. Механику финального поиска туров в E2 не переделывали:
текущий tour widget визуально размещён как временный компонент, но его
финальный provider/архитектура — это E5 (§10).

### 9B. E2 — выполненные slices (E2-A1…E2-A7) — ✅ COMPLETE

> **Историческая запись E2.** Упоминания «временный `/tours` / tour-search до E5» ниже описывают состояние на момент E2. E5 закрыт на уровне приложения — актуальное состояние см. §10.

E2 **завершён на уровне приложения**. Ниже — все завершённые slices. Публичный
shell и завершённые публичные E2-страницы/surfaces (header/footer/shell, home,
travel discovery, company/trust, news/articles, reviews, contacts,
informational/legal/404) переведены на единую E2-презентацию и финальную
визуальную систему (E2-A7). **Исключение:** временный блок поиска/виджета
`/tours` (`resources/views/tours/index.blade.php`) намеренно НЕ включён в E2-A7
и остаётся временным до E5 (§10). Финальный сайт будет позже показан руководству
компании; возможные замечания по дизайну — это последующий polish/follow-up, а
не открытый блокер закрытия E2.

Authoritative application HEAD на закрытии E2: `35f91b9e270cf68654877d42fc8b0d0d59d12458`
(`feat: finalize public visual system palette (E2-A7)`); прямой parent —
`baf7487b5fe03c978cbc101ad2b7e6c72481c610`.
Full baseline на закрытии E2: **1051 tests / 7180 assertions** (после E2-A5 было
1006 / 7037; E2-A6…A7 добавили публичные E2 regression-тесты).

#### E2-A1 — header / первый экран главной
✅ COMPLETE — `43a073e676d441021445f73f38733fa70a0e1463` (`feat: redesign public header and home first screen`)

- единый публичный header/навигация; route-derived active states; accessible `aria-current`;
- редизайн hero главной; один H1; улучшенная CTA-иерархия;
- временный tour-search widget визуально интегрирован — архитектура tour-search намеренно НЕ переделана (E5). Текущее решение поиска туров не финальное.

#### E2-A2 — home below-the-fold / shared public shell
✅ COMPLETE — `72202ab7d35b064ab4b0c66147bfff21357e5343` (`feat: redesign home and shared public shell`), `eaa2093f5f406e7a5fbd73c6fe3a1897802852a5` (`refactor: unify public manager interactions`)

- shared public shell; редизайн home below-the-fold; footer cleanup;
- взаимодействие с телефоном в header/footer больше не вызывает page jump;
- убран сломанный Yandex-информер; очищен map placeholder;
- контрол scroll-to-top доступен с клавиатуры;
- в verified QA нет page-level горизонтального overflow;
- общий слой manager-contact взаимодействия.
- Не заявление о production deployment.

#### E2-A3 — public travel discovery
✅ COMPLETE — `5e22e4b78ed6e8610d4c2b7f11043ff9e1336806` (`feat: redesign public travel discovery`)

- Countries; Destinations; Specials / публичные travel discovery surfaces;
- slice включал populated / browser QA.
- Историческая SQL для изолированного визуального/reference QA: `C:\Users\nikita\Downloads\u0588341_turfirma.sql`, SHA256 `A721C984DE0F2B366598A7B5D92E6B5F6C7D629692C2C34E2EED52BD85B3109A`. Исторические fixture-счётчики (countries_images 55, destination_images 12, our_clients 1, best_offers 4, reviews 46, employees 10, partners 20, awards 22, articles 50, news 138, users 7) — **только исторические QA/reference**, НЕ текущая бизнес-истина. Legacy users/персональные данные никогда не импортируются в canonical/production data.

#### E2-A4 — company / trust surfaces
✅ COMPLETE — `94aedad09468d50be45e8f11c4be0a8c41dbb474` (`feat: redesign company trust pages`)

Область: About Company, Employees, Awards.

- **About Company:** убрана legacy sidebar/grid презентация; один H1; E2 breadcrumbs/hero/секции; исправлен wide-desktop layout после browser QA; три существующих PDF сохранены (НЕ объявлены юридически/актуально up to date; их отображаемая/repository дата остаётся **22 May 2024**); канонические формулировки оплаты/возврата сохранены; публичный email `avilonatur@bk.ru` сохранён; публичная формулировка изменена с общей «курьерской доставки» на `«Передача документов по договорённости»`; общая формулировка рассрочки/кредита сохранена; country slug контракты сохранены.
- **Employees:** responsive E2 employee cards; контактные ссылки остаются прямыми личными контактами; tel/mailto/WhatsApp/VK поведение сохранено; поддержаны image placeholders; личные контакты сотрудников НЕ заменены на generic manager modal.
- **Awards:** responsive award grid; native button как триггер модалки; keyboard-accessible Bootstrap modal; portrait/landscape media handling; null-image-safe; без выдуманных дат, issuers, рейтингов, provenance.
- Populated QA: 10 employees, 22 awards. Финальный E2-A4 browser QA пройден.

#### E2-A5 — News + Articles editorial experience
✅ COMPLETE — `ad6e9c23986d479cbbbf6f511e96bc139ae26576` (`feat: redesign public News + Articles editorial experience (E2-A5-I1)`). Documentation checkpoint после E2-A5: `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa` (`docs: checkpoint E2 through E2-A5`).

Область: News listing/detail, Articles listing/detail, общий partial `includes/e2-editorial-card`, editorial CSS, детерминированный порядок пагинации News, безопасная граница публичного рендера ссылок-источников News, regression-тесты.

- **News listing:** удалена legacy sidebar; E2 breadcrumb + page hero; один H1; responsive 1/2/3 card grid; title-ссылки вместо повторяющихся «Подробнее»; `pub_date` на карточках News; date filter сохранён; убрана старая AJAX-пагинация → обычная server-side пагинация; добавлен RSS HTML autodiscovery `<link>`; сохранены `#news-container`, `.card-text`, контракт escaped first-paragraph excerpt.
- **News detail:** широкая центрированная editorial колонка (~84ch при ≥992px, ~88ch при ≥1200px — намеренно НЕ узкая generic 68ch); left-aligned H1; реальный `pub_date` рядом с заголовком (только если не null); сохранён `.news-content`; сохранена граница рендера `NewsHtmlSanitizer`; безопасное действие «Источник новости» (рендерятся только валидные явные http/https `News.link`; `target=_blank` + `rel="noopener noreferrer"`); `og:type=article`; back-to-news; CTA.
  - Browser-QA follow-up (включён в HEAD `ad6e9c23`): первая реализация рендерила `News.image` как отдельное верхнее изображение, тогда как то же изображение уже встроено в тело RSS → дубль. Пользователь явно запросил удаление ПЕРВОГО standalone-изображения. Отдельный блок медиа в News detail удалён; изображение в теле остаётся. Listings News и Articles не изменены.
- **Article listing:** E2 editorial cards; без выдуманных дат; `.card-text` сохранён; сохранена подстрока empty-state `«Статьи пока не добавлены»`; server-пагинация.
- **Article detail:** широкая центрированная editorial колонка; `Article.image` остаётся одним standalone hero (отдельное CMS-медиа, не дублировало протестированное тело статьи); сохранён `.article-content`; сохранена граница `NewsHtmlSanitizer`; дата публикации статьи не выдумывается; `og:type=article`; back-to-articles; CTA.
- **Shared layout (точечно):** `resources/views/layouts/main.blade.php` — hard-coded `og:type` website → `@yield('og_type', 'website')`; добавлен `@yield('head_extra')`. Это НЕ общий редизайн shared-layout.
- **HelpfulNewsController:** первичный порядок `pub_date DESC` + детерминированный вторичный `id DESC`. Без изменения cache-policy, без изменения page-size, без введения recent/related News запроса.

**Тестовый baseline.** До E2-A5: 1001 tests / 7013 assertions. После E2-A5: **1006 tests / 7037 assertions** (PHP 8.3.32, PHPUnit 11.5.56, Laravel 12.65.0, SQLite `:memory:`). E2-A5 намеренно добавил 5 regression-тестов: 2× публичная render-safety `News.link`, 2× editorial `og:type=article`, 1× News RSS autodiscovery. 1006 / 7037 — ожидаемо, не регрессия. Единственная PHPUnit deprecation — pre-existing XML schema deprecation.

**E1 security-контракты остаются закрытыми.** `NewsHtmlSanitizer` остаётся security-границей для публичного stored/external rich HTML. Известное наблюдение: структура HTML-таблиц сейчас не сохраняется allow-list санитайзера — это НЕ дефект E2-A5, и allow-list не расширяется в рамках этой работы.

**News RSS scheduling** остаётся operations-пунктом E6 — E2-A5 добавил только HTML autodiscovery, production cron не верифицирован.

#### E2-A6-I1 — Reviews + Contacts
✅ COMPLETE — `1de95ad88092e2fad482949a9cd19fb80682d674` (`feat: redesign public reviews and contacts experience (E2-A6-I1)`)

Область: `resources/views/reviews.blade.php`, `resources/views/contacts.blade.php`, contact/home form requests и mail, `public/css/unified.css`, `routes/web.php`, regression-тесты.

- **Reviews:**
  - современные responsive review cards; нейтральный avatar-fallback там, где нет реального изображения;
  - 2-колоночная компактная desktop-сетка отзывов; одна колонка на мобильном;
  - teaser/expand для длинных отзывов;
  - disclosure «отредактировано модератором» сохранён;
  - Stage 13 контракты moderation / consent / privacy сохранены; публичный вывод escaped;
  - современная форма отзыва; empty state;
  - пагинация в этом slice, позже обновлена до 6 на страницу в E2-A6-I2.
- **Contacts:**
  - современный E2 layout страницы; улучшенный UX формы обратной связи;
  - необязательное поле «Тема» детерминированно ограничено `nullable|string|max:150` (оба request: `SendContactRequest`, `SendHomeRequest`);
  - текущий публичный физический адрес; исторический/юридический адрес там, где он явно помечен как регистрационный, сохранён и не смешивается с физическим;
  - блок реквизитов перекомпонован в сбалансированные desktop-колонки; существующие PDF сохранены;
  - блок «Как нас найти»;
  - POST-throttling на утверждённых маршрутах отправки: `throttle:8,1` (8 запросов в минуту) — `contact.send` и `home.send`;
  - внутренний получатель формы остаётся `straus97@mail.ru` (`Mail::to(...)` в `SendContactController` / `SendHomeController`);
  - публичный e-mail остаётся `avilonatur@bk.ru`.
  - Роли этих двух адресов НЕ смешивать: `straus97@mail.ru` — внутренний получатель заявок, `avilonatur@bk.ru` — публичный контактный e-mail.

#### E2-A6-I2 — Informational / Legal / 404
✅ COMPLETE — `baf7487b5fe03c978cbc101ad2b7e6c72481c610` (`feat: complete public informational pages redesign (E2-A6-I2)`) — прямой parent E2-A7

Область: Travel Dictionary, пять legal-страниц, публичная 404, Reviews pagination, `public/css/unified.css`, regression-тесты.

- **Travel Dictionary (`helpful_information/travel_dictionary`):** удалена legacy sidebar; ровно один отрендеренный H1; E2 breadcrumbs/hero; native `details/summary` disclosure; контент сохранён; multi-column Terms-презентация на desktop; корректное responsive-поведение на мобильном.
- **Пять legal-страниц (`legal/cookies`, `legal/personal-data-consent`, `legal/registration-personal-data-consent`, `legal/review-personal-data-consent`, `legal/review-publication-consent`):** E2-презентация; юридический текст сохранён без модернизации/переписывания; улучшены breadcrumbs / H1 / читабельность.
- **404:** существующая публичная 404 переведена в общую E2-презентацию; фактическое поведение HTTP 404 сохранено; страницы 403 / 419 / 429 / 500 / 503 НЕ добавлялись — эти системные error-surfaces остаются пунктом E4 (resilience/stabilization), а не пропуском E2.
- **Reviews:** пагинация изменена с 4 на **6** на страницу (`Review/IndexController::paginate(6)`); порядок публикации / фильтр / семантика withdrawal сохранены.
- **Общая desktop-ширина:** generic E2 informational prose / hero / title больше не использует излишне узкий desktop character-width cap; на desktop используется доступная ширина parent/container; поведение на мобильном не изменилось; пользователь явно одобрил это направление.

#### E2-A7 — финальная публичная визуальная система
✅ COMPLETE — `35f91b9e270cf68654877d42fc8b0d0d59d12458` (`feat: finalize public visual system palette (E2-A7)`) — authoritative application HEAD на закрытии E2 (заменён E3)

Область: `public/css/unified.css` (E2 token-система), `public/css/style_min.css`, `resources/views/home.blade.php`, `resources/views/layouts/main.blade.php`, `resources/views/reviews.blade.php`, `resources/views/helpful_information/for_our_clients.blade.php`, один существующий тест обновлён (`tests/Feature/PublicReviewSubmissionHygieneTest.php`).

Пользователь **отклонил** прежнюю доминирующую палитру:

- cream/peach/тёплые surfaces;
- тёплые tan-границы;
- brown/orange primary CTA-система.

Финальное принятое направление текущего этапа:

- белая база главной страницы;
- прохладные светлые blue-gray alternate surfaces;
- прохладные нейтральные границы;
- sea-blue / blue primary actions;
- более тёмный синий для hover/strong states;
- orange сохранён только как сдержанный декоративный акцент;
- консистентная E2-обработка button / form / alert / header / footer;
- текущая публичная визуальная система принята для этого этапа.

Важные детали реализации:

- убран legacy `body { background: snow }` из `style_min.css`, который просвечивал сквозь E2-shell;
- общая E2 token-система — авторитетна для публичного shell;
- header auth-actions нормализованы в E2-презентацию;
- цвета активного поиска/виджета на главной приведены к E2-токенам без изменения механики поиска;
- презентация валидации Reviews нормализована;
- пагинация публичных special-offers выровнена;
- legacy-виджет `/tours` намеренно НЕ перекрашивался/не переделывался — финальная архитектура поиска туров остаётся E5.

Во время browser QA случайный CSS-терминатор комментария временно инвалидировал
E2 `:root` token-блок; это было исправлено до commit, полный прогон тестов
прошёл, финальный закоммиченный checkpoint здоровый. Финальный browser QA и
восстановление пройдены.

**Тестовый baseline после E2-A6…A7.** До E2-A6: 1006 tests / 7037 assertions.
На закрытии E2: **1051 tests / 7180 assertions** (PHP 8.3.32, PHPUnit 11.5.56,
Laravel 12.65.0, SQLite `:memory:`, exit 0). Новые тесты покрывают публичную
E2-презентацию Reviews/Contacts/Travel Dictionary/legal/404 и пагинацию
Reviews = 6. Рост ожидаемый, не регрессия. Единственная PHPUnit deprecation —
pre-existing XML schema deprecation.

**Browser QA:** PASS для финальной публичной визуальной системы E2 на текущем
этапе. Пользователь явно принял палитру / визуальную систему E2-A7 для этого
этапа. Готовый сайт будет позже показан руководству компании; итоговые
замечания по дизайну — последующий polish/follow-up, а не открытый блокер
закрытия E2.

### 9.1 Technical / product audit (E1 — выполнено)

Покрыто в E1 (§5A). Оставлено здесь как чеклист областей:

- functionality and error paths;
- security/privacy/data minimization;
- content consistency;
- responsive behavior;
- performance/query issues;
- duplicated/dead/obsolete code;
- component/page composition;
- accessibility/usability basics.

### 9.2 Public UX/UI/design pass (E2) — ✅ ВЫПОЛНЕНО

Выполнено в E2-A1…E2-A7 (§9B). Оставлено здесь как чеклист охваченных областей:

- visual hierarchy;
- page grid/layout;
- spacing and density;
- typography;
- header/footer/navigation;
- hero/sections/cards/tables/forms/alerts/modals;
- iconography;
- color system and states;
- mobile/tablet/desktop composition;
- outdated-looking or rough block placement;
- consistency between pages/components.

Публичный сайт менялся не механически: сначала audit/findings/priorities, затем approved redesign slices. Результат — единая E2 token-система и финальная визуальная система (E2-A7).

### 9C. E3 — выполненные slices (E3-A1…E3-A6) — ✅ CLOSED на уровне приложения

E3 — Cabinet UX/UI/Design Modernization — глубокий пройден для tourist /
manager / admin кабинетов: information architecture, navigation/sidebars/
headers, dashboard priorities, action placement, tables/forms/cards, icons,
status presentation/color system, spacing/typography/hierarchy,
mobile/tablet/desktop behavior, визуальная консистентность с публичной
E2-системой там, где уместно, без стирания role-specific UX. Design-решения
следовали из findings по каждому кабинету, а не из blanket-restyling —
Manager (E3-A4) и Admin (E3-A5) начались с существующих E3-shell/компонентов
и правили конкретные найденные дефекты, а не переписывали страницы с нуля.

Текущий authoritative application HEAD: `ed550df8989b44e7305bdce6b6f5f063b82a2616`
(`fix: polish cross-role chat switching (E3-A6-B)`); прямой parent —
`8a5018bdf6a95658d195772c05adc3c4f557329d` (`perf: polish manager dashboard queries (E3-A6-A)`).
Финальный full baseline: **1242 tests / 8056 assertions** (§9C.5).

#### E3-A1 — Shared Cabinet Foundation
✅ COMPLETE — `66b5628daf76cc5a7d05d4ca2ab85e8f2be74c3d` (`feat: establish shared cabinet foundation (E3-A1)`)

Design-фундамент, на котором построены все последующие E3-slices:

- новая token/primitive CSS-система `public/css/cabinet-e3.css` (общая база для tourist/manager/admin, без страницы-специфичных цветов);
- существенно упрощённый `resources/views/cabinet/layouts/app.blade.php` (общая shell-разметка, landmark/skip-link, mobile drawer control hooks);
- общий компонент `cabinet/components/flash.blade.php` — единая flash-область для всех существующих controller flash-ключей, dismissible Bootstrap-alert структура;
- обновлённые `booking-card`, `empty-state`, `stat-card`, `status-badge` компоненты;
- обновлённые sidebar-партиалы для всех трёх ролей (admin/manager/tourist) с активным-пунктом `aria-current`;
- header user-dropdown триггер — нативная `<button>`;
- редирект пользователя с обязательной сменой пароля сохранён через общую shell.
- Тесты: `tests/Feature/CabinetSharedShellFoundationTest.php` (контракт-ориентированные проверки: landmark-рендер для каждой роли, header profile/settings ссылки, состав sidebar по роли, `aria-current`, общая flash-область по каждому controller-ключу, видимость ошибок валидации, password-change-required редирект, mobile drawer хуки, dismissible flash, нативная кнопка dropdown).

#### E3-A2 — Tourist Cabinet и cross-role chat continuity
✅ COMPLETE — `6fdbe8eea6fb3eb5a7309396753efb8f2ae1f9ed` (`feat: modernize tourist cabinet and cross-role chat (E3-A2)`)

- 9 tourist blade-страниц переведены на E3-shell/токены; новая `tc-*` CSS-секция в `cabinet-e3.css`;
- `CabinetController::touristSidebarData()` — переиспользует существующую формулу непрочитанных сообщений, чтобы badge чата совпадал на всех tourist-страницах; добавлен `hasAnyDocuments` view-флаг и `manager` eager-load на документах брони; удалена мёртвая `pendingBookingsCount`-передача;
- Bonus-страница очищена от выдуманной реферальной программы/условий начисления — остаются только реальный баланс/уровень/суммы/транзакции; wishlist заменён на честное «в разработке» уведомление (route сохранён);
- общий кросс-role continuity-слой чата: `public/js/cabinet-chat.js` (AJAX-переключение thread без перезагрузки для tourist/manager/admin, History API, per-user/context/booking localStorage-черновики, AJAX-отправка, защита от гонки polling, progressive fallback);
- Admin-как-assignee контракт установлен здесь и сохранён во всех следующих slices: Admin читает любой booking chat; писать может только Admin, лично назначенный `booking.manager_id`; наблюдающий (не назначенный) Admin остаётся read-only — без композера, без poll, без изменения read-state;
- logout корректно очищает только собственные chat-черновики текущего пользователя (никогда не `localStorage.clear()` целиком).
- Тесты: `tests/Feature/TouristCabinetE3RedesignTest.php`, `tests/Feature/CabinetChatContinuityTest.php`.
- Перенесено дальше: AJAX thread-switch UX полировка → E3-A6/E4.

#### E3-A3 — Shared Booking Surfaces
✅ COMPLETE — `2b567f04b52ebee0085a11e195e973b621a58031` (`feat: modernize shared booking surfaces (E3-A3)`)

- переведены на E3-систему три общие role-sensitive страницы `resources/views/bookings/{show,edit,create}.blade.php`; новая CSS-секция `.booking-*` (только токены, без новой палитры) в `cabinet-e3.css`;
- новый переиспользуемый партиал `cabinet/components/booking-facts.blade.php` (key/value `<dl>`, пропускает null-значения);
- удалена мёртвая unauthenticated/guest-ветка из всех трёх view (route-группа всегда authed);
- нормализация статус-формулировки: канонический label для статуса `progress` — «В обработке» (источник истины — `Booking::availableStatuses()`/`getStatusLabelAttribute()`); единственный расходившийся `status-badge`-компонент исправлен; хранимые значения и `Booking::transitionMap()` не менялись;
- на booking `show` добавлены role-aware ссылки в чат (owner → `cabinet.chat`, назначенный manager → `cabinet.manager.chat`, admin → `cabinet.admin.chats` всегда, включая observer-режим), скрыты для owner до назначения менеджера;
- **заморожено без изменений:** `BookingPolicy`, `BookingController` (контроллер не менялся вовсе), routes, middleware, validation, `transitionMap`, правила назначения, `User::assignableToBookings()`, авторизация документов/сообщений.
- Тесты: `tests/Feature/SharedBookingSurfacesE3Test.php`.

#### E3-A4 — Manager Cabinet
✅ COMPLETE — `e9440fc99e1205c7066fe0074e30f1afcb992c07` (`feat: modernize manager cabinet (E3-A4)`)

Manager-страницы уже переиспользовали общий E3-shell/компоненты из более ранних
slices, поэтому это была не переделка с нуля, а точечное исправление
конкретных найденных дефектов плюс новая иерархия «что требует внимания» на
дашборде:

- Chart.js реально никогда не подгружался на Manager dashboard/statistics (canvas молча пустовал); подключён уже установленный `public/plugins/chart.js/Chart.min.js` без новой зависимости;
- убран N+1 в списке тредов Manager-чата (один `Message::count()` на бронь в цикле) — заменён одним сгруппированным запросом в `ManagerController::chat()`;
- sidebar-badge «Мои заявки» был мёртвым кодом (ни один контроллер не передавал такую переменную) — подключён реальный fallback-запрос по образцу существующего unread-messages fallback;
- **более крупная находка:** `ManagerController::dashboard()`/`::statistics()` использовали MySQL-only raw SQL (`DATE_FORMAT()`, `MONTH()`) для месячной группировки графика/статистики — из-за этого оба маршрута не имели Feature-тестового покрытия и падали бы под обязательным SQLite `:memory:`. Переписаны на группировку в PHP (`Collection::countBy`/`groupBy`) — тот же результат, портируемо, теперь тестируемо;
- в work queue (`bookings.blade.php`) добавлен индикатор непрочитанного чата (ограниченный сгруппированный запрос по броням текущей страницы);
- ярлык статистики «Общий доход» (двусмысленно читался как личный доход) переименован в «Выручка (завершено)» — под фактическую семантику суммы (завершённые брони) и под уже существующую формулировку `finance.blade.php`; данные не менялись;
- добавлена dashboard-секция «Требует внимания» (new/progress-брони, от старых к новым) над карточками статистики; существующая «Последние заявки» (все статусы, новые сверху) сохранена как есть.
- **Намеренно не тронуто** (вне явной приоритетной иерархии, без доказанного дефекта): `manager/{finance,content,articles/*,reviews/*,documents,profile,settings}.blade.php`. Маршрут `/manager/knowledge` подтверждён как orphan-from-navigation (делит контроллер/view с «Контент», но не имеет своего пункта в sidebar) — оставлен как есть.
- Независимая read-only ревизия (отдельная сессия, source/diff-level аудит) подтвердила: manager-scoping корректен везде по построению (attention queue, bookings, chat, sidebar badge — cross-manager утечка невозможна, все `whereIn` id-списки заранее ограничены аутентифицированным менеджером); `DATE_FORMAT()`/`MONTH()` → PHP `groupBy`/`countBy` семантически эквивалентен оригинальному MySQL; рост числа запросов 3→4 в `ManagerClientListQueryEfficiencyTest` — реальный намеренный +1 от sidebar-badge fallback, не регрессия. Вердикт: 0 MUST-FIX находок.
- Тесты: `tests/Feature/ManagerCabinetE3RedesignTest.php` (14 новых); существующий `ManagerClientListQueryEfficiencyTest` обновлён под реальный (не регрессивный) рост числа запросов.
- Перенесено дальше в E3-A6/E4 (non-blocking polish, без доказанного дефекта): дублирующийся sidebar pending-badge запрос можно переиспользовать вместо повторного вычисления; `attentionBookings` eager-load неиспользуемого `tour`-отношения; несколько test-coverage пробелов (multi-year группировка статистики, zero-data график, sender-side исключение сообщений).

#### E3-A5 — Admin Cabinet
✅ COMPLETE — `9fee7dfb990c7a6c18fc9dcf9205e3db3dca24e6` (`feat: modernize admin cabinet (E3-A5)`) — application HEAD на закрытии E3-A5 (заменён E3-A6-A/B)

Финальный redesign-slice E3: Admin Dashboard, Bookings (+ booking detail), Chat, Finance,
Users, Roles, Profile, System, Logs, Bonus, Content, article creation, общий
sidebar/mobile shell. Browser QA пройдено на desktop и responsive/mobile для
всех перечисленных поверхностей.

Ключевые продуктовые/security/UX-контракты, закреплённые этим closure:

- **Admin chat: read vs write.** Admin может читать чат любой брони. Писать
  может только Admin, лично назначенный `booking.manager_id`; наблюдающий
  (не назначенный) Admin остаётся строго read-only — попытка записи от
  ненаначенного Admin отклоняется. Контракты назначенного Manager и
  Tourist-участника сохранены без изменений.
- **Семантика назначения брони.** Цели назначения используют единый источник
  истины `User::assignableToBookings()` (query-scope `scopeAssignableToBookings`
  в `app/Models/User.php`): активные Manager и активные Admin назначаемы;
  Tourist исключены; неактивные сотрудники не могут быть назначены заново;
  исторически назначенный, но с тех пор деактивированный сотрудник остаётся
  видимым в UI как inactive (не скрывается и не подменяется).
- **Dashboard.** Использует пять канонических статусов брони по отдельности
  (не агрегирует их в укрупнённые категории); честный label для завершённой
  выручки (не путается с «общим доходом»/незавершёнными суммами).
- **Finance.** Разбивка по ответственному сотруднику включает и Manager, и
  Admin (не только Manager) — отражает то, что Admin тоже может лично вести
  брони.
- **Admin Profile/System IA split.** Личные настройки Admin консолидированы
  под «Мой профиль» (единая точка входа для персональных данных/пароля).
  «Система» содержит runtime/system-информацию и управление кэшем —
  операционный, не персональный раздел.
- **Logs safety.** Логи остаются Admin-only, ограниченными по объёму и
  read-only; без раскрытия абсолютных путей на standalone-странице.
- **N+1 correction.** Admin-страница bookings теперь eager-load'ит роли и
  читает уже загруженную коллекцию ролей вместо повторных вызовов `hasRole()`
  внутри циклов по broniям/сотрудникам.
- **Responsive closure.** Известные responsive-дефекты Dashboard и Profile
  закрыты (включая перенесённый из E3-A4 пункт про переполнение карточки
  «Доход» при ₽-переносе).
- Faker/apostrophe flakiness в `AdminCabinetE3RedesignTest`, найденная в ходе
  сессии, исправлена до финальной верификации.

Тесты: `tests/Feature/AdminCabinetE3RedesignTest.php` (45 тестов, основной
объём), плюс новые `tests/Feature/AdminLogsTest.php` (7),
`tests/Feature/AdminSettingsTest.php` (16), и точечные правки существующих
`CabinetHeaderRoleLinkConsistencyTest`, `CabinetSharedShellFoundationTest`,
`MessageParticipantAuthorizationTest` под новый Admin write-контракт.

#### E3-A6 — Cross-cabinet point-polish
✅ **CLOSED** — два application-slice (E3-A6-A, E3-A6-B); дополнительный application commit не требуется.

Закрывает точечные пункты, перенесённые из E3-A2 / E3-A3 / E3-A4 (не выдуманный новый E3-scope).

##### E3-A6-A — Manager dashboard / query polish
✅ COMPLETE — `8a5018bdf6a95658d195772c05adc3c4f557329d` (`perf: polish manager dashboard queries (E3-A6-A)`)

- Manager dashboard переиспользует уже посчитанные pending/unread значения для sidebar вместо дублирующих `COUNT`-запросов; sidebar fallback для остальных Manager-страниц сохранён;
- неиспользуемый eager-load `tour` убран только из dashboard-запроса `attentionBookings`;
- добавлено regression-покрытие: исключение из месячной статистики текущего года, zero-data dashboard/statistics, query-count.
- Тесты: `tests/Feature/ManagerCabinetE3RedesignTest.php`.

##### E3-A6-B — Cross-role chat UX polish
✅ COMPLETE — `ed550df8989b44e7305bdce6b6f5f063b82a2616` (`fix: polish cross-role chat switching (E3-A6-B)`) — текущий authoritative application HEAD

- общее AJAX-переключение chat thread (`public/js/cabinet-chat.js`) сохраняет `scrollTop` списка тредов; Tourist / Manager / Admin используют явные стабильные хуки `data-chat-thread-scroll`;
- browser Back/Forward остаётся на том же общем пути переключения;
- гонка stale-response в `refreshNavUnread` закрыта через уже существующую generation-модель;
- добавлено regression-покрытие sender-side unread;
- контракты Admin observer / assigned-Admin (security и UI) сохранены без изменений;
- у Tourist chat исправлен page-level пустой вертикальный overflow узким `.tc-chat__panel { contain: layout; }`; layouts Manager/Admin измерены и не затронуты.
- Browser QA PASS: Tourist, Manager, assigned Admin, observer Admin — сохранение scroll, быстрое переключение, Back/Forward, draft, polling, unread, read-only observer и композер assigned Admin.
- Тесты: `tests/Feature/CabinetChatContinuityTest.php`, `tests/Feature/MessageParticipantAuthorizationTest.php`.

##### E3-A6 closure

E3-A6 — **CLOSED**. **E3-A6-C application slice не нужен.** Два проаудированных «хвоста» намеренно не менялись:

- **Tourist «В работе».** Агрегатная метрика считает `NEW` + `PROGRESS`; опция фильтра с той же формулировкой соответствует только `PROGRESS`; каноническая формулировка `PROGRESS` в остальных местах — «В обработке». Семантика статусов/запросов не нарушена — это косметическая терминологическая консистентность. Отложено в E4 «remaining visual inconsistencies». Итоговая заменяющая формулировка **не** выбрана.
- **`/manager/knowledge`.** Legacy `/manager/knowledge` — GET-redirect; реальный `/cabinet/manager/knowledge` — alias на `ManagerController::content()`; текущий sidebar/навигация использует «Контент». Route рабочий и безвредный — **оставлен как есть**, не классифицируется как дефект, требующий удаления. Опциональная гигиена redirect/alias может быть пересмотрена в E4.

#### 9C.5 — E3 итоговый test baseline

**Финальный верифицированный baseline на закрытии E3-A6 (авторитетный):**

```text
PHP 8.3.32
PHPUnit 11.5.56
SQLite :memory:
full: 1242 tests / 8056 assertions, 0 failures, 0 errors
```

(На закрытии E3-A5 baseline был 1233 tests / 8023 assertions.)

Единственная PHPUnit deprecation — pre-existing XML schema deprecation; это не
функциональный/кодовый сбой. Финальный полный прогон потребовал прямого
вызова PHPUnit с временным CLI-override `-d memory_limit=1024M` (дефолтный
128M CLI memory_limit этой машины недостаточен для набора такого размера);
это не изменение `php.ini`/runtime-конфигурации проекта. PHPUnit против
canonical MySQL остаётся запрещён.

До E3 (после закрытия E2, `886bde98`): 1051 tests / 7180 assertions. Рост до
1233 / 8023 (E3-A5) и 1242 / 8056 (E3-A6) распределён по E3-A1…E3-A6
(foundation-контракты, tourist, shared booking, manager, admin, dashboard-query
и chat-polish regression-покрытие) — ожидаемый, не регрессия.

#### 9C.6 — Перенесённые пункты E3-A2…A4: итоговая disposition

- AJAX thread-switch UX полировка чата (E3-A2) — ✅ сделано в E3-A6-B;
- дублирующийся sidebar pending-badge запрос в Manager (E3-A4) — ✅ сделано в E3-A6-A;
- неиспользуемый eager-load `tour` в `attentionBookings` (E3-A4) — ✅ сделано в E3-A6-A;
- тестовые пробелы (месячная группировка статистики Manager, zero-data, sender-side исключение сообщений; E3-A4) — ✅ покрыто в E3-A6-A / E3-A6-B;
- tourist «В работе» — косметика, **отложено в E4** (итоговая формулировка не выбрана);
- `/manager/knowledge` — **оставлен как есть**; опциональная alias-гигиена может быть пересмотрена в E4.

### 9.3 Personal cabinet UX/UI/design pass (E3) — ✅ CLOSED (E3-A1…E3-A6)

Полное содержание — §9C. Кабинеты tourist / manager / admin пройдены: information
architecture, navigation/sidebars/headers, dashboard priorities, action
placement, cards/tables/forms, icons, status presentation/color system,
spacing/typography/hierarchy, mobile/tablet/desktop behavior, визуальная
консистентность с публичной E2-системой там, где уместно, consistency между
ролями без стирания role-specific UX. Design-решения следовали из findings, а
не из blanket-restyling.

**S13-R2 — Manager review cache parity relevance check — закрыт.** См. §5.7:
живого дефекта нет, parity-реализация не требовалась, это завершённая
historical relevance check.

Точечная полировка E3-A6 (A + B) закрыта; оставшиеся «хвосты» — §9C.6 (перенесены в E4 / оставлены как есть), не блокируют закрытие E3.

### 9.4 Post-redesign stabilization / resilience (E4) — ✅ CLOSED

E4 продолжил закрытый E3-A6 (application HEAD на входе — `ed550df8`, docs
checkpoint входа — `c89e923f`). E4 полностью закрыт на уровне приложения этой
серией slices, завершается настоящей документационной правкой (E4-E2).

**Итоговый application HEAD E4: `55a9fcaf6371ef0c749bb668c9b27783d1df358c`
(`fix: close final E4 application polish`).**

#### E4-A — baseline confirmation / QA matrix
✅ CLOSED — планирование, без отдельного application-коммита.

Подтверждён full-regression baseline на входе в E4 (1242 / 8056, checkpoint
`c89e923f`) и определена матрица browser/device/keyboard/accessibility QA,
использованная в E4-C.

#### E4-B — branded system error pages
✅ CLOSED — `511282e38f05b63871d4e1d9fcb154a8fa63df56` (`feat: add branded system error pages (E4-B)`)

Добавлены недостающие брендированные системные error-страницы
(403/419/429/500/503; 404 уже была переведена на E2-презентацию в
E2-A6-I2) — закрывает пункт, перенесённый из E2-A6-I2/E3.

#### E4-C — Browser / Device / Keyboard / Accessibility QA
✅ CLOSED — QA-проход без отдельного application-коммита; findings закрыты в E4-D1…E4-D3.

Большой QA-проход по браузерам/устройствам/клавиатуре/accessibility.
Evidence: `C:\Avilona_private\E4\E4-C_Browser_Device_Accessibility_QA\20260920-150303\`
(включая persistent QA SQLite `qa.sqlite`) — сохраняется, не удаляется/не
сбрасывается.

Найденные и впоследствии закрытые дефекты (F-01…F-16 — все
**FIXED_VERIFIED**) устранены в E4-D1/E4-D2/E4-D3. Итоговые P-классификации
(P-01…P-13) — см. §9.4.5 ниже.

#### E4-D1 — shared shell and auth UX stabilization
✅ CLOSED / PUSHED — `16b71c6a51064737007b0645214c919462e40f17` (`fix: stabilize shared shell and auth UX (E4-D1)`)

#### E4-D2 — cabinet responsive layout stabilization
✅ CLOSED / PUSHED — `46a97f3d241ab1f53663651f7b5e9147d00f9ec7` (`fix: stabilize cabinet responsive layouts (E4-D2)`)

#### E4-D3 — stabilization findings closure
✅ CLOSED / PUSHED / VERIFIED — `c1ad29cb5127536577545778fa8b8a79e9ddd24e` (`fix: close E4-D3 stabilization findings`)

Reconciliation-статус: `REPORTING_COUNT_ERROR_ONLY` — расхождение было
исключительно в отчётном подсчёте находок, не в фактическом application-
поведении.

Evidence: `C:\Avilona_private\E4\E4-D3_Browser_QA\20260922-121234\`.

#### E4-E1 — final technical closure
✅ TECHNICALLY CLOSED / PUSHED — `55a9fcaf6371ef0c749bb668c9b27783d1df358c` (`fix: close final E4 application polish`)

Финальный полный PHPUnit: **1286 tests / 8628 assertions, 0 failures, 0
errors** (SQLite `:memory:`; canonical MySQL не затронут). Локальный
`php artisan test --compact` упёрся в дефолтный 128M CLI memory_limit ближе к
концу набора — это ограничение локального PHP CLI runner'а, **не** дефект
приложения и **не** провал теста; полный исторический прогон с
`memory_limit=512M` прошёл целиком с тем же результатом (1286 / 8628, 0 / 0).

Evidence: `C:\Avilona_private\E4\E4-E1_Final_Technical_Closure\20260923-015901\`.

Результат: `READY_FOR_E4_DOCUMENTATION_CLOSURE`. Блокеров релиза уровня
приложения не осталось.

#### 9.4.5 — Итоговые находки E4 (F / P)

Все **F-01…F-16 — FIXED_VERIFIED**.

Итоговые P-классификации:

- **P-01** FIXED_VERIFIED — public focus indication исправлен и верифицирован кросс-браузерно.
- **P-02** FIXED_VERIFIED — public skip link добавлен и верифицирован.
- **P-03** FIXED_VERIFIED на уровне шаблона — у изображений CAPTCHA теперь есть accessible alt-текст. **Остаточное ограничение (не блокер релиза, non-blocking accessibility debt):** визуальная image CAPTCHA сама по себе остаётся принципиально сложной/недоступной для части пользователей assistive technology без нетекстовой/невизуальной альтернативы вызова; alt-текст **не делает** CAPTCHA универсально доступной — это ограничение сохраняется как известный backlog, не как решённая проблема.
- **P-04** FIXED_VERIFIED — ошибки логина теперь раскрываются через alert-семантику.
- **P-05** Общего release-blocking дефекта заголовков не найдено. Единственный реальный оставшийся экземпляр — **`/tours`**: временная страница поиска туров не имеет желаемой структуры H1/main. Это **не** общий public accessibility debt — переносится в E5 исключительно потому, что именно эта страница целенаправленно перестраивается/заменяется в рамках E5 Tour Search.
- **P-06** FIXED_VERIFIED — public mobile toggler: accessible name/state/Esc-поведение.
- **P-07** FIXED_VERIFIED — reduced motion.
- **P-08** FIXED_VERIFIED — фокус после Accept на cookie-баннере.
- **P-09** FIXED_VERIFIED — read-only уведомление Admin observer.
- **P-10** Не блокер релиза. Целевое text/reflow-тестирование не выявило page-level горизонтального overflow. Внутренний скролл `.table-responsive` — ожидаемое поведение. Сохраняется в backlog только как non-blocking polish-заметка (без обязательного немедленного действия).
- **P-11** NOT_REPRODUCED — 320px page-level overflow Admin-чата не воспроизведён.
- **P-12** FIXED_VERIFIED — password autocomplete-семантика.
- **P-13** **KNOWN_POLISH_BACKLOG** — responsive table wrappers не имеют контекстных accessible-имён; сами таблицы сохраняют собственную `<th>`-семантику и остаются пригодны для использования. Правильное решение — осмысленный per-table label, а не слепое массовое добавление generic-подписей. **Не реализовывать P-13 в E4-E2** (документационная задача); остаётся в non-blocking polish backlog для последующей отдельной slice.

#### 9.4.6 — E4 QA evidence (сохранить, не трогать)

- `C:\Avilona_private\E4\E4-C_Browser_Device_Accessibility_QA\20260920-150303\` (включая persistent QA SQLite `qa.sqlite`);
- `C:\Avilona_private\E4\E4-D3_Browser_QA\20260922-121234\`;
- `C:\Avilona_private\E4\E4-E1_Final_Technical_Closure\20260923-015901\`.

Эти директории и QA SQLite — не удалять/не сбрасывать/не пересеивать. QA-сервер, если он ещё запущен, не останавливать только ради документационного закрытия.

#### 9.4.7 — non-blocking polish backlog (перенесено дальше E4, не E5)

Ниже — общий accessibility/UX polish backlog, явно **не** относящийся к E5
(так как не связан с заменой `/tours`):

- P-13 — responsive table wrapper accessible naming (см. выше);
- CAPTCHA residual accessibility limitation (см. P-03 выше) — известное
  ограничение image-CAPTCHA для части assistive-technology пользователей;
- P-10 cosmetic/internal table-tightness — только как non-blocking заметка, не отдельная обязательная задача;
- tourist «В работе» терминологическая консистентность (перенесено из E3-A6, §9C.6) — итоговая формулировка всё ещё не выбрана;
- опциональная `/manager/knowledge` redirect/alias-гигиена (перенесено из E3-A6) — route рабочий и безвредный.

Опциональная гигиена, ранее упомянутая для E4: legacy-redirect `/manager/knowledge` (рабочий и безвредный, не дефект) — осталась нетронутой, может быть пересмотрена в будущей non-blocking polish-slice.

#### E4 closure

E4 объявляется **CLOSED** этой документационной правкой (E4-E2) поверх
application-checkpoint `55a9fcaf`. Технический closure (E4-E1) уже был PASS
до этой правки; настоящая правка фиксирует это на уровне документации и не
меняет код приложения.

## 9A. Канонические факты компании

Точные проектные факты (не менять при рефакторинге контента):

- Официальный публичный e-mail: `avilonatur@bk.ru`.
- Получатель входящей публичной формы: `straus97@mail.ru` — это намеренный
  внутренний submission recipient; **не** заменять его автоматически на
  публичный e-mail.
- Текущий фактический офис / публичный адрес:
  `198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10`.
- Старый адрес на Звенигородской (`191119, Санкт-Петербург, ул. Звенигородская,
  д. 22, литера А, офис 053, пом. 7Н`) — **не** текущее физическое расположение.
  Там, где Звенигородская явно помечена как юридический/регистрационный адрес,
  это намеренно и не должно удаляться из-за переезда офиса.
- Параллельно идёт регистрация юридического адреса `198302, г. Санкт-Петербург,
  ул. Морской Пехоты, д. 10, корп. 1, литера А, кв. 22`. Успешная регистрация в
  ФНС/ЕГРЮЛ пользователем **НЕ подтверждена**. Этот адрес **НЕ** документируется
  как текущий зарегистрированный/юридический адрес компании — упоминается только
  как pending параллельный юридический трек, явно помеченный «не утверждён».
- Оплата: наличные; интернет-эквайринг; оплата по QR на расчётный счёт
  организации; эквайринговый терминал в офисе.
- Возвраты: на банковскую карту клиента; итоговая сумма зависит от условий/
  решения туроператора; при, например, неподтверждённом отеле возможен полный
  возврат; отмена по инициативе клиента может быть ограничена условиями
  оператора.
- Передача документов: Авилона публично **не** рекламирует отдельную общую
  курьерскую доставку. По индивидуальной договорённости сотрудники могут
  распечатать документы (страховку/ваучер/билеты) и организовать их передачу.
  Каноническая публичная формулировка — `«Передача документов по договорённости»`.
- Рассрочка/кредит на поездки сейчас предлагаются, но допустима только общая
  формулировка — не выдумывать банки, ставки, партнёров, финансовые условия.
- Часы работы: `PENDING_BUSINESS_DECISION_OPENING_HOURS` — конфликт исторической
  публичной копии (home: «будни 10:00–20:00» vs contacts: «будни 11:00–20:00, по
  предварительной записи»). Авторитетного выбора нет. **Не выбирать.** Должно быть
  решено до финального production-релиза (E6).

## 10. Поиск туров — E5 ✅ CLOSED на уровне приложения

**E5 закрыт на уровне приложения** (E5-A1 … E5-A4, все коммиты запушены; финальный
application checkpoint `c56e3306`). **Production НЕ развёрнут** — закрытие E5 не
означает production-readiness (см. §12 — E6).

Старое временное локальное решение поиска туров (форма фильтров на home, локальный
поиск по таблице `tours`, Sletat public API) **больше не является ни текущей
реализацией, ни активным планом** — оно удалено (см. §10.7).

`docs/E5-tour-search-research-2026-09-23.md` — исторический research-отчёт
**до** реализации (в нём E5 ещё «NEXT»); сохранён как запись обоснования выбора, не
как описание текущего состояния. Авторитетны текущее дерево, `git log`, тесты и
миграции.

### 10.1 Хронология E5 (из Git, полные SHA)

| Slice | Полный SHA | Subject |
|---|---|---|
| E5 research docs | `1476c1cc87fa83aa3d191d215a8f210f21120799` | `docs: record E5 tour search research` |
| E5-A1 | `9005457a2bde7a5592b152ac7e601f7c22d9b61f` | `feat: add Tourvisor search module PoC (E5-A1)` |
| E5-A2A | `c0e16ae3dc86703c019ca7fd138ab4fa658481c5` | `feat: establish Tourvisor inquiry integration foundation (E5-A2A)` |
| E5-A2A.1 | `d59d211e518169297869dff103d87b85da0e5ea8` | `fix: harden Tourvisor webhook callback (E5-A2A.1)` |
| E5-A2B | `0399d21fb10a9b5f69c3f2c8940a96777197bf67` | `fix: secure Tourvisor webhook registration (E5-A2B)` |
| E5-A3 | `137f109ba46263a20af7d521a9dc32677493a031` | `feat: complete incoming inquiry booking workflow (E5-A3)` |
| E5-A3.1 | `00ea2f0440f0a25b6ecd161aadedb804ce9e8ac2` | `fix: remove plaintext staff client credentials (E5-A3.1)` |
| **E5-A4 (финальный application checkpoint)** | `c56e330685ffa8953b22c803d6dce5334c69e81b` | `fix: complete final tour search polish (E5-A4)` |

Все — линейная история `a1d72a40..c56e3306` на ветке `db-rebuild-stage3`
(восемь коммитов, сверено `git log` в этом docs-slice). E5-A2B в Git содержит
только `TourvisorWebhookRegistrar` (+ exception и тест); живой PoC (§10.4)
выполнялся вне репозитория, его evidence — приватные артефакты.

### 10.2 Финальная архитектура E5

**Публичная часть:**

```text
Avilona /tours
  -> Tourvisor Standard search module
  -> обычная заявка (inquiry)
  -> Tourvisor получает данные обращения/контакта
  -> Tourvisor webhook-уведомление
  -> Avilona проверяет callback (токен в пути, fail-closed)
  -> авторитетный server-side запрос Export API
  -> IncomingInquiry
```

**Сотрудники (Manager / Admin):**

```text
IncomingInquiry
  -> явное взятие в работу (claim) / назначение
  -> явный выбор клиента ИЛИ безопасное создание клиента
  -> менеджер проверяет цену и наличие
  -> явное создание нативной Booking Avilona
  -> существующий жизненный цикл Booking
```

**Внешнее исполнение:** менеджер Avilona позже бронирует тур вручную на сайте
туроператора под учётными данными агентства.

**Явно НЕТ (инварианты E5):**

- НЕТ автоматического бронирования у туроператора;
- НЕТ автоматической оплаты;
- НЕТ автоматического создания Booking при импорте;
- НЕТ автоматического определения владельца (User) по e-mail/телефону/имени;
- НЕТ фиктивной локальной записи `Tour` при конвертации обращения Tourvisor.

CRM Tourvisor не является авторитетным источником: рабочий процесс остаётся в
Avilona (Tourist / Manager / Admin, чат, документы, уведомления, жизненный цикл).

Ключевые компоненты (для ориентира; точные сигнатуры — в исходниках):
`app/Services/Tourvisor/*` (`TourvisorExportClient`, `TourvisorInquiryImporter`,
`TourvisorInquiryIntake`, `TourvisorWebhookRegistrar`),
`app/Http/Controllers/Api/TourvisorWebhookController.php` + middleware
`VerifyTourvisorWebhookToken` (маршрут `GET /api/webhooks/tourvisor/inquiries/{webhookToken}`,
throttle `tourvisor-webhook`), `app/Jobs/ImportTourvisorInquiry.php`,
`app/Models/IncomingInquiry.php`, `app/Services/IncomingInquiries/*`,
`app/Policies/IncomingInquiryPolicy.php`, `app/Services/Accounts/StaffClientAccounts.php`,
staff-экраны `manager/inquiries/*` (маршруты `/manager/inquiries…`).

### 10.3 Факты провайдера Tourvisor (только подтверждённые)

- Tourvisor — выбранный провайдерский путь для E5.
- Используется существующий аккаунт Avilona в Tourvisor; домен `avilona.ru`
  связан с аккаунтом.
- Модуль: **Standard**. ID модуля **9981450** — публичная, **не секретная**
  конфигурация встраивания.
- Для заявок используется **Export API**; для уведомлений — **WebHook**.
- Живой PoC успешно подтвердил точное поведение Export + webhook (§10.4).
- Коммерческие данные, ранее зафиксированные в
  `docs/E5-tour-search-research-2026-09-23.md` (addendum 2026-09-25: пробный
  период тарифа Standard до 2026-10-04, ориентир бюджета до ~2 000 ₽/мес.,
  окончательное коммерческое решение на тот момент не принято), **не
  обновлялись** в этом slice; цены не пересматривались. Актуальное коммерческое
  решение (продление/оплата тарифа) — действие владельца вне репозитория и
  должно быть подтверждено до production.
- Условия провайдера сверх перечисленного здесь не утверждаются.
- Секреты (`TOURVISOR_EXPORT_API_KEY`, `TOURVISOR_WEBHOOK_TOKEN`) — только в
  server-side `.env`; в репозитории — лишь пустые ключи в `.env.example.generated`;
  в браузер/Blade не попадают.

### 10.4 E5-A2B — запись живого PoC (без секретов и персональных данных)

Живой PoC доказал:

- защищённый Export API-запрос работает;
- реальная live-структура обычной заявки совместима с текущей нормализацией;
- регистрация webhook работает;
- неверный callback-токен отвергается (fail-closed);
- второй синтетический inquiry прошёл полную реальную цепочку;
- создана ровно одна запись IncomingInquiry;
- повтор webhook идемпотентен;
- лишнего второго Export-запроса после состояния imported нет;
- видимость для Manager/Admin подтверждена;
- Tourist получает отказ;
- webhook безопасно удалён;
- туннель после PoC остановлен.

В документацию **не** включены: API-ключ, webhook-токен, полный callback URL,
реальные/синтетические контактные значения. Приватные артефакты PoC:
`C:\Avilona_private\E5\E5-A2B_Live_PoC\` (не в репозитории, не в Project Sources).

### 10.5 E5-A3 — рабочий процесс входящего обращения

У IncomingInquiry есть **бизнес-workflow, независимый от технического состояния
импорта** (`state`: pending/importing/imported/failed/unsupported — техническое;
`workflow_state` — бизнес):

```text
new -> in_progress -> converted
                  \-> closed
```

- явное взятие в работу сотрудником (claim; Manager или Admin);
- Admin может переназначить ответственного (reassign — только Admin); чужой
  менеджер не обрабатывает обращение, взятое коллегой;
- подсказки клиентов (по совпадению данных) — **не** автоматическое владение;
- явный выбор существующего клиента из активных туристов;
- безопасное создание клиента (см. §10.6);
- одно обращение → не более одной Booking (`incoming_inquiries.booking_id` UNIQUE);
- конвертация транзакционна, с блокировкой строки и перепроверкой состояния;
- фиктивный Tour **не** создаётся; `bookings.tour_id` может оставаться null для
  внешнего обращения;
- итоговая цена — подтверждённая менеджером сумма (`bookings.total_price`);
  импортированная цена Tourvisor — только справочная; сумма **не**
  умножается на число пассажиров;
- переиспользуется нативный начальный статус Booking;
- закрытие без бронирования поддержано (close, с причиной);
- происхождение (provenance) сохраняется.

Миграция: `2026_10_01_000000_add_workflow_to_incoming_inquiries_table.php`.

### 10.6 E5-A3.1 — исправление безопасности (plaintext credentials)

Прежний staff-путь создания клиента в Booking: генерировал пароль, хранил его
открытым текстом в `users.temp_password` и отправлял по e-mail. **E5-A3.1 убрал
это активное поведение.** Текущий контракт:

- общий сервис `StaffClientAccounts` (и для обращений, и для ручного оформления);
- случайный пароль (64 символа) нигде не раскрывается; хранится только хеш;
- `temp_password` остаётся `null` для новых аккаунтов; открытый пароль не
  отправляется, не отображается, не логируется;
- явное действие «ссылка установки пароля» — стандартная ссылка сброса Laravel
  (Password broker), только для клиента без подтверждённого e-mail и без входа;
- импортированный/введённый сотрудником e-mail **не** считается подтверждённым
  (`email_verified_at = null`);
- защита от дубликатов e-mail без учёта регистра;
- технические адреса `.invalid` (`temp_<uuid>@no-email.avilona.invalid`) не
  получают внешних писем со ссылкой установки.

Миграция очистки: `2026_10_02_000000_clear_legacy_temp_passwords_from_users_table.php`
(обнуляет накопленные значения `users.temp_password`; колонка остаётся, nullable;
`down()` пустой). **Эта миграция НЕ применена к canonical MySQL.**

### 10.7 E5-A4 — финальное публичное состояние

**HOME:** устаревшая фиктивная форма фильтров удалена; на home — честный CTA на
`/tours`; без имитации сохранения параметров.

**TOURS:** модуль Tourvisor остаётся на `/tours`; один загрузчик (HTTPS); секретов в
браузере нет; публичное пояснение: поиск работает через Tourvisor; цена и
наличие могут измениться; обращение — не бронирование и не оплата; менеджер
проверяет предложение. Страница имеет H1/main (закрывает перенесённый в E5 пункт
P-05 для `/tours`).

**РАСКРЫТИЕ ПЕРСОНАЛЬНЫХ ДАННЫХ:** `/tours` сообщает, что информация, введённая в
модуле Tourvisor, обрабатывается через Tourvisor и затем передаётся Avilona;
страница cookies фиксирует поведение внешнего модуля.

**LEGACY:** публичный Sletat API удалён; неиспользуемый локальный `/api/tours`
поиск удалён; неиспользуемые Sletat service/controller/command/config удалены.
Структуры БД Tour/локального каталога **сохранены**, пока от них зависит нативная
Booking. Более глубокая очистка (Coral / sync / seed / TourOperator) — отложена
(§10.10).

### 10.8 Ручные настройки провайдера и приватность

**Подтверждено владельцем вручную (кабинет Tourvisor → Настройки → Модули):**
«Иконка избранного для туристов» (Favorites icon for tourists) — **ВЫКЛЮЧЕНА (OFF)**.
Ранее наблюдавшееся пересечение Favorites с cookie-баннером решено **настройкой
провайдера, а не CSS приложения**. CSS-обходов для Favorites Tourvisor **не**
добавлять.

**Текущая видимая конфигурация приватности в аккаунте провайдера:** чекбокс
согласия на обработку персональных данных Tourvisor — **OFF**; поля URL политик
согласия — **пусты**. Это **не** принимается молча как финальная production-
конфигурация. Классификация: **`PRIVACY_LEGAL_FACT_REQUIRED` / E6 PRE-PRODUCTION
ACTION.** URL и юридические тексты не выдумываются.

**Неразрешённые факты приватности до production** (решает владелец/юрист; не
реоткрывают реализацию E5 — это pre-production требования):

1. корректная идентификация оператора/провайдера Tourvisor для публичного показа;
2. корректный URL политики конфиденциальности провайдера, если требуется;
3. какие cookies/хранилища реально использует встроенный модуль;
4. нужен ли addendum к существующей политике/согласию Avilona на персональные
   данные для потока Tourvisor;
5. семантика хранения данных (retention) у Tourvisor;
6. должен ли модуль Tourvisor загружаться только после согласия на cookies;
7. корректный URL для поля «согласие на обработку персональных данных» в Tourvisor;
8. корректный URL для поля политики конфиденциальности в Tourvisor;
9. нужно ли включить чекбокс согласия Tourvisor до production.

Юридических выводов без проверенных фактов не делается.

### 10.9 Тестовый baseline E5

**1393 tests / 9759 assertions, 0 failures, 0 errors** (SQLite `:memory:`;
canonical MySQL тестами не затрагивается). Одна PHPUnit deprecation — XML-схема
`phpunit.xml` (подтверждено выводом PHPUnit), функциональным сбоем не является.
Ход: E5-A3 → 1383 / 9682 → E5-A3.1/E5-A4 → 1393 / 9759.

### 10.10 Non-blocking backlog (не блокеры E5)

Проверено на `c56e3306`; решённое не переносится:

- **Остаточные legacy-структуры:** `CoralTravelService` / `app/Services/TourOperators/*`,
  sync/seed-команды (`SyncToursCommand`, `UpdateToursCommand`, `SeedTourOperatorsCommand`),
  `TourOperator` — всё ещё существует; более глубокая очистка отложена.
- **`Tour::scopeSearch`** (`app/Models/Tour.php`) — неиспользуемый scope, всё ещё существует.
- **P-13** — contextual accessible names для responsive table wrappers (актуален).
- **CAPTCHA** — остаточное ограничение визуальной image-CAPTCHA для assistive
  technology (актуально, не решено).
- **P-10** — косметическая/внутренняя плотность таблиц (info-only).
- **Tourist «В работе»** — терминологическая консистентность (итоговая формулировка
  по-прежнему не выбрана; актуально).
- **`/manager/knowledge`** — redirect/alias-гигиена (route `/manager/knowledge`
  всё ещё существует как редирект; безвредно).

Решено и больше не переносится: P-05 для `/tours` (H1/main — E5-A4); временное
tour-search решение (заменено).

### 10.11 После E5, до E6 (утверждённый порядок)

```text
E5 documentation closure (этот slice)
  -> свежий Project Sources
  -> Screenshot Audit Pack
  -> независимый полный аудит Astra / ChatGPT Work
  -> утверждённые исправления по аудиту (если есть)
  -> E6 production / operations
```

Объём независимого аудита (не выполняется в этом slice): architecture; security;
performance; database; queries; maintainability; technical debt; modernization;
UX; accessibility; SEO; public structure; функции для добавления/удаления;
Tourist workflow; Manager workflow; Admin workflow; operations; design.

## 11. Запреты без отдельного operational plan

- `composer update`, `npm update`, `npm audit fix`;
- migrations/seed/import/reset/refresh/wipe;
- direct writes to canonical MySQL;
- PHPUnit against canonical MySQL;
- `legacy:import-v4 --execute`;
- real RSS/provider/email/SMS/Telegram external calls;
- deletion of recovery/rollback artifacts;
- destructive Git operations;
- broad refactor mixed with functional/DB/docs/dependency work.

## 12. E6 — финальный production / operations stage (чеклист)

⬜ **PENDING.** E6 начинается только после: E5 documentation closure → свежий
Project Sources → Screenshot Audit Pack → независимый полный аудит → утверждённые
исправления по аудиту (§10.11). Закрытие E5 ≠ production-ready.

### 12.1 Production platform

- доступность/развёртывание Timeweb, когда провайдер выделит мощности (§13);
- bootstrap Ubuntu-сервера;
- SSH hardening; firewall;
- Nginx; PHP-FPM; СУБД; TLS;
- права на storage; deployment user;
- backups; logging/rotation; monitoring.

### 12.2 Runtime / dependency refresh (явное требование владельца проекта)

**Перед финальным production-релизом — ОТДЕЛЬНЫЙ guarded modernization pass:**
проверить и, где уместно, обновить НЕОБХОДИМЫЕ системные/прикладные компоненты до
последних **СТАБИЛЬНЫХ ПОДДЕРЖИВАЕМЫХ** версий, совместимых с проектом.

Аудит минимум: пакеты ОС; PHP; Composer; Laravel / PHP-зависимости; Node.js; npm;
frontend-зависимости; сервер БД; Nginx; любые queue/scheduler/runtime-сервисы,
которые реально используются.

«Latest» **не** означает слепой major-upgrade. Требования:

- поддерживаемые stable-релизы;
- сначала совместимость;
- обзор release notes / breaking changes;
- один контролируемый слой за раз;
- полные тесты после соответствующих изменений;
- путь отката (rollback);
- dependency modernization отделена от функциональной работы.

В этом (E5 closure) docs-slice зависимости **не** обновлялись.

### 12.3 Production migrations

- применять все pending E5-миграции только по guarded production-плану миграций
  (`2026_09_26_000000_create_incoming_inquiries_table`,
  `2026_10_01_000000_add_workflow_to_incoming_inquiries_table`,
  `2026_10_02_000000_clear_legacy_temp_passwords_from_users_table`); фактический
  применённый статус на canonical MySQL сверить с таблицей `migrations`
  (известно: `2026_10_01…` и `2026_10_02…` НЕ применены);
- перед миграцией очистки `temp_password` — аудит legacy-пользователей;
- решить вопрос об инвалидации старых «принудительных» паролей до применения.

### 12.4 Tourvisor (production)

- настроить production-секреты (`TOURVISOR_EXPORT_API_KEY`, `TOURVISOR_WEBHOOK_TOKEN`);
- настроить webhook на production-домен; проверить Export API;
- Favorites — OFF (подтверждено, перепроверить);
- решить privacy/consent-настройки провайдера (§10.8);
- подтвердить коммерческий тариф/продление (§10.3);
- production-domain smoke Tourvisor.

### 12.5 Production-domain smoke (перепроверить)

Поиск Tourvisor; Санкт-Петербург / Москва; запросы провайдера в Firefox;
предупреждение провайдера Yandex Maps; поведение отложенного loader'а;
мобильный fullscreen; dropdown на узком desktop; отправка заявки; webhook;
импорт и workflow сотрудников Avilona.

### 12.6 Security / ops

`APP_ENV` / `APP_DEBUG`; HTTPS; trusted proxies / `X-Forwarded-Proto`; security
headers / CSP (по необходимости); mail; cron/scheduler; RSS scheduling; backups;
restore test; безопасность логов; права файлов/storage.

### 12.7 Business

Решить `PENDING_BUSINESS_DECISION_OPENING_HOURS` (§9A) до финального релиза.

## 13. Инфраструктура / хостинг — текущее состояние

- **Текущий production-хостинг остаётся: REG.RU.** Миграции production не было.
- **Будущий сервер Timeweb Cloud заказан (предзаказ): `web-prod-spb-01`** —
  2 vCPU, 4 GB RAM, 50 GB NVMe, Санкт-Петербург. Состояние: **PREORDERED / ожидание
  мощностей провайдера**. Это **НЕ production**.
- Владелец создал отдельный SSH-публичный ключ для этого будущего сервера.
  Пути к приватным ключам и секреты в документацию репозитория **не** записываются.
- **DNS не менялся; домены остаются у REG.RU.**
