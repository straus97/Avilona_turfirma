# AOA-04-I5 — Inquiry closure without a Booking and scoped workload counters

Date: 2026-10-28. Base commit: `e88ae706880ee60ea53e1003cd458336cc29cd54`. Fifth bounded slice of AOA-04 (roadmap §A4/§A6), after I1 (creation + draft), I2 (assignment), I3 (Admin creation + filters) and I4 (contact + client association). Booking conversion (AOA-05) is **not** part of this slice. Nothing is deployed; no real data, migration or backfill was touched.

## 1. Status contract

| From | To | Who | Notes |
|---|---|---|---|
| `in_progress` | `closed` | Admin, or the Manager responsible **now** (`InquiryPolicy::close`) | independent Inquiries only; reason required |

`closed` is terminal in this slice. Not implemented: `closed → in_progress`, any `→ converted`, linked external Inquiry status updates. Existing historical statuses and rows are not rewritten. No new status and no migration: `closed_at`, `closed_by`, `close_reason` already existed on `inquiries`.

## 2. Closure service (`App\Services\Inquiries\InquiryClosure`)

One transaction: lock the Inquiry row → re-read the actor (`User::active()`) → `Gate close` → independent provenance (linked ⇒ refused before any write) → status (`closed` ⇒ "already closed", no event; other than `in_progress` ⇒ refused) → expected status and expected responsible employee from the form (mismatch ⇒ refused, nothing overwritten) → reason (trim/collapse, 5–200 chars, `AuditMetadata::validate`: no e-mail, phone, path, token) → pending-verification check → set `status`, `closed_at`, `closed_by`, `close_reason` → `inquiry.closed` audit event (`previous_status`, `new_status`, `assignee_id`, `reason`; actor and `occurred_at` are event columns). A failing audit write rolls everything back. Creator, assignee, origin, channel, contact, client link, draft, Booking and IncomingInquiry are untouched; nothing is deleted. The route additionally applies `visible()` (foreign/former/linked ⇒ 404) and `Gate close` before the service; a `AuthorizationException` inside the service (assignment changed between show and submit) is mapped to 404. `close_reason` is in the model `$hidden` list.

## 3. Pending client verification (option B)

Closure is refused while a `pending` `client_verification_requests` row exists for the Inquiry ("ожидающий решения администратора"). Reasoning: all resolution paths (`linkExisting`/`approveNew`) lock the Inquiry row first, so closure and resolution serialise; `reject` locks only the request and is never affected by closure. Refusing is the smallest change that keeps E5/I4 verification semantics intact and leaves no actionable request against a closed Inquiry; the Admin resolves or rejects the request first. A pre-existing pending row against a closed Inquiry (cannot arise through this workflow) is already non-actionable (`assertAgencyInquiryCurrent` requires `in_progress`) and can be rejected. No Admin/Manager notification exists for this (R-L, AOA-16).

## 4. Closed-record behaviour (enforced in services, not only hidden buttons)

Draft save, contact save/clear, client select/create/unlink, verification request and assignment/reassignment all already require an open / `in_progress` Inquiry and refuse a closed one (covered by `InquiryClosureTest::test_closed_inquiry_refuses_…`; a refused edit leaves no audit event). The card shows a «Закрыто без заявки» banner with date, closer and reason, a `Закрыто` status row, a disabled draft/contact form and no closure form. Old records without a reason show «Причина закрытия не записана» — nothing is invented.

## 5. Counters (`InquiryWorkloadSummary::forIndependent`)

Summary «Всего / В работе / Закрыто» on «Обращения клиентов»: one `GROUP BY status` aggregate over `scopeIndependent(Inquiry::query())->visibleTo($user)` — the same authorised base scope as the list, never a global count hidden in Blade. Admin: all independent Inquiries; Manager: currently assigned only; other roles: zeros (and the route is 403 anyway). Linked external Inquiries are excluded. **Semantics:** the summary covers *all* authorised records and ignores list filters (status/channel/assignee) and spoofed query parameters; filters narrow only the rows below; ordering and pagination are unchanged. Returned data are three integers. (The older all-purpose `InquiryWorkloadSummary::for` is unchanged and still not wired to a screen.)

## 6. Client visibility after closure — observation, not changed

`ClientVisibility` gives a Manager the client of an independent Inquiry assigned to them **regardless of status**; closing therefore neither grants nor revokes visibility, and reassignment still removes it. Whether a client should stay visible to the responsible Manager after the Inquiry is closed is a business decision; **no permission was changed** (consistent with the legacy `IncomingInquiry` branch).

## 7. Verification

- PHPUnit: new `InquiryClosureTest` (21 tests) + route-matrix entry; full suite 2331 tests / 17996 assertions, 0 failures/errors on PHP 8.5.12 and 8.4.13 (baseline 2310 / 17860).
- Disposable MySQL 9.7.1 (port 3450, own datadir, loopback, synthetic credentials; identity guard on `@@port`/`@@datadir`): 27/27 checks — simultaneous closes ×2/×6, close vs draft/contact/reassignment both orders, close vs Admin resolution/rejection ×12, close vs request submission ×4, audit-failure rollback, stale status refusal, counters after commit. No deadlocks or lock timeouts.
- Headless Chrome 360/1200 px, synthetic data: 36/36 at each width (required-reason feedback, pending-verification message, keyboard-only closure, read-only closed card, stale draft tab, Manager B 404, Admin oversight, summary before/after, filters, spoofed `assignee`, pagination, no horizontal overflow, a11y basics, no 5xx/console errors). One transient header-overflow a11y flag appeared in one of four 360 px runs and did not reproduce in three reruns.

## 8. Residuals

R-B/U-10 retention OPEN; R-D unchanged (owner confirmation pending); R-L notifications OPEN; Booking conversion AOA-05; reopen, search, linked-Inquiry closure (E5), E6 gates untouched.
