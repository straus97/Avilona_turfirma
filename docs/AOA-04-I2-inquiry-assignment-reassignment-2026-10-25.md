# AOA-04-I2 — Admin assignment, reassignment and history of independent Inquiries

Date: 2026-10-25. Base commit: `27170d2407ec5622358f6d6bd8154e9c33a15f5a`. Second bounded slice of AOA-04 (roadmap §A4/§A6), built on the AOA-04-I1 Manager creation flow. No real data, no production, no deployment.

## 1. Scope actually delivered

An Admin can **assign** (initial) or **reassign** the responsible employee of an *independent* (manually created) Inquiry from the existing Inquiry card, must give a reason, and sees a compact «История назначений» built from durable audit events. Managers cannot assign, reassign or self-claim and see neither the controls nor the history.

| Piece | Where |
|---|---|
| Transactional operation + history read | `App\Services\Inquiries\InquiryAssignment` (+ `InquiryAssignmentException`) |
| Ability `assign` (Admin only) | `InquiryPolicy::assign` |
| Endpoint `POST /cabinet/manager/agency-inquiries/{inquiry}/assignment` (`cabinet.manager.agency-inquiries.assign`) | `AgencyInquiryController::assign`, `routes/web.php` (same `role:manager,admin` group; authorization in controller/policy) |
| Admin-only block on the card | `resources/views/manager/agency-inquiries/show.blade.php` (no new sidebar entry, no new page) |
| Audit | existing `AuditRecorder`; existing `AuditEventType::InquiryAssigned` / `InquiryReassigned` (declared in AOA-01-I1; subject type `inquiry`, distinct from the legacy `incoming_inquiry`) |
| One model-invariant adjustment | `Inquiry::assertInvariants` — see §6 |

Reused unchanged: `Inquiry`, `InquiryPolicy` (view / updateDraft / create), `InquiryAuthority`, `InquiryDraftStore` (its row-lock + re-authorization is what makes the race safe), `ManualInquiryCreator`, `User::assignableToBookings()` (the single eligibility rule), `AuditRecorder`/`AuditMetadata`, `ClientVisibility`. The pattern follows `BookingAssignment` / `IncomingInquiryWorkflow::reassign`; no parallel permission framework or second audit log.

## 2. Eligibility and rules

- Actor: Admin only, re-read as an *active* user under the transaction and re-authorized on the locked row (a de-roled or deactivated admin is refused).
- Target: `User::assignableToBookings()` — active Manager **or** Admin, locked inside the transaction. Tourists, role-less, inactive and non-existent ids are rejected. An Admin assignee is allowed (the existing rule) and does not become a "responsible manager" in Manager scoping.
- Inquiry must be **independent** (`manager_created` / `admin_created`, no `incoming_inquiry_id`) and `in_progress`.
- `created_by`, `origin`, `contact_channel`, status, draft, client, booking and `incoming_inquiry_id` are never written. Only `assigned_to` and `assigned_at`. No Booking, IncomingInquiry, User, mail or notification is created.
- **Reason**: required for every change (including the initial assignment), 5–200 characters after whitespace normalisation, and screened by the unchanged `AuditMetadata` validator (e-mail, phone numbers, file paths, token-like strings rejected). The UI states that passwords, passport data, phones and e-mails must not be entered. The reason is shown only to Admins.

## 3. Independent vs linked Inquiries (E5 authority)

- **Independent**: handled here.
- **Linked to `IncomingInquiry`** (`external_intake`): *never* changed by this operation. The route answers 404 (these records are not part of this screen, same as I1) and the service throws before any write or source lock. E5's `IncomingInquiryWorkflow` remains the only assignment path; no cross-model synchronisation was invented. Tested: neither row changes, no audit event, and a legacy E5 reassignment still does not mirror to the Inquiry side.
- **Can an independent Inquiry be unassigned?** Created Inquiries always start `in_progress` with an assignee, and the model forbids `new` + assignee / `in_progress` without assignee on *writes*. The only legitimate unassigned independent state is the database's `ON DELETE SET NULL` after the responsible (and creator) account is deleted. The Admin may then perform an **initial assignment** (`inquiry.assigned`, previous = null) without a status change. An independent `new`/closed/converted record (impossible through services) is refused with a clear message: no status transition was added (out of scope).

## 4. Transaction, audit, idempotency

One `DB::transaction`: lock the Inquiry row → re-read actor → authorize → provenance → status → reason → lock the target user (`assignableToBookings`) → no-op check → stale check → save → `inquiry.assigned|reassigned` (metadata: `previous_assignee_id`, `new_assignee_id`, `reason`; actor = Admin). Audit failure rolls the assignment back (tested on SQLite and MySQL). Rejections write no event.

- **Same target again** (double submit, repeated request): safe no-op — no write, no event, a success message saying nothing changed.
- **Stale submit** (`expected_assignee_id` ≠ current, including the "unassigned" case): refused, never overwrites; the Admin sees the current state and an explicit "not performed" message. The field is mandatory (`present`).
- **Lock order**: Inquiry → (actor read) → target User. The draft store locks only the Inquiry; creation locks only a User and inserts a new row; no cycle exists (MySQL run: no deadlocks).

## 5. Access revocation

Immediately after commit the former Manager loses card (404), draft saves (404), the scoped list, and the client entitlement that came from the transferred Inquiry; the new Manager gains the card, draft saving, list entry and (through the existing independent-Inquiry rule) visibility of the explicitly linked client — nothing else: not the former Manager's Bookings, chats or documents (tested: Booking 403, chat 404). Admin oversight is unchanged. `403/404` conventions preserved: a Manager hitting the endpoint on an Inquiry they can see → 403, on anything else (foreign, unassigned, former, missing, linked) → 404; Tourist/role-less → 403 (role middleware); guest → login.

Race with an open draft transaction (real MySQL, two processes): a draft save that already holds the row lock commits first and the reassignment waits and then applies (event order: draft → reassignment); a draft save arriving while the reassignment transaction is open blocks, then fails authorization after commit — the draft stays untouched, no `inquiry.draft_updated` event.

## 6. Model-invariant adjustment

`Inquiry::assertInvariants` required `created_by` for manual origins on *every* invariant-relevant save. Because `created_by` is `ON DELETE SET NULL`, an Inquiry of a deleted employee could then never be reassigned (the save threw). The creator is now required **on creation**, and an existing record may legitimately have lost it through account deletion; application code still cannot set or change `created_by` of an existing record (`InquiryInvariantViolation`). Authorship is not rewritten by reassignment (tested on SQLite and MySQL).

## 7. UI

Admin-only section «Ответственный менеджер» on the existing card: current responsible (or «не назначен»), selector of eligible employees (name + role, current assignee excluded, no e-mail/phone), «Причина переназначения» (or «…назначения»), «Передать обращение» / «Назначить сотрудника», success/validation feedback in Russian, and «История назначений» (date/time, event label, Admin actor, previous → new, reason; newest first by audit id; a deleted account shows «учётная запись удалена» instead of an invented name; an empty history says nothing was recorded — old/raw changes are not reconstructed and the initial assignment made at creation is not shown, it is `inquiry.created`). Only `User` id/name are loaded; raw audit JSON and `User` models are never rendered. For a Manager the data is not even loaded.

## 8. Not done (remaining AOA-04 work)

Admin-created Inquiry and assignment at creation; full list/filter/search and Inquiry counters; client association/verification UX and client-contact capture; status transitions (close); Booking conversion (AOA-05); assignee notifications (AOA-16); reassignment of linked external Inquiries (stays E5 until a verified coordinated operation exists); reason catalogue/codes (free text for now).

## 9. Verification

- Focused: `InquiryAssignmentTest` (38 tests) + extended `StaffAccessPolicyMatrixTest` (new route classified; denial/transfer matrix) + `ManualInquiryCreationTest`, `InquiryFoundationTest` unchanged and green.
- Full PHPUnit, sequential, Xdebug off: **PHP 8.5.12 and PHP 8.4.13 — 2185 tests, 16657 assertions, 0 failures, 0 errors** (baseline 2147/16394; the 1 pre-existing PHPUnit deprecation notice unchanged). Code is 8.3-compatible; 8.3.32 was not used for runtime testing.
- Disposable MySQL 9.7.1 (fresh datadir, loopback port 3429, synthetic credentials, identity confirmed by `@@port/@@datadir/@@bind_address`; WAMP 3306/3308 and `turfirma_rebuild_v4` untouched; only the verified instance was stopped): 36 checks — atomic reassignment + audit, audit-failure rollback, 3-admin concurrent reassignment (one winner, two stale refusals, one event), 3-way identical request (1 changed + 2 no-ops, one event), stale refusal, both draft/reassignment lock orders with controlled process synchronisation, post-commit authority, deleted-account recovery, ineligible targets, linked refusal, no Booking/IncomingInquiry changes.
- Chrome (headless, CDP) on synthetic data at 360 px and 1200 px, 43 checks each, three independent browsers (stale Admin tab, acting Admin, Manager A's old draft tab): validation, keyboard-only transfer, history, stale refusal, former-assignee 404, new-assignee access, Admin-only controls, endpoint 403, no Booking access gained, a11y basics, no horizontal overflow, no HTTP 5xx, no console errors or failed first-party requests (the single expected 404 is the refused stale draft save). Evidence is kept outside the repository.
