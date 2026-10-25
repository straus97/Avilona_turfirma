# AOA-03-I7 — Owner decision: clean-start Inquiry domain (documentation only)

Date: 2026-10-25. Base commit: `896f461b6e05539f34175fde3a1054ce156a9138`. Documentation only: no code, migration, test, database, Tourvisor, mailbox or production access; the PHPUnit suite was **not** re-run (last complete baseline, historical: 2100 tests / 16088 assertions, 0 failures, 0 errors).

## 1. Owner-confirmed business facts (not independently verified from any data)

1. The agency currently receives **no** inquiries through its existing website.
2. The agency has **never** received a customer inquiry through that website.
3. The agency is waiting for the new website and management system.
4. Current customer inquiries arrive by **telephone** and **personal messenger conversations**.
5. The new Laravel application is **not deployed** in production.

These are statements of the owner. They say nothing about database contents, historical telephone or messenger conversations, or the Tourvisor account.

## 2. Decision — Scenario A (clean start)

The owner accepts launching the new CRM with a provider-independent `Inquiry` domain **without migrating website inquiries**, because none are stated to exist. Telephone and messenger inquiries are handled manually by staff in **AOA-04**; active conversations may be entered through the future CRM workflow where appropriate. Historical correspondence is not discarded, erased or imported automatically.

Explicitly **not** done or authorized by this decision: online synchronization, a transactional change journal, historical or production backfill, real-data inspection or copy, deletion/reset/truncation of synthetic or legacy data, new webhook routes, any change of E5 behaviour. The I2–I6 tooling stays in the repository unchanged.

## 3. Source inventory — what the owner statement resolves

Labels: VFR = VERIFIED_FROM_REPOSITORY, DNL = DOCUMENTED_NOT_LIVE_VERIFIED, OCR = OWNER_CONFIRMATION_REQUIRED, UNK = UNKNOWN.

| Question | Status after I7 |
|---|---|
| Do website customer inquiries exist that must be migrated? | **Resolved by owner statement (OWNER-CONFIRMED): none.** No website inquiry history is required for the initial migration. |
| Laravel public contact forms persist nothing (mail only) | VFR (unchanged) |
| All code paths writing `incoming_inquiries` / `inquiries` | VFR (I4/I5/I6 inventories, unchanged) |
| Actual contents of `turfirma_rebuild_v4` (experimental / PoC / other rows) | **UNK** — never read; "no website inquiries" does not imply the database is empty |
| E5 PoC rows were synthetic | DNL (roadmap E5-A2B record) |
| Current Tourvisor account records (technical or experimental) | **UNK** |
| Live Tourvisor webhook registration | **UNK** (PoC registration documented as removed — DNL) |
| Provider retry / retention / export-by-date guarantees | **UNK** (open provider questions) |
| Mailbox contents and routing; other historical data in other systems | **UNK** |
| Historical telephone / messenger conversations | not a database source; not assumed absent; manual entry via AOA-04 where appropriate |

## 4. AOA-03 requirement review

Canonical definition (roadmap §A6): *provider-independent `Inquiry` entity, additive migration, link/backfill from `IncomingInquiry` as provenance.*

| Stage | Delivered (development) | Evidence |
|---|---|---|
| I1 | `inquiries` table, model/invariants, `InquiryPolicy`, `InquiryDraftStore` | [I1](AOA-03-I1-inquiry-foundation-2026-10-24.md) |
| I2 | Guarded idempotent backfill + integrity verification (disposable DBs only) | [I2](AOA-03-I2-controlled-backfill-2026-10-24.md) |
| I3 | Inquiry-aware read authority (`InquiryAuthority`), `ClientVisibility` branch for independent Inquiries, read-only reconciler | [I3](AOA-03-I3-inquiry-aware-read-integration-2026-10-25.md) |
| I4 | `inquiry.draft_updated` audit, mutation inventory, catch-up feasibility | [I4](AOA-03-I4-inquiry-audit-catch-up-readiness-2026-10-25.md) |
| I5 | Freeze-feasibility analysis, objective blockers B1/B2 | [I5](AOA-03-I5-freeze-convergence-readiness-2026-10-25.md) |
| I6 | Synthetic offline convergence rehearsal; no real-data migration | [I6](AOA-03-I6-prelaunch-offline-convergence-2026-10-25.md) |
| I7 | Source inventory and owner decision (this document) | this file |

Classification:

- **A. Completed development requirements:** entity, additive schema, provenance link, policy, durable draft, draft audit, visibility integration, backfill and verification tooling (rehearsed on synthetic data).
- **B. Future AOA-04 operational workflows:** creation, source selection, ownership/assignment, list/detail screens, status handling, a staff form for the draft (R-D); Booking linkage is AOA-05.
- **C. Pre-production / E6 requirements:** deploy, production database readiness, security/release gates, canonical-DB content check, PHP/Composer residuals, legal PDF and external website updates.
- **D. Migration mechanisms not required for the clean-start scenario:** production backfill, source-of-truth SWITCH, online catch-up, change journal, freeze/write barrier, `MIRROR_FROM_SOURCE`.

No unimplemented feature is required by the AOA-03 definition under Scenario A. **AOA-03 is closed as a development stage (clean-start scope).** This is *not* a production Inquiry cutover and not a claim that any real data was migrated.

## 5. Retained open items (not closed by this decision)

- **B1** (no proven lossless intake during an ingress freeze) and **B2** (no complete write barrier) remain **OPEN**. They are not prerequisites for a clean initial domain with no active-source migration; they become relevant again if such a migration (or a freeze/cutover) is ever introduced.
- Canonical DB contents unverified; no owner permission for real-data inspection; no authorization for any production backfill.
- Tourvisor retry/retention uncertainty; webhook registration status unknown.
- Historical BLOCKED classes and the I3/I4/I6 cutover prerequisites if a real migration ever becomes necessary.
- Linked `IncomingInquiry`/`Inquiry` pairs: E5 remains authoritative where such a pair exists; no SWITCH was performed.
- PHP/Composer compatibility residuals, production database readiness, legal PDF / external website updates, and all E6 security/release gates (including R-D and R-L in the roadmap register).
- Before E6 deploy: an owner-approved, aggregate-only check that the canonical database holds no unintended records (separate approval, not granted here).

## 6. AOA-04 readiness

AOA-04 (manual Inquiry creation, ownership and assignment per §A4) is the next functional stage. Readiness conditions: AOA-03 closed (§4); reuse `Inquiry`, `InquiryDraftStore`, `InquiryAuthority`, `InquiryPolicy`, `ClientVisibility`, the neutral client-verification process and the business audit; a Manager-created Inquiry is automatically that Manager's responsibility; Admin oversight/assignment; source selection (telephone, messenger); privacy-safe JSON and audit; no new CRM architecture. No AOA-04 route, screen or code exists. The first slice must be planned in a **new** Claude Code chat.
