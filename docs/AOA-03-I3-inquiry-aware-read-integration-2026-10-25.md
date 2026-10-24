# AOA-03-I3 — Inquiry-aware read integration and transitional reconciliation (technical reference)

Date: 2026-10-25. Technical evidence and design only; **status, NEXT and residuals are canonical in [`roadmap.md`](roadmap.md)**. Continues [`AOA-03-I1`](AOA-03-I1-inquiry-foundation-2026-10-24.md) (EXPAND) and [`AOA-03-I2`](AOA-03-I2-controlled-backfill-2026-10-24.md) (BACKFILL + VERIFY rehearsal). This slice prepares the **read side** of EXPAND → BACKFILL → VERIFY → SWITCH.

**Not done, by design:** no real-data backfill; the canonical database `turfirma_rebuild_v4` and WAMP MySQL 3306/3308 were **not** accessed; no E5 route was switched to `Inquiry`; no route, view, listener, notification, dual-write, assignment UI or Inquiry creation was added; Composer packages, Booking lifecycle and Tourvisor are untouched; AOA-03 stays **ACTIVE** (not closed).

## 1. Read-surface inventory (what actually reads ownership)

Classes: **A** legacy `IncomingInquiry`-based, **B** `Booking`-based, **C** new-`Inquiry`-capable, **D** shared/derived.

| Surface | Source of truth today | Class | Changed in I3? |
|---|---|---|---|
| `IncomingInquiry::scopeVisibleTo`, `IncomingInquiryPolicy`, Manager inquiry list/show/actions (`/cabinet/manager/inquiries*`) | `incoming_inquiries.assigned_to` | A | No |
| `ClientVisibility::scopeTo` / `touristsVisibleTo` — consumers: Manager clients list (`ManagerController::clients`), booking-create client dropdown and validation (`BookingController`), inquiry client candidates (`IncomingInquiryClientMatcher`), client selection (`IncomingInquiryWorkflow::selectClient`) | Booking ∪ legacy inquiry ∪ approved verification request | A + B (+ verification) → **D** | **Yes** — one added branch (§4) |
| `Inquiry::scopeVisibleTo`, `InquiryPolicy`, `InquiryDraftStore` (no route consumes them yet) | `inquiries.assigned_to` | C | **Yes** — authority rule (§3) |
| Manager dashboard counters (assigned / pending / confirmed / completed Bookings, unread messages), Booking list, sidebar badges (`Message::scopeAccessibleTo`) | `bookings.manager_id` | B | No |
| Chat JSON (`messages.index`, `messages.store`, `messages.unread-count`), `ChatMessagePresenter` | Booking participation | B | No (SEC-FIX-01 contract re-tested) |
| Notifications: bell count/preview, `NotificationController::open`, `MessageNotificationVisibility` | Booking participation, **only** `NewMessageDatabaseNotification` | B | No (read-side verified fail-closed, §6) |
| Admin overview / sidebars | no inquiry counters exist; the sidebar item is a link without a count | — | No |
| Client detail | no client-detail route exists (list only) | — | — |

**Finding that shaped the slice:** there are **no IncomingInquiry or Inquiry counters on any screen today**. The only inquiry "summary" is the E5 list with its status filter. I3 therefore changed **no live counter**; it adds a tested, query-only summary service that nothing renders yet (§5).

## 2. Defects and gaps reproduced on synthetic data before any change

| Id | Reproduction (red before, green after) | Exposure today |
|---|---|---|
| I3-G1 | A linked pair (`Inquiry` snapshot assigned to Manager A, legacy source assigned to A). E5 reassigns the source to B. **A kept `view` / `updateDraft` on the `Inquiry`**, and `InquiryDraftStore::save` accepted A's draft. | Latent: no route returns an `Inquiry` yet, so no user-visible leak existed. It would have become one with the first Inquiry-reading screen. |
| I3-G2 | Conflicting pair (legacy A, snapshot B): the Inquiry-side assignee B was granted `view` although E5 says A. | Latent, same reason. |
| I3-G3 | An independent `Inquiry.client_user_id` gave the assigned Manager **no** client visibility (I1/I2 documented this as fail-closed, required before SWITCH). | Not reachable: nothing creates independent Inquiries yet. |

## 3. Transitional authority model (single source: `App\Services\Inquiries\InquiryAuthority`)

| Case | Who is responsible | Notes |
|---|---|---|
| **Independent** `Inquiry` (manual origin, no source) | `inquiries.assigned_to` | No legacy counterpart exists. |
| **Linked** `Inquiry` (`external_intake`, source exists) | The **legacy** record. The Inquiry side grants a Manager access **only if** `inquiries.assigned_to` = `incoming_inquiries.assigned_to` = that Manager | E5 stays authoritative until an approved cutover. |
| Linked, assignees differ (incl. assigned ↔ unassigned) | **Nobody on the Inquiry side.** The legacy record keeps behaving exactly as today (its assignee still sees it through E5) | No winner by timestamp or history; nothing is rewritten; reported by the reconciler as `OWNERSHIP_CONFLICT`. |
| Malformed provenance (external without source id, manual with one, source deleted) | Managers: nobody. Admin: oversight unchanged | Reported as `STRUCTURAL`. |
| `created_by`, `converted_by`, `closed_by`, `client_linked_by`, `draft_updated_by`, backfill executor, historical assignee | **Never** grant access | Tested. |

`scopeResponsibleManager` (SQL) and `isResponsibleManager` (single row) implement the same rule; a test compares scope, policy and `responsibleInquiryIds` over every combination of legacy/snapshot assignee, orphans and malformed rows. Admin keeps `view`/`updateDraft` on every Inquiry (unchanged). Tourist/no role: nothing.

A later switch changes **only the "linked" branch** of `InquiryAuthority` (and removes the agreement requirement); the independent branch and all callers stay as they are. There is deliberately **no runtime flag** for it: an untested switch would be a permission risk.

`InquiryDraftStore` additionally locks the source `IncomingInquiry` row (order Inquiry → IncomingInquiry; E5 locks only the source, so no inverse path) so an E5 reassignment cannot slip between the authorization and the write (§8, MySQL).

## 4. ClientVisibility

One branch added to the Manager rule: a client linked to an **independent** `Inquiry` that the Manager is assigned to **now**, and only if that client is a `tourist`. Linked Inquiries add **no** client visibility in either direction:

- while E5 is authoritative the client comes from the legacy record (existing branch), so a second, possibly diverging snapshot cannot duplicate or widen it (test: the snapshot names another client — not exposed);
- therefore there is nothing to de-duplicate across models; membership is by `IN (subquery)`, so a client reachable by Booking + legacy inquiry + Inquiry still appears **once**;
- reassignment (independent Inquiry or legacy source) revokes the client at once; closed/converted rows follow the same current-assignee rule as today's legacy rows;
- Admin oversight, the neutral client-verification workflow (option C, AOA-01-I7) and the approved-request branch are unchanged.

Consequence to know: a client that becomes visible through an independent Inquiry is also selectable in the booking-create form and the legacy client-selection step — that is the existing meaning of "visible client". It cannot occur in production until AOA-04 creates independent Inquiries. With no `inquiries` rows the visible set equals the pre-I3 set (test).

## 5. Counters — `InquiryWorkloadSummary` (prepared, not wired to any screen)

Unit = one business inquiry, counted **once**: an external inquiry is counted from its **legacy record** (E5 authoritative), even when a linked `Inquiry` exists (the shadow is not counted again); an independent `Inquiry` is counted from itself. Only `actionable()` legacy rows count (technical load states are not business inquiries); statuses are the shared vocabulary `new / in_progress / converted / closed` (identical meaning, verified in I2). Visibility = the list rule (Manager: own now; Admin: all). Returns `by_status`, `total`, `external`, `independent`, and, for Admin only, `ownership_conflicts` and `unresolved_provenance`.

Consequences, all tested: a conflicting pair is counted once under the legacy assignee and never under the snapshot assignee; a reassignment moves the count immediately; with no `inquiries` rows the result equals the E5 list result for every actor; query count is constant (2 aggregates for a Manager, 4 for an Admin plus role lookups) regardless of volume — no per-record authorization.

## 6. Notifications (read side only; nothing is sent)

`MessageNotificationVisibility` filters on `type = NewMessageDatabaseNotification` **and** Booking participation, so any other notification type is invisible to every role in the bell count, the preview and `NotificationController::open` (404, not marked read). Tested with a synthetic "future Inquiry" notification: hidden for Manager and Admin, direct-ID open → 404 (also for Admin and for a foreign id). The prepared rule for a future Inquiry notification is `whereIn('data->inquiry_id', InquiryAuthority::responsibleInquiryIds($user))`: evaluated **at display time** by current responsibility, so previous-Manager snapshots, Admin-owned, unassigned, other-Manager, conflicting-pair, stale-snapshot and orphaned (deleted) objects are all hidden while records are not deleted. A client visible through an Inquiry does not reveal Booking-message notifications of another Manager. Sender names/previews of a hidden notification never reach the page. No delivery event was changed; SEC-FIX-01 presenter behaviour is re-tested with Inquiry data present.

## 7. Two verification contracts

| | A. Initial backfill integrity (`InquiryBackfillVerifier`, I2 — **unchanged**) | B. Transitional / runtime reconciliation (`InquiryReconciler`, I3 — new, read-only) |
|---|---|---|
| Question | Was each source represented exactly as the backfill would create it? | Are the two models still structurally consistent while both live? |
| Valid | Before the first legitimate change | While both exist |
| Mutable fields | Must equal the source snapshot | Not required to equal it forever, never ignored |

Reconciler verdicts (each Inquiry gets exactly one; reason codes list every difference; report holds counts, codes, ≤ 20 numeric ids per code — no names, e-mail, phone, payload):

| Verdict | Meaning | Examples |
|---|---|---|
| `IN_SYNC` | All mirrored fields equal | Fresh backfill; a **draft edit** (draft fields have no source counterpart — counted as `expected_evolution`, not compared) |
| `INDEPENDENT` | No source to compare | Future manual Inquiry |
| `OWNERSHIP_CONFLICT` | Current assignees differ (incl. assigned ↔ unassigned) | E5 reassigned after backfill; stale snapshot. No winner, nothing rewritten |
| `REQUIRES_EVIDENCE` | Status / closure / conversion / client / assigned-at differ, assignees equal | Legacy conversion after backfill; Inquiry-side close. **Not classified as corruption nor as evolution** — without an Inquiry change log or sync marker the cause is unknowable. A factual `direction_of_difference` (`source_ahead`, `inquiry_ahead`, `same_stage` by process stage) is reported as information, not as a cause |
| `STRUCTURAL` | Genuine corruption | `missing_source`, `external_without_source_reference`, `malformed_provenance`, `inquiry_for_unsupported_source`, `inquiry_invariant_violation` (e.g. assignee deleted), `booking_reference_conflict` (pair members name different Bookings), `booking_shared_between_records` (one Booking, two different business inquiries); globally `duplicate_source_links`, `duplicate_booking_links` |
| (outside pairs) `unprocessed_sources` | Actionable source without an Inquiry | Incomplete migration **or** arrived after it — indistinguishable without a backfill marker |

`structurally_consistent` = no `STRUCTURAL` and no duplicate links (legitimate evolution is allowed). `fully_reconciled` additionally needs no ownership conflict, no `REQUIRES_EVIDENCE`, no unprocessed source; otherwise `incomplete_because` names each count and `limits` states what the tool cannot know (`no_inquiry_change_log`, `no_sync_marker`, `draft_not_mirrored`). Run via `inquiries:backfill --connection=<qa> --reconcile` — same `VerifiedQaConnection` guard as the other modes (refused before the first query; mutually exclusive with `--apply`/`--verify`); exit 1 while incomplete.

**Missing prerequisites (documented, not fabricated):** an Inquiry-side audit/sync event (AOA-04 wiring of `inquiry.created/assigned/reassigned` through `AuditRecorder`) and a catch-up high-water marker are required before `REQUIRES_EVIDENCE` and `unprocessed_sources` can be split into "expected" vs "defect".

## 8. Ongoing legacy intake — catch-up and cutover prerequisites (for a later AOA-03 slice)

A one-time backfill is **not** enough. E5 keeps running, so after any backfill the following appear and must be handled by a bounded, owner-approved catch-up before SWITCH:

1. new `IncomingInquiry` rows (no Inquiry) → `unprocessed_sources`;
2. assignment changes on legacy records → stale snapshots (`OWNERSHIP_CONFLICT`);
3. conversions and closures after the backfill → `REQUIRES_EVIDENCE` (`source_ahead`);
4. repeated provider deliveries (webhook `webhook_count`) — dedup is on the legacy key, so they must not create Inquiries;
5. users deleted after backfill (`assigned_to` → NULL → `inquiry_invariant_violation`) → Admin re-allocation, not a rewrite.

Cutover prerequisites: (a) a catch-up/sync mechanism with a recorded high-water mark (or a freeze window) — **no dual-write or listener was added in I3**; (b) Inquiry-side audit events so reconciliation can separate authorized changes; (c) explicit owner approval, a **verified backup** and an isolated copy rehearsal on **real-data copy** before any canonical migration/backfill; (d) owner decision per real `BLOCKED` class from I2 (`in_progress` without assignee, `converted` without a surviving Booking, `business_progress_on_non_actionable_source`, `booking_owned_by_other_inquiry`, …) — counts on real data are **unknown**; (e) replacing the SWITCH-time check by the reconciler's `fully_reconciled`; (f) flipping the "linked" branch of `InquiryAuthority` and switching the E5 routes in one reviewed step.

## 9. Residuals and notes (recorded, not fixed)

1. `VerifiedQaConnection` proves **configuration**, not server identity: any loopback instance on an unprivileged port other than 3306/3308 with an `aoa03_qa_*` database name passes. The services accept only that object (private constructor, only `verify()` builds it; reconciler/classifier/service/verifier use `$qa->name` exclusively — verified by reading every query path, plus a no-query-on-refusal test), so direct service invocation cannot bypass it from application code; only reflection/`unserialize` could forge it. A server-identity probe (e.g. data directory) would need a query *after* trust is established and was not added.
2. `ChatParticipantJsonMinimizationTest` (SEC-FIX-01) is **SQLite-only**: its fixture writes `1903-04-05` into a MySQL `TIMESTAMP` column (1292). Pre-existing, unrelated to I3; the same JSON contract is re-tested on MySQL in `InquiryAwareNotificationAndJsonTest`.
3. `InquiryWorkloadSummary` is not rendered anywhere; wiring it into a screen is a separate, browser-tested change.
4. I1 limits stay open: user deletion nulls `assigned_to`/`booking_id`; draft text is not content-classified; no audit wiring/creation/assignment (AOA-04). The Admin Booking-chat write contract is unchanged.

## 10. Evidence

- **PHPUnit** (SQLite `:memory:`, PHP 8.4.13, Xdebug off, `memory_limit=3G`): full suite **1925 tests / 15221 assertions, 0 failures, 0 errors** (+61 tests / +244 assertions over 1864 / 14977): `InquiryAwareAccessTest` (17), `InquiryWorkloadSummaryTest` (9), `InquiryAwareNotificationAndJsonTest` (10), `InquiryReconcilerTest` (25). No existing expectation changed. The red phase was recorded first (8 failures on unmodified code). Mutation checks (authority drops the source match, client branch drops the independent/tourist guard, counter includes linked rows, reconciler ignores ownership) are each caught.
- **Disposable MySQL 9.7.1** (fresh data directory, port 3402, loopback, `--no-defaults`, synthetic credentials, databases `aoa03_qa_i3` only; default connection of the harness pointed at a dead port; WAMP 3306/3308 and `turfirma_rebuild_v4` never touched; instance stopped afterwards): new classes `InquiryAwareAccessTest`/`WorkloadSummaryTest`/`NotificationAndJsonTest` (36 tests) and 163 related existing tests (E5 inquiry, client visibility, client verification, staff access matrix, notifications, bell) pass under `ONLY_FULL_GROUP_BY`; a dedicated harness (**38 checks pass**) covers reconcile classes, read-only hash, chunk independence, CLI guards, counters/aggregates, scope = policy = ids, and **two-process row-lock proof**: with the source lock an E5 reassignment waits ≈ 1.6 s for the open draft transaction, **without it (negative control, pre-I3 behaviour) it goes through in ≈ 30 ms**; reassignment committed first → the former Manager's draft is refused. `EXPLAIN`: the new branch uses `inquiries_assigned_to_status_index`. Harness/logs: `C:\Avilona_private\AOA-03-I3_Verification` (outside the repository). The 16 SEC-FIX-01 fixture errors above are the only MySQL failures.
- **Browser QA** (headless Chrome, disposable app + MySQL, synthetic data, 360 px and 1200 px): **58 checks each, 0 failures** — Manager clients list/booking dropdown sets and no duplicates, legacy inquiry list, foreign direct URLs (404/403, no data), hidden-notification bell/dashboard/direct open (404, stays unread), JSON (`403`, `{"count":0}`), Admin oversight, independent-Inquiry reassignment and E5 reassignment revoking at once, tourist denial, payload marker scan, no HTTP 500, no console errors, no horizontal overflow, Tab focus visible/in view. First QA run had a harness defect (static root) and 4 script mistakes, fixed and re-run; the application had no defect.
