# Avilona_turfirma — Roadmap

Актуализировано: **2026-10-01**

## Current state

- Branch: `db-rebuild-stage3`
- **Current authoritative application HEAD: `c56e330685ffa8953b22c803d6dce5334c69e81b`**
- Subject: `fix: complete final tour search polish (E5-A4)`
- Direct parent of current application HEAD: `00ea2f0440f0a25b6ecd161aadedb804ce9e8ac2` (`fix: remove plaintext staff client credentials (E5-A3.1)`)
- Documentation checkpoint for this application HEAD: **does not exist yet** — will be created by a separate docs-only commit on top of `c56e3306` (this file + `docs/README.md` + root `README.md`); that future HEAD is decided by Git and is not known/invented here
- Documentation checkpoint after E4 closure / entering E5 (previous docs-only commit; base of the now-**HISTORICAL** Project Sources set of 2026-09-23): `a1d72a4034b1d76b35e0033f4c1e2a69121ee06b` (`docs: close E4 and prepare E5`) — predates ALL E5 implementation, NOT the current HEAD, NOT authoritative over the current repository
- Application HEAD at E4 closure: `55a9fcaf6371ef0c749bb668c9b27783d1df358c` (`fix: close final E4 application polish`) — NOT the current HEAD
- Documentation checkpoint after E3-A6 / entering E4 (historical): `c89e923f966f5aaa8bb972e2d6944018f8dce4a8` (`docs: close E3-A6 and advance to E4`) — NOT the current HEAD
- Application HEAD at E3-A6 closure: `ed550df8989b44e7305bdce6b6f5f063b82a2616` (`fix: polish cross-role chat switching (E3-A6-B)`) — NOT the current HEAD
- Documentation checkpoint after E3-A5 (historical): `20cde21dda2c38682c796214fbb2401e3f1f7804` (`docs: close E3-A5 and refresh roadmap`) — NOT the current HEAD
- Application HEAD at E3-A5 closure: `9fee7dfb990c7a6c18fc9dcf9205e3db3dca24e6` (`feat: modernize admin cabinet (E3-A5)`) — NOT the current HEAD
- Documentation checkpoint after E2 closure (historical): `886bde9813a088d56d7db1e6b963f6f1d05ab4b2` (`docs: close E2 public redesign`) — NOT the current HEAD
- Documentation checkpoint after E2-A5 (historical): `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa` (`docs: checkpoint E2 through E2-A5`) — NOT the current HEAD
- Application HEAD at E2 closure (E2-A7): `35f91b9e270cf68654877d42fc8b0d0d59d12458` (`feat: finalize public visual system palette (E2-A7)`) — NOT the current HEAD
- Historical E1 closure application commit: `08d0626311234faa06dedf2828cb878805241990` (`fix: close final public audit gaps`) — NOT the current HEAD
- Previous functional HEAD (Stage 13): `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` (`fix: remove obsolete guest booking flow`)
- Stage 0–13: ✅ CLOSED
- E1 Comprehensive Audit: ✅ TECHNICALLY CLOSED
- E2 — Public UX / UI / Design Redesign — ✅ **COMPLETE / CLOSED at application level** (E2-A1…E2-A7)
- **E3 — Cabinet UX/UI/Design Modernization — ✅ CLOSED at application level** (E3-A1…E3-A5 + E3-A6-A/B point-polish)
- S13-R2 (Manager review cache parity relevance check) — ✅ **CLOSED** as part of E3-A5
- **E4 — Post-redesign stabilization / resilience QA — ✅ CLOSED** (E4-A…E4-E1; final application checkpoint `55a9fcaf`)
- **E5 — Final Tour Search / Aggregation Product Block — ✅ CLOSED at application level** (E5-A1 `9005457a`, E5-A2A `c0e16ae3`, E5-A2A.1 `d59d211e`, E5-A2B `0399d21f`, E5-A3 `137f109b`, E5-A3.1 `00ea2f04`, E5-A4 `c56e3306`; all pushed). See "E5 — tour search" below.
- **APPLICATION CLOSED ≠ PRODUCTION DEPLOYED.** E5 being closed does NOT mean production-ready. No production deployment/migration has happened; E5 migrations (at least `2026_10_01_000000…` and `2026_10_02_000000…`) are NOT applied to canonical MySQL `turfirma_rebuild_v4`; production Tourvisor secrets/webhook are not configured; privacy/legal provider facts are unresolved (see E5/E6 below).
- **NEXT: Screenshot Audit Pack + independent full audit (Astra / ChatGPT Work) BEFORE E6.** Then approved audit fixes (if any), then E6. E5 is no longer NEXT.
- Full verified baseline: **1393 tests / 9759 assertions**, 0 failures, 0 errors (PHPUnit 11.5.56, PHP 8.3.32, Laravel 12.65.0, SQLite `:memory:`) — final E5 baseline, re-verified in the E5 documentation-closure slice (`php -d xdebug.mode=off -d memory_limit=2048M vendor/bin/phpunit`)
  - history: E5-A3 closure 1383 / 9682; E4 closure 1286 / 8628; E4 entry 1242 / 8056; E3-A5 closure 1233 / 8023; E2 closure 1051 / 7180; E1 closure 1001 / 7013
  - 1 PHPUnit deprecation: the PHPUnit test-runner notice "Your XML configuration validates against a deprecated schema" (`phpunit.xml`) — verified in current output; not a functional failure
  - canonical MySQL is never touched by tests
- Infrastructure: current production hosting remains **REG.RU**. Future Timeweb Cloud server `web-prod-spb-01` (2 vCPU / 4 GB RAM / 50 GB NVMe / Saint Petersburg) is **PREORDERED / waiting for provider capacity — NOT production**. No DNS change; domains remain with REG.RU. (No private key paths/secrets are recorded in the repository.)
- Project Sources: the external set of 2026-09-23 (`a1d72a40`) is **HISTORICAL ONLY** (predates E5). A fresh set is generated from the new clean pushed documentation HEAD after this docs-only commit. The current repository (tree, `git log`, tests, migrations, docs) is authoritative.
- E5 private evidence (never in Project Sources): `C:\Avilona_private\E5\` (E5-A1_Browser_PoC, E5-A2B_Live_PoC, E5-A3_Browser_QA, E5-A3.1_Browser_QA, E5-A4_Browser_QA). E4 QA evidence preserved: `C:\Avilona_private\E4\…`.
- Opening hours remain **`PENDING_BUSINESS_DECISION_OPENING_HOURS`** (home 10:00–20:00 weekdays vs contacts 11:00–20:00 by appointment) — unresolved, must be decided before final production release (E6).

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
✅ **CLOSED** (resolved as part of E3-A5)

Historical finding: Admin and Manager had asymmetric legacy review cache clearing.

Carried READ-ONLY through E1/E2 into E3 per its original plan; resolved during
E3-A5. Re-verification found: `AdminController::updateReview()` does call
`Cache::forget('home_reviews')` and `Cache::forget('reviews_page_'.$page)` (the
historical source of the asymmetry — `ManagerController::updateReview()` has no
equivalent call), but no controller anywhere in the app ever calls
`Cache::remember('home_reviews', ...)` or `Cache::remember('reviews_page_...',
...)` — those keys are never populated, and public Reviews/Home read reviews
directly from the database, uncached. The `Cache::forget()` calls in Admin are
therefore clearing entries that never exist (a no-op), not a working live cache
layer. Public review changes are immediately visible regardless of which role
saved the edit. **Conclusion: no live defect, no parity implementation
necessary.** This is a completed relevance check / obsolete historical concern,
not an outstanding defect — do not introduce new review-cache code merely for
symmetry. If a real review cache layer is ever introduced later, this check
must be re-run.

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
✅ COMPLETE (historical checkpoint)

Documentation closure for Stage 13 was recorded in `docs/README.md` and this file at that time. This has since been superseded by the E1 closure docs, the E2-closure docs, the E3-A5-closure docs, and now the E3-A6-closure docs (this update). Project Sources refresh remains a separate required follow-up (see Current state) generated from the newest docs closure HEAD.

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
- *(Historical E2-time note; ✅ resolved in E5 — replaced by the Tourvisor module, see E5 below.)* Temporary public tour-search solution stays until E5 — still temporary; E2 (incl. E2-A7) only made the surrounding UI visually coherent; the `/tours` legacy widget was deliberately not recolored in E2-A7.
- News RSS scheduling — verify real production cron in E6; do not add Laravel scheduling blindly. E2-A5 added only HTML autodiscovery on the News listing; production scheduling is NOT verified.
- Future-risk raw HTML (`Best_offer` / `OurClient` / `Countries_image` / `Destination_image`) — no current untrusted web write path; do not reopen unless a CMS/write path is added.

### E2 — public-site UX/UI/design modernization
✅ **COMPLETE / CLOSED at application level** — E2-A1…E2-A7.

Not merely a cosmetic recolor — the public site was treated as a coherent modern tourism website: information architecture, header/navigation, home-page hierarchy, typography, spacing, colour system, buttons/forms, cards, responsive behaviour, mobile navigation, visual consistency, destinations/countries, company pages, employees, awards, articles/news/special offers/reviews, contacts, empty/error states, consent UI, accessibility, trust/credibility, conversion paths, CTA consistency, image treatment, desktop/tablet/mobile. E2-A7 landed the final public visual system and a single authoritative E2 token system for the public shell.

The final tour-search mechanics were deliberately NOT redesigned; the current widget is visually accommodated as a temporary component, its final provider/architecture belongs to E5. *(Historical E2-time note: E5 is now closed at application level — see "E5" below.)*

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
✅ COMPLETE — `baf7487b5fe03c978cbc101ad2b7e6c72481c610` (`feat: complete public informational pages redesign (E2-A6-I2)`) — direct parent of E2-A7

- **Travel Dictionary:** legacy sidebar removed; exactly one rendered H1; E2 breadcrumbs/hero; native `details/summary` disclosure; content preserved; desktop multi-column Terms treatment; responsive mobile behaviour.
- **Five legal pages** (`cookies`, `personal-data-consent`, `registration-personal-data-consent`, `review-personal-data-consent`, `review-publication-consent`): E2 presentation; legal copy preserved (no modernization/rewrite); breadcrumbs / H1 / readability improvements.
- **404:** existing public 404 redesigned into shared E2 presentation; actual HTTP 404 behaviour preserved; no 403/419/429/500/503 pages were added — those remain an E4 resilience/stabilization consideration, not an E2 omission.
- **Reviews:** pagination changed from 4 to 6 per page (`Review/IndexController::paginate(6)`); publication ordering/filter/withdrawal semantics preserved.
- **Shared desktop width:** generic E2 informational prose/hero/title no longer uses an unnecessarily narrow desktop character-width cap; on desktop it uses the available parent/container width; mobile behaviour unchanged; user explicitly approved this direction.

#### E2-A7 — final public visual system
✅ COMPLETE — `35f91b9e270cf68654877d42fc8b0d0d59d12458` (`feat: finalize public visual system palette (E2-A7)`) — authoritative application HEAD at E2 closure (superseded by E3)

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
✅ **E3-A1…E3-A5 CLOSED at application level; E3-A6 point-polish (A + B) CLOSED**

Deep pass for tourist/manager/admin cabinets:

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

Design decisions followed findings, not blanket restyling — Manager (E3-A4) and
Admin (E3-A5) in particular already reused the E3 shared shell/components from
earlier slices, so those two were targeted defect-fixing passes plus real
information-hierarchy additions, not from-scratch rewrites.

Current authoritative application HEAD: `ed550df8989b44e7305bdce6b6f5f063b82a2616`
(`fix: polish cross-role chat switching (E3-A6-B)`); direct parent —
`8a5018bdf6a95658d195772c05adc3c4f557329d` (`perf: polish manager dashboard queries (E3-A6-A)`).

#### E3-A1 — Shared Cabinet Foundation
✅ COMPLETE — `66b5628daf76cc5a7d05d4ca2ab85e8f2be74c3d` (`feat: establish shared cabinet foundation (E3-A1)`)

- new token/primitive CSS system `public/css/cabinet-e3.css`, shared base for tourist/manager/admin;
- substantially simplified `resources/views/cabinet/layouts/app.blade.php` (shared shell markup, landmark/skip-link, mobile drawer control hooks);
- new shared `cabinet/components/flash.blade.php` — one flash region covering every existing controller flash key, dismissible Bootstrap-alert structure;
- updated `booking-card`, `empty-state`, `stat-card`, `status-badge` components;
- updated per-role sidebar partials (admin/manager/tourist) with `aria-current` on the active item;
- header user-dropdown trigger is a native `<button>`;
- password-change-required redirect preserved through the shared shell.
- Tests: `tests/Feature/CabinetSharedShellFoundationTest.php` — contract-level checks (landmarks per role, header profile/settings links, per-role sidebar contents, `aria-current`, shared flash region per controller key, validation-error visibility, password-change-required redirect, mobile drawer hooks, dismissible flash, native dropdown button).

#### E3-A2 — Tourist Cabinet and cross-role chat continuity
✅ COMPLETE — `6fdbe8eea6fb3eb5a7309396753efb8f2ae1f9ed` (`feat: modernize tourist cabinet and cross-role chat (E3-A2)`)

- 9 tourist blade views moved onto the E3 shell/tokens; new `tc-*` CSS section in `cabinet-e3.css`;
- `CabinetController::touristSidebarData()` reuses the existing unread-message formula so the chat badge is consistent across tourist pages; added `hasAnyDocuments` view flag and a `manager` eager-load on booking documents; removed dead `pendingBookingsCount` plumbing;
- Bonus page stripped of an invented referral program/earning rules — only real balance/level/totals/transactions remain; wishlist replaced with an honest "in development" notice (route kept);
- shared cross-role chat continuity layer: `public/js/cabinet-chat.js` (AJAX thread switching without reload for tourist/manager/admin, History API, per-user/context/booking localStorage drafts, AJAX send, polling race guard, progressive fallback);
- the Admin-as-assignee contract is established here and preserved through every later slice: Admin may read any booking chat; only an Admin personally assigned to `booking.manager_id` may write; a non-assigned (observer) Admin stays strictly read-only — no composer, no poll, no read-state change;
- logout correctly clears only the current user's own chat drafts (never a blanket `localStorage.clear()`).
- Tests: `tests/Feature/TouristCabinetE3RedesignTest.php`, `tests/Feature/CabinetChatContinuityTest.php`.
- Carried forward: AJAX thread-switch UX polish → E3-A6/E4.

#### E3-A3 — Shared Booking Surfaces
✅ COMPLETE — `2b567f04b52ebee0085a11e195e973b621a58031` (`feat: modernize shared booking surfaces (E3-A3)`)

- the three shared role-sensitive views `resources/views/bookings/{show,edit,create}.blade.php` moved onto the E3 system; new `.booking-*` CSS section (tokens only, no new palette) in `cabinet-e3.css`;
- new reusable partial `cabinet/components/booking-facts.blade.php` (key/value `<dl>`, skips null values);
- dropped the dead unauthenticated/guest branch from all three views (the route group is always authed);
- status-wording normalization: the canonical label for stored status `progress` is «В обработке» (source of truth: `Booking::availableStatuses()`/`getStatusLabelAttribute()`); the single dissenting `status-badge` component was fixed; stored values and `Booking::transitionMap()` untouched;
- role-aware chat links added to booking `show` (owner → `cabinet.chat`, assigned manager → `cabinet.manager.chat`, admin → `cabinet.admin.chats` always, including observer mode), hidden for the owner until a manager is assigned;
- **frozen and unchanged:** `BookingPolicy`, `BookingController` (no controller change at all), routes, middleware, validation, `transitionMap`, assignment rules, `User::assignableToBookings()`, document/message authorization.
- Tests: `tests/Feature/SharedBookingSurfacesE3Test.php`.

#### E3-A4 — Manager Cabinet
✅ COMPLETE — `e9440fc99e1205c7066fe0074e30f1afcb992c07` (`feat: modernize manager cabinet (E3-A4)`)

Manager pages already reused the shared E3 shell/components from earlier
slices, so this was a targeted fix of concrete, verified defects plus a real
"what needs attention" hierarchy on the dashboard — not a from-scratch
overhaul:

- Chart.js was never actually loaded on the Manager dashboard/statistics pages (canvases silently blank); wired up the already-installed `public/plugins/chart.js/Chart.min.js` asset — no new dependency;
- removed a genuine N+1 in the Manager chat thread list (one `Message::count()` query per booking in the loop) — replaced with one grouped query in `ManagerController::chat()`;
- the sidebar "Мои заявки" badge was dead code (no controller ever passed that variable) — wired a real fallback query mirroring the existing unread-messages fallback pattern;
- **bigger find:** `ManagerController::dashboard()`/`::statistics()` used MySQL-only raw SQL (`DATE_FORMAT()`, `MONTH()`) for monthly chart/stat grouping — those two routes therefore had zero prior Feature-test coverage and would crash under the mandated SQLite `:memory:` runtime. Rewrote both to group in PHP (`Collection::countBy`/`groupBy`) — same output, portable, now testable;
- added an unread-chat indicator to the work queue (`bookings.blade.php`) via a bounded grouped query scoped to the current page's booking ids;
- reworded the ambiguous statistics label "Общий доход" (read as personal income) to "Выручка (завершено)" to match what it actually sums and to match `finance.blade.php`'s existing wording — no data change;
- added an additive dashboard "Требует внимания" section (new/progress bookings, oldest-first) above the stat cards; the existing "Последние заявки" (all-status, newest-first) section kept as-is.
- **Deliberately left untouched** (out of the explicit primary hierarchy, no proven defect): `manager/{finance,content,articles/*,reviews/*,documents,profile,settings}.blade.php`. `/manager/knowledge` confirmed orphan-from-navigation (shares a controller/view with "Контент" but has no sidebar entry of its own) — left as-is.
- An independent read-only review (separate session, source/diff-level audit) confirmed manager-scoping is correct everywhere by construction (attention queue, bookings, chat, sidebar badge — no cross-manager leakage possible, every `whereIn` id list is pre-scoped to the authenticated manager); the `DATE_FORMAT()`/`MONTH()` → PHP rewrite is semantically equivalent to the original MySQL; the 3→4 query-count bump in `ManagerClientListQueryEfficiencyTest` is a real, deliberate +1 from the new sidebar badge fallback, not a regression. Verdict: zero MUST-FIX findings.
- Tests: `tests/Feature/ManagerCabinetE3RedesignTest.php` (14 new); existing `ManagerClientListQueryEfficiencyTest` updated for the real, non-regressive query-count increase.
- Carried forward to E3-A6/E4 (non-blocking polish, no proven defect): the sidebar's duplicate pending-badge query could reuse a value the controller already computed; `attentionBookings` eager-loads an unused `tour` relation; a few test-coverage gaps (multi-year stats grouping, zero-data chart, sender-side message exclusion).

#### E3-A5 — Admin Cabinet
✅ COMPLETE — `9fee7dfb990c7a6c18fc9dcf9205e3db3dca24e6` (`feat: modernize admin cabinet (E3-A5)`) — application HEAD at E3-A5 closure (superseded by E3-A6-A/B)

Final E3 redesign slice: Admin Dashboard, Bookings (+ booking detail), Chat, Finance,
Users, Roles, Profile, System, Logs, Bonus, Content, article creation, shared
sidebar/mobile shell. Browser QA passed for desktop and responsive/mobile on
every surface listed.

Key product/security/UX contracts locked in by this closure:

- **Admin chat: read vs write.** Admin may read any booking's chat. Only an
  Admin personally assigned as `booking.manager_id` may write; a non-assigned
  (observer) Admin stays strictly read-only — write attempts from a
  non-assigned Admin are denied. Assigned-Manager and Tourist-participant
  contracts preserved unchanged.
- **Booking assignment semantics.** Assignment targets use the single source
  of truth `User::assignableToBookings()` (query scope `scopeAssignableToBookings`
  in `app/Models/User.php`): active Managers and active Admins are assignable;
  Tourists are excluded; inactive employees cannot be newly assigned; a
  historically assigned employee who has since been deactivated remains
  visible in the UI as inactive (not hidden or swapped out).
- **Dashboard.** Uses the five canonical booking statuses individually (not
  collapsed into broader buckets); the completed-revenue label is honest (not
  conflated with total/incomplete income).
- **Finance.** The responsible-person breakdown includes both Managers and
  Admins (not Managers only) — reflecting that an Admin can personally run
  bookings too.
- **Admin Profile/System IA split.** Personal settings consolidated under
  "Мой профиль" (single entry point for personal data/password). "Система"
  holds runtime/system information and cache management — an operational, not
  personal, section.
- **Logs safety.** Logs remain Admin-only, bounded, and read-only; no
  standalone absolute-path disclosure.
- **N+1 correction.** The Admin bookings page now eager-loads roles and reads
  the loaded role collection instead of repeatedly calling `hasRole()` inside
  booking/employee loops.
- **Responsive closure.** Known responsive issues on Dashboard and Profile are
  closed (including the ₽-wrap card-overflow item carried forward from
  E3-A4).
- Faker/apostrophe flakiness found in `AdminCabinetE3RedesignTest` during the
  session was corrected before final validation.

Tests: `tests/Feature/AdminCabinetE3RedesignTest.php` (45 tests, the bulk of
the new coverage), plus new `tests/Feature/AdminLogsTest.php` (7),
`tests/Feature/AdminSettingsTest.php` (16), and targeted updates to existing
`CabinetHeaderRoleLinkConsistencyTest`, `CabinetSharedShellFoundationTest`,
`MessageParticipantAuthorizationTest` for the new Admin write contract.

#### E3-A6 — Cross-cabinet point-polish
✅ **CLOSED** — two application slices (E3-A6-A, E3-A6-B); no further application commit required.

Closes the point items carried forward from E3-A2 / E3-A3 / E3-A4 (not an invented new E3 scope).

##### E3-A6-A — Manager dashboard / query polish
✅ COMPLETE — `8a5018bdf6a95658d195772c05adc3c4f557329d` (`perf: polish manager dashboard queries (E3-A6-A)`)

- the Manager dashboard now reuses its already-computed pending/unread values for the sidebar instead of triggering duplicate `COUNT` queries; the sidebar fallback for other Manager pages is intact;
- the unused `tour` eager-load was removed only from the dashboard `attentionBookings` query;
- regression coverage added: current-year monthly-stat exclusion, zero-data dashboard/statistics, query-count.
- Tests: `tests/Feature/ManagerCabinetE3RedesignTest.php`.

##### E3-A6-B — Cross-role chat UX polish
✅ COMPLETE — `ed550df8989b44e7305bdce6b6f5f063b82a2616` (`fix: polish cross-role chat switching (E3-A6-B)`) — current authoritative application HEAD

- shared AJAX chat thread switching (`public/js/cabinet-chat.js`) now preserves the thread-list `scrollTop`; Tourist / Manager / Admin use explicit stable `data-chat-thread-scroll` hooks;
- browser Back/Forward stays on the same shared switch path;
- `refreshNavUnread` stale-response race hardened using the existing generation model;
- sender-side unread regression coverage added;
- Admin observer / assigned-Admin security and UI contracts preserved unchanged;
- Tourist chat page-level blank vertical overflow fixed with a narrow `.tc-chat__panel { contain: layout; }`; Manager/Admin chat layouts were measured and unaffected.
- Browser QA PASS: Tourist, Manager, assigned Admin, observer Admin — scroll preservation, rapid switching, Back/Forward, draft, polling, unread behaviour, observer read-only and assigned-Admin composer behaviour.
- Tests: `tests/Feature/CabinetChatContinuityTest.php`, `tests/Feature/MessageParticipantAuthorizationTest.php`.

##### E3-A6 closure

E3-A6 is **CLOSED**. **No E3-A6-C application slice is needed.** Two audited tails remain, both deliberately not changed:

- **Tourist «В работе» wording.** The tourist aggregate metric counts `NEW` + `PROGRESS`; the filter option with the same wording maps only to `PROGRESS`; canonical `PROGRESS` wording elsewhere is «В обработке». No status/query semantics are wrong — this is cosmetic terminology consistency only. Deferred to E4 "remaining visual inconsistencies". A final replacement wording has **not** been decided.
- **`/manager/knowledge`.** The legacy `/manager/knowledge` route is a GET redirect; the real `/cabinet/manager/knowledge` aliases `ManagerController::content()`; current sidebar/navigation uses «Контент». The route is functional and harmless — **left as-is**, not a defect requiring removal. Optional redirect/alias hygiene may be reconsidered during E4.

#### E3 test baseline

**Final verified baseline at E3-A6 closure (authoritative):**

```text
PHP 8.3.32
PHPUnit 11.5.56
SQLite :memory:
full: 1242 tests / 8056 assertions, 0 failures, 0 errors
```

(At E3-A5 closure the baseline was 1233 tests / 8023 assertions.)

The single PHPUnit deprecation is the pre-existing XML schema deprecation, not
a functional/code failure. The final full run required a direct PHPUnit
invocation with a temporary `-d memory_limit=1024M` CLI override (this
machine's default 128M CLI memory_limit is insufficient for a suite this
size) — not a `php.ini`/runtime configuration change. PHPUnit against
canonical MySQL remains forbidden.

Before E3 (after E2 closure, `886bde98`): 1051 tests / 7180 assertions. Growth
to 1233 / 8023 (E3-A5) and 1242 / 8056 (E3-A6) is spread across E3-A1…E3-A6
(foundation contracts, tourist, shared booking, manager, admin, dashboard-query
and chat-polish regression coverage) — expected, not a regression.

#### E3 closure — carried-forward point items: disposition

The point items carried forward from E3-A2…A4 are resolved as follows:

- chat AJAX thread-switch UX polish (E3-A2) — ✅ done in E3-A6-B;
- Manager sidebar duplicate pending-badge query (E3-A4) — ✅ done in E3-A6-A;
- Manager `attentionBookings` unused `tour` eager-load (E3-A4) — ✅ done in E3-A6-A;
- test-coverage gaps: Manager stats month grouping / zero-data / sender-side message exclusion (E3-A4) — ✅ covered in E3-A6-A / E3-A6-B;
- tourist «В работе» aggregate wording — cosmetic, **deferred to E4** (no final wording decided);
- `/manager/knowledge` — **left as-is**; optional alias hygiene may be reconsidered in E4.

**S13-R2 — Manager review cache parity relevance check — ✅ CLOSED.** See the
Stage 13 closed queue above: no live public review cache layer exists (the
Admin `Cache::forget()` calls target keys that are never populated via
`Cache::remember()`), so no parity implementation was necessary. Completed
relevance check, not an outstanding defect.

### E4 — post-redesign stabilization / regression / browser-device / resilience
✅ **CLOSED** — entry docs checkpoint `c89e923f` (`docs: close E3-A6 and advance to E4`), final application checkpoint `55a9fcaf6371ef0c749bb668c9b27783d1df358c` (`fix: close final E4 application polish`).

#### E4-A — baseline confirmation / QA matrix
✅ CLOSED — planning only, no dedicated application commit. Confirmed the E4-entry full-regression baseline (1242 / 8056, checkpoint `c89e923f`) and defined the browser/device/keyboard/accessibility QA matrix used in E4-C.

#### E4-B — branded system error pages
✅ CLOSED — `511282e38f05b63871d4e1d9fcb154a8fa63df56` (`feat: add branded system error pages (E4-B)`). Added the missing branded 403/419/429/500/503 system error pages (404 was already redesigned in E2-A6-I2) — closes the item carried forward from E2-A6-I2/E3.

#### E4-C — Browser / Device / Keyboard / Accessibility QA
✅ CLOSED — QA pass only, findings closed in E4-D1…E4-D3. Evidence: `C:\Avilona_private\E4\E4-C_Browser_Device_Accessibility_QA\20260920-150303\` (incl. persistent QA SQLite `qa.sqlite`, preserved).

All findings **F-01…F-16 are FIXED_VERIFIED**. Final P-classifications:

- **P-01** FIXED_VERIFIED — public focus indication corrected and verified cross-browser.
- **P-02** FIXED_VERIFIED — public skip link added and verified.
- **P-03** FIXED_VERIFIED at template level — CAPTCHA images now have accessible alt text. **Residual limitation (non-blocking accessibility debt):** a visual image CAPTCHA remains inherently difficult/impossible for some assistive-technology users without a non-visual challenge alternative; alt text does **not** make it universally accessible — kept as known backlog, not resolved.
- **P-04** FIXED_VERIFIED — login errors exposed via alert semantics.
- **P-05** No general release-blocking heading defect found. One real remaining instance is `/tours`: the temporary Tour Search page lacks the desired H1/main structure. This is **not** general public accessibility debt — it travels to E5 solely because that exact page is intentionally rebuilt/replaced there.
- **P-06** FIXED_VERIFIED — public mobile toggler accessible name/state/Esc behaviour.
- **P-07** FIXED_VERIFIED — reduced motion.
- **P-08** FIXED_VERIFIED — cookie Accept focus management.
- **P-09** FIXED_VERIFIED — Admin observer read-only notice.
- **P-10** No release blocker — targeted text/reflow testing found zero page-level horizontal overflow; internal `.table-responsive` scrolling is expected. Kept only as a non-blocking polish note.
- **P-11** NOT_REPRODUCED — 320px page-level Admin chat overflow not present.
- **P-12** FIXED_VERIFIED — password autocomplete semantics.
- **P-13** **KNOWN_POLISH_BACKLOG** — responsive table wrappers lack contextual accessible names; underlying tables retain their own `<th>` semantics and remain usable. A correct fix needs a meaningful per-table label, not blind mass-labelling. **Not implemented in E4-E2** — remains non-blocking polish backlog.

#### E4-D1 — shared shell and auth UX stabilization
✅ CLOSED / PUSHED — `16b71c6a51064737007b0645214c919462e40f17` (`fix: stabilize shared shell and auth UX (E4-D1)`)

#### E4-D2 — cabinet responsive layout stabilization
✅ CLOSED / PUSHED — `46a97f3d241ab1f53663651f7b5e9147d00f9ec7` (`fix: stabilize cabinet responsive layouts (E4-D2)`)

#### E4-D3 — stabilization findings closure
✅ CLOSED / PUSHED / VERIFIED — `c1ad29cb5127536577545778fa8b8a79e9ddd24e` (`fix: close E4-D3 stabilization findings`). Reconciliation status: `REPORTING_COUNT_ERROR_ONLY` (a findings-count reporting discrepancy only, not an application-behaviour defect). Evidence: `C:\Avilona_private\E4\E4-D3_Browser_QA\20260922-121234\`.

#### E4-E1 — final technical closure
✅ TECHNICALLY CLOSED / PUSHED — `55a9fcaf6371ef0c749bb668c9b27783d1df358c` (`fix: close final E4 application polish`). Final full PHPUnit: **1286 tests / 8628 assertions, 0 failures, 0 errors** (SQLite `:memory:`; canonical MySQL untouched). Local `php artisan test --compact` hit the PHP CLI default 128M memory_limit late in the run — a local-runner limitation, not an application failure; the full historical run with `memory_limit=512M` passed completely with the same 1286 / 8628, 0 / 0 result. Evidence: `C:\Avilona_private\E4\E4-E1_Final_Technical_Closure\20260923-015901\`. Result: `READY_FOR_E4_DOCUMENTATION_CLOSURE`; no application-level release blocker remains.

#### E4 — non-blocking polish backlog (carried past E4, not part of E5)

- P-13 responsive table wrapper accessible naming;
- CAPTCHA residual accessibility limitation (P-03);
- P-10 cosmetic/internal table-tightness note (informational only);
- tourist «В работе» aggregate wording consistency (carried from E3-A6) — final wording still not decided;
- optional `/manager/knowledge` redirect/alias hygiene (carried from E3-A6) — route is functional and harmless, not a defect.

#### E4 closure

E4 is declared **CLOSED** by this documentation checkpoint (E4-E2) on top of application checkpoint `55a9fcaf`. Application-level technical closure (E4-E1) already PASSED before this documentation pass; this pass records that at the documentation level only and changes no application code.

### E5 — TOUR SEARCH / AGGREGATION — FINAL PRODUCT BLOCK
✅ **CLOSED at application level** — final application checkpoint `c56e330685ffa8953b22c803d6dce5334c69e81b` (`fix: complete final tour search polish (E5-A4)`). **Production NOT deployed.**

The old temporary local tour-search implementation (home filter form, local `tours` search, public Sletat API) is **no longer the active plan or implementation** — it has been removed. `docs/E5-tour-search-research-2026-09-23.md` is the historical pre-implementation research record (it still says "E5 NEXT"; it is not current state).

#### E5 commit history (verified from Git, `a1d72a40..c56e3306`)

| Slice | Full SHA | Subject |
|---|---|---|
| E5 research docs | `1476c1cc87fa83aa3d191d215a8f210f21120799` | `docs: record E5 tour search research` |
| E5-A1 | `9005457a2bde7a5592b152ac7e601f7c22d9b61f` | `feat: add Tourvisor search module PoC (E5-A1)` |
| E5-A2A | `c0e16ae3dc86703c019ca7fd138ab4fa658481c5` | `feat: establish Tourvisor inquiry integration foundation (E5-A2A)` |
| E5-A2A.1 | `d59d211e518169297869dff103d87b85da0e5ea8` | `fix: harden Tourvisor webhook callback (E5-A2A.1)` |
| E5-A2B | `0399d21fb10a9b5f69c3f2c8940a96777197bf67` | `fix: secure Tourvisor webhook registration (E5-A2B)` |
| E5-A3 | `137f109ba46263a20af7d521a9dc32677493a031` | `feat: complete incoming inquiry booking workflow (E5-A3)` |
| E5-A3.1 | `00ea2f0440f0a25b6ecd161aadedb804ce9e8ac2` | `fix: remove plaintext staff client credentials (E5-A3.1)` |
| **E5-A4** | `c56e330685ffa8953b22c803d6dce5334c69e81b` | `fix: complete final tour search polish (E5-A4)` |

#### Final E5 architecture

```text
PUBLIC
Avilona /tours -> Tourvisor Standard search module -> ordinary inquiry
  -> Tourvisor receives inquiry/contact data -> Tourvisor webhook notification
  -> Avilona validates callback -> authoritative server-side Export API fetch
  -> IncomingInquiry

STAFF
IncomingInquiry -> explicit staff claim/assignment
  -> explicit client selection OR safe client creation
  -> manager verifies price / availability
  -> explicit native Avilona Booking creation -> existing Booking lifecycle

EXTERNAL FULFILLMENT
Avilona manager later books manually on the tour operator agent website.
```

Explicitly: **NO** automatic tour-operator booking; **NO** automatic payment; **NO** automatic Booking creation on import; **NO** automatic User ownership inference by e-mail/phone/name; **NO** fake local `Tour` creation for Tourvisor inquiry conversion.

#### Tourvisor / provider facts (confirmed only)

- Selected provider path for E5; existing Avilona Tourvisor account; `avilona.ru` associated with the account; **Standard** module; module ID **9981450** is public/non-secret; **Export API** for inquiries; **WebHook** for notification; live PoC verified exact Export + webhook behaviour.
- Commercial data already documented in the research record (Standard trial until 2026-10-04, budget guidance ≤ ~2 000 ₽/month, final commercial decision not recorded) was **not refreshed** here; no pricing invented; provider terms are not claimed beyond verified information. The commercial decision/renewal is an owner action outside the repository and must be confirmed before production.
- Secrets (`TOURVISOR_EXPORT_API_KEY`, `TOURVISOR_WEBHOOK_TOKEN`) live only in server-side `.env`.

#### E5-A2B live PoC record (no secrets, no personal data)

The live PoC proved: protected Export API request works; live ordinary-inquiry payload is compatible with current normalization; webhook registration works; wrong callback token fails closed; a second synthetic inquiry completed the real chain; exactly one IncomingInquiry created; webhook replay is idempotent; no redundant second Export fetch after imported state; Manager/Admin visibility; Tourist denial; webhook safely removed; tunnel stopped after the PoC. Not recorded: API key, webhook token, full callback URL, any real/synthetic contact values.

#### E5-A3 — incoming inquiry business workflow

IncomingInquiry has a **business workflow independent of the technical import state** (`state` = pending/importing/imported/failed/unsupported; `workflow_state` = business): `new → in_progress → converted`, or `closed`.

- explicit staff claiming; Admin-only reassignment; another manager cannot process an inquiry claimed by a colleague;
- client suggestions are **not** automatic ownership; explicit existing-client selection; safe client creation;
- one inquiry → at most one Booking (`incoming_inquiries.booking_id` UNIQUE); transactional conversion;
- no fake Tour; Booking `tour_id` may remain null for an external inquiry;
- manager-confirmed total price (`bookings.total_price`); Tourvisor imported price is reference only; total is not multiplied by passenger count;
- native initial Booking status reused; close-without-booking supported; provenance retained.
- Migration: `2026_10_01_000000_add_workflow_to_incoming_inquiries_table.php`.

#### E5-A3.1 — plaintext staff client credentials removed (security)

The old staff Booking client-creation path generated a password, stored it in plaintext in `users.temp_password` and e-mailed it. E5-A3.1 removed that active behaviour. Contract: shared `StaffClientAccounts` service; random credential never exposed; only the password hash is stored; `temp_password` stays null for new accounts; no plaintext password mailed/displayed/logged; explicit standard password-reset/setup-link action; imported/staff-entered e-mail is **not** automatically verified; case-insensitive duplicate-e-mail protection; technical `.invalid` addresses receive no external setup e-mail. Migration `2026_10_02_000000_clear_legacy_temp_passwords_from_users_table.php` clears legacy non-null `temp_password` values — **NOT applied to canonical MySQL**.

#### E5-A4 — final public state

- **HOME:** obsolete fake filter form removed; honest CTA to `/tours`; no pretend parameter preservation.
- **TOURS:** Tourvisor module remains on `/tours`; one HTTPS loader; no secret in the browser; public explanation (search powered through Tourvisor; price/availability may change; inquiry is not booking/payment; manager verifies the offer); H1/main present (closes the P-05 `/tours` item).
- **PRIVACY DISCLOSURE:** `/tours` discloses that information entered in the Tourvisor module is processed through Tourvisor and then passed to Avilona; the cookies page records external-module behaviour.
- **LEGACY:** public Sletat API path removed; unused local `/api/tours` search removed; unused Sletat service/controller/command/config removed; Tour/local catalog DB structures retained where native Booking depends on them; Coral / sync / seed / TourOperator deeper cleanup deferred.

#### Tourvisor manual provider settings and privacy facts

- Tourvisor account → Settings → Modules → **"Favorites icon for tourists": OFF** (confirmed manually by the project owner). The earlier Favorites/cookie-banner collision is therefore resolved by provider configuration, **not** application CSS. **Do NOT add any CSS workaround** for Tourvisor Favorites.
- Currently visible provider-account privacy configuration: Tourvisor personal-data consent checkbox **OFF**; consent-policy URL fields **empty**. NOT accepted silently as final production configuration. Classification: **`PRIVACY_LEGAL_FACT_REQUIRED` / E6 PRE-PRODUCTION ACTION.** No URLs or legal text are invented.
- Unresolved before production (owner/legal review; these do **not** reopen E5 implementation): (1) correct Tourvisor operator/provider identification to present publicly; (2) correct provider privacy-policy URL if required; (3) which cookies/storage the embedded module actually uses; (4) whether Avilona's personal-data policy/consent needs an addendum for the Tourvisor flow; (5) retention semantics for data held by Tourvisor; (6) whether the module must be gated by cookie consent; (7) the URL for Tourvisor's "consent to personal-data processing" field; (8) the URL for Tourvisor's privacy-policy field; (9) whether the Tourvisor consent checkbox must be enabled before production.

#### E5 non-blocking backlog (verified still present at `c56e3306`)

- `CoralTravelService` / `app/Services/TourOperators/*` / sync / seed commands / `TourOperator` cleanup (deferred);
- unused `Tour::scopeSearch`;
- P-13 responsive table wrapper accessible naming;
- residual visual CAPTCHA accessibility limitation;
- P-10 cosmetic/internal table tightness (informational);
- Tourist «В работе» terminology consistency (final wording not decided);
- optional `/manager/knowledge` alias hygiene (route still exists as a harmless redirect).

Already solved and NOT carried forward: P-05 for `/tours` (H1/main — E5-A4); the temporary tour-search solution (replaced).

#### E5 closure

E5 is declared **CLOSED at application level** by this documentation checkpoint on top of application checkpoint `c56e3306`. This pass changes no application code and updates no dependencies. **E5 closed ≠ production-ready.**

### After E5, before final E6 — Screenshot Audit Pack + Astra Task 2 (full independent project audit)
⬜ **NEXT**

Approved order:

```text
E5 documentation closure -> fresh Project Sources -> Screenshot Audit Pack
  -> independent full Astra / ChatGPT Work audit -> approved fixes from that audit (if any)
  -> E6 production / operations
```

Independent audit scope: architecture; security; performance; database; queries; maintainability; technical debt; modernization; UX; accessibility; SEO; public structure; features to add/remove; Tourist workflow; Manager workflow; Admin workflow; operations; design. Not executed in the E5 documentation-closure slice.

### E6 — final release / deploy / production smoke
⬜ **PENDING** — final production / operations stage. Starts only after the audit above. **Production is NOT deployed.**

**Production platform**
- Timeweb availability / provisioning when provider capacity becomes available (`web-prod-spb-01`, preordered, not production);
- Ubuntu/server bootstrap; SSH hardening; firewall; Nginx; PHP-FPM; DB; TLS;
- storage permissions; deployment user; backups; logging/rotation; monitoring.

**Runtime / dependency refresh (explicit project-owner requirement)**
Before final production release, perform a SEPARATE guarded modernization pass to verify and, where appropriate, update REQUIRED system/application components to the latest STABLE SUPPORTED versions compatible with the project. Audit at minimum: OS packages; PHP; Composer; Laravel / PHP dependencies; Node.js; npm; frontend dependencies; database server; Nginx; any queue/scheduler/runtime services actually used. "Latest" is NOT blind major-version upgrading. Requirements: supported stable releases; compatibility first; release notes / breaking changes reviewed; one controlled layer at a time; full tests after relevant changes; rollback path; dependency modernization separate from functional work. No dependency updates in the E5 documentation-closure slice.

**Production migrations**
- apply all pending E5 migrations (`2026_09_26_000000_create_incoming_inquiries_table`, `2026_10_01_000000_add_workflow_to_incoming_inquiries_table`, `2026_10_02_000000_clear_legacy_temp_passwords_from_users_table`) only in a guarded production migration plan; verify actual applied state against the canonical `migrations` table first (known: `2026_10_01…` and `2026_10_02…` NOT applied);
- audit legacy users before the `temp_password` cleanup migration;
- decide old forced-password credential invalidation before applying it.

**Tourvisor**
- configure production secrets; configure webhook; verify Export API; Favorites OFF; privacy/consent provider settings resolved (see E5 privacy list); confirm commercial plan/renewal; production-domain Tourvisor smoke.

**Production-domain smoke (recheck)**
- Tourvisor search; Saint Petersburg / Moscow; Firefox provider requests; Yandex Maps provider warning; delayed loader behaviour; mobile fullscreen behaviour; narrow desktop dropdown behaviour; inquiry submission; webhook; Avilona staff import/workflow.

**Security / ops**
- `APP_ENV` / `APP_DEBUG`; HTTPS; trusted proxies / `X-Forwarded-Proto`; security headers / CSP as appropriate; mail; cron/scheduler; RSS scheduling; backups; restore test; log safety; file/storage permissions.

**Business**
- resolve `PENDING_BUSINESS_DECISION_OPENING_HOURS`.

Also: production-ready claim is only allowed after E6 closes; final handoff / release checkpoint. An intermediate deployment for validation may be planned separately, but it does not replace the final release.

## Guardrails

- One semantic slice at a time.
- No dependency update mixed with functional work.
- No canonical DB writes without guarded plan.
- PHPUnit only PHP 8.3.32 + SQLite `:memory:`.
- No real provider integrations without explicit operational approval.
- Current Project Sources must correspond to a clean pushed documentation HEAD.
- No CSS workaround for Tourvisor provider UI (Favorites is controlled by provider account settings).
- Dependency/runtime modernization is a separate guarded E6 pass, never mixed with functional work.
