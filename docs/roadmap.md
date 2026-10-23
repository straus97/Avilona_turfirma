# Avilona_turfirma — Canonical Roadmap

Updated: **2026-10-23** (AOA-01-CLOSE, documentation only; previous syncs ROADMAP-SYNC-02 2026-10-20, ROADMAP-SYNC-01 2026-10-19)

> **SINGLE SOURCE OF TRUTH.** This file is the one canonical document for project stages, current status, implementation order, owner-approved decisions, unresolved business decisions, upcoming work and production readiness. `README.md`, `docs/README.md`, the E5.5 architecture document, generated Handoff and Project Sources must **defer to this file** for stage/status/NEXT statements and must not keep competing roadmaps. Architecture documents keep technical design and rationale only. Generated Handoff / Project Sources are versioned **snapshots** of this repository, never a competing source. Where code and this file disagree about *current behaviour*, the code at the pushed HEAD wins; where they disagree about *approved intent*, this file wins and the difference is an implementation gap (§A5).

This file has two parts: **Part A — canonical plan** (§A1–§A9, kept current) and **Part B — stage history** (closed stages, kept as a historical record).

## A0. Status legend

| Label | Meaning |
|---|---|
| `APPROVED REQUIREMENT` | The project owner approved the rule or scope. It says nothing about whether it is implemented. |
| `OWNER_APPROVED` | An owner business decision that is final. Implementation status is stated separately. |
| `PROPOSED IMPLEMENTATION` | A plan or slice proposed for delivering an approved requirement. It is not binding until its own guarded plan is approved. |
| `IN PROGRESS` | Work has started and is not yet pushed. |
| `CLOSED` | Implemented, verified and pushed (or documented as closed). |
| `BLOCKED` | Cannot proceed until a stated dependency or decision is resolved. |
| `BUSINESS_DECISION_REQUIRED` | The owner has not decided. Nothing may be invented. |
| `OPEN` / `VERIFY_NEEDED` / `REVIEW` | Audit-finding states, see §A7. |

## A1. Canonical stage status

| Stage | Status |
|---|---|
| Stage 0–13 | ✅ CLOSED |
| E1 — Comprehensive Audit | ✅ CLOSED (technically) |
| E2 — Public UX/UI redesign | ✅ CLOSED |
| E3 — Cabinet UX/UI redesign | ✅ CLOSED |
| E4 — Post-redesign stabilization / QA | ✅ CLOSED |
| E5 — Final tour search (Tourvisor → IncomingInquiry → native Booking) | ✅ FULLY CLOSED AND DOCUMENTED (application level; E5 is **not** reopened by E5.5) |
| **E5.5 — Agency Operations Automation (AOA)** | 🟢 **ACTIVE** |
| E5.5-A0 — architecture checkpoint | ✅ CLOSED AND PUSHED (`cb6e440a`, docs only) |
| AO-FIX-01 … AO-FIX-06 — small audit fixes (§A7) | ✅ CLOSED AND PUSHED (last: `4b7abff2f8c88c5d38a9b32ceef7ca6a4b1f54b9`) |
| AOA-01-I1 — business audit foundation, safe Admin log/audit UI (AO-04), staff access matrix | ✅ COMPLETED (commit `feat: establish AOA business audit foundation (AOA-01-I1)`); **AOA-01 as a whole stays 🟢 ACTIVE** |
| AOA-01-I2A — secure `IncomingInquiry` access + Admin allocation (G1, G2, G3, G4, G6) | ✅ COMPLETED (commit `feat: secure incoming inquiry access and allocation (AOA-01-I2A)`); verified on SQLite `:memory:` + disposable-DB browser QA; **MySQL concurrency NOT verified**; **G5, G7, G8 remain OPEN → AOA-01-I2B is NEXT**; AOA-01 stays 🟢 ACTIVE |
| AOA-01-I2B — client privacy (G5), Booking reassignment reason + audit (G7), stale notification previews (G8) | ✅ COMPLETED (commit `feat: secure client visibility and booking reassignment (AOA-01-I2B)`); verified on SQLite `:memory:` + disposable-DB browser QA (360/1200 px); **MySQL concurrency NOT verified**; **AOA-01 stays 🟢 ACTIVE until the owner accepts closure and the disposable-MySQL checks are recorded** |
| AOA-01-I3 — independent closure verification (MySQL gate, e-mail-existence disclosure, delayed notifications, access matrix) | ✅ VERIFICATION COMPLETED (docs only, matrix §8): migration chain, `audit_events` schema and true multi-process row-lock concurrency of the I1/I2A/I2B transactions **VERIFIED on a disposable MySQL 9.7.1** (canonical never touched; production engine unknown). Findings: **I3-F1** Booking lifecycle writes (confirm/cancel/complete/update) are not re-authorized under a lock — a former Manager's in-flight request can still change status after reassignment (low–medium); **I3-F2** a queued e-mail for a former Manager fails (retried, then `failed_jobs`) without leaking or sending (low); **I3-F3** one-bit client-e-mail existence oracle for Managers (equal to the pre-existing public `/register` and `/forgot-password` oracle) — **owner decision required**. **AOA-01 stays 🟢 ACTIVE**; follow-up slice **AOA-01-I4** proposed |
| AOA-01-I4 — Booking write re-authorization under row lock (I3-F1) and graceful skip of stale queued mail (I3-F2) | ✅ COMPLETED (commit `fix: guard booking writes and skip stale mail (AOA-01-I4)`); **I3-F1 FIXED** (confirm/cancel/complete/update via the new `BookingLifecycle` service; chat `store` — the race was demonstrated and fixed too) and **I3-F2 FIXED** (the three chat-link mailables skip a recipient who no longer participates, at delivery time). Verified on SQLite PHPUnit (1631 tests / 12524 assertions), on a **disposable MySQL 9.7.1 with true multi-process barriers** (47 checks pass on the fixed code; the same harness fails 21 checks on the pre-fix HEAD) and in Chrome QA at 360/1200 px (31 checks each). Matrix §9. **I3-F3 stays OPEN (owner decision)**; **AOA-01 stays 🟢 ACTIVE** — not closed by this slice |
| AOA-01-I5 — Booking document, client password-setup and deleted-message mail hardening (I4 residuals) | ✅ COMPLETED (commit `fix: harden booking document and client access actions (AOA-01-I5)`); **FIXED:** Booking document upload and delete re-authorize under the Booking row lock (staged file cleaned on refusal; file removed after commit), document download re-reads the responsible Manager immediately before streaming, `sendClientPasswordSetup` decides under the lock and mails after commit (the inquiry flow's no-op lock is now a real transaction), and a queued chat e-mail whose message was deleted is skipped instead of failing (`NewMessageReceived` now carries the message id). **Reviewed, non-exploitable:** Tourist document download (owner-based), document metadata (no endpoint). Verified on SQLite PHPUnit (**1650 tests / 12613 assertions**), on a **disposable MySQL 9.7.1 with true multi-process barriers** (all checks pass; the same harness fails 16 checks on the pre-fix HEAD; stress 6×25 ops ×3 runs, no deadlock/lock-wait/orphan file) and in Chrome QA at 360/1200 px (37 checks each). Accepted limits: an already-started download or e-mail cannot be recalled. New observation **I5-F1** (a deleted message's database notification keeps a ≤120-char preview) and the `Tour::increaseAvailableSeats()` concurrency note are recorded as follow-ups, **not** fixed. Matrix §10. **I3-F3 stays OPEN (owner decision)**; **AOA-01 stays 🟢 ACTIVE** — not closed by this slice |
| AOA-01-I6 — Deleted chat message no longer survives in notifications (I5-F1) | ✅ COMPLETED (commit `fix: protect deleted chat content in notifications (AOA-01-I6)`); **FIXED:** messages are hard-deleted, but the recipient's database notification kept a ≤120-char preview that the header bell still displayed (reproduced: 11 of 16 new tests fail on the unfixed code). One server-side visibility rule (`MessageNotificationVisibility`) now shows/counts/opens a message notification only while its `message_id` still exists in `messages` — for **every** role incl. Admin, for historical snapshots and for any deletion path; `NotificationController::open` uses the same rule (deleted ⇒ 404, not marked read). Deleting a message additionally redacts the stored preview (`Сообщение удалено`) in the **same transaction**; rows are kept. Message-deletion authorization (sender or Admin), G8 reassignment rules and Admin chat read/write rights are unchanged. Verified on SQLite PHPUnit (**1666 tests / 12787 assertions**), the same new tests on **disposable MySQL 9.7.1** (16/16; 11 fail pre-fix) and Chrome QA at 360/1200 px (42 checks each). Matrix §11. **I3-F3 stays OPEN (owner decision)**; **AOA-01 stays 🟢 ACTIVE** — not closed by this slice |
| AOA-01-I7 — Privacy-safe client verification and Admin resolution (I3-F3, **OWNER_APPROVED option C**) | ✅ COMPLETED (commit `feat: add privacy-safe client verification (AOA-01-I7)`); **I3-F3 RESOLVED.** A Manager no longer creates Tourist accounts and no longer learns whether a client e-mail exists: the two creation paths that revealed it (`POST /bookings` with `is_new_client=1`, inquiry `client/create`) now only submit a **client-verification request** (new table `client_verification_requests`, one additive migration) whose Manager-facing outcome is byte-identical for a new e-mail, another Manager's client, the Manager's own client, an Admin or Manager e-mail, an inactive account and a differently cased e-mail — submission never queries `users` by e-mail, so the response (and its timing) does not depend on existence. Admin alone resolves each request in one transaction under a row lock: **link an existing active Tourist**, **approve creation of a new Tourist** (existing safe `StaffClientAccounts` pattern: random hashed password, unverified e-mail, no mail), or **reject with a safe comment**; creation is refused when the e-mail belongs to any account; the resulting Manager access is limited to that client's name/e-mail/phone for 30 days (`ClientVisibility`), never to foreign Bookings, chat or inquiries, and is re-checked against current ownership (a reassigned inquiry or a demoted/deactivated requester blocks approval). Duplicate-request key, per-Manager pending cap (25) and rate limit (10/min); audit events `client_verification.requested/linked/created/rejected` (no e-mail/phone/names) in the same transaction (audit failure rolls the resolution back). Verified on SQLite PHPUnit (**1706 tests / 13255 assertions**), on a **disposable MySQL 9.7.1** (migration chain, 240 focused/related tests, multi-process concurrency S1–S4) and in Chrome QA at 360/1200 px (77 checks each). Matrix §12. **AOA-01 was not closed by this slice** (closed afterwards by the owner-approved documentation checkpoint AOA-01-CLOSE, next row) |
| **AOA-01 — Data visibility & audit (formal closure, AOA-01-CLOSE)** | ✅ **CLOSED — OWNER ACCEPTED WITH TRACKED RESIDUALS** (documentation-only checkpoint, no application change). The owner formally accepted the **development stage** I1–I7 as implemented and verified (evidence: slice rows above, matrix §6–§12); every residual risk is **kept OPEN** in the register §A7.1 with an explicit release gate. This is **not** a declaration of production readiness and does **not** authorise deployment, remove any privacy safeguard, or close unrelated findings (AO-02, AO-03, AO-06, AO-07, AO-08, AO-09, AO-12). E5 stays CLOSED; E5.5 stays 🟢 ACTIVE; E6 stays PENDING; production stays NOT DEPLOYED. **NEXT: AOA-02** (§A9) |
| E6 — Production deployment / operations | ⬜ PENDING |
| **Production** | ❌ **NOT DEPLOYED** (current REG.RU hosting remains in use) |

**Nothing in E5.5 beyond the architecture checkpoint, the small AO-FIX-01…06 fixes (§A7) and AOA-01-I1 is implemented.** AOA-01-I1 added only: the append-only `audit_events` table with its writer (`AuditRecorder`, **not yet wired into any business process**), the Admin-only audit page (`/cabinet/admin/audit`), a safe Admin technical-log summary (AO-04), and the read-only staff access matrix [`docs/AOA-01-I1-staff-access-surface-matrix-2026-10-20.md`](AOA-01-I1-staff-access-surface-matrix-2026-10-20.md) (evidence, not a roadmap). The owner-approved access and assignment rules (§A3–§A4) are approved requirements only; the Manager privacy gaps in §A5 are **not fixed** (reproduced at runtime in the matrix: G1–G8). No `Inquiry`, `OperatorBooking`, ledger, Bridge, VK or outbound-e-mail feature exists in code. **AOA-01-I2A (2026-10-21) then closed the legacy-`IncomingInquiry` gaps G1, G2, G3, G4 and G6 only** (Manager sees/acts on own assigned inquiries only, 404 otherwise; Admin-only direct assignment and reason-required reassignment, both recorded through `AuditRecorder` in the same transaction; authorization before idempotent returns). The complete Manager privacy contract is **not** implemented: G5 (tourist directory / client lookup), G7 (Booking reassignment reason/history) and G8 (stale notification previews) remain open (AOA-01-I2B). No new `Inquiry` entity, migration or production change.

## A2. Current repository state

- Branch: `db-rebuild-stage3`.
- **Live HEAD:** Git is authoritative (`git rev-parse HEAD`); this file deliberately does not track it commit by commit. Distinct reference points: the *last verified test checkpoint* (below); the *architectural decision checkpoint* `cb6e440a`; *historical Project Sources checkpoints* (ledger below). No workflow may require every application commit to rewrite a SHA in several documents.
- **Last verified test checkpoint:** application commit `4b7abff2f8c88c5d38a9b32ceef7ca6a4b1f54b9` (`fix: correct narrow tourist chat header layout (AO-05)`) — **1478 tests / 11644 assertions, 0 failures, 0 errors** (PHP 8.3.32, PHPUnit 11.5.56, Laravel 12.65.0, SQLite `:memory:`), as reported by AO-FIX-06. This is the last *reported* baseline; ROADMAP-SYNC-02 is documentation-only and did **not** rerun the suite. Documentation-only commits never rerun it.
  - AOA-01-I1 (PHP 8.4.13, same command, SQLite `:memory:`): **1528 tests / 11806 assertions, 0 failures, 0 errors** — +50 tests from the new `AuditEventFoundationTest` (31), `AdminAuditPageTest` (12) and `AdminTechnicalLogSafetyTest` (7); one existing assertion label updated in `AdminLogsTest`. Same single deprecation notice.
  - AOA-01-I2A (PHP 8.4.13, same command, SQLite `:memory:`): **1561 tests / 12166 assertions, 0 failures, 0 errors** — +33 tests: new `IncomingInquiryAccessAllocationTest` (32) plus the deliberate rewrite of the permissive E5 expectations in `IncomingInquiryWorkflowTest` / `IncomingInquiryStaffAccessTest` (listed in the matrix §6). Same single deprecation notice. The CLI needs `-d xdebug.mode=off -d memory_limit=2048M` (the default 128M limit is exhausted by the full suite).
  - AOA-01-I2B (PHP 8.4.13, same command, SQLite `:memory:`): **1599 tests / 12401 assertions, 0 failures, 0 errors** — +38 tests: new `ClientVisibilityPrivacyTest` (13), `BookingReassignmentAuditTest` (15), `BookingNotificationPrivacyTest` (10) plus deliberate fixture/expectation updates in existing tests (listed in the matrix §7.3). Same single deprecation notice.
  - AOA-01-I5 (PHP 8.4.13, same command, SQLite `:memory:`): **1650 tests / 12613 assertions, 0 failures, 0 errors** — +19 tests: new `BookingDocumentAndClientAccessRaceTest` (15) and 4 deleted-message tests in `StaleBookingMailDeliveryTest`; one existing assertion line in `NewMessageEmailPreferenceTest` updated (`messageId`). Same single deprecation notice.
  - AOA-01-I6 (same command, SQLite `:memory:`): **1666 tests / 12787 assertions, 0 failures, 0 errors** — +16 tests (new `DeletedMessageNotificationPrivacyTest`); three existing tests adjusted: the constant `from messages` query budget of the bell-bearing pages 2→4 (`AdminChatListQueryEfficiencyTest`, 2× `ManagerCabinetE3RedesignTest`; the no-N+1 equality is unchanged) and two raw-payload tests now carry a real `message_id` (`CabinetHeaderNotificationBellTest`, `CabinetNotificationOpenTest`). Same single deprecation notice.
  - AOA-01-I7 (same command, SQLite `:memory:`): **1706 tests / 13255 assertions, 0 failures, 0 errors** — +40 tests: new `ClientVerificationWorkflowTest` (39) and one Admin new-client Booking test. **Deliberately changed legacy expectations:** the tests that exercised a *Manager* creating a client (with a Booking or from an inquiry) now run as the **Admin** — the only role that still creates accounts directly (`BookingCreationOwnershipAndAtomicityTest`, `StaffClientCredentialHardeningTest`, `IncomingInquiryWorkflowTest`; Admin Bookings have no Manager and status `new`), and the two Manager-specific privacy tests in `ClientVisibilityPrivacyTest` were rewritten to assert the new contract (Manager gets the same neutral response for an existing and a new e-mail; Admin keeps the explicit conflict message). `StaleBookingMailDeliveryTest::runWorker` now passes `--memory=0`: `queue:work` defaults to `--memory=128`, and the shared PHPUnit process grew past 128 MB, so the worker quit after its first job (latent order/size-dependent fragility, not an I7 logic change). Same single deprecation notice.
  - **AOA-01-I7 count reconciliation (checked against Git, `1366b334..f77839e9`):** net **+40** = 43 test methods added − 3 removed (the three Manager-specific legacy tests were replaced by renamed rewrites in the same files). The 43 = **39** in the new `ClientVerificationWorkflowTest` + **1** new Admin new-client Booking test + **3** renamed rewrites; the "39 new tests" figure counts only the new class. These numbers come from the I7 run; **they were not re-run in the closure task**. MySQL evidence is **not** a green full suite: in I7, 240 of 241 focused/related tests passed on a disposable MySQL 9.7.1; the failing `AuditEventFoundationTest::test_records_a_valid_event` depends on MySQL JSON key ordering and fails identically on the pre-I7 checkpoint (test-portability item **R-N**, §A7.1). The dedicated multi-process MySQL concurrency checks (I3–I7) are separate from, and unaffected by, that portability issue.
  - history: AOA-01-I7 1706 / 13255; AOA-01-I6 1666 / 12787; AOA-01-I5 1650 / 12613; AOA-01-I4 1631 / 12524; AOA-01-I2B 1599 / 12401; AOA-01-I2A 1561 / 12166; AOA-01-I1 1528 / 11806; AO-FIX-06 1478 / 11644; AO-FIX-02 1414 / 9905; AO-FIX-01 1403 / 9799; E5 closure 1393 / 9759; E5-A3 1383 / 9682; E4 closure 1286 / 8628; E4 entry 1242 / 8056; E3-A5 1233 / 8023; E2 1051 / 7180; E1 1001 / 7013.
  - 1 PHPUnit deprecation: the runner notice "Your XML configuration validates against a deprecated schema" (`phpunit.xml`); not a functional failure.
  - Canonical MySQL is never touched by tests. Full-suite command: `php -d xdebug.mode=off -d memory_limit=2048M vendor/bin/phpunit` with the project PHP.
- **Infrastructure:** production hosting remains **REG.RU**. Timeweb Cloud server `web-prod-spb-01` (2 vCPU / 4 GB / 50 GB NVMe, Saint Petersburg) is **PREORDERED / waiting for provider capacity — NOT production** (not independently re-verified in ROADMAP-SYNC-01). No DNS change; domains stay with REG.RU. No private key paths or secrets are recorded in the repository.
- **Canonical MySQL `turfirma_rebuild_v4`:** E5 migrations `2026_10_01_000000…` and `2026_10_02_000000…` are **not applied**. The AO fixes added no migration. The AOA-01-I1 migration `2026_10_20_000000_create_audit_events_table` is **not applied** either and was **tested only on SQLite** (PHPUnit `:memory:` and a disposable file: migrate → rollback → migrate); MySQL validation is required before E6 (see the matrix document §5). **AOA-01-I3 (2026-10-21) applied the whole migration chain, including this migration, on a *disposable* MySQL 9.7.1 successfully** (matrix §8); the canonical database is still untouched and the migration is still not applied there.
- **Project Sources:** external versioned snapshots only. All existing sets (2026-09-23 `a1d72a40`, 2026-10-01 `c68b6814`) are historical and predate the AO fixes. **Not regenerated in ROADMAP-SYNC-02:** the existing generator contains hard-coded HEAD assumptions and a destructive promotion step, so a separate reviewed guarded generator-modernization task is required before any new authoritative package. Existing archives and wrappers are preserved unmodified. See `docs/README.md` §8.1.
- **Private evidence (never in Project Sources):** `C:\Avilona_private\E5\…`, `C:\Avilona_private\E4\…`; Screenshot Audit Pack under `C:\Avilona_private\Audit\…`. The independent Astra analysis is private and not stored in this repository.
- Opening hours remain **`PENDING_BUSINESS_DECISION_OPENING_HOURS`** (home 10:00–20:00 weekdays vs contacts 11:00–20:00 by appointment) — `BUSINESS_DECISION_REQUIRED`, must be decided before E6.

### Checkpoint ledger (historical — none of these is the current HEAD)

- Application HEAD at E5 closure: `c56e330685ffa8953b22c803d6dce5334c69e81b` (`fix: complete final tour search polish (E5-A4)`); parent `00ea2f0440f0a25b6ecd161aadedb804ce9e8ac2` (`fix: remove plaintext staff client credentials (E5-A3.1)`).
- E5 documentation closure: `c68b6814381cdd1e8921946dd7bbe153a13eb66f` (`docs: close E5 and prepare final audit`).
- E5.5-A0 architecture checkpoint: `cb6e440a` (`docs: establish E5.5 agency operations architecture`).
- Documentation checkpoint after E4 closure (base of the historical Project Sources set of 2026-09-23): `a1d72a4034b1d76b35e0033f4c1e2a69121ee06b` (`docs: close E4 and prepare E5`).
- Application HEAD at E4 closure: `55a9fcaf6371ef0c749bb668c9b27783d1df358c` (`fix: close final E4 application polish`).
- Documentation checkpoint after E3-A6 / entering E4: `c89e923f966f5aaa8bb972e2d6944018f8dce4a8`; application HEAD at E3-A6: `ed550df8989b44e7305bdce6b6f5f063b82a2616`.
- Documentation checkpoint after E3-A5: `20cde21dda2c38682c796214fbb2401e3f1f7804`; application HEAD at E3-A5: `9fee7dfb990c7a6c18fc9dcf9205e3db3dca24e6`.
- Documentation checkpoint after E2 closure: `886bde9813a088d56d7db1e6b963f6f1d05ab4b2`; after E2-A5: `eb88f0fc02b2bea37f4817c7cfc3ace0ef002caa`; application HEAD at E2-A7: `35f91b9e270cf68654877d42fc8b0d0d59d12458`.
- E1 closure application commit: `08d0626311234faa06dedf2828cb878805241990`; Stage 13 functional HEAD: `dba20e2c6e2e66b6f69f33710b2626b3fe181e31`.

## A3. Owner-approved access control — `OWNER_APPROVED`, NOT YET FULLY IMPLEMENTED

This is a **final owner business decision**. It resolves former open decision U-1. The current code does **not** yet enforce it (see §A5).

### Manager

A Manager may see **only**:

- their own assigned inquiries;
- their own assigned bookings;
- private client communications associated with work they are authorized to handle;
- their own relevant operational information.

A Manager must **not** see:

- another Manager's inquiries, bookings or client conversations;
- Admin's inquiries unless personally assigned;
- unassigned incoming inquiries.

This must not leak through: list pages; detail routes; search; AJAX/JSON; API; counts; notifications; attachments; chat endpoints; document download links. Client lookup needed for legitimate account selection must not grant access to another employee's private booking or correspondence history. When an inquiry is reassigned away from a Manager, it becomes "another employee's" inquiry and the rule above applies to the former assignee.

### Admin

Admin may: see all inquiries; see all bookings; see all related conversations for oversight; manage assignment; retain inquiries personally; reassign when reasonably necessary.

**Existing E3 Admin chat contract is preserved and not broadened:** Admin may *read* any Booking chat but may *write* only when personally assigned, per the existing Booking chat authorization rules.

## A4. Owner-approved inquiry assignment — `OWNER_APPROVED`, NOT YET IMPLEMENTED

| Origin | Assignment |
|---|---|
| Manager-created | Automatically assigned to the creating Manager. No Admin approval. |
| Admin-created | Assigned to Admin by default, or explicitly to another eligible employee chosen by Admin. |
| Automatic / external | **Unassigned** until Admin allocates it, using staff workload and actual agency needs. A Manager may **not** claim an unassigned external inquiry. |

**Reassignment.** Admin may reassign only when genuinely necessary; it must not be arbitrary or silent. A future implementation must record: previous responsible employee; new responsible employee; the Admin who performed it; timestamp; a **required reason**. The inquiry record and its history must remain available to authorized Admin. Ordinary Managers must not be able to reassign anyone else's inquiry.

"Eligible employee" is not defined beyond what the code already uses for booking assignment (`User::assignableToBookings`); no new eligibility rule is invented here.

### Financial business rules — `OWNER_APPROVED`

- The tour operator's displayed selling price **already includes** the agency commission; commission is **never added on top** of the client price.
- A discount reduces the customer's price.
- Agency commission and margin are strictly **internal**; the client never sees internal margin or operator settlement details.
- Original operator currency and the operator-specific exchange rate are preserved where relevant (no substitution with Central Bank rates).
- Client payments and operator payments are separate. Price, payment and status dimensions are independent (see AO-09).
- No automatic operator booking and no automatic payment.
- **Not decided (`BUSINESS_DECISION_REQUIRED`):** discount approval thresholds, negative-margin permissions, who may enter payments/refunds. Do not invent them.

Further architectural invariants (documents, Bridge, platform) are in `docs/E5.5-agency-operations-automation-architecture-2026-10-19.md` §3.

## A5. E5.5 implementation gaps (verified against code at `6cbdbcda`; later commits touched no inquiry/visibility code)

E5's `IncomingInquiry` workflow predates the owner decisions in §A3–§A4. E5 stays CLOSED (its closure was correct for its own scope); the following are **gaps to be closed by AOA-02 and AOA-04**, not defects reopening E5. **HIGH-PRIORITY SECURITY/PRIVACY GAP: the Manager visibility boundary is not enforced today and must be closed server-side before further staff workflows are built.** Hiding UI links is not authorization; enforcement is required on every route, data query, JSON response, document, chat and search surface.

| Area | Current behaviour (code) | Required (§A3–§A4) |
|---|---|---|
| Inquiry list (`Manager\IncomingInquiryController::index`) | Lists **all** inquiries to every Manager/Admin; no per-user scoping | Manager: own assigned only; Admin: all |
| Inquiry view (`IncomingInquiryPolicy::view`) | Any staff | Manager: only if personally assigned |
| Claim (`IncomingInquiryPolicy::claim`) | Any staff may claim an unassigned inquiry | Manager may not claim; Admin allocates (Admin may self-assign) |
| Close (`IncomingInquiryPolicy::close`) | Any staff may close while unassigned | Re-evaluate: unassigned inquiries are not visible to Managers |
| Reassign | Admin only (matches), but **no required reason and no previous/new/actor/timestamp history** (`IncomingInquiryWorkflow::reassign`) | Required reason + durable history readable by Admin |
| Client lookup (`IncomingInquiryClientMatcher`) | Searches active Tourist accounts by name/e-mail/phone | Must not expose another employee's booking/correspondence history; **not audited beyond the matcher in this task** |
| Other surfaces (Booking lists, search, AJAX/JSON, API, counts, notifications, attachments, chat endpoints, document links) | **Inventoried read-only in AOA-01-I1** — see the [access matrix](AOA-01-I1-staff-access-surface-matrix-2026-10-20.md): Booking/chat/message/document scoping is VERIFIED server-side; confirmed gaps G5 (tourist directory), G6 (service-level idempotent returns skip authorization), G7 (Booking reassignment has no reason/history), G8 (former manager keeps notification preview). **G6 fixed in AOA-01-I2A; G5, G7, G8 fixed in AOA-01-I2B (matrix §6–§7).** | AOA-02 turns the matrix into the policy layer and regression tests |
| Manager-created / Admin-created `Inquiry` | Does not exist (no manual creation; `Inquiry` entity not built) | AOA-03 / AOA-04 |
| Admin chat write | Existing E3 contract (read any, write only when assigned) | Unchanged |

**AOA-01-I2A update (2026-10-21):** the first five rows above (inquiry list, view, claim, close, reassign) are **resolved** for the legacy `IncomingInquiry` workflow — Manager list/view/actions are scoped to personally assigned inquiries (404 for foreign/unassigned), claim is Admin-only self-assignment, close follows `process`, and Admin direct assignment plus reason-required reassignment write audit events in the same transaction. The client-lookup row, the Booking/notification surfaces (G5, G7, G8) and the new `Inquiry` entity rows are **unchanged and open**.

**AOA-01-I2B update (2026-10-21):** the client-lookup row and the Booking/notification surfaces are **resolved to the extent verified** (matrix §7). Owner decision (OWNER_APPROVED): a Manager sees only clients tied to their *current* inquiries/bookings; Admin sees the full directory; association of an already-existing client with another Manager's work is done by Admin. Implemented as one SQL-level rule (`ClientVisibility`) used by the clients page, booking-create dropdown and validation, inquiry candidates/search/select; Booking reassignment now needs a reason category (+ optional note) and writes `booking.reassigned` / `booking.assigned` audit events atomically; message notifications are shown only while the Booking still belongs to the recipient. The new `Inquiry` entity rows remain open (AOA-02…).

## A6. Canonical AOA implementation roadmap

The AOA-01 … AOA-18 identifiers and titles below are the **project's dependency-ordered plan as organised in the E5.5-A0 checkpoint**. They are **not** a verified mapping to the private Astra report (which is not available in this repository); if that report is later supplied, reconcile by adding a cross-reference column — do **not** renumber silently, because AOA-NN is already cited in the E5.5 architecture document and other docs. This is a plan of **18 sequenced slices, not 18 simultaneous releases and not an irreversible requirement for the initial release.** Each slice gets its own guarded plan (exact allowed paths), tests and commit; the **Depends on** column is the binding part, the release grouping is a recommendation the owner may re-cut.

Implementation status of every slice below: **NOT STARTED**, except **AOA-01**, which is **ACTIVE**: internal slice **I1 is completed** (audit foundation, safe Admin audit/log UI, access matrix) and **I2A is completed** (secure legacy `IncomingInquiry` access + Admin allocation; G1, G2, G3, G4, G6 resolved to the extent verified in the matrix §6). **I2B is completed** (G5 client visibility per the owner decision, G7 Booking reassignment reason/audit, G8 stale notification previews — resolved to the extent verified in the matrix §7). AOA-01 stays ACTIVE only until the owner accepts closure and the disposable-MySQL concurrency checks are recorded. **I3–I6 and I7 are completed** (I7: privacy-safe client verification, owner option C); the MySQL gate is satisfied and **AOA-01 is CLOSED (owner-accepted with tracked residuals, AOA-01-CLOSE, 2026-10-23)**.

| # | Workstream | Slice | Depends on | Requirement status | Blocked by |
|---|---|---|---|---|---|
| AOA-01 | A Data visibility & audit | Append-only business audit events + safe staff audit/log UI (covers AO-04). **I1 ✅ completed** (foundation + UI + access matrix). **I2A ✅ completed**: `IncomingInquiry` Manager visibility scoping (404 for foreign/unassigned), Admin-only direct assignment and reason-required reassignment with audit events (`inquiry.assigned`, `inquiry.reassigned`) in the same transaction, authorization before idempotent returns. **I2B ✅ completed**: `ClientVisibility` (Manager = clients of current work, Admin = all; neutral conflict messages), Booking reassignment with reason category + audit (`booking.assigned`, `booking.reassigned`) in the same transaction with stale-form rejection, notification visibility by *current* Booking ownership, narrow phone-number rejection in `AuditMetadata`. **I7 ✅ completed**: Manager-to-Admin client verification (`client_verification_requests`, neutral Manager outcome, Admin-only link/create/reject, audited). Overall: ✅ **CLOSED — OWNER ACCEPTED WITH TRACKED RESIDUALS** (AOA-01-CLOSE; not production readiness; residuals §A7.1) | — | I1 + I2A + I2B + I4 + I5 + I6 + I7 IMPLEMENTED (I3 = verification only) | residual register §A7.1 |
| AOA-02 | A Data visibility & audit | Visibility & assignment **policy layer** implementing §A3 across all surfaces (policy/surface matrix, eligible-employee rule) | AOA-01 | APPROVED REQUIREMENT (§A3) / PROPOSED IMPLEMENTATION | — (former U-1 resolved) |
| AOA-03 | B Inquiry | Provider-independent `Inquiry` entity, additive migration, link/backfill from `IncomingInquiry` as provenance | AOA-02 | PROPOSED IMPLEMENTATION | — |
| AOA-04 | B Inquiry | Manual Inquiry creation, ownership, responsibility & assignment per §A4, Admin allocation, reassignment with reason + history | AOA-03 | APPROVED REQUIREMENT (§A4) / PROPOSED IMPLEMENTATION | — (former U-1 resolved) |
| AOA-05 | B Inquiry | Explicit Inquiry → Booking conversion on the new entity (E5 atomicity/idempotency kept) | AOA-04 | PROPOSED IMPLEMENTATION | — |
| AOA-06 | C OperatorBooking | `OperatorBooking` domain, manual entry, provenance | AOA-05 | PROPOSED IMPLEMENTATION | — |
| AOA-07 | C OperatorBooking | Import snapshots, diff, controlled approval (source-agnostic) | AOA-06 | PROPOSED IMPLEMENTATION | — |
| AOA-08 | D Pricing & finance | Pricing / commission / discount / currency model + internal-only data separation guard | AOA-06 | APPROVED REQUIREMENT (§A4 rules) / PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-2, U-4) |
| AOA-09 | D Pricing & finance | Customer receipts ledger; `paid_amount` as projection | AOA-08 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-3) |
| AOA-10 | D Pricing & finance | Operator payments / settlement ledger | AOA-08 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-3, U-4) |
| AOA-11 | D Deadlines | Deadlines (payment/document/travel) with staff reminders | AOA-06 | PROPOSED IMPLEMENTATION | — |
| AOA-12 | E Documents | Template engine, immutable revisions, secure storage | AOA-05 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-6) |
| AOA-13 | E Documents | Explicit client publication; operator-issued documents handling | AOA-12 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-6, U-10) |
| AOA-14 | F Bridge / adapters | Operator adapter contract and **Bridge import protocol** (API → Bridge → manual); first adapter | AOA-07 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-5) |
| AOA-15 | F Bridge / extension | Avilona Bridge browser connector / extension (thin, staff-operated; no stored operator passwords, no CAPTCHA bypass, no silent financial updates) | AOA-14 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-5) |
| AOA-16 | G Communications | Outgoing e-mail from Avilona objects (internal-data guard enforced) | AOA-08, AO-01 (CLOSED) | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-8); AO-02/AO-03 first |
| AOA-17 | G Communications | VK community integration | AOA-02 | PROPOSED IMPLEMENTATION | BUSINESS_DECISION_REQUIRED (U-7); AO-02/AO-03 first |
| AOA-18 | H QA | Integrated QA and security review of all shipped slices; E6 hand-off | all shipped slices | PROPOSED IMPLEMENTATION | — |

After AOA-18 (not slices, not renumbered):

1. **Final independent audit** of the integrated result — `PROPOSED IMPLEMENTATION` (scope as listed in Part B "After E5").
2. **E6 — production deployment / operations** — `PENDING` (§A9).

### Recommended release grouping (PROPOSED — may be re-cut by the owner)

- **First release (core operations):** AOA-01 → AOA-10, then the AOA-18 QA gate.
- **Second release (incremental):** AOA-11 deadlines; AOA-12/13 limited document templates with explicit publication; AOA-16 outgoing e-mail.
- **Optional extensions (each separately approved, not required for E6):** AOA-14/15 operator adapter and Bridge; AOA-17 VK.

## A7. Audit observations (AO) status

Small independent fixes are named `AO-FIX-NN` (chronological fix id) and cite the AO finding they close. Each commit below was verified by ROADMAP-SYNC-02 to be an ancestor of the checkpoint `4b7abff2` in `db-rebuild-stage3`.

### Closed

| Fix id | AO | What | Commit | Status |
|---|---|---|---|---|
| AO-FIX-01 | AO-01 | Admin BookingCreated e-mail `children_ages` rendering | `6cbdbcdab1df4b7d7605ed22cc469c53128163a3` | ✅ CLOSED |
| AO-FIX-02 | AO-11 | Tourist password error-bag display and form isolation | `f60b6c2a53b27db271ba2039a46bfd585c258507` | ✅ CLOSED |
| AO-FIX-03 | — (follow-up found during AO-FIX-01/02 work) | Nullable child ages in client e-mail templates | `81fd1370b78a21988369b8e36e3ac2aad0fba336` | ✅ CLOSED |
| AO-FIX-04 | — (follow-up) | Invalid child ages in Booking `show` view | `d5fa53c2be7bee49efbe14f8704da195f1befcb3` | ✅ CLOSED |
| AO-FIX-05 | AO-10 | Russian validation localization | `c1955d7c44a4ac2c64c32a6a25ac8827d20d020d` | ✅ CLOSED |
| AO-FIX-06 | AO-05 | Tourist chat header on narrow screens | `4b7abff2f8c88c5d38a9b32ceef7ca6a4b1f54b9` | ✅ CLOSED |
| AOA-01-I1 | AO-04 | Admin technical-log screen no longer prints raw `laravel.log` text (stack traces carried absolute server paths, SQL, e-mails, tokens). Reproduced first with a synthetic log (5 of 7 new regression tests failed on the old viewer), then replaced by a bounded, sanitized summary (time, level, short message; no traces/context/paths). The log file, its location and rotation are untouched and remain available to server operators. | commit `feat: establish AOA business audit foundation (AOA-01-I1)` | ✅ CLOSED |
| AO-VERIFY-01 | legacy `str_plural()` helper check | Helper is autoloaded and renders valid inputs correctly | none — no code change | ✅ **VERIFIED_WORKING** |

Only the findings listed above are claimed fixed; no unrelated AO finding is implied closed.

### Open

| ID | Observation | Status |
|---|---|---|
| AO-02 | Long unbroken chat message overflow | **OPEN** — before communications expansion (AOA-16/17) |
| AO-03 | Chat timestamp consistency | **OPEN** — before communications expansion (AOA-16/17) |
| AO-06 | Unverified-e-mail Tourist reaches cabinet | **BUSINESS_DECISION_REQUIRED** — business/security policy (U-9) |
| AO-07 | 403/404 ownership-denial inconsistency | **REVIEW** — not automatically a vulnerability; revisit with AOA-02 visibility work |
| AO-08 | Public header switches to hamburger at 1200 px | **UX REVIEW** — not automatically a defect |
| AO-09 | Booking can reach confirmed/completed with zero paid | **BUSINESS RULE REVIEW** — Booking status, client payment and operator settlement are independent by the owner's financial architecture; **no rule that a completed Booking must be fully paid is approved or implied** |
| **AO-12** | **ADMIN CHAT NARROW-VIEWPORT OVERFLOW** — on `/cabinet/admin/chats/{id}` a very long manager name makes the page ~395 px wide at 320–390 px viewports. Independently reproduced during AO-FIX-06; pre-existing; separate Admin chat header markup (not touched by AO-FIX-06). *Id checked: AO-01…AO-11 and AO-FIX-01…06 are the only existing ids; AO-12 is unused.* | **OPEN** — focused responsive fix, scheduled **before the final integrated Browser QA (AOA-18)**; not implemented |

### Verification / follow-up items (not stages)

| Item | Note | Where |
|---|---|---|
| CSS cache-busting | `public/css/cabinet-e3.css` is loaded without explicit cache-busting; stale browser-cached CSS can delay visibility of new responsive styling. No asset-loading change made. | Deployment-readiness / **E6 verification item** |
| Client-facing `BookingCreated` mailable | AO-FIX-03 reported it is **not dispatched** by the ordinary Booking creation flow. Not wired up automatically. | Potential product behaviour to review under communication automation (AOA-16) |
| Child-age display surfaces | AO-FIX-04 did not audit every surface that shows child ages. | Small QA follow-up within AOA-18 integrated UI regression |
| Localization polish | Minor Russian wording differences between Tourist and staff forms; raw list values such as "pdf, doc". Non-blocking. | Polish, no schedule |
| **Tour seat concurrency** (observed AOA-01-I4/I5, **not fixed**) | `Tour::increaseAvailableSeats()` checks the **in-memory** `available_seats + count <= max_tourists` and then issues `increment()`; two concurrent cancellations of different Bookings of the same Tour can both pass the check and push `available_seats` above `max_tourists` (read-then-write, not verified on MySQL). Pre-existing and not an authorization issue; `Booking::persistTransition()` calls it inside the *Booking* row lock, but the `tours` row is not locked. Separate slice (atomic `UPDATE … SET available_seats = LEAST(…)`), with a MySQL multi-process check. | Before booking/seat features are extended (AOA-05…), no schedule |
| ~~**I5-F1 — notification preview after message deletion**~~ **FIXED in AOA-01-I6** | The bell/open path now hides a message notification whose message no longer exists (all roles incl. Admin, historical snapshots included) and `destroy` redacts the stored preview in the same transaction (matrix §11). Residual: a notification created *after* its message was already deleted (listener racing a delete) keeps its snapshot in the table but is never shown. | Done; residual recorded in matrix §11.5 |
| **I7 limitations** (recorded, not fixed) | (1) After approval the Manager can infer, from the account's real name/phone shown in their client list, that the e-mail matched an existing account if the name differs from what they typed — the approved state and wording are identical for link and create, and the Admin makes that choice explicitly. (2) Public `/register` and `/forgot-password` still distinguish known from unknown e-mails (pre-existing, outside this scope). (3) The request stores the submitted name/e-mail/phone in plain columns (needed for the Admin decision) with no retention job. (4) No e-mail/in-app notification to the Admin or Manager about a new request or decision — the Admin sees a sidebar counter, the Manager a status page. (5) Access after approval is a fixed 30-day window; it is replaced by a Booking or an assigned inquiry. (6) `AuditEventFoundationTest::test_records_a_valid_event` fails on MySQL (JSON key order) at the pristine HEAD too — pre-existing, unrelated. | Matrix §12.6 |

### A7.1 AOA-01 residual register and release gates (AOA-01-CLOSE)

Closing AOA-01 accepts the implemented development scope only. **Every item below stays OPEN** and is not fixed unless a later slice says so with evidence. "Gate" is the latest point at which the item must be resolved or explicitly re-accepted by the owner; no retention period, policy value or production fact is invented here.

| ID | Residual | Status | Owner slice / release gate |
|---|---|---|---|
| R-A | Public account enumeration: `/register` and `/forgot-password` still disclose whether an e-mail has an account (pre-existing, outside the Manager workflow fixed in I7). | OPEN | Review and resolve **before E6 production** (security/privacy follow-up; relates to U-9 / AO-06) |
| R-B | `client_verification_requests` stores submitted name, e-mail and phone in plain columns; the 30-day Manager visibility window is **not** a retention/deletion policy. | OPEN — `BUSINESS_DECISION_REQUIRED` (retention period not decided) | Retention decision **and** implementation before production (with U-10) |
| R-C | After an Admin link, the Manager legitimately sees the client's profile, which can indirectly reveal that the account already existed. Known functional privacy limit; perfect non-disclosure after legitimate access is not promised. | ACCEPTED LIMIT (documented) | None; re-read with AOA-02 policy work |
| R-D | A pending verification creates no Booking; the Manager may need to re-enter trip details. | OPEN | AOA-02 / AOA-03 must provide durable inquiry/draft continuity |
| R-E | MySQL concurrency was verified only on a disposable **MySQL 9.7.1**; the production engine/version is not established. | OPEN | Compatible-engine verification **before E6** |
| R-F | Audit events are append-only at application level only; raw database writes remain possible. | OPEN | Production DB permission/hardening review before E6 |
| R-G | Already sent e-mails and already started file downloads cannot be recalled. | ACCEPTED TECHNICAL LIMIT | None |
| R-H | File deletion after database commit can fail and leave an orphaned physical file (documents, attachments). | OPEN | Cleanup/reconciliation before operational readiness (E6 gate) |
| R-I | Password-setup broker throttle is non-atomic; concurrent requests may both pass. | OPEN (bounded reliability follow-up) | Small reliability slice before E6 |
| R-J | `Tour::increaseAvailableSeats()` is check-then-increment (possible lost update; not verified on MySQL). | OPEN | Tour inventory work (before booking-capacity features / E6) |
| R-K | Notification residuals (matrix §11.5): snapshot rows created after a message deletion, JSON-path redaction scan, already-rendered bell until reload, same-second ordering ties, delivery limits. | OPEN / ACCEPTED LIMITS as listed there | AOA-16 communications work; recheck before E6 |
| R-L | Client-verification operations: no Admin/Manager notification for new or resolved requests; fixed 30-day access window; no manual revoke; request lifecycle and Admin workload not managed. | OPEN | Operational-readiness / workflow slice (with AOA-04 allocation and AOA-16 notifications) before E6 |
| R-M | Unrelated findings AO-02, AO-03, AO-06, AO-07, AO-08, AO-09, AO-12 keep their §A7 status; closing AOA-01 does **not** close them. | OPEN (per §A7) | As stated in §A7 |
| R-N | Test portability: `AuditEventFoundationTest::test_records_a_valid_event` (JSON key order) fails on MySQL, and `ManagerCabinetE3RedesignTest::test_dashboard_reuses_precomputed_counts…` is SQLite-specific; `StaleBookingMailDeliveryTest` previously depended on the queue worker's default 128 MB limit (fixed in I7 with `--memory=0`). | OPEN (first two) | Small test-portability slice before any MySQL-based CI/gate |
| R-O | I7 limitations not listed above: request timing reasoned from the code path, not measured. | OPEN (low) | Re-check during the E6 security review |

## A8. Unresolved owner decisions (not guessed)

| ID | Decision | Status | Blocks |
|---|---|---|---|
| U-1 | Manager visibility of unassigned/colleagues' inquiries | ✅ **RESOLVED** — `OWNER_APPROVED` (§A3, §A4) | — |
| I3-F3 | Manager must not learn whether a client e-mail already has an account | ✅ **RESOLVED** — `OWNER_APPROVED` option C (uniform "request passed to the Administrator" + Admin-resolved link/create); **implemented in AOA-01-I7** | — |
| U-2 | Manager discount thresholds, negative-margin permissions, approval flow | BUSINESS_DECISION_REQUIRED | AOA-08 |
| U-3 | Who may enter payments and refunds | BUSINESS_DECISION_REQUIRED | AOA-09, AOA-10 |
| U-4 | Operator commission / settlement worked examples (per operator) | BUSINESS_DECISION_REQUIRED | AOA-08, AOA-10 |
| U-5 | First operator for Bridge / adapter work | BUSINESS_DECISION_REQUIRED | AOA-14, AOA-15 |
| U-6 | Approved document templates and legal authority to issue them | BUSINESS_DECISION_REQUIRED | AOA-12, AOA-13 |
| U-7 | Official VK community and staff reply privileges | BUSINESS_DECISION_REQUIRED | AOA-17 |
| U-8 | E-mail sender identity and Reply-To | BUSINESS_DECISION_REQUIRED | AOA-16 |
| U-9 | Account e-mail-verification policy (AO-06) | BUSINESS_DECISION_REQUIRED | security/product policy |
| U-10 | Document and data retention | BUSINESS_DECISION_REQUIRED | AOA-13, AOA-16 |
| — | `PENDING_BUSINESS_DECISION_OPENING_HOURS` | BUSINESS_DECISION_REQUIRED | E6 |
| — | Tourvisor privacy/legal provider facts (Part B, E5 list) | `PRIVACY_LEGAL_FACT_REQUIRED` | E6 |
| — | AO-09 payment-blocking / warning rules | BUSINESS RULE REVIEW | none until approved |

## A9. NEXT and production readiness

**Order of upcoming work (PROPOSED; one semantic slice at a time, each with its own guarded plan):**

1. ✅ **AOA-01 CLOSED — owner accepted with tracked residuals (AOA-01-CLOSE).** All residuals stay OPEN in §A7.1. **NEXT: AOA-02**, implemented as its own guarded slice (nothing coded yet). Owner-stated direction for the phase that follows (kept high level): preserve `IncomingInquiry` as technical intake; introduce a provider-independent `Inquiry` as the staff business entity; keep the established Manager/Admin visibility and assignment restrictions; keep existing Bookings and historical links; protect E5 conversion idempotency; migrate additively (expand → backfill → verify → switch) without inventing or overwriting history; keep Tourvisor, manual and future VK sources distinguishable; durable inquiry/draft continuity (R-D). **Numbering note (not renumbered):** in the §A6 plan **AOA-02 is the visibility & assignment policy layer** and the provider-independent `Inquiry` entity is **AOA-03**; the guarded AOA-02 plan must state explicitly whether the owner wants that entity foundation inside AOA-02 or kept as AOA-03.
2. **AOA-02** (visibility & assignment policy layer implementing §A3 across all surfaces) → AOA-03 → AOA-04 …, in the dependency order of §A6. AO-02/AO-03 before AOA-16/17.
3. AO-12 (Admin chat narrow-viewport overflow): focused fix, before AOA-18.
4. AOA-18 integrated QA gate (incl. child-age surface follow-up) → final independent audit → E6 (incl. CSS cache-busting verification).

### AOA-01 / AOA-02 dependency note

**Status after AOA-01-I2B (2026-10-21):** G5, G7 and G8 are **implemented** (see the matrix §7); the remaining AOA-01 work is only owner closure plus MySQL verification. **Status after AOA-01-I2A:** the minimal Manager scoping of `IncomingInquiry` list/view/claim/close, Admin direct assignment and reason-required reassignment with audit **are implemented** (see the matrix §6 for evidence and the changed-test list). Gaps G5, G7 and G8 are **not** implemented (AOA-01-I2B). **Earlier status after AOA-01-I1:** the read-only staff-surface inventory proposed below **is done** (see the [access matrix](AOA-01-I1-staff-access-surface-matrix-2026-10-20.md)); the minimal Manager scoping of `IncomingInquiry` list/view/claim, plus Admin direct assignment and reason-required reassignment with audit, was AOA-01-I2 (now implemented as I2A). The §A6 numbering and dependency order are **unchanged**. The original note follows. Concrete tension: AOA-01 (audit foundation, no dependency) precedes AOA-02 (policy layer), yet the Manager visibility gap (§A5) is an exposure that exists *today*, and audit events built first would record activity on surfaces whose access rules are not yet enforced. **Proposed narrow adjustment (PROPOSED, not applied; subject to owner approval of the AOA-01 guarded plan):** AOA-01 additionally includes a *read-only inventory of every staff data surface* (routes, queries, JSON, documents, chat, search, counts) that seeds the AOA-02 visibility matrix, and — if the owner does not want to leave it exposed until AOA-02 — minimal server-side Manager scoping of `IncomingInquiry` list/view/claim. The full policy layer, `Inquiry` entity and assignment workflow stay in AOA-02…AOA-04 exactly as in §A6.

**Production readiness.** `APPLICATION CLOSED ≠ PRODUCTION DEPLOYED.` Production is **not deployed**; the project is **not production-ready** until E6 closes. Known pre-production items: pending E5 migrations on canonical/production; Tourvisor production secrets/webhook; privacy/legal provider facts; opening hours; separate runtime/dependency modernization pass; hosting move REG.RU → Timeweb (if pursued). See Part B "E6".

---

# Part B — Stage history (historical record; status is authoritative in Part A)

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

### E5.5 — Agency Operations Automation (AOA)
🟢 **ACTIVE** — status, owner decisions, implementation gaps, the AOA-01…AOA-18 roadmap, AO findings and NEXT are **canonical in Part A of this file (§A1–§A9)**. The technical architecture, rationale and invariants are in [`docs/E5.5-agency-operations-automation-architecture-2026-10-19.md`](E5.5-agency-operations-automation-architecture-2026-10-19.md) (reference, not a competing roadmap). Sits between CLOSED E5 and pending E6; only E5.5-A0 (architecture, docs), the small AO-FIX-01…06 fixes (§A7) and AOA-01-I1 (audit foundation, AO-04, access matrix) are done; AOA-01-I2 is NEXT.

### After E5 — Screenshot Audit Pack + Astra Task 2 (full independent project audit)
✅ Screenshot Audit Pack executed; independent analysis received (private, outside the repository). **Historical** — the current order of work is §A9. Approved order recorded at E5 closure:

Approved order:

```text
E5 documentation closure -> fresh Project Sources -> Screenshot Audit Pack
  -> independent full Astra / ChatGPT Work audit -> approved fixes from that audit (if any)
  -> E6 production / operations
```

Independent audit scope: architecture; security; performance; database; queries; maintainability; technical debt; modernization; UX; accessibility; SEO; public structure; features to add/remove; Tourist workflow; Manager workflow; Admin workflow; operations; design. Not executed in the E5 documentation-closure slice.

### E6 — final release / deploy / production smoke
⬜ **PENDING** — final production / operations stage. Starts only after the E5.5 slices the owner approves for the initial release (§A6), the AOA-18 QA gate and the final independent audit (§A6, §A9). **Production is NOT deployed.**

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
- This file is the single canonical roadmap; other documents must defer to it and must not carry their own NEXT lists.
- Current Project Sources must correspond to a clean pushed documentation HEAD.
- No CSS workaround for Tourvisor provider UI (Favorites is controlled by provider account settings).
- Dependency/runtime modernization is a separate guarded E6 pass, never mixed with functional work.
