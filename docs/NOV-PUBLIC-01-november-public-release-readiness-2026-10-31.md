# NOV-PUBLIC-01 — November public website: release readiness and safe release candidate

Date: **2026-10-31** · Branch `db-rebuild-stage3` · Base HEAD `e61a152e093b5753949ea40fc1d6172b89d52d37`

> **Status authority:** [`docs/roadmap.md`](roadmap.md) is the only canonical status document. This file is the evidence, decision record and runbook for the NOV-PUBLIC milestone and defers to the roadmap for status.
>
> **Nothing was deployed.** No hosting login, no production secret, no production database, no DNS/SSL change. The only contact with production was a handful of **anonymous public GET/HEAD requests** to `avilona.ru` (§3) — read-only observation of what any visitor sees.
>
> **PUBLIC RELEASE READY ≠ FULL CRM PRODUCTION READY.** This milestone does not close E6 and does not complete any AOA slice.

## 1. Owner deadlines (supersede the March–May 2027 estimate)

| Milestone | Date | Meaning |
|---|---|---|
| **NOV-PUBLIC** | **November 2026** | Public site accessible **and technically indexable** (crawlable, valid TLS, sitemap/robots, consistent canonical host). Not blocked by unfinished CRM. |
| **Full project** | **2027-01-31** | Agreed delivery target for the operational CRM and remaining approved functionality, subject to explicit release acceptance. |

## 2. What was verified at the start

Branch, HEAD, upstream and live `origin/db-rebuild-stage3` were all `e61a152e…`; only `.claude/` untracked; no staged/unstaged tracked changes. Baseline at start: 2477 tests / 19037 assertions, 0 failures.

## 3. What the live `avilona.ru` shows today (anonymous, read-only, 2026-10-31 ≈ 23:25 MSK)

| Observation | Fact | Consequence |
|---|---|---|
| **TLS certificate EXPIRED** | Subject `CN=www.avilona.ru`, SAN `www.avilona.ru, avilona.ru`, issuer GlobalSign AlphaSSL CA 2023, **valid until 2025-06-18**. A strict client fails with `SEC_E_CERT_EXPIRED`; only `curl -k` gets a response. | Normal browsers show a security interstitial; the site is **effectively not accessible to ordinary visitors and crawlers**. This alone fails the "accessible and indexable" deadline for the *existing* site. |
| **HSTS is sent** | `Strict-Transport-Security: max-age=31536000` | Browsers that have seen it cannot click through an expired certificate at all. |
| HTTP → HTTPS | `http://avilona.ru/` → `301 https://avilona.ru:443/` | Redirect is fine; destination is the broken-TLS origin. |
| Hosting | `server: nginx`; the host's own 403 page links `server222.hosting.reg.ru/manager` | Confirms **REG.RU shared hosting** (owner notes). Hosting renewal/billing state is **unverified** — an expired certificate for 16 months suggests the hosting/SSL subscription was not maintained. |
| Application | Laravel session/XSRF cookies, `/login` → 200, `/tours` → **404**, `/robots.txt` and `/sitemap.xml` present (272 URLs) | The live site is an **older release of the same project**; it has no Tourvisor search page. |
| Host canonical | Live `robots.txt` says `Sitemap: https://www.avilona.ru/sitemap.xml`; the repository says non-www `https://avilona.ru/sitemap.xml` | **Canonical host (www vs apex) is undecided in practice** and must be fixed before Webmaster setup. |
| `/.env` | 403 from the hosting panel's page (not a Laravel page, no debug output) | Not exposed (sampled once; not a security audit). |

**Everything above is observation of the public edge. Server-side facts (PHP version, extensions, DB, mail, cron, SSH) remain owner-verification items (§6).**

## 4. Public route inventory and readiness

Source: `routes/public.php` (moved there in this slice; see §8). Classes: **READY**, **NEEDS FIX**, **BLOCKED**, **DEFER TO CRM**.

| Area | Route(s) | DB | Class | Notes |
|---|---|---|---|---|
| Home | `/` | news, reviews, best_offers, partners | **READY** (code) / **BLOCKED** on content data | Hero CTA → `/tours`, contact form (`/contact/send_home`), office map, footer with company contacts. One H1. |
| Company | `/company/about_company`, `/employees`, `/awards` | employees, awards, countries | READY (code) / content BLOCKED | Response-cached. |
| Services/discovery | `/countries(/{slug})`, `/destinations(/{slug})` | countries_images, destination_images | READY (code) / content BLOCKED | Missing images render a placeholder, not a broken `<img>`. |
| Info | `/helpful_information/{news,news/{slug},news/rss,interesting_articles,…,for_our_clients,…,travel_dictionary}` | news, articles, our_clients | READY (code) / content BLOCKED | |
| Tour search | `/tours` | none | **READY as explicitly interim** — **owner verification BLOCKER** (§9) | Embedded Tourvisor module; truthful wording already present. |
| Contacts | `/contacts` (+ `contact/send_*`) | none | READY after **CSRF-cache fix (this slice)** | Legal/office/phones/e-mail; map; consent checkboxes; captcha; throttle. Opening hours conflict **BLOCKED** (owner, §10). |
| Reviews | `/reviews` | reviews | READ-ONLY in the public profile | Submission is **DEFER TO CRM** (needs staff moderation UI, which is not part of the public release). |
| Legal HTML | `/cookies`, `/personal-data-consent`, `/reviews/personal-data-consent`, `/reviews/publication-consent` | none | READY (text) | Carry the verified company details. |
| Legal PDFs | `/documents/*.pdf` | none | **BLOCKED — owner/legal** (§10) | User Agreement and Personal-Data Policy PDFs state a **different legal address**. |
| Header/footer/mobile nav | layout | none | READY | Verified at 360/768/1200 (§12). **NEEDS FIX (product, not done):** "Поиск туров" is not in the primary menu (only CTAs link to `/tours`). |
| Error pages | 404 (themed), 401/403/419/429/500/503 (standalone, `noindex`) | none | READY | 404 is a normal layout page with `index, follow` (404 status prevents indexing). |
| Registration/login/cabinets/chat/booking/documents/API/webhook | — | yes | **DEFER TO CRM** — **not registered in the public profile** | §8. |

Accessibility / SEO basics (all pages, all three widths): exactly one H1, `lang="ru"`, skip link, titled and described, canonical present, no horizontal overflow, no broken first-party resources. **NEEDS FIX (minor, not done):** many meta descriptions exceed ~160 characters (up to ~320) — search snippets will be truncated; no structured data (JSON-LD) exists anywhere (nothing to validate).

## 5. Data dependency — the hidden critical path

The public pages are **database-driven** (countries, destinations, news, articles, employees, awards, partners, best offers, our clients, reviews). The repository contains **no content seeder and no content dump** (`database/seeders` has only roles/users/tours/destination cities; `turfirma_dev_schema_only.sql` is schema only). Roadmap §Part B records that legacy dump content is "historical QA/reference only" and **legacy users/personal data must never be imported**.

Therefore *any* Laravel public release (route A or C below) needs, from the owner:

1. an **approved content source** (the live site's public content — exported from its own admin/DB — **public content tables only, no `users`, no `reviews` consent/personal fields beyond published text**);
2. a **content-import plan** onto the *new* schema (the rebuild schema differs from the legacy one — never assume column compatibility), rehearsed on a disposable DB;
3. image/file assets that the rows reference (`public/img/*` is tracked; uploaded content under `storage/app/public` is **not**).

The static `sitemap.xml` (273 URLs, curated) must match the imported content slug-for-slug; it is a release gate (§12).

## 6. Hosting facts: known vs. unverified

| Item | Status |
|---|---|
| Host | REG.RU shared hosting, nginx front (**observed**); ISPmanager / PHP 8.3.36 (**owner notes only — unverified**) |
| Hosting renewal/billing | **UNVERIFIED — owner task** (expired certificate is a warning sign) |
| PHP version actually selected for the site | **UNVERIFIED — owner task** |
| PHP extensions (`gd`, `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `ctype`, `tokenizer`, `xml`, `intl` optional) | **UNVERIFIED — owner task** (`gd` is needed for the captcha) |
| SSH / Composer / cron on this tariff | **UNVERIFIED** (not needed for the public profile if `vendor/` is uploaded built; needed for January CRM) |
| MySQL availability, version (MySQL vs MariaDB), charset/collation | **UNVERIFIED — owner task** |
| Outgoing mail (SMTP host/port/credentials, sender identity — roadmap **U-8** open) | **UNVERIFIED — owner task** |
| Document root can be set to `public/` | **UNVERIFIED — owner task** (mandatory; see GO conditions) |
| `.htaccess` honoured behind nginx | **UNVERIFIED** (the app ships a standard Laravel `public/.htaccess`; nginx-only hosts need the equivalent `try_files`) |
| Timeweb VPS `web-prod-spb-01` | **Preordered, not provisioned — not production** |

## 7. PHP / Composer compatibility (production PHP 8.3.36)

**Local evidence, produced without running the vulnerable 8.3.32:**

* The official `php-8.3.36-1-nts-Win32-vs16-x64.zip` was downloaded from `downloads.php.net` into a scratch directory; **SHA-256 `1a097d52b7a68622cdc723073b61cc70caf79c941218ff65b2d8441dbc6b10d4` matches `releases.json`**; `php -v` → 8.3.36 (NTS, built 2026-10-26). Extensions enabled for the run: curl, gd, mbstring, openssl, pdo_sqlite, sqlite3, fileinfo, intl, zip, pdo_mysql, mysqli.
* **Full PHPUnit on PHP 8.3.36** (SQLite `:memory:`, Xdebug off, `memory_limit=3G`): **2500 tests / 19287 assertions, 0 failures, 0 errors** — identical to PHP 8.4.13 and 8.5.12.
* The public profile was served by the built-in server on PHP 8.3.36 in `APP_ENV=production`, `APP_DEBUG=false` against a disposable SQLite database and exercised in a real browser (§12). Production-mode log: no errors.
* Static analysis of `composer.lock` (Composer's own `Semver`): **every locked package's `php` constraint admits 8.3.36**; only `nette/utils 4.0.4` (`>=8.0 <8.4`) and `nette/schema 1.3.0` (`8.1 - 8.3`) **reject 8.4/8.5** (already recorded in ENV-COMPAT-01). `laravel/framework v12.65.0` requires `^8.2`, but the **runtime** packages `intervention/image 4.2.1` and `intervention/gif 5.0.1` require **`^8.3`**; the generated `vendor/composer/platform_check.php` therefore enforces `PHP_VERSION_ID >= 80300` and answers HTTP 500 on PHP 8.2. **Net effect: the locked application supports exactly the PHP 8.3.x series** (≥ 8.3 from intervention, < 8.4 from nette) — a one-minor-version window that happens to match the owner's reported host.

**Remaining compatibility blockers / risks (nothing in the code, all environmental):**

1. **B-PHP-1 — the host's real PHP version and extension set are unverified** (§6). Local success on 8.3.36 does not prove the host's build/ini (open_basedir, disabled functions, memory_limit 256M in `public/.user.ini`).
2. **B-PHP-2 — the host must stay on PHP 8.3.x (neither 8.2 nor 8.4+ per Composer's platform rules).** Runtime and tests do pass on 8.4/8.5 locally, but the declared ceilings (`nette/*`) mean that auto-upgrade of the host's PHP default (or a downgrade to 8.2) must be treated as a release-breaking event; pin the PHP version in the hosting panel and re-validate before any change. The dependency-modernisation pass remains a **separate E6 task** (roadmap guardrail). No `composer update` was run.
3. `composer.json` declares `"php": "^8.0.2"` although Laravel 12 needs ≥ 8.2 — cosmetic and **not changed** (dependency/metadata change would need its own plan).
4. Build the release `vendor/` with **`composer install --no-dev --optimize-autoloader` using PHP 8.3** so the uploaded tree matches the target. `spatie/laravel-ignition` and the other dev tools are `require-dev`; with `--no-dev` there are no `_ignition` routes (they exist only when dev packages are installed — the route-allowlist test skips them for that reason).

## 8. Route choice and the public-only release profile

### 8.1 What was built (bounded, default **OFF**)

`RELEASE_PUBLIC_ONLY=true` (config `release.public_only`) makes `RouteServiceProvider` register **only** `routes/public.php` (+ the cookie-less `routes/seo.php`). Login, registration, password reset, all cabinets, chat/messages, booking documents, the internal API and the **Tourvisor webhook are not registered at all** — they answer 404 like any unknown URL; there is nothing to authorise and nothing to bypass. This is **not** link-hiding and **not** a global authorization switch: with the flag off (default, and the January mode) behaviour is unchanged, and no `can`/role/policy code was touched.

* **Fail-closed:** a new route added to `routes/web.php` is private by default. `PublicReleaseProfileTest` enumerates every registered route in the profile and fails unless it is in the explicit allow-list.
* **One codebase:** January = flip the flag, run migrations, configure the CRM runtime. No second repository, no duplicated views.
* Header "Войти/Регистрация/кабинет" block, the review submission UI and the "Войти в кабинет" button on 401/419 are hidden **only** in this profile.
* Config is read when configuration loads: after changing the variable run `php artisan config:clear` (or rebuild the config cache). `route:cache` is unusable in this project already (closures in `web.php`).

### 8.2 Route comparison

| Criterion | **A. Laravel public profile on existing REG.RU** | **B. Keep/refresh the current site** | **C. Laravel on a verified VPS** |
|---|---|---|---|
| Time/complexity (November) | Medium: content import + MySQL + upload `vendor/` + docroot + mail | Low code effort, but **still requires fixing TLS** and any content refresh uses the old admin | High: Timeweb not provisioned; OS hardening, nginx, PHP-FPM, TLS, DB, backups, monitoring |
| PHP/Composer | 8.3.36 validated locally; host unverified | n/a (existing runtime) | Free to choose, but nette ceiling (≤8.3) applies until modernisation |
| Database | **New/empty MySQL + content import** (owner-approved source) | Existing DB, untouched | Same as A, plus server DB install |
| Security exposure | Smallest possible (public-only profile; no auth surface, no webhook) | Existing older release with login/registration/cabinets live | Larger (own server to harden) |
| Availability/rollback | Stage on a **separate hostname/dir**, cut over by switching docroot/vhost; rollback = switch back (old tree kept) | Trivial | Switch DNS back; longer propagation |
| SEO/URL continuity | Same URL scheme (`/company/…`, `/helpful_information/…`); `/tours` is new; canonical host must be fixed first | Unchanged URLs | Same as A, DNS move adds risk |
| Maintenance | One codebase | Old code in parallel to the new one (duplicate permanent codebase) | One codebase, more ops |
| CRM in January | Shared hosting likely **insufficient** for scheduler/queue/SSH → move to VPS in January with the same code | Dead end — CRM needs the new app anyway | **Best fit for January** |
| Cost | Existing hosting | Existing hosting + certificate | New server cost |

### 8.3 Recommendation

* **PRIMARY — Route A, staged:** Laravel public-only profile on the **existing REG.RU hosting**, deployed first to a **separate non-production hostname/directory** for owner acceptance, then cut over by docroot/vhost switch with the old tree retained for rollback.
* **FALLBACK — Route B-minimal:** if any hard GO gate in §13 is not met by the decision date, **keep the current site and only restore accessibility**: renew/replace the TLS certificate (covers `avilona.ru` **and** `www`), pick one canonical host and 301 the other, refresh `robots.txt`/`sitemap.xml`, verify the Webmaster properties. This alone makes the existing site "accessible and indexable" and costs no development.
* **Route C (VPS)** is **not** a November dependency; it is the proposed January platform for the CRM (decision stays with the owner; Timeweb capacity unconfirmed).

**Proposed dates (owner may change):** gate review **2026-11-10** · staging acceptance **2026-11-17** · cut-over window **2026-11-20…25** · fallback trigger **2026-11-20** if content import, PHP/extension/DB verification, PDF/legal or lead-delivery gates are still open. **The expired TLS certificate should be treated as urgent regardless of route** — every day it stays expired the live site is unreachable for normal browsers.

## 9. Lead intake: contact forms and the Tourvisor interim page

**Contact forms** (`/contacts`, home): CSRF via `@csrf`; throttle `8/min/IP`; server validation (name, email, message ≥ 50, subject ≤ 150); image captcha; mandatory user-agreement and personal-data consent checkboxes with server `accepted` rules; neutral error text ("Ошибка отправки сообщения."); on mail failure only the **exception class** is logged (no PII); **no database access** in either controller (code inspection; the public-profile test sends the form with `Mail::fake` — recipients: `straus97@mail.ru` internal routing is **preserved unchanged**, public address `avilonatur@bk.ru` shown on the site is **unchanged**). No account/e-mail enumeration (no lookup of the submitted address). Mail was tested only with `Mail::fake`/the `array` mailer — **no real mail was sent**.

Findings:

* **Fixed — `/contacts` CSRF defect.** `/contacts` is behind `cache.response` (60-minute shared cache) and its HTML contains the first visitor's CSRF token; every later visitor would receive someone else's token and get **HTTP 419 on submit** for up to an hour. Reproduced with a probe, fixed in `CacheResponse` (never store a response that contains the current session token), covered by `CacheResponseCsrfIsolationTest`.
* **Residual (design, not changed):** the form e-mails a copy of the visitor's own text to the address they typed (`UserFormMail`). Captcha + 8/min throttle bound abuse, but it is a mail-reflection vector; consider removing or rate-limiting per recipient in NOV-PUBLIC-02 (owner decision).
* **Residual:** no honeypot; captcha is the only automated-abuse control.
* **Mail transport/sender identity (U-8) is unverified** — until a real SMTP route is verified by the owner, the form cannot be called working in production.

**`/tours` (Tourvisor module 9981450):** *usable as an explicitly interim, honest feature.* The page states that results come from Tourvisor, prices/availability change, and that **a request is not a booking or payment**; a noscript note and a 10-second "module did not load" notice give a human fallback (contact the manager). It makes **no server call and no database access**; no Tourvisor key is used.
Limits (cannot be proven locally): the module is **domain-bound** — on `127.0.0.1` it fails (`tourvisor.ru/api/v1.2/settings` blocked, `stat…/sessions` 400), so it must be verified **on the production domain**. In the public profile the Tourvisor **webhook is not registered**, so leads reach the agency **only** through whatever notification channel is configured in the Tourvisor personal account → **owner BLOCKER: perform a real test request on the production hostname and confirm who receives it**, confirm the Tourvisor plan/renewal, and resolve the provider privacy facts (roadmap: `PRIVACY_LEGAL_FACT_REQUIRED`). If any of these fails, hide the search and show only the contact CTA (truthful fallback) — do not present it as automated booking.

## 10. Company details and legal documents

Verified details were **not changed** (checked in the views: INN 7805502454, KPP 780501001, OGRN 1097847289803, OKPO 62981592, OKTMO 40340000; registered address 198302 …Морской Пехоты, д. 10, к. 1, лит. А, кв. 22; actual office 198261, ул. Генерала Симоняка, д. 10). The map is an owner-curated Google embed titled "Туристическое агентство «Авилона»" and the "open in Google Maps" links query the **office** address `198261…`; the legal address appears only as text. Nothing was moved. Banking details were not touched. (KPP/OKPO/OKTMO/OGRN appear on `/contacts`; INN/OGRN/legal address on the consent pages.)

**PDFs** (tracked in `public/documents/`, linked from the footer/consent/tours pages and listed in the sitemap): text extracted with `pdftotext`:

| PDF | Finding |
|---|---|
| `User_Agreement.pdf` | **Legal address `191119, г. Санкт-Петербург, ул. Звенигородская, д. 22 литера А` ≠ verified registered address 198302.** No INN/OGRN in the text. |
| `Policy_regarding_the_protection_and_processing_of_personal_data.pdf` | **Same stale address 191119 / Звенигородская.** References Federal Law of 27.07.2006. |
| `Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf` | Generic offer template ("г. Москва", dated clauses 2012/2019); **no mention of «Авилона», INN, OGRN or address** in extractable text. |
| `Public_offer_for_ticket_sales_and_hotel_bookings.pdf` | Template (2012); no company identification in extractable text. |
| `Reminder_for_those_traveling_abroad_general.pdf` | Informational memo; no company data (not a binding document). |

These are **legally binding or consent-bearing documents and were not edited, regenerated or "fixed"**. The HTML consent pages (correct 198302 details) and the PDFs now **disagree**. **Owner action (BLOCKER for the public release):** legal/owner confirms which version is current, supplies corrected files (or approves publishing as-is in writing), and the editable `.docx/.doc` sources are updated. Replacing `User_Agreement.pdf` changes the review-consent version fingerprint by design (`sha256` of the file) — acceptable, not a defect.

Also open from the roadmap: **opening hours** conflict (`/` and header 10:00–20:00 vs `/contacts` 11:00–20:00 by appointment, `PENDING_BUSINESS_DECISION_OPENING_HOURS`) — owner decides before launch; no text was invented.

## 11. SEO, robots, sitemap, indexability

| Topic | Finding | Action |
|---|---|---|
| `robots.txt`, `sitemap.xml` location | They lived in the **repository root**, outside the document root (`public/`) → crawlers would get **404** | **Fixed:** cookie-less routes `/robots.txt`, `/sitemap.xml` serve the root files (root files stay the single source and their existing tests keep working). A real `public/robots.txt` would take precedence if ever added. |
| `robots.txt` content | Disallows technical dirs and private route prefixes; `Sitemap: https://avilona.ru/sitemap.xml` (apex). Mixed legacy `/public/...` prefixes; does not list `/api`, `/captcha`, `/messages`, `/password`. | Not changed (owner-curated, tested). Protection of private routes does **not** rely on it (§8, X-Robots-Tag). |
| Sitemap | Static, 273 curated `https://avilona.ru/...` URLs incl. 4 PDFs and ~250 news/article slugs; no private routes; no `<lastmod>` | **Must match the imported content slug-for-slug** (post-deploy crawl, §13). Live site's sitemap is on `www`. |
| Canonical | `<link rel="canonical">` = `url()->current()` (no query) — correct per page; host comes from the request | Pick **one** host (repo assumes apex). Enforce with a 301 for the other host and `APP_URL=https://avilona.ru`. |
| HTTPS awareness | Behind a TLS-terminating proxy PHP may see `http` → mixed-content/`http://` canonical | **Fixed:** `URL::forceScheme('https')` when `APP_URL` starts with `https://` (no effect for `http`/local). |
| `noindex` for private/technical surfaces | Only error pages had it; login/register pages were indexable by header | **Fixed:** global `X-Robots-Tag: noindex, nofollow` for auth/cabinet/api/captcha/sanctum paths and for any response to a signed-in user; covered by `PublicSeoFilesAndNoIndexTest`. In the public profile those routes do not exist at all. |
| Redirects / www / HTTP→HTTPS | `.htaccess` has only Laravel defaults + trailing-slash 301; **no www or HTTPS rule** | Must be configured at the server/ISPmanager level (owner) and verified (§13). |
| Titles/H1/descriptions | 1 H1 and a unique title everywhere; descriptions often > 160 chars; no structured data | Minor SEO polish → NOV-PUBLIC-02 candidates. |
| Broken internal links | None found by the headless crawl of 19 public templates (3 widths) + legal pages | — |
| Public vs private | Verified: anonymous requests to `/login`, `/register`, `/cabinet*`, `/admin*`, `/manager*`, `/profile*`, `/account`, `/messages*`, `/bookings*`, `/api/*`, `/reviews/create`, Tourvisor webhook → **404** in the profile | — |

Nothing is claimed about indexing or ranking; those can only be observed after launch in the Webmaster tools.

### Yandex Webmaster / Google Search Console launch checklist (owner, after cut-over)

1. Decide canonical host (apex recommended by the repository); make the other host 301 to it.
2. Valid TLS for **both** hostnames; HSTS only after TLS is verified valid everywhere.
3. Add/verify **one property per canonical host** (HTTPS) in Yandex Webmaster and Google Search Console (meta-tag/DNS/file method — `google*.html` / `yandex*.html` verification files are git-ignored by design: add them **on the server**, not in git; DNS method needs the owner).
4. Submit `https://<canonical>/sitemap.xml`; check "robots.txt" analysis and that it fetches HTTP 200 as `text/plain`.
5. Yandex: check "Region" (Saint Petersburg), "Site quality"; Google: URL Inspection of `/`, `/contacts`, `/tours`, one country, one news item.
6. Confirm that the old host/property history (if any) and the 272-URL live sitemap are reconciled with the new one.
7. Record observations only after they are real (no "indexed" claims in advance).

## 12. Verification performed in this slice (evidence)

Focused tests (all green on 8.5.12): `CacheResponseCsrfIsolationTest` (5), `PublicSeoFilesAndNoIndexTest` (5), `PublicReleaseProfileTest` (8, boots the app **with the profile on**), `PublicReleaseProfileDefaultOffTest` (3), `PublicHttpsSchemeTest` (2).
Full PHPUnit (sequential, SQLite `:memory:`, Xdebug off, `memory_limit=3G`): **PHP 8.4.13, 8.5.12 and 8.3.36 — each 2500 tests / 19287 assertions, 0 failures, 0 errors** (+23 tests / +250 assertions over 2477 / 19037; one pre-existing PHPUnit XML-schema deprecation notice). Six existing tests that read `routes/web.php` as source were repointed to `routes/public.php` (five) or to both files (one) because the route definitions moved; no assertion was weakened.

Browser QA (headless Chrome 155 over the DevTools protocol; disposable SQLite DB with synthetic rows; PHP 8.3.36 built-in server on `127.0.0.1:8123`, `APP_ENV=production`, profile ON; no real DB, no real mail): 22 URLs × 360/768/1200 px.

* No horizontal overflow; exactly one H1; no `/login`, `/register`, `/cabinet` links on any public page; no broken first-party resources; no first-party console errors (the only console noise is the Tourvisor module blocked on `127.0.0.1` and the intentional 404 pages' own status).
* `/login`, `/register`, `/cabinet`, `/admin/dashboard`, `/api/destination-cities` → 404; `/robots.txt` → `text/plain`; `/sitemap.xml` → XML; `/.env`, `/composer.json`, `/storage/logs/laravel.log` → 404 (the built-in server's docroot is `public/`).
* 360 px mobile menu opens and lists Главная, Компания, Страны, Направления, Контакты, Отзывы, Полезная информация.
* Contact form: both consent checkboxes present; a bad captcha is rejected (validation error, no success, **no HTTP 419** even on a cached page).
* **Not claimed:** any production-browser verification, Tourvisor behaviour on the real domain, real SMTP delivery, real TLS, or Webmaster results.

## 13. Production GO / NO-GO conditions (route A cut-over)

**NO-GO if any is false:**

| # | Condition | Owner/Dev |
|---|---|---|
| G1 | Hosting active and renewed; **PHP 8.3.x (≥ 8.3.36 security level) selected** for the site; extensions `gd mbstring openssl pdo_mysql fileinfo curl ctype tokenizer xml` present; `memory_limit ≥ 128M` | Owner |
| G2 | **Valid TLS** for apex and `www` (auto-renewal enabled); HTTP→HTTPS 301; one canonical host with 301 from the other | Owner |
| G3 | **Document root = `public/`** (verified: `/.env`, `/composer.json`, `/storage/…`, `/vendor/…`, `/routes/web.php` all 404/403 on the real host) | Owner+Dev |
| G4 | Production DB created (MySQL, utf8mb4); **content imported from an owner-approved public source onto the current schema**; **no `users`/PII imported**; sitemap crawl = 100 % HTTP 200 | Owner+Dev |
| G5 | `.env` provisioned **on the server only** (APP_ENV=production, APP_DEBUG=false, new APP_KEY, APP_URL=https://…, `RELEASE_PUBLIC_ONLY=true`, DB, SMTP); no secret in git/chat/package | Owner |
| G6 | SMTP route proven by **one owner-run real test** of each public form; sender identity decided (U-8); recipients as designed (`straus97@mail.ru` internal; `avilonatur@bk.ru` public) | Owner |
| G7 | Legal PDFs and opening hours resolved (§10) **or** explicitly accepted in writing by the owner | Owner/legal |
| G8 | Tourvisor: owner-run production-domain test request received by the agency; plan/renewal confirmed; **else `/tours` falls back to a contact CTA** | Owner |
| G9 | `RELEASE_PUBLIC_ONLY=true` confirmed on the server: `/login`, `/register`, `/cabinet`, `/admin`, webhook → 404 | Dev |
| G10 | Tested rollback (the previous docroot restored and responding) and a **backup of the current site and DB** exist | Owner+Dev |
| G11 | Post-deploy smoke (below) 100 % green | Dev |

**GO** requires G1–G11. **Fallback (B-minimal)** if G1–G8 cannot be met by the trigger date: renew TLS, choose host, refresh robots/sitemap, verify Webmaster — no Laravel cut-over.

## 14. Release and rollback runbook (**prepared, NOT executed**; each step needs explicit owner approval)

**A. Preparation (developer machine)**
1. Tag the release commit; record the SHA.
2. With **PHP 8.3** (use a verified 8.3.36 binary): `composer install --no-dev --prefer-dist --optimize-autoloader` (from the lock; **no `composer update`**). Do **not** run `npm`; the public layout does not use Vite.
3. Package: `app bootstrap config database lang public resources routes storage vendor artisan composer.json composer.lock robots.txt sitemap.xml` — exclude `.env*`, `.git`, `.claude`, `tests`, `node_modules`, `docs`, `*.sql`, `*.bat`, `storage/logs/*`, `storage/framework/{cache,sessions,views}/*`, `public/storage` symlink, `public/hot`. Checksum the archive.

**B. Server (owner/with owner supervision)**
4. Backup current docroot and current DB (provider panel) → store off-server; **test-restore** to a scratch location.
5. Upload to a **new directory** (not over the live one), e.g. `/new_release/`. Create `storage/{app/public,framework/{cache/data,sessions,views},logs}` and `bootstrap/cache` writable (`0775`, owner = PHP user). Do not make `storage` web-reachable.
6. Create `.env` on the server (key **names** only here): `APP_NAME, APP_ENV=production, APP_DEBUG=false, APP_KEY (php artisan key:generate --show, once), APP_URL=https://avilona.ru, RELEASE_PUBLIC_ONLY=true, LOG_CHANNEL=daily, LOG_LEVEL=warning, DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, SESSION_DRIVER=file, SESSION_SECURE_COOKIE=true, CACHE_DRIVER=file, QUEUE_CONNECTION=sync, MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS, MAIL_FROM_NAME`; **leave `TOURVISOR_*` unset**; `chmod 600 .env`.
7. Point a **staging hostname** at `new_release/public` (needs DNS/vhost by the owner) — or use a temporary path the owner supplies. **Do not touch the production vhost yet.**
8. Migrations on the **new, empty** production DB only: `php artisan migrate --force` (requires SSH/CLI; otherwise import a migrated schema dump built locally on a disposable MySQL). Never run migrations on the legacy DB.
9. Import approved public content (G4) → crawl the sitemap against staging.
10. `php artisan config:clear && php artisan view:clear` (do **not** `route:cache`).
11. Owner acceptance on staging: all §13 conditions measurable on staging (TLS, mail, Tourvisor needs the production domain → repeat G8 after cut-over).

**C. Cut-over (agreed low-traffic window)**
12. Switch the live vhost docroot to `new_release/public` (the old tree stays in place, untouched). Purge CDN/cache if any.
13. **Smoke (immediately):** `/`, `/tours`, `/contacts`, `/reviews`, one country, one news item, `/cookies` → 200 with valid TLS; `/robots.txt` text/plain with the correct `Sitemap:`; `/sitemap.xml` 200 and 100 % of its URLs 200; `/login`, `/register`, `/cabinet`, `/admin/dashboard`, `/api/webhooks/tourvisor/inquiries/x`, `/reviews/create` → 404; `/.env`, `/composer.json`, `/storage/logs/laravel.log`, `/vendor/autoload.php` → 404/403; `http://`→`https://` 301; www↔apex 301; response headers: `X-Frame-Options`, `X-Content-Type-Options`; no `X-Robots-Tag` on public pages; one real test of each form (mail received); Tourvisor test request (G8); view page source for `https://` assets only (no mixed content).
14. Check `storage/logs/*` for errors every hour for the first day; set up external uptime monitoring on `/` (**the app has no health endpoint**; do not add a route that exposes internals).

**D. Rollback (any smoke failure or first-day critical error)**
15. Switch the vhost docroot back to the previous directory; confirm `/` returns the old site; keep `new_release` for diagnosis. DB was never shared with the legacy site, so there is nothing to revert in the legacy DB. Record the incident; no partial fixes in production.

**E. Search-engine setup** — §11 checklist, after a stable 24 h.

## 15. Next-stage (January) CRM deployment strategy

The same codebase, `RELEASE_PUBLIC_ONLY=false`: apply the pending migrations through a guarded production migration plan (roadmap E6: E5 migrations, audit-events, inquiry tables, …), `QUEUE_CONNECTION` and scheduler cron, outgoing-mail identity (U-8), retention decision (R-B/U-10), Tourvisor secrets + webhook, FK/booking-cascade risk, session-reactivation residual, independent audit and AOA-18 QA gate. Shared hosting without SSH/cron/queue is unlikely to be sufficient → plan the VPS move (Timeweb or other) as the **January** platform with its own runbook; the public site keeps working during that move because the public profile is the same code. **None of this is part of NOV-PUBLIC.**

## 16. Residual register (carried, not fixed in this slice)

R-B/U-10 retention · booking FK cascade risk · session-reactivation residual · PHP/Composer ceilings (nette) · public legal PDFs (§10) · opening hours · Tourvisor privacy facts and production secrets/webhook · U-8 sender identity · E6 gates · AOA-05-I2 planning-only · AO-12 admin chat overflow · `UserFormMail` reflection (§9) · meta-description length · "Поиск туров" missing from the primary menu · per-page `noindex` on the 404 layout page.

## 17. Recommended NOV-PUBLIC-02 scope

1. **Content provisioning:** owner-approved public-content export → import tool with a rehearsal on a disposable MySQL, sitemap/slug reconciliation, asset list (read-only for the owner's data; synthetic rehearsal first).
2. **Hosting verification kit:** a one-file, non-secret PHP diagnostics script *the owner runs on the host* (PHP version, extensions, `memory_limit`, `.htaccess`/rewrite, writable dirs, DB connect with provided credentials) — report back, never with secrets.
3. **Legal documents:** apply the owner-supplied corrected PDFs/offer templates (no authoring by us).
4. **SEO polish** (only if the owner wants it before launch): description length, "Поиск туров" in the menu, `noindex` for the 404 template; `UserFormMail` decision.
5. **Staging cut-over rehearsal** on the chosen target once G1–G3 are verified by the owner.
