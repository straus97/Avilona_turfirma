# NOV-PUBLIC-LEGAL-01 S0 — Roskomnadzor registry discrepancy register (2026-11-24)

**Documentation-only (S0).** Part of the [decision register](NOV-PUBLIC-LEGAL-01-s0-decision-register-2026-11-24.md) set. **No notification is submitted, no authority is contacted, and no regulatory text is drafted from unverified facts.** Nothing here is a legal opinion.

Evidence labels are defined in the decision register §1. Every registry fact below is `OWNER_PROVIDED_EVIDENCE`: the registration is confirmed by the owner's evidence, but the **current accuracy** of the entry is **not** confirmed and the full entry text has not been seen (FQ-1). Scans or extracts of registry documents are kept **outside** the repository.

## 1. Owner-provided registry facts

| Item | Value |
|---|---|
| Operator | ООО «Авилона», ИНН 7805502454, ОГРН 1097847289803, КПП 780501001 (ИНН/ОГРН match the public consent page and `README.md` §9A — `VERIFIED_IN_REPO`) |
| Registry registration number | 78-20-007148 |
| Notification registration date | 30.09.2020 |
| Registry inclusion | Order No. 320 of 05.10.2020 |
| Processing start date | 01.03.2020 |
| Travel-agency registry number | РТА 0013440 |
| Entry content (as reported by the owner) | obsolete Звенигородская legal address; **LLC Company Master Agent** as the party responsible for organizing personal-data processing; storage on the software provider's server; purposes "tourism" and "personnel administration"; data-subject categories "contracted tourists" and "employees"; no cross-border transfer; database location Russia |
| Current legal address (travel-agency registry extract dated 24.11.2026) | `198302, г. Санкт-Петербург, вн. тер. г. муниципальный округ Красненькая речка, ул. Морской Пехоты, д. 10, к. 1, литера А, кв. 22` |
| Customer-service address | `198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10` — visits by prior arrangement only |

## 2. Newly confirmed facts and what stays unresolved

| ID | Statement | Status |
|---|---|---|
| NF-01 | Avilona has **completely discontinued MAG.Travel (Master Agent)** | `OWNER_CONFIRMED` |
| NF-02 | No historical Avilona tourist personal data remains with MAG.Travel | `OWNER_CONFIRMED` — factual question **CLOSED**; owner statement under their responsibility, not independently verified; no destruction certificate, provider confirmation or technical verification obtained |
| NF-03 | All business and data-processing arrangements with MAG.Travel have ended; no further contact required | `OWNER_CONFIRMED` — **CLOSED** on the same basis |
| NF-04 | Current formally appointed person responsible for organizing processing | **UNKNOWN** |
| NF-05 | Existence of a current appointment order | `UNVERIFIED` |
| NF-06 | Appoint the **general director** as the responsible person | `RECOMMENDATION` — **not** an owner-confirmed appointment; needs formal company confirmation |
| NF-07/08 | Standalone airline tickets, accommodation and transfers are arranged; transfers are performed by third-party carriers | `OWNER_CONFIRMED` (business scope) |

**Separation that must be kept:** (a) discontinued use of the software product (NF-01), (b) termination of the arrangements (NF-03) and (c) absence of historical data at the provider (NF-02) are three statements; the owner has now confirmed all three, and the **MAG.Travel factual question is CLOSED** on that owner-confirmed basis (not independently verified; no certificate or provider confirmation). This closure does **not** close: the Roskomnadzor registry amendment (ORG-RKN-01, **OPEN**), the appointment of the responsible person (DR-17, **OPEN**; general director = recommendation only), or any other privacy/legal requirement (**OPEN**). It must not be assumed that all data is hosted in Russia or that no cross-border transfer occurs.

## 3. Discrepancy register

Each row is a **candidate discrepancy** between the reported entry and the project's recorded facts. None is a legal conclusion; counsel decides what, if anything, must change.

| ID | Registry entry (reported) | Project / owner facts | Assessment | Status |
|---|---|---|---|---|
| RKN-01 | Legal address: obsolete Звенигородская (191119) | Legal address from 2026-10-12: 198302 … ул. Морской Пехоты (ЕГРЮЛ 2267802693431, extract 24.11.2026) | Entry address out of date; public HTML consent page already shows 198302, legacy PDFs still show the old address (N-4) | Open |
| RKN-02 | Responsible party for organizing processing: LLC Company Master Agent | NF-01–03: Master Agent discontinued, arrangements ended (owner-confirmed); NF-04/05: current responsible person unknown, order unverified; NF-06 recommendation only | Entry names a party the company says it no longer uses; current appointment unknown | Open — `UNRESOLVED` (DR-17) |
| RKN-03 | Storage on the software provider's server | Current hosting is the owner-reported AdminVPS (not deployed); inquiry mail in a `mail.ru` mailbox; Tourvisor module on its own servers; backups at the VPS provider | Reported storage description does not match the current stack | Open — `UNVERIFIED` (FQ-3/4/5/6) |
| RKN-04 | Purposes: tourism; personnel administration | Public inquiry handling without a contract, website operation/cookies, registration and cabinet, reviews, optional marketing, standalone tickets/accommodation/transfers (NF-07/08) | Whether the listed purposes cover these is a legal question | Open — counsel |
| RKN-05 | Data subjects: contracted tourists; employees | Inquiring visitors who never contract, registered users, reviewers, third-party travellers named in bookings | Categories in the entry may not include non-contract visitors | Open — counsel |
| RKN-06 | No cross-border transfer | Public pages make requests to third-party hosts (CDNs, fonts, analytics, maps, Tourvisor); recipients for tickets/accommodation/transfers unknown (FQ-11) | **Not proven either way**: a hostname is not proof of a transfer, and "no transfer" is not verified | Open — runtime assessment in an authorized environment |
| RKN-07 | Database location: Russia | Owner-reported Moscow data centre; localization evidence not held as documents (R-V5) | Unproven | Open — R-V5 |
| RKN-08 | Other entry fields (categories of data, legal bases, security measures, recipients) | Not provided | Cannot be compared | Open — FQ-1 |
| RKN-09 | Processing start 01.03.2020; notification registered 30.09.2020 | — | Recorded as stated; **no conclusion drawn** | Noted |
| RKN-10 | — | Customer-service address 198261 differs from legal address 198302 | Consistent with `README.md` §9A (legal address shown only as «Юридический адрес») | Consistent |
| RKN-11 | Master Agent as processor-like party | NF-01–03: owner confirms discontinuation, ended arrangements and no historical data at MAG.Travel | Factual question closed on owner-confirmed evidence (not independently verified); the registry entry still names it until amended | Factual question **CLOSED**; entry amendment stays under ORG-RKN-01 (OPEN) |
| RKN-12 | — | Travel-agency registry number РТА 0013440 recorded (NF-09); whether it appears on public pages is not checked here | Public display is a separate content question | Noted |

## 4. ORG-RKN-01 — organizational compliance task (separate from code)

- **Independence:** ORG-RKN-01 proceeds **independently of full-CRM completion** and **without unjustified delay**. It is not a CRM slice and does not wait for AOA stages, Qui-Quo or E6.
- **Not an owner-risk item:** the accuracy of notification data is a mandatory compliance condition; owner acceptance cannot waive it. It stays **OPEN** until counsel/owner establish the facts and any amendment is made by the company.
- **Amendment, not new registration:** ORG-RKN-01 concerns amendment of the **existing** notification (registration 78-20-007148), not a new registration.
- **Timing:** the statutory time for notifying changes (a previously identified reference is Article 22(7) of Federal Law No. 152-FZ — a supplied reference, not verified here) needs **prompt primary-source verification**. No deadline or legal conclusion is stated here, and the known discrepancies must not be postponed indefinitely until unrelated CRM work or every possible future processing detail is settled.
- **Out of scope here:** submitting an amendment, drafting the amendment text, contacting Roskomnadzor.

## 5. Organizational prerequisites (before any amendment text is prepared)

| ID | Prerequisite | Status |
|---|---|---|
| P-1 | Obtain the full current registry entry (FQ-1) and store the extract privately outside Git | `UNVERIFIED` |
| P-2 | MAG.Travel arrangement status and historical data (NF-02/03) | **CLOSED** — `OWNER_CONFIRMED`, not independently verified; no further contact required. Nothing further is needed for P-9 except the entry amendment decision |
| P-3 | **Internal appointment documents:** formal decision on the person responsible for organizing processing (recommendation: general director, NF-06) and a written appointment order | `UNRESOLVED` (NF-04/05) |
| P-4 | **Processing rules:** internal documents describing how personal data is processed (purposes, data categories, systems, recipients, access, security, data-subject requests) consistent with the revised Policy and the actual stack | `UNRESOLVED` |
| P-5 | **Destruction procedure:** written procedure plus act/extract forms per Order No. 179 (verified against the primary text) — see the [retention register](NOV-PUBLIC-LEGAL-01-s0-retention-rule-register-2026-11-24.md) §4 | `UNRESOLVED` |
| P-6 | Inventory of systems and recipients: website/VPS, mailboxes, Tourvisor, analytics counters, hotel/airline/carrier recipients (FQ-3/4/6/11) | `UNVERIFIED` |
| P-7 | Localization evidence for the database and mailbox (R-V5) | `UNVERIFIED` |
| P-8 | Counsel review of purposes/subject categories/cross-border statements (RKN-04/05/06) | `UNRESOLVED` |
| P-9 | Only after P-1 and P-3…P-8 establish the facts (P-2 is closed): decide whether and how the entry is amended | not started |

## 6. Related existing items (unchanged)

R-V5 and R-L1 stay OPEN; G7 (legal-PDF part) stays OPEN; production remains NOT DEPLOYED / NO-GO; BK-4A remains blocked and paused.
