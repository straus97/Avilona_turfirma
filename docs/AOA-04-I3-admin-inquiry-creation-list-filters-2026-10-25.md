# AOA-04-I3 — Admin manual Inquiry creation and staff list filters

Date: 2026-10-25. Base commit: `614ff4d376576db8238748c33c7c37b3e409994b`. Third bounded slice of AOA-04 (roadmap §A4/§A6), built on AOA-04-I1 (Manager creation) and AOA-04-I2 (Admin assignment). No real data, no production, no deployment.

## 1. Scope actually delivered

**Part A — Admin creation.** An active Admin creates an independent manual Inquiry and picks the initial responsible employee. **Part B — list.** The existing «Обращения клиентов» list gains status / channel / (Admin-only) responsible-employee filters, a status column, deterministic ordering, filter-preserving pagination and a filtered empty state.

| Piece | Where |
|---|---|
| Admin entry point (shared persistence + audit with the Manager path) | `ManualInquiryCreator::createForAdmin` (private `persist`, `run`, `cleanInput`; `createForManager` refactored onto them, behaviour unchanged) |
| Ability `createForStaff` (Admin only; `create` stays Manager-only) | `InquiryPolicy` |
| Route-specific store/create logic, list filters, back-link carry | `AgencyInquiryController` (same routes; no new route, no new nav item) |
| Status labels | `Inquiry::statusLabels()` / `statusLabel()` |
| History shows the real creation event | `InquiryAssignment::history` (+`created` flag) |
| Views | `agency-inquiries/{index,create,show}.blade.php` |

## 2. Admin creation contract

- Stored by the service only: `origin = admin_created` (existing vocabulary), `created_by` = authenticated Admin (never the assignee), `assigned_to` = selected employee, `assigned_at`, `status = in_progress`, `contact_channel` ∈ {phone, messenger}, `incoming_inquiry_id/booking_id/client_user_id = null`. No Booking, IncomingInquiry, User, mail or notification is written.
- Input accepted from an Admin: `contact_channel`, `creation_token`, `assignee_id`. Any other key (created_by, origin, status, booking_id, …) rejects the whole request. A Manager posting `assignee_id` is rejected the same way.
- Assignee must satisfy `User::assignableToBookings()` (active Manager or Admin, including the creating Admin); it is locked and re-checked inside the transaction. Inactive, Tourist, role-less, missing and non-numeric ids → validation error, no row, no event.
- Rule by route: effective role admin > manager. An account holding both roles follows the Admin rules (must choose an assignee, may choose themselves) and is refused by `createForManager`.
- Manager creation is unchanged (own Inquiry, no selector).

## 3. Idempotency

`creation_token` stays unique per `(created_by, creation_token)`. The Admin row is locked first, so same-actor submissions serialise; a replay returns the existing Inquiry (`wasRecentlyCreated = false`) **without changes** — a different assignee/channel in the replay is not applied and the flash says so — and without a second audit event. The lookup also filters by origin, and the creator is the trusted actor, so a token can never reach another employee's Inquiry. Unique-violation fallback covers the residual race. A retry after the assignee became ineligible still returns the already-created Inquiry.

## 4. Audit

Existing `inquiry.created`; actor = Admin; metadata `origin`, `contact_channel`, `initial_assignee_id` (ids/codes only). Inquiry and event commit atomically; audit failure rolls back the row. No `inquiry.assigned` event is fabricated for the initial responsible employee. The Admin-only history now also lists `inquiry.created` events that carry `initial_assignee_id` as «Создано администратором · … первый ответственный — X» — one real operation, one entry — distinct from later «Обращение назначено/переназначено» entries. Manager-created Inquiries add no entry (creator = responsible). I2 history, Admin-only visibility and reasons are unchanged.

## 5. List

Starts from the unchanged authoritative scope (`scopeIndependent` + `visibleTo`); filters only narrow it.

- `status` ∈ existing statuses, `channel` ∈ {phone, messenger}; `assignee` (Admin only) ∈ eligible employees or `none` (unassigned records, e.g. after account deletion). Values are whitelisted; anything else is ignored with an inline notice (never put into a query). A Manager's `assignee` parameter is never read, no control is rendered.
- 20 per page, `ORDER BY inquiries.id DESC` (deterministic), `appends()` keeps filters across pages; row links and the card's «← К списку» link carry the (re-validated) filters and page. Assignee names are eager-loaded (`id,name` only; query count is independent of row count — tested). No new JSON endpoint, no text search, linked external Inquiries stay excluded, no User models or e-mails in HTML.
- Indexes: existing `status` and `(assigned_to, status)` indexes serve the Manager/Admin-by-assignee/status paths; `contact_channel` is filtered over an already narrow manual set and **no index was added** (no evidence of need; revisit if the table grows).
- Known limit: the assignee filter lists eligible staff only, so records held by a now-inactive employee are reachable via the unfiltered list or «Не назначен» (when account deleted) but not by a dedicated filter.

## 6. Tests and verification

- New `AdminInquiryCreationAndFiltersTest` (34 tests) + updated `ManualInquiryCreationTest` (Admin is no longer refused; dual-role case) + route-matrix additions in `StaffAccessPolicyMatrixTest`.
- Full PHPUnit sequential, Xdebug off: PHP 8.5.12 and PHP 8.4.13 — 2219 tests, 16886 assertions, 0 failures, 0 errors (baseline 2185/16657; the 1 pre-existing PHPUnit deprecation notice unchanged).
- Disposable MySQL 9.7.1 (fresh datadir, loopback port 3439, synthetic credentials, identity from `@@port/@@datadir/@@bind_address`; 3306/3308 and `turfirma_rebuild_v4` untouched; only the verified instance stopped): admin creation + audit, audit-failure rollback, eligibility, 5 parallel same-token processes (one row, one event), same token from two admins (two rows), 6 parallel distinct tokens, assignee-row lock vs concurrent deactivation, JSON-path history query under strict SQL mode, no Booking/IncomingInquiry writes. Two runs.
- Headless Chrome on synthetic data at 360 px and 1200 px (51 checks each, three independent browsers) plus a keyboard-only creation script. Evidence outside the repository.

## 7. Not done (remaining AOA-04 work)

Client-contact capture and client association/verification UX; status transitions (close/convert); Inquiry counters and full search; assignee notifications (AOA-16); Booking conversion (AOA-05); dedicated filter for formerly-eligible assignees; linked external Inquiries remain E5-only.
