# NOV-PUBLIC-LEGAL-01 S0 — Closure and canonical documentation sync (2026-11-25)

**Documentation-only reconciliation.** It follows the pushed S0 checkpoint and records, in one place, decisions and evidence supplied by the owner after S0. It does not repeat S0, reopen the MAG.Travel question, restart the legal inventory or start S1. Status of every stage, gate and risk stays authoritative in [`roadmap.md`](roadmap.md); stable IDs of the S0 registers are unchanged. This reconciliation has **not** itself been reviewed, committed or pushed, and no future commit hash is stated.

Related: [decision register](NOV-PUBLIC-LEGAL-01-s0-decision-register-2026-11-24.md) · [retention-rule register](NOV-PUBLIC-LEGAL-01-s0-retention-rule-register-2026-11-24.md) · [RKN registry discrepancy register](NOV-PUBLIC-LEGAL-01-s0-rkn-registry-discrepancy-register-2026-11-24.md) · [legal-evidence prerequisites](NOV-PUBLIC-LEGAL-01-s0-legal-evidence-prerequisites-2026-11-24.md).

No external legal or technical verification was performed. Legal references below are **supplied references, not a fresh legal opinion**; nothing here says any document, process or architecture is legally sufficient, approved, compliant or deployable.

## 1. Evidence legend

| Label | Meaning |
|---|---|
| `VERIFIED_IN_REPO` | Read in repository files (read-only) while writing this document. |
| `OWNER_CONSOLE` | Verbatim console results reported by the owner. Not performed by the documentation author. |
| `OWNER_PROVIDED_EVIDENCE` | Owner statements or owner-supplied facts/paths; not independently verified, not inspected. |
| `PROPOSED` | Design or recommendation, not a decision unless stated. |
| `UNVERIFIED` | Cannot be established from the sources available here. |
| `OPEN` / `NOT_IMPLEMENTED` | Unresolved gate / not present in code. |

## 2. S0 closure and checkpoint (§5.1)

| Item | Value | Evidence |
|---|---|---|
| Branch | `db-rebuild-stage3` | `VERIFIED_IN_REPO` (`.git/HEAD`) |
| S0 documentation checkpoint | **`593d9c71f1d30835f2ac1a6ba5e8f64650548219`** — `docs: establish NOV-PUBLIC-LEGAL-01 S0 legal decisions and evidence foundation`; parent `67e425b5b86824631e16b1839caa0d48b924e168` | local ref read: `VERIFIED_IN_REPO`; commit message, parent and the checks below: `OWNER_CONSOLE` |
| Application checkpoint | `59d9c2299f6428c1bd2c16ff59c79df93570ec71` (unchanged) | recorded in [`roadmap.md`](roadmap.md) |
| Status | **S0 CLOSED AND PUSHED** | `OWNER_CONSOLE` (2026-11-25 00:43:10 UTC+3): `S0_COMMIT_PUSH=PASS`; local = tracking = live origin HEAD; six documentation files, 360 insertions / 1 deletion; six reviewed SHA-256 values matched; staged and committed blobs matched reviewed content; parent and exact scope verified; push without force; staging empty; tracked tree clean; only allowed untracked path `.claude/settings.json` |
| Tests for S0 | None (documentation-only scope) | `OWNER_CONSOLE` |
| Database / VPS | No access | `OWNER_CONSOLE` |
| Project Sources | **Not refreshed**; production **NOT DEPLOYED** | `OWNER_CONSOLE` |

**Historical record preserved:** the original S0 task (2026-11-24) read Git state only through `.git` files and ran no commands; its statements that working-tree state was `UNVERIFIED` and that nothing was committed were true *at that time* and are kept as historical. They are not rewritten into claims that the author ran commands or verified live Git state.

**Stale wording reconciled by this task:** "not committed by S0" (README ledger row; roadmap §A10.O item 4a) and "legal audit not started" (roadmap §A10.O item 2a). Historical sentences are marked as such.

**Quarantined archive:** an unrelated untracked `public/documents/documents.zip` was found during S0 review. The owner moved it into private quarantine (`OWNER_CONSOLE`; size and hash identical before and after): **1074470 bytes, SHA-256 `9753C6A81E78492BE22DBA463C9318C40814306EB392AAABE7C38D5D1BBE85B5`**. Contents were not opened or extracted. It must never return to `public/`, enter Git or enter Project Sources. Its absence from `public/documents/` is `OWNER_CONSOLE`, not checked by the documentation author.

## 3. Test baseline preserved (§5.2)

NOV-PUBLIC-OFFICE-01, owner-console evidence of 2026-11-22 (`OWNER_CONSOLE`, already in [`roadmap.md`](roadmap.md) §A10.F and R-T1):

- Focused, PHP 8.5.12: **89 tests / 1862 assertions**.
- Full PHPUnit on PHP **8.3.36, 8.4.13 and 8.5.12: 2656 tests / 20707 assertions on each version**; 0 failures/errors, exit 0, SQLite `:memory:`, isolation guards PASS.
- Each full run: **one skipped test** — `PublicDocumentExposureTest::test_release_archives_exclude_editable_sources_but_keep_pdfs_and_assets` ("archive listing unavailable (tar/git)"; not exercised) — and **one PHPUnit XML-schema deprecation**.
- Faker apostrophe flake was not observed but is **not proven fixed**.
- Tests ran **before** the application commit; tested-content identity was verified afterwards; **no post-commit rerun**. **Fresh release-package validation remains OPEN**: packaging tests that read Git HEAD in those runs inspected the earlier checkpoint `2bd32df6`. **R-T1 stays tracked.**
- Historical 2646-test baselines (`b7adafda`, `72ae200d`) stay historical and are not merged with the figure above.
- **S0 ran no tests**; this reconciliation ran none either.

## 4. Business model and prebooking (§5.3) — `OWNER_PROVIDED_EVIDENCE`, business direction only

None of this is implemented functionality, legal approval or a statement that any contract, scan, first-payment step or currency rule is legally sufficient.

**Role and services**
- Avilona acts as a **travel agent, not a tour operator**. Services: packaged tours, standalone airline tickets, accommodation, transfers; transfers are performed by **third-party carriers**.
- Customer registration is **optional**. The **manager handles the actual booking**. Tourvisor provides search; the manager verifies the real operator offer.

**Target prebooking workflow (new)**
- Before the **first payment**, Avilona intends to generate its own complete individual travel-product contract, using the applicable travel-agent contract form, from an internal booking application. Avilona is willing to print and sign its own contract first.
- The internal CRM prebooking/application is **not an operator booking**. The customer must properly accept the applicable contract before the first payment. **No operator booking is created** before actual receipt of the first payment and satisfaction of the legal prerequisites; the manager initiates the operator booking manually.
- Operator **rejection**, **changed conditions** and **confirmation** are distinct states. A material change needs a **new document version and renewed agreement**, never retroactive modification.
- If the operator cannot confirm and the customer rejects alternatives, the agreed business direction is **full return of the advance**. An operator cancellation penalty is **not** automatically a lawful deduction from the customer.

**Money**
- First payment usually 20%, a further payment after booking often about 30%, balance by individual operator deadlines — **examples, not fixed rules** (early booking, holidays and operator conditions differ).
- Customer price in **RUB**; currency-linked recalculation rules are **unresolved**.
- Supported methods: cash, internet acquiring, QR payment to the company bank account, office acquiring.
- **Actual payment receipt, operator payment schedules and fiscal receipts are separate concepts.** Cash-register/fiscal compliance is a separate prerequisite (`OPEN`).

**Distinction:** the *former* practice obtained operator documents after a booking was submitted; the target above is a *newly agreed* workflow.
**Current code:** the Inquiry-to-Booking implementation requires an account; **guest prebooking is a future requirement, `NOT_IMPLEMENTED`**.

## 5. CRM document generation and delivery (§5.4)

**Owner requirement:** personalized documents are generated automatically from verified CRM data.

Target capabilities (`NOT_IMPLEMENTED`): approved versioned DOCX templates; generated PDF; versioned input snapshots and template identifiers; immutable document history and PDF hashes; protected storage and authorized access; **separate** generation, review, approval, delivery, acknowledgement, signing/evidence, payment and booking events; any change creates a new version; operator-originated documents are kept as originals and never recreated under the operator's identity.

Two equivalent delivery channels, same revision and conditions: (1) tourist cabinet for registered customers; (2) manager-mediated e-mail/messenger delivery for customers without an account.

Current practice: Avilona signs a printed document first; the manager sends a scan; the tourist returns a signed scan; paper originals by arrangement.

**Signature:** Avilona holds a Russian enhanced qualified electronic signature (УКЭП/UKEP), but **routine UKEP use is not an accepted operational requirement**. (The earlier wording "UK qualified electronic signature" in OC-10 was wrong and is corrected in the decision register.) A scan, click, acknowledgement or payment is **not** equated with a legally sufficient signature. No signing keys on the shared VPS; no signing service; no claim of electronic-contract compliance.

Dependencies (not started, not renumbered): AOA-05 (active), AOA-06, AOA-07, pricing/payment stages, AOA-12 and AOA-13; owner decision U-6 (approved templates and authority) remains open.

## 6. Legal-document preparation results (§5.5) — WORKING REVISIONS, not approved

Prepared **outside Git** in the owner's private workspace `C:\Avilona_private\Legal\For_Approval` (working directory `Revision_1_1`). **Not accessed** by the documentation author; presence of the latest copies on the owner's machine is `UNVERIFIED`. Each name below has a DOCX/PDF working pair:

| Working basename | Future runtime mapping (**not authorized now**) |
|---|---|
| `Avilona_privacy_v1_1` | `public/documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf` |
| `Avilona_agreement_v1_1` | `public/documents/User_Agreement.pdf` |
| `Avilona_consent_v1_1` | `/personal-data-consent`, synchronized Blade content, retained document master |
| `Avilona_Tour_Terms_v1_1` | `public/documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf` |
| `Avilona_Separate_Services_v1_2` | `public/documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf` |
| `Avilona_memo_v1_1` | `public/documents/Reminder_for_those_traveling_abroad_general.pdf` |
| `Avilona_contract_v1_1` | **private** individual CRM contract template — never a public customer document |

- Packages: `Avilona_Legal_Revision_1_1.zip`, `Avilona_Legal_Update_2026-11-24.zip`. The updated Tour Terms and Separate Services revisions **supersede** the older `Tour_Booking_Terms` and `tickets_v1_1` working versions; previous revisions are preserved privately.
- The five existing runtime PDF filenames are kept for existing URLs and packaging compatibility.
- Visible labels of the two informational service documents must eventually stop describing them as public offers. `contacts` and `company/about_company` are known relevant views; exact scope is to be verified in a later authorized task.
- The individual contract = main agreement + three appendices. Latest reported template verification: **77 unique placeholders / 105 occurrences**; an earlier passport says **78 unique**. **Discrepancy preserved for future reconciliation**, not independently verified here.
- Still required before any use: reconciliation against the applicable contract form, tourist rights, operator authority and financial guarantees, direct/subagent relationships, payment rules and signature requirements.
- **Nothing here is legally approved, installed, published or deployable.** R-L1, N-4 and the G7 legal-document part stay `OPEN`.

## 7. Registry facts and operator relationships (§5.6)

`OWNER_PROVIDED_EVIDENCE`: travel-agency registry extract **No. 776123 dated 2026-11-24**; travel-agent registration **РТА 0013440**; reported 19 pages, **26 direct-contract** entries and **25 subagency-contract** entries. These are **relationship records, not necessarily 51 unique tour operators**. Cryptographic signature verification of the extract was **not** established. Many reported relationships expire on **2026-12-31**. Reported anomalies to reconcile (not to be silently corrected): AMБ ГРУПП 2389; ГОРТРЕВЕЛ 2124; ГОСТЬ ТУР 2911–2912. **Do not guess which records are currently valid.** No real-data import is authorized.

Future CRM directory (`PROPOSED`, `NOT_IMPLEMENTED`) must distinguish: legal entities; tour operators and registry identifiers; agents/subagents; relationship chains; contract dates and scope; authority evidence; expiry dates; source evidence and review status.

Company addresses and identifiers are unchanged (see [`README.md`](README.md) §9A).

## 8. Personal-data organizational requirements (§5.7)

- **Roskomnadzor registration 78-20-007148** exists (owner-confirmed); **current accuracy of the entry is not established**. **ORG-RKN-01 concerns amendment of the existing notification, not a new registration**; it is separate from code, proceeds independently of full-CRM completion and **without unjustified delay**. No notification submitted, no authority contacted, no unverified notification data drafted.
- **Timing:** the statutory time for notifying changes needs **prompt primary-source verification**, including the previously identified **Article 22(7) of Federal Law No. 152-FZ** (a supplied reference, not verified here). No deadline, legal conclusion or amendment is stated; known discrepancies must not be postponed indefinitely until unrelated CRM work or every possible future processing detail is settled.
- **MAG.Travel:** NF-01, NF-02, NF-03 remain `OWNER_CONFIRMED`; FQ-2, RKN-11 and P-2 remain **CLOSED** on owner-provided evidence; no further contact is required under the owner's decision. No independent provider evidence, destruction certificate or technical verification was obtained. It is not reopened.
- **Still OPEN:** RKN-02, R-LG3 and the registry-amendment task.
- **Responsible person:** the **general director is the recommended** responsible person; **appointment is not confirmed** (NF-04/05/06, DR-17 unchanged). The general director is not responsible for programming or server administration.
- **Future organizational deliverables:** formal appointment order; responsibilities and internal instructions; staff acknowledgement records; processing/access rules; retention and destruction procedure; destruction acts and event-log extracts; data-subject request handling; incident-response procedure; authorized-access records. Generation may use future CRM automation where appropriate.
- **Retention:** the 12-month maximum applies **only** to non-contract inquiries after the last substantive interaction (never inferred from submission), with earlier destruction where legally required and lawful exceptions separately justified. Consent evidence, contracts, accounting and backup retention remain **separately unresolved**. Order No. 179 acts and event-log extracts, including the reported **three-year keeping**, still need primary-source verification. No deletion, cleanup, mailbox access or scheduled destruction is authorized.

## 9. Qui-Quo — future integration only (§5.8)

`OWNER_PROVIDED_EVIDENCE`: the owner contacted Qui-Quo support and received three technical specifications — `custom-crm-requirements.docx` (selection/import protocol); `Требования к CRM - Быстрая Заявка (1).docx` (operator-booking import: customer/traveller details, services, prices, operator payment schedules); `QQ-QR-readme.docx` (reverse transfer from CRM into operator booking forms through the browser extension). Source package: `22-11-2026_15-50-20.zip`. The existing Maximum plan costs **790 RUB/month** per the owner; the existing subscription continues; **no new paid service is approved**. These files were not read or inspected here.

- Qui-Quo is a **manager-operated browser-extension workflow, not a universal direct tour-operator API**.
- Proposed architecture: inbound selection/booking data enters a **controlled review buffer**, is authenticated, validated, normalized, deduplicated and explicitly reviewed before association with internal entities. Relevant: AOA-06 OperatorBooking, AOA-07 import snapshots.
- **Unresolved:** authentication, origin verification, replay protection, idempotency, payload versioning, privacy, supported operators, delivery/error contracts, authorization of reverse transfer.
- Operator `paymentSchedule` / `payments` / `paidStatus` data **must not establish actual customer payment receipt**. Reverse transfer of passport and traveller data needs a separate authorization and security review.
- **No Qui-Quo endpoint, callback, URL configuration, code or live test is authorized**; do not send another initial support request.
- Sixteen historical contract/document samples were reviewed earlier; obsolete templates, duplicate contracts, broad historical personal-data consents and the old 50% preliminary-payment form are **not** carried forward as-is.
- Qui-Quo is **not required for the public-only release**.

## 10. Technical program after S0 (§5.9)

Accepted planning basis: Architecture Revision 2 with S0 corrections. Sequence unchanged (full details in the [prerequisites document](NOV-PUBLIC-LEGAL-01-s0-legal-evidence-prerequisites-2026-11-24.md) §7): S1 legal-document archive and registry · S2 form session/cache/log privacy hardening · S3 signed form context and interim mail correlation · S4 dedicated minimal receipt persistence and delivery-state handling · S5 operator-confirmed destruction and statutory evidence · S6 organizational runbooks · S7 free pinned local static assets and licence preservation · S8 Tourvisor runtime assessment and owner decision · S9 optional form hygiene.

- **S1 is the next technical slice** only after this canonical documentation closure **and** a verified Project Sources refresh. **All S1–S9 slices remain individually unauthorized**; this task implements nothing.
- **"legacy-as-published"** means a legacy *source artifact archived as-is*. It does **not** prove deployment of the rebuilt application and is **not** legal approval.
- Boundary preserved: the archive holds **public legal text only**, exact content bytes, SHA-256, version linkage and owner-held hash pins; Git/manifest tests are tamper evidence, not absolute immutability; **no customer personal data is stored in the legal-text archive**. DR-08 version/approval decisions are **not** resolved by being documented.
- Unchanged: mail recipients, separate consent fields, CAPTCHA, throttling, public-only routing, cookie separation, current application behavior. Retained: minimal receipt design with attribution limits, unresolved retention, conservative SMTP outcome model, rollback protection of populated evidence tables.

## 11. Project status and release blockers (§5.10) — unchanged

- **CLOSED:** Stage 0–13, E1–E4; E5 (application); AOA-01…04; NOV-PUBLIC-01/02/03A/03A-F1/04A/04B/L1/L3/OFFICE-01. **ACTIVE:** E5.5; AOA-05. **Unstarted:** AOA-06…18. **PENDING:** E6. **Not implemented:** L2. P0: owner-reported READY.
- **OPEN:** G7 legal-document part, R-L1/N-4, R-LG1…R-LG5, R-V2/V3/V4/V5/V6, G6 SMTP, R-B/U-10. **BK-4A: OPEN / BLOCKED / TEMPORARILY PAUSED**; P5/P7/P8 and BK4A-D1/D2 not reopened; R2 and B1–B4 open; the previously authorized B1/B2 preflight attempt is exhausted; **no new infrastructure operation is authorized**.
- November public-only launch and 2027-01-31 full-project completion are **owner targets, not guaranteed dates**. **Production NOT DEPLOYED / NO-GO.** Owner acceptance of technical risk does not waive statutory requirements.

## 12. Permanent documentation and Sources transition workflow (§5.11) — `OWNER_PROVIDED_EVIDENCE`

After each major work block:

1. Record actual changes, verified tests, owner decisions, open risks, residual and deferred work.
2. Reconcile canonical documentation in a separately scoped documentation task.
3. The owner runs complete guarded PowerShell blocks for scope, content, hashes, tests if required, commit and push.
4. After a successful scoped review, ordinary guarded commit/push needs no further separate owner confirmation.
5. Never force-push, `git add .`, destructive rollback or unapproved scope expansion.
6. Refresh Project Sources only from a confirmed, clean, committed and pushed documentation checkpoint.
7. First inspect the **actual** active mirror and current helper/template configuration; do not assume historical wrappers are current.
8. Build the compact source archive from tracked **committed** Git blobs, never WIP.
9. Exact five-file set: source ZIP; `Handoff.md`; `Roadmap.md`; `New_Chat_Prompt.md`; CSV manifest with four data rows.
10. Verify archive integrity, manifest sizes and SHA-256, required canonical documentation, expected membership, and exclusion of secrets, real `.env`, `vendor`, `node_modules`, `.claude`, dumps, customer data, private Word/PDF files and binary media.
11. Archive the previous set before guarded promotion of the verified new set.
12. The owner replaces the active ChatGPT Project Sources and confirms the upload.
13. Generate a self-contained new-chat prompt: actual checkpoint, exact next task, evidence, restrictions, remaining gates, model/mode/effort guidance.
14. Retire or mark transition supplements historical only after their information is preserved in canonical documentation and the verified new set.

Owner-supplied paths (not inspected, not executed, not modified): helper `C:\Avilona_private\Create-Avilona-ChatGPT-SourceArchive.ps1`; active mirror `C:\Avilona_private\ChatGPT_sources`; historical wrapper `C:\Avilona_private\Avilona_B52_Project_Sources_Refresh_v2`.

Communication workflow: owner-facing discussion in Russian; Claude Code tasks and technical reports in English; explicit new/current/no-Claude chat status; model, mode and effort recommendations outside the task prompt; manual approval for edits; Plan mode for read-only planning where appropriate; Sonnet for standard tasks, costlier models only when justified; no automatic approval or shell execution by Claude; complete copyable PowerShell blocks for owner-side operations.

## 13. What this reconciliation did not do

No S1–S9 work; no command, test, Git write, database/VPS access or Sources refresh; no access to private legal files, quarantine or helper paths; no change to application code, configuration, routes, migrations, tests, PDFs, Word files, assets or `.claude`; no change to any gate status; no regulatory submission.
