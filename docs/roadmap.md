# Avilona_turfirma — Roadmap

Актуализировано: **2026-09-05**

## Current state

- Branch: `db-rebuild-stage3`
- **Current authoritative application HEAD: `35f91b9e270cf68654877d42fc8b0d0d59d12458`**
- Subject: `feat: finalize public visual system palette (E2-A7)`
- Direct parent of current HEAD: `baf7487b5fe03c978cbc101ad2b7e6c72481c610` (`feat: complete public informational pages redesign (E2-A6-I2)`)
- Documentation checkpoint after E2-A5: `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa` (`docs: checkpoint E2 through E2-A5`) — previous docs-only commit, NOT the current HEAD
- Historical E1 closure application commit: `08d0626311234faa06dedf2828cb878805241990` (`fix: close final public audit gaps`) — NOT the current HEAD
- Previous functional HEAD (Stage 13): `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` (`fix: remove obsolete guest booking flow`)
- Stage 0–13: ✅ CLOSED
- E1 Comprehensive Audit: ✅ TECHNICALLY CLOSED
- **E2 — Public UX / UI / Design Redesign — ✅ COMPLETE / CLOSED at application level** (E2-A1…E2-A7)
- **Next major phase: E3 — Cabinet UX/UI/Design Modernization**
- Full verified baseline: **1051 tests / 7180 assertions**, exit 0 (PHPUnit 11.5.56, PHP 8.3.32, Laravel 12.65.0, SQLite `:memory:`)
  - after E2-A5 the baseline was **1006 tests / 7037 assertions**; historical E1-closure baseline was **1001 tests / 7013 assertions**; E2-A6 and E2-A7 added public E2-redesign regression tests (Reviews/Contacts E2, Reviews pagination = 6, Travel Dictionary E2, legal pages E2, public 404 E2) — expected, not a regression
- Single PHPUnit deprecation = pre-existing XML schema deprecation, not a code failure
- Browser QA: PASS for the final E2 public visual system at this stage; the user explicitly accepted the E2-A7 palette / visual system for this stage. The finished site will later be shown to company management; any resulting design feedback is a later polish/follow-up, not an open blocker for E2 closure.
- The new documentation closure HEAD created after this task will be newer than the application checkpoint `35f91b9e…`; that docs HEAD is decided by Git and must NOT be invented or pre-hardcoded.
- Project Sources: the active external set was generated from `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa` (after E2-A5) and is now **STALE** — E2-A6 and E2-A7 completed after it. Refresh is required after this docs-only E2-closure slice is reviewed, committed, pushed and a clean new documentation HEAD exists. The future docs HEAD and the future source-archive filename are not known and must not be invented.

## Completed stages

### Recovery / Stage 0–6
✅ COMPLETE

Canonical recovery, booking lifecycle, protected chat/documents and foundational role flows closed and preserved.

### Stage 7 — role precedence / authorization
✅ COMPLETE

Canonical effective precedence:

```text
admin > assigned manager > owner-facing tourist
```

Mixed-role booking/message/document behavior covered by regression tests.

### Stage 8 — local/public tour catalog
✅ COMPLETE

Current catalog/search behavior is local/read-only. This completion does **not** mean the current tour-search widget is the final commercial aggregation solution.

### Stage 9 — public content / CMS / RSS
✅ COMPLETE

### Stage 10 — notifications / cabinet
✅ COMPLETE

### Stage 11 — security / reliability / performance
✅ COMPLETE

### Stage 12 — dependency modernization / repository hygiene
✅ COMPLETE

Laravel 12.65.0, Vite 7, vendor/node_modules not tracked, prior security/dependency checks closed.

## Stage 13 — Production readiness / public surface
✅ **COMPLETE** — repository/local technical closure at `dba20e2c6e2e66b6f69f33710b2626b3fe181e31`

### Completed before review flow

- Vite generated-build cleanup;
- browser runtime fixes;
- mobile/tablet responsive fixes;
- public development-notice cleanup;
- cookie consent + analytics gating;
- company details alignment;
- separate personal-data consent on Home/Contacts.

### Reviews track

#### Submission + privacy foundation
✅ COMPLETE

- public UGC escaping;
- moderation notice and no auto-publish;
- public identity/content scope cleanup;
- review-specific private evidence foundation;
- consent legal pages;
- three confirmations;
- private full name/email evidence;
- no IP/UA/device/session evidence;
- subject/title removed from future public review flow;
- validation UX.

#### C4A — moderation state
✅ COMPLETE — `2a48951c…`

- `is_moderator_edited`;
- `moderator_edited_at`;
- `publication_conditions_satisfied_at`.

#### C4B1 — server-side moderation rules
✅ COMPLETE — `ebbc60e4…`

- author name immutable;
- conditions publication gate;
- content edit invalidates stale satisfaction;
- sticky moderator-edit marker;
- transactional write behavior;
- legacy review compatibility.

#### C4B2 — Admin/Manager moderation UI
✅ COMPLETE — `149bce99…`

- author display-only;
- conditions visible and escaped;
- transient confirmation checkbox never prechecked/restored;
- validation error near control;
- private consent identity hidden;
- Admin/Manager parity;
- browser QA PASS.

#### C4C — public moderator-edit disclosure
✅ COMPLETE — `15bd01a2…`

Exact public wording when `is_moderator_edited=true`:

```text
Текст отзыва отредактирован модератором без изменения общего смысла.
```

Public surfaces:

- `/reviews`;
- homepage teaser.

Privacy:

- no public `moderator_edited_at`;
- no private consent/evidence;
- no moderator identity.

Evidence:

- focused 13 / 81;
- review regression 113 / 709;
- full 839 / 3718;
- browser QA isolation PASS.

## Stage 13 — closed queue

### S13-R1 — withdrawn consent publication guard/workflow
✅ COMPLETE — `e2a7ce0637f146b77c1ce1fcbc13008c18a50fb2`, `ac11dc3237272999dbb1a31d1371b384d5971e97`

`withdrawn_at` enforced on the public path (fails safe even if stale `is_published=true`); Admin/Manager cannot publish/re-publish withdrawn reviews; explicit unpublish still possible; dedicated operator workflow records an already-received/verified withdrawal request; first timestamp preserved on repeated action; no public self-service withdrawal introduced.

### S13-R2 — Manager review cache parity relevance check
⬜ TODO / READ-ONLY FIRST

Historical finding: Admin and Manager had asymmetric legacy review cache clearing.

Current regression says public review pages do not use old cache layers and role publish/unpublish is immediately visible. First re-establish whether any live defect remains. If no live path depends on it, prefer removal/cleanup/no-op decision over unnecessary parity code. Not resolved by the Stage 13 docs closure or by E2 — kept explicitly open. Not reopened as an E1/E2 defect; **carried into E3** (cabinet pass) as a READ-ONLY relevance check unless a concrete live defect appears earlier. Do not automatically implement parity.

### S13-R3 — public registration consent/policy
✅ COMPLETE — `1cef8d2642b3785e3ab759d5eedbc1ddd65b9cf9`, `a3824554033f92c0ef8723c6ab1cdc2a5c6eaa0f`

Two separate required confirmations (User Agreement; registration personal-data processing consent), dedicated consent page, `UserRegistrationConsent` one-to-one evidence with server-side timestamps/SHA256 document versions, atomic user+role+evidence creation. User Agreement extended with §9 for registration/account use.

### S13-R4 — guest booking contract
✅ COMPLETE — `dba20e2c6e2e66b6f69f33710b2626b3fe181e31`

Anonymous booking confirmed unsupported; dead anonymous `StoreController` and unreachable `@guest` form/layout/modal remnants removed; `/tours` CTA uses canonical `bookings.create` with `tour_id` prefill; unauthenticated create/store route boundary covered by tests; no booking schema/migration change.

### S13-R5 — final local production-readiness
✅ COMPLETE

Full PHPUnit (917 / 4012), Stage 13 migration/schema inventory (4 migrations, all Ran, 0 pending on canonical local MySQL), code/schema/legal/test reconciliation PASS. Password visibility UX (login + independent registration/confirmation toggles) shipped as part of this closure pass — `7818c54ee3315e34f26fc8c1e9796b9b6417e79c`.

### S13-R6 — Stage 13 closure docs
✅ COMPLETE (this checkpoint)

Documentation closure for Stage 13 was recorded in `docs/README.md` and this file at that time. This has since been superseded by the E1 closure docs and the E2-closure docs (this update). Project Sources refresh remains a separate required follow-up (see Current state) generated from the newest docs closure HEAD.

## Endgame after Stage 13 — E1…E6

### E1 — comprehensive project audit
✅ TECHNICALLY CLOSED — application commit `08d0626311234faa06dedf2828cb878805241990` (`fix: close final public audit gaps`)

Baseline at closure: **1001 tests / 7013 assertions**.

Closed slices (details — `docs/README.md` §5A):

- **E1-A1** — canonical social image host; duplicate public nav IDs; Google Maps consent gating.
- **E1-A2** — employee `tel:` href; current payment/refund copy; transactional email public company details; stale public profile/dashboard disposition.
- **E1-A3** — sitemap; robots.txt; regression coverage.
- **E1-A4** — page-specific dynamic detail OG/Twitter title/description.
- **E1-A5 / RSS** — external RSS News HTML sanitisation at ingestion + render-time for historical rows; safe URL-scheme handling; RSS security regressions.
- **E1-RPD** — News listing decode-then-raw XSS; public inner-cache TTLs corrected to one hour; Destination/Specials nullable image robustness; Destination null-slug rendering.
- **E1-FINAL** — About country links use slugs; Article rich HTML sanitised on Admin/Manager write + re-sanitised for historical rows; Article listing excerpt plain/escaped; About cache TTL; hardcoded 55/12 SEO claims removed; reload-captcha removed from response cache; Awards public regression coverage; Cyrillic `Str::slug` audit claim DISPROVEN by runtime (`«Путешествие по Азии» -> putesestvie-po-azii`).

Intentionally deferred (NOT defects — do not "fix" accidentally):

- `PENDING_BUSINESS_DECISION_OPENING_HOURS` — home 10:00–20:00 weekdays vs contacts 11:00–20:00 weekdays by appointment plus current weekend wording; no authoritative decision; **still unresolved** after E2; must be resolved before final production release (E6).
- Per-page `og:type=article` refinement — ✅ resolved in E2-A5 (News detail + Article detail declare `og:type=article`; `layouts/main` now `@yield('og_type', 'website')`).
- Temporary public tour-search solution stays until E5 — still temporary; E2 (incl. E2-A7) only made the surrounding UI visually coherent; the `/tours` legacy widget was deliberately not recolored in E2-A7.
- News RSS scheduling — verify real production cron in E6; do not add Laravel scheduling blindly. E2-A5 added only HTML autodiscovery on the News listing; production scheduling is NOT verified.
- Future-risk raw HTML (`Best_offer` / `OurClient` / `Countries_image` / `Destination_image`) — no current untrusted web write path; do not reopen unless a CMS/write path is added.

### E2 — public-site UX/UI/design modernization
✅ **COMPLETE / CLOSED at application level** — E2-A1…E2-A7.

Not merely a cosmetic recolor — the public site was treated as a coherent modern tourism website: information architecture, header/navigation, home-page hierarchy, typography, spacing, colour system, buttons/forms, cards, responsive behaviour, mobile navigation, visual consistency, destinations/countries, company pages, employees, awards, articles/news/special offers/reviews, contacts, empty/error states, consent UI, accessibility, trust/credibility, conversion paths, CTA consistency, image treatment, desktop/tablet/mobile. E2-A7 landed the final public visual system and a single authoritative E2 token system for the public shell.

The final tour-search mechanics were deliberately NOT redesigned; the current widget is visually accommodated as a temporary component, its final provider/architecture belongs to E5.

The finished public site will later be shown to company management; any resulting design feedback is a later polish/follow-up, not an open blocker for E2 closure.

#### E2-A1 — public header / home first screen
✅ COMPLETE — `43a073e676d441021445f73f38733fa70a0e1463` (`feat: redesign public header and home first screen`)

- unified public header/navigation; route-derived active states; accessible `aria-current`;
- home hero redesign; one H1; improved CTA hierarchy;
- temporary tour-search widget visually integrated — tour-search architecture deliberately NOT redesigned (E5).

#### E2-A2 — home below-the-fold / shared public shell
✅ COMPLETE — `72202ab7d35b064ab4b0c66147bfff21357e5343` (`feat: redesign home and shared public shell`), `eaa2093f5f406e7a5fbd73c6fe3a1897802852a5` (`refactor: unify public manager interactions`)

- shared public shell; redesigned home below-the-fold; footer cleanup;
- header/footer phone interaction no longer causes page jump;
- broken Yandex informer removed; map placeholder cleaned;
- scroll-to-top control keyboard accessible;
- no page-level horizontal overflow in verified QA;
- shared manager-contact interaction layer.
- Not a production-deployment claim.

#### E2-A3 — public travel discovery
✅ COMPLETE — `5e22e4b78ed6e8610d4c2b7f11043ff9e1336806` (`feat: redesign public travel discovery`)

- Countries; Destinations; Specials / public travel discovery surfaces;
- included populated / browser QA.
- Historical SQL used for isolated visual/reference QA only (`C:\Users\nikita\Downloads\u0588341_turfirma.sql`, SHA256 `A721C984DE0F2B366598A7B5D92E6B5F6C7D629692C2C34E2EED52BD85B3109A`). Legacy dump content is historical QA/reference only — NOT current business truth; legacy users/personal data must never be imported into canonical or production data.

#### E2-A4 — company / trust surfaces
✅ COMPLETE — `94aedad09468d50be45e8f11c4be0a8c41dbb474` (`feat: redesign company trust pages`)

- **About Company:** retired legacy sidebar/grid; one H1; E2 breadcrumbs/hero/sections; corrected wide-desktop layout after browser QA; three existing PDFs preserved (NOT declared legally/currently up to date; displayed/repository date remains **22 May 2024**); payment/refund canonical wording preserved; public email `avilonatur@bk.ru` preserved; public wording moved from a general "courier delivery" service to `«Передача документов по договорённости»`; generic installments/credit offering remains; country slug contracts preserved.
- **Employees:** responsive E2 employee cards; contact links remain direct personal contacts; tel/mailto/WhatsApp/VK behaviour preserved; image placeholders supported; personal contacts NOT replaced with the generic manager modal.
- **Awards:** responsive award grid; native button modal trigger; keyboard-accessible Bootstrap modal; portrait/landscape media handling; null-image-safe; no invented dates, issuers, rankings or provenance.
- Populated QA: 10 employees, 22 awards. Final E2-A4 browser QA passed.

#### E2-A5 — News + Articles editorial experience
✅ COMPLETE — `ad6e9c23986d479cbbbf6f511e96bc139ae26576` (`feat: redesign public News + Articles editorial experience (E2-A5-I1)`). Docs checkpoint after E2-A5: `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa`.

- Scope: News listing/detail, Articles listing/detail, shared `includes/e2-editorial-card` partial, editorial CSS, deterministic News pagination ordering, safe public rendering boundary for News source links, regression tests.
- **News listing:** legacy sidebar removed; E2 breadcrumb + page hero; one H1; responsive 1/2/3 card grid; title links instead of repeated "Подробнее"; `pub_date` shown on cards; date filter preserved; old AJAX pagination removed → normal server-side pagination; RSS HTML autodiscovery `<link>` added; `#news-container`, `.card-text`, escaped first-paragraph excerpt contract retained.
- **News detail:** broad centred editorial column (~84ch ≥992px, ~88ch ≥1200px — intentionally not a narrow 68ch column); left-aligned H1; real `pub_date` near heading; `.news-content` retained; `NewsHtmlSanitizer` render boundary retained; safe "Источник новости" action (only explicit http/https `News.link` values render; `target=_blank` + `rel="noopener noreferrer"`); `og:type=article`; back-to-news; CTA.
  - Browser-QA follow-up (in HEAD `ad6e9c23`): the first implementation rendered `News.image` as a standalone top image while the same image was already embedded in the RSS body → duplicate. The standalone News detail media block was removed at the user's explicit request; the body image remains. News listings and Articles unchanged.
- **Article listing:** E2 editorial cards; no fabricated dates; `.card-text` preserved; empty-state substring `«Статьи пока не добавлены»` retained; server pagination.
- **Article detail:** broad centred editorial column; `Article.image` remains as one standalone hero (separate CMS media, did not duplicate the tested article body); `.article-content` retained; `NewsHtmlSanitizer` boundary retained; no article publication date invented; `og:type=article`; back-to-articles; CTA.
- **Shared layout (surgical):** `resources/views/layouts/main.blade.php` — hard-coded `og:type` website → `@yield('og_type', 'website')`; added `@yield('head_extra')`. Not a general shared-layout redesign.
- **HelpfulNewsController:** primary ordering `pub_date DESC` + deterministic secondary `id DESC`. No cache-policy change, no page-size change, no recent/related News query introduced.

#### E2-A6-I1 — Reviews + Contacts
✅ COMPLETE — `1de95ad88092e2fad482949a9cd19fb80682d674` (`feat: redesign public reviews and contacts experience (E2-A6-I1)`)

- **Reviews:** modern responsive review cards; neutral avatar fallback where no real image exists; 2-column compact desktop review grid, one column on mobile; long-review teaser / expand behaviour; moderator-edit disclosure preserved; Stage 13 moderation/consent/privacy contracts preserved; public escaped output; modern review form; empty state; pagination later updated to 6 in I2.
- **Contacts:** modern E2 page layout; feedback-form UX; optional "Тема" field bounded `nullable|string|max:150` (`SendContactRequest`, `SendHomeRequest`); current public physical address; historical/legal registered address kept distinct where required; requisites recomposed into balanced desktop columns; existing PDFs preserved; "Как нас найти" treatment; POST throttling `throttle:8,1` (8 requests/minute) on the approved `contact.send` / `home.send` routes; internal form recipient stays `straus97@mail.ru` (`SendContactController` / `SendHomeController`); public email stays `avilonatur@bk.ru`. The two email roles must not be conflated.

#### E2-A6-I2 — Informational / Legal / 404
✅ COMPLETE — `baf7487b5fe03c978cbc101ad2b7e6c72481c610` (`feat: complete public informational pages redesign (E2-A6-I2)`) — direct parent of the current HEAD

- **Travel Dictionary:** legacy sidebar removed; exactly one rendered H1; E2 breadcrumbs/hero; native `details/summary` disclosure; content preserved; desktop multi-column Terms treatment; responsive mobile behaviour.
- **Five legal pages** (`cookies`, `personal-data-consent`, `registration-personal-data-consent`, `review-personal-data-consent`, `review-publication-consent`): E2 presentation; legal copy preserved (no modernization/rewrite); breadcrumbs / H1 / readability improvements.
- **404:** existing public 404 redesigned into shared E2 presentation; actual HTTP 404 behaviour preserved; no 403/419/429/500/503 pages were added — those remain an E4 resilience/stabilization consideration, not an E2 omission.
- **Reviews:** pagination changed from 4 to 6 per page (`Review/IndexController::paginate(6)`); publication ordering/filter/withdrawal semantics preserved.
- **Shared desktop width:** generic E2 informational prose/hero/title no longer uses an unnecessarily narrow desktop character-width cap; on desktop it uses the available parent/container width; mobile behaviour unchanged; user explicitly approved this direction.

#### E2-A7 — final public visual system
✅ COMPLETE — `35f91b9e270cf68654877d42fc8b0d0d59d12458` (`feat: finalize public visual system palette (E2-A7)`) — current authoritative application HEAD

- User rejected the old dominant cream/peach/warm surfaces, warm tan borders and brown/orange primary CTA system.
- Final accepted current-stage direction: white main-page base; cool light blue-gray alternate surfaces; cool neutral borders; sea-blue / blue primary actions; darker blue hover/strong states; orange retained only as a restrained decorative accent; consistent E2 button/form/alert/header/footer treatment.
- Legacy `body { background: snow }` bleed-through from `style_min.css` removed; the shared E2 token system is authoritative for the public shell; header auth actions normalized into E2 presentation; Home active search/widget colours aligned to E2 tokens without changing search mechanics; Reviews validation presentation normalized; public special-offers pagination aligned.
- `/tours` legacy widget deliberately NOT recolored/redesigned — final tour-search architecture remains E5.
- During browser QA an accidental CSS comment terminator temporarily invalidated the E2 `:root` token block; it was corrected before commit, full tests passed and the final committed checkpoint is healthy. Final browser QA and recovery passed.
- Baseline after E2-A6…A7: **1051 tests / 7180 assertions**, exit 0.

#### E2 closure
E2 is complete at application level. The public shell and the completed public
E2 pages/surfaces (header/footer/shell, home, travel discovery, company/trust,
news/articles, reviews, contacts, informational/legal/404) are unified under the
E2 presentation and the final E2-A7 visual system. **Explicitly excluded:** the
temporary `/tours` search/widget block (`resources/views/tours/index.blade.php`)
was deliberately left out of E2-A7 and remains temporary until E5. Remaining
public concerns (extra system error surfaces 403/419/429/500/503, final
tour-search mechanics) are carried by E4 and E5 respectively. Design feedback
from the later management review is a polish/follow-up, not an E2 blocker.

### E3 — cabinet UX/UI/design modernization
⬜ **NEXT MAJOR PHASE**

Separate deep pass for tourist/manager/admin cabinets:

- information architecture;
- navigation/sidebars/headers;
- dashboard priorities;
- action placement;
- tables/forms/cards;
- status presentation;
- icons/statuses/color system;
- visual hierarchy and density;
- mobile/tablet/desktop behavior;
- visual consistency with the completed public E2 system where appropriate.

Design decisions must follow findings, not blanket restyling.

**Carried item: S13-R2 — Manager review cache parity relevance check.** Begins
**READ-ONLY** in E3. Historical concern: Admin and Manager once had asymmetric
legacy review cache clearing. Current evidence says old public review cache
layers may no longer make this relevant. First re-establish whether a live
defect still exists; if no live path depends on it, prefer removal/cleanup/no-op
over unnecessary parity code. Do not automatically implement parity.

### E4 — post-redesign stabilization / regression / browser-device / resilience
⬜ PLANNED

- full regression;
- browser/device QA;
- accessibility;
- remaining visual inconsistencies;
- existing/missing system error behaviour including 403/419/429/500/503 where appropriate (404 already handled in E2-A6-I2);
- production-readiness repeat.

### E5 — TOUR SEARCH / AGGREGATION — FINAL PRODUCT BLOCK
⬜ DELIBERATELY LAST

The current homepage and `/tours` search solution is **temporary**. E2 only made
the surrounding UI visually coherent; it did not touch search mechanics and did
not mark tour search complete. Final search/provider/aggregation architecture is
this stage.

Compare:

- ready-made widget/aggregator;
- tour-operator/API integrations;
- own aggregation/search implementation.

Decision criteria: cost; reliability; contractual/legal terms; UX/mobile; booking/cabinet/CRM integration; caching/rate limits; maintenance burden.

### E6 — final release / deploy / production smoke
⬜ PLANNED

After the final selected tour-search solution and stabilization:

- guarded production deploy;
- migrations only through a dedicated approved plan;
- production smoke checks;
- production RSS scheduling / cron verification;
- final handoff.

An intermediate deployment for validation may be planned separately, but it does not replace the final post-redesign/post-tour-search release.

## Guardrails

- One semantic slice at a time.
- No dependency update mixed with functional work.
- No canonical DB writes without guarded plan.
- PHPUnit only PHP 8.3.32 + SQLite `:memory:`.
- No real provider integrations without explicit operational approval.
- Current Project Sources must correspond to a clean pushed documentation HEAD.
