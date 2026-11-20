# NOV-PUBLIC-03B-B52 — Stage backup/restore readiness checkpoint (2026-11-20)

**Documentation-only checkpoint.** No application, dependency, migration, configuration or test change; no VPS, MySQL, Docker, SSH or secret access by this task. It records infrastructure work performed 16–20 November 2026 by the owner with the infrastructure chat. Status of every stage is authoritative in [`roadmap.md`](roadmap.md); this file keeps the detail.

## 1. Evidence legend

| Label | Meaning |
|---|---|
| `VERIFIED_IN_REPO` | Seen in the repository at the time of writing. |
| `OWNER_CONSOLE` | Reported by the owner from verbatim console output on the VPS or Windows. **Not independently verified** by this repository's tooling or by the author; no live access exists. |
| `PENDING` | Not yet known or not yet received. |
| `NOT_DONE` | Not performed. A plan is not a result. |

No secret, password, key material, dump, ciphertext identifier or personal data is recorded here.

## 2. Repository and source baseline

| Item | Value | Status |
|---|---|---|
| Branch | `db-rebuild-stage3` | OWNER_CONSOLE (manual read-only Git audit) |
| Local HEAD | `801746f11b5dee486a5084bc01d6b8c666b7dae6` — **owner-verified pre-documentation-commit baseline**, not the future documentation commit HEAD | OWNER_CONSOLE |
| Tracking HEAD | `801746f11b5dee486a5084bc01d6b8c666b7dae6` | OWNER_CONSOLE |
| Live origin HEAD | `801746f11b5dee486a5084bc01d6b8c666b7dae6` | OWNER_CONSOLE |
| HEAD alignment (local = tracking = origin) | PASS | OWNER_CONSOLE |
| Staging area | empty | OWNER_CONSOLE |
| Worktree at audit time | **not clean**: modified tracked `docs/README.md`, `docs/roadmap.md`; new untracked `docs/NOV-PUBLIC-03B-B52-stage-backup-restore-checkpoint-2026-11-20.md` (these three intended documentation changes are not yet committed); other untracked `.claude/settings.json` | OWNER_CONSOLE |
| `git diff --check` / DOCS_GIT_AUDIT | PASS / PASS | OWNER_CONSOLE |
| Documentation commit hash (this checkpoint) | not yet created | PENDING |
| Last documented source baseline | `801746f11b5dee486a5084bc01d6b8c666b7dae6` (2026-11-15, ROADMAP-SYNC-03) — **not guaranteed to be the current HEAD** | VERIFIED_IN_REPO (docs) |
| Last documented release application commit | `0d34526e053dd78e9dae89ceb0753a7925c3dc07` (L3 packaging) | VERIFIED_IN_REPO (docs) |
| New Project Sources set for this checkpoint | not generated; no hash exists | NOT_DONE |

Project Sources regeneration, commit and push are performed separately by the owner after review. The owner reports that the ChatGPT Project Sources visible to them are dated 2026-09-23 (`OWNER_CONSOLE`); this disagrees with the roadmap's record that a 2026-11-15 set was promoted locally — the upload state in the chat project is unverified.

## 3. Environment and boundaries (OWNER_CONSOLE)

- Shared AdminVPS `web-prod-01` hosts Avilona and unrelated projects; no operation outside the explicitly authorised Avilona scope, no change to shared settings or neighbouring projects.
- MySQL 8.4.13 on the VPS, bound to `127.0.0.1:3306`. Schema `avilona_stage`: **empty**, `utf8mb4` / `utf8mb4_unicode_ci`. **The real Laravel stage migration has NOT been executed.** Laravel is not deployed on the VPS.
- The local canonical database `turfirma_rebuild_v4`, WAMP ports 3306/3308 and the default `.env` remain forbidden (see README §1.0.Q).

## 4. Completed checkpoints

| ID | Result | Evidence | Notes |
|---|---|---|---|
| **P5** — one-shot credential setup for `avilona_stage_migrator@127.0.0.1` | ✅ CLOSED / PASS | OWNER_CONSOLE | A single MySQL-generated password was set once, delivered encrypted, decrypted on Windows with a non-exportable key and stored in the owner's password manager. Installed approved script SHA-256 `231b18b91b8a58975daab1b6185d8e822edf4c78b8b3ebc7c55c03a98385c85a`; the one-shot marker is preserved. **P5 must never be retried or re-run automatically.** No credential material is recorded in Git. |
| **P7** — account unlock | ✅ CLOSED / PASS | OWNER_CONSOLE | Exactly one `ACCOUNT UNLOCK`; `account_locked` Y → N; authentication data and the exact eight schema grants unchanged. |
| **P8** — read-only TCP authentication | ✅ CLOSED / PASS | OWNER_CONSOLE | Windows loopback SSH tunnel `127.0.0.1:23306` → VPS `127.0.0.1:3306`; `CURRENT_USER()` correct; `DATABASE()=avilona_stage`; 0 visible tables; no DDL/DML, no migration. Local port `13306` was occupied and must not be modified. Tunnel state at any later time is not guaranteed. |
| **B5.2-BK-1** — VPS backup-tool preflight | ✅ PASS | OWNER_CONSOLE | `mysqldump 8.4.13`; `log_bin=1`; GTID off; binlog retention 2 592 000 s (30 days); expected Stage charset/collation. |
| **B5.2-BK-2** — Windows local restore host | ✅ PASS | OWNER_CONSOLE | Docker Engine 29.9.1; cached `mysql:8.4.13`; private storage about 69.7 GB free at check. |
| **B5.2-BK-3** — Docker context/image/names | ✅ PASS | OWNER_CONSOLE | Context `desktop-linux` (local Windows named pipe); image ID `sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902`; candidate container, volume and directory names free when checked. The canonical database must never be accessed. |

P5, P7 and P8 are not to be repeated or retested.

### Earlier local rehearsal (OWNER_CONSOLE, local and disposable only)

- On a **local disposable Docker MySQL 8.4.13** the owner reports 66/66 Laravel migrations applied and 36 resulting tables.
- A local two-table synthetic test demonstrated that sequential MySQL DDL is partial and not atomic, and that isolated restoration of those two tables worked.
- **This is NOT proof** of a successful VPS stage migration, of a restore of the real `avilona_stage` schema, or of production restore readiness. **R-V3 stays OPEN** (§9).

## 5. BK-4A: stop, root cause, diagnosis, cleanup

| ID | Result | Evidence |
|---|---|---|
| BK-4A initial backup attempt | 🛑 **STOP** — no valid Stage dump exists | OWNER_CONSOLE |
| BK4A-D1 read-only diagnosis | ✅ PASS | OWNER_CONSOLE |
| BK4A-D2 guarded empty-placeholder removal | ✅ **PASS** | OWNER_CONSOLE |

**Root cause (BK-4A).** The shell script enabled `set -C` (noclobber) and then created the output file early with `: > "$BACKUP_FILE"`. The following `mysqldump … > "$BACKUP_FILE"` was refused by noclobber ("cannot overwrite existing file"), so `mysqldump` did not run. The failure was in the shell redirection design, not in MySQL.

**BK4A-D1.** The protected directory `/home/vpsadmin/avilona_b52_bk4_20261120` (owner `vpsadmin`, mode 0700) contained exactly one entry, `avilona_stage_pre_b52.sql` (mode 0600, 0 bytes, SHA-256 of the empty string `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`). MySQL was not touched.

**BK4A-D2 (separately authorised, one-time, guarded).** Reported output: `BK4A_D2=PASS`, `SERVER_GUARDS=PASS`, `DIRECTORY_GUARDS=PASS`, `DIRECTORY_ENTRY_COUNT=1` before removal, `EXACT_FILE=VERIFIED`, `FILE_SIZE_BYTES=0`, `EMPTY_FILE_SHA256=PASS`, `EMPTY_PLACEHOLDER_REMOVED=YES`, `BACKUP_DIRECTORY_EMPTY=PASS`, `BACKUP_DIRECTORY_PRESERVED=YES`, `VALID_BACKUP_CREATED=NO`, `MYSQL_TOUCHED=NO`, `MIGRATIONS_EXECUTED=NO`, `OTHER_PROJECTS_MODIFIED=NO`, `BACKUP_RETRY_EXECUTED=NO`. Only the placeholder `/home/vpsadmin/avilona_b52_bk4_20261120/avilona_stage_pre_b52.sql` was removed; the parent directory remains in place and empty.

## 6. Authorization boundary (as of this checkpoint)

| Action | Status |
|---|---|
| Guarded removal of the zero-byte placeholder | ✅ authorised and **completed** (D2 PASS, `OWNER_CONSOLE`) |
| Preparing and reviewing a corrected `mysqldump` command | ✅ authorised (preparation/review only) |
| Executing a new `mysqldump` attempt | ⛔ **NOT APPROVED.** Requires the complete corrected command and its validation results to be reviewed and explicitly approved by the infrastructure authority first; then exactly one attempt. |
| Valid Stage backup | ❌ NOT_DONE (none exists) |
| Transfer of a dump to Windows | ❌ NOT_DONE |
| Local isolated Docker restore from a new backup | ❌ NOT_DONE |
| Real B5.2 Laravel migrations on `avilona_stage` | ⛔ **NO-GO** |
| Public activation / site cut-over | ⛔ **NO-GO** |
| Financial operations | ⛔ **NO-GO** |

On any discrepancy: STOP. Never auto-delete, auto-retry or clean up.

## 7. Next safe steps (PLAN — none executed, none authorised by this document)

1. Keep the D2 state: empty protected directory, no dump. Any deviation ⇒ STOP and read-only diagnosis only.
2. Design a corrected single-file, no-overwrite `mysqldump` strategy that **keeps** `noclobber` and does **not** pre-create the output file (the single `mysqldump` stdout redirection creates the file exclusively), with an existing 0700 directory and 0600 file, no deletion of a partial SQL file on error, correct schema identity/version/zero-object checks, MySQL 8.4.13 options reviewed (`--single-transaction`, `--no-tablespaces`, `--set-gtid-purged=OFF`, no global locks, no foreign schemas), and no password in output.
3. Independently audit the **entire** planned command and any strict SQL-content allowlist against the real MySQL 8.4.13 dump format — versioned comments (`/*!… */`), multi-line statements, `CREATE DATABASE` / `USE` behaviour. A previously drafted, untested regular-expression parser is **not** considered ready.
4. Submit the complete command plus offline validation results to the infrastructure chat; wait for explicit approval of **one** new attempt.
5. Only after an authorised PASS: inspect the SQL for non-Avilona references, record checksum and size, copy to Windows with SCP, verify checksum, restore in a **new** local Docker MySQL 8.4.13 with no published ports and no real databases mounted, then validate charset/collation and the empty schema.
6. Separately write a contingency plan for partial Laravel DDL (§8).
7. B5.2 live migrations, public activation and financial actions need separate approval after the above and after provider backup evidence.

## 8. Partial-DDL risk (recovery gate OPEN)

An individual supported MySQL DDL statement may be atomic, but each DDL statement commits implicitly, so a **sequence** of DDL statements inside a Laravel migration is not rolled back as a single transaction; earlier DDL changes stay committed if a later statement fails. A failure inside a migration (for example the `booking_documents.uploaded_by` foreign-key migration: drop FK → modify column → add FK) can leave the schema between two states. The earlier local two-table test showed partial DDL and an isolated restoration, but **no** successful VPS Stage restore exists.

Contingency principles (to be written as a separate plan; not yet written): STOP; collect read-only facts about the real schema and the `migrations` table; never run `migrate:fresh`, `migrate:rollback`, `db:wipe`, `DROP DATABASE`, or re-run a failed migration on the shared VPS automatically; assume no transactional DDL rollback; restore only `avilona_stage`, only with independent authorisation, confirming it holds no data and no external dependency. **Recovery gate: OPEN.**

## 9. Open gates and risks

| Gate | Status |
|---|---|
| AdminVPS backup/restore, ticket `880372` | **Unverified.** No successful isolated restore test is confirmed; presence of binlogs and `mysqldump` is not a verified restore point; availability, completeness, RPO/RTO unknown (R-V2 OPEN). |
| Migration proof on MySQL 8.4.13 | Only the owner-reported **local disposable** result (§4); not VPS, not restore (R-V3 OPEN). |
| Partial-DDL recovery plan | OPEN (R-V6). |
| Valid Stage backup + isolated restore proof | NOT_DONE. |
| Server release / PHP / Composer check, trusted proxies (R-V4), SMTP, Tourvisor, TLS/DNS, legal, content | unchanged; see roadmap §A10. |

## 10. Contradictions and unknowns recorded

- Git alignment is now verified at the pre-commit baseline `801746f1…` (§2, OWNER_CONSOLE). The new documentation commit and the subsequent Project Sources generation remain PENDING; no hash exists for either.
- Project Sources date visible to the owner (2026-09-23) vs the roadmap record (2026-11-15 set promoted).
- Roadmap R-V3 ("not proven on 8.4.13") vs the owner-reported local Docker 8.4.13 migration result: reconciled as local-only, R-V3 stays OPEN.
- The B5.2 technical plan and transition addendum referred to by the owner are external files and not in the repository (not verified).
