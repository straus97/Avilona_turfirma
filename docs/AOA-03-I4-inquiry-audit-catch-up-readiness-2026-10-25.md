# AOA-03-I4 — Inquiry audit and catch-up readiness (technical reference)

Date: 2026-10-25. Technical evidence and design only; **status, NEXT and residuals are canonical in [`roadmap.md`](roadmap.md)**. Continues [`AOA-03-I1`](AOA-03-I1-inquiry-foundation-2026-10-24.md), [`AOA-03-I2`](AOA-03-I2-controlled-backfill-2026-10-24.md) and [`AOA-03-I3`](AOA-03-I3-inquiry-aware-read-integration-2026-10-25.md) (EXPAND → BACKFILL → VERIFY → SWITCH; this slice prepares the step between VERIFY and SWITCH).

**Not done, by design:** no real-data access of any kind — the canonical database `turfirma_rebuild_v4` and WAMP MySQL 3306/3308 were **not** accessed, no backup/export/copy of company data was made, no backfill was run on real data; **the owner has NOT authorized any real-data access** (§8). No E5 dual-write or change-journal hook, no runtime switch, no route/view/listener/notification, no AOA-04 UI, no migration, no Composer change. E5 (`IncomingInquiry`) stays authoritative for every linked pair; AOA-03 stays **ACTIVE**.

**Headline finding:** **EXACT (incremental, online) CATCH-UP REQUIRES ADDITIONAL CHANGE TRACKING.** No candidate cursor over today's `incoming_inquiries` is provably complete (§3). A marker-free, full-comparison catch-up *is* exact, but only inside a write-freeze window (§5).

## 1. Mutation inventory of `incoming_inquiries` (read from code at `ac8a4e49`)

Every `app/` writer was found by reading all references; a heuristic guard test (`IncomingInquiryChangeDetectionProbeTest`) fails if a *new* application file starts writing the table. Legend — **U**: `updated_at` advances; **T**: inside `DB::transaction`; **A**: durable audit event; **Z**: authorization re-checked on the locked row; **R**: can race with a running backfill.

| # | Path (entry point) | Fields changed | U | T | A | Z | Delete / reassign | R |
|---|---|---|---|---|---|---|---|---|
| P1 | Webhook intake — `TourvisorInquiryIntake::receive` (webhook token middleware) | **INSERT**: provider, external_type, external_id, state, webhook_count=1, first/last_notified_at | ✔ (insert) | ✘ single statement; unique index = idempotency | ✘ | n/a (system) | no | **yes**: a row can appear after a chunk was read (reported as `unprocessed_sources`) |
| P1b | Repeated delivery (unique violation branch) | `webhook_count+1`, last_notified_at | ✔ (Eloquent builder adds it — probe-verified) | ✘ | ✘ | n/a | no | noise only |
| P2 | Import claim — `TourvisorInquiryImporter::claim` | state→importing, last_attempt_at, attempts+1 | ✔ | ✘ one conditional UPDATE | ✘ | n/a | no | no business field |
| P3 | Import result — `TourvisorInquiryImporter::import` | state→imported, imported_at, contact fields, price, currency, operator, cities, hotel, fly_date, nights, vendor_payload, normalizer_version | ✔ | ✔ | ✘ | n/a | no | **yes**: turns a row *actionable* (first time it is backfill-eligible) |
| P4 | Import failure — `markFailed` | state→failed, last_error_* | ✔ | ✘ | ✘ | n/a | no | no |
| P5 | `IncomingInquiryWorkflow::assign` / `claim` | workflow_state→in_progress, assigned_to, claimed_at | ✔ (explicit) | ✔ + row lock + conditional UPDATE | ✔ `inquiry.assigned` | ✔ before idempotent return | assignment | **yes** |
| P6 | `reassign` | assigned_to, claimed_at | ✔ | ✔ + lock | ✔ `inquiry.reassigned` (+reason) | ✔ | **reassignment** | **yes** |
| P7 | `selectClient` | client_user_id, client_linked_by, client_linked_at | ✔ | ✔ + lock | ✘ | ✔ | client link | yes |
| P8 | `createClient` (Admin only) | same as P7 (+ new tourist `User`) | ✔ | ✔ + lock | ✘ | ✔ | client link | yes |
| P9 | `sendPasswordSetup` | none (lock + read; mail after commit) | — | ✔ | ✘ | ✔ | no | no |
| P10 | `convert` | workflow_state→converted, booking_id, converted_at, converted_by (+ creates a `Booking`) | ✔ (explicit) | ✔ + lock + conditional UPDATE | **✘** (enum `inquiry.converted` exists, unused) | ✔ before idempotent return | conversion | **yes** |
| P11 | `close` | workflow_state→closed, closed_at, closed_by, close_reason | ✔ | ✔ + lock | **✘** | ✔ | closure | yes |
| P12 | `ClientVerificationService` | reads and locks the row only (writes its own table) | — | ✔ | own events | ✔ | no | no |
| **P13** | **User hard-delete** (Admin deletes a user, or Admin/Manager/Tourist deletes own account — `User` has no soft delete) → FK `ON DELETE SET NULL` | assigned_to, client_user_id, client_linked_by, converted_by, closed_by (`booking_id` only on a hard Booking delete — the app soft-deletes) | **✘ never** | database side, not part of any app transaction | **✘** | n/a | **silent ownership/client change** | **yes** |
| P14 | Direct SQL / query builder | any | ✘ unless set by hand | — | ✘ | — | — | no such path in `app/`; possible for operators/scripts (tests use it for fixtures) |

Timestamp precision: `created_at`/`updated_at` and every business timestamp are `timestamp(0)` — **one-second resolution**, no `ON UPDATE CURRENT_TIMESTAMP` (information_schema, MySQL 9.7.1). `updated_at` is stamped by the application (`now()`) **before** the transaction commits. The only deletion of an `incoming_inquiries` row that could exist is manual SQL: the Inquiry → source FK is `RESTRICT`.

Audit coverage of E5 **today**: only assignment and reassignment (P5, P6). Client link, conversion and closure leave no event. This is a legacy-side gap that I4 deliberately does **not** fix (that would be an E5 hook; §5).

## 2. Change-detection coverage (what the probes show)

`IncomingInquiryChangeDetectionProbeTest` (SQLite and MySQL) and the MySQL harness scenarios 6–7. **Coverage** tests describe what E5 does; **UNSAFE** tests record the *failure of a candidate* (they pass because the failure is the evidence).

| Probe | Result |
|---|---|
| Coverage: assign, reassign, selectClient, createClient, convert, close each move `updated_at` (frozen clock, +1 h) | ✔ every supported workflow mutation advances it |
| Coverage: webhook repeat, import success/failure | ✔ advances (repeat = harmless noise) |
| **UNSAFE** two changes in the same second | a strict `updated_at > marker` cursor **misses the second**; `>=` finds it but re-reads the first |
| **UNSAFE** user deletion (P13) | `assigned_to`/`client_user_id` become NULL, **`updated_at` unchanged** → a timestamp cursor never sees an ownership change (SQLite + MySQL) |
| **UNSAFE** commit order (MySQL, two real connections) | slow transaction stamps `updated_at = t1`, a faster one stamps `t2 > t1` and commits; the cursor is cut at `t2`; the slow one commits afterwards with `t1 < cursor` → **never seen by `>` or `>=`**, nor by an id cursor |
| **UNSAFE** `max(id)` | sees new rows, **blind to every change of an old row** |
| **UNSAFE** write outside Eloquent | `DB::table()->update()` leaves `updated_at` untouched |
| Heuristic guard | the set of `app/` writers is exactly the three documented files |

## 3. High-water marker feasibility

| Candidate | Finds new rows | Finds old-row changes | Proven gaps | Verdict |
|---|---|---|---|---|
| `max(id)` | yes (not commit-order safe) | **no** | all updates | ✘ |
| `max(updated_at)` strict | partly | partly | equal seconds; stamp-before-commit; **P13**; P14 | ✘ |
| `(updated_at, id)` composite | yes | partly | fixes tie *ordering* only; commit order, P13, P14 remain | ✘ |
| Overlapping window (re-read last N min) | — | — | cannot cover P13 at all (stamp never moves); N is unbounded for lock waits up to `innodb_lock_wait_timeout` | ✘ not provable — **not used to hide the gap** |
| Triggers on `incoming_inquiries` | yes | yes for direct writes | **cascaded `SET NULL` does not fire triggers** (verified on MySQL 9.7.1, `evidence/trigger_probe.txt`) → P13 still invisible | ✘ alone |
| Full comparison, no marker | yes | yes | needs quiescence to prove convergence; cost O(n) | ✔ **exact for state, inside a freeze** |
| App-level transactional journal | yes | yes if every writer + a User-deleting hook is covered | needs gap-tolerant reader (sequence allocated in-transaction is also not commit-ordered) | ✔ for *incremental*, future |

**EXACT CATCH-UP REQUIRES ADDITIONAL CHANGE TRACKING** (for incremental/online catch-up). Smallest safe future mechanism if incremental catch-up is wanted: an **application-level journal table written in the same transaction** as each of P1, P3, P5–P8, P10, P11 plus a `User` *deleting* hook that journals the rows referencing the user *before* the delete (the only way to see P13), read by a gap-tolerant consumer (re-check unresolved sequence gaps), with the full-comparison pass kept as the safety net. This needs E5 production hooks and is **not** implemented in I4 (separate, separately verified step).

## 4. Inquiry-side business audit (implemented)

* New type `AuditEventType::InquiryDraftUpdated` (`inquiry.draft_updated`, label «Черновик обращения обновлён»); the column is a plain string, **no migration**.
* `InquiryDraftStore` now records it through `AuditRecorder` **inside the same transaction**, after the draft write: subject `inquiry`/id, actor = the authenticated user passed to the service (never request data), metadata exactly `previous_revision`, `new_revision`, `changed_field_count`, `changed_fields` (comma-separated **field names** in `Inquiry::DRAFT_FIELDS` order, NULL when none). **No draft value, note, preference, date, contact or provider payload is written** — verified with sentinel private text (even text that looks like an e-mail/phone, which the metadata validator would have rejected, is saved to the draft and never reaches the journal).
* Events only for the accepted change. **No event** for: validation failure, unknown/forbidden field, stale revision, authorization failure (foreign/former Manager, unassigned, Tourist, no role), closed/converted Inquiry, E5 reassignment that won the row lock, the empty patch (existing no-op: revision unchanged), or a rolled-back transaction. A retry with a stale revision cannot duplicate an event.
* Existing semantics kept: a *non-empty* patch whose values equal the stored ones still raises the revision (I1 behaviour); it produces an event with `changed_field_count = 0`, so **events = revisions** always holds.
* Not created: events for historical Imported Inquiry actions (the backfill writes none — tested), for creation/assignment/reassignment/conversion/closure of an `Inquiry` (no service performs those yet — AOA-04/05 will emit their own).
* One existing assertion changed deliberately: `InquiryDraftStoreTest` previously asserted *zero* audit events after a save (the I1 contract «draft is a note, not audited»); it now asserts one.

**Transaction guarantees (MySQL 9.7.1, InnoDB, real DB failure):** a `BEFORE INSERT` trigger on the disposable `audit_events` made the audit insert fail → the draft change was **rolled back** (state hash identical, revision/fields/actor unchanged) and after removing the fault the same operation succeeded exactly once. Two real connections saving with the same revision: exactly one `saved`, one `stale`, one event, `event.actor = draft_updated_by`. Row locks: an E5 reassignment waits (~1.58 s) behind the open draft+audit transaction; when the reassignment commits first the former Manager is refused with **no** event.

## 5. Reconciler evidence handling

What the new evidence **resolves** — precisely one ambiguity: *is a draft revision N > 0 legitimate Inquiry-side evolution or unexplained drift?* `InquiryReconciler` now classifies every Inquiry's draft as `evidenced` (events form the chain 0→1→…→N, last actor = `draft_updated_by` unless that user was deleted), `unevidenced` (N > 0, no events) or `inconsistent` (gap, extra event, actor mismatch, events at revision 0). Only events with the exact type and subject `inquiry` count (same numeric id under `incoming_inquiry` is ignored). Output: `draft_audit_evidence` counts, reason codes `draft_unevidenced` / `draft_inconsistent` (with ≤ 20 sample ids), and `fully_reconciled` is now false while any draft is unproven (`incomplete_because.draft_without_audit_evidence`). Pair verdicts are **not** changed — unproven is neither corruption nor proven evolution.

What it deliberately does **not** resolve (all tested as still failing): structural corruption, `OWNERSHIP_CONFLICT` (no winner), `REQUIRES_EVIDENCE` (status/closure/conversion/client/assigned-at differences — no supported service changes those on an `Inquiry`, so there is nothing to audit; `limits.no_inquiry_change_log` was reworded accordingly), and `unprocessed_sources` (still no marker). The I2 initial verifier is untouched and still passes. Reconciliation remains strictly read-only (state hash before = after, on SQLite and MySQL; constant query count per chunk).

On real data this cannot yet matter: no code path creates an `Inquiry` or calls the draft store, so any `draft_revision > 0` found later is by definition unexplained.

## 6. Catch-up design contract (for a future slice; nothing here is implemented except the pure planner)

`App\Services\Inquiries\Backfill\InquiryCatchUpPlanner` (new, pure: takes a classifier decision + the current Inquiry status, returns category + *proposed* action, no DB, no writes) fixes the vocabulary:

| Category | Source of truth | Proposed action |
|---|---|---|
| NEW_ELIGIBLE_SOURCE | classifier `NOT_PROCESSED` | CREATE_FROM_SOURCE — only when the list of new sources is provably complete |
| UNCHANGED_LINKED | `MATCHED` | NONE |
| POST_BACKFILL_CONVERSION / CLOSURE / CLIENT_LINK_CHANGE | source ahead, assignees equal | MIRROR_FROM_SOURCE — only if the Inquiry itself did not evolve those fields |
| REASSIGNMENT | assignees differ | **HOLD** — no winner |
| INQUIRY_AHEAD | Inquiry stage > source stage | **HOLD** — never overwritten |
| DUPLICATE_BOOKING, UNRESOLVED_USER_REFERENCE, UNRESOLVED_CLIENT_REFERENCE, REQUIRES_OWNER_DECISION | blocked / conflict classes | **HOLD** |
| UNSUPPORTED_TECHNICAL_SOURCE | not actionable | NONE |

Design rules for the future slice:

* **A Discovery.** Under a freeze: full comparison (planner over every source and every Inquiry). Online: journal consumer (§3) + periodic full comparison.
* **C Authority.** Before the switch E5 stays authoritative; `InquiryAuthority` already denies Inquiry-side access on any disagreement — a stale snapshot can never grant access.
* **D Synchronization.** Mirrorable: status, assigned_at, client link triple, booking link, conversion and closure fields (the I2 `COMPARED_FIELDS`). **Never overwritten:** draft fields and draft revision/actor (no source counterpart), `created_by`, any field whose Inquiry-side value differs from the source *and* has audit evidence of Inquiry-side change. A genuine Inquiry-side change is distinguishable from stale backfill data **only** where an audit trail exists — today that is the draft alone; for every other mirrored field the planner returns HOLD/«needs evidence».
* **E Atomicity.** One transaction per source; lock order `Inquiry` → `IncomingInquiry` (as `InquiryDraftStore`, no inverse path exists); lock the Booking row when a link is mirrored; re-read and re-classify under the locks; never create a `Booking`.
* **F Idempotency.** Natural keys: unique `incoming_inquiry_id`, unique `booking_id`; a repeat pass finds `UNCHANGED_LINKED` and writes nothing; any business audit event is emitted only by the pass that changes the row.
* **G Checkpointing.** **No marker is defined** — none of the candidates is provably complete (§3). The prerequisite is the change journal; until then the checkpoint is "a full pass reports `fully_reconciled` while E5 writes are frozen".
* **H Recovery.** Per-source transactions are restartable and never modify the legacy row; a failed pass leaves the previous state intact; the I2 verifier and I3 reconciler are the post-run checks.
* **I Cutover prerequisites.** Owner authorization for real-data work (§8); verified backup; resolved `BLOCKED`/`CONFLICT` decisions; an E5 write-freeze window (or the journal); a clean full pass; then one reviewed step flipping the «linked» branch of `InquiryAuthority` and switching the E5 routes. No runtime flag. Not in this slice.

## 7. Evidence

* **PHPUnit** (SQLite `:memory:`, Xdebug off, `memory_limit=3G`): full suite **1977 tests / 15413 assertions, 0 failures, 0 errors** on PHP 8.4.13 **and** 8.5.12 (+52 tests / +192 assertions over 1925 / 15221): `InquiryDraftAuditTest` (14), `InquiryDraftEvidenceReconciliationTest` (14), `InquiryCatchUpPlannerTest` (15), `IncomingInquiryChangeDetectionProbeTest` (9). One existing assertion changed (§4). New and changed files lint clean on PHP 8.3.32 (syntax only; not used to run the app). Same single PHPUnit deprecation notice as before. Mutation checks, each caught: audit call removed (9 tests fail), audit failure swallowed (rollback test fails), reconciler always «evidenced» (5 fail), planner mirrors a reassignment (2 fail).
* **Disposable MySQL 9.7.1** (new data directory, port **3412**, loopback, `--no-defaults`, database `aoa03_qa_i4`, synthetic credentials; harness default connection pointed at a dead port; WAMP 3306/3308 and `turfirma_rebuild_v4` never touched; instance stopped afterwards): 206 related existing tests pass; of the 52 new tests 22 run and pass on MySQL and 30 skip explicitly (they need the `VerifiedQaConnection` guard, which refuses the PHPUnit default connection on MySQL — the same scenarios are covered by the harness); plus a **45-check harness** (guards, precision/FK evidence, audit atomicity with a real DB failure, stale retry, two-connection race, two-process lock proof, draft-evidence on MySQL, planner on the real classifier, read-only, commit-order proof, FK-null proof). Run 1 had one harness miscount (expected 7 FKs, there are 6) — fixed, run 2 is 45/45; both logs kept. Evidence outside the repository: `C:\Avilona_private\AOA-03-I4_Verification`.
* **Browser QA: DEFERRED — NO CHANGED UI SURFACE.** No route, view or visible behaviour changed (the only user-visible-adjacent change is two extra lines of CLI output for the QA-only reconcile command).

## 8. Proposed isolated real-data rehearsal — PLAN ONLY

**Permission to *prepare* this plan was granted; permission to *execute* any step was NOT.** The owner has not authorized reading the canonical database, making a backup, exporting or copying customer data, importing it into a QA environment or running the backfill on real data. Each step below needs its own explicit written owner authorization.

1. **Authorization & roles.** Owner states in writing: source (which database), purpose, who may see the data, retention, deletion date. One person executes; one reviews counts only.
2. **Source identity.** Confirm host, port, `@@datadir`, database name and row-count fingerprints of `incoming_inquiries`, `inquiries`, `bookings`, `users` *before* anything is copied; record in a private evidence file.
3. **Backup.** A verified backup of the source exists (restore-tested on a scratch instance) *before* the copy is taken; the copy is taken from the backup, never from the live instance if avoidable.
4. **Isolation.** New data directory outside WAMP, new loopback port (not 3306/3308), `--no-defaults`, fresh synthetic credentials, firewall off, no application `.env` pointing at it; the app's default connection must point at a dead port (as in I3/I4 harnesses).
5. **No outward effects.** `MAIL_MAILER=array`, queue `sync` with notifications faked, no Tourvisor key (so no provider call), no webhook route reachable, no scheduler.
6. **Minimization & handling.** Prefer copying only the tables the tooling reads; contact columns and `vendor_payload` are not read by the tooling and should be nulled in the copy; **do not assume anonymization preserves referential relationships** — keep ids, check FKs after any scrub. Evidence files contain counts and numeric ids only; the disk folder is access-restricted and outside the repository.
7. **Engine compatibility.** Same MySQL major as production (not assumed — confirm), `ONLY_FULL_GROUP_BY`, `utf8mb4`; compare `SHOW CREATE TABLE` of the five tables against the migrations.
8. **Dry-run first.** `inquiries:backfill --connection=<qa>` (default dry-run) → record the *actual* counts per class (`NOT_PROCESSED`, `BLOCKED` by reason, `CONFLICT` by reason, `UNSUPPORTED`). **No expectation of the counts is made here; they are unknown.**
9. **Owner/Admin decisions.** Per real class: map, fix in E5 by an authorized human process, or exclude. Nothing is auto-rewritten.
10. **Apply on the copy only** (`--apply --confirm-database=<qa name>`), then `--verify` and `--reconcile`; run the planner offline to enumerate catch-up categories.
11. **Rollback/recovery.** The copy is disposable: restore from the backup copy or re-create; the source is never written.
12. **Post-run integrity.** I2 verifier `fully_verified`, I3 reconciler without `STRUCTURAL`, hash of the source tables unchanged, no `Booking` created, no `audit_events` rows other than the rehearsal's.
13. **Cleanup.** Stop the instance, securely delete the data directory and dumps, record the deletion; keep only the count-level report.

## 9. Residuals and notes (recorded, not fixed)

1. **P13** user hard-delete silently changes ownership/client on `incoming_inquiries` and `inquiries` without `updated_at` or audit; only a User-deleting hook can journal it. Related existing item: I1 limit «user deletion nulls assigned_to/booking_id».
2. E5 `convert`, `close`, `selectClient`, `createClient` have **no audit event** (assign/reassign have). Adding them is an E5 change for a later step.
3. `webhook_count` noise advances `updated_at`; irrelevant for correctness but makes timestamp cursors chatty.
4. The `limits` text of the reconciler changed (`no_inquiry_change_log`); consumers must not match on the old wording.
5. The writer-inventory guard is a heuristic (statement scan); it cannot see a writer that builds SQL dynamically.
6. All earlier AOA-01 residuals, the I3 residual list (roadmap row «AOA-03-I3 residuals») and the Composer platform-constraint item are unchanged.

## 10. Recommended bounded AOA-03-I5

Choose by the owner's cutover mode: **(a) freeze-window convergence** — a maintenance switch that makes the E5 write endpoints read-only for a bounded window plus the *offline* full-comparison catch-up pass built on `InquiryCatchUpPlanner` and the reconciler (no E5 schema change); or **(b) incremental** — the transactional journal + User-deleting hook (separate verified E5 change). Either way: still no real data without the §8 authorizations; the real-data rehearsal itself is a separate owner-authorized task.
