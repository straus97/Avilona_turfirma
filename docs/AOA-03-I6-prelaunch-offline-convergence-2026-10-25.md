# AOA-03-I6 — Prelaunch offline convergence and synthetic cutover readiness (technical reference)

Date: 2026-10-25. Technical evidence and design only; **status, NEXT and residuals are canonical in [`roadmap.md`](roadmap.md)**. Continues [`I1`](AOA-03-I1-inquiry-foundation-2026-10-24.md), [`I2`](AOA-03-I2-controlled-backfill-2026-10-24.md), [`I3`](AOA-03-I3-inquiry-aware-read-integration-2026-10-25.md), [`I4`](AOA-03-I4-inquiry-audit-catch-up-readiness-2026-10-25.md) and [`I5`](AOA-03-I5-freeze-convergence-readiness-2026-10-25.md).

**Not done, by design:** no real-data access — the canonical database `turfirma_rebuild_v4` and the WAMP MySQL ports 3306/3308 were **not** accessed (not even read-only); no backup, export, copy, real backfill, freeze, deployment, runtime switch, E5 hook, dual-write, change journal, migration, route, view or Composer change. The owner preferred *investigating* a simpler prelaunch approach; that is **not** authorization for real data or a cutover. All rehearsal data in this slice is synthetic.

## 0. Five different achievements — do not conflate them

| Achievement | State after I6 |
|---|---|
| **Successful synthetic offline rehearsal** (sealed snapshot → guarded backfill → verification → exact accounting, on SQLite and on a disposable MySQL) | ✅ done (§5–§8) |
| **Operational readiness** (a runbook that has been executed on infrastructure like the real one, with a restore-tested backup and a known production engine) | ❌ **not established** — production engine unknown, no restore test, owner inputs missing (§9) |
| **Completeness of the real source population** (the snapshot holds every inquiry ever accepted by any writer) | ❌ **not established and not establishable by this tool** (§1, §3) |
| **Real-data migration authorization** | ❌ not granted |
| **Source-of-truth switch** (Inquiry replaces `IncomingInquiry` in the running application) | ❌ not implemented, not authorized; `production_cutover_authorized` is the constant `false` |

Blockers **B1** (no proven lossless intake during an active ingress freeze) and **B2** (no complete write barrier) from I5 are **still OPEN**. A successful rehearsal on a truly isolated, immutable synthetic snapshot says nothing about an active source that keeps receiving data.

## 1. Source-of-truth inventory (from code and documentation only — no live inspection)

| # | Way an inquiry can enter or change the system | Where the data lives | Class | Evidence | Status |
|---|---|---|---|---|---|
| 1 | Tourvisor webhook `GET /api/webhooks/tourvisor/inquiries/{token}` → `TourvisorInquiryIntake` → `incoming_inquiries` | Laravel DB | **A** | routes/api.php; I4/I5 mutation inventory | verified in code |
| 2 | `ImportTourvisorInquiry` job / sync import (`TourvisorInquiryImporter`) | Laravel DB (after fetching from Tourvisor's Export API) | **A** (+ **B**: the authoritative record is at Tourvisor) | I4 §1 | verified in code |
| 3 | Staff workflow: claim, assign, reassign, client link/create, convert, close (`IncomingInquiryWorkflow`) | Laravel DB | **A** | I4 §1 | verified in code |
| 4 | User hard-delete (five controller sites) rewriting six FK columns via `ON DELETE SET NULL` | Laravel DB | **A** | I5 §1 | verified in code |
| 5 | `InquiryDraftStore` (draft + `inquiry.draft_updated` audit) and the I2 backfill → `inquiries` | Laravel DB | **A** | I1/I2/I4 | verified in code |
| 6 | **Public contact forms of this Laravel app** (`/contact/send_home`, `/contact/send_contact`) | **Mail only** — the controllers call `Mail::to(...)` and persist nothing | **C** | `SendContactController`, `SendHomeController`; pinned by `test_public_contact_forms_persist_nothing…` | verified in code: these forms create **no** database record |
| 7 | Tourvisor search/booking module embedded on the **existing public site** (`avilona.ru`); Tourvisor's own copy of every request | Tourvisor's system (and whatever it e-mails) | **B / C** | roadmap E5-A2 record: "`avilona.ru` associated with the account", Standard module, module ID public | existence verified in docs; **retention, retry, export-by-date: UNVERIFIED** (open provider questions) |
| 8 | Database of the existing public site (if any) | unknown | **B / E** | not documented in this repository | **UNVERIFIED — it must not be assumed to share a database with the unreleased Laravel app** |
| 9 | Live Tourvisor webhook registration | — | **E** | roadmap: PoC webhook "safely removed; tunnel stopped" | no live registration is documented; whether any environment receives callbacks today is **UNVERIFIED** |
| 10 | Mail-based intake (a mailbox read into the system) | — | **D/E** | no IMAP/inbound-mail code exists | none today; if staff copy mailbox inquiries by hand they enter as Booking/Inquiry through staff paths — **UNVERIFIED** |
| 11 | Scheduled commands (`bookings:send-trip-reminders`, `tours:*`, RSS sync), `legacy:import-v4` | — | — | none writes `incoming_inquiries`/`inquiries` (I4/I5 guard test; `legacy:import-v4` copies users/content tables, no inquiry table) | verified in code |
| 12 | Direct SQL / tinker / maintenance / migrations | Laravel DB | **E** | invisible to the application | UNVERIFIED by nature |
| 13 | Planned: AOA-04 manual Inquiry UI, AOA-17 VK, OperatorBooking | Laravel DB | **D** | roadmap | not implemented |

**Consequences.** (1) *Class A* rows are everything the Laravel database can contain; the I2–I5 tools cover them. (2) Public-form requests (6) and anything outside Tourvisor (7–10) are **not** in `incoming_inquiries` and cannot be converged from it: they are neither migrated nor lost by this work, they simply were never in the table. (3) What the canonical database contains today (PoC/synthetic/real rows, counts, who wrote them) is **UNVERIFIED** — it was deliberately not read. (4) "Not yet deployed" is **not** evidence of "no writers": rows 7–10 and 12 can only be settled by live inspection that needs separate owner approval.

**Needs separate owner approval:** reading the canonical DB (even read-only/counts), a backup or copy, inspecting the old site's host/database, inspecting the Tourvisor account (webhook registration, retention, export-by-date), reading the mailbox, and any freeze or cutover.

## 2. Feasibility of an offline-snapshot approach

An immutable offline snapshot **can** make online timestamp catch-up unnecessary *for that snapshot*: nothing changes during the run, so a full comparison is exact (I5 §6) and no cursor, `updated_at` or commit order is involved. It does **not** establish that:

* the snapshot contains every inquiry ever accepted by an outside system (§1 rows 7–10);
* anything accepted *after* the snapshot is carried over — that is exactly B1/B2 (an active source), unchanged by this slice;
* the dataset that was snapshotted is the dataset that will be live.

Verdict: the approach is **sound for a prelaunch moment in which the new application has never accepted external traffic** *and* the owner can show that the snapshot is the whole population (§9). It is not a substitute for B1/B2 once any live writer exists.

## 3. Existing components: what already worked, what was missing

| Component | Reused as | Changed? |
|---|---|---|
| `VerifiedQaConnection` | the only way to obtain a connection; refuses unmarked, production, non-loopback, WAMP 3306/3308, canonical or colliding configurations **before any query** | no |
| `InquiryBackfillService` | the only apply path (creates only missing `Inquiry`, one short transaction per source, never touches Booking/legacy rows) | no |
| `InquiryBackfillClassifier` / `InquiryBackfillVerifier` | single mapping `new→new, in_progress→in_progress, converted→converted, closed→closed`; historical (I2) contract | no |
| `InquiryReconciler` | current-state (I3/I4) contract incl. draft audit evidence | no |
| `InquiryCatchUpPlanner` | classification of differences, `HOLD_FOR_DECISION`; `MIRROR_FROM_SOURCE` still not implemented | no |
| `InquiryConvergenceRehearsal` + `InquiryFreezeGate` + `DatabaseFreezeObserver` (I5) | full read-only comparison and the window gate | no |
| `InquiryAuthority`, `InquiryWorkloadSummary` | not touched (no UI/counter wiring in I6) | no |

**Could the existing tools already do the rehearsal?** Mostly: dry-run, apply, verify, reconcile and the full comparison all existed. Four things were missing, and only these were added (no second backfill framework):

1. **Server-confirmed identity.** `VerifiedQaConnection` reads configuration only. A tunnel or port forward can make a "disposable" configuration answer from another server. → `OfflineSnapshotContract::identity()` asks the server (`database()`, `@@port`, `@@version`, `@@datadir`, `@@server_uuid`) and refuses on disagreement.
2. **A snapshot that outlives one run.** I5 compared one table around one scan. → a **manifest** (`seal()`/`compare()`) of the whole protected set and of the pre-existing `inquiries`.
3. **Protection of tables the I5 counters do not cover** (`users`, `roles`, `role_user`, `bookings`, `audit_events`) → per-table write counters for the protected set (MySQL).
4. **Orchestration + accounting** of seal → preflight → apply → verify → compare, with refusal **before** the first write → `OfflineConvergenceRehearsal` and `inquiries:offline-rehearsal`.

## 4. Snapshot integrity contract

`App\Services\Inquiries\Convergence\OfflineSnapshotContract` (read-only) and `OfflineIsolationObserver`.

| Evidence | How | What it does **not** prove |
|---|---|---|
| Verified source/destination identity | `identity()`: server names its database, port, version, datadir, uuid; must equal the configuration; WAMP ports and the canonical name are refused again at runtime; a digest is pinned in the manifest and compared on every run | that the right *data* is in the database (only that it is the intended instance) |
| Schema compatibility | required tables, the I1 migration recorded in `migrations`, a column-layout fingerprint of the protected tables and `inquiries` | data validity |
| Synthetic-only | counts of user and source e-mail addresses outside RFC 2606/6761 reserved domains must be 0; only numbers are reported | that no real person exists in free-text fields; it is a tripwire against an accidental real copy, not a privacy proof |
| Expected source population | the manifest records the row count and a SHA-256 of every protected table; a run refuses if the snapshot differs from its manifest | **that the manifest equals the real world** (the manifest author's claim) |
| Protected relationships | `users`/`roles`/`role_user`/`bookings`/`incoming_inquiries`/`audit_events` fingerprints; pre-existing `inquiries` rows (id ≤ sealed max) must stay byte-identical, new rows are allowed | referential integrity of historical data (that is the classifier's `BLOCKED/unresolved_*`) |
| No external writer | SQLite `:memory:`: private to the process by construction. MySQL/MariaDB: observation of foreign sessions, foreign transactions, queued/in-flight imports and **write counters of all six protected tables** (`performance_schema`) at start and end, plus four operator attestations | **prevention.** Observation detects a write after the fact; a writer that connects later is not stopped (B2) |
| Unexpected mutation | content fingerprints (cross-run) **and** counters (catch ABA and rolled-back attempts) | a write that leaves no counter and no content change |
| Unsafe ⇒ refused | file-backed SQLite, anything unobservable, any foreign session/transaction, missing attestation, mismatching manifest, non-synthetic data → `UNSAFE_REHEARSAL` **before apply**; a mutation seen after the run → `UNSAFE_REHEARSAL`, counts marked provisional | — |

A fingerprint or row count alone is **not** accepted as proof (documented ABA test). A file-backed SQLite snapshot is rejected because another process can open the file.

## 5. Statuses

`UNSAFE_REHEARSAL` (isolation/identity/synthetic/manifest failure, or mutation detected; provisional) → `INTERRUPTED` (apply threw; only the exception class is reported) → `NOT_CONVERGED` (records not processed, accounting incomplete, apply integrity failed, **structural anomalies**) → `DECISIONS_REQUIRED` (safe scope converged, held differences remain) → `CONVERGED_OFFLINE` (nothing left to process, decide or evidence). Every status carries `status_reasons`. A run without `--apply` is a read-only verification and can only confirm convergence when nothing is left to do. Exit code 0 only for `CONVERGED_OFFLINE`. Output always states `production_cutover_authorized=false`, `completeness_of_capture=NOT_ESTABLISHED_BY_THIS_TOOL`, B1/B2 `OPEN` and the five I5 cutover blockers plus two I6 additions.

## 6. Synthetic convergence results

Scenario (12 sources, also run on disposable MySQL 9.7.1): already-backfilled with a legitimately edited draft (revision 1 with audit evidence); new/unassigned; in-progress with null client; in-progress with client; converted with an existing Booking; closed; technical import failed; unsupported type; dangling assignee id; assignee NULLed by a hard delete; client who is not a tourist; ownership conflict (Inquiry assigned to another manager); one independent Inquiry. Variant: a source whose Booking is already owned by another Inquiry.

| Result | Value |
|---|---|
| Created by apply | 5 (`new`, `in_progress`×2, `converted` with its existing Booking, `closed`); statuses mapped one-to-one |
| Verdicts | MATCHED 6, UNSUPPORTED 2, BLOCKED 3, CONFLICT 1 (= 12) |
| Categories | matched 6, unsupported 2, assignee missing/deleted 2, invalid client reference 1, conflicting ownership 1 (= 12); `decisions_required` 4 |
| Final status | `DECISIONS_REQUIRED` (`decisions_pending`, `reconciler_incomplete`) — the safe scope converged, 4 differences wait for an Admin |
| Protected set | intact; no Booking, user, audit event or source row created or changed; the edited draft (revision 1) and the conflicting Inquiry (still assigned to the other manager) byte-identical |
| Repeat | 0 created, identical state; interrupted apply: only completed creations exist, the rerun adds the missing 3 and ends in the same state |
| Duplicate-Booking variant | `NOT_CONVERGED` (`structural_anomalies`): the reconciler treats a Booking shared between two records as structural; the contested source gets no Inquiry, no Booking is created or re-pointed |

Note (schema fact found while building the scenario): `incoming_inquiries.booking_id` is UNIQUE, so two **sources** can never share a Booking in the new schema; the reachable duplicate is a source whose Booking an Inquiry (e.g. a manual one) already owns.

## 7. Safe apply boundary

* The apply is the unchanged I2 service, reached only through the orchestrator after every preflight check; the command additionally needs `--apply --confirm-database=<name>`. No general write mode exists.
* It creates only missing `Inquiry` rows (`NOT_PROCESSED → CREATED`). Existing linked Inquiries are never repaired: differing fields are *classified* (reassignment, Inquiry ahead, client/stage difference, duplicate Booking, unresolved reference) with the current authority named by the planner, and left for an Admin decision. `MIRROR_FROM_SOURCE` stays unimplemented — it would need Inquiry-side audit for status/client/closure/conversion first (I4 §6).
* Measured on MySQL: an account with `SELECT` on the schema, `INSERT` on `inquiries` and a **column-level** `UPDATE` on a single source column runs the unmodified apply to the same result, while the server refuses every write to `users`, `bookings`, `incoming_inquiries` (other columns) and to existing `inquiries`. With no `UPDATE` privilege at all the apply is interrupted (`SELECT … FOR UPDATE` needs one). This is the recommended runbook account; the privilege is an operational control, not a code change.
* If a protected reconciliation apply is ever needed it must be a separate slice with: audit events on the Inquiry side first; per-class decisions; a fingerprint equality precondition; one transaction per record; no Booking creation; no overwrite of a draft revision; dry-run first; a restricted database account.

## 8. Accounting

Three independent partitions, each of which must add up (`complete` flags): sources by decision verdict; sources by planner category; Inquiries by reconciler verdict. Named counters: sources examined, eligible, linked-consistent (and how many by this seal lineage vs before), unsupported technical imports (**excluded, never "migrated"**), blocked legacy, ownership conflicts, client-reference conflicts, Booking-link conflicts, missing provenance, structurally invalid Inquiries, legitimately changed drafts (with audit evidence), unresolved evidence, records not processed, independent Inquiries. Success is reported only when the eligible scope is fully verified *and* nothing needs a decision or evidence. The I2 verifier (historical contract) and the I3/I4 reconciler (current-state contract) are both run and kept separate.

## 9. Prerequisites before an actual cutover could be authorized (none is satisfied)

Evidence required, each with an objective STOP:

1. **Exact inventory of live data sources** (§1 rows 7–10 closed with evidence). STOP: any UNVERIFIED row.
2. **Proof which old systems remain active** (old site, Tourvisor webhook registration, mailboxes, other forms). STOP: an active writer without a capture plan.
3. **Secure migration boundary** (who may see the copy, retention, deletion). STOP: absent written owner approval.
4. **Verified complete data capture** — the snapshot's row count and identity set equal an independent source (e.g. Tourvisor Export for the period, if the provider can list it). STOP: population unverified (the tool says `NOT_ESTABLISHED`).
5. **Backup and restore test** on a scratch instance. STOP: backup not restore-tested. *An application code rollback does not restore modified database data.*
6. **Intended production database engine** known and rehearsed (the observers need MySQL/MariaDB with `performance_schema`). STOP: engine unknown.
7. **Safe deploy order** (additive migration → backfill → verify → switch → monitor) written and rehearsed.
8. **Complete record reconciliation**: status `CONVERGED_OFFLINE`, accounting complete. STOP: any other status.
9. **Admin decisions** recorded for every held class. STOP: `decisions_required > 0`.
10. **Client-visibility verification** (no foreign client data visible to Managers/Tourists after the run; AOA-01/AOA-02 matrices unchanged).
11. **Booking identity preservation** (Booking ids, counts and links unchanged; no extra Booking).
12. **Intake configuration and routing** (webhook target, token, retry contract or a durable buffer — **B1**).
13. **Queue and scheduler control** (workers and scheduler stopped or proven idle — **B2**).
14. **Protection against delayed legacy writes** (a write barrier or process-level stop that has been rehearsed — **B2**).
15. **Rollback and recovery**: restore the verified backup (losing everything written since) or stay on E5 until the switch.

## 10. Evidence

* **PHPUnit** (SQLite `:memory:`, Xdebug off, `memory_limit=4G`, PHP 8.4.13 and 8.5.12): full suite **2100 tests / 16088 assertions, 0 failures, 0 errors** (+56 / +293 over 2044 / 15795; same single PHPUnit deprecation notice). Honest note: the first 8.5.12 run reported **one failure** whose output was not kept (only the tail was captured); it ran while PHP 8.3 lint and file writes were executing in parallel, i.e. not strictly sequentially, and it **could not be reproduced** — the identical code then passed four further complete 8.5.12 runs and the 8.4.13 run. The failing test is unidentified. New: `InquiryOfflineSnapshotContractTest` (31), `InquiryOfflineConvergenceRehearsalTest` (25). No existing expectation changed. New files lint clean on PHP 8.3.32 (syntax only; not used as a runtime). Mutation checks, each caught: preflight refusals ignored (5 fail), unsafe window ignored (2), pre-existing Inquiries not compared (2), synthetic check always ok (2), port check removed (2), decisions never counted (4).
* **Disposable MySQL 9.7.1** — new data directory `C:\Avilona_private\AOA-03-I6_Verification\mysql_data`, loopback port **3418**, `--no-defaults`, database `aoa03_qa_i6`, synthetic credentials; the application's default connection pointed at a dead port (3409); WAMP 3306/3308 and `turfirma_rebuild_v4` never touched; instance stopped afterwards. **76-check multi-process harness, 76/76** (final run 5; runs 1–4 failed on harness defects only and are kept): server-confirmed identity and a **forwarded-port refusal** (the configuration-only guard accepts it, the runtime identity does not); seal; foreign idle session and foreign open transaction refused before any write; clean apply with exact accounting; repeat; `UNIQUE` on source and Booking links; a **worker killed inside its transaction** (exit 9; server rolled back; rerun completes; further runs change nothing); **six real unexpected writers during a paused apply** (source update, ABA on a source, user update, ABA on a user, pre-existing draft edit, user hard-delete cascade) — all detected, status `UNSAFE_REHEARSAL`; two concurrent raw backfill services (5 created in total, no duplicate); an overlapping rehearsal is refused and writes nothing; restricted-account runs (§7); CLI refusals (default connection, unmarked, WAMP 3306 and 3308, canonical name) and a CLI end-to-end converging to exit 0. **Negative control:** with the protected-table counters disabled 6 checks fail, including the ABA-on-`users` case that then reports a false `DECISIONS_REQUIRED` — the counters, not the fingerprint, catch it.
* Findings during the harness: a user hard-delete issued while an apply is open **waits** on the apply's foreign-key locks (the apply holds shared locks on referenced users), so the cascade writer must target a user the open transaction does not reference; MySQL needs `UPDATE` on a source table for `SELECT … FOR UPDATE` (see §7); sessions of a killed client disappear immediately but innodb transactions need the ≤ 4 Hz polling noted in I5.
* Evidence outside the repository: `C:\Avilona_private\AOA-03-I6_Verification`.

**Browser QA: DEFERRED — NO CHANGED UI SURFACE.** No route, view, response or visible behaviour changed; no Inquiry screen or counter is wired to the CRM.

## 11. Residuals and notes (recorded, not fixed)

1. B1/B2 stay open. 2. Population completeness depends on the manifest author; only an independent count (e.g. Tourvisor Export) can close it. 3. The synthetic tripwire inspects e-mail addresses only. 4. Observation, not prevention; a restricted account (§7) and process-level stops (web server, workers, scheduler) are the operational controls. 5. The production engine is unknown; the MySQL observers need `performance_schema` and `PROCESS`. 6. `MIRROR_FROM_SOURCE`/protected reconciliation apply not implemented. 7. The apply keeps `SELECT … FOR UPDATE`, so a no-UPDATE account cannot run it (an offline-only variant without row locks is possible but would change a reviewed I2 path — not done). 8. Manifests are engine-specific (the driver is part of them). 9. All earlier I3/I4/I5 and AOA-01 residuals unchanged.

## 12. Recommended AOA-03-I7

Decide with the owner which of three tracks is next (they are independent): **(a)** an *owner-authorized read-only inventory* of the real sources (§9 items 1, 2, 4, 6: counts and writer discovery, no copy) — the cheapest way to learn whether the prelaunch approach even applies; **(b)** B1/B2 for an *active* source — Tourvisor's written retry contract or a durable intake buffer plus a rehearsed process-level stop; **(c)** the E5 transactional change journal (no freeze). A real-data-copy rehearsal needs its own written approval and must not start before (a).
