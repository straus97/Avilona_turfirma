# NOV-PUBLIC-LEGAL-01 S0 — Retention-rule register (2026-11-24)

**Documentation-only (S0).** Part of the [decision register](NOV-PUBLIC-LEGAL-01-s0-decision-register-2026-11-24.md) set. No deletion, cleanup, schedule, migration, mailbox access or destructive command is authorized or implied by this file. Nothing here is a legal opinion; no period below is "approved by counsel".

Evidence labels (`VERIFIED_IN_REPO`, `OWNER_PROVIDED_EVIDENCE`, `PROPOSED`, `UNVERIFIED`) are defined in the decision register §1. Register status values: `DECIDED` (owner-confirmed rule) · `NOT_DECIDED` (no period or trigger exists; **none is invented**) · `UNVERIFIED` (facts missing).

## 1. Ground rules

1. **12 months** applies **only** to RR-01 (non-contract inquiries) and runs from the **last substantive interaction**. It is **not** assigned to consent evidence, contracts, accounting records, correspondence of other kinds, backups or any other category.
2. **Earlier destruction** applies whenever legally required (purpose ended, consent withdrawn, etc.), subject to statutory requirements and independently justified lawful retention exceptions. Statutory deadlines and their exact triggers are `UNVERIFIED` until counsel confirms the primary text.
3. **The last substantive interaction is never inferred from the initial submission timestamp.** The application knows only when a form was received; staff replies and follow-ups happen in the mailbox. Any future application support (S5) may hold an *operator-entered* interaction date and *review prompts* for humans, but never an automatic destruction trigger based on `received_at` alone.
4. Owner acceptance of a technical risk does not waive statutory destruction, evidence or notification duties.
5. Two kinds of destruction record exist and must not be conflated (§4).

## 2. Category register

| ID | Category | Rule / trigger | Owner of rule | Enforcement surface | Evidence | Status |
|---|---|---|---|---|---|---|
| RR-01 | Non-contract public inquiry (message thread in the mailbox, any local copies) | **Max 12 months after the last substantive interaction**; earlier when legally required | Owner (OC-05) | Organizational procedure (mailbox is outside the code); Laravel cannot see replies | Destruction act (RR-06) | `DECIDED` (period) / definition of "substantive interaction" `NOT_DECIDED` (DR-03) |
| RR-02 | Anonymous-inquiry receipt (future dedicated consent-evidence record) | **No period.** Options in DR-04 | Owner + counsel | Future S4/S5; until then none exists | Destruction event-log extract + act | `NOT_DECIDED` |
| RR-03 | Contract-related records | **No period** | Owner + counsel + accountant | Future CRM (AOA-13) | — | `NOT_DECIDED` |
| RR-04 | Accounting records | **No period** (statutory; not inferred here) | Accountant/counsel | Organizational / future CRM | — | `NOT_DECIDED` |
| RR-05 | Other consent evidence (registration consent, review consent, publication consent) | **No period.** Existing tables `user_registration_consents`, `review_consents` hold versions/timestamps (`VERIFIED_IN_REPO`); registration consent is deleted with an allowed account deletion (R-B/AOA-05-I3) | Owner + counsel | Existing CRM code; no retention job | — | `NOT_DECIDED` |
| RR-06 | **Destruction acts and system event-log extracts** (statutory confirmation of destruction) | **Kept 3 years after destruction** — requirement stated by the owner citing Roskomnadzor Order No. 179; **primary-source verification still required** | Responsible person (DR-17) | Organizational + future S5 generation into protected storage | the documents themselves | `DECIDED` by owner statement; primary-source check `UNVERIFIED` |
| RR-07 | Sessions and cache (technical) | Configured TTLs: session 120 min, captcha cache 60 s, acknowledgement limiter 1 h, throttle 1 min (`VERIFIED_IN_REPO`). Technical lifetime, **not** a legal retention period | Engineering | Laravel config/env; unencrypted file sessions and GC lottery 2/100 noted | — | `NOT_DECIDED` as a policy; values `VERIFIED_IN_REPO`, production values `UNVERIFIED` |
| RR-08 | Application logs | Code logs only the exception class for contact-form mail failures (`VERIFIED_IN_REPO`); `daily` channel keeps 14 days only if `LOG_CHANNEL=daily` is set; web-server and MTA logs unknown | Engineering/infra | Laravel + VPS log rotation | — | `NOT_DECIDED`; production values `UNVERIFIED` |
| RR-09 | Backups and snapshots | Provider-reported snapshots; binlog retention reported as 30 days; no restore demonstrated (R-V2, BK-4A paused). **No retention rule decided** | Infra + counsel | Provider; restore re-application of destruction (DR-07) | — | `NOT_DECIDED` / `UNVERIFIED` |
| RR-10 | Withdrawal requests and other correspondence in `avilonatur@bk.ru` | **No period** | Owner | Organizational | destruction act | `NOT_DECIDED` |
| RR-11 | Tourvisor provider-side copy of requests entered in its module | Provider retention unknown | Provider / owner | Provider | provider confirmation, if obtained | `UNVERIFIED` |
| RR-12 | CRM categories (`client_verification_requests`, `inquiry_contacts`, `incoming_inquiries`, bookings, documents, messages) | **No period** (R-B, U-10, AOA-13/16); 30-day Manager window is not a retention policy | Owner | Future CRM | — | `NOT_DECIDED` (future CRM task) |
| RR-13 | Operational audit events (`audit_events`) | **No legal retention period assigned**; operational telemetry; append-only at application level only (R-F) | Engineering | Existing `AuditRecorder` | **Not** statutory destruction evidence | `NOT_DECIDED` |

Local copies, exports and staff devices fall under the rule of the category they copy; no personal-data export command exists for the public forms (`VERIFIED_IN_REPO`), and any future export must carry the same destruction obligations.

## 3. Surfaces: what Laravel can automate vs what cannot be

| Surface | Laravel/code can | Organizational procedure or provider support needed |
|---|---|---|
| Future receipt table | operator-confirmed destruction command (dry-run default), review prompts, destruction event log | decision on rule and basis (DR-03/04) |
| Sessions / cache | TTLs, encryption env, flash-input minimization | file-session cleanup on the VPS |
| Application logs | level, rotation, no personal data in log context | web-server / PHP-FPM / MTA log rotation |
| Mailbox `straus97@mail.ru`, sent/trash/forwarding/IMAP/mobile copies | **nothing** | periodic review and deletion by a defined procedure; provider "delete" semantics; staff local copies |
| `avilonatur@bk.ru` | **nothing** | same |
| Exports and local copies | future generation into protected storage with expiry markers | staff handling rules |
| Backups and restore | re-apply the destruction register after restore | provider retention facts; counsel view on erasure-by-expiry |
| Tourvisor copy | nothing | provider facts |
| Existing mailbox backlog older than 12 months (FQ-9) | nothing | decide an initial destruction campaign with its own acts |

## 4. Destruction evidence — two separate kinds

### 4.1 Operational audit events (not legal evidence)
Aggregate or event-level operational records (for example via the existing `AuditRecorder`). They help engineers and the owner see that a job ran. They hold no personal data, have no assigned retention period, and are **explicitly not** a substitute for statutory destruction acts or event-log extracts. Aggregate counts alone do **not** satisfy Roskomnadzor Order No. 179.

### 4.2 Statutory destruction evidence (Roskomnadzor Order No. 179 — owner-stated requirement)
- **Destruction act** and, for processing with automation, a **system event-log extract**, as applicable.
- **Kept three years** after destruction (RR-06).
- Field checklist below is the **drafter's recollection** and must be **verified against the primary text of Order No. 179 by counsel before any template is built** (`UNVERIFIED`):
  - operator's name and address;
  - identification of the data subject(s) whose data was destroyed;
  - names, positions and signatures of the persons who destroyed the data;
  - categories of personal data destroyed;
  - information system and/or material carrier;
  - method of destruction;
  - date of destruction;
  - for automated processing: an extract from the event log (subject identification, categories, system, cause, date); the act must carry whatever the extract lacks.
- **Protecting necessary identification without keeping the destroyed dataset:** the act and extract keep only the minimum identification and categories — never message text, subject lines or mail bodies. They live in protected private storage outside `public/`, outside the Git repository and outside the public release package, not in application logs, with restricted access. The destroyed record and mail thread are not retained.
- **Signature:** regular UKEP use is not part of the workflow (OC-10) and no paid signing service is used. Planning assumes a wet-signed paper act unless counsel says otherwise. A scanned copy is a convenience copy and is **not** assumed to resolve the legal question; whether an electronic-form act requires an electronic signature is `UNVERIFIED`.
- **Application side (future S5, unauthorized):** a dedicated append-only destruction event log, separate from `audit_events` and from `laravel.log`, able to produce an extract file with its own hash into protected storage; the act is generated from a **versioned template** (OC-07). No such code exists today.

## 5. Open items

- DR-03 (definition of "substantive interaction"), DR-04 (consent-evidence period), DR-05 (mailbox), DR-07 (backups), DR-10 (act form/signatory), DR-17 (responsible person), FQ-9 (existing backlog).
- Primary-source verification of Order No. 179 (fields, forms, 3-year keeping) and of statutory destruction deadlines in Federal Law No. 152-FZ.
- R-B/U-10 stay OPEN for CRM data.
