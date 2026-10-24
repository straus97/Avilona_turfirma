# AOA-03-I2 — Controlled historical Inquiry backfill and integrity verification (technical reference)

Date: 2026-10-24. Technical evidence and design only; **status, NEXT and residuals are canonical in [`roadmap.md`](roadmap.md)**. Continues [`AOA-03-I1-inquiry-foundation-2026-10-24.md`](AOA-03-I1-inquiry-foundation-2026-10-24.md) (EXPAND). This is the BACKFILL + VERIFY rehearsal step of EXPAND → BACKFILL → VERIFY → SWITCH.

**Not done, by design:** the real-data backfill was **NOT executed**; the canonical database `turfirma_rebuild_v4` and WAMP MySQL 3306/3308 were **NOT accessed**; no route, listener, dual-write or runtime switch exists; `ClientVisibility` and all policies are unchanged.

## 1. Approved test mapping (owner proposal, synthetic verification only)

`IncomingInquiry.workflow_state` → `Inquiry.status`: `new→new`, `in_progress→in_progress`, `converted→converted`, `closed→closed`. Independently checked against the real workflow (`IncomingInquiryWorkflow`) and the I1 invariants: the meanings are identical (new = nobody's, in_progress = has an assignee, converted = one existing Booking + `converted_at`, closed = `closed_at`). Legacy rows that **violate** an invariant are never repaired (the old status is not rewritten); they are `BLOCKED` with a reason code. Examples that really occur: an `in_progress` row whose assignee was deleted (`assigned_to` is `SET NULL`), a `converted` row whose Booking was hard-deleted. The technical import state (`state`: pending/importing/imported/failed/unsupported, and `external_type ≠ 0`) is **not** a business status: such rows are `UNSUPPORTED` with `technical_state_<state>` / `external_type_not_supported` and get no Inquiry; if they nevertheless carry staff work they are `BLOCKED` (`business_progress_on_non_actionable_source`). No fifth status exists. This mapping is **not** approval to run on live data.

## 2. Field mapping

Copied (verbatim, NULL stays NULL): `origin=external_intake`, `incoming_inquiry_id`, `status`, `assigned_to`, `claimed_at→assigned_at`, `client_user_id`, `client_linked_by`, `client_linked_at`, `booking_id`, `converted_at`, `converted_by`, `closed_at`, `closed_by`, `close_reason`, `created_at`, `updated_at` (source values, so history is not re-dated). `created_by` stays NULL (no invented creator).
Not copied: `vendor_payload`, contacts (`client_name/phone/email/comment`), price/hotel/operator, provider and `external_id` (provenance stays in `IncomingInquiry`, leading zeros cannot be lost), passport data, secrets. The source query does not even select these columns. **Trip data** (`departure_city`, `destination_country`, `fly_date`, `nights`) is **not** mapped into the typed draft: a provider's flight date/nights is not the staff's draft, and the draft's `draft_updated_by/revision` semantics would be falsified. **Limitation recorded:** the draft of a backfilled Inquiry is empty; a future read path may show the provider summary from the source.

## 3. Components (`App\Services\Inquiries\Backfill`, command `inquiries:backfill`)

| Part | Role |
|---|---|
| `VerifiedQaConnection::verify()` | Positive proof of a disposable QA database from **configuration only (no query)**; the only way to obtain the connection object the services accept |
| `InquiryBackfillClassifier` | One decision function used by dry-run, apply and verify: `MATCHED` / `BLOCKED` / `CONFLICT` / `UNSUPPORTED` / `NOT_PROCESSED` + reason codes |
| `InquiryBackfillService` | `dryRun()` (no writes) and `apply()` (creates only missing Inquiries); chunked (`chunkById`, 1–1000, default 200) |
| `InquiryBackfillVerifier` | Read-only comparison + global checks; `fully_verified` only with no BLOCKED/CONFLICT/NOT_PROCESSED and no orphan/duplicate |
| `InquiryBackfillSnapshot` | SHA-256 of all `incoming_inquiries` rows (chunked) + Booking / `audit_events` counts before and after apply |
| `BackfillInquiriesCommand` | `inquiries:backfill --connection=<qa>`; default dry-run; `--apply --confirm-database=<name>`; `--verify`; `--json`; `--chunk` |

**Database protection.** The default Laravel connection (which may be canonical) is never used. `--connection` is mandatory and must pass: marker `qa_disposable === true` (`AOA_QA_DISPOSABLE`), not production, driver sqlite/mysql/mariadb; MySQL: loopback host, explicit port in 1024–65535 and **not 3306/3308**, database `aoa03_qa_*` (never `turfirma_rebuild_v4`), no URL/socket override, no collision with another configured connection or with the default connection's port; SQLite: `:memory:` or `aoa03_qa_*.sqlite` in the OS temp directory. Refusal happens before any query (tested with a query listener and the connection registry). `--apply` additionally needs `--confirm-database` equal to the database name. A different database name on the same WAMP instance is **not** isolation. The `aoa_qa` entry in `config/database.php` is empty unless `AOA_QA_DB_*` are set.

## 4. Behaviour contracts

- **Identity** `incoming_inquiry_id` (unique, RESTRICT FK); one Booking ↔ at most one Inquiry (`booking_id` unique). Never creates a Booking, never touches `IncomingInquiry`, assignments or `audit_events` (no fabricated history events). Verified by hash/count before and after.
- **Idempotent / restartable.** Each source = one short transaction: lock source row `FOR UPDATE` → re-classify under the lock → one INSERT. A repeat run reports `ALREADY_PRESENT`; a crash leaves only complete rows and the next run continues. A lost unique race is caught (`race_resolved`) and settled by re-classification.
- **Conflicts (never overwritten).** `CONFLICT`: `diverges:<field>` (existing Inquiry differs from the mapping; field names only), `duplicate_inquiries_for_source`, `booking_owned_by_other_inquiry` (ownership preserved), `duplicate_booking_reference_in_sources` (both sources conflict; no arbitrary winner), `inquiry_exists_for_unmappable_source`. `BLOCKED`: `invariant_*`, `conversion_fields_on_non_converted`, `closure_fields_on_non_closed`, `unresolved_assignee|client|actor_reference|booking`, `client_not_tourist`, `model_invariant`. Manual Inquiries are never modified.
- **Report privacy.** Counts, reason codes and at most 20 numeric source ids per code; no names, e-mail, phone, passport data or payload.
- **Verification semantics.** `MATCHED` ≈ what apply would create; the apply outcomes are `CREATED` / `ALREADY_PRESENT` / `BLOCKED` / `CONFLICT` / `UNSUPPORTED`. The comparison is valid **before** the runtime switch only: once Inquiries are edited (AOA-04/05) divergence of status/assignee becomes legitimate and the verifier must be replaced by a parity check (a test shows it flags legacy drift).

## 5. ClientVisibility — unchanged; required change before the switch

`ClientVisibility` and the Manager counters still read `IncomingInquiry` / `Booking` only; a backfilled `Inquiry.client_user_id` grants **no** access (test: visible-tourist sets of Manager, outsider Manager and Admin are identical before and after apply). Before SWITCH it must be extended so that a Manager sees a client through an Inquiry **only when `inquiries.assigned_to` is that Manager now** (current assignee authoritative; `created_by`, `converted_by`, historic assignment or a former `assigned_at` never grant access), together with the Manager counters and notifications, behind tests. Closed/converted rows follow the same current-assignee rule as today's legacy rows.

## 6. Remaining prerequisites before a real-data backfill / SWITCH

1. Owner authorization + backup + migration of the canonical database (not authorized in I2); rehearsal on a **copy** of canonical data first (counts of `BLOCKED/CONFLICT` there are unknown).
2. Owner decision for each real `BLOCKED` class (e.g. `in_progress` rows that lost their assignee: Admin re-allocation is a business act, not a backfill rewrite).
3. `ClientVisibility`/counter extension (§5); dual-write or freeze window design so legacy and Inquiry cannot drift between backfill and switch; replace the verifier by a parity check.
4. Known unchanged limits: deleting a user nulls `assigned_to`/`booking_id` (I1 §7); `Tour` seat concurrency (roadmap) is unrelated.

## 7. Evidence

- PHPUnit (SQLite `:memory:`): new `InquiryBackfillTest` (58 test cases, 14 of them refused-connection data-set cases). Full suite **1864 tests / 14977 assertions, 0 failures, 0 errors** on PHP 8.4.13 (+58 over 1806 / 14660); the new class also passes on PHP 8.5.12; the new files lint clean on PHP 8.3.32 (no 8.3.36 binary locally). One pre-existing PHPUnit deprecation notice.
- Disposable **MySQL 9.7.1** (fresh data directory, port 3398, loopback, `--no-defaults`, synthetic credentials, database `aoa03_qa_rehearsal`, stopped afterwards; the harness's default connection pointed at a dead port so any accidental use of the default would have failed; WAMP 3306/3308 and `turfirma_rebuild_v4` never touched). **29 checks pass**: CLI dry-run (no writes) / apply refusal without confirmation / first apply (25 created) / second apply (0 created, 25 `ALREADY_PRESENT`) / verify; deterministic rebuild after wipe (identical content hash); 1062 on duplicate source and Booking, 1452 unknown client, 1451 RESTRICT on source delete; a process killed (`exit`) inside the open transaction leaves 4 complete rows and the resume creates the remaining 21; blocked/conflict classes with existing Booking owner untouched; two sources on one Booking (legacy unique index swapped for a plain index on the disposable DB only) both `CONFLICT`; **4 parallel worker processes** (start barrier file) create each of 125 Inquiries exactly once, result identical to a single process; negative control: a naive check-then-insert without lock/unique key creates duplicates (harness is sensitive). Limits: per-source `FOR UPDATE` serializes workers on a row, so the unique-race path (`race_resolved`) was not triggered on MySQL (covered by an injected-exception test only); a deadlock/lock-timeout under much heavier load, and real-data volume, are untested. Harness and logs live outside the repository (`C:\Avilona_private\AOA-03-I2_Verification`).
- Found and fixed during verification: the apply report's `inquiries_created` was a table-count delta (it showed 4×125 for four parallel workers); it now counts this run's `CREATED` outcomes, the delta is `inquiries_table_delta`.
- Browser QA: **DEFERRED — NO I2 UI SURFACE.**
