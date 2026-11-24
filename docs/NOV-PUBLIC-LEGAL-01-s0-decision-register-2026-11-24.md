# NOV-PUBLIC-LEGAL-01 S0 — Decision register (2026-11-24)

**Documentation-only checkpoint (S0).** No application, configuration, route, migration, dependency, test or legal-PDF change; no database, VPS, SMTP, mailbox or secret access; no regulatory submission. Status of every stage, gate and risk is authoritative in [`roadmap.md`](roadmap.md); this file keeps the detail. Companion files: [retention-rule register](NOV-PUBLIC-LEGAL-01-s0-retention-rule-register-2026-11-24.md), [Roskomnadzor registry discrepancy register](NOV-PUBLIC-LEGAL-01-s0-rkn-registry-discrepancy-register-2026-11-24.md), [legal-evidence prerequisites](NOV-PUBLIC-LEGAL-01-s0-legal-evidence-prerequisites-2026-11-24.md).

Nothing in this file is a legal opinion. No statement here says that any text, process or architecture is legally sufficient, approved or compliant.

> **Update 2026-11-25:** S0 is **CLOSED AND PUSHED** at documentation checkpoint `593d9c71f1d30835f2ac1a6ba5e8f64650548219` (owner-console evidence). §2 below is the **historical** record of the original S0 task, which read Git state only from `.git` files and ran no commands; it is not rewritten. Later owner decisions and evidence (business model and prebooking, document generation, working legal revisions, registry extract, Qui-Quo, Sources workflow) are in [closure and transition](NOV-PUBLIC-LEGAL-01-s0-closure-and-transition-2026-11-25.md). Decision statuses and IDs here are unchanged.

## 1. Evidence legend

| Label | Meaning |
|---|---|
| `VERIFIED_IN_REPO` | Seen in repository files (read-only) at the time of writing. |
| `OWNER_PROVIDED_EVIDENCE` | Stated or supplied by the owner (including owner-run console results and registry facts). **Not independently verified** here. |
| `PROPOSED` | A design or recommendation. Not a decision unless a row says `OWNER_CONFIRMED`. |
| `UNVERIFIED` | Cannot be established from the repository or the sources available to this task. |

Decision status values: `OWNER_CONFIRMED` (decided by the owner) · `RECOMMENDATION` (proposed, awaiting owner/counsel) · `UNRESOLVED` (no proposal accepted, or facts missing).

## 2. Repository baseline and guard limits

| Item | Value | Evidence |
|---|---|---|
| Branch | `db-rebuild-stage3` | `VERIFIED_IN_REPO` (`.git/HEAD` read) |
| HEAD | `67e425b5b86824631e16b1839caa0d48b924e168` | `VERIFIED_IN_REPO` (`.git/refs/heads/db-rebuild-stage3` read) |
| Application checkpoint | `59d9c2299f6428c1bd2c16ff59c79df93570ec71` (`fix: unify public office operating information`) | recorded in [`roadmap.md`](roadmap.md) §A10.O |
| Working-tree cleanliness, staging area, tracking/origin alignment | Owner-console check: local, tracking and live origin HEAD all `67e425b5b86824631e16b1839caa0d48b924e168`; staging empty; the six expected S0 documentation changes; an unrelated untracked `public/documents/documents.zip` was found and the exact-scope guard stopped correctly (the owner handles it separately; it was not read or touched by S0). The S0 task itself ran no Git command | `OWNER_PROVIDED_EVIDENCE` |
| Tests | **2656 tests / 20707 assertions per PHP version on 8.3.36, 8.4.13, 8.5.12**, 0 failures/errors, 1 skipped packaging-related test per run, 1 PHPUnit XML-schema deprecation per run, SQLite `:memory:` isolation | `OWNER_PROVIDED_EVIDENCE`; already recorded in [`roadmap.md`](roadmap.md) §A10.F and R-T1 |
| Historical results | 2646 / 20559 (`b7adafda`) and 2646 / 20567 (`72ae200d`) remain **historical** and are not merged with the figure above | `VERIFIED_IN_REPO` |
| New test results | **None.** S0 ran no PHPUnit and no other test. | — |

A grep of `tests/` found `docs/` only inside comments and one packaging fixture string; no test asserts documentation text, so a documentation-only change needs no test run.

## 3. Owner-confirmed decisions and facts (recorded, not re-decided)

### 3.1 Business decisions confirmed for this workstream (`OWNER_PROVIDED_EVIDENCE`, status `OWNER_CONFIRMED`)

| ID | Decision |
|---|---|
| OC-01 | Public website visitors can submit inquiries **without registration**. |
| OC-02 | Internal inquiry recipient stays `straus97@mail.ru`. |
| OC-03 | Public company e-mail stays `avilonatur@bk.ru`. |
| OC-04 | Personal-data consent stays **separate** from acceptance of the User Agreement. |
| OC-05 | Inquiries that do not result in a contract: maximum **12 months after the last substantive interaction**, with earlier destruction when legally required (purpose ended, etc.) and independently justified lawful retention exceptions. |
| OC-06 | Contract-related records, accounting records, consent evidence and other categories need **separate** retention rules; **no period is invented** here. |
| OC-07 | All future CRM documents must support automated generation, versioning and protected storage. |
| OC-08 | The November public-only release must not depend on completion of the full CRM. |
| OC-09 | No paid services or new paid dependencies. |
| OC-10 | Routine use of Avilona's Russian enhanced qualified electronic signature (УКЭП/UKEP) is **not** an accepted operational requirement for the contract-signing workflow (*corrected 2026-11-25: the earlier wording "UK qualified electronic signature" was erroneous*). Scanned signatures are **not** automatically deemed legally sufficient. |

### 3.2 Owner-confirmed facts added 2026-11-24 (`OWNER_PROVIDED_EVIDENCE`)

| ID | Fact | Status |
|---|---|---|
| NF-01 | Avilona has **completely discontinued MAG.Travel (Master Agent)**. | `OWNER_CONFIRMED` |
| NF-02 | No historical Avilona tourist personal data remains with MAG.Travel. | `OWNER_CONFIRMED` — **CLOSED** on the owner's statement under their responsibility; not independently verified; no destruction certificate, provider confirmation or technical verification was obtained |
| NF-03 | All business and data-processing arrangements with MAG.Travel have ended; no further contact with MAG.Travel is required. | `OWNER_CONFIRMED` — **CLOSED** on the same basis (owner-confirmed, not independently verified provider evidence) |
| NF-04 | The current formally appointed person responsible for organizing personal data processing. | **UNKNOWN** |
| NF-05 | Existence of a current appointment order. | `UNVERIFIED` |
| NF-06 | Appointing the **general director** as the responsible person is the *recommended organizational solution*. It is **not** an owner-confirmed appointment. | `RECOMMENDATION` |
| NF-07 | Avilona arranges **standalone airline tickets, accommodation and transfers** (besides tour packages). | `OWNER_CONFIRMED` |
| NF-08 | Transfers are performed by **third-party carriers**, not by Avilona's own transport. | `OWNER_CONFIRMED` |
| NF-09 | Travel-agency registry number **РТА 0013440**. | `OWNER_CONFIRMED` |
| NF-10 | Roskomnadzor personal-data operator registration number **78-20-007148**. | `OWNER_CONFIRMED` (registration); current accuracy of the entry is **not** confirmed |

Preserved without change: legal address `198302, г. Санкт-Петербург, вн. тер. г. муниципальный округ Красненькая речка, ул. Морской Пехоты, д. 10, к. 1, литера А, кв. 22` (ЕГРЮЛ-based, shown only as «Юридический адрес»; registry-extract confirmation dated 24.11.2026 is `OWNER_PROVIDED_EVIDENCE`); customer-service office `198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10`, visits by prior arrangement; ООО «Авилона», ИНН 7805502454, КПП 780501001, ОГРН 1097847289803; public e-mail and internal recipient as above; payment practices and the document-automation requirement (OC-07) as recorded in [`README.md`](README.md) §9A and [`roadmap.md`](roadmap.md) §A10.L. NF-07/NF-08 are inputs to the legal-document revision (R-L1/N-4) and to the registry review (RKN-04/05); they change no public copy in S0.

## 4. Decision register DR-01 … DR-18

IDs are stable: they are never renumbered or reused. Each row separates what is confirmed, what is recommended and what is unresolved.

**Mandatory-law rule (applies to every row).** Owner acceptance of a *technical* or *project* risk does **not** waive statutory obligations. Written owner acceptance is **not** a substitute for legally required consent evidence, data-localization evidence, Roskomnadzor notification accuracy, or destruction procedures. Unresolved mandatory compliance conditions stay OPEN. (The G7 wording "or explicitly accepted in writing by the owner" in [`NOV-PUBLIC-01`](NOV-PUBLIC-01-november-public-release-readiness-2026-10-31.md) §13 is a *project-gate* convention for the legal-PDF and opening-hours item; it is not a legal waiver and is not extended to the items below.)

| ID | Decision needed | Confirmed (owner) | Recommendation (`PROPOSED`) | Unresolved |
|---|---|---|---|---|
| DR-01 | How consent for anonymous public inquiries is evidenced in the public release | OC-01, OC-04 | Dedicated minimal receipt (one row per submission, no name/e-mail/message/IP/user-agent/captcha) as the **target**; **mail-only evidence is an interim development stage, not an approved production solution** | Whether and when the receipt must exist before go-live is a **mandatory compliance condition** for counsel to define; it cannot be closed by owner acceptance of a gap |
| DR-02 | How a receipt stays attributable to the data subject | — | L1 correlation via the mail thread; L2 the destruction act lists the receipt reference next to the minimum subject identification; **L3 keyed e-mail identifier is not in the baseline** and needs a documented purpose, retention rule and access model first. A UUID alone does not prove who consented | Choice of L1/L2/L3; counsel view on listing a reference in the act |
| DR-03 | Definition of "substantive interaction" and "purpose ended" for non-contract inquiries | OC-05 (rule exists) | Define in writing which events count (staff reply, visitor follow-up, quotation, negotiation) and which do not (automatic acknowledgement, spam); the clock is never inferred from the submission timestamp | The definition itself |
| DR-04 | Receipt lifetime relative to the mail thread; **retention period for consent evidence** | OC-06 (separate rule, none invented) | Evaluate: destroy with the thread / keep longer under L2 / keep longer under L3 | Period and basis — `NOT_DECIDED`; 12 months is **not** assigned to it |
| DR-05 | Mailbox: keep the consumer mailbox `straus97@mail.ru` as the inquiry store, or move to a company-domain mailbox; provider meaning of "delete" | OC-02 (recipient preserved) | Decide before go-live whether a consumer mailbox can be a controlled store; confirm provider deletion semantics | Decision and provider facts |
| DR-06 | Behaviour if the evidence record cannot be written (fail closed vs accept without evidence) | — | Fail closed with the existing generic visitor error | Owner decision; accepting inquiries **without** mandatory evidence is not a pure owner-risk choice (mandatory-law rule) |
| DR-07 | Backup retention facts; restore re-application of destruction; acceptability of erasure-by-expiry | — | After any restore, re-apply the destruction register before serving traffic | Backup retention (R-V2), counsel view on erasure-by-expiry |
| DR-08 | Source of truth for legal texts; version label scheme; archive location | OC-04 | Immutable archive of **public legal text only** with SHA-256 per version (see prerequisites file §2) | Label scheme; approval references |
| DR-09 | Treatment of acceptances that predate the registry | — | Production has never been deployed; earlier text is recoverable only from Git history (best effort) | What the former REG.RU-hosted site collected and kept (`UNVERIFIED`) |
| DR-10 | Destruction act: responsible signatory/commission, paper vs electronic form, template approval, protected storage location | OC-10 | Wet-signed paper act unless counsel says otherwise; scan = convenience copy | All items; Order No. 179 form requirements need primary-source verification |
| DR-11 | Ownership and scheduling of **ORG-RKN-01** (registry entry review/amendment) | — | Must proceed **independently of full-CRM completion and without unjustified delay**; first step is fact-gathering (see RKN register §5), not drafting a notification | Owner of the task, target date, counsel involvement |
| DR-12 | Tourvisor module on `/tours`: keep + disclose / click-to-load / other; Policy addendum | — | Assess separately from analytics and outbound links; no change in S0 | Provider role, retention, localization (`PRIVACY_LEGAL_FACT_REQUIRED`, N-6) |
| DR-13 | Approval to vendor free third-party static assets later; licence-notice handling | OC-09 | Free/local assets preferred; exact versions, licences, visual parity; **no download in S0** | Approval and the later download step |
| DR-14 | Cookie-page wording about remaining third-party hosts | — | Disclose or localize the unconditional CDN/font hosts | Wording (counsel); runtime facts |
| DR-15 | Keep or remove the visitor acknowledgement mail | — | Keep bounded behaviour until decided (N-7) | Owner decision |
| DR-16 | Acceptance of the "unverified e-mail address" limit (no double opt-in) | — | Document the limit; a receipt proves a session ticked the boxes, not that the owner of the typed address consented | Counsel/owner decision |
| DR-17 | The person responsible for organizing personal-data processing (and its appointment order) | NF-04: currently **UNKNOWN**; NF-05 order **UNVERIFIED** | **Recommendation only (NF-06):** appoint the general director, subject to formal company confirmation (order/decision) | The appointment itself; whether any order exists |
| DR-18 | Visitor wording for an *uncertain* mail-delivery outcome; expiry value of the form context | — | Conservative: never promise exactly-once delivery; no blind automatic retry | Texts and TTL value |

## 5. Public-release blockers vs future CRM tasks

Classification is `PROPOSED` and subject to counsel; it does not change any gate status in [`roadmap.md`](roadmap.md).

| Item | Class | Note |
|---|---|---|
| Approved legal documents and consent text (G7 legal-PDF part, R-L1, N-4) | **Public blocker — OPEN** | PDFs not replaced in S0 |
| Consent evidence for anonymous inquiries (DR-01/02) | **Public blocker — OPEN (mandatory condition)** | mail-only stays interim; not closable by owner acceptance |
| Retention/destruction procedure for public inquiries (RR-01, RR-06) incl. statutory destruction acts | **Public blocker — OPEN (mandatory condition)** | procedure/organizational first; code later |
| Roskomnadzor entry accuracy and organizational prerequisites (ORG-RKN-01) | **Mandatory compliance condition — OPEN; proceeds independently of CRM** | no notification drafted from unverified facts |
| Personal-data localization evidence (R-V5) | **Public blocker — OPEN** | |
| Cookie page vs unconditional third-party loads | **Public blocker candidate** | fix by disclosure or local assets |
| Infrastructure gates (R-V2, R-V3, R-V4, R-V6, G6 SMTP, BK-4A) | **Public blocker — OPEN** | BK-4A stays blocked and paused |
| CRM data retention (R-B, U-10, AOA-13/16), CRM destruction acts, contract-document generation/versioning/protected storage (OC-07), contract signing workflow (OC-10), Qui-Quo | **Future CRM tasks** | outside the public-only release; not waived for any CRM go-live |

Production remains **NOT DEPLOYED / NO-GO**. The public-only release stays independent of the full CRM (OC-08) **without** waiving any mandatory privacy or legal release condition.

## 6. Factual questions (owner / infrastructure)

| ID | Question | Status |
|---|---|---|
| FQ-1 | Full text of the current Roskomnadzor registry entry (all fields) | `UNVERIFIED` (only a summary was provided) |
| FQ-2 | MAG.Travel (Master Agent): arrangement terminated and no historical customer data held (NF-02/03) | **CLOSED** — `OWNER_CONFIRMED` (not independently verified; no certificate or provider confirmation obtained; no further contact required). Registry amendment (ORG-RKN-01), appointment of the responsible person (DR-17) and other compliance requirements stay OPEN |
| FQ-3 | Where data is actually stored today (AdminVPS, mailbox provider, Tourvisor) vs "software provider's server" in the entry | `UNVERIFIED` |
| FQ-4 | Mailbox storage location and terms for `straus97@mail.ru` and `avilonatur@bk.ru` | `UNVERIFIED` |
| FQ-5 | Backup retention and restore behaviour (R-V2) | `UNVERIFIED` |
| FQ-6 | Tourvisor's processing role, retention, localization | `UNVERIFIED` |
| FQ-7 | Actual browser requests with/without cookie consent (authorized environment only) | `UNVERIFIED` |
| FQ-8 | Production values of `MAIL_MAILER`, `SESSION_DRIVER`/encryption, `LOG_CHANNEL`/`LOG_LEVEL`, `CAPTCHA_DISABLE`, trusted proxies, cron `schedule:run` | `UNVERIFIED` |
| FQ-9 | Existing mailbox content older than 12 months (initial destruction backlog) | `UNVERIFIED` |
| FQ-10 | Does an appointment order for the responsible person exist (NF-05) | `UNVERIFIED` |
| FQ-11 | Which recipients receive customer data for standalone tickets, accommodation and transfers (airlines, hotels, third-party carriers) and whether any is outside Russia (NF-07/08) | `UNVERIFIED` — not proven by hostname or business type |

## 7. What S0 did not do

No S1 or later slice; no code, config, route, migration, test, dependency or legal-PDF change; no Project Sources refresh; no Roskomnadzor submission or draft notification; no commit or push.
