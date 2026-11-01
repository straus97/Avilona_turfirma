# NOV-PUBLIC-04A — Offline public content recovery and safe import preparation

Date: **2026-11-01** · Branch `db-rebuild-stage3` · Base HEAD `1ca476ac5281b9287ebb4cbcf9d277fbca3ffbd9`

> **Status authority:** [`docs/roadmap.md`](roadmap.md) is the only canonical status document. This file is the evidence, design record and runbook for NOV-PUBLIC-04A and defers to the roadmap for status.
>
> **Offline content-preparation slice. Nothing was imported, nothing was deployed.** No database of any kind was opened (no WAMP 3306/3308, no `turfirma_rebuild_v4`, no `.env` connection, no hosting account, no search-engine account). The old SQL dump was never executed against anything.
>
> **The two owner inputs were NOT present in the expected location. No real content was read, extracted or counted in this slice.** This slice delivers the *tooling* and proves it on synthetic data; the real-data run is the first step after the files are placed (§12).

## 1. Repository guards (start of slice)

Branch `db-rebuild-stage3`; local HEAD = tracking HEAD = live `origin` HEAD = `1ca476ac5281b9287ebb4cbcf9d277fbca3ffbd9`; staging empty; only `.claude/` untracked. Baseline 2586 tests / 20107 assertions (NOV-PUBLIC-03A-F1).

## 2. Input inventory (precise)

| Expected | Result |
|---|---|
| `C:\Avilona_private\PublicContentIncoming\` | **does not exist** |
| `u0588341_turfirma(1).sql` | **absent** |
| `img.zip` | **absent** (searched to depth 3: `C:\Avilona_private`, Downloads, Desktop, Documents — no `img*.zip` anywhere) |

One look-alike exists outside the designated directory: `C:\Users\nikita\Downloads\u0588341_turfirma.sql` (2 287 498 bytes, modified 2026-06-24 16:45, header `phpMyAdmin SQL Dump`). Its name differs from the supplied `…(1).sql`, it is outside the private input directory, and its identity was **not** confirmed, so it was **not opened beyond file metadata and the first header line** and was not processed. Owner decision O-1 (§11) says whether it is the same dump.

Per the task's fallback the slice continued as code-only analysis. The original dump is never copied into Git; the tool refuses to read a dump (or archive, or write results) inside the repository working tree.

## 3. What was built

| Piece | Path | Role |
|---|---|---|
| Parser | `app/Services/Content/Legacy/LegacySqlDumpParser.php` | Treats the dump as **text/data**: scans `CREATE TABLE` (column names) and `INSERT … VALUES`. No execution, no DB connection, no `eval`, no restore. |
| Table policy | `…/LegacyTablePolicy.php` | Strict classification: `extract` / `inventory` / `private` / `excluded` / `unknown`. |
| Extractor | `…/LegacyContentExtractor.php` | Maps the three allowlisted tables to `avilona-public-content/1`, holds rows the importer would reject, summarises inventory-only tables. |
| Content checks | `…/LegacyContentChecks.php` | Detects (never rewrites) malformed/unsafe HTML, outdated links, past years/prices/rules, mojibake. |
| Image inspector | `…/LegacyImageArchiveInspector.php` | Inventories `img.zip` **without extracting to disk**. |
| URL reconciler | `…/LegacyUrlReconciler.php` | Compares recovered slugs with `resources/seo/legacy-sitemap-paths.txt`; invents no redirect. |
| Pipeline | `…/LegacyPublicContentPipeline.php` | Orchestrates, redacts portrait file names, writes the private report set. |
| Command | `app/Console/Commands/ExtractLegacyPublicContentCommand.php` | `php artisan content:extract-legacy-dump` |
| Contract | `PublicContentImporter::SCHEMA` (private → `public` const) | The only change to existing code: the extractor uses the importer's own schema as the single source of truth. No behaviour change. |

### Run (when the inputs exist)

```text
php artisan content:extract-legacy-dump "C:\Avilona_private\PublicContentIncoming\u0588341_turfirma(1).sql" ^
    --images="C:\Avilona_private\PublicContentIncoming\img.zip" ^
    --out="C:\Avilona_private\PublicContentIncoming\out-<date>"
php artisan content:import-public "<out>\public-content.json"          # dry-run only; --commit is NOT part of this slice
```

Output directory (new or empty, **outside** the repository): `report.json` (full private review data), `report.md` (counts and codes), `public-content.json` (only if no unknown table blocks), `asset-manifest.json` (only with `--images`). Console output contains counts and codes only. If the dump has tables the policy does not know, **all public output is withheld** until the owner names them explicitly with `--acknowledge-unknown=a,b` (acknowledgement cannot unlock a private table).

## 4. Public table / field mapping

The extractor reuses `PublicContentImporter::SCHEMA`, so the mapping *is* the import contract. Source column names are **assumed equal to the application's own migrations** (the old site is the earlier version of this application). That assumption is **unverified until the real dump is read**; a mismatch is reported (missing required column ⇒ table not emitted and listed; unmapped columns ⇒ dropped and listed by *name only*).

| Source table | Required | Optional | HTML (kept verbatim, sanitised by the importer) | Image columns |
|---|---|---|---|---|
| `countries_images` | title, slug, category, description | image_small, image_large, documents_url (https) | description | image_small, image_large |
| `destination_images` | title, slug, description | image_small, image_large | description | image_small, image_large |
| `articles` | title, slug, content | image | content | image |

Also carried when present and valid: `created_at`, `updated_at` (original dates; MySQL zero dates are omitted, never invented). `id` is never carried (ids are recreated; see §9, the numeric-URL rule). Rows with a non-empty `deleted_at` are excluded and counted. **Titles, slugs, HTML and image references are transferred unchanged.**

A row is **held** (excluded from the file, listed with a code and its coordinates `table#row`/slug) when the importer would reject it: `required_blank:<col>`, `too_long:<col>` (>255), `invalid_slug`, `invalid_image_ref:<col>` (importer's character rule — e.g. Cyrillic or spaces in a file name, `..`), `invalid_documents_url`, `invalid_timestamp:<col>`, `invalid_utf8:<col>`, and `duplicate_slug` (exact **and** case-insensitive, because the MySQL unique index is case-insensitive; **all** members of a group are held — choosing one is an editorial decision). Nothing is repaired. A test proves a held row is rejected by the real importer and an emitted row passes it.

## 5. Excluded categories

* **Never read** (values are not even materialised in memory; only a row count is kept): `users`, `password_resets`, `password_reset_tokens`, `personal_access_tokens`, `role_user`, `roles`, `notifications`, `profiles`, `bookings`, `booking_documents`, `booking_trip_reminder_deliveries`, `bonus_accounts`, `bonus_transactions`, `client_verification_requests`, `incoming_inquiries`, `inquiries`, `inquiry_contacts`, `messages`, `review_consents`, `user_documents`, `user_registration_consents`, `audit_events`, `sessions`; known system/non-public tables `migrations`, `failed_jobs`, `jobs`, `job_batches`, `cache`, `cache_locks`, `tours`, `tour_operators`, `destination_cities`.
* **Unknown table ⇒ fail closed** (no public file, exit 1, names and column names — never values — reported).
* **Inventory only — counted, not published, owner decision required:** `news` (RSS: freshness, attribution, redistribution rights; the summary reports rows without a source link, `pub_date` range, rows older than 12 months and source host counts), `our_clients` (special offers), `best_offers`, `partners`, `awards`, `employees` (staff personal data and portraits), `reviews` (**values not read at all**; consent evidence needed). Only the listed image columns of the non-review inventory tables are read, to build the asset owner-review list.
* The parser refuses the whole file on any statement other than `CREATE TABLE`, `INSERT … VALUES` and a short list of inert statements (`SET`, `START`, `BEGIN`, `COMMIT`, `LOCK`, `UNLOCK`, `USE`, `ALTER`, `DROP`, `CREATE DATABASE`): `UPDATE`, `DELETE`, `TRUNCATE`, `GRANT`, `DELIMITER`, triggers, views, users, `INSERT … SELECT`, `ON DUPLICATE KEY`, function calls in values. Skipped statements are scanned for their terminating `;` and never run.
* Error messages contain a reason code, statement number, line and table name only — never row text.

## 6. Import-format compatibility

`public-content.json` is exactly `{"format": "avilona-public-content/1", "tables": {…}}` for the three tables, with only importer-known keys. A test extracts a synthetic dump, runs the **current** `content:import-public` (dry-run, then `--commit` into the disposable SQLite), renders the pages and reads the dynamic sitemap. Local image references are left as found; the importer normalises them to root-absolute paths (`img/x.png` → `/img/x.png`) and warns when the file is not in `public/`. **Placement rule for later:** the final file location is `public/` + the normalised reference, so the recovered images must be copied to those exact relative paths (the repository already stores flat country images under `public/img/countries_image/`). Image staging/copying is deliberately **not** part of this slice (the manifest gives the exact target path per reference).

## 7. Image archive rules (no unsafe extraction)

Entries are read into memory in bounded chunks through `ZipArchive`; **nothing is written to disk** (a test snapshots the directories before/after and checks that `../evil.png` is not created). Entry names with `..`, `.` segments, a leading `/`, a drive letter, a backslash, NUL/control characters, over 260 characters, symlink entries, encrypted entries and duplicate names are flagged and never read or matched. Limits: ≤ 20 000 entries, ≤ 25 MB per entry, ≤ 2 GB total, ≤ 100 Mpx, compression ratio flag at 200×. Allowed image types `jpg/jpeg/png/gif/webp`, and the decoded type must match the extension; PHP-like content in an image, non-images with image extensions, `.php/.html/.js/.svg/.exe…` are `suspicious`; `psd/psb/ai/xcf/tif/indd/sketch/cdr/eps/raw` are `editable_source` — **never publishable**.

For every reference the report says: `present` (with dimensions), `missing`, `case_mismatch` (exists only with different letter case — a Linux hosting risk), `ambiguous`. Matching tolerates a leading `/`, `public/`, and an archive rooted inside `img/`. It also lists unreferenced images, extension counts, dimensions and SHA-256 per file, and images embedded in article HTML.

**Missing large-country variants:** the initial independent inventory reported 54 missing `countries_image/large/…` references. **This slice could not verify that number** (no archive). The tool reports every missing reference grouped by directory (`missing_by_directory`) and a dedicated `large_variants` section: each affected row with the status and **dimensions of its small image**. Small images are **never up-scaled** to stand in for large photographs. Finding to keep in mind: the country/destination detail templates print `image_large ?: image_small`, so a *non-empty but missing* `image_large` gives a broken `<img>` even though the small image exists. Handling is an owner decision (O-4) and no data is changed by the tool.

**Portraits:** staff and customer portraits are never published by this slice. File names can contain personal names, so in `report.json`, `report.md` and `asset-manifest.json` every portrait entry (directory names `personal/employees/staff/team/reviews/users/avatars…`, or a file referenced only by `employees`) is listed as `<first two directories>/[portrait-<sha256 prefix>].<ext>`; the owner finds the file by hash or directory. A file that public content also references is not hidden. Tests prove no private marker, including a marker in a portrait file name, reaches any written file or the console.

## 8. Content-quality detection (nothing is rewritten)

Per emitted row, counted by code with coordinates in `report.json`: `unsafe_markup` (script/iframe/object/embed/form/style, `on*=` handlers, `javascript:`/`data:` URLs), `malformed_html` (unbalanced tags, dangling `<`), `tags_dropped_by_sanitizer` (tag names `NewsHtmlSanitizer` will remove, e.g. `table`, `div`, `h1`), `insecure_http_link`, `www_host_link`, `outdated_internal_link` (internal link whose route/document no longer exists, classified with the production `LegacyUrlClassifier`), `past_year_reference`, `price_reference`, `rules_reference` (visa/COVID/PCR/entry-rule wording), `possible_mojibake`, `replacement_character`, `title_has_markup`, `empty_text`, `missing_publication_date`, plus duplicate slugs and blank/invalid titles in §4. The importer still sanitises HTML at import; the extractor deliberately keeps the original markup so the owner sees what the old site contained.

## 9. Legacy SEO reconciliation

Input: `resources/seo/legacy-sitemap-paths.txt` (272 URLs). Each path is classified with `LegacyUrlClassifier` and gets one status:

| Status | Meaning |
|---|---|
| `exact_match` | recovered, importable record with that exact slug — the historical URL is preserved unchanged |
| `exact_match_image_missing` | same, but at least one of the record's images is missing from the archive |
| `published_content_missing` | the route exists, the dump has no importable record with that slug (reason says if the whole table is absent) |
| `held_by_quality_gate` | a record exists but was held (§4) |
| `unresolved_mapping` | not provable: numeric `/countries/{id}` (never mapped to a slug), a slug that differs only by letter case, news/special-offer detail pages (tables not in this slice), private/no-route paths |
| `static_or_list_page` | page does not depend on recovered records |

No redirect is generated, no missing page is sent to the home page, no id→slug pair is guessed. `recovered_slugs_without_legacy_url` counts recovered records that were never in the old sitemap (informational: the old sitemap was curated). Without an archive the image part of the status is reported as `not_run`.

## 10. Verification

* **Synthetic only** — all fixtures are marked `SYNTHETIC`; "private" tables carry `PRIVATE-MARKER-*` values that the tests assert never appear in the extraction result, the written files or the console. Disposable SQLite `:memory:` only.
* New tests (41): `LegacySqlDumpParserTest` (7), `LegacyContentExtractorTest` (17), `LegacyImageArchiveInspectorTest` (7), `LegacyPublicContentPipelineTest` (10), helper trait `BuildsSyntheticLegacyDump`.
  * Parser safety: escapes, doubled quotes, `;` and comment markers inside strings, hex/BOM, 12 refused statement forms, 11 malformed forms with marker-free messages, size limit, **no DB queries** (`DB::listen`), counting without materialising private values.
  * Allowlist/closed-by-default, unknown-table refusal and explicit acknowledgement, private tables cannot be unlocked, inventory not published, soft-deleted excluded, verbatim transfer, duplicate-slug (exact and case) holding, importer-parity holds, zero dates, schema mismatch, unsafe-HTML flagged but not rewritten, quality flags.
  * Image archive: present/missing/case/ambiguous, rooted archives, hostile names, no extraction, polyglot/mismatch/symlink/oversize/total-size, portrait flagging.
  * Pipeline/command: URL reconciliation statuses, written-file leak scan, portrait redaction, repository-path refusal, non-empty-output refusal, malformed-dump refusal, zero queries, **importer dry-run + commit + render + dynamic sitemap preserving historical URLs**.
  * **Mutation checks** performed by hand: disabling portrait redaction, allowlisting `users`, and disabling the repository-path guard each make tests fail.
* Parser throughput check (not a test): a 23 MB synthetic phpMyAdmin-style dump parses in ≈ 2.2 s at ≈ 110 MB peak.
* Full PHPUnit (SQLite `:memory:`, Xdebug off, `memory_limit=3G`, sequential): **2627 tests / 20479 assertions, 0 failures, 0 errors** on each of PHP 8.3.36 (checksum-verified scratch build), 8.4.13 and 8.5.12 (+41 / +372 over 03A-F1; same single PHPUnit config-schema deprecation notice).
* Browser QA: **not required** — no public route or rendering behaviour changed (the importer's `SCHEMA` constant is the only existing-code change).

## 11. Owner decisions and evidence (not guessed)

| ID | Item |
|---|---|
| O-1 | Confirm whether `C:\Users\nikita\Downloads\u0588341_turfirma.sql` is the supplied dump; place the real files at `C:\Avilona_private\PublicContentIncoming\` (`u0588341_turfirma(1).sql`, `img.zip`) — or authorise reading the Downloads copy |
| O-2 | `news`: attribution, freshness and redistribution rights for RSS-sourced items before any publication |
| O-3 | `employees`: approval to publish staff data and portraits; `reviews`: consent evidence (`review_consents`) and approval; `partners`, `awards`, `best_offers`, `our_clients`: confirmation |
| O-4 | Missing large-country photographs: supply the originals, or approve emptying `image_large` for those rows so the small image is used (a template fallback) |
| O-5 | Held records (duplicate slugs, invalid image names, blank titles…) — choose/rename/fix at the source |
| O-6 | Past-year, price and rule statements flagged in the text — editorial review |
| O-7 | `.psd` and other editable sources found in the archive stay out of the release package (see also N-10) |
| O-8 | Whether to keep an unknown table name as non-public (`--acknowledge-unknown`) |

## 12. Next steps

1. **Does not need hosting:** place the two files in `C:\Avilona_private\PublicContentIncoming\` (or answer O-1) and run the command in §3 → NOV-PUBLIC-04B: review `report.md`/`report.json`, resolve O-4/O-5/O-6, re-run until the dry-run of `content:import-public` is clean. Only counts and codes should be pasted into chats; `report.json` and `asset-manifest.json` are private working files.
2. **After the hosting purchase:** NOV-PUBLIC-03B — copy the approved images to their `public/` paths, import into the *empty* target with `SEO_NOINDEX=true` on staging, run `seo:audit-urls --check-db` against a disposable QA copy, then the owner's cut-over gates.

Nothing in this slice marks content import, hosting, deployment or November release as done. AOA-05 ACTIVE · E5.5 ACTIVE · E6 PENDING · production NOT DEPLOYED.
