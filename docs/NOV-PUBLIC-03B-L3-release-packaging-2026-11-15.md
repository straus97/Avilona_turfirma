# NOV-PUBLIC-03B-L3 — Public-only release packaging

Status: local development tooling. **Production NOT DEPLOYED.** No server, DNS or database was touched.
Backup/restore remain unverified and are owned by the infrastructure team.

## What it is

`scripts/release/build-public-package.php` builds one `.tar.gz` of the Laravel application for the
November PUBLIC-ONLY release from a **committed revision** (`git archive`), restricted by the explicit
allowlist in `scripts/release/public-package-manifest.json`. It is not a fork: the same repository and
the same `RELEASE_PUBLIC_ONLY` runtime switch are used.

```
php scripts/release/build-public-package.php --out=<directory outside the repository> [--rev=HEAD]
```

Output (in `--out`): `avilona-public-<sha12>.tar.gz`, `.tar.gz.sha256`, `.files.txt` (sorted file list).
Nothing is written inside the repository; nothing is committed.

## Safety model (fail-closed)

1. Source is the git object database only — `.env`, logs, QA output, `vendor/`, untracked files cannot enter.
2. `git archive` is given an explicit pathspec list; `.gitattributes` `export-ignore` still drops `.doc/.docx/.psd`.
3. The produced tar is parsed entry by entry. The build **fails and deletes the intermediate** unless every entry:
   matches an allow regex; matches no deny regex; is a regular file (symlinks/hardlinks rejected); has no
   absolute path, `..`, backslash, drive letter or NUL; has no dotfile outside `.gitignore/.htaccess/.user.ini`;
   and contains no private-key block, `APP_KEY=base64:` value, `DB_PASSWORD=` value or SQL-dump header.
   All required files (routes, config, five legal PDFs, logo, favicon, `robots.txt`, …) must be present.
4. Reproducible: commit-time mtimes, normalized modes, gzip header mtime 0 → two builds of one commit are
   byte-identical (verified, SHA-256 equal).

## Included / excluded

Included: `artisan`, `composer.json`, `composer.lock`, `robots.txt` (read at runtime by `/robots.txt`), `app/`,
`bootstrap/app.php`, `bootstrap/cache/.gitignore`, `config/`, `database/migrations/`, `lang/`, `routes/`,
`resources/views/`, `resources/seo/`, `public/` (index, `.htaccess`, favicon, `css`, `js`, `img`, `documents`
PDFs), `storage/**/.gitignore` placeholders (empty writable skeleton).

Excluded: `tests/`, `docs/`, `scripts/`, `.claude/`, `.cursor/`, `node_modules/`, `vendor/`, `.env*`,
`database/seeders` (default `admin@… / password` accounts) and `factories`, `resources/js|css|sass`, root
`package*.json`, `vite/tailwind/postcss` configs, `phpunit.xml`, `README`, `structure.txt`, dev `.bat/.sh`,
`turfirma_dev_schema_only.sql`, `backup-db.bat`, `.env.example.generated`, `.doc/.docx/.psd`,
**`public/dist` and `public/plugins`** (AdminLTE).

Why `public/dist` and `public/plugins` are safe to drop: no public view, layout, error page, CSS, JS, PHP or
route references them. The public layout loads Bootstrap from a CDN; the only local references are in
`layouts/profile`, `cabinet/*`, `manager/*`, `admin/*` and `profile/chat` views, which are unreachable under
`RELEASE_PUBLIC_ONLY=true`. Verified by grep and by rendering the public pages from the extracted package.
(Caveat: a page that stored a `/plugins/…` or `/dist/…` URL inside database content would not be detected —
this was not checked against the content import set and should be confirmed during the VPS stage rehearsal.)

## Runtime contract

`RELEASE_PUBLIC_ONLY=true` is **not** in the archive and not hardcoded anywhere; the central team sets it in
the server environment. Without it the application behaves as the full CRM (default OFF) — so a package is
only "public-only" when the environment says so. After changing it: `php artisan config:clear`.

## Verification performed (synthetic, SQLite `:memory:`)

- Clean extract → `composer install --no-dev --optimize-autoloader` (PHP 8.5.12, no `--ignore-platform-reqs`,
  83 packages from `composer.lock`, no dev package present, `package:discover` ok).
- `config:cache`, `route:cache`, `view:cache` succeed with the flag on.
- Public-only: 31 routes (the allowlist plus the captcha and sanctum vendor routes already accepted in
  `PublicReleaseProfileTest`); `/`, `/contacts`, `/countries`, `/tours`, `/company/*`, `/robots.txt`,
  `/sitemap.xml` → 200; login, register, admin, manager, cabinet, profile, chat, password reset, API, Tourvisor
  webhook → 404. Same on PHP 8.4.13 and 8.3.32.
- Negative tests of the validator (planted `.env`, `.sql`, private key, SQL dump header, `.zip`, dotfile,
  private `storage/app` file, symlink, missing required file) all fail the build and leave nothing behind.
- `tests/Feature/PublicReleasePackagingTest.php` locks the manifest contract and byte-reproducibility.

## What the central VPS team receives (when deployment is approved)

Already provisioned (not touched here): user `avilonaapp`, `/var/www/avilona/{releases,shared}`, PHP-FPM pool
`avilona`, nginx preview `127.0.0.1:8081`, database `avilona_stage`.

They receive: the `.tar.gz`, its `.sha256`, the commit SHA, and this note. Deployment steps for them to own:

1. Verify the checksum; extract into `releases/<sha12>/`; web root = `releases/<sha12>/public`.
2. `composer install --no-dev --optimize-autoloader --no-interaction` on the server PHP (8.5.x) from the
   shipped `composer.lock`; never `--ignore-platform-reqs`.
3. Provide `shared/.env` (symlinked) with real values — nothing is generated by us: `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `RELEASE_PUBLIC_ONLY=true`, `SEO_PUBLIC_URL`, `SEO_NOINDEX=true`
   for staging, DB (`avilona_stage`), mail, `TRUSTED_PROXIES` if behind nginx.
   *(Correction, ROADMAP-SYNC-03 2026-11-15: the application reads **no** `TRUSTED_PROXIES` variable —
   `App\Http\Middleware\TrustProxies::$proxies` is `null`. Proxy trust is not configurable through `.env` today;
   see roadmap §A10.F / risk R-V4. HTTPS link generation is covered by `APP_URL=https://…`.)*
4. `shared/storage` symlinked to `storage/` (writable by `avilonaapp`); `bootstrap/cache` writable.
5. `php artisan migrate --force` only against `avilona_stage`, only by the infrastructure team; content import
   (`content:import-public`) is a separate, owner-approved step.
6. nginx must deny `*.doc|*.docx` in `public/documents` if those files are ever added (not in the package),
   serve only `public/`, and block dotfiles.
7. Switch the `current` symlink atomically; reload PHP-FPM; rollback = previous release directory.

## Remaining blockers (not solved by packaging)

Hosting/TLS/DNS gates, backup and restore verification (infrastructure), owner decisions on content (D-1…D-7),
legal PDFs with the correct company details (N-4), canonical host (N-5), Tourvisor lead delivery (N-6),
square favicon (N-10), and the unverified production MySQL 8.4.13 behaviour of the migrations
(a VPS stage rehearsal against `avilona_stage` is the next step). Package size is ~112 MB, dominated by
`public/img`; image optimization is a separate owner/asset decision.
