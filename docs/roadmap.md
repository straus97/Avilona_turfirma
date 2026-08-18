# Avilona_turfirma — Roadmap

Актуализировано: **2026-08-18**

## Current state

- Branch: `db-rebuild-stage3`
- Functional HEAD: `15bd01a29cdb17c8bda3e3812027343971d6bd80`
- Functional subject: `feat: disclose moderator-edited reviews`
- Stage 0–12: ✅ COMPLETE
- Stage 13: 🚧 IN PROGRESS
- Full verified baseline: **839 tests / 3718 assertions**
- PHP: 8.3.32
- PHPUnit DB: SQLite `:memory:`

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
🚧 **IN PROGRESS**

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

## Stage 13 — remaining queue

### S13-R1 — withdrawn consent publication guard/workflow
⬜ TODO

Goal: define and enforce what happens when review publication consent is withdrawn, including already-published state. Exact legal/business behavior must be agreed before implementation.

Keep separate from cache cleanup and other consent surfaces.

### S13-R2 — Manager review cache parity relevance check
⬜ TODO / READ-ONLY FIRST

Historical finding: Admin and Manager had asymmetric legacy review cache clearing.

Current regression says public review pages do not use old cache layers and role publish/unpublish is immediately visible. First re-establish whether any live defect remains. If no live path depends on it, prefer removal/cleanup decision over unnecessary parity code.

### S13-R3 — public registration consent/policy
⬜ TODO

Separate business/legal/content decision for account creation and cabinet data processing.

### S13-R4 — guest booking contract
⬜ TODO / VERIFY FIRST

Re-check whether anonymous public booking UI still conflicts with protected endpoint behavior. Fix as a separate functional slice if confirmed.

### S13-R5 — final local production-readiness
⬜ TODO

After R1–R4:

- full PHPUnit;
- migration/schema inventory;
- read-only dependency/security verification where appropriate;
- routes/config/cache/build checks;
- desktop/tablet/mobile key browser flows;
- auth/role/cabinet smoke;
- reviews/privacy/legal paths;
- no real external-provider traffic.

### S13-R6 — Stage 13 closure docs
⬜ TODO

Create closure documentation/source checkpoint only after remaining Stage 13 work is verified.

## Endgame after Stage 13

### E1 — comprehensive project audit
⬜ PLANNED

Not only technical defects. Produce prioritized findings for:

- functionality;
- security/privacy;
- code quality/duplication/dead code;
- performance/query patterns;
- content consistency;
- responsive behavior;
- accessibility/usability;
- page/component composition;
- old/rough visual decisions.

### E2 — public-site UX/UI/design modernization
⬜ PLANNED

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
