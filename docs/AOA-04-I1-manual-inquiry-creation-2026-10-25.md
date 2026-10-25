# AOA-04-I1 — Manager manual Inquiry creation and durable draft UX

Date: 2026-10-25. Base commit: `d0cd38a5e73a2c774abba02054afdaab9052d545`. First, bounded slice of AOA-04 (roadmap §A4/§A6). Implemented on the AOA-03 foundation under the owner's clean-start decision (AOA-03-I7, Scenario A). No real data, no production, no deployment.

## 1. Scope actually delivered

A Manager can record a **telephone** or **messenger** inquiry, save trip preferences and come back to the saved draft later. Reuses, unchanged in behaviour: `Inquiry` model/invariants, `InquiryPolicy`, `InquiryAuthority`, `InquiryDraftStore` (transaction, row lock, current-owner re-authorization, revision check, `inquiry.draft_updated` audit), `InquiryDraftData`, `AuditRecorder`, `AuditEventType::InquiryCreated` (declared in AOA-01-I1, first wired here). No second draft store, no parallel authorization layer, no `IncomingInquiry` involvement.

| Piece | Where |
|---|---|
| Additive migration (`contact_channel`, `creation_token`, unique `(created_by, creation_token)`) | `database/migrations/2026_10_26_000000_add_contact_channel_to_inquiries_table.php` |
| Closed channel vocabulary + invariants | `Inquiry::CHANNEL_PHONE`, `CHANNEL_MESSENGER`, `contactChannelLabels()`, `assertInvariants()` |
| Creation service | `App\Services\Inquiries\ManualInquiryCreator` |
| `create` ability | `InquiryPolicy::create` |
| Controller | `App\Http\Controllers\Manager\AgencyInquiryController` |
| Routes (`cabinet.manager.agency-inquiries*`) | `routes/web.php`, inside the existing `role:manager,admin` group |
| Views | `resources/views/manager/agency-inquiries/{index,create,show}.blade.php`, sidebar entry «Обращения клиентов» (manager + admin) |

## 2. Trusted creation

`ManualInquiryCreator::createForManager($actor, $channel, $token)` runs in one transaction: re-reads the actor under lock (`User::assignableToBookings` — the existing eligibility rule — so a deactivated or de-roled account is refused), re-authorizes `create`, then writes: `origin = manager_created`, `status = in_progress`, `created_by = assigned_to = actor`, `incoming_inquiry_id / booking_id / client_user_id = NULL`, `draft_revision = 0`, and records `inquiry.created` with metadata **only** `origin` + `contact_channel`. Audit failure rolls the Inquiry back (tested on SQLite and MySQL).

The HTTP layer accepts exactly `contact_channel` and `creation_token` (+ CSRF). **Any other field** (`assigned_to`, `created_by`, `origin`, `status`, `booking_id`, `client_user_id`, `incoming_inquiry_id`) rejects the whole request with a validation error and creates nothing. `InquiryPolicy::create` = has Manager role **and not Admin** (effective role admin > manager): Admin-created inquiries and assignment to another employee are deferred (§6). Nothing creates an `IncomingInquiry`, a `Booking`, a Tourist account, mail or a notification (tested).

## 3. Source channel vs origin

`origin` = who created the record (manager / admin / external intake). `contact_channel` = how the client reached the agency: `phone` («Телефонный звонок») or `messenger` («Личные сообщения в мессенджере»). Closed vocabulary; the model rejects unknown values, requires a channel for manual creation, and forbids one on external-intake Inquiries. No messenger platforms, provider ids, conversation text, credentials or contact data are stored. The factory states `managerCreated` / `adminCreated` default to `phone`.

## 4. Retry safety

The creation form carries a random UUID (`creation_token`). `(created_by, creation_token)` is unique at the database level; a repeat with the same token returns the existing Inquiry (`wasRecentlyCreated = false`, **no second row, no second audit event**), and a concurrent duplicate that loses the unique-index race is resolved the same way. The submit button is also disabled after the first submit (existing `data-submit-once` convention). Verified: HTTP resubmission (SQLite), 6 parallel processes on one token (MySQL → one row, one event, one `new`), and a real double-click in Chrome. Limits: a token is per form display; reopening the form is a new, legitimate inquiry.

## 5. UI behaviour

- **Index** «Обращения клиентов» (manager: own only; admin: all independent, with owner column; no create button for admin). Explicitly says these are not bookings and not the Tourvisor «Входящие обращения» (that entry and its routes are untouched). Linked/unassigned external Inquiries are not shown and return 404 here.
- **Create**: radio choice of channel, no other field.
- **Card**: info block (channel, created, owner, client «Не привязан», draft revision) and the draft form for exactly the `Inquiry::DRAFT_FIELDS` (country, city, departure city, dates, nights, adults, children, children ages as "5, 8", preferences, internal notes). All optional. The payload goes to `InquiryDraftStore::save` as is, so unknown/protected keys are rejected by the store itself.
- **Errors**: validation keeps the typed values; a stale revision **does not save**, redirects to the card with the current revision and shows the submitted values in a comparison panel so nothing the user typed is silently lost; an assignee change between display and submit → 404.
- **Access**: foreign / unassigned / former-assignee → **404** (as for `IncomingInquiry`); Tourist/no role → 403; guest → login. Admin can open and (through the same store, audited with their id) edit; this follows the existing policy («Admin ведёт все»).
- No JSON endpoint; pages show names only (never a `User` model, e-mail or phone). `creation_token` and `internal_notes` are `$hidden`.

## 6. Not done (remaining AOA-04 work)

Full list/filter/search dashboard; Admin-created Inquiry, Admin allocation of external work and reassignment with reason + history (§A4); client association/verification UX from the Inquiry card and client-contact capture (a deliberate privacy/data-model decision is still needed — I1 stores no client contact data); status transitions (close); explicit Booking conversion (AOA-05); Inquiry-side counters/notifications (AOA-16). Residual R-D is **partially addressed** (manual form + durable draft now exist); a *pending client verification* from an Inquiry still cannot be started on the new entity.

## 7. Verification

- Focused: `ManualInquiryCreationTest` (46 tests) + one added case in `StaffAccessPolicyMatrixTest` (new `{inquiry}` routes classified in the AOA-02 route guard).
- Full PHPUnit, run sequentially, Xdebug off: **PHP 8.5.12 and PHP 8.4.13 — 2147 tests, 16394 assertions, 0 failures, 0 errors** (baseline 2100/16088; 1 pre-existing PHPUnit deprecation notice). Code is 8.3-compatible; 8.3.32 was not used for runtime testing.
- Disposable MySQL 9.7.1 (own datadir, loopback port 3428, synthetic credentials, identity confirmed by `@@port/@@datadir`; WAMP 3306/3308 and `turfirma_rebuild_v4` not touched): migration, rollback + reapply, unique index, creation + audit, audit-failure rollback, 6-way same-token race, concurrent stale-revision race (one winner, two stale rejections, one draft audit event), authorization, no Booking / IncomingInquiry created — all pass.
- Chrome (headless, CDP) against synthetic data at 360 px and 1200 px, 43 checks each: creation by keyboard, channel validation, double-activation, draft save/revisit, validation retention, stale-revision conflict, Manager B 404, Admin oversight, Tourist 403, nav active state/`aria-current`, labels, no horizontal overflow, no console errors or failed first-party requests. External CDN requests (Bootstrap/fonts) were allowed to load as in the rest of the cabinet. Evidence is kept outside the repository.
