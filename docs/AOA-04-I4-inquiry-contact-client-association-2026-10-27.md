# AOA-04-I4 — Inquiry contact capture and explicit client association

Date: 2026-10-27. Base commit: `cefd0a1469e92aa8d7c18bc8f4713077c47cc759`. Fourth bounded slice of AOA-04 (roadmap §A4/§A6), built on I1 (Manager creation + draft), I2 (assignment), I3 (Admin creation + filters). Synthetic data only; no production, no deployment, canonical database not accessed.

## 1. Scope actually delivered

| Gate | Delivered |
|---|---|
| A — contact | Optional unverified prospect contact on an independent Inquiry: display name (≤120), callback phone (≤20), messenger contact (≤120, free text). Versioned, audited, atomic. |
| B — Option C | `ClientVerificationService::submitForInquiry` — a third context (`inquiry`) of the **existing** verification workflow; neutral for registered/unknown email; Admin resolves through the existing `linkExisting` / `approveNew` / `reject`. |
| C — association | `InquiryClientAssociation`: Manager/Admin select a **visible** client (`linkVisible`), Admin `createAndLink` (via `StaffClientAccounts`), Admin `unlink` with a validated reason. |
| D — UX | Existing Inquiry card gains «Контакт клиента» and «Клиент (аккаунт в Avilona)» sections; list gains a «Клиент» column (contact name only + «Аккаунт привязан» badge); Admin request page gains context label, link back, stale banner; Manager «Проверка клиентов» masks transferred rows. |

Optional operations **all included** (createAndLink, unlink) — none deferred.

Three identities stay separate: (A) unverified Inquiry contact (`inquiry_contacts`), (B) Tourist `User` linked only by explicit act (`inquiries.client_user_id`), (C) Booking travelers (untouched). No matching/merging by name, email, phone or messenger anywhere; no Booking, User (by a Manager), mail or notification is created implicitly.

## 2. Schema (two additive migrations, no backfill)

- `2026_10_27_000000_create_inquiry_contacts_table`: PK/FK `inquiry_id` (cascade), `contact_name 120`, `contact_phone 20`, `contact_messenger 120` (all nullable), `revision` unsigned int, `updated_by` (nullOnDelete), timestamps. No index on name/phone (no lookup/dedupe/search exists).
- `2026_10_27_000001_add_agency_inquiry_to_client_verification_requests_table`: nullable `agency_inquiry_id` FK → `inquiries` (nullOnDelete). `context_type` gains the string value `inquiry` (no enum).
- No email, passport, birth date or address on the Inquiry. Email stays only in the verification request (existing storage, existing R-B scope).

## 3. Revision / ABA contract (plan correction)

The preflight plan proposed deleting the row when all fields are cleared; that resets the counter and lets a stale form become valid again. **Chosen contract — tombstone row + monotonic revision, serialised by the Inquiry row lock:**

1. Absent row = revision 0. Every effective change (create, edit, clear) raises the revision by exactly 1.
2. **The application never deletes the row.** Clear nulls the three fields and raises the revision (tombstone). The row disappears only with its Inquiry (cascade).
3. A save carries the revision the employee saw; any mismatch is refused (no overwrite) with a comparison banner showing the rejected values.
4. All operations lock `inquiries.id` (`FOR UPDATE`) first — the same lock `InquiryDraftStore`/`InquiryAssignment` use. This serialises two first creations (nothing to lock on the contact row yet); the contact PK is a second line of defence.
5. Same values / clearing an empty contact = no-op: no write, no revision bump, no audit event.

Covered: create, update, clear, recreate, stale form from before clear, concurrent clear vs update, concurrent recreate vs stale update (SQLite logic tests + real MySQL multi-process, §8).

## 4. Authorization and privacy

- `InquiryPolicy`: `updateContact`/`linkClient` = Admin or the Manager responsible **now**; `requestClient` = responsible non-Admin Manager; `administerClient` = Admin. Controllers: role middleware → `visible()` (foreign/former/unassigned/E5-linked → 404) → Gate (403) → service re-check under lock with a fresh active actor row. Tourist 403, guest → login. Dual-role accounts follow Admin rules.
- Forged ids and protected fields (`inquiry_id`, `assigned_to`, `client_user_id`, `status`, …) reject the whole request; models are `$guarded=['*']`.
- Contact values never appear in: audit metadata (field **names**, revisions, `cleared` flag only), logs, exception messages, URLs (PUT/DELETE/POST only; client search is POST with one-shot old-input), JSON (`InquiryContact`/`Inquiry` hide everything), notifications, backfill/reconciler code (asserted), lists other than the Manager/Admin's own authorised list (name only, no phone).
- Option C neutrality: `submitForInquiry` reads only the actor's own `users` row; identical SQL statement sequence, status and message for registered and unknown email (tested with a query log). Rate limit (10/min) and pending cap (25) are shared with the existing flows. One open request per manager per Inquiry (repeat = same row, no second event).
- Visible-client selection: candidate = `ClientVisibility::touristsVisibleTo(actor)->lockForUpdate()`. Foreign/inactive/staff/non-existent ids give one identical refusal; Managers cannot browse all Tourists, enumerate emails or create Users. Visibility does not extend to the client's other Bookings, chats or documents (tested: 403/404).
- Reassignment: contact and linked client follow the Inquiry; the former Manager gets 404 immediately; inquiry-context verification approvals carry **no 30-day window** (`access_expires_at` NULL), so visibility disappears with the assignment; the former Manager's request row is masked («Обращение передано»).

## 5. Association rules

- Link: one locked transaction; Inquiry independent and `in_progress`; never overwrites; same-client replay = no-op; `expected_client_id` mismatch = «Клиент обращения уже изменён… Ваше действие НЕ выполнено»; audit `inquiry.client_linked` (`client_id`, `via` = selected|verification|created, `verification_id`).
- Verification resolution re-checks under locks: requester still an active Manager, Inquiry independent, in progress, assigned to the requester, **no client yet**; otherwise «потерял актуальность — отклоните». Nothing is written (no account created) on a stale resolve; reject is always allowed. Audit keeps existing payload keys and adds `agency_inquiry_id` only for this context.
- `createAndLink` (Admin only): existing `StaffClientAccounts::createTourist` (random hashed password, unverified email, no mail); email conflict/unique race → existing conflict wording.
- `unlink` (Admin only): reason 5–200 chars screened by `AuditMetadata` (no email/phone/path/token); clears client fields; audit `inquiry.client_unlinked` (`previous_client_id`, `reason`); the account, contact and draft are untouched; next link is a new explicit act. Managers can neither change nor remove a link.
- **Lock order** (all operations): Inquiry → ClientVerificationRequest → requester user → client. Matches `InquiryAssignment` (Inquiry → employee). Verified with 16 parallel resolve-vs-reassign rounds (no deadlock; both orderings exercised).

## 6. Routes added

`PUT|DELETE /cabinet/manager/agency-inquiries/{inquiry}/contact` (`…contact.update|clear`); `POST …/client/verification|search|select|create|unlink` (`…client.*`). All classified in `StaffAccessPolicyMatrixTest`.

## 7. Files

New: 2 migrations; `InquiryContact`; `InquiryContactData|Store|Exception`; `InquiryClientAssociation(+Exception)`; `AgencyInquiryContactController`, `AgencyInquiryClientController`, trait `Concerns/ResolvesAgencyInquiry`; views `agency-inquiries/partials/{contact,client}`; tests `InquiryContactCaptureTest` (33), `InquiryVerificationContextTest` (22), `InquiryClientAssociationTest` (25), `InquiryCardClientUxTest` (11).
Modified: `Inquiry`, `ClientVerificationRequest`, `InquiryPolicy`, `AuditEventType` (+`inquiry.contact_updated`, `inquiry.client_unlinked`), `ClientVerificationService`, Admin/Manager `ClientVerificationController`, `AgencyInquiryController`, `routes/web.php`, views (`show`, `index`, manager/admin client-requests), `StaffAccessPolicyMatrixTest`. Not touched: E5 files, `ClientVisibility`, `InquiryAssignment`, `InquiryDraft*`, Booking code, backfill tooling, `.env`, Composer.

## 8. Verification

- **PHPUnit (SQLite :memory:, Xdebug off, sequential):** PHP 8.5.12 and PHP 8.4.13 — 2310 tests / 17860 assertions, 0 failures/errors (baseline 2219 / 16886; +91 tests). PHP 8.3 syntax lint of all changed files clean. One pre-existing flaky test was seen once in an earlier full run (`AdminCabinetE3RedesignTest`, random Faker name with an apostrophe vs HTML-escaped output; passes on rerun, unrelated, left unchanged).
- **Disposable MySQL 9.7.1** (own datadir, loopback port 3449, identity confirmed via `@@port/@@datadir`, synthetic credentials; stopped afterwards): migration up/down/up OK; 41/41 checks — first-creation races (2 and 6 writers: exactly one winner), clear vs update, concurrent recreate vs stale update, ABA after clear+recreate, **negative control** (unlocked variant loses an update), resolve vs reassign ×16 (both orderings), submit vs reassign ×8, duplicate submissions, link vs unlink ×10, two Admins ×10 and ×6, audit-failure rollback for contact/link/create-and-link/unlink/resolution/request, PK/FK/strict-length/`pending_key` uniqueness, cascade/nullOnDelete.
- **Browser (headless Chrome, synthetic data):** 360 px and 1200 px — 58/58 workflow checks each (Manager/Admin/foreign Manager/Tourist, contact create/validation/stale/ABA, scoped search, neutral requests, Admin link/unlink/create/direct select, reassignment revocation, stale request banner, list) and 8/8 keyboard-only checks each; no horizontal overflow, a11y basics pass, no first-party asset/console failures, no external CDN failures.
- Evidence and harness are kept outside the repository.

## 9. Residuals (recorded, not fixed)

- **Retention R-B / U-10 stays OPEN.** `inquiry_contacts` and inquiry-context verification rows are further plain-PII stores with no retention period or deletion job; clearing is the only erase path. Nothing here claims production privacy or migration readiness.
- R-L: no Admin/Manager notification for new/resolved verification requests (inquiry context included); no manual revoke of other contexts' 30-day window.
- One open request per Manager per Inquiry: a typo in a pending request can only be corrected after the Admin rejects it.
- Admin search matches name/email/phone with `LIKE` over the Tourist directory (same matcher as E5); no phone/email normalisation beyond the matcher's.
- Closed/converted Inquiries are read-only for contact and client; status transitions, counters, search over contacts and Booking conversion remain AOA-04/05 work.
- Recommended next boundary (AOA-04-I5): status transitions (close with reason / hand-off to conversion preconditions) and Inquiry counters on the staff dashboard, still without Booking conversion (AOA-05).
