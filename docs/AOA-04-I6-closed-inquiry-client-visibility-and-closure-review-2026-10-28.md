# AOA-04-I6 — Closed-Inquiry client visibility and AOA-04 development closure review

Date: 2026-10-28. Base commit: `072bf15457261f5ae074ca3ac9fbfced52583c12`. Final slice of AOA-04 (roadmap §A4/§A6), after I1–I5. No real data, no production, no deployment, no migration.

## 1. Result

- **Part A (owner-approved policy):** a *closed* independent Inquiry no longer grants its responsible Manager access to the linked Tourist profile. An *open* one still does. Every other entitlement source is unchanged.
- **Part B (closure review):** every mandatory AOA-04 requirement has verified implementation (matrix §6). One literal §A4 gap found by the review — "Admin-created → assigned to Admin **by default**" — was closed in this slice (§5). **AOA-04 is CLOSED as a development stage.** Production residuals stay open (§8). Nothing is deployed.

## 2. Policy change (single place)

`ClientVisibility::scopeTo()` — the SQL rule used by «Мои клиенты», the Booking client selector and validation, the Inquiry client search/select and the verification service — gains one predicate on the *independent-Inquiry* branch: `inquiries.status != 'closed'`. Nothing else in the rule changed.

| Source of Manager → client access | After I6 |
|---|---|
| Booking where the Manager is responsible | unchanged |
| Legacy `IncomingInquiry` assigned to the Manager (open **or** closed) | unchanged (E5 authority) |
| Independent `Inquiry`, assigned to the Manager, **open** | unchanged |
| Independent `Inquiry`, assigned to the Manager, **closed** | **no longer grants** |
| Linked (`external_intake`) `Inquiry` | still grants nothing (unchanged) |
| Approved verification window, until `access_expires_at` | unchanged; an expired window stays expired |
| Admin | unchanged (whole directory) |
| Other roles | unchanged (none) |

Notes: the predicate is "not closed", not "is in progress", so the only status it removes is the one the owner named; `new`/`converted` (not reachable for independent Inquiries today) keep the pre-I6 behaviour and are for AOA-05 to decide. Reassignment still revokes the former assignee immediately (I2); a closed Inquiry cannot be reassigned (I5), so a closed record never re-grants.

## 3. History versus profile access

Closing deletes nothing: `client_user_id`, `client_linked_*`, contact rows, draft, creator, assignee, audit and provenance are untouched (tested). The Inquiry *card* stays readable under the unchanged `InquiryPolicy` / `InquiryAuthority` rules.

On the card (`AgencyInquiryController::clientState`) the linked account is now loaded **through** `ClientVisibility`:

- visible to the viewer (open Inquiry, own Booking, live window, Admin) → name shown as before;
- not visible (closed-only Manager) → «Аккаунт привязан (профиль недоступен)» in the details list and a neutral «профиль клиента вам недоступен» notice instead of the client block. No account name, e-mail, phone, profile link, search/link/unlink forms. Admin card unchanged (name + «карточка клиента» link).
- The open-card notice now says the access ends when the Inquiry is closed.

## 4. Other changes

- Two older tests asserted the pre-I6 behaviour and were deliberately rewritten: `InquiryClosureTest::test_client_visibility_ends_on_closure_and_follows_assignment` (was «…is_unchanged…») and `InquiryAwareAccessTest::test_closed_independent_inquiry_grants_no_client_while_converted_keeps_the_assignee_rule`.
- Two pre-existing assertions compared the raw faker name with escaped HTML and failed intermittently (~1.5 % of names contain an apostrophe, e.g. `O'Reilly`): `AdminInquiryCreationAndFiltersTest` and `ManualInquiryCreationTest` now compare `e($name)`. Observed once on PHP 8.5.12 during this slice; not related to the visibility change.

## 5. Admin-created Inquiry default (§A4 literal gap)

§A4: *Admin-created → assigned to Admin by default, or explicitly to another eligible employee chosen by Admin.* The I3 form required an explicit choice and preselected nothing. I6 preselects the creating Admin in the selector (`defaultAssigneeId`); the server contract is unchanged (an explicit `assignee_id` is still required and re-validated under lock; a blank choice is rejected). Verified by PHPUnit only (HTML assertion), not re-run in a browser.

## 6. AOA-04 requirement matrix

Canonical definition (roadmap §A6): *manual Inquiry creation, ownership, responsibility & assignment per §A4, Admin allocation, reassignment with reason + history.* §A4 rules and the A3 visibility rules are the owner decisions.

| # | Requirement | Evidence | Tests | Status |
|---|---|---|---|---|
| 1 | Manager-created → automatically assigned to the creator, no Admin approval | I1 `ManualInquiryCreator::createForManager`, `inquiry.created` audit, retry-safe token | `ManualInquiryCreationTest` | ✅ |
| 2 | Admin-created → Admin by default or an explicitly chosen eligible employee | I3 `createForAdmin` (`User::assignableToBookings()`, re-checked under lock); **default preselected in I6** | `AdminInquiryCreationAndFiltersTest` (+1 in I6) | ✅ |
| 3 | External/automatic → unassigned until Admin allocates; Manager may not claim | E5 legacy path (AOA-01-I2A); independent Inquiry: Admin-only (re)assignment, Manager self-claim refused (I2) | `IncomingInquiryAccessAllocationTest`, `InquiryAssignmentTest` | ✅ |
| 4 | Reassignment only by Admin; required reason; previous/new/actor/timestamp recorded; history kept for Admin; ordinary Manager cannot reassign | I2 `InquiryAssignment` (locked transaction, `inquiry.assigned`/`reassigned`, Admin-only history) | `InquiryAssignmentTest` | ✅ |
| 5 | Former assignee loses access (record **and** client) immediately | I2 + `ClientVisibility`; I6 adds closed-record rule | `InquiryAssignmentTest`, `InquiryClosedClientVisibilityTest` | ✅ |
| 6 | Manager sees only own inquiries; no leak via list/detail/search/JSON/counts | `InquiryPolicy`/`InquiryAuthority`; scoped list; counters over the same scope (I5) | `InquiryAwareAccessTest`, `InquiryWorkloadSummaryTest`, `InquiryClosureTest` | ✅ |
| 7 | Contact and draft durability | I1 revisioned draft; I4 contact with tombstone revisioning | `InquiryDraftStoreTest`, `InquiryContactCaptureTest` | ✅ |
| 8 | Client verification and association without account-existence leak (owner Option C) | I4 `inquiry` context of `ClientVerificationService`, `InquiryClientAssociation` (explicit link, Admin create/unlink) | `InquiryVerificationContextTest`, `InquiryClientAssociationTest` | ✅ |
| 9 | Status / closure workflow | I5 `InquiryClosure` (`in_progress → closed`, terminal, required reason, `inquiry.closed`) | `InquiryClosureTest` | ✅ |
| 10 | Scoped lists and counters | I3 filters + pagination; I5 «Всего / В работе / Закрыто» | `AdminInquiryCreationAndFiltersTest`, `InquiryClosureTest` | ✅ |
| 11 | Owner decision: closed Inquiry does not keep client-profile access | **I6** | `InquiryClosedClientVisibilityTest` (15) | ✅ |

## 7. Remaining work, classified

| Item | Class | Why it is not an AOA-04 requirement |
|---|---|---|
| Inquiry → Booking conversion | **B** — AOA-05 | Separate slice in §A6 |
| Notifications to assignees | **B** — AOA-16 (R-L) | Separate slice; mail identity U-8 undecided |
| Search in the Inquiry list | **C** — deferred | Not in §A4/§A6 for AOA-04; no owner request; A3 only forbids *leaking* through search |
| Reopen a closed Inquiry | **C** — deferred | Closure is terminal by the I5 contract; needs an owner decision |
| Closing a linked (external) Inquiry | **C** — deferred | E5 stays authoritative until a cutover that the clean-start decision does not require |
| Assignee filter for formerly-eligible staff | **C** — deferred | Records stay reachable via the unfiltered list / «Не назначен» |
| Retention R-B / U-10 (new plain-PII stores) | **D** — pre-production | `BUSINESS_DECISION_REQUIRED` |
| R-D owner confirmation | **D** — owner | Technically addressed, **not** owner-confirmed |
| E6 security / compatibility / data-migration / backup gates; I5 B1/B2 | **D** | Unchanged; canonical DB neither inspected nor claimed empty |

No class **A** item (mandatory AOA-04 requirement still missing) remains.

## 8. Residuals (unchanged by this slice)

R-B/U-10 retention OPEN. R-D «technically addressed, owner confirmation pending» — the owner's I6 decision covers client-profile visibility after closure only and is **not** acceptance of R-D or of any other residual. R-L OPEN. B1/B2 stay relevant only if an active-source migration is introduced. Canonical MySQL `turfirma_rebuild_v4` not accessed; no migration added; E5 migrations still not applied there. Laravel production NOT deployed; E5.5 stays ACTIVE; E6 PENDING.

## 9. Verification

- **PHPUnit** (SQLite `:memory:`, Xdebug off, `memory_limit=4G`): **PHP 8.5.12 and PHP 8.4.13 — 2347 tests / 18081 assertions, 0 failures, 0 errors** (+16 tests / +85 assertions over 2331 / 17996: new `InquiryClosedClientVisibilityTest` (15) and one Admin-default test; two tests rewritten, two assertions made escape-safe). 1 deprecation: the known `phpunit.xml` schema notice. PHP 8.3.32 not used for runtime tests.
- **Disposable MySQL 9.7.1** (fresh datadir, loopback `3460`, synthetic credentials, identity from `@@port/@@datadir/@@bind_address`; 3306/3308 and `turfirma_rebuild_v4` untouched; only the verified instance stopped by PID/command line): 23 checks — open/closed, real `InquiryClosure` and `InquiryAssignment` services, Booking / live window / expired window / legacy / Admin combinations, exact list set, search, uncommitted close invisible to a second connection and revoked after commit, SQL carries the guard. **Negative control** (old `ClientVisibility`): 10 of those checks fail.
- **Browser** (headless Chrome, synthetic users, MySQL above): 360 px and 1200 px, 35/35 each — Manager list contents, closed-only card (neutral label, no name/link/forms), Booking entitlement keeps the name, live closure removes the client from «Мои клиенты», Admin oversight, reassignment (former 404, new gains), foreign Manager 404, Tourist 403, guest redirect, no horizontal overflow, a11y basics, no console errors, no 5xx, no first-party asset failures.
- **I5 header-overflow signal:** *not reproduced* — 48 controlled loads (24 per width: list, closed card, open card, «Мои клиенты» × 6 runs, alternating settle time) showed no horizontal overflow or off-screen element. The cause of the single I5 occurrence remains undetermined; it is neither fixed nor claimed fixed.

## 10. Next

AOA-05 — explicit Inquiry → Booking conversion on the new `Inquiry` entity (E5 atomicity/idempotency kept). It must decide the client-visibility and status semantics of a `converted` Inquiry (see §2 note) and must not revive access through a closed Inquiry.
