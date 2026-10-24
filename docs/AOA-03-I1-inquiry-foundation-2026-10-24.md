# AOA-03-I1 — Provider-independent Inquiry foundation (technical reference)

Date: 2026-10-24. Technical evidence and design only; **status, NEXT and residuals are canonical in [`roadmap.md`](roadmap.md)** (§A1, §A6, §A7.1). This is the EXPAND step of EXPAND → BACKFILL → VERIFY → SWITCH. It adds a table, a model, a policy and a draft service. It adds **no route, view, listener or UI**, migrates **no existing record** and switches **no runtime path**.

## 1. Three distinct concepts (not merged)

| Entity | Role | Changed in I1? |
|---|---|---|
| `IncomingInquiry` | Technical external intake: provider metadata, deduplication (`provider + external_type + external_id`), E5 workflow | No |
| `Inquiry` (new, `inquiries`) | Agency-owned business inquiry: responsible employee, status, authorized client link, trip draft | New |
| `Booking` | Operational booking, lifecycle, security contracts | No |

A draft is never a Booking; no Booking is created, and no operator tour is booked, implicitly.

## 2. Schema — `inquiries` (migration `2026_10_24_000000_create_inquiries_table.php`)

Additive: one `CREATE TABLE`; `incoming_inquiries`, `bookings`, `users` are not altered. No data is copied.

| Group | Columns | Notes |
|---|---|---|
| Identity | `origin` (`manager_created` \| `admin_created` \| `external_intake`), `status` (`new` \| `in_progress` \| `converted` \| `closed`, default `new`) | Closed vocabularies enforced by the model |
| Provenance | `incoming_inquiry_id` nullable, **UNIQUE**, FK → `incoming_inquiries` **RESTRICT** | One business Inquiry per `IncomingInquiry`. Provider and `external_id` are **not copied**, so leading zeros/type cannot be corrupted; the source cannot be deleted while referenced |
| People | `created_by`, `assigned_to` (+`assigned_at`), `client_user_id` (+`client_linked_by`, `client_linked_at`), `converted_by`, `closed_by`, `draft_updated_by` — all nullable FK → `users` SET NULL | Client is an explicit staff decision, never derived from contacts |
| Booking | `booking_id` nullable, **UNIQUE**, FK → `bookings` SET NULL; `converted_at` | Same contract as `incoming_inquiries.booking_id`; filled only by the future explicit conversion (AOA-05); nothing writes it in I1 |
| Closing | `closed_at`, `close_reason(500)` | Mirrors the legacy fields so the later backfill is lossless |
| Trip draft | `destination_country/city`, `departure_city` (128), `date_from/to` (date), `nights_min/max`, `adults`, `children` (tinyint unsigned), `children_ages` (JSON list), `preferences` (1000), `internal_notes` (text, ≤ 2000 by contract) | Typed, bounded columns; the only JSON is a list of 0–10 integers 0–17 |
| Draft concurrency | `draft_revision` (default 0), `draft_updated_by`, `draft_updated_at` | Optimistic revision |

Deliberately absent: client name/phone/e-mail (unverified-client contacts stay in `client_verification_requests`), passwords, tokens, passport data, any provider payload or provider-specific field, prices/finance (U-2…U-4 are undecided), a Tour link.

## 3. Model invariants (`App\Models\Inquiry::assertInvariants`, run on create and whenever a watched attribute changes)

- `origin` and `status` come from the closed lists above.
- `external_intake` ⇔ has `incoming_inquiry_id`, has no `created_by`. Manual origins ⇔ have `created_by`, no source.
- On creation: `manager_created` is assigned to its creator; `admin_created` is assigned (to the Admin by default or to an explicit employee — the choice is AOA-04's); both start `in_progress`. `external_intake` may start in any valid status so that I2 can reproduce existing states.
- `new` ⇒ no responsible employee; `in_progress` ⇒ has one; `converted` ⇔ has `booking_id` and `converted_at`; `closed` ⇒ `closed_at`.
- `$guarded = ['*']` (no mass assignment of ownership); relations and `internal_notes` are in `$hidden`, so a loaded `User` relation is never serialized implicitly. `draftSnapshot()` is the explicit projection.
- **Not defined on purpose:** status *transitions* and the legacy→new status mapping. Transitions belong to AOA-04/05; the mapping is an I2 decision on real data. The identical status names are a vocabulary choice, **not** an approved mapping.

## 4. Authorization (`InquiryPolicy`, `Inquiry::scopeVisibleTo`)

| Actor | `view` / `updateDraft` |
|---|---|
| Admin | every Inquiry (assigned or not) |
| Manager | only an Inquiry assigned to them **now**; foreign, unassigned and formerly-assigned → denied |
| Tourist / no role | denied |

No `create`, `assign`, `reassign` ability exists in I1 (AOA-04). The service re-authorizes on the row locked inside its transaction, so a former Manager with an in-flight form is refused.

## 5. Draft persistence (`InquiryDraftStore::save`, `InquiryDraftData`)

`save(Inquiry, User $actor, array $patch, int $expectedRevision)`: one transaction → `lockForUpdate` → policy → inquiry must be open (`new`/`in_progress`) → revision must equal the one the employee saw (otherwise "already changed", nothing overwritten) → validated patch → revision +1. Only the 12 `Inquiry::DRAFT_FIELDS` are accepted; any other key (`assigned_to`, `status`, `booking_id`, `client_user_id`, …) rejects the whole patch. Empty strings/arrays and `null` clear a field; an empty patch does not bump the revision. Limits: nights 1–30 (max ≥ min), adults 1–10, children 0–10 with exactly that many ages 0–17, dates `Y-m-d` with `date_to ≥ date_from`, strict integers (no bool/float/array). No audit event: a draft edit is not a responsibility change. The free text is length-bounded but its content is **not classified** — staff must not paste credentials or passport data (documented limit).

Creation of Inquiries and assignment are **not** implemented (AOA-04): the service takes an existing Inquiry and derives the actor from the trusted caller, never from the patch. Tests create rows through `InquiryFactory` only.

## 6. Legacy E5 preservation

No change to `IncomingInquiry`, its workflow/policy, Tourvisor intake, deduplication, client selection/verification, the one-Booking conversion, notifications or accounts. Nothing creates an `Inquiry` when an `IncomingInquiry` is created (test). `AuthServiceProvider` only gains the new policy mapping.

## 7. Known limits carried to I2 / AOA-04 (recorded, not fixed)

1. `ClientVisibility` (SQL rule for what a Manager may see of clients) reads `IncomingInquiry` and `Booking` only. An `Inquiry.client_user_id` grants a Manager **no** client visibility yet — fail-closed. The switch must extend the rule (and its counters/notifications) before any UI uses `Inquiry`.
2. Deleting a user nulls `assigned_to` (as in the legacy table): an `in_progress` row can lose its assignee at database level; it then needs Admin re-allocation (AOA-04). Draft saves of that row still work for Admin; model invariants are rechecked only when a watched attribute changes.
3. A hard-deleted Booking nulls `booking_id` on a `converted` row (Bookings are soft-deleted in the application).
4. A Booking can be referenced both by a legacy `incoming_inquiries.booking_id` and by `inquiries.booking_id` (separate unique indexes); I2 must reconcile this, not duplicate it.
5. The new entity is not wired to `AuditRecorder`; assignment/reassignment events (`inquiry.created`, `inquiry.assigned`, `inquiry.reassigned`) belong to AOA-04 and must reuse the existing recorder inside the same transaction.
6. Any future API returning an Inquiry must use an explicit projection; never return a `User` model.

## 8. Prerequisites for AOA-03-I2 (controlled backfill + integrity verification)

1. Owner/analyst decision on the legacy→new **status mapping** and on which legacy states are backfilled (e.g. non-actionable `failed/unsupported` provider rows), validated on a **copy** of canonical data, never on live data.
2. Idempotent, resumable, dry-run-first backfill keyed on `incoming_inquiry_id` (unique) — creates `origin = external_intake`, copies `assigned_to`, `claimed_at → assigned_at`, `client_user_id/linked_by/linked_at`, `booking_id`, `converted_*`, `closed_*`, `close_reason`; **never** copies contacts or the provider payload; never mutates `incoming_inquiries`.
3. A verification report: counts per legacy state ↔ new status, orphan/duplicate check, Booking-link reconciliation (item 4 above), unchanged hashes of the legacy rows.
4. Extend `ClientVisibility` and the Manager counters to the new entity behind tests **before** SWITCH (limit 1).
5. Canonical MySQL stays untouched until the owner authorizes migration and backup; the backfill is rehearsed on a disposable MySQL first.

## 9. Evidence

- PHPUnit (SQLite `:memory:`): new `InquiryFoundationTest` (22) and `InquiryDraftStoreTest` (31); full suite 1806 tests / 14660 assertions, 0 failures, 0 errors (PHP 8.4.13; the two new classes also pass on PHP 8.5.12; new files lint clean on 8.3.32).
- Disposable MySQL 9.7.1 (port 3397, own data directory, `--no-defaults`, loopback, synthetic credentials; WAMP 3306/3308 and `turfirma_rebuild_v4` never touched; instance stopped afterwards): full chain, rollback `--step=1` → reapply, rollback with data, 9 foreign keys, unique indexes, JSON/date/utf8mb4 round trips, duplicate-source 1062, RESTRICT on source delete, assignee deletion; **37 checks pass**. Multi-process draft saves with file barriers and `performance_schema.data_lock_waits`: **12 checks pass**; the same harness without `lockForUpdate` fails 6 (negative control). Harness and logs live outside the repository (`C:\Avilona_private\AOA-03-I1_Verification`).
- Browser QA: **DEFERRED — NO I1 UI SURFACE.** End-to-end draft UX is not claimed.
