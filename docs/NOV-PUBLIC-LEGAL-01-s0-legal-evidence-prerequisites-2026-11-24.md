# NOV-PUBLIC-LEGAL-01 S0 — Legal-document and consent-evidence prerequisites (2026-11-24)

**Documentation-only (S0).** Part of the [decision register](NOV-PUBLIC-LEGAL-01-s0-decision-register-2026-11-24.md) set. Everything below is **`PROPOSED`** design and prerequisites. **S1 and all later slices are unauthorized.** No code, migration, configuration, test, dependency or legal-PDF change is made by S0. Nothing here is a legal opinion and nothing is claimed legally sufficient.

Evidence labels: see the decision register §1. §8 (added 2026-11-25) records owner decisions and evidence as well as proposals, under its own labels.

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
6. **No legal PDF is replaced** by S0 or S1. The first archive step records the currently published files as-is, labelled `legacy-as-published`, status **not** "approved". The label means a legacy source artifact archived as-is; it does **not** prove deployment of the rebuilt application and is not legal approval. New approved revisions (R-L1/N-4, including NF-07/08 service scope) arrive later as new versions. *(Partly superseded 2026-11-25 — see §8.2; the text of this item is kept as historical.)*
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

## 8. Update 2026-11-25 — S1 scope adjustment, evidence and proposed split

**Documentation only.** Prepared from base `b89dd39acb7ed37abd4b67a7890f08e9581b33b5` (application checkpoint `59d9c229` unchanged); **not yet committed or pushed**. The active Project Sources checkpoint stays `b89dd39a` until an actual verified refresh and promotion. S0 remains **CLOSED AND PUSHED**. No code, test, configuration, manifest, `.gitattributes` or PDF change; **S1-A and S1-B are both unauthorized**.

This section is the **canonical location** of the binary identity evidence below; other documents cross-reference it.

Labels: decision register §1, plus `OWNER_CONSOLE` (owner-reported console results) and `REVIEWER_REPORTED` (reported by the independent reviewer; not verified by the documentation author). The author did not run commands, open PDFs, access private directories or verify live Git state. The latest owner-console evidence reports local/tracking HEAD alignment, empty staging, a clean tracked tree and the expected untracked guard; **live origin was not rechecked** in those steps.

### 8.1 Owner-confirmed scope adjustment (`OWNER_CONFIRMED`)

1. Preserve the five original legacy PDFs privately.
2. Do **not** create additional application-archive copies of those legacy PDFs.
3. Preserve existing Git history.
4. Keep the existing public PDFs unchanged until a separately authorized replacement.
5. Begin the new application version history with **approved new** document revisions.
6. Preserve owner-operated resolution of known historical User Agreement hashes through the private archive.
7. Clean and verify metadata before final approval of new public-document bytes.

The legacy public PDFs **remain in the current source and release packages** until their authorized replacement. No runtime dependency on the private archive is permitted.

### 8.2 What this supersedes

Only these three earlier requirements (§2 item 6 and equivalent wording elsewhere) are superseded, by §8.1 and the separately gated plan in §8.6:

- that the initial application archive must contain the legacy PDFs;
- that S1 may not replace any legal PDF;
- that initial registry records must use legacy (`legacy-as-published`) activation.

`legacy-as-published` keeps its meaning (a legacy source artifact archived as-is, not approval) for the **private** archive only. All other items of §2 stand.

### 8.3 Evidence A — legacy binary identity — CLOSED / PASS (`OWNER_CONSOLE`)

At `b89dd39a` the five PDFs match their committed blobs, the working-tree bytes and an in-memory Git ZIP archive. All are classified **binary** by Git; the seven inspected Git attributes were unspecified.

| Key | Source file in `public/documents/` | Bytes | Git blob | SHA-256 |
|---|---|---|---|---|
| `personal-data-policy` | `Policy_regarding_the_protection_and_processing_of_personal_data.pdf` | 93119 | `7a2460b6e86ecf2aaa5457103d48e55f34bfbbfb` | `b5b4aa7082f48d4b5ac2014207cc5642e54e73b54343229a3ac74a1830e0f76e` |
| `separate-services-terms` | `Public_offer_for_ticket_sales_and_hotel_bookings.pdf` | 262939 | `60d53a8fafa5c4020f8d1019cab9febf8c4c4929` | `ca3ff60bc248a2caf27fc45098c4a97880087458021a948db43265e6bf803487` |
| `tour-product-terms` | `Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf` | 379507 | `decb090831484981c3834ac7b4828ed452cc7566` | `4453f1f64e7c2a3388eb59429e82cf311d82ea4e6de0549451b190a033ab57c5` |
| `travel-memo-general` | `Reminder_for_those_traveling_abroad_general.pdf` | 111409 | `b240fc3e221d6bc4ea0cb84ffd149f118e1ac0df` | `7bc69f4b5c88ad198017657a7b3f982ea206f33379146134487adf43317f5f1b` |
| `user-agreement` | `User_Agreement.pdf` | 73878 | `c5fd010b3f9bab9b1fa275d8bfb8bce784e4762c` | `a686c44c3a38529422734ba4ea54dcc10ecda3d57664db54a7d120bd21de87d9` |

Total: **920852 bytes**.

**Consent Blade baseline** (`resources/views/legal/personal-data-consent.blade.php`):

| Copy | Bytes | Line endings | SHA-256 |
|---|---|---|---|
| Committed | 3735 | 58 LF, 0 CRLF, 0 standalone CR | `a66927705c44afd3a504f5c5ca9615f81e62e4bd111a68df61050bf8d3f1a571` |
| Working tree | 3793 | 58 CRLF, 0 standalone CR | `55e8be4850046f223985e4dd60a774b04266c66c950c738898d69534086feb28` |

EOL-normalized text equality: PASS. Effective, global and system `core.autocrlf=true`; global Git EOL settings are not to be changed.

**Evidence C** (isolated before-change rendering baseline of the consent page): **PENDING**.

### 8.4 Evidence B — classification for private preservation (`REVIEWER_REPORTED`)

The independent reviewer inspected the exact five uploaded PDFs (38 pages). Within the inspection methods applied, no customer data, populated individual contracts, embedded files, signatures, private comments or JavaScript were detected. **Three** files carry an owner account identifier in author metadata; the owner confirmed its origin (`OWNER_PROVIDED_EVIDENCE`). The identifier is deliberately not recorded.

This is **not** absolute proof that no hidden content exists, **not** legal approval and **not** authorization for additional public distribution.

### 8.5 Private legacy archive — PASS (`OWNER_CONSOLE`)

The owner created a private historical archive **outside Git and outside application release storage**: exactly five byte-identical PDFs (920852 bytes) and `legacy-manifest.json`, six files, from checkpoint `b89dd39a`. Manifest SHA-256: `62a768bba6168a64fe1c60ef2f9dee5aa5ff8099d9b66f5f273a43524ffeef41`. Source PDFs and repository unchanged.

**UNVERIFIED:** private filesystem access control and independent backup durability. Preserved Git history is not a controlled backup.

### 8.6 Proposed split (`PROPOSED`; accepted at independent-review level)

These are technical recommendations, **not** owner-confirmed decisions. The split, version IDs, master-document handling and activation rules await separate implementation authorization and **do not resolve DR-08**. S1 stays open until both parts pass.

**S1-A — legal registry foundation**

- Pure registry, fragment and package-integrity validators; strict TAR-reader and release-builder hardening.
- Synthetic test data only. **No** `resources/legal-archive/` directory and **no** registry file, including empty or placeholder registries.
- No new legal documents; no change to public PDFs, views, routes or runtime legal behavior.
- The existing package stays buildable. The builder reports `NOT_ACTIVE` explicitly and **fails if the selected committed Git tree contains any entry under the future archive path**. The check inspects that revision's tree, not only the TAR listing, because the current manifest does not include the directory.

**S1-B — initial legal document integration** (only after legal-document acceptance and separate authorization)

- Five approved public PDFs and one approved canonical HTML consent fragment; all six active documents introduced together; proposed initial ID `rev-0001`.
- Exact SHA-256 and byte-size pins; locked immutable version records; append-only activation and restriction histories.
- The five public PDF URLs are preserved; their bytes are replaced with the approved binaries; exact copies of the **approved** PDFs are archived outside `public/`.
- The consent page is rendered from the verified fragment.
- The S1-A `NOT_ACTIVE` branch is removed and legal-archive validation becomes mandatory; no persistent bypass and no optional runtime verification flag.
- Recommended: the consent DOCX/PDF master stays private; the application displays the canonical HTML, and a private external ledger links the approved master to the HTML version.

**Design contracts carried into the split**

- Archive at `resources/legal-archive/`, outside `public/` and writable customer storage; no download endpoint, database, dependency, Artisan command or runtime writes.
- Document keys: the five in §8.3 plus `inquiry-consent-text`.
- Deterministic strict JSON registry (canonical byte round trip and exact key sets, so duplicate keys are rejected).
- Version record: `version_id`, `locked`, `approval_reference`, `content_type`, `path`, `sha256`, `bytes`, `links`. Activation: `version_id`, `reference`, `effective_from`. Current version = last activation; supersession is derived; publication evidence stays outside the registry; no drafts in the repository.
- Rollback only as a new forward activation of an earlier approved, unrestricted registry version; none exists at first activation, and private legacy files are never registry records.
- Repository-relative file path and public asset path are separate, validated fields, so existing URLs do not change.
- A current page version links to the exact current version of each linked document.
- Invalid archive content: the consent page fails with a generic error and class-only log, with no fallback text. Public PDF equality is checked at test and package gates, not at runtime (residual drift risk). Form submission enforcement stays S3.
- History comparison between exact revisions (previous accepted checkpoint to candidate) plus owner-held external hash pins. The first pins are set at S1-B. No absolute immutability or legal sufficiency is claimed.

### 8.7 Structural inventory of the working consent master (`REVIEWER_REPORTED`)

`Avilona_consent_v1_1` (working revision, **not approved**): one title; one subtitle; one revision-label paragraph; five numbered section headings; ten substantive paragraphs; no tables or separate bullet lists; no embedded hyperlinks; two PDF pages; no ampersands, angle brackets, percent characters, non-breaking spaces or checked zero-width spaces in extracted DOCX paragraph text.

`PROPOSED`: a closed HTML grammar supporting `h1`, `h2`, `p` and narrowly controlled `a` elements, with a clickable Policy reference on the corresponding existing text reference, pinned to the exact approved Policy version. No arbitrary rich HTML; no legal wording is written or changed here.

### 8.8 Future acceptance requirements — packaging and tests (`NOT_IMPLEMENTED`)

- Manifest and artifacts verified from the exact selected revision.
- Strict TAR completion and header checksums; bounded, unambiguous PAX processing; compatibility with actual `git archive` output.
- Rejection of exact and case-only duplicate entries; bounded-memory streamed hashing.
- Checked output writes and cleanup on failure.
- When active: archived and public PDF equality using bytes from the same package.
- No weakening of existing package security exclusions; no weakening or permanent exclusion of tests.
- **S1-A:** old and new builders must produce identical `.tar.gz` bytes for the same base revision, PHP and Git versions and environment (a changed informational sidecar is not a package-byte difference). The full pre-commit suite has **no planned exclusions**.
- **S1-B:** the HEAD-dependent package build test may need an explicitly recorded pre-commit deferral, followed by mandatory passing post-commit verification before push; the candidate commit stays local until then. New PDFs must be classified binary before staging.

**None of these tests or package gates has been executed.**

### 8.9 Historical evidence and legal limitations

- A known historical User Agreement hash can be resolved manually against the private manifest, **only** for acceptances corresponding to those exact bytes.
- Historical registration, review and publication Blade hashes are not necessarily reconstructible. Anonymous inquiries recorded no document hashes. No database lookup or runtime historical-archive service is introduced.
- Replacing the shared User Agreement changes the values that registration and review record afterwards. A new Policy also changes what the legal links on the existing registration/review consent pages open, including the public route `/reviews/personal-data-consent`; this route must be part of the later legal-coverage assessment.
- Full-CRM legal readiness must **not** be inferred from public-only approval.
- S1-B needs narrowly authorized reconciliation of the old offer labels, displayed update dates, relevant metadata and existing legal-wording test assertions. No new date or wording is invented here.
- All unresolved legal, organizational, retention and infrastructure gates stay **OPEN**.
