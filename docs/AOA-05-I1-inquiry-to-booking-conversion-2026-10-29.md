# AOA-05-I1 — Independent Inquiry → Booking conversion

Date: 2026-10-29. Base commit: `87fb8872ecbc72152b741066ea394f7976f91daf`. First bounded slice of AOA-05 (roadmap §A6). No production, no deployment, no migration, no real data, no Composer change. **AOA-05 stays ACTIVE** (§10).

## 1. Result

A Manager (their own current Inquiry) or an Admin can explicitly convert an **independent** Inquiry (`manager_created` / `admin_created`, no `IncomingInquiry` source) in `in_progress` with an explicitly linked Tourist into **exactly one** Booking. Nothing converts implicitly: saving a draft, linking a client, or an imported operator record never creates a Booking. The Booking is created in the same locked transaction as the Inquiry transition and the audit event; any failure rolls all three back.

## 2. What was reused (nothing parallel was created)

| Reused | From |
|---|---|
| Locked transaction + conditional `UPDATE` second line + `UNIQUE inquiries.booking_id` + events fired **after** commit | E5 `IncomingInquiryWorkflow::convert` (unchanged) |
| `Booking` model, whitelist of creatable fields, `STATUS_PROGRESS`, `DestinationCity::addCityIfNotExists`, `BookingCreated` event/listener | E5 conversion and `BookingController::store` |
| Eligible handler = `User::assignableToBookings()`; Tourist = active user with role `tourist` | Booking / E5 |
| Authority = `InquiryPolicy::view` family via `InquiryAuthority` (Admin or the Manager responsible **now**) | AOA-03/04 |
| Audit: `AuditRecorder`, `AuditEventType::InquiryConverted` (type already registered), `AuditMetadata` | AOA-01 |
| Existing columns `inquiries.booking_id` (unique, FK `SET NULL`), `converted_at`, `converted_by` | AOA-03-I1 — **no migration** |
| Lock order Inquiry-row first | `InquiryAssignment`, `InquiryClosure`, `InquiryClientAssociation`, `ClientVerificationService` |

New code: `InquiryConversion` (service), `InquiryConversionException`, `AgencyInquiryConversionController`, `InquiryPolicy::convert`, one route (`POST cabinet/manager/agency-inquiries/{inquiry}/conversion`, `agency-inquiries.convert`), view blocks, one predicate in `ClientVisibility`, one counter.

## 3. Eligibility (all re-checked under lock)

1. Route middleware `role:manager,admin` (Tourist 403, guest → login).
2. `visible()`: independent provenance and `view` (foreign / former / unassigned Manager → **404**).
3. In the service, on the locked rows: actor still active and authorized (`convert`); independent provenance (linked E5 Inquiry refused); status `in_progress` (closed / new refused); form not stale (status, responsible employee and client equal to the form's); **a linked client exists** (`conversion_client` error — a distinct refusal, never a validation error); **no pending client-verification request** on the Inquiry (not bypassed, same rule as closure); client active and a Tourist; responsible employee still `assignableToBookings()`.
4. A client is never created or inferred (contact name / phone / e-mail are not matched). The Admin/Manager client flows from AOA-04-I4 are the only way to link.

## 4. Booking contract

| Field | Value |
|---|---|
| `user_id` | the explicitly linked Tourist |
| `manager_id` | the Inquiry's **current** responsible employee (also when an Admin converts) |
| `status` | `progress` (as E5 and Manager-created Bookings) |
| `tour_id` | `NULL` — no fabricated Tour |
| `total_price` | `NULL` — no price is invented (price stays a later Booking edit; finance semantics unchanged) |
| `paid_amount` | DB default `0` — no payment |
| trip fields | from the **confirmed conversion form** (pre-filled from the draft: country, city, departure city, dates, nights, adults, children, ages); `departure_city`, `destination_country`, `start_date` (> today), `nights`, `adults` are mandatory by Booking's NOT NULL columns, so the form is a bounded explicit input step |
| `notes` | client-visible; pre-filled from the draft **preferences** and editable |
| `manager_notes` | empty field the employee may fill |
| draft `internal_notes` | **never copied** |

The `Inquiry` keeps: creator, origin, channel, contact, draft (revision untouched), client link, assignment history; it gains `status=converted`, `booking_id`, `converted_at`, `converted_by` (the actor). `created_by` is not replaced. No `OperatorBooking`, payment, settlement, Tour or user is created (tested).

## 5. Atomicity, uniqueness, races

One transaction (`DB::transaction(..., 3)` — three attempts so an InnoDB deadlock victim retries):

1. `SELECT … FOR UPDATE` the Inquiry row (first, like every other Inquiry operation → all of them serialize per Inquiry).
2. `SELECT … FOR UPDATE` the actor, client and responsible-employee rows in **one** query ordered by `id` (two Admins converting each other's Inquiries cannot deadlock).
3. Checks (§3) on the locked data.
4. `INSERT bookings`.
5. Model invariants checked on the target state, then a **conditional `UPDATE`** (`status=in_progress AND booking_id IS NULL AND assigned_to=? AND client_user_id=?`); `affected rows ≠ 1` → rollback.
6. `inquiry.converted` audit event. A failing recorder rolls everything back (tested).

Database protection: `UNIQUE(inquiries.booking_id)` — a Booking can belong to at most one Inquiry; the lock + conditional update make one Inquiry get at most one Booking. No external call happens inside the transaction; the `BookingCreated` event is dispatched after commit and a listener failure is logged and swallowed (the Booking stays).

Idempotency: authorization runs **before** the "already converted" return. A retry by an authorized actor returns the existing Booking (`created=false`): no second Booking, no second event, no applied values; the message never contains the Booking id. A foreign Manager's replay is a plain 404.

## 6. Converted status semantics

`in_progress → converted`, terminal for this slice (no reopen, no un-convert). Every edit path already guards `isOpen()` / `in_progress` and refuses a converted Inquiry (draft, contact, assignment, client select/create/unlink, closure, verification) — tested. The card stays readable under the unchanged `InquiryPolicy`; it shows the «Обращение оформлено в заявку» block with date and converting employee, and a link **only** if `BookingPolicy::view` allows (otherwise «ссылка недоступна»; a soft-deleted Booking is reported as deleted). Label: «Конвертировано в заявку».

## 7. Client visibility after conversion (owner contract implemented)

`ClientVisibility` independent-Inquiry branch: `status NOT IN (closed, converted)`. A converted Inquiry **by itself** grants no Tourist-profile access; the resulting Booking does (Booking branch: client owns a Booking where the Manager is responsible). Consequences, all tested (SQLite) and re-checked on MySQL: the handler keeps the client via the Booking; after the **Booking** is reassigned, the former Inquiry assignee loses the profile and the new handler gains it; a soft-deleted Booking grants nothing; other open Inquiries, other Bookings, live verification windows and Admin oversight are unchanged; closed behaviour (AOA-04-I6) is unchanged; E5 linked semantics are unchanged. Booking, chat and document access follow the existing `BookingPolicy` (a foreign Manager gets 403; Admin chat-write rules are untouched).

## 8. Audit and notifications

`inquiry.converted`, subject `inquiry`/id, actor = converter. Metadata: `previous_status`, `new_status`, `booking_id`, `assignee_id`, `client_id` — ids and statuses only (no names, contacts, notes, prices; asserted by scanning the stored row). Notifications: the existing `BookingCreated` listener fires **once** after commit (owner mail if preferences allow, Admin mails); tests use fake transports; no new message type was added.

## 9. UI, counters

Inquiry card: «Оформить заявку» section (explains the effect, shows the linked client and the responsible employee, blocks with an explanation if no client / pending verification, collapsible form, confirmation checkbox, hidden form-version fields `expected_status` / `expected_owner_id` / `expected_client_id`, double-submit guard). Trip fields are posted as `booking[...]` so redisplayed input never mixes with the draft form. After success: banner + authorized link, read-only card. List: new counter «Конвертировано» (`data-summary="converted"`), «Всего = В работе + Конвертировано + Закрыто» for independent Inquiries, scoped to the viewer's authorized set; linked E5 records are not counted; the status filter offers the new label.

## 10. Remaining AOA-05 work (not done here)

Conversion of **linked / `external_intake`** Inquiries (E5 stays the source of truth; a coordinated cross-model conversion needs its own design); price/commission entry (AOA-08), operator reference (AOA-06); reopening / reversing a conversion; Booking → Inquiry navigation on the Booking page; notification semantics for the handler (AOA-16). **AOA-05 is not closed.** R-B/U-10 retention, R-D owner acceptance, R-L notifications and all E6 gates stay OPEN; production is not deployed.

## 11. Verification

SQLite: PHP 8.5.12 and 8.4.13, Xdebug off, **2383 tests / 18470 assertions, 0 failures** (baseline 2347 / 18081). Disposable MySQL 9.7.1 (port 3470, dedicated datadir; 55 checks, four consecutive green runs): concurrent processes for two/four simultaneous conversions, conversion vs reassignment / closure / unlink / deactivation / deletion / verification resolution, crossed-Admin deadlock probe, audit- and INSERT-failure rollback, `UNIQUE(booking_id)`, converted-visibility, unchanged E5 conversion. Browser QA at 360 px and 1200 px with synthetic users. Results are recorded in the checkpoint report.

## 12. Test deviations

- `InquiryAwareAccessTest::test_closed_independent_inquiry_grants_no_client_while_converted_keeps_the_assignee_rule` encoded the pre-AOA-05 rule (converted still grants) and was rewritten to the owner contract (renamed `…_grant_no_client_the_booking_does`).
- `InquiryClosureTest` summary assertions gained the `converted` key.
- `AdminCabinetE3RedesignTest` compared a raw Faker name with escaped HTML (apostrophe names such as `O'Reilly` failed intermittently, once in a full run of this slice): now `e($name)`, as in AOA-04-I6's two fixes.

## 13. Findings recorded during verification

- **Client-account hard deletion (existing design, not changed).** A Tourist can delete their account (and an Admin can delete users); `bookings.user_id` cascades, so the Tourist's Bookings — including a converted one — are removed, and `inquiries.booking_id` is detached (`SET NULL`). The Inquiry stays `converted` (history, audit evidence and contact are kept) and the card shows «Созданная заявка удалена»; a retry is refused with a clear message and never creates a second Booking. Found by the MySQL race run; covered by `test_hard_deleting_the_client_account_keeps_a_readable_converted_record`. Whether Bookings should survive account deletion (soft delete / retention) is an owner decision tied to R-B/U-10 and was **not** changed here.
- **Deadlock handling.** Cross-operation user-row ordering is only guaranteed inside this service (ascending id). A rare deadlock with an unrelated multi-user locker (e.g. verification resolution locking requester → client) is resolved by InnoDB; the transaction is retried up to three times.
- **Disposable-MySQL lock order** captured from the server's general log: `inquiries … FOR UPDATE` → `users … ORDER BY id FOR UPDATE` → `INSERT bookings` → conditional `UPDATE inquiries` → `INSERT audit_events` → `COMMIT`.
