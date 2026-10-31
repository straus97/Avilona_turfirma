# NOV-PUBLIC-02 — Public SEO, content readiness and hosting-independent release preparation

Date: **2026-11-01** · Branch `db-rebuild-stage3` · Base HEAD `d48058fe5e06e512aca520173be73e8127712169`

> **Status authority:** [`docs/roadmap.md`](roadmap.md) is the only canonical status document. This file is the evidence, decision record and checklist set for NOV-PUBLIC-02 and defers to the roadmap for status.
>
> **Nothing was deployed.** No DNS, no hosting account, no REG.RU access, no production or `turfirma_rebuild_v4` database, no real e-mail, no search-console account, no real customer record. All QA used disposable SQLite databases with synthetic rows (marker `SYNTHETIC`).
>
> **CODE-LEVEL SEO READY ≠ CONTENT READY ≠ HOSTING READY ≠ LIVE INDEXABILITY VERIFIED** (§14). This milestone delivers the first only.

## 1. Owner facts recorded (2026-11-01)

* **REG.RU hosting will NOT be renewed.** The replacement provider is not selected; the owner will supply details after purchase. → NOV-PUBLIC-01 §8 *Route A (staged on REG.RU)* and *Route B-minimal (repair the REG.RU site)* are **withdrawn**. The new route is *"public-only profile on the future host"*; everything below is host-portable and assumes **nothing** about VPS vs shared vs managed hosting, PHP build, extensions or DNS.
* Public site live and indexable in **November 2026**; complete approved project **2027-01-31**. Search-engine indexability and correct metadata are explicit release requirements.
* Proposed canonical public host: **`https://avilona.ru`** (no `www`) — implementation preference, **pending final owner approval (N-5)**.

## 2. Repository guards (start of slice)

Branch `db-rebuild-stage3`; local HEAD = tracking HEAD = live `origin` HEAD = `d48058fe…`; staging empty; only `.claude/` untracked. Baseline 2500 tests / 19287 assertions.

## 3. SEO inventory (before → after)

Method: source inspection of every public route, controller and template plus a headless-Chrome crawl of 23 URLs × 360/768/1200 px on a synthetic site (§12).

| Page type | Before | After |
|---|---|---|
| **All pages (layout)** | `meta robots` hard-coded; canonical built from the *request* host (staging host would leak into production metadata); `og:image` hard-coded `https://avilona.ru/img/logo.png`; `og:title`/`og:description`/`twitter:*` **defaulted to the home-page text on every page without explicit sections** (every page without explicit sections — home, tours, countries, destinations, dictionary, special offers, legal pages — shared one social preview); `meta keywords` emitted (ignored by Google/Yandex; the dynamic ones were keyword lists); footer "Карта сайта" hard-coded host | Canonical, `og:url`, `og:image`, sitemap link, JSON-LD, `sitemap.xml` and the `Sitemap:` line of `robots.txt` all derive from **one** config value (`seo.public_url`). `og:*`/`twitter:*` default to the page's own title/description. Per-page `og:image` (valid https URL or existing local asset, else logo). `meta robots` overridable per page (`@section('robots')`) and forced `noindex, nofollow` by `SEO_NOINDEX=true`. `meta keywords` removed everywhere (17 dead sections deleted). |
| Home `/` | description ≈ 330 chars with unverifiable superlatives ("лучшие горящие предложения", "проверенные отели") | Concise factual description; **one** JSON-LD `TravelAgency` block |
| Company `/company/about_company`, `/employees`, `/awards` | boilerplate "Добро пожаловать на страницу…" descriptions, ungrammatical ("о нашей туристической фирмы") | Unique, factual descriptions. Pages with no DB rows are excluded from the sitemap |
| `/tours` | description claimed "ведущих туроператоров", "из Санкт-Петербурга и Москвы" (not verifiable from the page) | Honest description (Tourvisor, request ≠ booking/payment). **"Поиск туров" added to the primary menu and footer.** Visible fallback with phone/e-mail when the external module does not load or JavaScript is off |
| Countries / destinations (list) | boilerplate | unique descriptions; filtered lists (`?category=`, `?title=`) canonicalise to the unfiltered list |
| Country / destination / article / news / special-offer detail | description = the **same three boilerplate sentences with the title substituted** (duplicate/misleading), `og:image` always the logo, keyword stuffing in `meta keywords` | Description = **"Title. first sentence(s) of the real content"** (tags stripped, blocks spaced, cut on a sentence/word with `…`, **no hard 160-char limit**, soft ≈ 220); neutral fallback only when content is empty; page image as `og:image`; escaped once |
| News / articles / special offers / reviews (lists) | `?page=N` canonical pointed at page 1 for `url()->current()` users and the title logic said "Страница 1" for `?page=1` | `?page=N` (N ≥ 2) is a **self-canonical** page with "— страница N" in title/`og:title` and "Страница N." in description, exactly once; `?page=1`/junk/`?utm_…` canonicalise to the base URL |
| Contacts `/contacts` | description generic; no structured data | Description carries the real e-mail, office street and "реквизиты"; **one** JSON-LD block |
| Legal HTML pages | titles/descriptions fine, `meta keywords` | keywords removed; unchanged otherwise |
| Error pages | 404 themed layout page was `index, follow` (status 404 prevents indexing but the tag was wrong); 401/403/419/429/500/503 already `noindex` | **404 → `noindex, follow`**; others unchanged |
| H1 / hierarchy | one H1 on every page | unchanged; the only `h2→h5` jump is Bootstrap modal titles (hidden, not outline content) — recorded, not changed |
| Image alt | all content images carry `alt` | unchanged (0 images without an `alt` attribute in the crawl) |
| Mobile | no horizontal overflow | unchanged (0 px overflow at 360/768/1200 on 23 URLs) |

No metadata text states a price, offer, destination, award, rating or achievement that is not in the database/page.

## 4. Metadata contract (code)

* `config/seo.php` — `public_url` (env `SEO_PUBLIC_URL`, default `https://avilona.ru`), `noindex` (env `SEO_NOINDEX`, default false), `sitemap_lastmod` (env `SEO_SITEMAP_LASTMOD`, default false), `sitemap_max_urls` (45 000).
* `App\Support\Seo\PublicSeo` — `canonicalUrl()`, `absoluteUrl()`, `describe()`, `entityDescription()`, `imageUrl()`, page-number helpers, `businessIdentity()` (JSON-LD). Pure, no DB access.
* `layouts/main.blade.php` — resolves title/description/OG/Twitter/canonical/robots once; values from sections are decoded once and echoed once through `{{ }}` (no double escaping, covered by tests).
* Five detail controllers pass `$meta_description` and `$og_image`; `$meta_keywords` removed.
* Existing tests whose contract changed *deliberately* (not weakened): `PublicCanonicalMetadataConsistencyTest` (canonical now `seo.public_url` + path instead of `url()->current()`; added: `?page=N`, junk `page`, config-host-not-request-host), `PublicSeoFilesAndNoIndexTest` (robots/sitemap are now generated), `PublicSitemapConsistencyTest` (rewritten around the generator), `NewsPublicRenderSanitizationTest` (asserted the old boilerplate sentence; now asserts the content-derived description).

## 5. Canonical host strategy (proposed, owner approval pending — N-5)

* **Canonical:** `https://avilona.ru`. Set on the future host: `APP_URL=https://avilona.ru` (HTTPS scheme forcing, NOV-PUBLIC-01) **and** `SEO_PUBLIC_URL=https://avilona.ru`. Staging: `SEO_PUBLIC_URL=<staging host>` is **not** needed — staging should set **`SEO_NOINDEX=true`** and sit behind real HTTP authentication (robots.txt is not access control). Production metadata can never contain a staging hostname because the host is configuration, not the request.
* **Redirects (to configure at the future web server / proxy once TLS + DNS exist — NOT done now):**
  `http://avilona.ru`, `http://www.avilona.ru`, `https://www.avilona.ru` → `301 https://avilona.ru$request_uri`.
  Portable rules (templates; verify against the chosen host):
  ```nginx
  # nginx, TLS terminated here
  server { listen 80; server_name avilona.ru www.avilona.ru; return 301 https://avilona.ru$request_uri; }
  server { listen 443 ssl; server_name www.avilona.ru; ssl_certificate …; ssl_certificate_key …; return 301 https://avilona.ru$request_uri; }
  ```
  ```apache
  # Apache .htaccess fallback (shared hosting); needs mod_rewrite
  RewriteEngine On
  RewriteCond %{HTTP_HOST} ^www\.avilona\.ru$ [NC,OR]
  RewriteCond %{HTTPS} !=on
  RewriteCond %{HTTP:X-Forwarded-Proto} !https
  RewriteRule ^ https://avilona.ru%{REQUEST_URI} [L,R=301]
  ```
  **Redirect-loop rule:** behind a TLS-terminating proxy the application must never redirect on its own; keep exactly **one** layer responsible for HTTP→HTTPS and www→apex (the web server/proxy). The Laravel app only forces `https` URL generation (`APP_URL`). Test with `curl -sI -L --max-redirs 5` for the four variants — expected: ≤ 1 hop to `https://avilona.ru/`.
* **HSTS:** only after valid TLS is verified on both hostnames (the old site's HSTS + expired certificate made it unreachable).
* **Old-to-new redirects:** none invented. The new URL scheme is the same family as the old site (`/company/…`, `/countries/{slug}`, `/helpful_information/news/{slug}` …). The only redirect need that can be *proven* is slug drift: after the owner-approved content export exists, diff the old site's published sitemap (observed 272 URLs on 2026-10-31) against the imported slugs; every old URL that no longer exists needs an owner-approved target. `/tours` is new on the old host (was 404 there).
* **Verification-file note:** `google*.html` / `yandex*.html` are git-ignored by design; put them on the server (or use DNS/meta methods).

## 6. Sitemap and robots

**Sitemap is generated from the database** (`App\Services\Seo\SitemapBuilder`, route `/sitemap.xml`, cookie-less, `Cache-Control: public, max-age=900`). The old static file (273 URLs, curated, never tied to records, included `?page=1` duplicates, `?page=N` numbers that may not exist, 4 PDFs) was **deleted** — a static list cannot guarantee "no non-existent slug".

Eligibility contract (each rule is a test in `PublicSitemapConsistencyTest`):

| Included | Rule |
|---|---|
| Static pages | `/`, `/tours`, `/contacts`, `/company/about_company`, `/helpful_information/travel_dictionary` — render without DB rows |
| List pages | only when they have ≥ 1 record: `/company/employees`, `/company/awards`, `/countries`, `/destinations`, `/helpful_information/{news,interesting_articles,for_our_clients}`, `/reviews` |
| Detail pages | non-soft-deleted rows with a non-empty `slug` of countries, destinations, articles, news, special offers (the same condition the route uses; `firstOrFail`) |
| Reviews | only `is_published = 1` **and** consent not withdrawn (same query as `Review\IndexController`); the reviews page is listed only if ≥ 1 review qualifies |
| Pagination | `?page=2…N` computed exactly from the record count (page size 6, same as the controllers); **never** `?page=1` |
| Excluded | private/account/auth/cabinet/API/webhook/captcha routes (not even registered in the public profile), PDFs (legal files are an open owner gate), filtered/query duplicates, soft-deleted or slug-less rows, empty lists, 404s |
| `<lastmod>` | **omitted** by default: after an import `updated_at` is the import time, not an editorial date. `SEO_SITEMAP_LASTMOD=true` emits it only if the import preserved original dates (the importer accepts `created_at`/`updated_at`). Static pages never get one |
| Bound | ≤ `seo.sitemap_max_urls` (45 000); `chunkById(500)` over `id, slug`; on a database error the route answers **503 + Retry-After** (never a partial/empty map) |

A test crawls every URL of a synthetic sitemap and requires HTTP 200; the rehearsal test (§9) requires an exact path set.

**robots.txt** — rules still come from the repository file (single source, unchanged tests); the `Sitemap:` line is replaced at serve time with `seo.public_url + /sitemap.xml`. Added disallows: `/api/`, `/messages/`, `/bookings/`, `/password/`, `/captcha`, `/reload-captcha`, `/contact/` (POST-only endpoints) and `/*.doc$`, `/*.docx$` (editable legal sources, see §11). Public pages stay crawlable (no `Disallow: /`); a test proves no sitemap URL is blocked by any robots rule. robots.txt is **not** access control: private surfaces additionally return `X-Robots-Tag: noindex, nofollow` and, in the public profile, **do not exist** (404).

**Caches:** `/robots.txt`/`/sitemap.xml` are served outside the `web` group (no session cookie); user-specific responses never reach `cache.response` (CSRF/consent isolation tests from NOV-PUBLIC-01 stay green).

## 7. Structured data

One JSON-LD `TravelAgency` block on `/` and `/contacts` only (single entity, shared `@id` = `<public_url>/#organization`; other pages carry none). Source of truth: `PublicSeo::businessIdentity()`; `JSON_HEX_TAG|JSON_HEX_AMP` so it cannot break out of its `<script>`.

Included: `name` Авилона, `legalName` ООО «Авилона», `url`, `logo`/`image`, `email avilonatur@bk.ru`, `telephone` (the two manager numbers already printed on every page), `taxID 7805502454`, `address` = **actual office** `198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10` (the registered apartment address 198302 is **not** used — asserted by a test).
Deliberately **not** included (no approved source fact): `geo`, `openingHours*`, `aggregateRating`/`review`, `priceRange`, licences, awards, **`sameAs`** (the footer links `vk.com/avilona`, but nobody has approved it for structured data — owner can add it, one line). Validated by `json_decode` with `JSON_THROW_ON_ERROR` in tests and by parsing the live head in the browser crawl (`valid` on both pages). Schema.org / Google Rich Results Test was **not** run (needs a public URL).

## 8. Navigation and public UX

"Поиск туров" → `/tours` added to the primary navigation (active state, `aria-current`) and to the footer short menu. `/tours` keeps the honest interim copy (Tourvisor, a request is neither booking nor payment); the `noscript` note and the 10-second "module did not load" note now both show a clickable phone and e-mail, so a customer is never stuck. No provider API or webhook was touched; `/tours` still makes no server call.

## 9. Content readiness (the November critical path — **CONTENT NOT READY**)

**Finding:** every public content page except a few static ones is database-driven, and the repository still has no public-content seed or verified export (`database/seeders` has none; `turfirma_dev_schema_only.sql` is schema only). The owner has **not approved any real-data export.** Nothing was read from `turfirma_rebuild_v4`, REG.RU or any production database, and no fake tourism content was created to make the site look populated.

### 9.1 Minimal content categories for launch

| Priority | Category (table) | Used by |
|---|---|---|
| Must | Countries (`countries_images`) | `/countries`, `/countries/{slug}`, about page, sitemap |
| Must | Destinations (`destination_images`) | `/destinations(/{slug})` |
| Should | News (`news`), Articles (`articles`), Special offers (`our_clients`) | home, `helpful_information/*` — site is valid without them (lists drop out of the sitemap) |
| Should | Employees (`employees`), Awards (`awards`) | `/company/*` — **owner must confirm** staff contact data may be published (already printed publicly today) |
| Optional | Partners (`partners`), Best offers (`best_offers`) | home sections |
| Deferred | Reviews | require consent evidence (`review_consents`) → owner decision; the importer **refuses** them |
| Never | Users, bookings, inquiries, messages, documents, anything personal | refused by the importer |

### 9.2 Source → destination mapping and exchange format

The importer does **not** assume the format of any unknown source. It defines the target interchange file `avilona-public-content/1` (JSON); an owner-approved exporter/transformation must produce it. Per table (required → optional):

| Table | Required | Optional | HTML (sanitised) | Image columns |
|---|---|---|---|---|
| `countries_images` | title, **slug**, category, description | image_small, image_large, documents_url (https) | description | image_small, image_large |
| `destination_images` | title, **slug**, description | image_small, image_large | description | same |
| `articles` | title, **slug**, content | image | content | image |
| `news` | title, **slug**, link (http/https), description | image, pub_date | description | image |
| `our_clients` | title, **slug**, content | image | content | image |
| `employees` | name, position, tel, email, whatsapp, vk | image | — | image |
| `awards` | category | image | — | image |
| `partners` | name_partner | logo_partner | — | logo_partner |
| `best_offers` | title, content | image | content | image |

All tables also accept `created_at`/`updated_at` (preserved when present). Anything else (`id`, unknown columns, `password`, …) is rejected. **Slugs are mandatory and never generated** — the URL is the SEO asset; invented slugs would break existing links. Strings ≤ 255 chars; slug ≤ 191, no `/ ? # \ % whitespace`, may not start with `.`.

### 9.3 Guard rails of `php artisan content:import-public <file> [--commit] [--force]`

Dry-run by default (nothing written); `--commit` runs in **one transaction**, only into **empty** tables, asks for confirmation in production (`--force` to skip); slugs duplicated in the file or already in the database abort the whole import; HTML fields pass `NewsHtmlSanitizer` (same allow-list as the public render); images must be an `https` URL or a path relative to `public/` — local paths are **normalised to root-absolute** (`img/x.png` → `/img/x.png`, because the templates print the value as-is and `img/x.png` on `/countries/x` would resolve to `/countries/img/x.png`), `..`/`javascript:`/`http:` rejected, missing local files produce a **warning** (the page would show a broken image; the post-import crawl must be clean). Output contains counts and coordinates only, never row text. It reads no database other than the one it writes to.

Operational note: images stored as `/storage/…` need the `public/storage` link (`php artisan storage:link` or the host's equivalent) **and** the files themselves — uploaded assets are not in git.

### 9.4 Synthetic rehearsal — result

`PublicContentImportRehearsalTest` (9 tests) and the browser crawl: a synthetic file (2 countries, 1 destination, 1 article, 8 news, 1 special offer, 1 employee, 1 award, 1 partner, 1 best offer) was validated (dry-run wrote 0 rows), committed, and served. The sitemap contained **exactly** the expected 26 URLs (5 static + pages/records derived from the fixture, `?page=2` for 8 news items), every one returned 200 with one H1, a self-referencing canonical and no `src=""`; a country whose image file does not exist is reported by the dry-run and renders a broken `<img>` (the only browser finding — see R-IMG). Rejection paths proven: non-content tables (`users`, `bookings`, `inquiries`, `messages`, `reviews`, `review_consents`, `incoming_inquiries`), extra field, missing/duplicate/unsafe slug, `javascript:`/`..`/`http:` image, bad link, bad date, over-long title, missing required field, slug collision with existing rows, non-empty target (whole import rolled back), wrong format version, unreadable and non-JSON files.

### 9.5 Owner/evidence needed before a real import

1. Approval of **which** public tables may be exported and by whom (N-3).
2. The export itself, in the format of §9.2, produced from the live site's own admin/database, with original slugs and dates — or a decision to start with an empty/curated set.
3. The image/file assets those rows reference (a list of the `image*` values is produced by the dry-run warnings).
4. Decision on reviews (consent evidence) and on publishing employee contact fields.

## 10. Contact-form mail safety (N-7)

Finding: the visitor-typed address receives `UserFormMail`. The body contains **no message text** — only a fixed paragraph and the visitor-typed **name** — but the name is free text (links, markup, advertising, a different person's name), the mail leaves from the company sender, and nothing limited how often one recipient could be mailed (captcha + 8 requests/min/IP only): an *advertising-reflection and spam-amplification path*.

Is it necessary? **No** for the lead workflow: staff still receive every lead at `straus97@mail.ru` (unchanged); `avilonatur@bk.ru` remains only the public company address. The acknowledgement is a courtesy.

Smallest safe change made (workflow preserved, owner decision still open):

1. The acknowledgement greets only name-shaped input (`^[\p{L}\p{M}][\p{L}\p{M} '’-]{0,59}$`; no digits, `@`, dots, `/`, `<`, `[`); anything else → generic "Здравствуйте!". Nothing else from the form enters the mail.
2. At most **2 acknowledgements per recipient per hour** (case-insensitive, `RateLimiter`); extras are silently skipped; staff mail is unaffected.
3. A failing acknowledgement no longer turns an already-delivered lead into "Ошибка отправки" (visitors would resubmit duplicates); only the exception class is logged (`Log::warning`).

**Owner decision N-7 (low), tradeoff:** *keep* (current: confirmation to the customer, small residual abuse surface bounded by 1–3 above) vs *remove* (zero reflection, but customers get no e-mail receipt and may resubmit; one-line change in the two controllers). Until decided, the bounded behaviour ships. Real mail was never sent (`Mail::fake`/mocks only).

## 11. Legal documents and opening hours — gates stay OPEN

Not edited, regenerated or replaced (no approved documents were supplied):

| File (`public/documents/`) | Problem (NOV-PUBLIC-01 §10) | Linked from |
|---|---|---|
| `User_Agreement.pdf` | old legal address 191119 Звенигородская ≠ 198302 | `contacts` (l.184), `home` (l.364), `reviews` (l.223) |
| `Policy_regarding_the_protection_and_processing_of_personal_data.pdf` | same stale address | `about_company` (l.30), `contacts` (l.269), `legal/cookies`, `legal/personal-data-consent`, `legal/registration-personal-data-consent`, `legal/review-personal-data-consent`, `tours` (l.68) |
| `Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf` | generic template, no company identification | `about_company` (l.22), `contacts` (l.255) |
| `Public_offer_for_ticket_sales_and_hotel_bookings.pdf` | generic template (2012) | `about_company` (l.26), `contacts` (l.262) |
| `Reminder_for_those_traveling_abroad_general.pdf` | informational memo | `countries` (l.54) |

**New finding:** the editable sources `public/documents/*.doc` / `*.docx` (five files) are inside the document root and therefore **directly downloadable** (and may carry the same stale text). Owner/legal action: decide whether they may stay public; recommended to exclude them from the release package or move them out of `public/`. robots now disallows them but that is not access control. They were not moved or edited.

Owner actions: supply corrected documents (or written acceptance of the current files) → apply by replacing the files (the review-consent fingerprint changes by design) → then PDFs may be added to the sitemap. **Opening hours** (`/` and header 10:00–20:00 vs `/contacts` 11:00–20:00 by appointment, `PENDING_BUSINESS_DECISION_OPENING_HOURS`) remain unresolved; no hours were added to pages or JSON-LD. Verified requisites were not changed.

## 12. Verification evidence

* **Focused tests:** `PublicSeoMetadataReadinessTest` (18), `PublicSitemapConsistencyTest` (13, rewritten), `PublicContentImportRehearsalTest` (9), `PublicCanonicalMetadataConsistencyTest` (10, +3), `PublicSeoFilesAndNoIndexTest`, `PublicRobotsConsistencyTest`, `PublicContactMailFailureLoggingTest`, `PublicReleaseProfileTest` (profile ON) and the dynamic-metadata/nav/cache suites — all green.
* **Full PHPUnit:** see the table in the roadmap/closing report (§15 of the report). Sequential, SQLite `:memory:`, Xdebug off, `memory_limit=3G`; PHP 8.3.36 = the checksum-verified official NTS build (SHA-256 `1a097d52…10d4`, matches `releases.json`); the vulnerable 8.3.32 was not run.
* **Browser QA** (headless Chrome 155 over the DevTools protocol, PHP 8.3.36 built-in server with the Laravel router, `APP_ENV=production`, `APP_DEBUG=false`, `RELEASE_PUBLIC_ONLY=true`, disposable SQLite imported from the synthetic file): 23 URLs × 360/768/1200 px = 69 page loads. Exactly one `<title>`, description, canonical per page; canonical = `og:url` = `https://avilona.ru/…`; zero duplicate titles/descriptions across the 22 indexable pages; one H1 everywhere; 0 px horizontal overflow; no `meta keywords`; no private links (`/login`, `/register`, `/cabinet`, `/admin`, …) on any page; "Поиск туров" present in the menu; JSON-LD parses on `/` and `/contacts` only; `/no-such-page` → 404 + `noindex, follow`; `/robots.txt` `text/plain`, `/sitemap.xml` XML with 26 URLs; `/login`, `/register`, `/cabinet`, `/admin/dashboard`, `/api/…`, `/reviews/create`, `/.env`, `/composer.json`, `/storage/logs/laravel.log` → 404. Console: no first-party JavaScript errors; the only errors are the Tourvisor module refusing `127.0.0.1` (CORS — it is domain-bound, N-6), the intentional 404 page's own status, and the deliberately missing synthetic image.
* **Not verified (cannot be, offline):** real TLS, real domain Tourvisor behaviour, SMTP delivery, Rich Results Test, Search Console/Webmaster, actual indexing.

## 13. New-hosting portability requirements (host-independent checklist)

Nothing here assumes a provider. Every line is a **requirement the owner/provider must evidence**; none is verified yet.

| # | Requirement | Evidence to collect |
|---|---|---|
| H1 | **PHP 8.3.x only** for this lockfile (intervention/image 4.2.1 needs ≥ 8.3; nette/utils 4.0.4 and nette/schema 1.3.0 reject 8.4+). Pin the version in the panel/service so an auto-upgrade cannot break the site. Security level ≥ 8.3.36. Composer's platform check must stay on — do not bypass it. | `php -v`, `php -m` from the host |
| H2 | Extensions: `gd mbstring openssl pdo_mysql fileinfo curl ctype tokenizer xml` (`gd` for the captcha), `intl` optional; `memory_limit ≥ 128M` | `php -m`, `php -i` |
| H3 | **Document root = `public/`**; `/.env`, `/composer.json`, `/vendor/`, `/storage/`, `/routes/`, `/.git/` must answer 404/403 | `curl -I` on each |
| H4 | Web server rewrite: all non-file requests → `public/index.php` (nginx `try_files $uri $uri/ /index.php?$query_string;` / Apache `.htaccess` honoured). PHP must also serve **`/robots.txt` and `/sitemap.xml` through Laravel** (do not put a static copy in `public/`, or the generated sitemap is shadowed). | requests return 200 with the generated body |
| H5 | **MySQL/MariaDB**, utf8mb4, new empty database + dedicated user; migrations run only on it; connection details never in git/chat | owner-provided out of band |
| H6 | `.env` created **on the server only**: `APP_ENV=production APP_DEBUG=false APP_KEY (new) APP_URL=https://avilona.ru SEO_PUBLIC_URL=https://avilona.ru RELEASE_PUBLIC_ONLY=true LOG_CHANNEL=daily LOG_LEVEL=warning DB_* SESSION_DRIVER=file SESSION_SECURE_COOKIE=true CACHE_DRIVER=file QUEUE_CONNECTION=sync MAIL_*`; leave `TOURVISOR_*` unset; staging adds `SEO_NOINDEX=true`. `config:clear` after any change. Do **not** `route:cache` (closures). | `php artisan about` on the host (no secrets) |
| H7 | **TLS** for apex **and** `www`, auto-renewal enabled and monitored (the old site's certificate expired in June 2025 and nobody noticed) | certificate chain/expiry from a second machine |
| H8 | **Reverse proxy:** if TLS is terminated upstream, the proxy must send `X-Forwarded-Proto`; the app forces https URLs from `APP_URL`. One layer owns redirects (§5). | redirect matrix `curl -sIL` ≤ 1 hop |
| H9 | **Private-route isolation:** `RELEASE_PUBLIC_ONLY=true` ⇒ `/login /register /cabinet /admin /manager /profile /api/* /reviews/create` and the Tourvisor webhook answer 404. | smoke list below |
| H10 | **Mail transport:** SMTP host/port/encryption/credentials, sender identity (roadmap U-8), SPF/DKIM/DMARC for the sender domain; one owner-run test of each form; recipients `straus97@mail.ru` (internal), public `avilonatur@bk.ru` | received test mails |
| H11 | **Scheduled tasks:** none required for the public-only profile (RSS news sync `news:sync-rss` is optional and only if the owner wants automatic news; the CRM scheduler is a January topic). | decision recorded |
| H12 | **Health monitoring:** the app has no health endpoint (none added on purpose); use an external uptime check on `/` (200 + a stable marker) and a TLS-expiry alert; read `storage/logs/` daily for the first week | monitor configured |
| H13 | **Backups & rollback:** nightly DB dump + a copy of `.env`/uploaded assets off-server; a *tested* restore; previous release directory retained; rollback = repoint the document root | one successful test restore |
| H14 | Writable `storage/**`, `bootstrap/cache` (0775, PHP user); `storage:link` or equivalent if any `/storage/…` image is used | `ls -l`, an image request |
| H15 | Release package built with PHP 8.3: `composer install --no-dev --prefer-dist --optimize-autoloader` from the lock (never `composer update`); no `node_modules`, `tests`, `docs`, `.env*`, `.git`, `.claude`, `*.sql`, `*.bat`, **`public/documents/*.doc(x)` pending N-4** | archive checksum |

**Post-deploy smoke (staging first, then production):** `/ /tours /contacts /company/about_company /countries /destinations /reviews /helpful_information/news /cookies` → 200 over valid TLS; one country, one news item → 200; `/robots.txt` → `text/plain`, a single correct `Sitemap:`; `/sitemap.xml` → XML, **100 % of its URLs 200**, count equals the imported records; private routes and secret files → 404/403; HTTP/`www` variants → 1 hop 301 to `https://avilona.ru/`; `view-source` shows canonical = `https://avilona.ru/…` and no staging host; no `X-Robots-Tag` on public pages (and present everywhere on staging); one real test of each form; Tourvisor test request on the production domain; `storage/logs` clean.

## 14. Release readiness classes

| Class | State | Basis |
|---|---|---|
| **A. CODE-LEVEL SEO READY** | ✅ **READY** | §3–§8, §10; tests + browser QA |
| **B. CONTENT READY** | ❌ **NOT READY** | no approved real-data source/export; tool + rehearsal ready; legal PDFs & hours unresolved (§9, §11) |
| **C. HOSTING READY** | ❌ **NOT READY** | REG.RU not renewed, new provider not selected, nothing verified (§13) |
| **D. LIVE INDEXABILITY VERIFIED** | ❌ **NOT VERIFIED** | nothing is live; needs TLS, DNS, Webmaster/Search Console observations (§15) |

**November deployment: NO-GO** while B, C, legal PDFs, TLS and mail delivery are unverified. A does not imply B, C or D.

## 15. Search-engine launch checklists (owner; after the site is live on the final host)

No console is configured, no verification token exists in the repository, and no indexing report exists. Discovery is not a promise of indexing or ranking.

**Yandex Webmaster** — 1) Add `https://avilona.ru` (the chosen canonical, with `https`); also add `www` only to confirm the redirect and "main mirror". 2) Confirm ownership (meta tag on the server / DNS TXT / file in `public/`; keep the token out of git). 3) *Robots.txt analysis*: it must fetch HTTP 200 as `text/plain`; test `/`, `/tours`, `/countries/<slug>`. 4) *Sitemap files* → add `https://avilona.ru/sitemap.xml`. 5) *Server response check* on representative URLs (home, a country, a news item, a news `?page=2`, `/tours`, `/contacts`). 6) Set region (Санкт-Петербург). 7) Monitor *Indexing → Pages in search / Excluded pages* and *Diagnostics* weekly; compare "loaded" vs sitemap count.

**Google Search Console** — 1) Add a **Domain property** `avilona.ru`. 2) Verify via DNS TXT (owner/DNS access). 3) *Sitemaps* → submit `https://avilona.ru/sitemap.xml`; status must be "Success" with the expected URL count. 4) *URL Inspection* on `/`, `/contacts`, `/tours`, one country, one news item: "URL is on Google"/"can be indexed", user-declared canonical = Google-selected canonical. 5) Monitor *Pages* (Page indexing) — reasons such as "Duplicate without user-selected canonical", "Alternate page with proper canonical" (expected for filtered/`www` URLs), "Crawled – currently not indexed"; 6) *Rich results*: run the Rich Results/Schema validator on `/` and `/contacts` (the JSON-LD was only syntax-validated locally).

**Both:** record only observed facts; reconcile with the old site's property/sitemap history; do not call the site "indexed" before the consoles say so.

## 16. Residual register (carried / new)

R-B/U-10 retention · booking FK cascade risk · session-reactivation residual · PHP/Composer ceilings (nette) · **legal PDFs (N-4) incl. publicly reachable `.doc(x)` sources** · opening hours · Tourvisor privacy facts and production secrets/webhook (N-6) · U-8 sender identity · E6 gates · AOA-05-I2 planning-only · AO-12 admin chat overflow · **N-7 acknowledgement keep/remove** · **N-5 canonical host approval** · **N-3 content source** · **R-IMG** rows pointing to non-existent local files render a broken image (importer warns; add the crawl to the launch smoke) · modal titles `h5` after `h2` (cosmetic) · reviews excluded from the importer (consent evidence) · `meta keywords` removed (intentional).

## 17. Recommended NOV-PUBLIC-03 scope

1. **After the owner supplies the hosting details:** a host-verification kit (one non-secret PHP diagnostics script the owner runs: PHP version, extensions, `memory_limit`, rewrite, writable dirs) and a staging dry-run on the chosen host with `SEO_NOINDEX=true`.
2. **Content:** run `content:import-public` on the first owner-approved file (dry-run → report → commit on staging), post-import sitemap crawl, asset reconciliation, slug diff against the old site, owner-approved redirects for any dropped slug.
3. **Legal:** apply owner-supplied PDFs; decide the `.doc(x)` question; then (optionally) list the legal PDFs in the sitemap.
4. Owner decisions N-3, N-4, N-5, N-6, N-7 + opening hours.
5. Cut-over only when classes B and C are green; then the §15 checklists.
