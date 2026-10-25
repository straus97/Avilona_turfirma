# AOA-03-I5 — Freeze-window convergence readiness and isolated rehearsal (technical reference)

Date: 2026-10-25. Technical evidence and design only; **status, NEXT and residuals are canonical in [`roadmap.md`](roadmap.md)**. Continues [`I1`](AOA-03-I1-inquiry-foundation-2026-10-24.md), [`I2`](AOA-03-I2-controlled-backfill-2026-10-24.md), [`I3`](AOA-03-I3-inquiry-aware-read-integration-2026-10-25.md) and [`I4`](AOA-03-I4-inquiry-audit-catch-up-readiness-2026-10-25.md).

**Not done, by design:** no real-data access — the canonical database `turfirma_rebuild_v4` and WAMP MySQL 3306/3308 were **not** accessed; no backup, export, copy, real backfill, real freeze, deployment or runtime switch; no E5 hook, dual-write, change journal, migration, route, view or Composer change. The owner approved *investigating* the freeze-window approach; that is **not** approval of a freeze, real-data access or a cutover.

## 0. Five different achievements — do not conflate them

| Achievement | State after I5 |
|---|---|
| **Feasible synthetic rehearsal** (read-only full comparison on a disposable database, with an honest report) | ✅ done (§5–§7) |
| **Proven operational freeze barrier** (nothing can write while the window is open) | ❌ **not proven, not implemented** — the application has no write barrier; only *observation* of writes exists (§3) |
| **Verified provider intake preservation** (an inquiry is never lost during the freeze) | ❌ **not proven** — blocker B1 (§2) |
| **Real-data reconciliation** | ❌ not done, not authorized |
| **Production-cutover authorization** | ❌ not granted; the code hard-wires `production_cutover_authorized = false` |

## 1. Freeze feasibility verdict

**A write freeze of `incoming_inquiries` that is provably lossless cannot be established with the current system.** Two independent blockers:

* **B1 — no lossless intake.** See §2.
* **B2 — no verified write barrier.** Laravel maintenance mode answers HTTP with 503; it does not stop an in-flight DB transaction, a reserved queue job, a scheduler run, an idle DB session or manual SQL. Nothing in the application blocks a writer *inside* the database. §3 lists what can and cannot be verified.

What *is* possible today is a **read-only convergence rehearsal that detects an unsafe window** and refuses to call a run converged unless the window was observed clean and the operator attested the rest (§5). That is a prerequisite, not a barrier.

### Writer inventory (re-read from code at `3cfe4d5a`; guarded by tests)

The 14 mutation paths of I4 §1 stand. I5 adds how each is stopped (`App\Services\Inquiries\Convergence\WriterInventory`, cross-checked by `InquiryFreezeGateTest`):

| Writers | Stopped only by | Notes |
|---|---|---|
| P1 webhook intake + repeat delivery | ingress stop at the web server | `GET /api/webhooks/tourvisor/inquiries/{token}` |
| P2–P4 import claim/result/failure | queue workers stopped **and** ingress stopped | runs *inside the webhook request* when `QUEUE_CONNECTION=sync` (the code default) |
| P5–P11 staff workflow (assign, reassign, client link, create client, convert, close) | ingress stop | authenticated HTTP only |
| **P13a user hard-delete** — **five** controller call sites: `Admin\AdminController` (2: `deleteUser`, own account), `Cabinet\CabinetController`, `Manager\ManagerController`, `ProfileController` | ingress stop | `ON DELETE SET NULL` on six FK columns; `updated_at` never moves |
| P13b Booking hard-delete | no manual SQL | the application only soft-deletes Bookings |
| P14 direct SQL / tinker / migrations | no manual SQL | invisible to the application |
| Queue (delayed releases `60 s × attempt`, `tries = 3`) | worker stop | `queue:work` pauses in maintenance mode unless `--force`, **but a job already reserved finishes** |
| Scheduler | scheduler pause | only `bookings:send-trip-reminders` (does not touch the table); listed so a future scheduled writer is not forgotten |

`test_user_hard_delete_exists_only_at_the_documented_call_sites` fails when a sixth deletion site appears; I4's guard still fails on a new `incoming_inquiries` writer.

## 2. External intake: what the code guarantees and what it does not

Verified from code and tests (`InquiryIntakeFreezeSemanticsTest`, also green on the disposable MySQL):

* **Persist before acknowledge.** `TourvisorInquiryIntake::receive` inserts the row (unique `(provider, external_type, external_id)` = idempotency) **before** the controller returns 200/202. A failing queue never turns a persisted row into an error (it stays `pending`).
* **Retry identity exists and is idempotent:** a repeated delivery keeps one row and counts `webhook_count`.
* **Maintenance mode rejects intake.** `PreventRequestsDuringMaintenance::$except` is empty and the middleware is global, so during maintenance the webhook is answered **503 and nothing is persisted or queued** — the request exists nowhere in this system. The per-IP limiter (120/min) likewise answers 429 without persisting.
* **No durable intake buffer** exists (a test fails if an `intake`/`webhook`/`inbox` table appears, forcing this analysis to be redone).
* **Provider retry is not proven.** The webhook is an unsigned `GET ?id=&type=` with no payload; the E5 research document lists "retry, timeout, order, retention, signature" as an **open question to Tourvisor** (§ questions, item 6). Nothing in the repository shows that Tourvisor re-sends after 503/429, how often or for how long.

**Missing guarantee (precise):** *an inquiry whose webhook arrives while ingress is stopped must be recoverable.* This needs one of: (a) a written provider delivery agreement (retry schedule and duration) covering the planned window length, or (b) a **durable intake buffer** that stays up during the freeze (accept → append to a separate table/queue → ack; replay after the window), or (c) a Tourvisor-side pull/list API to re-discover missed ids. None is verified or implemented. **Not invented in I5.** Making the webhook an exception to maintenance mode is *not* a fix: the P1 insert (and, with `sync` queue, the P3 update) would then change the source during the window.

## 3. Quiescence: what can be verified, and how

A safe freeze would need all of: no new protected mutation can begin; in-flight mutations finished or rolled back; queue drained/paused; scheduler paused; accepted intake durable; user deletion impossible; no bypassing writer; state stable. **A config flag read at request start cannot satisfy this** (in-flight transactions commit afterwards).

Verified on the disposable MySQL 9.7.1 (§7) — the *observation* tools:

| Signal | What it proves | Limits found |
|---|---|---|
| `performance_schema.threads` (foreign sessions) | no other client session exists → no one can start a write next | an **idle** session blocks too (correct: it could write next millisecond) |
| `information_schema.innodb_trx` (foreign transactions) | no transaction in flight | a **dead session's transaction stays listed** after the session left `threads`; it cleared in ~0.3 s at 4 Hz polling but **was still listed after 25 s under 50 Hz polling** → observe at ≤ 4 Hz, never in a tight loop |
| `performance_schema.table_io_waits_summary_by_table` write counters of `incoming_inquiries` | no INSERT/UPDATE/DELETE was *attempted* in the window | **counts the `ON DELETE SET NULL` cascade** (triggers cannot); counts rolled-back attempts and uncommitted writes (conservative); resets on server restart; needs `performance_schema` and the `SELECT` privilege |
| content fingerprint of `incoming_inquiries`/`inquiries` before vs after the scan | the data did not change | **misses ABA** (two commits restoring the content) — the counters catch it |
| `jobs` table (database queue only) | no `ImportTourvisorInquiry` queued/reserved/delayed | unknown for `sync`/Redis queues → reported as *unobservable*, never as clean |
| `incoming_inquiries.state = importing` with a live claim | no import in flight | — |

SQLite exposes none of the first three → the rehearsal on SQLite **can never be `window_quiescent`** (tested). The operator must additionally *attest* four things the database cannot show (`FreezeAttestation`: `ingress_stopped`, `queue_workers_stopped`, `scheduler_paused`, `no_manual_sql_or_maintenance_commands`); attestations are reported as "attested, not machine-verified" and never replace a measurement. Laravel maintenance mode is **not** an accepted attestation.

**Observation is not a barrier.** In the harness a real E5 `reassign`, a user hard-delete and a webhook-style INSERT all **committed freely during the scan** (nothing blocked them); the gate *detected* them afterwards and refused the run. That is the honest ceiling of I5: *fail closed on detection*, not *prevent*.

## 4. Why a small default-OFF write barrier was NOT added

The task allowed one only if exhaustive, fail-closed and loss-free. It is not achievable as a small slice: the 14 paths plus the five user-delete sites and raw SQL cannot all be gated in application code, a gate in the HTTP layer does not stop in-flight transactions or the cascade, and a gate that rejects intake creates B1. A partial flag would let the freeze *look* implemented. **Runtime is unchanged.** The bounded alternatives: (i) durable intake buffer + process-level stop (web server, queue workers, scheduler) with the DB observer as verification; or (ii) the I4 transactional change journal (online, no freeze) — both separate, separately verified slices.

## 5. Implemented in I5 (read-only; no migration, route or runtime change)

`App\Services\Inquiries\Convergence\` and `inquiries:convergence-rehearsal` (guarded by the unchanged `VerifiedQaConnection`; **no write mode, no `--apply`**):

* `InquiryConvergenceRehearsal` — observe → fingerprint → full scan with the existing **classifier (I2) + planner (I4)** → existing **reconciler (I3/I4)** → fingerprint → observe → `InquiryFreezeGate`. Nothing is created, mirrored or overwritten; Bookings and audit untouched.
* `InquiryFreezeGate` — pure evaluation. `window_quiescent` needs: no foreign session/transaction/queued import/in-flight import in **either** sample, no write-counter movement, unchanged fingerprints, all four attestations, **and every measurement observable** (null = fail closed). `production_cutover_authorized` is constant `false` with a constant blocker list (lossless intake unproven, no write barrier, apply not implemented, no switch, no owner authorization).
* `DatabaseFreezeObserver` (+ `FreezeObservation`, `FreezeObserver`, `FreezeAttestation`, `WriterInventory`) — numbers only (no ids, queries or data).
* One additive field in the reconciler report: `expected_evolution.draft_edited_evidenced`.

**Status:** `UNSAFE_FREEZE` (window not proven or source changed during the scan; counts are `provisional`) → `NOT_CONVERGED` (window proven, but something needs action/decision or the reconciler is incomplete) → `CONVERGED_IN_WINDOW` (window proven, every source `matched` or `unsupported_technical_import`, accounting complete, reconciler fully reconciled). Exit code 0 only for the last. It is never a cutover permission.

### Report categories (each source in exactly one; sum must equal the source count or `accounting_complete=false`)

`matched_source` · `new_eligible_source` · `requires_catch_up` (post-backfill conversion / closure / client-link change; proposed action MIRROR — **not implemented**) · `conflicting_current_state` (reassignment, Inquiry ahead; HOLD, no winner) · `assignee_missing_or_deleted` (user hard-delete: both sides null, an `in_progress` row without assignee) · `invalid_client_reference` · `duplicate_booking_linkage` · `unsupported_technical_import` · `unresolved_manual_decision`. Separately: `inquiries.legitimate_inquiry_side_evolution` (draft revisions *with* an audit chain), `inquiries.independent`, and the freeze result. Output is counts, reason codes and ≤ 20 numeric sample ids per category — no contact, payload or secret (tested with sentinel values).

## 6. Convergence strategy and conflict handling (design; apply is a later slice)

Under a **verified** quiet window a full scan replaces the timestamp cursor entirely: nothing depends on `updated_at`, ids or commit order (tested with same-second stamps, a stamp that never moved, an earlier-stamped late commit). It does **not** license overwriting: a difference is *classified*, and only `NEW_ELIGIBLE_SOURCE` has an existing safe apply (the I2 backfill, idempotent, restartable, never creates a Booking). `MIRROR_FROM_SOURCE` stays unimplemented because, except for the draft, an Inquiry-side change to status/client/closure/conversion has no audit trail to tell "source ahead" from "legitimate Inquiry edit" (I4 §6 rule D). Reassignment, Inquiry-ahead, duplicate Booking, unresolved references and unknown fields are always HOLD for an Admin/owner decision. No Booking is created, no historical source row modified, no business audit invented.

## 7. Evidence

**PHPUnit** (SQLite `:memory:`, Xdebug off, `memory_limit=4G`): full suite **2042 tests / 15792 assertions, 0 failures, 0 errors** on PHP 8.4.13 **and** 8.5.12 (+64 tests / +372 assertions over 1978 / 15420): `InquiryFreezeGateTest` (28), `InquiryConvergenceRehearsalTest` (28), `InquiryIntakeFreezeSemanticsTest` (8). No existing expectation changed. New files lint clean on PHP 8.3.32 (syntax only; not used as a runtime). Same single PHPUnit deprecation notice. Mutation checks, each caught: gate ignores source change (3 tests fail), ignores open transaction (3), treats unobservable as clean (7), status ignores the gate (6), assignee mapping removed (1), every category counted as settled (error). What SQLite tests do **not** prove: any real session/transaction/counter behaviour — the real `DatabaseFreezeObserver` MySQL path is covered only by the harness below, and the orchestrator tests use a scripted observer for the clean-window case (labelled in the test header).

**Disposable MySQL 9.7.1** — new data directory `C:\Avilona_private\AOA-03-I5_Verification\mysql_data`, loopback port **3415**, `--no-defaults`, database `aoa03_qa_i5`, synthetic credentials; instance identity (version, port, `@@datadir`, bind address) asserted before any write; Laravel's default connection pointed at a dead port; WAMP 3306/3308 and `turfirma_rebuild_v4` never touched. **46-check multi-process harness, 46/46** (run 3 and run 4; run 1/2 failed on two *harness* defects: a wrong audit column name and tight-loop polling, §3). Real separate PHP processes with file barriers (no arbitrary sleeps): proven window + idempotent repeat + read-only; open transaction and idle session at freeze start (unsafe; uncommitted change invisible under REPEATABLE READ; clears after rollback); **real E5 `reassign`, ABA, rolled-back attempt, user hard-delete cascade and a P1 INSERT during the scan — all committed unblocked and all detected**; deleted employee found although `updated_at` did not move; worker killed while holding a transaction (server rolls back; window clears); early-stamped late commit still found (commit order irrelevant); delayed import job blocks; rehearsal killed mid-scan leaves identical state; repeated backfill ×3 creates no duplicate and no Booking; CLI exit codes and refusals. **Negative control:** with the write counters disabled in the gate 5 checks fail (including both the ABA and rolled-back cases, which the content fingerprint alone cannot see). The intake-semantics and webhook test classes (25 tests) also pass on this MySQL. Evidence outside the repository: `C:\Avilona_private\AOA-03-I5_Verification`.

**Browser QA: DEFERRED — NO CHANGED UI SURFACE.** No route, view or visible behaviour changed. The separate LEGAL-ADDR-01 Vite/browser limitation and OKTMO verification are untouched.

## 8. Future operational sequence — NOT executed, NOT approved

0. **Explicit owner approval** in writing (source database, purpose, who sees data, retention). 1. **Resolve B1** (provider agreement or durable intake buffer) and **B2** (process-level stop is documented and rehearsed). 2. Verify **source/destination identity** (host, port, `@@datadir`, schema, row-count fingerprints). 3. **Restorable backup**, restore-tested on a scratch instance. 4. **Ingress protection** (web server level; buffer on). 5. **Queue/scheduler**: pause workers, wait for reserved jobs to finish, pause scheduler. 6. **Drain**: observe no foreign session/transaction/queued import/in-flight import (≤ 4 Hz polling). 7. Operator **attests** the four items. 8. **Full comparison** (this tool) → `CONVERGED_IN_WINDOW` or a conflict report. 9. **Conflict report** → Admin/owner decisions per class. 10. **Protected apply** (a future slice; new sources via the I2 backfill; mirror only with evidence). 11. **Post-apply verification** (I2 verifier, I3/I4 reconciler, Booking/audit counts, source fingerprint). 12. **Source-of-truth switch** only if all prerequisites hold. 13. **Restore normal intake**, replay the buffer, then monitor (reconciler daily for a defined period, error log, unprocessed sources = 0).

**Objective STOP conditions** (any one ⇒ do not switch; stay on E5): status ≠ `CONVERGED_IN_WINDOW`; any blocker/unverifiable item; accounting incomplete; any `HOLD` class unresolved; reconciler not fully reconciled; fingerprints changed between scan and apply; Booking count or audit count changed unexpectedly; backup not restore-tested; intake buffer not verified empty or replayed; owner approval absent. **Rollback:** an application rollback restores *code*, not data — recovery is restoring the verified backup (losing everything written since) or leaving E5 authoritative, which is unchanged until the switch. The apply step must therefore keep E5 rows untouched (it only adds/aligns `inquiries`).

## 9. Residuals and notes (recorded, not fixed)

1. **B1/B2 above** — no lossless intake, no write barrier. 2. `MIRROR_FROM_SOURCE` and the protected apply are not implemented. 3. The write-counter and session probes need MySQL/MariaDB with `performance_schema`; the production engine is **unknown** and was not checked. 4. Counters reset on server restart (a restart inside the window reads as "write observed" or, if equal by chance, as clean — the other signals still apply). 5. `webhook_count` noise from repeated deliveries counts as a write (conservative). 6. `queue:work` honours maintenance mode but a job already reserved finishes; delayed releases keep rows in `jobs` for minutes. 7. Tight-loop polling of `innodb_trx` delays dead-transaction cleanup (§3). 8. The user-delete call-site guard is a pattern scan and cannot see a dynamically built deletion. 9. All AOA-01, I3 and I4 residuals and the Composer platform-constraint item are unchanged.

## 10. Recommended AOA-03-I6

Per the owner's cutover mode, one of: **(a) intake preservation** — decide with the owner whether to obtain Tourvisor's written retry contract or build the durable intake buffer (separate table, accept-then-replay), and only then a process-level freeze runbook verified on disposable infrastructure; or **(b) incremental** — the I4 transactional change journal + User-deleting hook (no freeze, no B1). Either way the protected apply (new sources via the I2 backfill, evidence-gated mirror) and the real-data rehearsal need their own owner authorizations.
