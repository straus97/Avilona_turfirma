# NOV-PUBLIC-04B — Public content quality, recovery gaps and safe import readiness

Date: **2026-11-14** · Branch `db-rebuild-stage3` · Base HEAD `82d7fc38dd7acc7005e9cc31fb678446f9e9d14f`

> **Status authority:** [`docs/roadmap.md`](roadmap.md) is the only canonical status document. This file is the evidence and design record for NOV-PUBLIC-04B and defers to the roadmap for status.
>
> **Local, offline slice. Nothing was imported into any real database, nothing was deployed, no live content was published.** The central VPS was **not** accessed or modified. No `.env` connection, no `turfirma_rebuild_v4`, no port 3306/3308 was used: every database-backed step ran on a disposable SQLite file after the resolved connection identity was verified.
>
> **No real extracted content is in Git.** The derived files live only in the private directory `C:\Avilona_private\PublicContentIncoming\out-20261114-04b\`.

## 1. Repository guards

Branch `db-rebuild-stage3`; local HEAD = tracking HEAD = live `origin` HEAD = `82d7fc38dd7acc7005e9cc31fb678446f9e9d14f`; staging empty; tracked tree clean; only `.claude/` untracked. Baseline 2627 tests / 20479 assertions.

## 2. Contracts (verified by reading the code)

* **Importer** (`PublicContentImporter::SCHEMA`): for `countries_images` the columns `image_small`, `image_large` and `documents_url` are **optional**; a missing or empty value becomes `NULL`. `documents_url`, when present, must be an `https://` URL. So a null `documents_url` is explicitly allowed — no schema/business blocker.
* **`content:import-public` "dry-run" is not file-only:** `validate()` calls `checkSlugCollisions()`, which queries the default database. It must never be run with the default `.env` connection for a rehearsal.
* **Views:** the country and destination detail pages used `image_large ?: image_small` inside the `.e2-detail-media` frame (16:9, 21:9 from 768 px, `width:100%`, `object-fit: cover`). A 270×200 thumbnail was therefore upscaled ≈3× and cropped into a strip; a non-empty `image_large` pointing to a missing file rendered a broken `<img>`.
* **Earlier decision:** `ImportLegacyDataToV4` already treated the exact value `/documents/countries/` as a "known null pattern" and planned missing large files as null.

## 3. Invalid `documents_url` handling

Source (parsed as text, never executed): of 55 `countries_images` rows, **33** carry exactly `/documents/countries/` and **22** are NULL. There is **no** valid document URL anywhere, so nothing valid can be removed.

New opt-in option `--normalize-documents-url` (`LegacyContentExtractor::DOCUMENTS_URL_DIRECTORY_PLACEHOLDERS`): only the **exact** value `/documents/countries/` becomes empty in the derived file. Any other value is untouched: a real https URL is kept byte for byte, `/documents/other/` and `/documents/countries/file.pdf` are still held as `invalid_documents_url`. No replacement URL is invented. The original SQL is never modified. Every change is recorded (table, slug, source value, output value, rule, rationale) in the private `normalization-evidence.json`.

Result: the 33 held rows are accepted; **55 source = 0 soft-deleted + 55 accepted + 0 held** (balanced). 55 is a consequence of the evidence, not a target.

## 4. Image fallback policy

* Option `--null-missing-large` (requires `--images`): an `image_large` that is **absent from the supplied archive** becomes empty, only when the row's `image_small` is present. The source reference is preserved only in the private evidence (with the small image's real dimensions). No file is fabricated and the thumbnail is not copied into the large field. If the small image is also missing, the row is left alone for an owner decision.
* Result: of 55 rows, **54** referenced a large file that is not in `img.zip` (all 54 smalls exist, 270×200); one row had no large value at all. All 54 are nulled in the derived file; destination images are unchanged (their large files exist).
* **View change (tested):** new include `includes/e2-detail-hero-media.blade.php`, used by both detail pages. Large image → hero frame exactly as before. Only a small image → a natural-size slot (`.e2-detail-thumb`, `max-width: 270px`, `height:auto`, no cropping). Neither → the existing placeholder. Listing cards keep using `image_small`. Open item: the Open Graph image still falls back to the 270×200 thumbnail (`PublicSeo::imageUrl(large ?: small)`) — acceptable but small; the owner may supply large originals.
* The repository already holds `public/img/countries_image/large/abkhazia.jpg` (the earlier V4 replacement for the broken `abkhazia.png` reference). It is **not** applied automatically; whether that country may use it is an owner decision.
* PSD sources (`special/big.psd`, `special/little.psd`) are not publishable and are not referenced by any derived record.

## 5. Content quality gates (proposed classification — owner confirms)

Flags come from regex checks. The private `article-review.json` identifies items by public slug only, with no personal data.

| Flag (source) | Items | Proposed handling |
|---|---|---|
| Past-year reference (5 articles) | `…-straxovke-v-2024-godu` (insurance rules incl. COVID-19); `…-tailanda-9-rekomendacii…` (text says prices as of January 2019); `…-seifax-v-oteliax…` (2018 incident), `kak-soxranit-cemodan…` (dated statistics), `5-pricin-otdoxnut-v-anape` (2009 history) | first two **hold**; the other three **publish with note** (dates are historical facts) |
| Rules / visa (4) | `kak-izbezat-otkaza-v-vize` (visa), `cto-sleduet-znat-pri-vyezde…` (vaccination), insurance (above); `…-venecii…` ("quarantine" = 15th-century history, false positive) | first two **hold**; Venice **publish with note** |
| Prices (4 articles + 2 destinations) | `skolko-deneg-brat-s-soboi-v-tailand` (exchange rate), `kak-desevo-zvonit-s-kipra…` (tariffs), `cto-privezti-iz-grecii…` (52 price references), `cto-obiazatelno-privezti-iz-ispanii` (2 indicative prices); `zd-tury` (railway fares), `sop-tury` ("1 EUR" symbolic) | Thailand budget, Cyprus, Greece, `zd-tury` **hold**; Spain and `sop-tury` **publish with note** |
| Malformed HTML (3) | `10-privycek-…`, `ispaniia-lucsie-mesta-dlia-otdyxa`, Spain (above) | sanitizer repairs; sanitized text equals the source text → **publish with note** |
| Unsafe markup (1) / tags dropped by sanitizer (1) | one article: the only construct is a `https://www.youtube.com` `<iframe>`; no `<script>`, no `on*=`, no `javascript:` | sanitizer drops it (video not shown, text preserved) → **publish with note**. **Sanitization was not weakened.** |
| No publication date (43 articles) | — | **No date invented.** Article pages and structured data render no date; the importer stamps `created_at`/`updated_at` with the import time. Keep `SEO_SITEMAP_LASTMOD=false` until dates are honest |

Totals: 16 flagged items → **8 hold_owner_review** (7 articles + `zd-tury`), **8 publish_with_note**. Nothing was rewritten. The checks are heuristics: unflagged text is **not** verified as current; the owner should skim country and destination pages.

Minimum honest November set (private `public-content.november-candidate.json`): **articles 43, countries 55, destinations 11** (109 records); the 8 held records are quarantined, never deleted from the derived file `public-content.json`.

## 6. Image security verification

The earlier scan reported 14 suspicious entries: **13 `embedded_script`** (all PNG) + `favicon.ico` (unsupported type).

Verified on the real archive, read-only, nothing executed or written to disk:

* All **209** raster files: extension = signature (191 PNG, 18 JPEG); all PNG chunk CRCs valid; `IEND` last with **0 trailing bytes**; every JPEG ends at EOI; all 13 flagged files decode.
* **No `<?php` anywhere.** All 13 hits are the three bytes `<?=` (27 occurrences), **every one inside an `IDAT` (deflate) chunk**; none in metadata, none after `IEND`. Context after each hit: at most 19 of 32 printable bytes, no `?>` within 256 bytes. This is random compressed data (≈1 expected hit per ~16 MB), i.e. a **matching-heuristic false positive**.
* Archive paths: 0 unsafe names; `.psd` and `.ico` remain unpublishable. No output path is built from an archive name (the pipeline writes only JSON).

Scanner (`LegacyImageArchiveInspector::scriptSignature`): `<?php` (any case) stays an unconditional hold. `<?=` is accepted **only** for a PNG whose full structure verifies (signature, lengths, CRC, `IHDR` first, `IEND` last, nothing after it), where **every** hit lies inside `IDAT` data and is not payload-like (no `?>` in the next 256 bytes, fewer than 24 of the next 32 bytes printable). Otherwise it stays held: metadata chunk, trailing bytes, bad CRC, non-PNG, payload-like context. Tests cover both the accepted and the held cases. Result on the real archive: suspicious **14 → 1** (`favicon.ico`, unreferenced).

## 7. Private derived output

`C:\Avilona_private\PublicContentIncoming\out-20261114-04b\` (`out-20261113` untouched; the SQL and ZIP untouched):

| File | Content |
|---|---|
| `public-content.json` | derived exchange file: 50 articles, 55 countries, 12 destinations |
| `public-content.november-candidate.json` | the file above minus 8 quarantined records (43 / 55 / 11) |
| `normalization-evidence.json` | 87 transformations: 33 `documents_url`, 54 `image_large` (source value, output, rationale) |
| `article-review.json`, `candidate-reconciliation.json` | classification by slug; derived → candidate/quarantine reconciliation (balanced) |
| `legacy-url-reconciliation-candidate.json` | the 272 historical paths against the candidate |
| `report.json`, `report.md`, `asset-manifest.json` | pipeline report with a new `reconciliation` section; archive manifest |
| `harness/` | one-off scripts used for review, rehearsal and rendering (not part of the repository) |

Reconciliation (source → transformed → accepted/held): articles 50 → 0 → 50/0; countries 55 → 54 transformed (33 documents, 54 large) → 55/0; destinations 12 → 0 → 12/0. Remaining image references in the derived file: 129, **0 unresolved** against the archive manifest. Staff, reviews, tokens, users, messages, bookings and notifications were not read (counts only); news (138), reviews (46) and the other inventory tables stay unpublished.

## 8. Import validation and rehearsal (disposable SQLite)

Database identity was asserted before any query: driver `sqlite`, file inside the scratch directory, never the `.env` connection. After `migrate` (37 tables):

* `content:import-public` validation: derived file ok (50/55/12), candidate ok (43/55/11), 0 errors, **0 missing-image warnings** (the referenced small and destination images already exist in `public/img`).
* `--commit` of the candidate into the empty tables: 43 / 55 / 11 inserted; **re-commit refused** (109 "slug already exists" errors) and nothing was written (idempotency / non-empty refusal).
* No case-folded duplicate slugs; `documents_url` non-null: 0; `image_large` null: 55.
* **All 109 imported pages render HTTP 200** in-process: no bare `<img src="">`, no `<script>`/`<iframe>` in `<main>`; 55 country pages use the thumbnail slot, 54 pages (43 articles + 11 destinations) the hero frame. `sitemap.xml`: 124 unique URLs, 0 `lastmod`.
* Real content was **never** committed to any database other than this throwaway file.

## 9. Legacy SEO reconciliation (candidate set)

Of 272 historical paths: **109 exact matches** (every candidate record has a historical URL; recovered slugs without a legacy URL: 0), **49** static/list pages, **8 `held_by_quality_gate`** (the quarantined pages), **106 unresolved**: 100 news (not approved), 1 special offer, 5 document files. Missing page records: 0. Missing image variants: 54 country large images (the pages fall back to a small slot). **No redirect was invented** and nothing is redirected to the homepage; live indexing is **not** claimed to be restored. Owner-approved handling is needed for the 8 held pages (publish after review, or an approved 410/redirect), the 100 news URLs, the special offer and the 5 document files.

## 10. Code changes

`LegacyContentExtractor` (opt-in documents-URL normalization + evidence), `LegacyPublicContentPipeline` (options, missing-large nulling, evidence, reconciliation, evidence file), `LegacyImageArchiveInspector` (precise `<?=` rule), `ExtractLegacyPublicContentCommand` (two flags), `includes/e2-detail-hero-media.blade.php` + both detail views + `unified.css` (`.e2-detail-thumb`). New tests: `LegacyPublicContentNormalizationTest` (13), `PublicDetailMediaFallbackTest` (6). Importer and sanitizer unchanged.

## 11. Verification

* Focused: `LegacyPublicContentNormalizationTest` (13 tests, 68 assertions) and `PublicDetailMediaFallbackTest` (6 tests) plus the existing legacy-content, inspector, pipeline, rehearsal and robustness tests: green.
* Full PHPUnit (SQLite `:memory:`, Xdebug off, `memory_limit=3G`, run sequentially): **2646 tests / 20567 assertions, 0 failures, 0 errors** on PHP **8.3.36** (official `php-8.3.36-1-Win32-vs16-x64.zip`, SHA-256 `126dd3cc…ba92e` matched the official releases feed; scratch build, not installed), **8.4.13** and **8.5.12**; also 8.3.32 (the locally installed 8.3 build). +19 tests / +88 assertions over the 04A baseline 2627 / 20479; same single PHPUnit config-schema deprecation notice.
* Browser QA (views and CSS changed), synthetic data on a disposable SQLite DB, Chrome headless: country detail with large image, with small image only and with no image at **360 px (true 360 px viewport via iframe), 768 px and 1200 px**. Large → hero frame unchanged; small only → natural-size 270 px slot, no cropping or stretching, no horizontal overflow; none → existing placeholder. Method note: pages were captured from a local server and rendered as static copies with assets pointed at `public/`, because the single-threaded built-in server stalled Chrome's parallel asset requests; nothing external was contacted.

## 12. Owner decisions

D-1 confirm or change the proposed hold/publish classification (section 5); D-2 supply or approve missing large photos (54) and decide on `abkhazia.jpg`; D-3 approve the 33 country records without a memo document (or supply real document URLs); D-4 news (138, 100 legacy URLs), reviews, staff, partners, awards, special offers stay unpublished until approved; D-5 redirect/410 policy for held and unresolved URLs; D-6 publication dates for 43 articles (never guessed); D-7 whether to re-embed the YouTube video through an approved provider.

## 13. Hosting-dependent gates (infrastructure facts reported by the central VPS team are **not** proof of application compatibility)

Reported: Ubuntu 26.04.1 LTS, Nginx 1.28.3, PHP 8.5, MySQL 8.4.11. Not touched here. Upcoming gates: `composer.lock` on the current HEAD; PHP 8.5 platform requirements; an isolated MySQL 8.4.11 regression run; scheduler and queue assessment; server data-centre jurisdiction; separate Nginx/PHP-FPM/user/database; HTTPS and canonical host. No PHP/Composer was installed, no database created and nothing deployed.
