# NOV-PUBLIC-LEGAL-01 S0 — Legal-document and consent-evidence prerequisites (2026-11-24)

**Documentation-only (S0).** Part of the [decision register](NOV-PUBLIC-LEGAL-01-s0-decision-register-2026-11-24.md) set. Everything below is **`PROPOSED`** design and prerequisites. **S1 and all later slices are unauthorized.** No code, migration, configuration, test, dependency or legal-PDF change is made by S0. Nothing here is a legal opinion and nothing is claimed legally sufficient.

Evidence labels: see the decision register §1.

## 1. Current state (`VERIFIED_IN_REPO`, read-only)

- The public forms (`POST /contact/send_home`, `/contact/send_contact`) persist nothing: they validate, send a synchronous internal mail to `straus97@mail.ru`, send a bounded visitor acknowledgement and redirect. No consent timestamp, document version/hash, inquiry reference or delivery state is recorded anywhere (`SendContactController`, `SendHomeController`, `VisitorAcknowledgement`).
- Consent and User Agreement are **separate**, un-pre-ticked checkboxes (`agree`, `personal_data_consent`), each enforced with `accepted` server-side; this separation is preserved (OC-04).
- The User Agreement and Policy are mutable static PDFs in `public/documents/` with stale company details (N-4); the consent text is a Blade view with no version marker. Review/registration consents store the SHA-256 of the *current* file only — a hash without retained bytes cannot reconstruct what was accepted.
- Validation failure flashes name/e-mail/subject/message/captcha into unencrypted file sessions (120 min lifetime, GC lottery 2/100); `message` has no maximum length; no idempotency exists; SMTP outcome classes are not distinguished. Mail failure and acknowledgement failure are not recorded durably.
- Production values of mail, session, log and cache settings are `UNVERIFIED` (no `.env` access).

## 2. Legal-document archive and registry (target for S1)

1. **Scope: public legal text only.** The archive holds the public consent text fragment, the User Agreement, the Personal-Data Policy and later approved public legal documents. It must **never** contain generated customer documents, personal data, signed individual contracts, scanned signatures or anything produced per customer. Such documents (future CRM, OC-07) are generated, versioned and kept in **protected storage outside Git and outside the public release package**.
2. **Immutable versions.** Each version has an immutable identifier/label, effective dates, status (`draft | approved | published | superseded`), an approval reference (a decision ID, not a signature), the exact content file(s), their SHA-256 and byte size, and links to the exact versions of linked documents (the consent text records the Policy version it links to). A hash alone is never the record; the exact bytes/fragment are retained so the text behind any recorded hash can be reproduced.
3. **Displayed ↔ accepted ↔ archived.** *Displayed* content is rendered from the archived current version (layout excluded; link targets substituted from placeholders, with the hash computed before substitution and linked-document hashes recorded separately). *Accepted* content is identified by the version identifier and hashes stored in the future receipt. *Archived* content is retained in the repository/release package so any recorded hash can be re-verified.
4. **Wording updates** create a new version; the old one is kept. A form rendered under version N and submitted after N+1 became current is rejected as stale and records no acceptance.
5. **Overwrite protection and its limit.** Archived versions are marked locked in a manifest and covered by tests that fail on hash mismatch. These tests are **tamper evidence, not absolute cryptographic immutability**: a deliberate edit of both file and manifest would pass, so Git history and an owner-held hash ledger are additional pins.
6. **No legal PDF is replaced** by S0 or S1. The first archive step records the currently published files as-is, labelled `legacy-as-published`, status **not** "approved". The label means a legacy source artifact archived as-is; it does **not** prove deployment of the rebuilt application and is not legal approval. New approved revisions (R-L1/N-4, including NF-07/08 service scope) arrive later as new versions.
7. **Packaging** must include the archive; how the release manifest declares includes is `UNVERIFIED` and is part of S1's design step.
8. **Signature note:** no regular UKEP workflow is assumed (OC-10); a scanned signature is not automatically legally sufficient. Document signing questions belong to U-6/AOA-12/13, not to this archive.

## 3. Consent evidence (target design)

- **Target:** a dedicated minimal receipt, one row per submission: server reference (UUID), form source, server `received_at`, two **separate** acceptance blocks (consent; User Agreement) each with version label, SHA-256 and `accepted_at`, the referenced Policy version/hash, form-issue time, internal-mail state, acknowledgement state. **Not stored:** name, e-mail, subject, message, IP address, user-agent, captcha, session id, cookies, referrer.
- **Mail-only evidence** (reference and versions in the internal mail) is an **interim development stage only**. It is **not** an approved production compliance solution: the evidence would sit in a consumer mailbox that cannot be technically time-limited or protected by the operator and would be missing whenever mail fails.
- **Attribution without duplicating personal data:** L1 — the reference in the internal mail links the receipt to the thread while the thread exists; L2 — the statutory destruction act lists the reference next to the minimum subject identification (no new identifier; counsel to confirm); L3 — a keyed e-mail identifier is **excluded from the baseline** and may be introduced only with a documented purpose, retention rule and access model (DR-02). A UUID alone does not prove who consented, and the typed e-mail address is unverified (DR-16).
- **Retention of receipts is undecided** (RR-02); 12 months is not assigned to it. **Owner acceptance of a missing mechanism does not satisfy a statutory requirement for consent evidence** (DR-01).

## 4. Submission integrity (target for S3/S4)

- Existing CSRF, captcha, `throttle:8,1`, FormRequest validation and the independent `accepted` rules for both consents stay in force; the new check only adds to them and never implies consent.
- A form context bound to the originating form (home/contact), the session (as a keyed hash, not the id), the document versions and an expiry (≤ session lifetime), carrying no personal data.
- Tampering, wrong form or wrong session: reject, no receipt, no mail, class-only log. Stale version: reject without recording acceptance. Replay: a completed reference returns the same neutral success without re-sending. Concurrency: a unique reference claim is atomic (before a table exists, a best-effort cache lock whose reliability is `UNVERIFIED`).
- **SMTP outcomes:** `DEFINITE_NOT_SENT` (failure before the DATA phase), `ACCEPTED_BY_RELAY` (final success reply; never "delivered"), `UNCERTAIN` (anything after DATA began or unclassifiable). **No blind automatic retry after `UNCERTAIN`**, no queue retry, no scheduled resend. **Exactly-once external e-mail delivery is not promised.** How the mailer exposes the phase is `UNVERIFIED` and belongs to S4's design step.
- Acknowledgement outcomes are recorded separately and never alter the internal outcome. Sessions, cache keys and logs carry no unnecessary personal data; any keyed hash used for the acknowledgement limiter needs a documented purpose, retention (≤ 1 h cache TTL) and access (cache store only).

## 5. Future database rollback (correction recorded)

- A populated evidence table must **not** be assumed droppable by an automatic rollback. Future migrations for evidence/destruction tables must not drop a table that holds rows (refuse or omit a destructive `down`), and any removal needs a documented, owner/counsel-approved procedure that **preserves legally required records** (for example export to protected storage first).
- Application-code rollback means reverting the checkpoint commit only; data is never removed by a rollback.
- **No destructive rollback is authorized.** On the shared VPS, `migrate:fresh`, `rollback`, `db:wipe` and `DROP DATABASE` remain forbidden (R-V6).

## 6. External dependencies (assessment frame; nothing changed)

- Free, local static assets are the preferred direction. Any later step must keep exact compatible versions, retain licence notices and preserve visual behavior (parity check; SRI hashes already in the layout can verify downloaded bytes). **No asset is downloaded and no integration is modified in S0.**
- **International transfer is not concluded from a hostname.** Each third party stays "transfer status not established" until actual browser requests and provider facts are assessed in a later **authorized** environment.
- **Tourvisor is assessed separately** from analytics and outbound links: automatic load of its module on `/tours`, contact data typed into the module on the provider side, provider role/retention/localization (`PRIVACY_LEGAL_FACT_REQUIRED`, N-6), the cookie-page statement, and its relation to the registry entry (RKN-04/05). Decision DR-12.
- Static source evidence (`VERIFIED_IN_REPO`): `layouts/main.blade.php` loads Bootstrap/Bootstrap Icons (jsDelivr), Google Fonts, Font Awesome (kit and `use.fontawesome.com`) and lazysizes (cdnjs) unconditionally; the three analytics counters and the Google Maps embed are gated by the `v1_all` cookie value; Tourvisor loads unconditionally on `/tours`. Local candidates: Font Awesome Free 5.15.4 exists in `public/plugins/fontawesome-free`; no usable local Bootstrap 5.3.2, Bootstrap Icons or Roboto copy exists. Runtime behavior is `UNVERIFIED`. The cookie page does not disclose the unconditional hosts.

## 7. Proposed sequence — all unauthorized after S0

Owner-gated checkpoints on the existing branch `db-rebuild-stage3` (no branch per slice). *Update 2026-11-25:* S0 is closed and pushed; S1 is the next technical slice only after canonical documentation closure and a verified Project Sources refresh; every slice remains individually unauthorized (see [closure and transition](NOV-PUBLIC-LEGAL-01-s0-closure-and-transition-2026-11-25.md) §10).

| Slice | Objective | Database |
|---|---|---|
| S1 | Legal-document archive and registry (public legal text only) | none |
| S2 | Session/cache/log hygiene without UX change | none |
| S3 | Form context, stale-form rejection, interim mail correlation (development stage only) | none |
| S4 | Receipt persistence, state machine, SMTP outcome classes, no blind retry | one new table; MySQL 8.4.13 proof needed first (R-V3); rollback rule §5 |
| S5 | Operator-confirmed destruction, destruction event log, act/extract generation into protected storage | event-log table |
| S6 | Organizational runbooks | none |
| S7 | Local static assets (separate download authorization) | none |
| S8 | Tourvisor decision and runtime-request assessment (authorized environment) | none |
| S9 | Optional form hygiene | none |
| ORG-RKN-01 | Registry review/amendment — proceeds independently of CRM, without unjustified delay | not a code slice |

Public-only release stays independent of the full CRM (OC-08) without waiving any mandatory privacy or legal condition. G7, R-L1, R-V5, R-B/U-10 and the infrastructure risks stay OPEN; production stays NOT DEPLOYED / NO-GO; BK-4A stays blocked and paused; Qui-Quo and unrelated CRM stages stay out of scope.
