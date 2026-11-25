# NOV-PUBLIC-LEGAL-01 S1-A — Legal registry foundation: closure and evidence record (2026-11-25)

**Documentation only.** This is the detailed canonical evidence record for S1-A. Status of every stage, gate and risk stays authoritative in [`roadmap.md`](roadmap.md). **At the time this record was prepared**, the documentation reconciliation had not yet been accepted by independent review, committed or pushed. That sentence describes the preparation state only; Git is authoritative for the live documentation HEAD, and no documentation commit hash is stated.

Related: [prerequisites §8–§9](NOV-PUBLIC-LEGAL-01-s0-legal-evidence-prerequisites-2026-11-24.md) · [closure and transition](NOV-PUBLIC-LEGAL-01-s0-closure-and-transition-2026-11-25.md) · [decision register](NOV-PUBLIC-LEGAL-01-s0-decision-register-2026-11-24.md).

Nothing here is a legal opinion. Technical acceptance of S1-A is **not** legal approval of any document, date, authority, wording or retention period.

## 1. Evidence labels

| Label | Meaning |
|---|---|
| `OWNER_CONSOLE` | Owner-run console results, supplied to the documentation author. Not executed or re-verified by the author. |
| `REVIEWER_REPORTED` | Independent reviewer's result, supplied to the author. Not re-verified. |
| `VERIFIED_IN_REPO` | Read by the author in working-tree files with file-reading tools. No Git command was run, so identity of those files with the committed blobs rests on the `OWNER_CONSOLE` evidence. |
| `PROPOSED` / `NOT_IMPLEMENTED` / `OPEN` | Design not decided / not present in code / unresolved gate. |

The documentation task ran **no** command and **no** test, and opened no private directory, legal document, quarantine file, environment file, database or VPS.

## 2. Checkpoint and status (`OWNER_CONSOLE`, 2026-11-25)

| Item | Value |
|---|---|
| Branch | `db-rebuild-stage3` |
| Application checkpoint | **`7706517ddc663e0b8b6787e42a281445514a0ec0`** — `feat: establish legal archive registry foundation (S1-A)` |
| Direct parent | `b71755b55ca4f04e3b9a0afc26a58249dd643a3b` — `test: make cabinet chat header assertion deterministic` (§5) |
| Previous remote / documentation checkpoint | `1b6c5771fb82225e3a9ca3d75bf8f49921e979ae` — `docs: record LEGAL S1 scope adjustment and legacy evidence` |
| Push | `LEGAL_S1A_GUARDED_PUSH=PASS`, `PUSH=VERIFIED`, 2 commits, 11 files, no force push |
| After push | local = tracking = live origin = `7706517d…`; ahead 0, behind 0; tracked worktree clean; staging empty; final repository guards PASS |
| Only permitted unrelated untracked file | `.claude/settings.json` |
| Technical acceptance | **PASS** |

**S1-A is implemented, technically accepted and pushed. S1 as a whole stays OPEN. S1-B is not authorized. Production NOT DEPLOYED / NO-GO.**

## 3. Implementation scope (`OWNER_CONSOLE`; reviewer match `REVIEWER_REPORTED`)

Ten files: nine added, one modified; 4834 insertions, 127 deletions. All file modes `100644`.

| File | Change | Git blob |
|---|---|---|
| `scripts/release/build-public-package.php` | modified | `0a1a71fc7328a31893409b7e5f4adb489bfa6ad8` |
| `app/Services/Legal/LegalRegistry.php` | added | `170e76c26e6bca7b692c6ebeef7a791722014b7e` |
| `app/Services/Legal/LegalFragment.php` | added | `82d326586ccfea28fd52ca51af1d52b3f273a436` |
| `app/Services/Legal/LegalArchiveException.php` | added | `5dfb8e41a1dc39f3dbb157c5fe45ea2289ba0186` |
| `scripts/release/read-tar.php` | added | `bf9b2eda5e1ccdd30e8b5470a791b01071822132` |
| `scripts/release/legal-package-check.php` | added | `4031aa569c5102af1d2bacbaaca99bfe3f29e8a4` |
| `tests/Unit/LegalRegistryTest.php` | added | `0d74f361be497f9ec20659ace90e66ad07e2b5b7` |
| `tests/Unit/LegalFragmentTest.php` | added | `87f6a71577d99eca56486414c85eb6b5277d02c6` |
| `tests/Unit/LegalPackageCheckTest.php` | added | `e0b8625f36288ccfbb8ae48096da357882dcaf87` |
| `tests/Unit/ReleaseTarReaderTest.php` | added | `63d3324b873397e23787bd6ffd8302e775025243` |

- The independent reviewer verified the reviewed F1 ZIP (SHA-256 `eed402e366e1cb7bd2e0005c17a6b741690e546a5e6b50bf5449aaa692286e20`, 60536 bytes) and matched all ten committed blobs against it; staged and committed blobs matched the reviewed source bytes.
- The release manifest `scripts/release/public-package-manifest.json` is **unchanged** (blob `01575c8d1ec7cbf23a6546f93af6b171c8b2c8ff`).
- No `resources/legal-archive` directory or registry exists in the committed application tree (also not found in the working tree, `VERIFIED_IN_REPO`).
- S1-A changed no legal PDF, Blade template, controller, route, migration, dependency or existing application behavior.

## 4. Technical contract as implemented (`VERIFIED_IN_REPO`)

**F1–F7 correction items.** The number-to-change mapping below is `REVIEWER_REPORTED` (supplied by the independent reviewer; no per-number marker was found in the repository). §4.1–§4.8 describe the resulting behavior and limitations by component. These corrections establish no legal approval.

| Item | Defect or gap | Correction and remaining limit |
|---|---|---|
| F1 | Duplicate method declarations in `LegalRegistry` (surface and activations) | Validation helpers separated from the public accessors |
| F2 | `LegalFragment` regular-expression and Unicode validation defects | Regex delimiters corrected; PCRE errors explicitly rejected; disallowed Unicode and control code points handled |
| F3 | Unsafe output-publication fallback | Rename fallback removed; publication is hard-link-only and fail-closed; pre-existing files are not overwritten. The unsupported-filesystem negative scenario was **not** executed (§8) |
| F4 | Path collision and Windows alias handling | Shared path validation, case folding, implicit-parent conflict detection and Windows alias keys, applied to the TAR reader and the preactivation archive guard. Not a proof of complete NTFS or Unicode-normalization equivalence |
| F5 | Missing mandatory policy reference; insufficient registry binding of fragment rendering | Every consent version must reference `personal-data-policy` through `POLICY`; rendering is bound to the exact pinned version bytes and registry-controlled link targets. The historical mutable-public-URL limitation remains |
| F6 | Insufficient aggregate resource bounds | Bounded TAR metadata and path indexes, registry and retained-fragment limits, Git-output parsing, diagnostics and content delivery. No general total TAR or disk consumption bound is claimed |
| F7 | Git subprocess handling, cleanup and compressed-output integrity | Argument-array `proc_open` without shell interpretation; file-backed stdout/stderr; checked process results and I/O; invocation-owned cleanup; strict single-member GZIP verification. External termination, external mutation and filesystem failures remain residual limitations |

### 4.1 Legal registry (`LegalRegistry`)

- Pure validator of exact registry bytes: no Laravel, file, database, environment or configuration access; no writer, no runtime activation.
- Canonical representation only: printable ASCII and LF, no BOM, one final LF, at most 64 KiB, byte-identical to the canonical re-encoding (rejects duplicate member names, alternative whitespace and escapes).
- Exact field sets in exact order, strict types, no coercion.
- Exactly the six document keys in fixed order: `inquiry-consent-text` bound to the route `personal_data_consent.info`, and five public-file documents bound to the five existing PDF paths.
- Versions: sequential `rev-NNNN`, `locked: true`, `approval_reference` (structurally valid, unique within a document), content type by surface, derived artifact path, pinned SHA-256 and byte size. At most 32 versions for the page document.
- Activations: at least one; non-decreasing real calendar `effective_from`; every version activated, first activations in version order; current version = last activation; restrictions forbid a version from being current.
- Links: only page versions carry them, only to public-file documents, to an existing version; the current consent version must link the current version of each target; **every** consent version (historical included) links `personal-data-policy` exactly once through the placeholder `POLICY`.
- The approved-version structure is a **technical contract only**: the code establishes no actual approval or authority.

### 4.2 Legal fragment (`LegalFragment`)

- Closed grammar of literal tokens (`h1`, `h2`, `p`, one exact anchor form); not an HTML parser or sanitizer. One leading `h1`, canonical spacing, no `<`, `>`, `&` or `%` in text, an explicit disallowed code-point list, at most 64 KiB.
- Every declared placeholder is used exactly once. A PCRE error is an explicit rejection.
- Rendering is registry-bound: the bytes must equal the pin of the named page version; link URLs come from the registry binding, never from a caller; text and URL are escaped; a trusted asset root is shape-checked.

### 4.3 Errors (`LegalArchiveException`)

Machine-readable reason code and a short bounded structural detail; document text is not placed in the message.

### 4.4 TAR reader and GZIP verifier (`scripts/release/read-tar.php`)

- Accepts only the `git archive` subset: POSIX ustar with verified checksum, regular files and directories, a bounded PAX subset; links, devices and other types are rejected; strict termination.
- One path contract (UTF-8, no control characters, backslash, absolute or dot segments, Windows-reinterpreted names); case-folding and file/directory collisions rejected; separate Windows alias detection key.
- Entry data is streamed and SHA-256-hashed; entry count, depth, path bytes, index size and delivered content are bounded; 64-bit PHP and `mbstring` required.
- `ReleaseGzipVerifier`: exactly one gzip member without name or timestamp, expected length and SHA-256, nothing after it.

### 4.5 Package checker (`scripts/release/legal-package-check.php`) — foundation only

From one TAR in one pass: exactly one valid registry; exact archive inventory; size and SHA-256 of every artifact; every fragment valid; exactly the five registered PDFs under `public/documents/`; each public PDF byte-identical to the current archived version. It is covered by **synthetic** tests and is **not wired** into the release builder or any runtime.

### 4.6 Release builder (`build-public-package.php`)

- Resolves the commit first and reads the manifest from **that** revision, with field and regex validation.
- Reports `legal_archive=NOT_ACTIVE` (console line and file listing) and fails if the selected commit's **complete tree** has any entry at or below `resources/legal-archive`, including case variants and Windows-equivalent spellings; paths under `resources/` must satisfy the shared path rules.
- git is started without a shell; reads, writes, closes and process results are checked; owned temporary and partial files are removed on failure.
- Validated TAR bytes are the compressed bytes (hash-checked), then GZIP-verified; same-revision output is reproducible.
- Never overwrites: pre-existing outputs are refused; outputs are exclusively created partials published **by hard link only**, with no rename or copy fallback; the package file is published last; the output directory must be outside the repository.

### 4.7 What is **not** implemented

No application legal registry or archive, no archived legal text, no runtime loader or versioned rendering, no consent receipts, no approved legal text, no active archive package validation. No application code outside `app/Services/Legal/` refers to the new classes.

### 4.8 Residual limitations

- No absolute immutability. One registry is validated at a time: append-only history across revisions is **not** checked; owner-held external pins do not exist yet.
- `approval_reference` and `effective_from` are structural. "Current" is the last activation; `effective_from` is **not** compared with a clock, so a future-dated activation would already be current.
- **Historical fragment rendering is not a safe historical runtime contract:** an older version's links resolve to the *current mutable* public PDF path, not to the archived linked version.
- The unchanged manifest's pathspecs and allowlist do not include `resources/legal-archive`; S1-B must add it together with the guard removal and checker wiring.
- An S1-A test deliberately asserts that the checker is not wired and that the builder reports `NOT_ACTIVE`; S1-B must replace that assertion explicitly.
- `.gitattributes` has no exact-byte rule yet for future archived artifacts.
- PDF content and metadata are not parsed or checked.
- The builder has no general bound on total TAR or disk consumption (`git archive` writes the whole TAR before validation).
- The content-scan rules apply only to files without a listed binary extension and of at most 2,000,000 bytes.
- External termination, concurrent external modification and filesystem failures are not universally recoverable.
- Windows path checks are not a full NTFS upcase, Unicode-normalization or 8.3 short-name equivalence proof.
- A successful package proves packaging integrity only — not legal approval, versioning or deployment readiness.

## 5. QA-FLAKY-01 — separate test-only checkpoint (`OWNER_CONSOLE`)

| Item | Value |
|---|---|
| Commit | `b71755b55ca4f04e3b9a0afc26a58249dd643a3b`, parent `1b6c5771…`; one file, +7/−1 |
| File / method | `tests/Feature/CabinetChatContinuityTest.php` / `test_tourist_chat_header_wraps_instead_of_squeezing_the_manager_name` |
| Committed blob | `ded7b6aff6d4c7edc79e3aa553452c63133c11a6` |
| Normalized LF SHA-256 | `54c6ff3d7629f6c1f9cb864368e6d718cb8f03f6be2361b2f3a51a09495edb18` |
| Focused QA | 30 tests / 141 assertions on each of PHP 8.3.36, 8.4.13, 8.5.12; no failures, errors or skips |

Root cause: the test searched rendered HTML for an unescaped random manager name; Faker occasionally produced an apostrophe (observed: `Novella O'Kon`), which Blade correctly escaped. A **test defect**, not an application escaping defect or a demonstrated PHP compatibility problem. The fix uses the synthetic name `QA O'Connor & Co` and asserts its escaped form inside the `tc-chat__header-main` heading (`VERIFIED_IN_REPO`). No application code changed; no assertion was removed without replacement. The full pre-commit and post-commit matrices then passed. **This specific flake is resolved.**

**Distinct recorded cases.** The roadmap separately records a Faker/apostrophe flakiness in `AdminCabinetE3RedesignTest`, corrected during E3-A5; it is a different test case from this one. Earlier references to a "Faker apostrophe flake" that name no test must not be assumed to identify either case; they stay as historical records. This record does not reopen the older work and does not claim that every possible Faker-related flake in the repository has been eliminated.

## 6. Accepted test evidence (`OWNER_CONSOLE`; PHPUnit 11.5.56)

| Focused set | Tests / assertions |
|---|---|
| `LegalRegistryTest` | 22 / 168 |
| `LegalFragmentTest` | 14 / 205 |
| `LegalPackageCheckTest` | 13 / 120 |
| `ReleaseTarReaderTest` | 26 / 392 |
| **New focused tests** | **75 / 885** |
| Existing `PublicReleasePackagingTest` | 5 / 110 |
| **Combined focused** | **80 / 995 — PASS** |

PHP lint: 10/10 reviewed implementation files PASS.

| Full matrix | PHP 8.3.36 | PHP 8.4.13 | PHP 8.5.12 |
|---|---|---|---|
| Final accepted pre-commit | 2731 / 21592 PASS | 2731 / 21592 PASS | 2731 / 21592 PASS |
| Final accepted post-commit at `7706517d` | 2731 / 21592 PASS | 2731 / 21592 PASS | 2731 / 21592 PASS |

Each full run: zero failures and errors; all 75 new tests, the corrected chat test and the mandatory committed packaging test passed; no planned exclusion or deferral; **one skipped test** — `Tests\Feature\PublicDocumentExposureTest::test_release_archives_exclude_editable_sources_but_keep_pdfs_and_assets` («archive listing unavailable (tar/git)»); **one** existing PHPUnit XML-schema deprecation. The explicit package inspection (§9) checked real TAR contents, but that PHPUnit test **remains skipped**.

Isolation: SQLite `:memory:` only; temporary synthetic `.env.testing`; 3G memory-limit override; `XDEBUG_MODE=off` with the effective mode verified; array mail/cache/session; synchronous queue; temporary environment removed and process environment restored; no global PHP configuration change; no authorized access to canonical MySQL, WAMP database ports, production or the VPS. This was application-level configuration and preflight verification, **not** an OS-level network sandbox.

**Earlier unsuccessful attempts (historical, distinct from final acceptance):**

1. Stopped on the default 128 MiB PHP memory limit in an existing document-upload test.
2. Stopped in an incorrect Xdebug preflight that inspected the configured rather than the effective mode.
3. PHP 8.5.12 and 8.4.13 passed; PHP 8.3.36 hit the pre-existing Faker apostrophe defect (§5).

All three were resolved before the final accepted matrices.

## 7. Old/new builder regression (`OWNER_CONSOLE`)

Selected revision `b89dd39acb7ed37abd4b67a7890f08e9581b33b5`; PHP 8.5.12; Git 2.51.0.windows.1. Original builder blob `b1a23f715c57fb9d301a88caa6d8cee704c24598`, SHA-256 `cf3fb0e593f4c198284862defe77f278a7d67d3e72786ebedf77c2adce5a7633`.

The first comparison attempt stopped before either builder ran, because `git archive` exported the two historical text files with CRLF instead of the raw blob bytes. Both CRLF variants were identified; the exact old builder and manifest were restored with binary `git cat-file blob` reads in a disposable local clone. No global Git configuration changed.

| Result (old and new builder) | Value |
|---|---|
| Files | 793 |
| GZIP bytes | 112205883 |
| Common SHA-256 | `f95a4baab8dd3ffde324b77846aec8806541ef0a04c1d5f0f381f9abfd88550e` |

Identical size and SHA-256; checksum sidecars matched; the only listing difference is the new line `legal_archive NOT_ACTIVE`; all required legacy PDFs listed; editable sources excluded; temporary files cleaned. This is **same-environment** reproducibility, not cross-platform.

## 8. Builder negative QA (`OWNER_CONSOLE`, PHP 8.5.12)

All nine scenarios ended in the expected controlled failure with exit code 1 and the expected diagnostics:

| ID | Scenario |
|---|---|
| N01 | existing complete package |
| N02 | existing final gzip |
| N03 | foreign partial file |
| N04 | invalid revision |
| N05 | output inside the repository |
| N06 | canonical premature legal archive |
| N07 | case-variant premature legal archive |
| N08 | forbidden file path |
| N09 | forbidden synthetic content marker |

Existing outputs were preserved and owned temporary files cleaned. Four synthetic fixture commits existed only as objects in the disposable clone; its refs and default index were unchanged; nothing entered the main repository or was pushed.

**NOT TESTED:** an output filesystem without hard-link support. Successful hard-link publication was exercised by the positive builds only.

## 9. Explicit committed-package gate — PASS (`OWNER_CONSOLE`)

Candidate `7706517ddc663e0b8b6787e42a281445514a0ec0`. Two separately generated, retained packages, identical by size and SHA-256:

| Item | Value |
|---|---|
| Files | 796 |
| GZIP bytes | 112218399 |
| GZIP SHA-256 | `00bd9bb25ad50a4cd05f8cc8efd71e1e9f4d2dd4f7d81a7bbb2e8108dbcbd236` |
| Expanded TAR bytes | 115783680 |
| TAR SHA-256 | `345141ea3ad244635f512564456d4b6076e53e5a8b6e0eca34c1ace5d434d5e9` |

Archive inspection: historical TAR 793 files, candidates A and B 796 each; all three inventories matched their listings; GZIP round trips and archive commit identities passed; A and B inventories matched; all 793 previous regular-file identities matched by relative path, size, SHA-256 and mode; no historical file removed; exactly three new runtime files; all five historical PDFs matched their size and SHA-256 pins (920852 bytes total, [prerequisites §8.3](NOV-PUBLIC-LEGAL-01-s0-legal-evidence-prerequisites-2026-11-24.md)); no editable source, prohibited extension, development path or legal archive; temporary files cleaned.

| New runtime file as exported (CRLF) | Bytes | SHA-256 |
|---|---|---|
| `LegalRegistry.php` | 27514 | `c2ec7738a6a7b39d3ddc585ba415d9e4fdb09ecba6393c58864180b49bdcc8b9` |
| `LegalFragment.php` | 19355 | `adedcadaa03a57568b9a703ce74027729231d29115dfd1d14bc45e832fefb0b8` |
| `LegalArchiveException.php` | 890 | `d3121795a9baac4d38f2cd179f3882bbf4e506aadf2c9d28a5712956eaf631ed` |

The expected CRLF hashes were calculated independently from the reviewed LF source bytes and matched. This known **text** export transformation is **not** a permitted transformation of PDF or future archived legal-content bytes.

The inspection used the reviewed `ReleaseTarReader` and `ReleaseGzipVerifier`, **not** an independent second TAR parser. `REVIEWER_REPORTED`: reviewed source and PDF hashes were matched against the owner-console inspection evidence; the reviewer did not directly inspect the two retained `.tar.gz` files. The final guarded push rechecked both package hashes and their unchanged outputs.

## 10. R-T1 and residual risks

For the current checkpoint `7706517d`, the earlier stale-HEAD packaging limitation is superseded by: post-commit full PHPUnit on three PHP versions, mandatory committed packaging tests, explicit committed-package reproducibility and actual TAR inspection.

This does **not** mean that the historical OFFICE-01 commit `59d9c229` was separately rebuilt or retested after commit.

R-T1 stays tracked (ID retained) with: the PHPUnit archive-exposure skip; the PHPUnit XML-schema deprecation; same-environment reproducibility only; hard-link-unsupported filesystem failure `NOT_TESTED`. Other future release and test obligations are unchanged. Implementation limitations: §4.8.

## 11. S1-B transition and legal boundaries

**State:** S1 `OPEN`; R-LG2 `OPEN` until the complete S1-A + S1-B archive/versioning contract is implemented and verified; DR-08 `OPEN`; DR-09 partially evidenced, not globally closed; R-L1, N-4, the G7 legal part and the other R-LG risks stay open.

The owner-confirmed scope adjustment of [prerequisites §8](NOV-PUBLIC-LEGAL-01-s0-legal-evidence-prerequisites-2026-11-24.md) remains authoritative and is not reopened: five legacy PDFs preserved privately outside Git; no duplicate legacy archive in the application; public PDFs unchanged until a separately approved replacement; Git history preserved; the application archive begins with approved new revisions; private legacy evidence is not a runtime dependency. Private legacy manifest SHA-256: `62a768bba6168a64fe1c60ef2f9dee5aa5ff8099d9b66f5f273a43524ffeef41`. Independent backup and access-control verification remain unresolved. Evidence C (before-change consent-page rendering baseline) stays `PENDING`.

Seven working legal-document pairs exist outside Git. They are **not** approved or published. S1-B requires actual owner/counsel acceptance **and** separate implementation authorization.

**S1-B architecture — `PROPOSED`, `NOT_IMPLEMENTED`, not authorized:**

- initial application registry for all six public legal-document identities; first approved revisions, proposed `rev-0001`;
- five approved PDF replacements at their existing public URLs, with exact archived approved copies;
- consent-page HTML as the archived application content; the consent DOCX/PDF master retained privately with explicit content linkage and hash evidence;
- archive binary/exact-byte handling before staging;
- approved labels, approval references, authority, version dates and activation decisions;
- runtime loader with current-version resolution and correct treatment of historical document references;
- mandatory active archive package validation; removal of the temporary `NOT_ACTIVE` branch;
- first owner-held external history/version pins; exact current PDF/archive identity;
- explicitly scoped reconciliation of public labels, dates, metadata and existing legal-wording test assertions;
- required committed-package QA and evidence C.

**Next sequence (as planned when this record was prepared):** (1) this documentation reconciliation → (2) independent diff/content review → (3) owner-run guarded documentation commit and push → (4) verified exact five-file Project Sources refresh → (5) owner confirmation of promotion → (6) new ChatGPT chat → (7) legal-document approval/readiness review for S1-B → (8) separate S1-B authorization, only after the necessary prerequisites. S1-B does not start automatically; S2–S9 stay individually unauthorized.

**Project Sources:** the active set is still `b89dd39a` and is deliberately stale until the documentation checkpoint exists and a separate refresh and promotion are verified.

## 12. Unchanged

S0 stays CLOSED AND PUSHED. Stage 0–13, E1–E4 and E5 application closure, E5.5 active, AOA-01…04 closed, AOA-05 active, AOA-06…18 unstarted, E6 pending, NOV-PUBLIC closure history, L2 not implemented. P5/P7/P8, BK-1…BK-3 and BK4A-D1/D2 are not reopened; BK-4A stays OPEN / BLOCKED / TEMPORARILY PAUSED; no infrastructure operation is authorized. ORG-RKN-01 stays a separate organizational task; the responsible-person appointment is unresolved; the owner-confirmed MAG.Travel closure stands without independent provider or destruction evidence. The 12-month rule applies only to non-contract inquiries from the last substantive interaction. Public e-mail, internal mail recipient and the approved office wording are unchanged. Production **NOT DEPLOYED / NO-GO**.
