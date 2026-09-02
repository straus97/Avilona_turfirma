# Документация Avilona_turfirma

Дата актуализации содержания: **2026-09-02**

## 1. Текущий checkpoint

| Параметр | Значение |
|---|---|
| Project | `C:\wamp\www\Avilona_turfirma` |
| Branch | `db-rebuild-stage3` |
| **Текущий authoritative application HEAD** | **`ad6e9c23986d479cbbbf6f511e96bc139ae26576` (`feat: redesign public News + Articles editorial experience (E2-A5-I1)`)** |
| Историческое E1 closure application commit | `08d0626311234faa06dedf2828cb878805241990` (`fix: close final public audit gaps`) — НЕ текущий HEAD |
| Предыдущий функциональный checkpoint (Stage 13) | `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` (`fix: remove obsolete guest booking flow`) |
| Активный внешний documentation/source (Project Sources) checkpoint | `0be9044e35ec5be670c8f9bb33de020491214423` (`docs: close E1 and prepare E2`) — набор сгенерирован 2026-08-29, сейчас **STALE / pending refresh** (см. §8) |
| Full PHPUnit baseline | **1006 tests / 7037 assertions** (историческое E1-closure значение: 1001 / 7013) |
| PHP | `C:\wamp\bin\php\php8.3.32\php.exe` (8.3.32) |
| PHPUnit DB | SQLite `:memory:` only |
| Laravel | 12.65.0 |
| PHPUnit | 11.5.56 |
| Stage 0–13 | ✅ CLOSED |
| E1 Comprehensive Audit | ✅ **TECHNICALLY CLOSED** (см. §5A) |
| Текущая фаза | **E2 — Public UX / UI / Design Redesign — 🔄 IN PROGRESS** (E2-A1…E2-A5 завершены; см. §9B) |
| E3 / E4 / E5 / E6 | PENDING (см. §9) |

Единственная PHPUnit deprecation — это pre-existing XML schema deprecation; это не функциональный/кодовый сбой.

Baseline 1006 / 7037 ожидаем: E2-A5 намеренно добавил 5 regression-тестов
(2× News.link render-safety, 2× editorial `og:type=article`, 1× News RSS
autodiscovery). Это **не** регрессия относительно 1001 / 7013.

Этот файл фиксируется отдельным docs-only commit поверх application HEAD `ad6e9c23`. Documentation/source HEAD после этого commit будет новее application commit и должен определяться Git, а не быть заранее зашит в этот файл. Будущий docs/source checkpoint hash пока неизвестен и не выдумывается.

Project Sources ещё не обновлён под этот checkpoint — активен исторический набор для `0be9044e`, он устарел — см. §8.

## 2. Источники истины

Приоритет:

1. current Git HEAD, source code и tests;
2. `README.md`, этот файл и `docs/roadmap.md`;
3. independently verified test/browser/DB evidence;
4. внешний Project Sources set, созданный из clean pushed documentation HEAD;
5. исторические материалы под `docs/archive/` и старые Project Sources.

Активный внешний source set для `0be9044e` (см. §8) после нового refresh должен быть сохранён в archive, а не удалён.

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

## 8. E1 closure / E2 progress и Project Sources статус

Stage 13 закрыт на уровне repository/local technical closure на функциональном HEAD `dba20e2c6e2e66b6f69f33710b2626b3fe181e31`. E1 Comprehensive Audit технически закрыт на историческом application commit `08d0626311234faa06dedf2828cb878805241990` (§5A). После E1 closure repository прошёл E2-A1…E2-A5 и находится на application HEAD `ad6e9c23986d479cbbbf6f511e96bc139ae26576` (§9B):

- все Stage 13 миграции применены, pending = 0 (§6.1);
- Stage 13 code/schema/legal/test reconciliation — PASS;
- E1 closure baseline: **1001 tests / 7013 assertions**; текущий baseline после E2-A5: **1006 tests / 7037 assertions**;
- функциональных блокеров Stage 13 / E1 не осталось (см. §5A.2 про намеренно отложенные пункты);
- E2 **в процессе** — глобально не завершён.

### 8.1 Project Sources — требуется refresh

Активный (внешний) Project Sources набор устарел относительно текущего repo:

- активный Project Sources checkpoint: `0be9044e35ec5be670c8f9bb33de020491214423` (`docs: close E1 and prepare E2`);
- активный набор из пяти файлов (сгенерирован 2026-08-29T01:22:19+03:00):
  - `Avilona_turfirma_source_0be9044e_20260829-012223.zip` (SHA256 `62F714B92A45147553EB325988A015E9F6F280562608BB6CED61258879356E80`, 1041510 bytes);
  - `Avilona_Handoff_2026-08-28_0be9044e.md`;
  - `Avilona_Roadmap_2026-08-28_0be9044e.md`;
  - `Avilona_New_Chat_Prompt_2026-08-28_0be9044e.md`;
  - `Avilona_Project_Sources_2026-08-28_0be9044e.csv`;
- с этого checkpoint репозиторий продвинулся через E2-A1…E2-A5 (HEAD `ad6e9c23`) — набор **STALE**;
- Project Sources refresh **обязателен** после review / commit / push этого docs-only slice и появления чистого нового documentation HEAD;
- refresh должен генерироваться из **нового docs HEAD**, а не из application HEAD `ad6e9c23` до docs commit; будущий docs HEAD пока неизвестен и не выдумывается;
- активный набор из пяти файлов **не трогать** до верификации нового сгенерированного пакета;
- механизм — существующий guarded PowerShell refresh (per-checkpoint wrapper + shared `Create-Avilona-ChatGPT-SourceArchive.ps1`), запускается пользователем из чистого pushed HEAD; publish с backup предыдущего набора в `archive/`.

Refresh выполняется отдельным guarded шагом ПОСЛЕ этого docs commit. Пакет сейчас НЕ генерируется.

## 9. Endgame roadmap — E1…E6

Канонический roadmap после Stage 13 (детали — `docs/roadmap.md`):

| Фаза | Название | Статус |
|---|---|---|
| E1 | Comprehensive Audit | ✅ TECHNICALLY CLOSED (§5A) |
| **E2** | **Public UX / UI / Design Redesign** | **🔄 IN PROGRESS** — E2-A1…E2-A5 завершены (§9B) |
| E3 | Tourist / Manager / Admin Cabinet UX/UI Redesign | PENDING |
| E4 | Post-redesign stabilization / regression / browser-device QA | PENDING |
| E5 | Final Tour Search Solution | PENDING (намеренно один из последних крупных блоков) |
| E6 | Production Deployment / Operations Validation | PENDING |

Текущая рабочая фаза — **E2** (в процессе). Следующий E2 slice ещё не выбран.

### 9.0 E2 — стартовые принципы

E2 — это не просто косметическая перекраска. Публичный сайт рассматривается как
цельный современный туристический веб-сайт: information architecture,
header/navigation, иерархия главной страницы, типографика, spacing, цветовая
система, buttons/forms, cards, responsive behavior, мобильная навигация,
визуальная консистентность, destinations/countries, страницы компании,
сотрудники, awards, articles/news/special offers/reviews, contacts,
empty/error states, consent UI, accessibility, trust/credibility, conversion
paths, CTA consistency, image treatment, desktop/tablet/mobile.

E2 началась с READ-ONLY visual/UX inventory и design-system proposal, затем идёт
согласованными slices. Механику финального поиска туров в E2 не переделывать:
текущий tour widget визуально размещается как временный компонент, но его
финальный provider/архитектура — это E5.

### 9B. E2 — выполненные slices (E2-A1…E2-A5)

E2 **в процессе**. Ниже — завершённые slices. E2 глобально НЕ завершён; следующий
E2-A номер и slice ещё не выбраны. Оставшиеся публичные маршруты (вероятные
кандидаты — Reviews, Contacts, прочие helper/content/error/empty surfaces) будут
проверены read-only перед выбором следующего coherent slice.

Текущий authoritative application HEAD: `ad6e9c23986d479cbbbf6f511e96bc139ae26576`.
Текущий full baseline: **1006 tests / 7037 assertions** (было 1001 / 7013; E2-A5
добавил 5 regression-тестов).

#### E2-A1 — header / первый экран главной
✅ COMPLETE — `43a073e676d441021445f73f38733fa70a0e1463` (`feat: redesign public header and home first screen`)

- единый публичный header/навигация; route-derived active states; accessible `aria-current`;
- редизайн hero главной; один H1; улучшенная CTA-иерархия;
- временный tour-search widget визуально интегрирован — архитектура tour-search намеренно НЕ переделана (E5). Текущее решение поиска туров не финальное.

#### E2-A2 — home below-the-fold / shared public shell
✅ COMPLETE — `72202ab7d35b064ab4b0c66147bfff21357e5343` (`feat: redesign home and shared public shell`), `eaa2093f5f406e7a5fbd73c6fe3a1897802852a` (`refactor: unify public manager interactions`)

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
✅ COMPLETE — `ad6e9c23986d479cbbbf6f511e96bc139ae26576` (`feat: redesign public News + Articles editorial experience (E2-A5-I1)`) — текущий authoritative application HEAD

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

### 9.2 Public UX/UI/design pass (E2)

Отдельно анализировать и при необходимости перерабатывать:

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

Не менять всё механически: сначала audit/findings/priorities, затем approved redesign slices.

### 9.3 Personal cabinet UX/UI/design pass (E3)

Глубоко пройти tourist / manager / admin cabinets:

- information architecture;
- sidebar/header/navigation;
- dashboard priorities;
- cards/tables/forms/actions;
- icons;
- status colors;
- spacing, typography, hierarchy;
- mobile navigation and responsive states;
- removal/merging/repositioning of awkward elements;
- consistency between roles without стирания role-specific UX.

### 9.4 Post-redesign validation (E4)

После redesign — full regression + cross-device/browser QA + final production-readiness again.

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

## 10. Поиск туров — самый последний продуктовый этап (E5)

Текущий widget на homepage и `/tours` — временная заглушка. В E2 (начиная с E2-A1)
widget только визуально интегрируется в новые surfaces; его механика/архитектура
не финальны и не решены. E2 НЕ решил финальный поиск туров.

Известные local/UX факты на момент Stage 13 closure (историческое):

- canonical local таблица `tours` содержит 0 строк;
- temporary `/tours` UI всё ещё содержит старую/статичную презентацию, включая placeholder «22 окт - 26 окт 25»;
- архитектура search/widget/aggregator намеренно отложена.

E5 остаётся выделенной финальной фазой поиска туров и должен сравнить готовые
widgets/aggregators, прямые API туроператоров и собственный aggregation/search
layer — с учётом стоимости, стабильности, contracts/legal terms, mobile UX,
интеграции с cabinet/booking/CRM, caching, rate limits и стоимости поддержки.

Не выбирать решение до завершения основной стабилизации и design audit.

Последним крупным product block сравнить:

1. готовые widgets/aggregators;
2. API/integration options туроператоров;
3. собственный search/aggregation layer.

Оценивать не только цену/доступность, но и:

- стабильность data source;
- legal/contract terms;
- UX and mobile integration;
- booking/cabinet/CRM integration;
- caching/rate limits;
- operational maintenance cost.

Реальные external provider calls — только по отдельному guarded plan.

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
