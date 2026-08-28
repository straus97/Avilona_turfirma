# Avilona_turfirma — Roadmap

Актуализировано: **2026-08-28**

## Current state

- Branch: `db-rebuild-stage3`
- Authoritative E1 closure application commit: `08d0626311234faa06dedf2828cb878805241990`
- Subject: `fix: close final public audit gaps`
- Previous functional HEAD (Stage 13): `dba20e2c6e2e66b6f69f33710b2626b3fe181e31` (`fix: remove obsolete guest booking flow`)
- Stage 0–13: ✅ CLOSED
- E1 Comprehensive Audit: ✅ TECHNICALLY CLOSED
- **Next phase: E2 — Public UX / UI / Design Redesign**
- Full verified baseline: **1001 tests / 7013 assertions** (PHPUnit 11.5.56, PHP 8.3.32, SQLite `:memory:`)
- Single PHPUnit deprecation = pre-existing XML schema deprecation, not a code failure
- Project Sources: stale relative to this HEAD, refresh required after this docs-only commit (previous Project Sources checkpoint: `8e8d6d96f4024953b4cca25f005d55429dec9e92`, 2026-08-24)

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

Current regression says public review pages do not use old cache layers and role publish/unpublish is immediately visible. First re-establish whether any live defect remains. If no live path depends on it, prefer removal/cleanup decision over unnecessary parity code. Not resolved by the Stage 13 docs closure — kept explicitly open, tracked outside Stage 13. Not reopened as an E1 defect; carry into E3 (cabinet pass) as a read-only relevance check unless a concrete live defect appears earlier.

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

Documentation closure recorded in `docs/README.md` and this file. Project Sources refresh is a separate required follow-up (see Current state) and must be generated from the new docs closure HEAD, not from `dba20e2c`.

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

- `PENDING_BUSINESS_DECISION_OPENING_HOURS` — home 10:00–20:00 vs contacts 11:00–20:00 by prior appointment; no authoritative decision; must be resolved before final production release.
- Per-page `og:type=article` refinement → E2.
- Temporary public tour-search widget stays until E5.
- News RSS scheduling — verify real production cron in E6; do not add Laravel scheduling blindly.
- Future-risk raw HTML (`Best_offer` / `OurClient` / `Countries_image` / `Destination_image`) — no current untrusted web write path; do not reopen unless a CMS/write path is added.

### E2 — public-site UX/UI/design modernization
⬜ **NEXT**

Not merely a cosmetic recolor — treat the public site as a coherent modern tourism website: information architecture, header/navigation, home-page hierarchy, typography, spacing, colour system, buttons/forms, cards, responsive behaviour, mobile navigation, visual consistency, destinations/countries, company pages, employees, awards, articles/news/special offers/reviews, contacts, empty/error states, consent UI, accessibility, trust/credibility, conversion paths, CTA consistency, image treatment, desktop/tablet/mobile.

Begin with a **READ-ONLY visual/UX inventory and design-system proposal** before broad implementation. Do not redesign final tour-search mechanics; the current widget may be visually accommodated as a temporary component, its final provider/architecture belongs to E5.

After analysis, improve coherent design system and individual pages/components:

- layout/grid;
- visual hierarchy;
- typography;
- spacing;
- navigation/header/footer;
- forms/cards/tables/alerts/modals;
- iconography;
- color scheme and semantic states;
- responsive composition;
- consistency across public pages.

### E3 — cabinet UX/UI/design modernization
⬜ PLANNED

Separate deep pass for tourist/manager/admin cabinets:

- information architecture;
- navigation/sidebars/headers;
- dashboard priorities;
- action placement;
- cards/tables/forms;
- icons/statuses/color system;
- visual hierarchy and density;
- mobile/tablet/desktop behavior.

Design decisions must follow findings, not blanket restyling.

### E4 — post-redesign stabilization
⬜ PLANNED

Full tests + browser/device regression + production-readiness repeat.

### E5 — TOUR SEARCH / AGGREGATION — FINAL PRODUCT BLOCK
⬜ DELIBERATELY LAST

The current homepage and `/tours` search widget remains temporary until this stage.

Compare:

- ready-made widget/aggregator;
- tour-operator/API integrations;
- own aggregation/search implementation.

Decision criteria: cost, stability, contract/legal terms, UX, mobile, booking/cabinet/CRM integration, caching/rate limits and maintenance.

### E6 — final release / deploy / production smoke
⬜ PLANNED

After the final selected tour-search solution and stabilization:

- guarded production deploy;
- migrations only through dedicated plan;
- smoke checks;
- final handoff.

An intermediate deployment for validation may be planned separately, but it does not replace the final post-redesign/post-tour-search release.

## Guardrails

- One semantic slice at a time.
- No dependency update mixed with functional work.
- No canonical DB writes without guarded plan.
- PHPUnit only PHP 8.3.32 + SQLite `:memory:`.
- No real provider integrations without explicit operational approval.
- Current Project Sources must correspond to a clean pushed documentation HEAD.
