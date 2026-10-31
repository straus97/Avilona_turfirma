# AOA-05-I4 — Safe account deactivation, authentication enforcement and last-active-Admin protection

Date: 2026-10-31. Base commit: `2a6e7d6fb4ed74aa0b9c394f16073c744305ac5c`. Fourth slice of AOA-05 (I2 was planning-only). No production, no deployment, **no migration** (the existing `users.is_active` column is reused), no FK change, no real data, no Composer change. **AOA-05 stays ACTIVE.**

## 1. Contract

An Admin can **deactivate** and later **reactivate** a Tourist, Manager or Admin account without destroying business history. Deactivation sets `users.is_active = false` and nothing else of substance: the user row, e-mail and personal data, role memberships, Bookings and their ownership/handler, Inquiries and assignment history, messages, booking and personal documents, audit events and financial data are **not touched**. It is **not** anonymization and **not** legal erasure; no retention period is invented (R-B/U-10 stay OPEN). Reactivation restores access only: it creates no User, Booking or assignment and never rewrites responsibility (a Booking that was handed to someone else while the account was inactive stays with that person). Deactivation sends no external message.

## 2. Operations

`App\Services\Accounts\AccountActivation::deactivate|reactivate(User $target, User $actor, string $reason, string $expectedState)` — one transaction under the lock contract of §4:

1. the acting user is re-read under lock and must be an **active Admin** now (a session that outlived its role/status is refused);
2. an Admin cannot deactivate **their own** account (a distinct message names the last-active-Admin case); another Admin can;
3. the **reason** is required (5–200 characters, whitespace-collapsed) and screened by the audit metadata validator (e-mail, phone, path, token-like strings are refused);
4. already in the requested state → **idempotent no-op** (no write, no audit event);
5. the form's **state token** (status + sorted role names, no personal data) must equal the current one, else a stale-form refusal;
6. `is_active` is changed; on deactivation the `remember_token` is rotated, Sanctum `personal_access_tokens` of the user are deleted and, only when `SESSION_DRIVER=database`, the user's `sessions` rows are deleted;
7. the invariant of §5 is checked after the change and rolls it back;
8. `user.deactivated` / `user.reactivated` is written **in the same transaction** — metadata: `previous_active`, `new_active`, `target_roles`, `reason`; actor id and subject `user` + id. No e-mail, name, phone, token or document data. If the audit insert fails, status, remember token and API tokens roll back; a refused call writes no audit event (it only logs ids and a reason code to the technical log).

Typed refusals: `AccountChangeRefused` (`not_authorized`, `self`, `last_admin`, `stale`, `invalid_reason`) with Russian user messages. Routes (Admin group only; unauthorized roles get 403, guests the login redirect): `POST cabinet/admin/users/{user}/deactivate` and `…/reactivate` (`cabinet.admin.user-deactivate|user-reactivate`); the target id comes only from the route.

## 3. Authentication enforcement (every path inventoried)

| Path | Enforcement |
|---|---|
| Fresh login (`LoginRequest`) | credentials carry `is_active = true`: an inactive account fails with the **same** `auth.failed` message, rate-limit hit and no hash check as an unknown e-mail or wrong password — no existence/state oracle |
| Open web session (every web route, authenticated or public) | `EnsureAccountIsActive` is in the `web` group after `AuthenticateSession`: it checks the **row** (not the possibly stale loaded model), logs the guard out, invalidates the session and redirects to the login page with a neutral «Сеанс завершён. Войдите снова.»; JSON requests get 401 |
| Remember-me | the cookie token is rotated on deactivation (the old cookie no longer matches) and, even with a matching token, the middleware rejects the inactive account |
| Sanctum bearer API (`GET /api/user`, the only `auth:sanctum` route) | tokens deleted on deactivation; the route also carries `account.active`, so a token that survives (direct DB edit) is still rejected |
| Password reset | link request and reset use `is_active = true` credentials: an inactive account gets no mail, the answer equals the unknown-address answer, a reset cannot change the password or reactivate; an active account is unchanged |
| Registration | not affected (creates active accounts; unique e-mail unchanged) |
| Email verification / confirm-password / logout | behind `auth` (+ the web middleware): unreachable for an inactive account |
| Public pages, the Tourvisor webhook (`api` group, own token), provider flows | unchanged; the webhook route has no `account.active` |

An inactive user therefore cannot reach any reactivation endpoint (all are Admin-only and behind the middleware), and the service re-checks the actor anyway. **Known limit:** with the default file session driver server-side session files cannot be deleted; a session that makes no request while the account is inactive is only closed at its next request, so reactivating *within the session lifetime* can revive such a session (its remember-me cookie is already dead).

## 4. Lock contract (`ActiveAdminInvariant`)

Used by deactivation/reactivation and role removal/replacement; `UserDeletion` (AOA-05-I3) uses the same ordering and is unchanged. In one transaction: the **first** statement is a locking read (no subqueries) over **all Admin-role holders + target + actor, ascending id**; the Admin set is re-verified under lock (a changed set rolls back and retries, 3×); all later checks therefore see committed rows of competing writers. Because every such operation takes the same ordered lock set, they serialize and cannot deadlock each other.

## 5. Last active Admin

The system must never end with zero active Admins through an application workflow:

* **Deactivation** — the actor must be an active Admin and cannot be the target, so the actor always remains; the post-change check still rolls back any state with no active Admin.
* **Deletion** (I3) — unchanged: the last active Admin is refused.
* **Role removal / replacement** — `AccountRoleChange::detach|replace` (the only place roles are taken away; a test scans `app/` for `roles()->detach|sync(` and `->removeRole(` call sites) refuses a change after which no active Admin remains. A dual-role account is judged by its actual roles; an **inactive** Admin does not count; unrelated role changes and removal of non-Admin roles behave as before. `AdminController::removeRole` / `updateUserRole` (both route sets) use it; granting a role is unguarded because it cannot reduce Admins. The old `User::removeRole()` helper has no callers.

Verified on a disposable MySQL with real concurrent processes (§8): mutual deactivation, role-removal vs deactivation, mutual role removal/replacement and deactivation vs deletion all leave exactly one active Admin.

## 6. Preserved records and assignments

Verified by tests (SQLite) and on MySQL with real FKs: Bookings, messages, documents, Inquiries (client/assignee links), role links, the old audit events and Booking/Inquiry counts are identical before deactivation, after it and after reactivation. A deactivated Manager keeps `bookings.manager_id`, `inquiries.assigned_to`, `incoming_inquiries.assigned_to` — nothing is auto-reassigned. The existing eligibility rule (`User::assignableToBookings`, active Managers/Admins) already prevents **new** assignments to an inactive employee; `InquiryConversion` already refuses an inactive assignee or client. The Admin user card shows counts of open work (Bookings new/progress/confirmed, independent Inquiries in progress, actionable incoming Inquiries) with the note that handing it over is manual. E5 intake rows and the AOA-05-I1 conversion are unchanged; the AOA-05-I3 deletion guards are untouched and still tested.

## 7. Admin UI

Only `admin/user-show.blade.php` changed: a «Статус учётной записи» card — badge, explanation, open-work note for staff, a reason field (label, `required`, `minlength`/`maxlength`, described by help text) with the Deactivate / Activate button, the self-deactivation note on one's own card and a short «История статуса» (last five events: type, date, acting Admin, reason; no raw JSON). The user list already marked inactive users («Неактивен»). Refusals and field errors use the shared cabinet flash and an inline message.

## 8. Verification

* PHPUnit (SQLite `:memory:`, Xdebug off, `memory_limit=2048M`) on PHP 8.5.12 **and** 8.4.13: **2477 tests / 19037 assertions, 0 failures, 0 errors** (baseline 2437 / 18761; +40 tests / +276 assertions: new `AccountDeactivationTest` (40); `StaffAccessPolicyMatrixTest` classifies the two routes; `UserDeletionGuardTest` and `IncomingInquiryWorkflowTest` inactive-actor tests now first assert the middleware redirect and then run the service-level rule with the middleware bypassed). Mutation checks: removing the middleware or the login credential turns the session/remember/last-Admin and login tests red. PHP 8.3 syntax lint only (8.3.32 not used at runtime). Same single PHPUnit deprecation notice.
* Disposable MySQL 9.7.1 (own datadir, port 3490, loopback only, synthetic credentials, identity verified; never 3306/3308 or the canonical DB), full HTTP kernel in separate OS processes with injected holds: **12/12** — mutual Admin deactivation (second waited ≈4 s, then refused), role removal vs deactivation, mutual role removal and replacement, deactivation vs deletion in both orders, reactivation vs deletion in both orders (no orphan event), concurrent deactivation of one target (one event, second a no-op), audit failure rollback (status, remember token, API token), history preserved on real FKs, full-stack session rejection.
* Chrome 360 / 1200 px, synthetic data, real session cookies in two browser profiles: **35/35 each** — card, reason validation, deactivation, the target's open session bounced to the login page, identical login error for inactive / wrong password / unknown account, public pages still open, reactivation and restored access, Tourist history on the card, own-card note, last-Admin refusals (role change and role removal), no horizontal overflow, no 5xx / console / first-party resource errors. **Limitation:** Chrome did not deliver synthetic `Input.dispatchKeyEvent` keys in this environment (verified with a capturing `keydown` listener), so keyboard operability is checked structurally (DOM focus order textarea → submit, all focusable and visible, no positive `tabindex`, label/description wiring, focused-button activation), not with real Tab/Enter presses.

## 9. Residuals / not done

* **Database is not protected:** direct SQL can still set `is_active`, delete users and remove roles; FKs remain `CASCADE`/`SET NULL` until the FK-contract stage. The Admin invariant is application-level.
* `UserDeletion` re-checks the last-Admin rule under lock but does not re-authorize its *actor* inside the transaction (the route middleware and the new active-account middleware do, at request start) — a narrow window for an Admin deactivated mid-request; harden when deletion is next touched.
* Outbound notifications/e-mail to an inactive recipient (booking, message, reminders) are **not** suppressed — unchanged by this slice (AOA-16).
* File-session limit of §3; no session revocation for file/cookie drivers beyond the middleware.
* Open work of a deactivated employee needs manual Admin reassignment (shown as counts; nothing automatic). No bulk reassignment tool.
* Retention (R-B/U-10), anonymization/erasure and the consent row deleted with a permitted account stay OPEN; avatars and chat attachments are still never cleaned (R-H).
* Self-service «deactivate my account» does not exist (not requested); self-deactivation by an Admin is refused by design.
* Still pending in AOA-05: linked (E5) Inquiry conversion, price/operator data, reversal/reopen, Booking→Inquiry navigation, notifications.
