# AOA-01-I1 — staff data-access surface matrix (implementation evidence)

**Date:** 2026-10-20 · **Slice:** AOA-01-I1 · **Source baseline:** `013d2483` (+ the I1 audit foundation).

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
| G1 | Manager lists and opens all inquiries incl. unassigned and colleagues' (client phone/e-mail/comment) | I-01, I-02 | Known (roadmap §A5); reproduced. **Not silently fixed in I1.** |
| G2 | Manager can claim and close unassigned inquiries | I-03, I-09 | Known; reproduced |
| G3 | Reassignment has no required reason and no durable history | I-04 | Known; foundation now exists (`audit_events`) |
| G4 | Admin cannot assign a NEW inquiry directly (must claim-then-reassign) | I-04 | New precision on §A4 |
| G5 | Tourist directory (name/e-mail/phone) exposed to any Manager via inquiry lookup and booking-create dropdown | I-05, I-12, B-05 | Needs an owner decision on lookup scope |
| G6 | Authorization is skipped on idempotent early returns inside `convert` and `close` (HTTP is still blocked by the controller) | I-08, I-09 | Defence-in-depth |
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
