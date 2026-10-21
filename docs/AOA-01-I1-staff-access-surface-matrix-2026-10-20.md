# AOA-01-I1 — staff data-access surface matrix (implementation evidence)

**Date:** 2026-10-20 · **Slice:** AOA-01-I1 · **Source baseline:** `013d2483` (+ the I1 audit foundation).

> **Update 2026-10-21 (AOA-01-I2A):** gaps G1, G2, G3, G4 and G6 below were *implemented and verified* in AOA-01-I2A — see **§6 (implementation evidence)**. The "Gap" columns and §3 describe the state at I1 and are kept as the historical baseline. G5, G7 and G8 were *implemented and verified* in AOA-01-I2B — see **§7**.
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
