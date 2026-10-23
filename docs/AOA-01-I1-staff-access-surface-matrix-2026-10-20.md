# AOA-01-I1 — staff data-access surface matrix (implementation evidence)

**Date:** 2026-10-20 · **Slice:** AOA-01-I1 · **Source baseline:** `013d2483` (+ the I1 audit foundation).

> **Update 2026-10-21 (AOA-01-I2A):** gaps G1, G2, G3, G4 and G6 below were *implemented and verified* in AOA-01-I2A — see **§6 (implementation evidence)**. The "Gap" columns and §3 describe the state at I1 and are kept as the historical baseline. G5, G7 and G8 were *implemented and verified* in AOA-01-I2B — see **§7**. The MySQL migration/concurrency gate and the closure risks were independently verified in AOA-01-I3 — see **§8** (findings I3-F1…F3; AOA-01 is not closed). **Update 2026-10-22 (AOA-01-I4):** I3-F1 and I3-F2 were *fixed and verified* (incl. multi-process MySQL) — see **§9**; I3-F3 remains OPEN and AOA-01 is not closed. **Update 2026-10-22 (AOA-01-I5):** the Booking-document upload/delete/download and client password-setup residuals and the deleted-message queued mail were *investigated, reproduced and fixed* — see **§10**; I3-F3 and owner closure remain OPEN. **Update 2026-10-23 (AOA-01-I7):** I3-F3 was **resolved by the owner-approved option C and implemented** — see **§12**; **Closure (2026-10-23):** the owner formally closed AOA-01 with tracked residuals — status and residual register are canonical in `docs/roadmap.md` (§A1, §A7.1); this file remains technical evidence only. **Update 2026-10-23 (AOA-02):** the staff visibility/assignment policy layer was consolidated and closed — see **§13**.
>
> **Role of this document: technical evidence for AOA-01-I2 / AOA-02. It is NOT a roadmap.** Status, slice order and owner decisions live only in [`docs/roadmap.md`](roadmap.md). Nothing in the *Gap* or *I2* columns is implemented by I1 (the only behavioural change in I1 is the Admin log viewer, AO-04).

**Method.** Source read of routes, controllers, policies, services and Blade layouts, plus a throw-away runtime probe (synthetic users and records, SQLite `:memory:`, not committed) that exercised the suspected gaps. Statuses:

- **VERIFIED** — read in source; where marked *(probe Nn/Pn)* also reproduced at runtime.
- **CONFIRMED GAP** — reproduced at runtime *(probe)* and contradicts the owner-approved rules (roadmap §A3–§A4).
- **NOT VERIFIED** — not proven either way in I1.

Owner rule under test: a Manager sees only **own assigned** Inquiries, Bookings and related private communications; Admin may inspect all. Hiding links is not access control.

## 1. Inquiry (`IncomingInquiry`) surfaces — group middleware `role:manager,admin`, prefix `/cabinet/manager/inquiries`

| # | Entry point → code | Data exposed | Authorization today | Manager | Admin | Server-side ownership scoping | Status | Proposed I2 regression test |
|---|---|---|---|---|---|---|---|---|
| I-01 | `GET /inquiries` → `IncomingInquiryController::index` (`IncomingInquiry::query()`, no scope, no policy call) | Tourvisor id, client name, destination, state, assignee name | route role middleware only | sees **all**, incl. unassigned and colleagues' (P1) | all | **No** | **CONFIRMED GAP G1** | Manager A's list contains only A's inquiries; unassigned and B's absent from rows, pagination total and `?status=` filters; Admin sees all |
| I-02 | `GET /inquiries/{id}` → `show`, `IncomingInquiryPolicy::view` (= any staff) | client name/phone/e-mail/comment, operator, hotel, reference price, assignee, linked client, booking, convert/close actors | policy `view` = `isStaff` | opens any inquiry, 200 with client phone (P2) | all | **No** | **CONFIRMED GAP G1** | A → B's and unassigned inquiry denied with no client data in body; own allowed; Admin allowed |
| I-03 | `POST …/claim` → `claim`, policy `claim` (= any staff); `IncomingInquiryWorkflow::claim` checks only that the actor is assignable | state/assignee change | policy + atomic conditional UPDATE | **can claim any unassigned inquiry** (P3) | can self-claim | n/a | **CONFIRMED GAP G2** | Manager claim of unassigned → 403 and row unchanged; Admin self-assignment succeeds |
| I-04 | `POST …/reassign` → `reassign`, policy `reassign` (admin only), re-authorized under row lock | assignee | policy + service Gate | 403 (P8 N8) | allowed; **no reason, no previous/new/actor/time history**; only from `in_progress`, so Admin cannot assign a NEW inquiry directly to a Manager | n/a | VERIFIED authorization; **CONFIRMED GAP G3** (no reason/history) and **G4** (no direct initial assignment) | Admin assigns NEW → Manager in one step; reassign without reason rejected; reassign writes an audit event in the same transaction; Manager reassign stays 403 |
| I-05 | `POST …/client/select` → `selectClient`, policy `process`, re-authorized in service | links any **active tourist by id** | policy `process` (admin or assigned manager) | only own assigned; may link *any* active tourist id, not only listed candidates | allowed | ownership yes; client directory no (G5) | VERIFIED (authz); see G5 | Non-assignee → 403 incl. repeated submit |
| I-06 | `POST …/client/create` → `createClient`, policy `process` | creates tourist | policy + service Gate | own assigned only | allowed | yes | VERIFIED | Non-assignee → 403; no account created |
| I-07 | `POST …/client/password-setup` → `sendPasswordSetup`, policy `process`; service calls `lock()` **outside** any transaction (row lock is a no-op) | sends reset link | policy + service Gate | own assigned only | allowed | yes | VERIFIED authz; **NOT VERIFIED** race impact | Wrap in transaction; non-assignee → 403 |
| I-08 | `POST …/convert` → `convert`, controller `authorize('process')`; service returns the existing Booking for an already-converted inquiry **before** `authorize` | Booking id/object | controller policy; service Gate only on the non-idempotent path | HTTP non-assignee → 403 (P5c); **service-level call by a non-assignee returns the Booking (P5d)** | allowed | controller yes; service no | VERIFIED (HTTP) / **CONFIRMED GAP G6** (service defence-in-depth) | Authorize before the idempotent return; direct service call by non-assignee throws; idempotent repeat by the assignee still returns the same Booking |
| I-09 | `POST …/close` → `close`, policy `close` = `process` **or any staff while unassigned**; service returns early if already closed **before** `authorize` | closes inquiry | policy + service Gate on non-idempotent path | **can close any unassigned inquiry** (P4); HTTP close of colleague-closed → 403 (P5a); service early return unauthorised (P5b) | allowed | partial | **CONFIRMED GAP G2** (unassigned close) and **G6** | Manager close of unassigned → 403; early-return path authorised |
| I-10 | JSON / count / badge paths for inquiries | — | — | none exist (no inquiry JSON route, no sidebar/layout inquiry counter, no search endpoint besides I-12) | — | — | VERIFIED by route + view grep | Guard test: no inquiry data in any JSON/count response for Manager |
| I-11 | `GET /api/webhooks/tourvisor/inquiries/{token}` → `TourvisorWebhookController` | status string only | secret path token + throttle | n/a | n/a | n/a | VERIFIED (no inquiry data in response) | none |
| I-12 | Client lookup: `IncomingInquiryClientMatcher::candidatesFor/search` (shown on I-02 when `process` allowed) | name, e-mail, phone of **any active tourist**; `withCount('bookings')` loaded but **not rendered** (verified by grep of the view) | `process` only | assigned manager can search the whole tourist directory (min 2 chars) (P7) | same | **No** (by design for linking) | **CONFIRMED GAP G5** (owner decision needed on lookup scope) | Define after owner decision (e.g. exact e-mail/phone match only, minimum fields); never show other staff's booking/correspondence history |
| I-13 | `bookings/show.blade.php:241` link "входящее обращение #N" | link only | visible to anyone who may view the Booking | after I2 may point to an inquiry the viewer cannot open | — | — | VERIFIED (link) | Link rendered only when `can('view', $inquiry)`; otherwise plain text/omitted |

## 2. Booking, chat, documents, notifications, search, finance

| # | Entry point → code | Data exposed | Authorization today | Manager | Admin | Server-side ownership scoping | Status | Proposed I2 / AOA-02 regression test |
|---|---|---|---|---|---|---|---|---|
| B-01 | `manager/dashboard`, `manager/bookings`, `manager/clients`, `manager/statistics`, `manager/finance` → `ManagerController` | own Bookings, derived clients, own revenue/paid | `where manager_id = auth id` in every query | own only (N5) | sees own only on these pages (Admin has separate admin pages) | **Yes** | VERIFIED | Keep: A's pages never contain B's booking/client |
| B-02 | `GET /bookings/{booking}` → `BookingController::show`, `BookingPolicy::view` | full Booking (+ messages, documents, manager notes) | policy: admin all / manager `manager_id === id` / tourist owner | colleague's → 403 (N1) | all | **Yes** (strict `===`) | VERIFIED; int-typing under MySQL **NOT VERIFIED** | Keep; add MySQL-driver check before E6 |
| B-03 | `edit`/`update`/`confirm`/`complete`/`cancel` → `BookingController` + `BookingPolicy` | edit status, `manager_notes`, `total_price` | policy; update field whitelist (status/manager_notes/total_price) | own only | all | **Yes** | VERIFIED; **price revision has no audit event** | AOA-09/08 to emit `booking.price_revised` in the same transaction |
| B-04 | `POST /bookings/{booking}/assign-manager` (`role:admin` + policy `assignManager`) | `manager_id` | admin only | 403 | allowed | n/a | VERIFIED; **Booking assignment/reassignment keeps no reason/history** (G7) | Out of I2 scope unless owner extends §A4 to Bookings; record as candidate |
| B-05 | `GET /bookings/create`, `POST /bookings` → `BookingController::create/store` | `create` lists **all active tourists (id, name, e-mail)** to any Manager/Admin; `store` forces `manager_id`/status from actor | role middleware; whitelist | sees whole tourist directory (P8); cannot forge owner/manager | same | no (directory) | **CONFIRMED GAP G5** (same decision as I-12) | Decision-dependent |
| B-06 | `BookingController::index` | — | — | no route points to it (`GET /bookings` redirects) | — | — | VERIFIED unreachable | none |
| B-07 | `CabinetController::bookings` (`/cabinet/bookings`) | staff branch builds an **unscoped** `Booking::query()` | `redirectIfNotTourist()` redirects every admin/manager **before** the query | unreachable | unreachable | latent | VERIFIED unreachable; **latent risk** | Regression: manager/admin hitting `/cabinet/bookings` is redirected and never receives other bookings; consider deleting the dead branch |
| B-08 | Chat pages: `manager/chat/{id?}`, `admin/chats/{id?}`, `cabinet/chat/{id?}` | messages + attachments | manager `where manager_id`; admin read-all/write-if-assigned; tourist own | colleague's → 404 (N2) | read all, composer only if assigned | **Yes** | VERIFIED | Admin contract unchanged: keep N6 (observe 200) and N7 (write 403) |
| B-09 | `messages.index/store/markAsRead/unread-count/destroy/attachment` → `MessageController` | JSON messages, attachments, counts | read gate `authorizeBookingRead`; write gate `authorizeBookingWrite` (Admin only if assigned); `markAsRead` receiver only; `unread-count` = own receiver rows; `destroy` = sender or any Admin | colleague's booking → 403 (N3, N4) | read all, write only if assigned (N7); may delete any message | **Yes** | VERIFIED; Admin message deletion is **unaudited** | Keep; AOA audit candidate for `admin.action` on message deletion |
| B-10 | Notifications: header bell (`cabinet/layouts/app.blade.php`) + `NotificationController::open` | sender name + 60-char preview of latest unread message; `open` redirects | notifiable relation; `open` re-checks booking assignment (manager) | after a Booking is reassigned the **former** manager still sees the stored preview and unread count (P9, P9b) although chat/JSON are denied (P9c 403, P9d 404) | n/a | partly | **CONFIRMED GAP G8** (minor, ≤ 60 visible chars) | On reassignment hide/mark notifications of the former manager; header query filters by current assignment |
| B-11 | Booking documents: `bookings.documents.{store,download,destroy}`, `admin.bookings.documents.*` | files | `role:manager,admin` + `authorizeBooking` (admin all / manager own) | colleague's → 403 | all | **Yes** | VERIFIED (source) | Keep existing tests; add colleague-download 403 if absent |
| B-12 | Personal documents: `cabinet.documents.personal*`, `manager.documents*`, `admin.user-document.download` | user files | owner-only (`user_id === auth id`); admin download is admin-only route | own only | any user's (admin route) | **Yes** | VERIFIED | none |
| B-13 | Tourist booking-document download `cabinet.documents.bookings.download` | file | owner only; staff are redirected away | n/a | n/a | **Yes** | VERIFIED | none |
| B-14 | Staff search | Admin `users`/`bookings` search (admin group); Manager has **no** search endpoint other than I-12 | `role:admin` | none | all | n/a | VERIFIED (grep) | none |
| B-15 | Admin-only pages: users, roles, user-show, finance, bonus, settings, logs, audit | all-system data | route group `role:admin` | 403 | allowed | n/a | VERIFIED; audit/logs also tested | Keep |
| B-16 | 403 vs 404 on ownership denial | — | mixed (AO-07) | policy 403, chat 404, notification 404 | — | — | **REVIEW** (roadmap AO-07), not a vulnerability by itself | Pick one convention in AOA-02 |

## 3. Confirmed gaps (summary)

| ID | Gap | Surface | Severity note |
|---|---|---|---|
| G1 | Manager lists and opens all inquiries incl. unassigned and colleagues' (client phone/e-mail/comment) | I-01, I-02 | Known (roadmap §A5); reproduced. **RESOLVED in I2A (§6).** |
| G2 | Manager can claim and close unassigned inquiries | I-03, I-09 | Known; reproduced. **RESOLVED in I2A (§6).** |
| G3 | Reassignment has no required reason and no durable history | I-04 | Known; foundation now exists (`audit_events`). **RESOLVED in I2A (§6).** |
| G4 | Admin cannot assign a NEW inquiry directly (must claim-then-reassign) | I-04 | New precision on §A4. **RESOLVED in I2A (§6).** |
| G5 | Tourist directory (name/e-mail/phone) exposed to any Manager via inquiry lookup and booking-create dropdown | I-05, I-12, B-05 | Needs an owner decision on lookup scope |
| G6 | Authorization is skipped on idempotent early returns inside `convert` and `close` (HTTP is still blocked by the controller) | I-08, I-09 | Defence-in-depth. **RESOLVED in I2A (§6).** |
| G7 | Booking assignment/reassignment keeps no reason/history | B-04 | Candidate; not in §A4 text |
| G8 | Former manager keeps notification preview/unread count after Booking reassignment | B-10 | Minor leak |

No *new* critical exploitable flaw beyond the already-recorded §A5 Manager-visibility gap was found; G1/G2 remain exploitable today by any authenticated Manager and are the reason AOA-01-I2 comes next.

## 4. AOA-01-I2 dependency plan (not implemented in I1)

Existing code: `IncomingInquiryPolicy` decides *who* by role/assignee; `IncomingInquiryWorkflow` re-checks state under a row lock inside `DB::transaction`; the controller authorizes first. The minimum atomic change set:

1. **Policy** (`IncomingInquiryPolicy`): `view`/`close` for Manager only when `assigned_to === id`; Admin always; remove `claim` for Manager and the "any staff while unassigned" close; new `assign` (Admin only).
2. **Scope** (`IncomingInquiry::scopeVisibleTo(User)`): Admin all; Manager `assigned_to = id`; others none. Use it in `index` (rows, pagination, filters) and nowhere rely on hidden links.
3. **Service** (`IncomingInquiryWorkflow`, all inside one `DB::transaction` with `lockForUpdate`, re-authorizing on the locked row **before any early/idempotent return**):
   - `assign(inquiry, admin, assigneeId)`: only for `new` + unassigned → `in_progress`, `assigned_to`, `claimed_at`; assignee must satisfy `User::assignableToBookings()`; Admin may pass self; records `inquiry.assigned` (audit, same transaction). No claim-then-reassign.
   - `reassign(…, reason)`: requires non-empty reason, state `in_progress`, different eligible assignee; records `inquiry.reassigned` with `previous_assignee_id`, `new_assignee_id`, `reason` (same transaction). A failed audit write rolls the reassignment back (covered by I1's rollback tests).
   - `convert`/`close`: move `authorize` ahead of the idempotent returns; keep row lock, conditional UPDATE, unique `booking_id`, and the single-Booking guarantee unchanged; record `inquiry.converted` in the existing transaction. Wrap `sendPasswordSetup` in a transaction.
   - Existing assignments are preserved: no data migration; `assigned_to` values are not rewritten.
4. **Controller/view**: `index` uses the scope; Admin form for assignment (self or eligible Manager); reassign form with required reason; Booking page shows the inquiry link only when `can('view')` (I-13); Admin sees the audit trail for the inquiry (`audit_events` subject `incoming_inquiry`).
5. **Unchanged by design**: Admin Booking-chat contract (read all, write only if assigned; probes N6/N7), E5 conversion atomicity/idempotency, Booking authorization (separate slice).
6. **Update existing tests deliberately**: `IncomingInquiryWorkflowTest` (manager claim, unassigned close by any staff) and `IncomingInquiryStaffAccessTest` (manager sees all) encode today's behaviour and must be changed, not deleted, in I2.
7. **Owner decisions needed before/within I2**: lookup scope for G5; whether denial should be 403 or 404 for foreign inquiries (AO-07).

## 5. I1 foundation notes (for E6 and later slices)

- New table `audit_events` (migration `2026_10_20_000000_create_audit_events_table`): additive, no FK on `actor_id`, nothing backfilled (starts empty). Append-only is enforced **in the application** (model events + query-builder guards + no mutating routes); it is **not** a database guarantee.
- Tested in I1 on SQLite `:memory:` (PHPUnit) and on a disposable SQLite file with migrate → `rollback --step=1` → migrate. **Not tested on MySQL.** Required before E6 (guarded plan, separate from I1): apply on a disposable MySQL copy; confirm `json`/`uuid`/`timestamp` column behaviour and index names; decide whether the application DB user should lack `UPDATE`/`DELETE` on `audit_events` (or use triggers) for a database-level append-only guarantee; confirm PDO integer typing for the strict `===` ownership comparisons (B-02, B-09).
- The recorder is not wired into any business process in I1; no existing behaviour changes except the Admin log viewer (AO-04).

## 6. AOA-01-I2A implementation evidence (2026-10-21)

**Scope:** legacy `IncomingInquiry` workflow only (no new `Inquiry` entity, no migration, no canonical-MySQL or production change). Gaps G1, G2, G3, G4, G6 are **resolved to the extent verified below**. **G5, G7, G8 are NOT addressed and remain OPEN (AOA-01-I2B)**; the complete Manager privacy contract is therefore **not** claimed.

### 6.1 What changed (by surface)

| Surface | Before (I1) | After (I2A) | Evidence |
|---|---|---|---|
| I-01 list, filters, pagination, counts | unscoped | `IncomingInquiry::scopeVisibleTo(User)`: Admin all, Manager `assigned_to = id`, others none; applied in `index` (rows, `?status=`, `total()`, pages) | `test_manager_list_contains_only_own_inquiries`, `test_pagination_and_filters_do_not_leak_foreign_rows`, `test_visibility_scope_matches_policy_for_every_role` |
| I-02 detail / direct URL / JSON | any staff | controller `visible()` → **404 without data** for foreign/unassigned (HTML and JSON); policy `view` = Admin or assigned Manager | `test_manager_cannot_open_foreign_unassigned_or_admin_owned_inquiry_and_gets_no_data` |
| Every inquiry action | policy only | `visible()` (404) **before** the ability check (403 for Admin-only operations on an own inquiry); service failure after a concurrent reassignment is mapped to the same 404 | `test_every_inquiry_action_is_hidden_from_a_manager_who_does_not_own_it`, `test_manager_cannot_claim_assign_or_reassign_even_for_own_inquiry` |
| I-03 claim | any staff | policy `claim` = Admin only (self-assignment through the same `assign` contract) | `test_manager_cannot_take_unassigned_inquiry`, `test_service_level_manager_claim_of_unassigned_inquiry_is_refused` |
| I-04 initial assignment (G4) | not possible directly | new `POST …/assign`, policy `assign` = Admin; `IncomingInquiryWorkflow::assign` — one transaction, row lock, re-authorization, eligibility via `assignableToBookings()` (locked), conditional UPDATE (`new` + `assigned_to IS NULL`), then `inquiry.assigned` audit (`new_assignee_id`, `self_assigned`); repeat with the same target is a no-op without a second event; a different target after someone else's assignment is rejected | `test_admin_assigns_new_inquiry_directly_to_a_manager_and_records_audit`, `test_assignment_rejects_ineligible_targets_without_side_effects`, `test_repeated_assignment_is_harmless_and_does_not_duplicate_audit`, `test_assignment_cannot_overwrite_a_concurrent_assignment`, `test_stale_second_assignment_loses_atomically` |
| I-04 reassignment (G3) | no reason/history | `reassign(…, reason, expectedAssigneeId)`: Admin only; reason 5–300 chars, whitespace-collapsed and pre-checked with the **unchanged** `AuditMetadata` validator (e-mail / path / token-like text rejected with a form error — validator not weakened); `expected_assignee_id` rejects stale forms; `inquiry.reassigned` audit (`previous_assignee_id`, `new_assignee_id`, `reason`; actor and `occurred_at` from the recorder) in the same transaction | `test_admin_reassigns_with_reason_and_event_is_recorded`, `test_reassignment_requires_a_meaningful_bounded_safe_reason`, `test_stale_reassignment_is_rejected_and_retry_does_not_duplicate_history`, `test_reassignment_requires_assignable_target_and_in_progress_state` |
| Audit atomicity | n/a | a failing `AuditRecorder` rolls back assignment and reassignment (state, `assigned_to`, `claimed_at`, zero events) | `test_assignment_rolls_back_when_audit_recording_fails`, `test_reassignment_rolls_back_when_audit_recording_fails` |
| Revocation | n/a | former Manager gets 404 on detail/close/list immediately; new Manager gains access | `test_previous_manager_loses_access_and_new_manager_gains_it_immediately` |
| I-08 / I-09 (G6) | `authorize` after the idempotent return | `authorize` moved before the "already converted" and "already closed" returns; HTTP maps a revoked owner to 404; legitimate idempotent repeats by owner/Admin unchanged | `test_repeated_conversion_after_access_is_revoked_returns_no_booking`, `test_repeated_close_after_access_is_revoked_is_refused` |
| I-09 close | any staff while unassigned | policy `close` = `process` (Admin or assigned Manager); unassigned inquiries can be closed only by Admin | `test_manager_closes_only_own_inquiry`, `test_unassigned_inquiry_is_closed_only_by_admin_and_assigned_one_by_responsible_or_admin` |
| I-13 Booking → inquiry link | link always rendered | rendered only when `can('view', inquiry)`, else plain text | `test_booking_page_links_to_inquiry_only_for_those_who_can_view_it` |
| Admin UI | reassign select only | Admin assign form (+ "take for myself"), reassign form with required reason and hidden `expected_assignee_id`, Admin-only "История назначений" read from `audit_events` | `test_admin_forms_are_rendered_only_for_admin`, `test_assignment_history_is_visible_to_admin_only` |
| Tourist | 403 | unchanged (route role middleware) | `test_tourist_has_no_access_to_inquiry_management` |

Denial convention for this slice: Manager + foreign/unassigned inquiry → **404**; Manager + Admin-only operation on an own inquiry → **403**; guest → login redirect; Tourist → 403. AO-07 (global 403/404 consistency) is **unchanged and still open**; Booking and chat denial behaviour was not touched.

Preserved by design (regression-tested): Tourvisor intake/provenance/deduplication, `booking_id` linkage and the single-Booking guarantee, assignment alone never creates or alters a Booking, no automatic or historical reassignment (existing owners keep their inquiries), no external calls. `inquiry.converted` is **not** emitted in I2A (conversion untouched).

### 6.2 Existing tests whose expectations changed (deliberately)

| Test | Old expectation (permissive E5) | New expectation and reason |
|---|---|---|
| `IncomingInquiryWorkflowTest::claimed()` helper | a Manager claims the inquiry through the HTTP route | an Admin assigns it through `…/assign`; Managers can no longer self-claim |
| `test_manager_claims_inquiry_and_technical_state_is_untouched` → `test_admin_claims_…` | Manager claim succeeds | Admin self-claim succeeds (same state/provenance assertions) |
| `test_repeated_claim_by_same_manager_is_harmless` → `…_same_admin_…` | Manager repeat claim | Admin repeat claim; also asserts a single audit event |
| `test_second_manager_cannot_steal_active_claim` → `test_admin_cannot_take_inquiry_already_assigned_to_someone_else` + `test_manager_cannot_take_unassigned_inquiry` | second Manager gets a form error | Admin gets a form error; a Manager now gets 404 |
| `test_stale_second_claim_loses_atomically` → `test_stale_second_assignment_loses_atomically` | two Managers race on claim | two Admins race on assignment (+ audit count) |
| `test_inquiry_with_unfinished_import_cannot_be_claimed` | Manager | Admin (Manager cannot see unassigned) |
| `test_inactive_staff_cannot_claim` → `test_inactive_admin_cannot_claim` | inactive Manager | inactive Admin (Manager has no route to the inquiry) |
| `test_admin_can_claim_and_reassign_only_to_assignable_staff` → `…_reassign_only_to_assignable_staff_with_reason` | reassign without reason | reason and `expected_assignee_id` required |
| `test_manager_cannot_reassign` | 403 with only `assigned_to` | 403 with the complete payload (own inquiry) |
| `test_other_manager_cannot_process_someone_elses_inquiry` | 403 | 404 (foreign inquiry does not exist for a Manager) |
| `test_selection_requires_inquiry_in_progress` | Manager 403 | Manager 404; Admin gets the original "take into work first" error |
| `test_conversion_preconditions_are_enforced_without_side_effects` (unassigned case) | Manager convert → 403 | Manager convert → 404 |
| `test_unclaimed_inquiry_can_be_closed_by_any_staff_but_…` → `test_unassigned_inquiry_is_closed_only_by_admin_and_…` | any staff closes an unassigned inquiry | only Admin; Manager gets 404 |
| `test_list_filters_by_workflow_state_and_shows_assignee` | asserted as Manager | asserted as Admin (Manager scoping covered in the new file) |
| `test_tourist_and_guest_cannot_use_any_inquiry_action`, `test_mutation_routes_are_behind_web_csrf_and_staff_role_middleware` | existing routes | `assign` added to both route lists; reassign payload extended |
| `IncomingInquiryStaffAccessTest::test_manager_sees_labelled_incoming_inquiry_…` | Manager sees an unassigned "Новое" inquiry | Manager sees an inquiry assigned to them ("В работе") |
| `IncomingInquiryStaffAccessTest::test_failed_and_unsupported_states_are_shown_honestly` | Manager | Admin (those inquiries are unassigned) |

### 6.3 Verification and limits

- PHPUnit (SQLite `:memory:`, synthetic data): **1561 tests / 12166 assertions, 0 failures, 0 errors** (previous baseline 1528 / 11806). Mutation spot-checks (view open to all staff; close idempotent return before authorize; reassign audit skipped) were each detected by the new tests.
- Browser QA (isolated disposable SQLite DB, headless Chrome, synthetic users; 360 px and 1200 px, 74/74 checks each): Admin sees all and assigns an unassigned inquiry **by keyboard only**; assigned Manager sees it; other Manager gets 404 without data; Manager cannot claim/assign/reassign/close foreign or unassigned (404/403 as specified); Admin reassigns by keyboard with a reason (a too-short reason is rejected with a visible error and writes no event); previous Manager loses access, new Manager gains it; owner close and convert (and their idempotent repeats) still work; Tourist gets 403; no horizontal overflow and no first-party console errors.
- **NOT verified:** true row-lock concurrency on MySQL. SQLite serialises writers, so the `lockForUpdate` / conditional-UPDATE protection is exercised only logically. A disposable-MySQL concurrency check (two Admins assigning/reassigning the same inquiry; assignment vs. Manager close) is **required before E6/production**, together with the I1 MySQL checks in §5.
- The Admin history view shows the reason text exactly as the Admin typed it (HTML-escaped). The audit validator does not detect phone numbers or other free-text PII; the form hint only warns about e-mail, paths and key-like strings.
- `sendPasswordSetup` still calls the row lock outside a transaction (I-07, unchanged; authorization there is intact).

### 6.4 Residual exposure (explicitly open — AOA-01-I2B)

- **G5:** the full active-Tourist directory (name/e-mail/phone) is still searchable by an assigned Manager (`IncomingInquiryClientMatcher`) and listed in the booking-create dropdown. I2A introduced no new lookup surface.
- **G7:** Booking assignment/reassignment still has no reason or history.
- **G8:** a former Booking manager keeps stale notification previews/unread count (≤ 60 visible characters). Inquiry reassignment creates no notifications, so I2A added no new leak here.


## 7. AOA-01-I2B implementation evidence (2026-10-21)

**Scope:** G5, G7, G8 only; legacy `IncomingInquiry` and Booking workflows; no new `Inquiry` entity, **no migration**, no canonical-MySQL or production change. Resolved **to the extent verified below** (SQLite `:memory:` + disposable-DB browser QA). True MySQL row-lock concurrency is **not** verified.

### 7.1 Owner decision implemented (G5, OWNER_APPROVED)

A Manager sees only clients tied to their **current** assigned inquiries or bookings; Admin sees the full directory; a client who exists only under another Manager is **not** discoverable by name, phone or e-mail; Admin links such a client to the receiving Manager's work through the existing Admin-capable `client/select` operation (no duplicate account, no Manager-controlled arbitrary linking). Visibility of one client never extends to that client's other bookings, correspondence or inquiries (those keep their own policies).

### 7.2 What changed (by surface)

| Surface | Before | After | Evidence |
|---|---|---|---|
| Client rule | none | `App\Services\Accounts\ClientVisibility` — SQL sub-queries: Admin all; Manager = clients of `bookings.manager_id = me` ∪ `incoming_inquiries.assigned_to = me AND client_user_id NOT NULL`; others none. Historical assignment grants nothing | `test_scope_is_applied_in_sql_and_excludes_inactive_and_non_tourists`, `test_manager_sees_clients_of_current_bookings_and_assigned_inquiries_only` |
| Manager clients page | clients of own bookings | scoped via the same rule (+ inquiry-linked clients); counts/"latest booking" only over the Manager's own bookings; Admin sees all tourists | `test_admin_sees_the_complete_directory_…`, `test_shared_client_clients_page_counts_only_own_bookings` |
| B-05 booking-create dropdown + `client_id` validation + owner lock | all active tourists | same scoped query for form, validation and write; failure text neutral ("Выберите клиента из доступного вам списка") | `test_booking_store_rejects_foreign_client_without_leaking_account_details` |
| I-12 inquiry candidates + manual search (`IncomingInquiryClientMatcher`) | whole directory, incl. booking count | `candidatesFor/search($actor)` scoped by `ClientVisibility`; the "заявок: N" figure (other managers' activity) removed | `test_foreign_client_cannot_be_found_by_name_phone_or_email_in_inquiry_search`, `test_manager_can_search_and_select_a_client_they_legitimately_work_with` |
| I-05 `client/select` | any active tourist id | service selects from the visible set; foreign and non-existent ids give the **identical** neutral error ("попросите администратора привязать его") | `test_selecting_a_foreign_or_missing_client_gives_one_neutral_answer_without_details` |
| New-client creation (booking + inquiry) | e-mail conflict message named the existing flow | Manager: neutral text, no account details; Admin keeps the informative hint; genuinely new clients still created | `test_new_client_creation_still_works_for_manager`, `test_existing_email_conflict_is_neutral_for_manager_and_informative_for_admin` |
| Admin-assisted association | n/a | Admin `client/select` on the Manager's inquiry; Manager then sees only the client profile, not the other Manager's booking/notes/chat | `test_admin_can_link_an_existing_foreign_client_to_a_managers_inquiry_without_exposing_other_records` |
| Revocation | n/a | reassigning the Booking/inquiry removes access unless another current relationship remains | `test_reassignment_revokes_access_unless_another_relationship_remains` |
| B-04 Booking (re)assignment (G7) | `manager_id` + status only, no reason/history | `App\Services\Bookings\BookingAssignment`: Admin only; one transaction with row lock on the booking, re-authorization, locked eligibility (`assignableToBookings()`), stale-form rejection (`expected_manager_id` required, `present`), no-op for same target on non-NEW (no event, no notification), reason **category** required when an existing assignee changes (`workload`, `absence`, `specialization`, `client_request`, `staff_change`, `other`) + optional note 5–200 chars (required for `other`) checked by `AuditMetadata`; audit `booking.reassigned` (change) or `booking.assigned` (first/confirming assignment, no reason) with `previous_manager_id`, `new_manager_id`, `reason_code`, `reason`; actor and `occurred_at` from `AuditRecorder`; Admin-only history on the booking page; status, payment data, client and chat untouched | `BookingReassignmentAuditTest` (15 tests) |
| Audit atomicity | n/a | failing `AuditRecorder` rolls the assignment back | `test_audit_failure_rolls_the_assignment_back` |
| Audit free-text PII | phone numbers not detected | `AuditMetadata` rejects phone-like digit runs (≥10 digits, ≤2 separators; ISO / dd.mm.yyyy dates exempt). Applies to Inquiry **and** Booking reasons; existing e-mail/path/token checks unchanged | `test_audit_metadata_rejects_phone_numbers_but_keeps_dates_and_plain_text`, `test_inquiry_reassignment_reason_also_rejects_phone_numbers` |
| B-10 notifications (G8) | bell count + 60-char preview by addressee | `App\Support\Notifications\MessageNotificationVisibility`: for `NewMessageDatabaseNotification` the bell count and preview are limited, in SQL (`data->booking_id IN (select …)`), to Bookings the user owns **now** (Manager `manager_id`, Tourist `user_id`); Admin unchanged (oversight); no readable `booking_id` → hidden for non-Admin; rows are never deleted, so access returns with the Booking; other notification types untouched | `BookingNotificationPrivacyTest` (10 tests) |
| `GET /messages/unread-count` | all unread for the receiver | restricted to Bookings accessible now (same role precedence) | `test_unread_messages_counter_is_consistent_with_current_access` |
| Chat / booking / write rules | — | **unchanged**; Admin may still write only while assigned | `test_admin_chat_write_rule_is_not_broadened_by_reassignment`, `test_direct_booking_and_chat_authorization_stays_intact_for_former_manager` |

Queued/delayed delivery: `NewMessageDatabaseNotification` is stored synchronously (channel `database`, not queued). The queued e-mail `NewMessageReceived` builds its link with `Booking::chatRouteFor()`, which throws for a user who is no longer the owner/assignee, so a mail still queued for a former Manager cannot render (source-read; not re-tested in I2B). E-mails already delivered cannot be recalled.

### 7.3 Existing tests whose fixtures/expectations changed (deliberately)

| Test | Change and reason |
|---|---|
| `BookingAssigneeEligibilityTest`, `BookingLifecycleAuthorizationTest`, `BookingReassignmentNotificationTest` (24 posts to `bookings.assign-manager`) | payload now carries `expected_manager_id` (= current manager) and `reason_code`; same assertions otherwise |
| `IncomingInquiryWorkflowTest` (`linkedClaimed()`, candidates/search/selection/password-setup tests) | the existing client is linked **by Admin**, candidate/search pages are viewed **as Admin** (a Manager no longer sees unrelated clients); `client_linked_by` asserts the Admin |
| `IncomingInquiryAccessAllocationTest::test_assigned_manager_converts_once_…` | client selected by Admin, conversion still by the Manager |
| `BookingCreationOwnershipAndAtomicityTest` (4 manager→existing-client tests) | fixture `giveWorkRelationship()` gives the Manager a current relationship (assigned inquiry) before choosing the client |
| `CabinetHeaderNotificationBellTest::notify()` | the notified user is now a real participant of the Booking (the old fixture notified a stranger); malformed-payload test split: Admin still sees the fallback text, a Tourist no longer sees an unresolvable notification |
| `ManagerClientListQueryEfficiencyTest` | bookings-touching queries 4 → 6 (pluck of ids replaced by sub-queries; bell adds 2 bounded queries); still constant in client count |
| `ManagerCabinetE3RedesignTest` (pending-count query shape) | queries on `notifications` are excluded from the sidebar-count heuristic |
| `AdminCabinetE3RedesignTest` (inactive assignee shown once) | counts `<option value="id"` (the form now also holds a hidden `expected_manager_id`) |

### 7.4 Verification and limits

- PHPUnit (SQLite `:memory:`, synthetic data): **1599 tests / 12401 assertions, 0 failures, 0 errors** (previous 1561 / 12166). Command needs `-d xdebug.mode=off -d memory_limit=2048M`.
- Browser QA (isolated disposable SQLite DB, headless Chrome CDP, synthetic users; 1200 px 66/66, 360 px 56/56 checks; no horizontal overflow, no first-party console errors): Admin sees all clients; Manager A sees only own clients and cannot find a foreign client by name/phone/e-mail; booking-create dropdown scoped and usable; Admin reassigns with a reason (missing reason and a phone number in the reason are rejected with visible errors; keyboard order covers reason select, note and submit); history shown; former Manager loses booking access, bell preview/count and client visibility; new Manager gains them; Admin links an existing client to Manager A's inquiry (1200 px) and Manager A still gets 403 on the other Manager's booking; Tourist cabinet unaffected.
- **NOT verified:** MySQL row-lock concurrency (two Admins reassigning the same Booking; reassignment vs. Manager action). SQLite serialises writers. A disposable-MySQL check is **required before E6**, together with the I1/I2A checks.
- Residual limits (documented, not fixed): (1) creating a client with an e-mail that already exists unavoidably answers "cannot create" (a one-bit existence oracle for e-mail; no name/owner/id is returned; `users.email` stays unique). (2) The phone check is deliberately narrow (digit runs); names and other free-text PII are not detected. (3) Old notifications without a readable `booking_id` are hidden from non-Admins; Admin still sees them. (4) Booking owner (`user_id`) cannot be changed after creation, so Admin-assisted association exists for `IncomingInquiry` (select) and for Admin-created Bookings (existing client, then assign); there is no CRM merge. (5) A Manager's closed/converted inquiry still grants client visibility while it stays assigned to them (same rule as the inquiry's own visibility). (6) The admin Booking list form for an assigned row carries the reason controls inline; creating the first assignment needs none.


## 8. AOA-01-I3 independent verification (2026-10-21) — MySQL gate and closure risks

**Scope:** verification only. No application code, policy, route, migration, dependency or test was changed. Baseline unchanged: `62421ab0` (1599 tests / 12401 assertions on SQLite `:memory:`, not re-run because nothing changed; 185 focused tests across the AOA-01 suites were re-run green). Probes used synthetic users and records only; no real e-mail, no Tourvisor/VK/operator calls; the canonical `turfirma_rebuild_v4` was **never connected to**. Disposable evidence and the reproducible harness are kept outside the repository (private `AOA-01-I3_Verification` folder).

### 8.1 Isolation and engine

A separate `mysqld` process (MySQL Community Server **9.7.1**, started with `--no-defaults`, `127.0.0.1:3399`, its own empty data directory, loopback only, no named pipe / shared memory) was used. It shares no port, data directory or configuration with the WAMP servers (3306 and 3308). The harness loads only a synthetic env file and **aborts unless the live connection reports port 3399 and that data directory**. Version 9.7.1 is the version WAMP's own config names for the canonical server; the *production* (REG.RU) engine and version are **not known** from the repository, so MariaDB/other-version behaviour is **NOT VERIFIED**.

### 8.2 Migration compatibility — VERIFIED (disposable MySQL 9.7.1)

- The complete existing chain (61 migrations, through `2026_10_20_000000_create_audit_events_table`) applies cleanly; `migrate:rollback --step=1` and re-migrate work.
- `audit_events`: `uuid char(36)` + unique index; `metadata` native `json` (Cyrillic, ints and booleans round-trip, `JSON_EXTRACT` and `metadata->key` queries work, invalid JSON rejected); `occurred_at timestamp NOT NULL` (no timezone drift); indexes on `event_type`, `actor_id`, `(subject_type, subject_id)` (used by `EXPLAIN`), `occurred_at`; no foreign key; duplicate UUID rejected as `UniqueConstraintViolationException`; a transaction rollback removes the event.
- Append-only remains **application-level only**: a raw `UPDATE`/`DELETE` through the application DB user succeeded. Whether the production DB user should lack those privileges (or use triggers) is still an **E6 decision**, unchanged.
- The `notifications.data` column is `TEXT`; the G8 filter (`data->booking_id IN (select …)`) works on MySQL (positive control: the current owner sees the preview and count; the former owner does not).
- Strict `===` ownership comparisons (B-02/B-09) hold on MySQL with the PDO driver in use (owner 200, former owner 403/404).

### 8.3 True concurrency — VERIFIED

Separate PHP processes, separate MySQL connections. Ordering is coordinated by file barriers placed **inside the open transaction** (after the row lock and the conditional UPDATE, before commit) plus a live `performance_schema.data_lock_waits` check that proves the second process is really blocked on an `X,REC_NOT_GAP` row lock; no sleep is used for ordering. 62 scenario checks passed: two Admins assigning one new inquiry to different / the same manager; two self-claims; stale reassignment form; two conflicting reassignments; identical concurrent reassignment; repeated assignment/reassignment (no-op, no second event); Admin close vs assign; former owner `close` blocked on the lock and denied afterwards; Manager assign/reassign always denied; Booking reassignment (conflicting, identical, first assignment of a NEW booking); audit failure (injected exception **and** a real MySQL unique-key error) rolls back inquiry assignment, inquiry reassignment and Booking reassignment with zero business events and no lock left behind. A **negative control** (the same reassignment written without `lockForUpdate`) produced a lost update and a broken audit chain, so the harness can detect the defect it tests for. A stress run (6 processes × 25 random operations, 52 events) produced no deadlock or lock-wait timeout, exactly one assignment event per newly assigned inquiry, and an unbroken previous→new chain equal to the final `assigned_to` / `manager_id` for every inquiry and Booking.

### 8.4 Findings

| ID | Finding | Evidence | Severity / scope |
|---|---|---|---|
| I3-F1 ✅ **FIXED in I4 (§9)** | **Booking lifecycle writes are not re-authorized under a lock.** `confirm` / `cancel` / `complete` / `update` authorize the route-bound Booking and then write without a row lock or ownership re-check. A former Manager whose request passed authorization **before** an Admin reassignment commits can still change the status **after** it (reproduced over HTTP on MySQL with a barrier just before the UPDATE: final `status = confirmed`, `manager_id` = new Manager). A request sent after the reassignment is correctly refused (403). | `toctou.txt` | Low–medium; millisecond window; pre-existing, outside the reassignment service itself. The same pattern is expected for chat `store` (authorize, then create) — **NOT VERIFIED**. Bounded follow-up recommended. |
| I3-F2 ✅ **FIXED in I4 (§9)** | **A queued e-mail for a former Manager fails noisily instead of skipping.** `NewMessageReceived` mail queued before a reassignment throws `LogicException` from `Booking::chatRouteFor()` when the worker renders it, is retried up to `--tries` (3 attempts with the documented supervisor line) and lands in `failed_jobs`. **No e-mail is sent and no message text or booking detail appears in the job payload or exception** (IDs only). If the Booking is reassigned back before the job runs, the same job renders normally. | `notify_flow.txt` | Low; privacy-safe, operationally noisy. |
| I3-F3 ✅ **RESOLVED in AOA-01-I7 (§12, owner option C)** — *was: OPEN, owner decision* | **Client-e-mail existence is observable as one bit.** A Manager creating a *new* client (booking form or inquiry `client/create`) gets a different outcome for an e-mail that already has **any** account (client of another Manager, own client, Admin, other Manager, inactive tourist) than for an unused one (redirect + new account versus a neutral validation error; HTTP 422 for JSON). All six *existing* cases are byte-identical, so the Manager learns nothing about **whose** account, its name, phone, bookings or chat; `client_id` / `client/select` with a foreign id and with a non-existent id are identical; lookups by foreign name/phone/e-mail find nothing; a lead whose own e-mail/phone equals a foreign client gets **no** auto-suggestion; no duplicate account can be created (`users.email` is unique, comparison is case-insensitive). The bit is **not new information for an outsider**: unauthenticated `/register` (`email` validation error) and `/forgot-password` already distinguish existing from unknown e-mails (pre-existing, outside AOA-01). | `matrix_run2.txt`, `matrix2.txt` | The owner-approved rule says a foreign client must not be *discoverable by e-mail*; this residual is the one-bit answer already listed in §7.4 (1). **Needs an explicit owner decision; no exception is assumed.** |

Options for I3-F3 (smallest first; none implemented; the onboarding workflow is not redesigned here): **(a)** accept and record the residual, because the same bit is already public — requires explicit owner approval; **(b)** keep behaviour, add a per-Manager throttle for failed new-client attempts and an audit counter (no e-mail stored; `AuditMetadata` rejects e-mails) — reduces enumeration, does not remove the bit; **(c)** make the Manager outcome uniform ("request accepted") and, when the e-mail already exists, create an Admin-visible pending association request instead of an account or an error — removes the bit, keeps legitimate creation and Admin-assisted association, but is a small new workflow state (separate slice).

### 8.5 Access matrix (real HTTP on MySQL; 75 checks + 11 for the auto-match / association path)

**VERIFIED:** inquiry list/filters/pagination/detail (HTML and JSON) for Manager A, Manager B, Admin, Tourist, Guest; claim / assign / reassign / client select / client create / password-setup / convert / close on foreign and unassigned inquiries (404, no state change, no audit events, no accounts created); claim / assign / reassign on an own inquiry (403); Admin assign → new Manager gains access, former Manager gets 404 immediately; client lookup, booking-create dropdown and clients page scoped; Admin-assisted association (Manager then sees the client profile but gets 403 on the other Manager's Booking and its messages); Booking show / edit / update / confirm / cancel / complete / assign-manager / documents for a foreign Booking (403, unchanged); chat page and messages JSON (read, store, attachment, mark-read, delete) for a foreign Booking; unread counter limited to current access; `audit` and `logs` pages (Admin 200, Manager/Tourist 403, Guest redirect); notification bell, count, direct open and the unread endpoint for a former Manager. **The Admin Booking-chat contract is unchanged:** Admin reads every chat, writes only to a Booking assigned to that Admin, and may delete any message (unaudited, as documented in B-09).

**NOT VERIFIED:** browser rendering (I2A/I2B browser QA stands); MariaDB or any non-9.7.1 production engine; the I-07 `sendPasswordSetup` unlocked row lock; chat `store` TOCTOU (I3-F1 pattern); throughput or deadlock behaviour above 6 concurrent processes; unrelated AO findings (AO-07 403/404 consistency, AO-12 Admin chat overflow) — none claimed fixed.

> **§9 below (AOA-01-I4, 2026-10-22) supersedes the "NOT VERIFIED chat `store`" note in §8.5 and fixes I3-F1/I3-F2.**

### 8.6 Closure assessment

The mandatory MySQL gate (migration compatibility and true concurrency of the I1/I2A/I2B transactions) is **satisfied**. AOA-01 is **not** closed because (1) I3-F3 requires an explicit owner decision under the strict privacy rule and (2) I3-F1 is a reproduced defect against the "responsibility change takes effect immediately" principle. Recommended next slice: **AOA-01-I4 — Booking lifecycle re-authorization under lock (I3-F1, plus the same check for chat `store`) and graceful skip of stale queued mail (I3-F2)**, together with the owner's I3-F3 decision; then owner closure of AOA-01 and AOA-02.

## 9. AOA-01-I4 — Booking write re-authorization and stale mail (2026-10-22)

Fixes **I3-F1** and **I3-F2**. **I3-F3 stays OPEN** (owner decision; nothing implemented). AOA-01 is **not** closed by this slice.

### 9.1 Reproduction before the fix

- **I3-F1 on SQLite** (deterministic, one process: after the request's first read of the Booking an Admin reassigns through the real `BookingAssignment`): on the unfixed code `confirm`, `cancel`, `complete`, `update` (with and without status change) and a Tourist `cancel` racing an assignment all **committed** for the stale actor; a stale transition (`cancelled → confirmed`) was written because the in-memory status was never re-validated.
- **Chat `store` — demonstrated, not assumed:** a former Manager's message was created (HTTP 200, `NewMessageReceived` dispatched), and a Tourist's message addressed to the former Manager was accepted (message + database notification + queued e-mail **to the former Manager**).
- **On MySQL 9.7.1 with true barriers** (pre-fix HEAD `7ada29e4` in a throw-away `git worktree`): 21 checks fail — every operation above commits after the reassignment (status/notes/price changed, 2 mails queued; chat: message, notification, mail), and a request started while the Admin holds the row lock is **not blocked** (no lock) and writes.
- **I3-F2 reproduced with the real queue and worker** (database queue, `queue:work --tries=3`, array mailer; SQLite and MySQL): `LogicException` from `Booking::chatRouteFor()`, **1 row in `failed_jobs`**, no e-mail sent. The same defect hits the sibling mailables `BookingStatusChanged` and `ManagerAssigned` (both call `chatRouteFor()` for a recipient chosen at queue time) — fixed with the same mechanism.

### 9.2 Implementation

| Area | Change |
|---|---|
| `app/Services/Bookings/BookingLifecycle.php` (new) | `transition()` (confirm / cancel / complete) and `update()` each run **one transaction**: `Booking::lockForUpdate()` (same row lock as `BookingAssignment`) → `Gate::forUser($actor)->authorize($ability, $lockedBooking)` → re-check `canTransitionTo()` on the **locked** status → write. A denial throws `AuthorizationException` (HTTP 403, unchanged semantics); an invalid transition returns `false` (controller shows the same validation error as before; previously a stale invalid transition was written or surfaced as HTTP 500). `update()` writes the transition **and** the fields atomically (before, a failing field write left the status changed). |
| `BookingController` | `confirm` / `cancel` / `complete` / `update` keep the early `authorize()` (fast refusal, unchanged order of validation) but the **final** check and write go through `BookingLifecycle`. |
| `Booking` model | `transitionTo()` is split into `persistTransition()` (status + tour-seat restoration in a transaction, **no event**) and `dispatchStatusChanged()`; `transitionTo()` = both (all other callers unchanged). `BookingStatusChanged` — and therefore the e-mails — is now dispatched **only after commit**, outside the lock. New `isChatParticipant(User)` predicate. Status/payment independence and the transition matrix are untouched. |
| `MessageController::store` | Early checks kept; the attachment is written to the private disk **before** the lock; then one transaction: `Booking` `lockForUpdate` → `authorizeBookingWrite()` + receiver check on the **locked** row → `Message::create`. `NewMessageReceived` is dispatched after commit. A refused request deletes the stored file (existing orphan cleanup) and is no longer logged as "Message store failed". Admin read-all / write-only-when-assigned and Tourist behaviour are unchanged. |
| `app/Mail/Concerns/SkipsStaleChatRecipient.php` (new), used by `NewMessageReceived`, `BookingStatusChanged`, `ManagerAssigned` | `send()` is overridden: at **delivery time** it re-reads `user_id` / `manager_id` from the database (`Booking::isChatParticipant`); if the recipient no longer participates (or no longer exists) it returns without sending and logs only `mailable`, `booking_id`, `recipient_id` at info level — no text, e-mail address or client data. The job **completes** (no retry, no `failed_jobs`). Exceptions from real delivery are **not** caught. `chatRouteFor()` still throws for a non-participant (fail-closed, covered by `EmailChatLinkTest`). A Booking reassigned away **and back** before the job runs is delivered normally; the recipient is never changed to someone else; existing database notifications are not deleted. |

Lock order is always `bookings` row → (`tours` row | `messages` insert | `audit_events` insert); no operation takes them in the opposite order. No external call is made under a lock (mail is only queued after commit).

### 9.3 Verification

- **PHPUnit:** `BookingWriteAuthorizationRaceTest` (20) + `StaleBookingMailDeliveryTest` (12) are new; full suite **1631 tests / 12524 assertions, 0 failures, 0 errors** (baseline 1599 / 12401).
- **True concurrency, disposable MySQL 9.7.1** (port 3398, own datadir, `--no-defaults`, synthetic credentials; canonical WAMP MySQL and `turfirma_rebuild_v4` never touched; instance stopped afterwards). Separate PHP processes and connections; ordering by file barriers (stale request paused after its first read, before any lock) plus `performance_schema.data_lock_waits` proving real lock waits — no sleeps for ordering. **47 checks pass on the fixed code:** A1–A7 (confirm, cancel, complete, update ×2, chat `store` by a former Manager, chat `store` by a Tourist to a former Manager) → HTTP 403/422, **no** status/field change, no message, no notification, no queued mail, exactly one assignment event; B1 a former Manager's request really blocks on an `X,REC_NOT_GAP` row lock held by the reassignment and is denied (403) once it commits; B2 an authorized Manager holding the lock mid-transaction makes the Admin reassignment wait, then both complete serially and the status-change mails are queued **only after** the commit; B3 the new Manager can act, the old one cannot; C stress (6 processes × 25 random operations, 4 Bookings): **no deadlock, no lock-wait timeout**, assignment audit chain unbroken and equal to the final manager. The identical harness on the pre-fix tree fails 21 checks (negative control).
- **Queued mail on MySQL** (real database queue + worker, array mailer): authorized recipient delivered; stale job → no mail, **0 failed jobs**, queue empty, no message text / address in logs, notification record kept; reassigned away and back → delivered; sibling mailables skipped; an injected transport failure is still recorded in `failed_jobs`. Pre-fix tree: failed job with `LogicException` (2 checks fail).
- **Browser (headless Chrome over CDP, disposable app + MySQL, synthetic users), 360 px and 1200 px, 31 checks each, all pass:** Manager confirms / completes / edits and chats on an assigned Booking; Admin reassigns through the UI; the former Manager gets 403 on the page, on confirm/cancel/chat POST and 404 on the chat page; a stale page whose "Завершить" button is clicked after reassignment is refused and the status is unchanged; the new Manager can act and chat; Tourist chat works and cannot address the former Manager (422); no horizontal overflow on any page; the only console entries are the deliberately provoked 403/404 responses; no JS exceptions. (The Claude-in-Chrome extension was not available in the session; a separate headless Chrome profile was used, as in earlier slices.) Evidence and harness are kept outside the repository.

### 9.4 Boundaries

- An e-mail that was **already delivered** to a Manager before the reassignment cannot be recalled; only still-pending jobs are skipped.
- `SkipsStaleChatRecipient` decides by participation only: e-mail preferences and technical-address checks stay in the listeners (not re-evaluated at delivery time).
- Only MySQL 9.7.1 was exercised; MariaDB or another production engine was not.
- Browser QA does not replace the multi-process tests; the SQLite tests alone do not establish the concurrency fix.

### 9.5 Residuals recorded, not changed (outside this slice)

1. **Same pattern, not fixed:** `BookingController::storeDocument`, `destroyDocument`, `downloadDocument` and `sendClientPasswordSetup` authorize the route-bound Booking and then write/read without a lock (a former Manager's in-flight upload/delete/send can land after a reassignment). Not part of I3-F1 (lifecycle + chat); candidate for AOA-02 or a small follow-up.
2. `Tour::increaseAvailableSeats()` is check-then-increment (pre-existing, not an authorization issue).
3. A queued mail whose `Message` was deleted before delivery fails with `ModelNotFoundException` (not exercised here; unchanged).
4. I3-F3 (client e-mail existence bit) — **OPEN, owner decision.**

> **§10 (AOA-01-I5, 2026-10-22) supersedes items 1 and 3 above:** the document/password-setup writes were investigated, reproduced and fixed, and the deleted-message mail failure was reproduced and fixed. Item 2 (tour seats) is now tracked in the roadmap follow-up table; item 4 is still open.

## 10. AOA-01-I5 — Booking documents, client password setup, deleted-message mail (2026-10-22)

Closes the residuals recorded in §9.5 (items 1 and 3). **I3-F3 stays OPEN** (owner decision; nothing implemented). AOA-01 is **not** closed by this slice.

### 10.1 Investigation (read-only first)

| Operation | Authorized actor / check before I5 | DB / side effects | Lock before I5 | Verdict |
|---|---|---|---|---|
| `POST bookings/{booking}/documents` (`BookingController::storeDocument`) | Admin, or Manager whose id equals the **route-bound** `Booking::manager_id` (`authorizeBooking`) | file written to private disk `local` (`documents/bookings/{id}/…`), then one `booking_documents` INSERT | none | **DEFECT** — a former Manager's in-flight upload committed a document to the Booking after the reassignment |
| `DELETE bookings/{booking}/documents/{document}` (`destroyDocument`) | same | soft-delete of the row, then physical file delete | none | **DEFECT** — a former Manager's in-flight delete removed the row and the file |
| `GET bookings/{booking}/documents/{document}/download` (`downloadDocument`) | same | read-only stream from disk | none (read) | **Stale read window** (route binding → controller); a stale request was streamed. Narrowed (see 10.3); an already-started transfer cannot be recalled |
| Tourist download `GET cabinet/documents/bookings/{booking}/{document}/download` | owner of the Booking (`booking.user_id`) | read-only | n/a | **Non-defect** — depends on the owner, never on the responsible employee; a reassignment does not change it; a foreign Tourist gets 403 |
| Document metadata change | — | — | — | **Not exposed**: there is no update route (store / download / destroy only) |
| `POST bookings/{booking}/client/password-setup` (`sendClientPasswordSetup`) | Admin, or Manager responsible (`update` policy on the **route-bound** Booking) | Laravel `Password::sendResetLink` — creates a reset token (`password_resets`) and **sends the e-mail synchronously (SMTP)** | none | **DEFECT** — a former Manager's in-flight request created a token and sent the link after the reassignment |
| `POST cabinet/manager/inquiries/{inquiry}/client/password-setup` (`IncomingInquiryWorkflow::sendPasswordSetup`) | inquiry assignee / Admin (`process` policy) on a **freshly read** row | same e-mail | `lockForUpdate()` was called **outside a transaction**, i.e. a no-op after the read (autocommit releases it) | **Latent / hardening**: authorization already used a fresh read, so the multi-process test (C3) refused the stale request before the fix too; the missing transaction is fixed for consistency |
| Queued `NewMessageReceived` mail whose `Message` was deleted (`MessageController::destroy`: sender or Admin) | — | the job's `SerializesModels` payload cannot restore the model | — | **DEFECT (noise, not disclosure)** — `ModelNotFoundException` on unserialize → one `failed_jobs` row; no mail, no message text leaked (id only) |

Framework detail established during the investigation: `Mail::queue()` wraps the mailable in `SendQueuedMailable`; on a missing model the worker consults `deleteWhenMissingModels` / `#[DeleteWhenMissingModels]` of the **wrapper** (`payload.data.commandName`), not of the mailable, so the framework offers no per-mailable switch.

### 10.2 Reproduction before the fix

- **SQLite, single process** (the I4 technique: after the request's first read of the Booking an Admin reassigns through the real `BookingAssignment`): on the unfixed code a stale Manager's **upload committed** (HTTP 302, row + file), **delete committed**, **download streamed (HTTP 200)** and **password setup sent the e-mail** (4 tests fail; the positive controls pass). Deleted-message mail: 3 tests fail with one `failed_jobs` row each.
- **MySQL 9.7.1, separate processes, file barriers, pre-fix HEAD `1ab7c3a4`:** 16 of the checks fail — A1–A4 (stale upload / delete / download / password setup all succeed), B1 (a stale request is **not** blocked by the reassignment and writes; for the upload the only wait was the foreign-key shared lock `S,REC_NOT_GAP`, after which the document was written onto the already-reassigned Booking), B2 (a Manager's upload transaction does not block the Admin reassignment — no booking lock is taken). Deleted-message mail on the real database queue: 1 `failed_jobs` row, `ModelNotFoundException: No query results for model [App\Models\Message]`, nothing sent.

### 10.3 Implementation

| Area | Change |
|---|---|
| `BookingController::storeDocument` | Early `authorizeBooking` and validation unchanged. The file is written to the private disk **before** the lock (a long operation is never done under a lock), then one short transaction: `Booking::lockForUpdate()` → `authorizeBooking()` on the **locked** row → `BookingDocument::create`. **A database rollback does not undo a file-system write**, so on any refusal or failure the staged file is deleted explicitly (existing cleanup pattern, extended to the 403/404 path); a refusal is a plain 403/404, not the "could not save" message. File types, size limit and publication rules are unchanged. |
| `BookingController::destroyDocument` | One transaction: Booking `lockForUpdate` → `authorizeBooking` on the locked row → re-read the document **under the lock** (a parallel delete yields 404) → soft-delete. The physical file is removed **after commit**, outside the lock; if that removal fails the file is left without a row (never the reverse), as before. |
| `BookingController::downloadDocument` | No lock (read-only). The responsible Manager is **re-read from the database immediately before the file is streamed**, so a request that reached the controller on a stale route-bound model is refused. |
| `BookingController::sendClientPasswordSetup` | Early `authorize('update')` kept. One short transaction: `lockForUpdate` → `authorize('update')` on the locked row → eligibility check (`canSendPasswordSetup`, active Tourist). The **e-mail is sent after commit, outside the lock** (SMTP is an external call). The decision is therefore linearised at the lock: a request serialised before a reassignment may still deliver its (already decided) e-mail a moment later — the same "events after commit" model as I4; a request serialised after it is refused with 403, and no token is created. The existing 60-second broker throttle, error messages and activation/reset contracts are unchanged; no token or link is logged. |
| `IncomingInquiryWorkflow::sendPasswordSetup` | The `lock → authorize → state → client → eligibility` block now runs in `DB::transaction`; the e-mail is sent after it. Behaviour and messages unchanged. |
| `App\Mail\NewMessageReceived` | Carries only `messageId` (int) instead of the `Message` model (`SerializesModels` removed; the private read cache is excluded from serialization by `__sleep`). The message, its booking, sender and receiver are read **at delivery time**; a deleted message makes `deliveryRecipient()` / `deliveryBooking()` return `null`. |
| `SkipsStaleChatRecipient` | `deliveryBooking()` may be `null`; a missing recipient **or** booking is the same expected "nothing to send" outcome as I4: no mail, no retry, no `failed_jobs`, log line with ids only. Only the missing-record condition is handled — a transport/database failure during delivery still throws and is recorded. The removed text cannot be reconstructed because the mailable never holds it. |

Lock order is unchanged: `bookings` row → (`booking_documents` insert/update | `tours` | `messages` | `audit_events`). No file transfer, SMTP or other external call is made while a lock is held.

### 10.4 Verification

- **PHPUnit (SQLite `:memory:`, PHP 8.4.13, Xdebug off, 2048M):** new `BookingDocumentAndClientAccessRaceTest` (15) — positive controls (Manager, Admin), former/unrelated Manager and Tourist refusals, stale upload / delete / download / password setup refused with no row, no file, no token, no notification, Admin unaffected, Tourist owner vs stranger, DB failure leaves no orphan file, file removed only after the row, inquiry flow decides inside a transaction and mails at transaction level 0 — and 4 new tests in `StaleBookingMailDeliveryTest` (deleted message skipped on the real queue/worker with 0 `failed_jobs`, Admin deletion, only the deleted message skipped, genuine transport failure still raises). Full suite **1650 tests / 12613 assertions, 0 failures, 0 errors** (baseline 1631 / 12524). The unfixed code fails the race tests, the inquiry transaction test and the deleted-message tests.
- **Changed existing test:** `NewMessageEmailPreferenceTest` asserted `$mail->message->id`; the mailable now exposes `messageId` (one assertion line).
- **True concurrency, disposable MySQL 9.7.1** (port 3397, own datadir `C:\Avilona_private\AOA-01-I5_Verification\mysql_data`, `--no-defaults`, synthetic credentials, guarded bootstrap that aborts unless the live connection is that instance; canonical WAMP MySQL 3306/3308 and `turfirma_rebuild_v4` never touched; instance stopped afterwards). Fixed code: **all checks pass** — A1–A5 (stale upload / delete / download / password setup → HTTP 403 with no row, no file, no token, no mail; Admin upload during a reassignment succeeds), B1a–c (the Manager's request **really blocks** on `X,REC_NOT_GAP` on `bookings` held by the reassignment — `performance_schema.data_lock_waits` — and is denied 403 when it commits), B2 (a Manager's upload transaction holds the booking lock; the Admin reassignment waits, both complete serially; exactly one row and one file), B3 (new Manager 302/200, former and unrelated Managers 403), C (mail at transaction level 0 for the booking and inquiry flows; stale inquiry request refused), stress **6 processes × 25 random operations × 4 Bookings, run three times: no deadlock, no lock-wait timeout, every live row has its file, no orphan file**. Deleted-message mail on the real database queue + worker: delivered when the message exists, skipped with **0 `failed_jobs`** when it was deleted (sender or Admin), only the deleted message's mail skipped, a genuine transport failure still lands in `failed_jobs`; the **same harness on the pre-fix tree fails** the corresponding checks. The only `ERR` entries in the stress runs are the existing optimistic `BookingAssignmentException` (stale expected manager between two Admin reassignments) — an intended refusal, not a lock failure. Evidence and harness are outside the repository.
- **Browser (headless Chrome over CDP, disposable app + MySQL, synthetic data and synthetic one-line PDFs, mail transport `array` — no outgoing e-mail), 360 px and 1200 px, 37 checks each, all pass:** Manager uploads through the real form, sees the document, downloads it (200, `attachment`, correct name, real bytes) and sends the password-setup link (success message, exactly one token; a second click within the throttle shows the existing safe error and creates no second token); Admin sees the controls, downloads and reassigns through the UI; the former Manager gets the 403 page without the document title or client address, direct download / delete / upload / password-setup URLs return 403, nothing changes; a stale page (buttons rendered before an Admin reassignment) has its delete click refused (403 page) and its upload / password-setup refused; the new Manager downloads, uploads and deletes; an invalid file type gives a validation message with no stack trace or server path; a Tourist downloads the document of their own Booking, the staff routes return 403, another Tourist gets 403; no horizontal overflow on any page; no first-party console errors other than the provoked 403s. (The Claude-in-Chrome extension was not available in the session; a separate headless Chrome profile was used, as in I4.)

### 10.5 Classification

**Verified fixed defects:** I5-D1 document upload, I5-D2 document delete, I5-D3 stale download window (narrowed), I5-D4 booking `sendClientPasswordSetup`, I5-D5 queued message mail for a deleted message (`ModelNotFoundException` → `failed_jobs`).

**Reviewed, non-exploitable / hardening only:** inquiry `sendPasswordSetup` (fresh read already; transaction added for consistency); Tourist booking-document download (owner-based, independent of reassignment); document metadata (no endpoint); throttle behaviour of the reset link (unchanged).

**Accepted technical limits:** (a) a download or an e-mail that has **already started** before a reassignment cannot be recalled — this is a property of streaming and of SMTP delivery, not a database race; (b) a password-setup request linearised before a reassignment may deliver its e-mail after the commit (same model as I4); (c) removal of the physical file after a committed soft-delete can fail and leave a file without a row (never a row without a file); (d) two truly simultaneous password-setup requests can both pass the broker's non-atomic 60-second throttle check — the second token replaces the first (pre-existing, not an authorization issue, not changed); (e) only MySQL 9.7.1 was exercised.

**New observation, NOT fixed in I5 — FIXED later in AOA-01-I6 (§11):** **I5-F1** — deleting a chat message does not delete the recipient's database notification, whose payload keeps a ≤120-character preview of the removed text (verified with a throw-away test: `messages = 0`, `notifications = 1`, `preview` = the text). It is shown only to the recipient who already received that message and stays access-controlled by *current* Booking ownership (G8), so it is a data-retention inconsistency, not a cross-user exposure. The fix (remove or blank the notification when a message is deleted) needs a JSON-payload lookup that works on both SQLite and MySQL and a decision on semantics, so it is recorded rather than expanded into I5.

**Unresolved owner decisions:** **I3-F3** (client e-mail existence bit) — OPEN; owner acceptance of AOA-01 closure.

### 10.6 Remaining AOA-01 closure blockers

1. Owner decision on I3-F3 (accept / throttle + audit / uniform "request accepted").
2. Owner acceptance of the recorded residuals: I5-F1 (notification preview after message deletion), the `Tour::increaseAvailableSeats()` check-then-increment (roadmap follow-up table), and the accepted technical limits above.
3. Owner closure of AOA-01 (this slice does not close it).

## 11. AOA-01-I6 — Deleted chat message and its database notification (2026-10-22)

Resolves the I5-F1 finding of §10.5. **I3-F3 stays OPEN** (owner decision; nothing implemented). AOA-01 is **not** closed by this slice.

### 11.1 Investigation

| Question | Finding |
|---|---|
| Who may delete a chat message? | `DELETE messages/{message}` (`MessageController::destroy`): the **sender** or an **Admin** (403 otherwise). The chat UI has no delete control; the route is the only path. Unchanged. |
| Soft or hard delete? | **Hard delete** (`Message` has no `SoftDeletes`; no other code path deletes messages). A deleted message disappears for **everyone**, including the receiver and Admin; `messages.index` JSON and `/messages/unread-count` (which count `messages` rows) already omit it. |
| Which notification payloads carry message text? | Only `NewMessageDatabaseNotification` (`type`, `message_id`, `booking_id`, `sender_id`, `sender_name`, **`preview` ≤120 chars**, `has_attachment`). The format never changed since it was introduced (one historical shape). Mail is a separate channel (fixed in I5). |
| Is the text a persistent snapshot? | Yes — a copy in `notifications.data`, independent of `messages`. |
| Did a viewer re-check the message? | No. The header bell (count + latest unread preview, `MessageNotificationVisibility`) and `NotificationController::open` checked only the Booking ownership (G8), never whether the message still exists. |
| Surfaces | (1) header bell — unread count and latest preview; (2) `POST cabinet/notifications/{id}/open`. There is **no** notification list page and **no** notification JSON endpoint. `/messages/unread-count` counts `messages` rows, not notifications. |
| Could a recipient / Admin still read deleted content? | **Yes (before the fix)** — the recipient (including an Admin recipient, who is not Booking-scoped) saw the deleted text in the bell until the notification was read. Reassignment already hides it from a former Manager (G8) but not from the current responsible person. |

### 11.2 Reproduction before the fix

New `DeletedMessageNotificationPrivacyTest` (16 tests, synthetic data) on the unfixed HEAD `365ac4ae`: **11 of 16 fail** on SQLite and the same **11** on disposable MySQL 9.7.1 — after the sender deletes the message the recipient's bell still shows the secret marker text and the badge, `open` redirects to the chat and marks the notification read, and the text stays in `notifications.data`. The positive controls (live notification, non-sender gets 403, live legacy snapshot, other notification types, JSON endpoints) pass before and after.

### 11.3 Implementation

| Area | Change |
|---|---|
| `MessageNotificationVisibility::restrict()` | Adds one condition for **all** roles (Admin included): `data->message_id IN (SELECT id FROM messages)`, in the same form as the existing `data->booking_id` filter. The bell count/preview and the open route share it, so deleted ⇒ not shown, not counted, not openable. It is evaluated at read time, therefore it covers **historical snapshots created before the fix**, any other deletion path and a notification created after its message was deleted. A notification without a readable `message_id` cannot be verified and is hidden (fail closed, also for Admin). |
| `NotificationController::open` | Resolves the notification through `restrict()` (same rule as the bell). A deleted-message notification is a **404**, is **not** marked read, and reveals nothing; the existing type/payload/booking/role checks stay as defence in depth. |
| `MessageController::destroy` | `DB::transaction`: delete the message **and** `MessageNotificationVisibility::redactDeleted()` — the stored `preview` becomes `Сообщение удалено`, `has_attachment=false`, `deleted=true`. Rows are **kept** (no indiscriminate deletion; business audit untouched). Only `NewMessageDatabaseNotification` rows whose `message_id` matches are touched. If the redaction fails, the deletion **rolls back** (all or nothing; the user may retry). The physical attachment file is still removed **after** commit and cannot be rolled back (unchanged order). |
| Not changed | Deletion authorization, G8 current-ownership rules, Admin Booking-chat read/write rights, notification dispatch, mail, chat UI. No generic "message deleted" entry is shown: the notification simply disappears, consistent with the unread-messages counter. |

### 11.4 Verification

- **PHPUnit (SQLite `:memory:`, PHP 8.4.13, Xdebug off):** full suite **1666 tests / 12787 assertions, 0 failures, 0 errors** (baseline 1650 / 12613; +16). New tests cover: live notification visible/counted/openable; delete before read and after read; Tourist, Manager and Admin recipients; Admin deleting another user's message; refused deletion (403) leaves the preview intact; redaction of the stored data (row kept, secret absent from the raw table) and an attachment-only message; rollback when the redaction fails; legacy snapshots (missing message, `null`/`abc`/`0`/`-3`/array/float/absent `message_id`) hidden for every role and `open` ⇒ 404 without marking read; a legacy snapshot of a live message (string ids) still visible; other notification types untouched; two notifications with one deleted (counts 2→1, `unread-count` agrees); `messages.index` / `unread-count` JSON free of deleted text; reassignment A→B→A combined with deletion. Existing tests adjusted: the constant `from messages` query budget of pages with the bell 2→4 (the two bell queries now embed the existence subquery; the one-vs-six-bookings equality that detects N+1 is unchanged) and two raw-payload tests (`CabinetHeaderNotificationBellTest`, `CabinetNotificationOpenTest`) now carry a real `message_id`; a scalar payload is now hidden from Admin too.
- **MySQL 9.7.1 (JSON filter + transaction):** disposable instance on `127.0.0.1:3411` (own datadir `C:\Avilona_private\AOA-01-I6_Verification\mysql_data`, `--no-defaults`, guarded bootstrap that aborts unless the live connection is that instance; WAMP 3306/3308 and `turfirma_rebuild_v4` never touched; stopped afterwards). The 16 new tests **pass (172 assertions)**; the unfixed `app/` tree fails **11/16**; 144 related tests ran with 1 failure — `ManagerCabinetE3RedesignTest::test_dashboard_reuses_precomputed_counts…`, which matches SQLite-quoted SQL (`from "bookings"`) and **fails identically on the unfixed tree** (pre-existing, driver-specific, unrelated). The Booking-lock/concurrency contracts of I3–I5 are untouched, so that suite was not rerun.
- **Browser (headless Chrome over CDP, disposable app + MySQL database `aoa01_i6_qa`, synthetic data, mail `array`), 360 px and 1200 px, 42 checks each, all pass:** live notification shown with badge; sender deletes (200) → the Manager's, Admin's and Tourist's bell lose the text and the badge, the historical snapshot is never rendered, `unread-count` agrees, the stored snapshot is redacted with the row kept; direct open of a deleted-message notification ⇒ 404 and stays unread; a non-sender delete ⇒ 403; keyboard (focus bell → Enter opens → ArrowDown → Enter opens the chat); the former Manager after reassignment sees nothing; the new Manager sees no deleted/historical content; no horizontal overflow; no first-party console errors (the deliberate 403/404 probe responses are excluded by exact URL).

### 11.5 Remaining limitations and residuals

1. A notification created **after** its message was deleted (the `NewMessageReceived` listener running after a concurrent delete) keeps its text snapshot in `notifications.data` (it is never shown — the read-time rule hides it); only the redaction-at-delete step cannot see it. A periodic cleanup of such rows is not part of this slice.
2. The redaction is a scan by `data->message_id` over `NewMessageDatabaseNotification` rows (no index on the JSON path) — acceptable for a rare action; revisit if the notifications table becomes large.
3. A notification **already rendered** in an open browser page before the deletion stays on that page until reload; a click then yields 404. The bell has no live refresh.
4. A message's attachment file removal after commit remains non-transactional (unchanged).
5. Pre-existing and unrelated: the bell shows the "latest" notification by `created_at`; two notifications created within the same second tie.
6. `ManagerCabinetE3RedesignTest::test_dashboard_reuses_precomputed_counts…` is SQLite-specific (quoted identifiers) and does not pass on MySQL, independent of this change.

### 11.6 Remaining AOA-01 closure blockers

1. Owner decision on I3-F3 (accept / throttle + audit / uniform "request accepted") — **OPEN**.
2. Owner acceptance of the recorded residuals: the `Tour::increaseAvailableSeats()` check-then-increment (roadmap follow-up table) and the accepted technical limits (§10.5, §11.5).
3. Owner closure of AOA-01 (this slice does not close it).

## 12. AOA-01-I7 — Privacy-safe client verification and Admin resolution (2026-10-23)

Resolves **I3-F3** with the owner-approved **option C**. This is the last planned functional slice of AOA-01. AOA-01 is **not** marked CLOSED here.

### 12.1 Owner decision implemented (OWNER_APPROVED)

A Manager must not be able to determine, through client creation, whether an e-mail already belongs to an account. The Manager submits a **client-verification request**; the Admin either **links an existing Tourist** to the Manager's legitimate work or **approves creation of a new Tourist**. Both paths use the same Manager-facing workflow and wording. A Manager still gains no access to another employee's inquiries, Bookings, chats or private client history, and the Admin keeps agency-wide client oversight. No exception is assumed.

### 12.2 Investigation and exact reproduction (unfixed HEAD `1366b334`)

Affected active Manager-facing endpoints (everything else — client list, `client/select`, search, candidates, `client_id` — was already scoped by `ClientVisibility` and unchanged):

| Route | Before | After (Manager) |
|---|---|---|
| `POST /bookings` with `is_new_client=1` (`BookingController::store`) | unused e-mail → account **and** Booking created, redirect to the Booking; existing e-mail → validation error on `client_email` (HTTP 422 for JSON) | request stored, redirect back to the Booking form with the booking input kept, neutral message, **no Booking, no account** |
| `POST /cabinet/manager/inquiries/{id}/client/create` (`IncomingInquiryController::createClient`) | unused e-mail → account created and linked, success; existing → error on `client_email` | request stored for that inquiry, neutral message, inquiry unchanged |

Reproduction: the new tests `test_manager_cannot_tell_existing_from_new_email_through_the_booking_form` / `…_through_the_inquiry_form` fail on the unfixed code (a Booking/account is created for the new e-mail, so the observable outcome differs) and pass on the fix. They compare, for seven e-mail cases, the status, redirect target, flash text, validation messages/keys and the absence of any side effect.

Existing contract reused: `StaffClientAccounts::createTourist` (random 64-character hashed password, `email_verified_at` null, `temp_password` null, technical e-mail when none is given, Tourist role); case-insensitive `emailExists`; `users.email` UNIQUE; Admin `client/select` for association; `AuditRecorder` / `AuditMetadata`. Nothing existing could hold a Manager→Admin request durably, and `IncomingInquiry` was deliberately not reused for an unrelated purpose, so **one small additive table** was added; no CRM redesign was needed.

### 12.3 Implementation

* **Schema** (`2026_10_22_000000_create_client_verification_requests_table`): `requested_by`, `context_type` (`booking_form` | `incoming_inquiry`), nullable `incoming_inquiry_id`, `client_name`/`client_email` (lower-cased)/`client_phone`, `status` (`pending|approved|rejected`), `pending_key` (CHAR(64), **UNIQUE**, non-null only while pending), `resolved_by`, `resolved_at`, `resolution` (`linked_existing|created_new|rejected`, Admin-only), `resolved_user_id`, `resolution_note` (≤300), `access_expires_at`, timestamps. No password, token, passport or Booking data.
* **`ClientVerificationService`** — `submit()` (Manager only; inquiry context re-authorised under the inquiry lock via `IncomingInquiryPolicy::process`; idempotent by `pending_key`; pending cap 25; 10 submissions/minute/Manager; **never queries `users` by e-mail**), `linkExisting()`, `approveNew()`, `reject()`, `candidatesFor()` (Admin only). Every resolution runs in one `DB::transaction`: `lockForUpdate` on the request, policy `resolve` (Admin only), re-check of the context (requester still an active non-Admin Manager; inquiry still open, actionable and assigned to the requester), conditional `UPDATE … WHERE status='pending'` as the second barrier, then the audit write. `approveNew` refuses when the e-mail belongs to **any** account (any role, inactive, any case) and catches the UNIQUE violation as the last line. A failed audit write rolls the whole resolution back. No mail is sent anywhere in the flow; passwords are neither generated for display nor sent.
* **Routes/UI:** Manager `GET|POST /cabinet/manager/client-requests` (own requests only; Admin → 403); Admin `GET /cabinet/admin/client-requests`, `…/{id}`, `POST …/link|create|reject` (`role:admin` + `ClientVerificationRequestPolicy`). Sidebar entries (Admin pending counter). The booking form (Manager) and the inquiry page now read "request verification"; Admin keeps the direct creation forms and explicit "already exists" messages. `IncomingInquiryWorkflow::createClient` additionally refuses any non-Admin actor **before** any e-mail check.
* **Manager access after approval:** `ClientVisibility` gains one source — the resolved client of an *approved request of this Manager* with `access_expires_at > now()` (30 days). It shows only the client's profile fields in the client list/dropdown; Booking/chat/inquiry visibility still comes from their own policies. For an inquiry context the client is also linked to the inquiry (only if it has none yet).
* **Audit** (`subject_type=client_verification`): `client_verification.requested` (`context`, `inquiry_id`, `has_email`), `.linked` / `.created` (`manager_id`, `client_id`, `inquiry_id`, `linked_to_inquiry`), `.rejected` (`manager_id`, `inquiry_id`, `reason`). No e-mail, phone or name is ever written to audit metadata (asserted).

### 12.4 Neutral-response guarantees and their limits

For a new e-mail, another Manager's client, the Manager's own client, an Admin e-mail, a Manager e-mail, an inactive account and a differently cased e-mail, the Manager's HTML response, redirect, flash text, validation keys, JSON status and stored state are identical (tests + Chrome QA); the approved state and wording are identical for *link* and *create*. **Limits (recorded honestly):** (a) after approval the Manager sees the real account's name/phone in "My clients", which can differ from what was typed; (b) request timing is independent of existence because the submit path never reads `users` by e-mail (asserted via a query listener), but network-level timing was not measured; (c) the pre-existing public `/register` and `/forgot-password` enumeration is **unchanged and out of scope** — recorded as a separate public issue; (d) a Manager with a legitimate prior link to a client is unaffected.

### 12.5 Verification

* **PHPUnit (SQLite `:memory:`):** **1706 tests / 13255 assertions, 0 failures, 0 errors** (baseline 1666 / 12787). New `ClientVerificationWorkflowTest` (39): neutral responses (booking form, inquiry form, dedicated endpoint, HTML + JSON), no account/booking side effects, no `users` query by e-mail, duplicate/case-insensitive dedupe, pending cap, rate limit, Tourist/guest/Manager/Admin denials, Admin link / create / reject, refusals for staff/inactive/other-role e-mails, repeated and stale-concurrent resolution, UNIQUE race, failed-audit rollback of all three resolutions, reassigned/closed inquiry, deactivated or demoted requester, time-limited access, foreign Booking/chat/inquiry still invisible, safe stored data and audit metadata, preserved ordinary workflows.
* **Changed legacy expectations (all deliberate):** tests that had a *Manager* create a client with a Booking or from an inquiry now use the **Admin** (the role that still creates accounts directly): `BookingCreationOwnershipAndAtomicityTest` (6 tests + the 2 mail tests; the Manager-specific "gets manager and progress status" test became "manager only submits a request" plus a new Admin test: no Manager, status `new`), `StaffClientCredentialHardeningTest` (5; the reset-link test assigns the Admin-created Booking to the Manager first), `IncomingInquiryWorkflowTest` (6; `client_linked_by` is now the Admin), `ClientVisibilityPrivacyTest` (2 rewritten: Admin creates directly and sees the explicit conflict; the Manager gets the same neutral success for existing and new e-mails). `StaleBookingMailDeliveryTest::runWorker` passes `--memory=0` (see roadmap §A2).
* **Disposable MySQL 9.7.1** (own data directory, port 3412, synthetic data; the WAMP 3306/3308 instances and `turfirma_rebuild_v4` were never contacted; guarded bootstrap): migration chain incl. the new table; the 240 focused/related tests pass (the single failure, `AuditEventFoundationTest::test_records_a_valid_event`, is MySQL JSON key ordering and fails identically on the pristine HEAD); **true multi-process barriers** (real OS processes): S1 six parallel approvals of one request → exactly one account, one audit event; S2 two Managers' requests for the same e-mail (different case) approved concurrently → exactly one account, loser stays pending, nothing half-written; S3 six parallel identical submissions → one row, one audit event; S4 link vs reject vs create on one request → exactly one resolution. All checks pass. SQLite alone does not prove concurrency safety; the MySQL results above are what does.
* **Chrome QA** (disposable app on the MySQL QA database, synthetic users, 360 px and 1200 px): 77/77 checks each — identical neutral message for a new and an existing e-mail, pending state, duplicate submission, booking-form input kept, inquiry flow, keyboard path (visible focus), Admin list/detail/link/create/reject with a stale repeat refused, Manager sees only legitimate clients with 0 foreign Bookings, foreign Booking/chat/inquiry still 403/404/invisible, no horizontal overflow, labelled controls, no first-party console errors (deliberate 403/404 probes excluded).

### 12.6 Remaining limitations (recorded, not changed)

1. Name/phone of an existing account become visible to the Manager after an Admin link (§12.4 a).
2. Public `/register` and `/forgot-password` enumeration — pre-existing, separate.
3. Request columns hold the submitted name/e-mail/phone in plain text (needed for the Admin decision); no retention job.
4. No e-mail/in-app notification for new requests or decisions (Admin counter, Manager status page only).
5. The Manager access window is a fixed 30 days; not extended by Admin and not revocable except by rejecting before approval (no revoke action).
6. Request timing was reasoned from the code path, not measured.
7. `AuditEventFoundationTest::test_records_a_valid_event` fails on MySQL (JSON key order) — pre-existing.
8. Carried over unchanged: future production-engine compatibility (only MySQL 9.7.1 tested); application-level (not database-level) audit append-only guarantee; already-delivered mail/download limits; exceptional attachment-cleanup limits; password-setup broker throttle concurrency; `Tour::increaseAvailableSeats()` concurrency; notification storage residuals (§11.5).

### 12.7 AOA-01 closure assessment

All planned functional slices (I1–I7) are implemented and verified, the MySQL gate is satisfied, and I3-F1, I3-F2, I3-F3 and I5-F1 are fixed or resolved. **Recommendation:** a single docs-only closure checkpoint that records the owner's acceptance of the residuals in §12.6 item 8 and §11.5, then AOA-02. *Superseded:* the owner closed AOA-01 on 2026-10-23 with tracked residuals; the canonical status and the residual register are in `docs/roadmap.md` (§A7.1). The residual list in §12.6 item 8 and §11.5 stays valid and OPEN.


## 13. AOA-02 — Staff visibility and assignment policy consolidation (2026-10-23)

Evidence only; status is canonical in `docs/roadmap.md` (§A1, §A6, §A9). Baseline HEAD `9a66781d`, PHP 8.5.12 / 8.4.13, SQLite `:memory:`; synthetic data only; canonical MySQL never touched.

### 13.1 Canonical scope and what AOA-01 had already delivered

Roadmap definition: *visibility & assignment policy layer implementing §A3 across all surfaces (policy/surface matrix, eligible-employee rule)*; the provider-independent `Inquiry` entity is **AOA-03** and was **not** started. Requirement-by-requirement status after the audit:

| §A3/§A4 requirement | Delivered by | AOA-02 action |
|---|---|---|
| Manager sees only currently assigned inquiries (list, detail, actions); unassigned intake hidden; Manager cannot claim | AOA-01-I2A (`IncomingInquiryPolicy`, `IncomingInquiry::scopeVisibleTo`, 404 for foreign/unassigned) | re-verified by the new matrix; no code change |
| Admin direct allocation, reassignment with required reason, audit in the same transaction, former assignee loses access, stale/concurrent writes | I2A, I4, I5 (`IncomingInquiryWorkflow`, row locks, `AuditRecorder`) | re-verified; no code change |
| Eligible-employee rule | `User::scopeAssignableToBookings()` — one source used by inquiry assign/reassign/convert, Booking assignment and the UI lists (I2A/I2B) | verified consistent; a tourist or inactive manager as target is refused on both paths (test added) |
| Booking visibility and writes limited to the responsible Manager (re-authorized under the row lock) | `BookingPolicy`, I4 `BookingLifecycle`, I5 documents | re-verified; no code change |
| Client directory limited to current work; no foreign history | I2B `ClientVisibility` | re-verified (test added) |
| Booking reassignment needs reason + audit; former Manager loses chat/notifications | I2B (G7, G8), I6 | **two residual surfaces found — see 13.2** |
| Neutral Manager client-verification request (option C) | I7 | re-verified (browser QA); **not redesigned** |
| Admin Booking chat: read all, write only when personally assigned | E3 contract | unchanged; pinned by a test |

### 13.2 Consolidation audit — gaps reproduced and fixed

Method: read every ownership check (policies, query scopes, controller `authorize` calls, workflow services, JSON endpoints, counts, notifications, attachments, direct URLs), then reproduced suspicions on synthetic data against the unfixed code.

| # | Gap (reproduced on `9a66781d`) | Authorization source | Fix |
|---|---|---|---|
| F1 | After a Booking is reassigned, the former Manager's **dashboard unread counter** and **sidebar chat badge** still counted unread messages of that Booking (only a number, but it discloses activity on someone else's conversation). I2B/I6 had fixed the JSON counter and the bell; these two surfaces ran their own unscoped `messages` count. 3 of the 14 new tests in that class failed before the fix. | `ManagerController::dashboard`, `cabinet/components/sidebar/manager.blade.php` | One rule `Message::scopeAccessibleTo(User)` (Admin all, Manager own current Bookings, Tourist own Bookings, others none) now used by `MessageController::unreadCount`, the dashboard and the sidebar |
| F2 | A Manager who **lost** a Booking (reassigned, or whose role was removed) could still **delete their own old message** in it (`DELETE /messages/{id}` only required `sender_id === self`), altering the history that the new Manager and the client see. 3 of the 14 new tests failed before the fix. | `MessageController::destroy` | A non-Admin sender must still pass the write gate (`authorizeBookingWrite`), re-checked under the Booking row lock inside the existing deletion transaction (same pattern as `store`, I4). Admin may still delete any message (existing contract, unchanged) |
| F3 | Latent only: `CabinetController::bookings` built an **unscoped** `Booking::query()` for staff behind `redirectIfNotTourist()` (matrix B-07). Unreachable, no exposure reproduced. | `CabinetController::bookings` | Owner scope (`user_id`) is now unconditional; staff are still redirected (regression test) |

Together the unfixed code failed 6 of the 14 tests of `StaffMessageVisibilityConsolidationTest` (F1: 3, F2: 3). Not changed on purpose: `messages/{id}/read` by a former Manager (touches only the caller's own receiver row, discloses nothing).

### 13.3 Manager / Admin authorization matrix (verified by `StaffAccessPolicyMatrixTest`)

`A` = Manager responsible; `B` = another Manager; `T` = Tourist owner; `U` = another Tourist. Cells are the observed behaviour.

| Surface | Admin | Manager A (own) | Manager B (foreign) | Tourist T (owner) | Tourist U (other) |
|---|---|---|---|---|---|
| Booking show — A's Booking | 200 | 200 | 403 | 200 | 403 |
| Booking edit — A's Booking | 200 | 200 | 403 | 403 | 403 |
| Booking show — Admin-owned or unassigned Booking | 200 | 403 | 403 | 200 | 403 |
| Booking writes (confirm/cancel/complete/update/password-setup) | allowed | own only | 403/404, state unchanged | no | no |
| Booking assign-manager | allowed | **403** (Admin only) | 403 | no | no |
| Chat JSON `messages?booking_id` and attachment | 200 (read all) | 200 | 403 | 200 | 403 |
| Chat write `messages.store` | **only if personally assigned** (403 otherwise) | 200 | 403 | 200 | 403 |
| Chat pages (`manager/chat`, `admin/chats`, `cabinet/chat`) | admin chats 200 | 200; foreign/Admin-owned 404 | 404 | own 200 | 404 |
| Message delete | any (unchanged) | own message **while still responsible** | 403 | own message | 403 |
| Booking document download / upload / delete | all | own Bookings | 403 | owner route 200 | 403 |
| Unread counts (JSON, dashboard, sidebar, bell) | all | **current Bookings only** | — | own | — |
| Inquiry list / show | all | own assigned only; foreign, unassigned, Admin-owned → 404 | 404 | 403 | 403 |
| Inquiry claim / assign / reassign | allowed (reason required for reassign) | 404 (unassigned) / 403 (own) | 404 | 403 | 403 |
| Inquiry close / convert / client actions | all | own assigned only; foreign → 403/404, state unchanged | 403/404 | 403 | 403 |
| Client directory (`manager/clients`, lookup, Booking-create) | all | clients tied to current work only | same rule | no | no |
| Client-verification request | resolves all | submits, sees own, neutral outcome | own only | no | no |
| Admin-only pages (users, roles, audit, logs, client-request resolution) | allowed | 403 | 403 | 403 | 403 |

The test also asserts that the target of an assignment must be an eligible employee (active Manager/Admin) for both inquiries and Bookings, and contains a **route-classification guard**: every named route with an ownership-bearing parameter (`booking`, `inquiry`, `message`, `clientRequest`, `notification`, `document`, `user`, `role`, `bookingId`) must be listed as matrix-tested, admin-only (and must still carry `role:admin`) or own-record; a new unclassified route fails the suite, so the policy layer cannot silently grow a hole.

### 13.4 Verification

- **Focused:** `StaffMessageVisibilityConsolidationTest` (14; 6 failed before the fix, 14/14 after) and `StaffAccessPolicyMatrixTest` (14; the matrix was already satisfied by the unfixed code, which is the evidence that AOA-01 had delivered the inquiry/booking/document/chat policy).
- **Full suite (SQLite `:memory:`, Xdebug off, 2048 MB):** **1737 tests / 13483 assertions, 0 failures, 0 errors** on PHP 8.5.12 and on PHP 8.4.13 (+28 over 1709 / 13258). One existing expectation changed deliberately: `ManagerClientListQueryEfficiencyTest` counts statements containing `bookings` (6→7; the sidebar unread counter is still a single statement, now with a sub-select; no growth with client count).
- **Disposable MySQL 9.7.1** (new data directory and port created for this slice; canonical never touched): the migration chain applies; `StaffMessageVisibilityConsolidationTest`, `StaffAccessPolicyMatrixTest`, `MessageAttachmentSecurityTest`, `BookingNotificationPrivacyTest`, `DeletedMessageNotificationPrivacyTest`, `MessageParticipantAuthorizationTest` — **101 tests / 572 assertions pass**. True two-process barrier: a reassignment holds the Booking row lock, the former Manager's delete issued meanwhile **blocks ≈3.9 s and then returns 403 with the message preserved**; the same harness on the **pre-fix controller returns 200 and deletes the message**. Stress: 3 rounds × (4 concurrent deleters × 25 + a locker × 25) — 0 errors, no deadlock, no lock-wait timeout.
- **Chrome QA (headless, CDP) at 360 px and 1200 px, app instance on the disposable MySQL:** 60/60 checks at each width — Manager isolation (own inquiry/booking/client only; colleague, unassigned, Admin-owned → 404/403 with no data), claim refused, Admin oversight and allocation through the real form, Booking reassignment through the real form with a reason, former Manager loses badge/chat/documents/clients/own-message deletion, new Manager gains them, neutral client-verification request (existing foreign e-mail vs unknown e-mail indistinguishable on the Manager page), Admin chat write refused when not assigned, Tourist isolation, no horizontal overflow, no first-party console errors. Evidence is kept outside the repository (`C:\Avilona_private\AOA-02_Verification\`).

### 13.5 Compatibility and safety review

No migration, no dependency change (Composer untouched; the nette/utils and nette/schema platform ceilings recorded in ENV-COMPAT-01 remain open and must be handled in a separate dependency pass before E6), no PHP configuration change. The code uses no 8.4/8.5-only symbols (PHP 8.3 compatibility is preserved by syntax; an 8.3.36 binary was still not available locally). No new route and no new response contract: denials keep their existing codes. No secrets, real e-mails or personal data were added; all fixtures are synthetic (`example.test`).

### 13.6 Residuals and observations (recorded, not changed)

- Every AOA-01 residual R-A … R-O (`docs/roadmap.md` §A7.1) is **unchanged** — in particular public account enumeration (R-A), client-verification data retention (R-B), the fixed 30-day window and missing revoke (R-L), and durable inquiry/draft continuity (R-D, **AOA-03**).
- **Admin message deletion is not audited** (B-09) and **Booking price revisions emit no audit event** (B-03): outside the visibility scope; candidates for the audit work in AOA-08/09.
- `users.is_active` is used only for assignment eligibility and client lookup; the application has **no deactivation feature and does not check the flag at login**. An already-assigned Manager who is deactivated by other means would keep their work. Not reachable through the product today; decide together with workload/eligibility rules in AOA-04.
- Admin and Tourist sidebar counters were deliberately left as they are (Admin oversight is allowed; a Tourist owns the Booking for life).
- `messages.index` serializes the full `sender`/`receiver` user models of both participants; both are legitimate participants, so this is not an authorization issue, but the serialized attributes were not reviewed here.
- AO-07 (403 vs 404) was re-examined and is consistent per surface family (13.3); not changed.

### 13.7 AOA-02 closure assessment

All §A3–§A4 requirements are implemented and now pinned by one route × actor regression plus the route-classification guard; the only code gaps found (F1, F2; F3 latent) are fixed with focused tests, MySQL concurrency evidence and browser QA. **AOA-02 is complete; AOA-03 (provider-independent `Inquiry` entity) is next** and must reuse `IncomingInquiryPolicy`/`scopeVisibleTo`, the audit events, `Message::scopeAccessibleTo` and this matrix test rather than introduce a parallel rule set.
