# NOV-PUBLIC-03A — Legacy SEO preservation and URL migration readiness

Date: **2026-11-01** · Branch `db-rebuild-stage3` · Base HEAD `50b76e2e2d9e04465f728463da91d3faaf1ebe41`

> **Status authority:** [`docs/roadmap.md`](roadmap.md) is the only canonical status document; this file is the technical reference for NOV-PUBLIC-03A and defers to it.
>
> **Nothing was deployed. Old SEO equity is NOT declared preserved.** Equity can only be judged after the real site is live and crawled (§9). No Google/Yandex account, no live crawl, no DNS/TLS/SMTP/provider action, no REG.RU access, no `turfirma_rebuild_v4`, no real data. All tests and QA used disposable SQLite databases with synthetic rows.

## 1. Owner-supplied historical evidence (screenshots, not a live crawl)

| Source | Figure |
| --- | --- |
| Google Search Console | 51 indexed URLs · 66 not indexed (42 *crawled – currently not indexed*, 24 *404*) · sitemap processed with **272** discovered URLs |
| Google, historical path shapes | `/countries/{numeric-id}`, `/helpful_information/news/{slug}`, `/helpful_information/interesting_articles/{slug}`, `/reviews?page=N` |
| Yandex Webmaster | 264 downloaded · 218 in search · 18 duplicate-description warnings · sitemap referenced as `https://www.avilona.ru/sitemap.xml` · favicon advice: SVG or 120×120 |

These are historical. They prove what the **old** site looked like to the engines; they prove nothing about the new deployment.

## 2. Known historical URLs

The repository's first commit contains the `sitemap.xml` of the old site: **exactly 272 `<loc>` entries**, all on `https://avilona.ru` (apex, not `www`) — the same count as Search Console. That the submitted file *is* this file is **inferred from the count, not proven**. It is preserved host-less in [`resources/seo/legacy-sitemap-paths.txt`](../resources/seo/legacy-sitemap-paths.txt) (no personal data; public URLs only).

| Pattern | URLs |
| --- | --- |
| `/helpful_information/news/{slug}` | 100 |
| `/countries/{slug}` | 55 |
| `/helpful_information/interesting_articles/{slug}` | 50 |
| `/destinations/{slug}` | 12 |
| `/helpful_information/for_our_clients/{slug}` | 1 |
| `?page=N` on `/reviews` (1–11), `/helpful_information/news` (1–17, i.e. 100 items ÷ 6), `/helpful_information/interesting_articles` (1–9) | 37 (of which **3** are `?page=1`) |
| `/documents/*.pdf` (the five published legal PDFs) | 5 |
| Static pages (`/`, `/company/{about_company,employees,awards}`, `/contacts`, `/travel_dictionary`) and the six list roots | 12 |

The **sitemap used slugs only.** Numeric `/countries/{id}` appears **only** in the owner's Search Console screenshots; it is not in the repository (slugs were added by migrations dated 2024-05-23, so numeric-id URLs are presumably older crawl residue — hypothesis).

## 3. Compatibility matrix (verified against the real routes)

Codes: **A** unchanged and supported · **B** resolves to an identical current document · **C** proven permanent redirect · **D** content-dependent or missing content · **E** no valid replacement.

| Old pattern | Class | Current behaviour | Evidence |
| --- | --- | --- | --- |
| Static pages, list roots, `/documents/*.pdf` (5) | **A** | same path, 200 | `PublicLegacyUrlCompatibilityTest` classifies all 272 inventory URLs |
| `/countries|destinations|news|articles|for_our_clients/{slug}` (218) | **A / D** | route unchanged, 200 **iff** a published record with that slug exists; otherwise honest 404 (`noindex`) | route exists; record existence is content (§5) |
| `?page=N`, N ≥ 2, on the 4 paginated lists (34) | **A / D** | 200 if that page exists, else 404 (new) | `PublicSeo::abortIfPageOutOfRange` |
| `?page=1` (and `0`, negative, non-numeric, empty, array) on the 4 paginated lists | **C** | **301 → same path without `page`** | the Laravel paginator renders exactly page 1 for all of these (`PaginationState`); proven with data in the test |
| `?page=N` on non-paginated pages (`/countries?page=3`) | **B** | same document, canonical without `page` (changed: it used to self-canonicalise every N) | `test_page_parameter_is_ignored_on_non_paginated_pages` |
| Trailing slash (`/countries/egipet/`) | **B** | 200 with the slash-less canonical; Apache `.htaccess` already 301s it | test + `public/.htaccess` |
| `/countries/{numeric-id}` | **D/E** | **404, never redirected** | no id→slug table exists in the repo; the importer assigns **new** ids (`id` is not an input column) |
| Slugs of vanished content (probably RSS news) | **D/E** | 404 | content decision (§5) |
| `http://`, `www.` variants | host layer | **not decided here** (owner N-5, hosting) | NOV-PUBLIC-02 §5 |

**Not implemented by design:** a speculative redirect table, redirects of numeric country ids, redirects of missing pages to the homepage, 410 for anything.

## 4. What changed in code

* **`public.pagination` middleware** (`NormalizePublicPagination`) on `/reviews`, `/helpful_information/news`, `/…/interesting_articles`, `/…/for_our_clients`: marks the request paginated and issues the 301 above. The `Location` is the route's own constant path as a **relative** URL (no host from the request → no open redirect, no loop; other query parameters such as filters are kept). It runs before `cache.response`. `RELEASE_PUBLIC_ONLY` registers it (it lives in `routes/public.php`); private routes are never touched.
* **Out-of-range pages are real 404s** (they were 200 with an empty list and a self-canonical — a soft-404 factory; the old sitemap alone listed news up to page 17). The first page of an empty list stays 200. The 404 page does not self-canonicalise.
* **`PublicSeo::pageNumber`** now honours `?page=N` only on paginated lists and reads it exactly like the paginator (`filter_var`), so canonical/title/description can never describe a page the body does not show.
* **Pagination links** in the two pagination views no longer emit `?page=1` (`PublicSeo::pageLinkUrl`), so internal links point at final URLs.
* **`public/documents/.htaccess`**: denies `.doc`/`.docx` (case-insensitive) with 403 and leaves the PDFs untouched — see §7.
* **`robots.txt`**: added the *real* asset paths `/dist/` and `/plugins/` (§6).
* **Read-only audit tooling** (§8): `seo:audit-urls`, `LegacyUrlClassifier`, `RobotsRules`, the inventory file.

## 5. Unresolved mappings — exact owner evidence needed

| # | Question | Evidence to supply | Why it blocks a redirect |
| --- | --- | --- | --- |
| U-1 | Where do the `/countries/{numeric-id}` URLs map? | A file `id,slug,title` from the **old** `countries_images` table (id as it was when those URLs were live), **and** confirmation that the new content carries the same slugs | Without the old id→slug pairs any target is a guess; the importer does not preserve ids |
| U-2 | The 24 URLs Google reports as 404 | The Search Console export (*Pages → Not found (404) → Export*), `url` column | Needed to see which are numeric ids, which are vanished news, which are typos |
| U-3 | The 42 *crawled – currently not indexed* URLs | Same export | Likely thin/duplicate pages; decide per pattern, not per URL |
| U-4 | Which of the 100 historic news slugs / 50 article slugs will exist in the approved content | The approved `avilona-public-content/1` export (N-3), then a `--check-db` audit — **only** on a verified disposable QA database (F1; a staging audit needs a separate owner-authorised operation) | News is an RSS feed; slugs derive from titles, so old items may simply be gone → 404 is correct; a redirect needs an owner-named successor |
| U-5 | Any slug that was *renamed* (not removed) | Old slug → new slug pairs, with the owner's confirmation that the document is the same | Only the owner can assert identity of content |
| U-6 | Canonical host (apex vs `www`) | Owner decision N-5 | Yandex saw `www.avilona.ru`; the old repo file said apex |

CSV for the audit (UTF-8, comma or `;`): `url` (required), `source` (`indexed` · `not_indexed_404` · `crawled_not_indexed` · `sitemap` · `other`), `note` (optional, copied as data). Export from the engines' own *Export* buttons; trim to these columns.

## 6. robots.txt and sitemap review

Old file (initial commit) vs current generated rules:

* The old `Sitemap:` pointed at the apex in the repository, but Yandex's screenshot shows `https://www.avilona.ru/sitemap.xml` — the file the old host actually served was not necessarily this one. The current `Sitemap:` line is always generated from `SEO_PUBLIC_URL`, the same single host as canonical, `og:url`, JSON-LD and every sitemap `<loc>` (test: one host on real pages).
* The old rules `Disallow: /public/documents/`, `/public/dist/`, `/public/plugins/`, `/public/storage/` … are **dead**: the document root is `public/`, so these URLs never exist and the legal PDFs under `/documents/` were always crawlable (and *were* in the old sitemap). I did **not** start blocking `/documents/`: whether the legal PDFs should be indexed is part of owner decision N-4. I added the real paths `/dist/` and `/plugins/` (only the cabinet layouts load them; public pages do not).
* NOV-PUBLIC-02 additions kept: `/api/`, `/messages/`, `/bookings/`, `/password/`, `/captcha`, `/reload-captcha`, `/contact/`, `/*.doc$`, `/*.docx$`.
* **Proof of no contradiction** (`RobotsRules`, longest-match/wildcard semantics): no sitemap URL generated from synthetic content, and none of the 272 historical URLs, is blocked; theme assets (`/css`, `/js`, `/img`) are not blocked; private surfaces, editable sources and cabinet asset trees are.
* `robots.txt` is **not access control**; private routes are separately `noindex` and, in the public-only profile, do not exist.

## 7. Public file exposure (`public/documents/`, no content read)

| File | Class | Evidence |
| --- | --- | --- |
| 5 × `*.pdf` (User Agreement, Personal-Data Policy, two offers, Reminder for travellers) | **Intended public** | linked from home, contacts, about-company, countries, tours, reviews, register and the legal pages |
| `Пользовательское соглашение.docx`, `Политика в отношении защиты и обработки персональных данных.docx`, `Памятка выезжающего за рубеж (общая).docx`, `Публичная оферта на заключение договора о реализации туристского продукта.doc`, `Публичная оферта на реализацию билетов и бронирование отелей.doc` | **Editable internal source** (one per PDF) | linked from **nowhere** (test); names mirror the PDFs; may carry the stale text recorded in N-4 |
| `public/img/special/big.psd`, `little.psd` | **Unknown — owner review** | image design sources, downloadable; outside this slice's scope, not blocked, not touched |

Action: the five `.doc`/`.docx` are denied by `public/documents/.htaccess` (**403**). Verified on a throw-away Apache 2.4.69 on a high port: both extensions and `.DOCX` → 403; the five PDFs → 200. Files were **not** deleted, moved or edited and no legal text changed. Limits: it only works where Apache honours `.htaccess` (AllowOverride, already required by `public/.htaccess`). On nginx add `location ~* ^/documents/.+\.docx?$ { return 404; }`; independent of the server, **exclude the five files from the release package** (NOV-PUBLIC-02 H15). A test now fails if a new unclassified file appears in `public/documents/`.

## 8. Audit tooling (read-only)

```
php artisan seo:audit-urls export.csv [--output=report.csv] [--check-db --connection=<verified QA connection>]
```

No network, no engine account, no crawler, no redirect table generated. Without `--check-db` the database is never opened. **Superseded by F1:** `--check-db` requires `--connection=<name>` and the connection must pass `VerifiedQaConnection` before any query — see [NOV-PUBLIC-03A-F1](NOV-PUBLIC-03A-F1-audit-db-safety-and-release-files-2026-11-01.md). The report adds `class`, `action`, `route`, `redirect_target` (only for the proven `?page=1` case), `in_legacy_sitemap`, `blocked_by_robots`; cells starting with `= + - @` are neutralised for spreadsheets. Classes are documented in `LegacyUrlClassifier`.

## 9. Favicon and metadata

* Verified fact: one icon, `/img/favicon.ico` (also `/favicon.ico`, identical bytes), a valid ICO containing a **single 16×16** image. Yandex's SVG / 120×120 advice is therefore **not met**. Not fixed: the only logo is a 990×800 illustration (1.7 MB) with no square mark, and producing one is a branding decision. **Owner item:** supply an approved square mark (SVG or ≥ 120×120 PNG) → then add `<link rel="icon" type="image/svg+xml">` / `sizes` and a PNG variant.
* NOV-PUBLIC-02 metadata is intact: one canonical, one `og:url` equal to it, one description and one robots meta per sampled page; the `TravelAgency` JSON-LD appears once on `/` and `/contacts` only.
* Observation (not changed): `logo.png` is 1.7 MB and is used as header logo, `og:image` and JSON-LD logo — worth a derived web-size copy later.

## 10. Post-hosting verification checklist (nothing here has been done)

1. Redirect matrix (`curl -sIL --max-redirs 5`) for `http://`/`https://` × apex/`www`: ≤ 1 hop to the approved host; `/x/` → `/x`.
2. `/helpful_information/news?page=1`, `/reviews?page=1` → 301 to the clean path; `?page=2` → 200; a page beyond the last → 404 `noindex`.
3. `/countries/<numeric>` → 404 (no redirect); a real slug → 200; `/robots.txt` text/plain with one correct `Sitemap:`; `/sitemap.xml` 100 % of URLs 200.
4. `/documents/*.pdf` → 200; `/documents/*.doc|*.docx` → 403/404 (or absent from the package).
5. After the approved import: run `seo:audit-urls` with the owner's exports (`--check-db` only on a verified QA database, F1) and resolve U-1…U-5 **one owner-approved pattern at a time**.
6. Search Console / Webmaster: add the approved host, submit the sitemap, URL-inspect representative pages, compare *Pages* statuses with §1 weekly; Yandex mirror = approved host.
7. Only then state whether any historical equity was kept.

## 11. Risks recorded

R-301 old numeric-id and vanished-news URLs return 404 (equity for those is lost unless U-1/U-4 supply identities) · R-HOST www vs apex unresolved · R-ICON no 120×120/SVG · R-DOC `.htaccess` protection is Apache-only · R-PDF legal PDFs remain crawlable and stale (N-4) · R-PSD design sources downloadable · R-CACHE `cache.response` stores one entry per full URL, so arbitrary `?x=` strings enlarge the cache (pre-existing, not changed) · R-LOGO 1.7 MB logo.

## 12. Verification record

Full PHPUnit (SQLite `:memory:`, Xdebug off, `memory_limit=3G`, sequential): **2571 tests / 20027 assertions, 0 failures, 0 errors** on PHP 8.5.12, 8.4.13 and checksum-verified 8.3.36 (+35 tests over NOV-PUBLIC-02: `PublicLegacyUrlCompatibilityTest` 17, `AuditLegacyUrlsCommandTest` 5, `PublicDocumentExposureTest` 6, `PublicFaviconAndMetadataRegressionTest` 3, `PublicLegacyUrlPublicProfileTest` 3, one new canonical test; the old `?page=N` canonical test was rewritten for the new rule). Browser QA on a disposable synthetic SQLite database at 360 (true-width iframe), 768 and 1200 px: news page 2, a 404 beyond the last page, and the `?page=1` 301 — no horizontal overflow; headless Chrome clamps the window width, so 360 px was captured through an iframe. `.htaccess` verified on a throw-away Apache 2.4.69 (high port, stopped afterwards). **No live SEO verification was performed.**
