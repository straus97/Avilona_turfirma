# NOV-PUBLIC-03A-F1 — SEO audit database safety and public release file hygiene

Date: **2026-11-01** · Branch `db-rebuild-stage3` · Base HEAD `b0fb9273a672be427e942e9ae0325dbeb7ed9069`

> Status authority: [`docs/roadmap.md`](roadmap.md). Nothing deployed; no DNS, hosting, provider, real database or real data. Hosting is not purchased; NOV-PUBLIC-03B is **not** started. Public deployment stays **NO-GO until the owner gates**.

## 1. The defect found in 03A

`seo:audit-urls --check-db` ran `exists()` against **whichever connection the application defaulted to**. In this workspace `.env` points at the canonical `turfirma_rebuild_v4` on WAMP port 3308, so an operator running the documented command would have queried the canonical database. Read-only does not make that acceptable. The 03A report and doc described it as a feature; that was wrong.

## 2. Fix

* `--check-db` now **requires** `--connection=<name>` and passes it through the project's existing `VerifiedQaConnection` (AOA-03-I2) **before the first query**: explicit name, `qa_disposable === true` (`AOA_QA_DISPOSABLE`), not production, supported driver, MySQL only on loopback with an explicit unprivileged port other than 3306/3308, database `aoa03_qa_*` and never `turfirma_rebuild_v4`, no URL/socket override, no collision with any other configured connection (the default included); SQLite only `:memory:` or `aoa03_qa_*.sqlite` in the OS temp directory.
* For MySQL, after the config check the server must also report the **same database name and port** (`select database(), @@port`) — a forwarded port that lands on another database is refused. The existing guard is configuration-only; this one extra read runs only on an already config-verified loopback QA target and fails closed.
* The slug lookups use `Model::on(<verified connection>)`; the default connection is never touched, and `--connection` without `--check-db` is an error.
* There is **no override flag** and the `VerifiedQaConnection` rules are unchanged. Refusals print one generic line; they do not echo the database name, credentials, host or file paths.
* Database-free URL analysis (no `--check-db`) is unchanged and opens no connection.
* A future owner-authorised staging audit would need its own controlled operation; it is **not** authorised here.

## 3. Tests (`AuditLegacyUrlsCommandTest`, now 19)

DB-free run: zero queries · `--check-db` without a connection / empty / unknown / malformed name / `--connection` alone: refused, zero queries · 11 unsafe targets (canonical database, ports 3306 and 3308, not disposable, marker as string, non-loopback, ordinary name, URL override, empty config, unsupported driver, SQLite outside temp): refused with **zero** queries on the default connection, the protected connection is **never opened** (`DB::getConnections()`), no report file · no password / canonical name / temp path in the output · verified synthetic SQLite target succeeds with `select`-only queries and no row changes · malformed input stays safe with a verified target · CSV formula neutralisation, semicolon/BOM input, no-overwrite and the no-network/no-write source scan are kept. Synthetic fixtures only.

## 4. Public file inventory (tracked, `public/`)

| Group | Files | Needed by the public site? |
| --- | --- | --- |
| `index.php`, `.htaccess`, `.user.ini`, `favicon.ico` | 4 | **Yes** |
| `css/`, `js/` | 5 / 6 | **Yes** (`cabinet*.css`/`cabinet-chat.js` only for private surfaces, harmless) |
| `img/` (images) | 210 | **Yes** (approved static assets) |
| `documents/*.pdf` | 5 | **Yes** — legally binding, linked, untouched (content decision N-4) |
| `documents/.htaccess` | 1 | **Yes** — Apache deny for `.doc`/`.docx` |
| `documents/*.doc`, `*.docx` | 5 | **No** — editable sources, linked nowhere |
| `img/special/*.psd` | 2 | **No** — design sources, linked nowhere |
| `dist/` (AdminLTE) | 70 | **No for the public-only profile** — loaded only by cabinet/profile layouts |
| `plugins/` (AdminLTE) | 1984 | **No for the public-only profile** — same; verify with the production smoke before dropping |
| `build/`, `storage` | untracked | `build/` produced by `npm run build`; `storage` is the `storage:link` symlink — neither is in git |

## 5. Exclusions

The repository has **no release/packaging mechanism** (no workflow, script or Dockerfile); `git archive` is the only one a deployment can reasonably use, so a large system was not built. Implemented, narrowly:

* **`.gitattributes`**: `public/**/*.doc`, `*.docx`, `*.psd` → `export-ignore`. `git archive` then omits them (verified: the five PDFs, `documents/.htaccess` and the images stay). Files are not deleted or edited; the owner can lift the rule when publication is approved. A test pins the rules and the archive listing.
* **Documented** (not enforced here): the package must also exclude `.env*`, `.git*`, `.claude*`, `.cursor`, `.github`, `tests`, `docs`, `node_modules`, `*.sql`, `*.bat`, `structure.txt`, `backup-db.bat`; and, recommended for the public-only profile, `public/dist` and `public/plugins`.
* **Web server**: Apache keeps `public/documents/.htaccess` (403). **nginx:** `location ~* \.(docx?|psd)$ { return 404; }` (or exclude the files — preferred; a deny rule is a second line). `robots.txt` is not a download control.

## 6. Hosting-independent prerequisites (concise)

1. **Web root** = `public/`; `.htaccess`/rewrite equivalent; one layer owns HTTP→HTTPS and `www`→apex. 2. **PHP 8.3** (8.3.36+ pinned; extensions: mbstring, openssl, pdo_mysql, fileinfo, gd, intl, curl, zip), `composer install --no-dev` from the lock. 3. **TLS** valid for apex and `www`, auto-renewal monitored; canonical `https://avilona.ru` (owner N-5). 4. **Metadata/redirects**: `SEO_PUBLIC_URL` set; redirect matrix ≤ 1 hop; `?page=1` → 301; beyond-last page → 404. 5. **Source exclusions** per §5 and the file list in the package checksum. 6. **Public-content import**: owner-approved export (N-3), `content:import-public` dry-run → commit on staging. 7. **SEO URL audit**: DB-free on the owner's CSV; `--check-db` only on a disposable QA copy. 8. **Legal PDFs**: owner-approved texts (N-4) before they are listed or promoted. 9. **SMTP and Tourvisor**: real test of each form, webhook token, production secrets (N-6). 10. **Staging**: `SEO_NOINDEX=true` plus real authentication. 11. **Production smoke**: NOV-PUBLIC-02 post-deploy list. 12. **Rollback**: previous release directory kept, `.env` and storage outside the release, database backup before import, a one-command switch back.

## 7. Remaining November release blockers

Hosting and TLS (N-1/N-2/N-8) · approved content export (N-3) · legal PDF texts (N-4) · canonical host approval (N-5) · Tourvisor/SMTP production facts (N-6) · legacy URL evidence (N-9) · square favicon (N-10) · opening hours · live verification. All are owner or hosting items; none is closed here.
