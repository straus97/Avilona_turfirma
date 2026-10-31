# AOA-05-I3 — Protected user deletion and Booking history preservation

Date: 2026-10-31. Base commit: `f6253a3931d690916bc055557ada1f5c06023171`. Second implementation slice of AOA-05 (roadmap §A6); **AOA-05-I2 was a read-only safety/planning checkpoint** (no code, no commit) whose findings this slice implements. No production, no deployment, no migration, no FK change, no real data, no Composer change. **AOA-05 stays ACTIVE.**

## 1. Why

Hard-deleting a Tourist cascaded (database FKs) to their Bookings — soft-deleted ones included —, messages, Booking documents (rows **and** files), reminder deliveries and the bonus ledger; deleting a Manager/Admin removed every message they sent or received and every client-verification request they raised, and silently nulled assignee / handler / creator evidence on Bookings and Inquiries. There was no audit event and the file cleanup ran inside the (uncommitted) transaction.

## 2. Owner-approved interim rules (implemented)

D1 a Tourist with any Booking (soft-deleted included) cannot be hard-deleted · D2 staff are not deletable where that would destroy or alter protected history · D3 a refusal keeps the session and explains in Russian with a contact path · D4 the last active Admin is never deleted · D7 linked (E5) Inquiry conversion stays deferred. No owner decision was made about deactivation, retention or erasure — none is implemented.

## 3. The guarded operation

`App\Services\Accounts\UserDeletion::delete(User $target, User $actor, string $channel)` is the **only** place that deletes a user (`InquiryFreezeGateTest` pins the single call site). Channels: `self_service`, `admin_panel`. Typed refusal: `UserDeletionRefused` (`last_admin` | `protected_history`, plus internal category codes).

Entry points, all routed through it: `AdminController::deleteUser`, `AdminController::destroyAccount`, `ManagerController::destroyAccount`, `CabinetController::destroyAccount`, and the unrouted `ProfileController::destroy` (so it cannot become a bypass if routed later). In addition `User::deleting` calls `UserDeletion::assertDeletable`, so a direct `$user->delete()` anywhere is refused for protected users and the last Admin. Controllers delete **first** and tear the session down only after a permitted delete; a refusal is `back()->with('error', …)` (shown by the shared cabinet flash component).

### 3.1 What is protected (refusal)

`UserDeletion::PROTECTED_REFERENCES` lists every column that references `users` and whose loss or nulling would destroy agency evidence. The check uses the raw table, so soft-deleted rows count:

| Category | References |
|---|---|
| Bookings | `bookings.user_id`, `bookings.manager_id` |
| Messages | `messages.sender_id / receiver_id / manager_id` |
| Booking documents | `booking_documents.uploaded_by` |
| Trip reminders | `booking_trip_reminder_deliveries.recipient_user_id` |
| Client verification | `client_verification_requests.requested_by / resolved_by / resolved_user_id` |
| Inquiries | `inquiries.created_by / assigned_to / client_user_id / client_linked_by / converted_by / closed_by / draft_updated_by`, `inquiry_contacts.updated_by` |
| Incoming (E5) inquiries | `incoming_inquiries.assigned_to / client_user_id / client_linked_by / converted_by / closed_by` |
| Bonus | `bonus_accounts.referred_by`; `bonus_accounts.user_id` only when the account is non-empty (balance / earned / spent ≠ 0 or any transaction) |

The check is role-independent: a dual-role account (e.g. Manager + Tourist) is judged by its actual references. **Accepted losses on a permitted deletion:** role links, personal documents (files cleaned after commit) and the registration-consent row (retention is open — U-10/R-B). A schema test (`test_every_foreign_key_to_users_is_classified_by_the_deletion_guard`) compares the live foreign keys to the inventory, so a new reference to `users` fails the suite until it is classified.

**Last active Admin:** refused when the target is an active Admin and no other active Admin exists (real role/`is_active` data, not the UI label); an inactive Admin may be deleted; the self-deletion check in the user list is kept.

## 4. Transaction and lock contract

One `DB::transaction` (deadlock retries 3): **(1)** first statement is a locking read without subqueries over the target **and all Admin rows, ascending id** (the Admin set is computed beforehand and re-verified under lock; a changed set retries) → **(2)** `assertDeletable` (protected references, last Admin) → **(3)** `user.deleted` audit event → **(4)** delete. Because the lock is taken before any consistent read, InnoDB's snapshot is created afterwards and sees every writer that committed first.

Writers: those that reference a user lock/touch the user row, so they either commit before the guard (the guard then sees and refuses) or wait for it (they then fail cleanly):

* already locked users: `BookingAssignment`, `InquiryAssignment`, `InquiryConversion` (actor, client, assignee, ascending), `IncomingInquiryWorkflow::convert`, `InquiryClientAssociation`, `ManualInquiryCreator`, `ClientVerificationService` client lookup;
* **added in this slice:** `BookingController::store` locks the actor row (owner-Tourist or handling Manager) before inserting; `MessageController::store` takes a shared lock on sender and receiver after the Booking lock;
* everything else (e.g. `requested_by`, `closed_by`) is covered by InnoDB's shared lock on the parent row of a foreign-key insert.

**Boundary — stated plainly:** the barrier relies on InnoDB (the production engine is unconfirmed — R-E) and on the FKs staying in place; direct SQL, tooling and the existing `ON DELETE CASCADE / SET NULL` actions bypass it. Role changes are not serialized against the guard; `removeRole`/`updateUserRole` can still leave zero Admins. The database is **not** deletion-proof until a future FK-contract stage.

## 5. Audit and logs

`AuditEventType::UserDeleted` (`user.deleted`): actor, subject type `user`, subject id, metadata `channel`, `target_roles` (e.g. `manager,tourist`), `self_service` — no name, e-mail, phone or passport data. The event is written in the deletion transaction; if the audit insert fails, the account, its rows and its files remain. A refusal writes **no** audit event; it logs `User account deletion refused` (technical log: actor id, target id, reason, category codes). Existing success logs (`Tourist deleted own account`, …) are unchanged.

## 6. File cleanup

`User::deleted` now calls `UserFileCleanup::schedule`, which uses `DB::afterCommit`: nothing is removed inside the transaction, a rollback discards the callback. Failure after commit cannot roll the delete back: it logs `User document file cleanup failed after commit` with `user_id`, counts and the **relative storage paths** (generated names, no personal data) — the manual recovery list. Residuals: avatars (public disk) and chat attachments are still never cleaned (R-H); no background garbage collection; a refused deletion never touches a file.

## 7. User-facing text

Tourist: «Аккаунт удалить нельзя: с ним связаны заявки, обращения или другие записи турагентства, которые мы обязаны сохранять. Вход в аккаунт и ваши данные в кабинете не изменились. … напишите нам на avilonatur@bk.ru — обращение будет рассмотрено отдельно.» Staff (self): work-history message, no counts or names. Admin (other user): categories only («заявки клиента, переписка по заявкам …»), explicit statement that deactivation is not implemented. Last Admin: dedicated message. The tourist confirmation modal no longer promises deletion of bookings; it distinguishes access to the account from the agency's obligations and does not claim that erasure requests are automatically impossible or satisfied.

## 8. Verification

* PHPUnit (SQLite `:memory:`, Xdebug off, `memory_limit=2048M`), PHP 8.5.12 and 8.4.13: **2437 tests / 18761 assertions, 0 failures, 0 errors** (baseline 2383 / 18470).
* Disposable MySQL 9.7.1 (own datadir, port 3480, synthetic data; never 3306/3308 or the canonical DB), real FKs and separate OS processes with injected holds: booking INSERT first → delete waits then refused; delete first → booking creation fails, no orphan; conversion first → client delete refused, Inquiry keeps its Booking; message INSERT vs sender delete → sender and message kept; mutual and concurrent Admin deletion → exactly one active Admin; real-FK refusal leaves rows intact; audit failure rolls the account/rows/files back; files exist inside an outer transaction and are removed only after commit; an outer rollback restores everything. (A "delete first vs conversion" order cannot arise: the Inquiry's client link is itself a protected reference, so the guard refuses before waiting.)
* Chrome 360/1200 px, synthetic data: Tourist/Manager/Admin refusals, last-Admin refusal, permitted Admin deletion, preserved session, no horizontal overflow, no 5xx / first-party resource or console errors — 19/19 each.

## 9. Not done / next

Account deactivation (enforce `is_active` at login and on live sessions, Admin deactivate/reactivate, last-Admin guard on it) is the sanctioned exit for refused accounts — separate slice. Retention/anonymization (U-10/R-B, legal D5/D6), the FK contract (`RESTRICT` + optional CHECK, expand/contract on a disposable MySQL), avatar/attachment cleanup, guarding Admin-role removal, and linked (E5) Inquiry conversion (D7, deferred) remain open. Reversal/reopen of a conversion, Booking→Inquiry navigation and handler notifications are unchanged AOA-05 remainders.
