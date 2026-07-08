<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Selective import command from turfirma_dev to turfirma_rebuild_v4.
 *
 * DEFAULT INVOCATION (strictly read-only dry-run):
 *   php artisan legacy:import-v4
 *
 *   No database writes are performed. No files are modified.
 *   The command audits source data, inspects transformations, and validates
 *   the target schema — but does not write anything.
 *
 * WRITE MODE (one-time, guarded execute):
 *   php artisan legacy:import-v4 --execute --confirm=turfirma_rebuild_v4
 *
 *   Requires BOTH --execute AND --confirm=turfirma_rebuild_v4 simultaneously.
 *   Writes ONLY to turfirma_rebuild_v4 via a single named-locked transaction
 *   with four source-consistency checkpoints and full post-commit verification.
 *
 * No Eloquent models are used to prevent slug generation, casts, mutators,
 * observers, and model events from modifying source values.
 * No migrations, seeders, SET FOREIGN_KEY_CHECKS, TRUNCATE, or DELETE.
 */
class ImportLegacyDataToV4 extends Command
{
    protected $signature = 'legacy:import-v4
        {--execute : Perform the one-time import into turfirma_rebuild_v4}
        {--confirm= : Exact target confirmation token required with --execute}';

    protected $description = 'Audit (dry-run, default) or execute one-time selective import from turfirma_dev to turfirma_rebuild_v4. Default invocation is strictly read-only. Write mode requires both --execute and --confirm=turfirma_rebuild_v4.';

    // ─── Constants ────────────────────────────────────────────────────────────

    private const LEGACY_DB   = 'turfirma_dev';
    private const TARGET_DB   = 'turfirma_rebuild_v4';
    private const LEGACY_CONN = 'legacy_source';
    private const TARGET_CONN = 'rebuild_target';

    private const EXPECTED_SOURCE_TABLES = [
        'profiles',
        'roles',
        'users',
        'role_user',
        'reviews',
        'best_offers',
        'partners',
        'countries_images',
        'destination_images',
        'employees',
        'awards',
        'articles',
        'news',
        'our_clients',
    ];

    private const EXPECTED_TARGET_TABLES = [
        'migrations',
        'roles',
        'users',
        'role_user',
        'reviews',
        'best_offers',
        'partners',
        'countries_images',
        'destination_images',
        'employees',
        'awards',
        'articles',
        'news',
        'our_clients',
    ];

    private const IMPORT_TARGET_TABLES = [
        'roles',
        'users',
        'role_user',
        'reviews',
        'best_offers',
        'partners',
        'countries_images',
        'destination_images',
        'employees',
        'awards',
        'articles',
        'news',
        'our_clients',
    ];

    private const CANONICAL_ROLE_DESCRIPTIONS = [
        'admin'   => 'Администратор - полный доступ к системе',
        'manager' => 'Менеджер - управление заявками и клиентами',
        'tourist' => 'Турист - просмотр и создание заявок',
    ];

    private const EXCLUDED_SCOPE = [
        'tours',
        'bookings',
        'messages',
        'user_documents',
        'booking_documents',
        'bonus_accounts',
        'bonus_transactions',
        'notifications',
        'jobs',
        'failed_jobs',
        'personal_access_tokens',
    ];

    /** Known broken Abkhazia PNG path stored in the source database. */
    private const ABKHAZIA_LEGACY_PNG = '/img/countries_image/large/abkhazia.png';

    /** Planned replacement Abkhazia JPEG path (public-relative). */
    private const ABKHAZIA_REPLACEMENT_JPG = '/img/countries_image/large/abkhazia.jpg';

    /** documents_url value that is mapped to null in the write stage. */
    private const DOCUMENTS_URL_KNOWN_NULL = '/documents/countries/';

    /** MySQL named lock for execute mode. */
    private const NAMED_LOCK = 'avilona_turfirma:legacy-import-v4:execute:turfirma_rebuild_v4';

    /** Expected source row counts before any write. */
    private const EXPECTED_SOURCE_COUNTS = [
        'roles'              => 2,
        'users'              => 7,
        'role_user'          => 7,
        'reviews'            => 46,
        'best_offers'        => 4,
        'partners'           => 20,
        'countries_images'   => 55,
        'destination_images' => 12,
        'employees'          => 10,
        'awards'             => 22,
        'articles'           => 50,
        'news'               => 138,
        'our_clients'        => 1,
    ];

    /** Expected total rows in target after successful import. */
    private const EXPECTED_TARGET_TOTAL = 375;

    /** Chunk size for batch inserts. */
    private const INSERT_CHUNK_SIZE = 100;

    // ─── State ────────────────────────────────────────────────────────────────

    private bool $hadFailure                      = false;
    private bool $executeModeActive               = false;
    private bool $sharedPipelineCompleted         = false;
    private bool $snapshotValidated               = false;
    private bool $namedLockAcquired               = false;
    private bool $transactionStarted              = false;
    private bool $transactionCommitted            = false;
    private bool $rollbackVerificationSucceeded   = false;
    private bool $postCommitVerificationSucceeded = false;
    private bool $lockReleaseConfirmed            = false;
    private bool $cleanupSucceeded                = false;
    private bool $checkpointDPassed               = false;

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function handle(): int
    {
        $modeResult = $this->validateCliOptionsForMode();
        if ($modeResult !== null) {
            return $modeResult;
        }

        if ($this->executeModeActive) {
            return $this->runExecuteMode();
        }

        return $this->runDryRunMode();
    }

    // ─── CLI validation ───────────────────────────────────────────────────────

    /**
     * Validate --execute / --confirm option combination before any other work.
     *
     * Returns null to continue (mode set), or an integer exit code to abort.
     *
     * Rules:
     *   Neither option       → dry-run (return null, $executeModeActive stays false)
     *   --confirm only       → FAILURE immediately
     *   --execute only       → FAILURE immediately
     *   --execute + wrong    → FAILURE immediately
     *   --execute + correct  → execute mode (return null, $executeModeActive = true)
     */
    private function validateCliOptionsForMode(): ?int
    {
        $hasExecute = (bool) $this->option('execute');
        $confirm    = $this->option('confirm');
        $hasConfirm = $confirm !== null;

        if (!$hasExecute && !$hasConfirm) {
            return null;
        }

        if ($hasConfirm && !$hasExecute) {
            $this->error('[cli-validation] --confirm supplied without --execute. Aborting.');
            return Command::FAILURE;
        }

        if ($hasExecute && !$hasConfirm) {
            $this->error(
                '[cli-validation] --execute requires --confirm=' . self::TARGET_DB . '. Aborting.'
            );
            return Command::FAILURE;
        }

        if ($confirm !== self::TARGET_DB) {
            $this->error(
                '[cli-validation] Confirmation token does not match target database name. Aborting.'
            );
            return Command::FAILURE;
        }

        $this->executeModeActive = true;
        return null;
    }

    // ─── Dry-run mode ─────────────────────────────────────────────────────────

    private function runDryRunMode(): int
    {
        $this->renderHeader();

        $originalConnections = config('database.connections');
        $originalDefault     = config('database.default');
        $cleanupOk           = false;

        try {
            if ($this->runSharedReadOnlyPipeline()) {
                $this->sharedPipelineCompleted = true;
            }
        } catch (\Throwable $e) {
            $this->error('Unexpected exception: ' . $e->getMessage());
        } finally {
            $cleanupOk = $this->cleanupRuntimeConnections($originalConnections, $originalDefault);
        }

        $this->line('-- [18] Final verdict ------------------------------------');

        if (!$this->sharedPipelineCompleted || $this->hadFailure || !$cleanupOk) {
            $this->error('  DRY-RUN VERDICT: FAILED');
            if (!$cleanupOk) {
                $this->error('  Cleanup or connection restoration failed.');
            }
            if (!$this->sharedPipelineCompleted) {
                $this->error('  Inspection chain did not complete (a hard guard failed).');
            }
            if ($this->hadFailure) {
                $this->error('  One or more inspection checks did not pass.');
            }
            $this->error('  Resolve all FAIL messages above before proceeding.');
            $this->newLine();
            return Command::FAILURE;
        }

        return $this->renderFinalVerdict();
    }

    // ─── Execute mode ─────────────────────────────────────────────────────────

    private function runExecuteMode(): int
    {
        $this->renderExecuteHeader();

        $originalConnections = config('database.connections');
        $originalDefault     = config('database.default');
        $executionTimestamp  = date('Y-m-d H:i:s');
        $currentStage        = 'init';

        try {
            do {
                // ── 1. Shared read-only pipeline ──────────────────────────────
                $currentStage = 'execute';
                $pipelineOk   = false;
                try {
                    $pipelineOk = $this->runSharedReadOnlyPipeline();
                } catch (\Throwable $e) {
                    $this->error("[{$currentStage}] Pipeline exception: " . get_class($e));
                    break;
                }

                if (!$pipelineOk || $this->hadFailure) {
                    $this->error("[{$currentStage}] Shared pipeline failed. Aborting execute mode.");
                    break;
                }
                $this->sharedPipelineCompleted = true;

                // ── 2. Execute schema preflight ───────────────────────────────
                $currentStage = 'schema-preflight';
                $preflightOk  = false;
                try {
                    $preflightOk = $this->runExecuteSchemaPreflight();
                } catch (\Throwable $e) {
                    $this->error("[{$currentStage}] Exception: " . get_class($e));
                    break;
                }

                if (!$preflightOk) {
                    $this->error("[{$currentStage}] Failed. Aborting execute mode.");
                    break;
                }

                // ── 3. Checkpoint A ───────────────────────────────────────────
                $currentStage = 'source-checkpoint-a';
                $checkpointA  = null;
                try {
                    $checkpointA = $this->buildAndValidateCheckpointA($executionTimestamp);
                } catch (\Throwable $e) {
                    $this->error("[{$currentStage}] Exception: " . get_class($e));
                    break;
                }

                if ($checkpointA === null) {
                    $this->error("[{$currentStage}] Failed. Aborting execute mode.");
                    break;
                }
                $this->snapshotValidated = true;

                // ── 4. Acquire named lock ─────────────────────────────────────
                $currentStage = 'lock-acquire';
                $lockAcquired = false;
                try {
                    $lockRow = array_change_key_case(
                        (array) DB::connection(self::TARGET_CONN)
                            ->selectOne('SELECT GET_LOCK(?, 0) AS result', [self::NAMED_LOCK]),
                        CASE_LOWER
                    );
                    $lockAcquired = (int) ($lockRow['result'] ?? null) === 1;
                } catch (\Throwable $e) {
                    $this->error("[{$currentStage}] Exception: " . get_class($e));
                    break;
                }

                if (!$lockAcquired) {
                    $this->error("[{$currentStage}] Could not acquire named lock. Aborting.");
                    break;
                }
                $this->namedLockAcquired = true;
                $this->line('[lock-acquire] Named lock acquired.');

                // ── 5+ Locked section — lock released in finally before break ──
                try {
                    // ── Checkpoint B ──────────────────────────────────────────
                    $currentStage  = 'source-checkpoint-b';
                    $checkpointBOk = false;
                    try {
                        $snapshotB     = $this->buildSourceSnapshot($executionTimestamp);
                        $checkpointBOk = $this->checkpointsEqual($checkpointA, $snapshotB);
                    } catch (\Throwable $e) {
                        $this->error("[{$currentStage}] Exception: " . get_class($e));
                    }

                    if (!$checkpointBOk) {
                        $this->error("[{$currentStage}] Mismatch with checkpoint A. Aborting.");
                        break;
                    }
                    $this->line('[source-checkpoint-b] Matches checkpoint A.');

                    // ── Target emptiness recheck ───────────────────────────────
                    $currentStage = 'target-emptiness-recheck';
                    $emptyOk      = false;
                    try {
                        $emptyOk = $this->recheckAllTablesEmpty();
                    } catch (\Throwable $e) {
                        $this->error("[{$currentStage}] Exception: " . get_class($e));
                    }

                    if (!$emptyOk) {
                        $this->error("[{$currentStage}] Target tables not empty. Aborting.");
                        break;
                    }
                    $this->line('[target-emptiness-recheck] All import and excluded tables confirmed empty.');

                    // ── Begin transaction ──────────────────────────────────────
                    $currentStage = 'transaction-begin';
                    try {
                        DB::connection(self::TARGET_CONN)->beginTransaction();
                        $this->transactionStarted = true;
                        $this->line('[transaction-begin] Transaction started.');
                    } catch (\Throwable $e) {
                        $this->error("[{$currentStage}] Exception: " . get_class($e));
                        break;
                    }

                    // ── Transaction block ──────────────────────────────────────
                    try {
                        $currentStage = 'insert';
                        $this->executeInserts($checkpointA['payload'], $currentStage);

                        $currentStage = 'verify-target';
                        $this->verifyInsertedTarget($checkpointA);

                        $currentStage  = 'source-checkpoint-c';
                        $checkpointCOk = false;
                        try {
                            $snapshotC     = $this->buildSourceSnapshot($executionTimestamp);
                            $checkpointCOk = $this->checkpointsEqual($checkpointA, $snapshotC);
                        } catch (\Throwable $e) {
                            $this->error("[{$currentStage}] Exception: " . get_class($e));
                        }

                        if (!$checkpointCOk) {
                            $this->error("[{$currentStage}] Source snapshot changed during transaction.");
                            throw new \LogicException('source-checkpoint-c-mismatch');
                        }
                        $this->line('[source-checkpoint-c] Matches checkpoint A.');

                        $currentStage = 'commit';
                        DB::connection(self::TARGET_CONN)->commit();
                        $this->transactionCommitted = true;
                        $this->line('[commit] Transaction committed successfully.');

                        $currentStage = 'auto-increment-repair';
                        $this->synchronizeTargetAutoIncrementCounters();

                    } catch (\Throwable $te) {
                        $this->error("[{$currentStage}] Failed: " . get_class($te));

                        if ($this->transactionStarted && !$this->transactionCommitted) {
                            try {
                                DB::connection(self::TARGET_CONN)->rollBack();
                                $this->line('[rollback-check] Transaction rolled back.');
                            } catch (\Throwable $re) {
                                $this->error('[rollback-check] Rollback exception: ' . get_class($re));
                            }
                            $this->rollbackVerificationSucceeded = $this->verifyAllTablesEmpty();
                        }
                        break; // Exit do/while; outer finally releases lock and cleans up
                    }

                    // ── Post-commit verification ───────────────────────────────
                    $currentStage = 'post-commit-verify';
                    try {
                        $this->postCommitVerificationSucceeded = $this->runPostCommitVerification($checkpointA);
                    } catch (\Throwable $e) {
                        $this->error("[{$currentStage}] Exception: " . get_class($e));
                        $this->postCommitVerificationSucceeded = false;
                    }

                    if (!$this->postCommitVerificationSucceeded) {
                        $this->error(
                            "[{$currentStage}] Failed. Data IS committed to " . self::TARGET_DB .
                            '. Manual review required.'
                        );
                    }

                    // ── Checkpoint D ──────────────────────────────────────────
                    $currentStage = 'source-checkpoint-d';
                    try {
                        $snapshotD = $this->buildSourceSnapshot($executionTimestamp);
                        if ($this->checkpointsEqual($checkpointA, $snapshotD)) {
                            $this->checkpointDPassed = true;
                            $this->line('[source-checkpoint-d] Matches checkpoint A.');
                        } else {
                            $this->checkpointDPassed = false;
                            $this->error(
                                "[{$currentStage}] Source changed after commit. " .
                                'Data IS committed to ' . self::TARGET_DB .
                                '. Manual review required.'
                            );
                        }
                    } catch (\Throwable $e) {
                        $this->checkpointDPassed = false;
                        $this->error(
                            "[{$currentStage}] Exception: " . get_class($e) .
                            '. Data IS committed to ' . self::TARGET_DB .
                            '. Manual review required.'
                        );
                    }

                } finally {
                    // Release named lock before connections are purged
                    $this->releaseLock();
                }

            } while (false);

        } finally {
            $this->cleanupSucceeded = $this->cleanupRuntimeConnections(
                $originalConnections,
                $originalDefault
            );
        }

        return $this->renderExecuteFinalVerdict();
    }

    // ─── Shared pipeline ──────────────────────────────────────────────────────

    /**
     * Run the shared guard chain and inspection sequence.
     * Returns true only when all guards pass and all inspections complete.
     * Called by both dry-run mode and execute mode.
     */
    private function runSharedReadOnlyPipeline(): bool
    {
        $completed = false;

        do {
            if (!$this->guardDefaultConnection()) {
                break;
            }
            if (!$this->guardDatabasesExist()) {
                break;
            }
            $this->registerRuntimeConnections();
            if (!$this->guardRuntimeSelectedDatabases()) {
                break;
            }
            if (!$this->guardSourceTablesExist()) {
                break;
            }
            if (!$this->guardProfilesEmpty()) {
                break;
            }
            if (!$this->guardTargetTablesExist()) {
                break;
            }
            if (!$this->guardTargetTablesEmpty()) {
                break;
            }
            if (!$this->guardMigrationHistory()) {
                break;
            }

            $this->inspectRoles();
            $this->inspectUsers();
            $this->inspectRoleUser();
            $this->inspectReviews();
            $this->inspectBestOffers();
            $this->inspectPartners();
            $this->inspectCountriesImages();
            $this->inspectDestinationImages();
            $this->inspectEmployees();
            $this->inspectAwards();
            $this->inspectArticles();
            $this->inspectNews();
            $this->inspectOurClients();
            $this->inspectLocalMedia();
            $this->reportExcludedScope();

            $completed = true;
        } while (false);

        return $completed;
    }

    // ─── Headers ──────────────────────────────────────────────────────────────

    private function renderHeader(): void
    {
        $this->line('==========================================================');
        $this->warn('  DRY-RUN ONLY — NO DATABASE WRITES WILL BE PERFORMED    ');
        $this->line('  Command : legacy:import-v4');
        $this->line('  Source  : ' . self::LEGACY_DB);
        $this->line('  Target  : ' . self::TARGET_DB);
        $this->line('==========================================================');
        $this->newLine();
    }

    private function renderExecuteHeader(): void
    {
        $this->line('==========================================================');
        $this->warn('  EXECUTE MODE — WRITES WILL BE PERFORMED                ');
        $this->warn('  Target  : ' . self::TARGET_DB);
        $this->line('  Command : legacy:import-v4 --execute --confirm=' . self::TARGET_DB);
        $this->line('  Source  : ' . self::LEGACY_DB);
        $this->line('  Lock    : ' . self::NAMED_LOCK);
        $this->line('==========================================================');
        $this->newLine();
    }

    // ─── Guards ───────────────────────────────────────────────────────────────

    /**
     * [2] Confirm that the default connection's driver is mysql and that the
     * database selected through that connection is exactly turfirma_dev.
     */
    private function guardDefaultConnection(): bool
    {
        $this->line('-- [2] Default database guard ----------------------------');

        $defaultConnectionName = config('database.default');
        $defaultDriver         = config("database.connections.{$defaultConnectionName}.driver");

        if ($defaultDriver !== 'mysql') {
            return $this->failGuard(
                "Driver for connection '{$defaultConnectionName}' is " .
                "'" . ($defaultDriver ?? 'null') . "', expected 'mysql'."
            );
        }

        $row      = DB::connection($defaultConnectionName)->selectOne('SELECT DATABASE() AS db');
        $row      = array_change_key_case((array) $row, CASE_LOWER);
        $activeDb = $row['db'] ?? null;

        if ($activeDb !== self::LEGACY_DB) {
            return $this->failGuard(
                "Active database on connection '{$defaultConnectionName}' is '{$activeDb}', " .
                "expected '" . self::LEGACY_DB . "'. Check DB_DATABASE in .env."
            );
        }

        $this->line('  OK connection : ' . $defaultConnectionName);
        $this->line('  OK driver     : mysql');
        $this->line('  OK database   : ' . $activeDb);
        $this->newLine();
        return true;
    }

    /**
     * [3] Confirm both databases exist on the MySQL server using
     * read-only information_schema queries on the default connection.
     */
    private function guardDatabasesExist(): bool
    {
        $this->line('-- [3] Database existence --------------------------------');

        $ok = true;

        foreach ([self::LEGACY_DB, self::TARGET_DB] as $dbName) {
            $result = DB::selectOne(
                'SELECT SCHEMA_NAME AS schema_name
                 FROM information_schema.SCHEMATA
                 WHERE SCHEMA_NAME = ?',
                [$dbName]
            );

            if ($result === null) {
                $this->error("  FAIL: Database '{$dbName}' not found on this server.");
                $ok = false;
            } else {
                $row = array_change_key_case((array) $result, CASE_LOWER);
                $this->line('  OK database exists : ' . $row['schema_name']);
            }
        }

        $this->newLine();
        return $ok;
    }

    /**
     * Register two runtime connections by cloning config('database.connections.mysql').
     * Only the database name is changed. The url key is explicitly set to null.
     * The default connection is never changed.
     */
    private function registerRuntimeConnections(): void
    {
        $base = config('database.connections.mysql');

        $legacyConfig             = $base;
        $legacyConfig['database'] = self::LEGACY_DB;
        $legacyConfig['url']      = null;

        $targetConfig             = $base;
        $targetConfig['database'] = self::TARGET_DB;
        $targetConfig['url']      = null;

        Config::set('database.connections.' . self::LEGACY_CONN, $legacyConfig);
        Config::set('database.connections.' . self::TARGET_CONN, $targetConfig);

        DB::purge(self::LEGACY_CONN);
        DB::purge(self::TARGET_CONN);
        DB::reconnect(self::LEGACY_CONN);
        DB::reconnect(self::TARGET_CONN);
    }

    /**
     * [4] Execute SELECT DATABASE() on both runtime connections and require
     * exact database-name matches.
     */
    private function guardRuntimeSelectedDatabases(): bool
    {
        $this->line('-- [4] Runtime connection verification -------------------');

        $ok = true;

        $legacyRow = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)->selectOne('SELECT DATABASE() AS db'),
            CASE_LOWER
        );
        $legacyDb = $legacyRow['db'] ?? null;

        $targetRow = array_change_key_case(
            (array) DB::connection(self::TARGET_CONN)->selectOne('SELECT DATABASE() AS db'),
            CASE_LOWER
        );
        $targetDb = $targetRow['db'] ?? null;

        if ($legacyDb !== self::LEGACY_DB) {
            $this->error(
                "  FAIL: legacy_source selected '{$legacyDb}', " .
                "expected '" . self::LEGACY_DB . "'."
            );
            $ok = false;
        } else {
            $this->line('  OK legacy_source  -> ' . $legacyDb);
        }

        if ($targetDb !== self::TARGET_DB) {
            $this->error(
                "  FAIL: rebuild_target selected '{$targetDb}', " .
                "expected '" . self::TARGET_DB . "'."
            );
            $ok = false;
        } else {
            $this->line('  OK rebuild_target -> ' . $targetDb);
        }

        $this->newLine();
        return $ok;
    }

    /**
     * [5] Confirm all expected source tables exist in turfirma_dev.
     */
    private function guardSourceTablesExist(): bool
    {
        $this->line('-- [5] Source table verification -------------------------');

        return $this->checkTablesExist(
            self::LEGACY_CONN,
            self::LEGACY_DB,
            self::EXPECTED_SOURCE_TABLES,
            'source'
        );
    }

    /**
     * [6] Confirm the legacy profiles table contains exactly zero rows.
     * Non-zero row count is a hard failure because profiles are out of import scope.
     */
    private function guardProfilesEmpty(): bool
    {
        $this->line('-- [6] Source profiles count -----------------------------');

        $count = DB::connection(self::LEGACY_CONN)
            ->table('profiles')
            ->count();

        if ($count !== 0) {
            $this->error(
                "  FAIL: profiles has {$count} row(s). " .
                'Expected 0. Profiles are excluded from import scope.'
            );
            $this->newLine();
            return false;
        }

        $this->line('  OK profiles rows  : 0 (empty — excluded from import scope)');
        $this->newLine();
        return true;
    }

    /**
     * [7] Confirm all expected target tables exist in turfirma_rebuild_v4.
     */
    private function guardTargetTablesExist(): bool
    {
        $this->line('-- [7] Target table verification -------------------------');

        return $this->checkTablesExist(
            self::TARGET_CONN,
            self::TARGET_DB,
            self::EXPECTED_TARGET_TABLES,
            'target'
        );
    }

    /**
     * [8] Confirm every import target table in turfirma_rebuild_v4 is empty.
     * Any non-empty table is a hard failure.
     */
    private function guardTargetTablesEmpty(): bool
    {
        $this->line('-- [8] Target import table emptiness ---------------------');

        $ok = true;

        foreach (self::IMPORT_TARGET_TABLES as $table) {
            $count = DB::connection(self::TARGET_CONN)
                ->table($table)
                ->count();

            if ($count > 0) {
                $this->error("  FAIL: {$table} has {$count} row(s). Target must be empty.");
                $ok = false;
            } else {
                $this->line("  OK empty : {$table}");
            }
        }

        $this->newLine();
        return $ok;
    }

    /**
     * [9] Compare local migration filenames (without .php extension) against
     * the migration names recorded in rebuild_target.migrations.
     *
     * Hard fails on:
     * - missing local migrations in target;
     * - unexpected target migrations;
     * - local/target unique-name count mismatch;
     * - duplicate migration names in rebuild_target.migrations.
     *
     * Reports separately:
     * - local filename count;
     * - target row count;
     * - target unique-name count;
     * - duplicate target migration names;
     * - missing names;
     * - unexpected names.
     *
     * No Artisan migration commands are executed.
     */
    private function guardMigrationHistory(): bool
    {
        $this->line('-- [9] Migration history equality ------------------------');

        $migrationPath = database_path('migrations');
        $localFiles    = glob($migrationPath . DIRECTORY_SEPARATOR . '*.php');

        if ($localFiles === false || count($localFiles) === 0) {
            return $this->failGuard("No migration files found in {$migrationPath}.");
        }

        $localNames = array_map(
            static fn(string $file): string => pathinfo($file, PATHINFO_FILENAME),
            $localFiles
        );
        sort($localNames);
        $localCount = count($localNames);

        // Fetch all target migration rows (may include duplicates)
        $targetRows = DB::connection(self::TARGET_CONN)
            ->table('migrations')
            ->pluck('migration')
            ->toArray();

        $targetCount = count($targetRows);

        // Detect duplicate names
        $targetValueCounts = array_count_values($targetRows);
        $duplicateNames    = array_keys(
            array_filter($targetValueCounts, static fn(int $c): bool => $c > 1)
        );
        sort($duplicateNames);

        // Unique set
        $targetUniqueNames = array_values(array_unique($targetRows));
        sort($targetUniqueNames);
        $targetUniqueCount = count($targetUniqueNames);

        // Diff both ways
        $missing    = array_values(array_diff($localNames, $targetUniqueNames));
        $unexpected = array_values(array_diff($targetUniqueNames, $localNames));

        $this->line("  Local migration files          : {$localCount}");
        $this->line("  Target migration rows          : {$targetCount}");
        $this->line("  Target unique migration names  : {$targetUniqueCount}");

        $ok = true;

        if (!empty($duplicateNames)) {
            $this->error('  FAIL: Duplicate migration names in target:');
            foreach ($duplicateNames as $name) {
                $times = $targetValueCounts[$name];
                $this->error("    - {$name} (×{$times})");
            }
            $ok = false;
        }

        if ($localCount !== $targetUniqueCount) {
            $this->error(
                "  FAIL: Count mismatch — local={$localCount}, " .
                "target unique={$targetUniqueCount}."
            );
            $ok = false;
        }

        if (!empty($missing)) {
            $this->error('  FAIL: Local migrations missing from target:');
            foreach ($missing as $name) {
                $this->error("    - {$name}");
            }
            $ok = false;
        }

        if (!empty($unexpected)) {
            $this->error('  FAIL: Target has unexpected migration records:');
            foreach ($unexpected as $name) {
                $this->error("    - {$name}");
            }
            $ok = false;
        }

        if ($ok) {
            $this->line("  OK migration sets are exactly equal ({$localCount} entries).");
        }

        $this->newLine();
        return $ok;
    }

    // ─── Inspections ──────────────────────────────────────────────────────────

    /**
     * [10] Roles — validate legacy role identity and report planned canonical mapping.
     */
    private function inspectRoles(): void
    {
        $this->line('-- [10] Roles -------------------------------------------');

        $roles = DB::connection(self::LEGACY_CONN)
            ->table('roles')
            ->select('id', 'name')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $count = count($roles);
        $this->line("  Source rows : {$count}");

        if ($count !== 2) {
            $this->error("  FAIL: Expected exactly 2 source roles, found {$count}.");
            $this->hadFailure = true;
            $this->newLine();
            return;
        }

        $rolesById = [];
        foreach ($roles as $role) {
            $rolesById[(int) $role['id']] = $role['name'];
        }

        foreach ([1 => 'tourist', 2 => 'admin'] as $id => $expectedName) {
            if (!isset($rolesById[$id])) {
                $this->error("  FAIL: No role with ID {$id} in source.");
                $this->hadFailure = true;
            } elseif ($rolesById[$id] !== $expectedName) {
                $actual = $rolesById[$id];
                $this->error("  FAIL: Role ID {$id} is '{$actual}', expected '{$expectedName}'.");
                $this->hadFailure = true;
            } else {
                $this->line("  OK ID {$id} = '{$expectedName}'");
            }
        }

        $this->newLine();
        $this->line('  Planned canonical roles for target (write-stage only):');
        $this->table(
            ['ID', 'Name', 'Description', 'Write-stage action'],
            [
                [1, 'tourist', self::CANONICAL_ROLE_DESCRIPTIONS['tourist'], 'PRESERVE from source'],
                [2, 'admin',   self::CANONICAL_ROLE_DESCRIPTIONS['admin'],   'PRESERVE from source'],
                [3, 'manager', self::CANONICAL_ROLE_DESCRIPTIONS['manager'], 'INSERT new — not in source'],
            ]
        );
        $this->newLine();
    }

    /**
     * [11] Users — validate required field completeness and report planned
     * field mapping. No email addresses, password hashes, or sensitive values
     * are displayed.
     */
    private function inspectUsers(): void
    {
        $this->line('-- [11] Users -------------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('users')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        foreach (['name', 'email', 'password'] as $field) {
            $nullCount = DB::connection(self::LEGACY_CONN)
                ->table('users')
                ->whereNull($field)
                ->count();
            $icon = $nullCount === 0 ? 'OK' : 'FAIL';
            $this->line("  {$icon} {$field} null count : {$nullCount}");
            if ($nullCount > 0) {
                $this->error("  FAIL: {$nullCount} user(s) have null {$field}.");
                $this->hadFailure = true;
            }
        }

        // Count duplicate email groups without printing emails
        $dupGroups = DB::connection(self::LEGACY_CONN)
            ->table('users')
            ->selectRaw('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($dupGroups > 0) {
            $this->error("  FAIL: {$dupGroups} duplicate email group(s). Import requires unique emails.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK duplicate email groups : 0');
        }

        $this->newLine();
        $this->line('  Planned field mapping (write-stage only, no sensitive values displayed):');
        $this->table(
            ['Field', 'Write-stage action'],
            [
                ['id',                       'PRESERVE'],
                ['name',                     'PRESERVE'],
                ['email',                    'PRESERVE'],
                ['email_verified_at',        'PRESERVE'],
                ['password',                 'PRESERVE hash — value not displayed'],
                ['created_at',               'PRESERVE'],
                ['updated_at',               'PRESERVE'],
                ['remember_token',           'SET NULL'],
                ['temp_password',            'SET NULL'],
                ['is_active',                'CANONICAL DEFAULT (true)'],
                ['password_change_required', 'CANONICAL DEFAULT (false)'],
                ['phone',                    'SET NULL — no source mapping'],
                ['birth_date',               'SET NULL — no source mapping'],
                ['gender',                   'SET NULL — no source mapping'],
                ['address',                  'SET NULL — no source mapping'],
                ['passport_number',          'SET NULL — no source mapping'],
                ['passport_issued_date',     'SET NULL — no source mapping'],
                ['passport_issued_by',       'SET NULL — no source mapping'],
                ['notification_settings',    'SET NULL — no source mapping'],
                ['last_login_at',            'SET NULL — no source mapping'],
                ['avatar_path',              'SET NULL — no source mapping'],
            ]
        );
        $this->newLine();
    }

    /**
     * [12] Role_user — validate referential integrity and report role distribution.
     */
    private function inspectRoleUser(): void
    {
        $this->line('-- [12] Role_user integrity ------------------------------');

        $count = DB::connection(self::LEGACY_CONN)
            ->table('role_user')
            ->count();

        $this->line("  Source rows : {$count}");

        $orphanUserCount = DB::connection(self::LEGACY_CONN)
            ->table('role_user AS ru')
            ->leftJoin('users AS u', 'ru.user_id', '=', 'u.id')
            ->whereNull('u.id')
            ->count();

        $orphanRoleCount = DB::connection(self::LEGACY_CONN)
            ->table('role_user AS ru')
            ->leftJoin('roles AS r', 'ru.role_id', '=', 'r.id')
            ->whereNull('r.id')
            ->count();

        if ($orphanUserCount > 0) {
            $this->error(
                "  FAIL: {$orphanUserCount} role_user row(s) reference non-existent user_id."
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK orphan user_id count : 0');
        }

        if ($orphanRoleCount > 0) {
            $this->error(
                "  FAIL: {$orphanRoleCount} role_user row(s) reference non-existent role_id."
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK orphan role_id count : 0');
        }

        $distribution = DB::connection(self::LEGACY_CONN)
            ->table('role_user AS ru')
            ->join('roles AS r', 'ru.role_id', '=', 'r.id')
            ->selectRaw('r.name AS role_name, COUNT(*) AS user_count')
            ->groupBy('r.name')
            ->orderBy('r.name')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $this->newLine();
        $this->line('  Role distribution:');
        $this->table(
            ['Role', 'User count'],
            array_map(static fn($r) => [$r['role_name'], $r['user_count']], $distribution)
        );

        $this->line('  Planned: timestamps and deleted_at will be preserved in write-stage.');
        $this->newLine();
    }

    /**
     * [13a] Reviews — report planned blank-title and image-"0" transformations.
     * Title is considered blank when null or PHP trim((string) $value) === ''.
     */
    private function inspectReviews(): void
    {
        $this->line('-- [13a] Reviews -----------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('reviews')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        $titleValues = DB::connection(self::LEGACY_CONN)
            ->table('reviews')
            ->pluck('title')
            ->toArray();

        $blankTitleCount = 0;
        foreach ($titleValues as $titleValue) {
            if ($titleValue === null || trim((string) $titleValue) === '') {
                $blankTitleCount++;
            }
        }

        $this->line("  Blank/null title count : {$blankTitleCount} -> planned SET NULL");

        $imageZeroRows = DB::connection(self::LEGACY_CONN)
            ->table('reviews')
            ->where('image', '0')
            ->pluck('id')
            ->toArray();

        $imageZeroCount = count($imageZeroRows);
        $this->line("  image = '0' count      : {$imageZeroCount} -> planned SET NULL");

        if ($imageZeroCount > 0) {
            $this->line('  Affected IDs           : ' . implode(', ', $imageZeroRows));
        }

        $this->line('  Other image paths      : planned PRESERVE');
        $this->line('  deleted_at             : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13b] Best_offers — report image trim normalization count.
     */
    private function inspectBestOffers(): void
    {
        $this->line('-- [13b] Best_offers -------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('best_offers')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        $normCount = $this->countTrimNormalization(self::LEGACY_CONN, 'best_offers', 'image');
        $this->line(
            "  image trim-normalize count : {$normCount} -> planned TRIM(CR/LF/tab/space)"
        );
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13c] Partners — report logo_partner trim normalization count.
     */
    private function inspectPartners(): void
    {
        $this->line('-- [13c] Partners ----------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('partners')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        $normCount = $this->countTrimNormalization(
            self::LEGACY_CONN,
            'partners',
            'logo_partner'
        );
        $this->line(
            "  logo_partner trim-normalize count : {$normCount} -> planned TRIM(CR/LF/tab/space)"
        );
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13d] Countries_images — validate slugs; validate ID 1 source path and
     * replacement file; report image_large preserved/planned-null counts for
     * other rows; strictly validate documents_url values.
     *
     * Blank slug = null or PHP trim((string) $value) === ''.
     * Whitespace slug = trim((string) $value) !== (string) $value (and not blank).
     * Any blank, whitespace, or duplicate slug is a hard failure.
     * Any documents_url value other than the known null pattern is a hard failure.
     */
    private function inspectCountriesImages(): void
    {
        $this->line('-- [13d] Countries_images --------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('countries_images')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        [$blankSlug, $whitespaceSlug, $dupSlug] = $this->checkSlugIntegrity(
            self::LEGACY_CONN,
            'countries_images'
        );

        if ($blankSlug > 0) {
            $this->error("  FAIL: {$blankSlug} blank/null slug(s) in countries_images.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK blank/null slugs         : 0');
        }

        if ($whitespaceSlug > 0) {
            $this->error(
                "  FAIL: {$whitespaceSlug} slug(s) with leading/trailing whitespace " .
                'in countries_images — slugs must be preserved exactly.'
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK whitespace slugs         : 0');
        }

        if ($dupSlug > 0) {
            $this->error("  FAIL: {$dupSlug} duplicate slug group(s) in countries_images.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK duplicate slug groups    : 0');
        }

        // ── ID 1 specific validation ──────────────────────────────────────────

        $id1Row = DB::connection(self::LEGACY_CONN)
            ->table('countries_images')
            ->where('id', 1)
            ->first();

        if ($id1Row === null) {
            $this->error('  FAIL: Source row with ID 1 does not exist in countries_images.');
            $this->hadFailure = true;
        } else {
            $id1Row      = array_change_key_case((array) $id1Row, CASE_LOWER);
            $actualLarge = $id1Row['image_large'] ?? null;

            if ($actualLarge !== self::ABKHAZIA_LEGACY_PNG) {
                $this->error(
                    "  FAIL: ID 1 image_large is '{$actualLarge}', " .
                    "expected '" . self::ABKHAZIA_LEGACY_PNG . "'."
                );
                $this->hadFailure = true;
            } else {
                $this->line(
                    "  OK ID 1 source image_large  : '" . self::ABKHAZIA_LEGACY_PNG .
                    "' (expected broken PNG path)"
                );
            }

            $replacementFullPath = public_path(ltrim(self::ABKHAZIA_REPLACEMENT_JPG, '/'));

            if (!$this->verifyExactPathCase($replacementFullPath, public_path())) {
                $this->error(
                    '  FAIL: ID 1 replacement file missing or case mismatch: ' .
                    'public' . self::ABKHAZIA_REPLACEMENT_JPG
                );
                $this->hadFailure = true;
            } else {
                $this->line(
                    '  OK ID 1 replacement exists  : ' . self::ABKHAZIA_REPLACEMENT_JPG
                );
                $this->line(
                    '  Planned ID 1 replacement    : ' . self::ABKHAZIA_REPLACEMENT_JPG
                );
            }
        }

        // ── Other image_large rows (excluding ID 1) ───────────────────────────

        $otherLargeRows = DB::connection(self::LEGACY_CONN)
            ->table('countries_images')
            ->select('id', 'image_large')
            ->where('id', '!=', 1)
            ->whereNotNull('image_large')
            ->where('image_large', '!=', '')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $largePreservedCount   = 0;
        $largePlannedNullCount = 0;

        foreach ($otherLargeRows as $row) {
            $path = trim((string) $row['image_large']);
            if ($path === '') {
                $largePlannedNullCount++;
                continue;
            }
            $fullPath = public_path(ltrim($path, '/'));
            if (is_file($fullPath)) {
                $largePreservedCount++;
            } else {
                $largePlannedNullCount++;
            }
        }

        $this->line(
            "  image_large other rows      : " .
            "preserved={$largePreservedCount}, planned-null={$largePlannedNullCount}"
        );

        // ── documents_url strict validation ──────────────────────────────────

        $docsRows = DB::connection(self::LEGACY_CONN)
            ->table('countries_images')
            ->select('id', 'documents_url')
            ->whereNotNull('documents_url')
            ->where('documents_url', '!=', '')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $docsPlannedNull = 0;
        $docsUnexpected  = 0;

        foreach ($docsRows as $row) {
            $val = trim((string) $row['documents_url']);
            if ($val === '' || $val === self::DOCUMENTS_URL_KNOWN_NULL) {
                $docsPlannedNull++;
            } else {
                $this->error(
                    "  FAIL: ID {$row['id']} has unexpected documents_url value " .
                    "'{$val}'. Manual review required."
                );
                $this->hadFailure = true;
                $docsUnexpected++;
            }
        }

        $this->line(
            "  documents_url planned-null  : {$docsPlannedNull}" .
            " (known value '" . self::DOCUMENTS_URL_KNOWN_NULL . "')"
        );

        if ($docsUnexpected === 0) {
            $this->line('  OK documents_url no unexpected values.');
        }

        $this->line(
            '  Slugs, image_small, title, category, description, timestamps, deleted_at' .
            ' : planned PRESERVE'
        );
        $this->newLine();
    }

    /**
     * [13e] Destination_images — validate slugs and report preservation plan.
     * Any blank, whitespace, or duplicate slug is a hard failure.
     */
    private function inspectDestinationImages(): void
    {
        $this->line('-- [13e] Destination_images ------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('destination_images')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        [$blankSlug, $whitespaceSlug, $dupSlug] = $this->checkSlugIntegrity(
            self::LEGACY_CONN,
            'destination_images'
        );

        if ($blankSlug > 0) {
            $this->error("  FAIL: {$blankSlug} blank/null slug(s) in destination_images.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK blank/null slugs      : 0');
        }

        if ($whitespaceSlug > 0) {
            $this->error(
                "  FAIL: {$whitespaceSlug} slug(s) with leading/trailing whitespace " .
                'in destination_images — slugs must be preserved exactly.'
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK whitespace slugs      : 0');
        }

        if ($dupSlug > 0) {
            $this->error("  FAIL: {$dupSlug} duplicate slug group(s) in destination_images.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK duplicate slug groups : 0');
        }

        $this->line('  Slugs                               : planned PRESERVE exactly');
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13f] Employees — report counts and preservation plan.
     */
    private function inspectEmployees(): void
    {
        $this->line('-- [13f] Employees ---------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('employees')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13g] Awards — report counts and preservation plan.
     */
    private function inspectAwards(): void
    {
        $this->line('-- [13g] Awards ------------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('awards')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13h] Articles — validate slugs and report preservation plan.
     * Any blank, whitespace, or duplicate slug is a hard failure.
     */
    private function inspectArticles(): void
    {
        $this->line('-- [13h] Articles ----------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('articles')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        [$blankSlug, $whitespaceSlug, $dupSlug] = $this->checkSlugIntegrity(
            self::LEGACY_CONN,
            'articles'
        );

        if ($blankSlug > 0) {
            $this->error("  FAIL: {$blankSlug} blank/null slug(s) in articles.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK blank/null slugs      : 0');
        }

        if ($whitespaceSlug > 0) {
            $this->error(
                "  FAIL: {$whitespaceSlug} slug(s) with leading/trailing whitespace " .
                'in articles — slugs must be preserved exactly.'
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK whitespace slugs      : 0');
        }

        if ($dupSlug > 0) {
            $this->error("  FAIL: {$dupSlug} duplicate slug group(s) in articles.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK duplicate slug groups : 0');
        }

        $this->line('  Slugs                               : planned PRESERVE exactly');
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [13i] News — validate slugs, report external URL count, and preservation plan.
     * Any blank, whitespace, or duplicate slug is a hard failure.
     */
    private function inspectNews(): void
    {
        $this->line('-- [13i] News --------------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('news')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        [$blankSlug, $whitespaceSlug, $dupSlug] = $this->checkSlugIntegrity(
            self::LEGACY_CONN,
            'news'
        );

        if ($blankSlug > 0) {
            $this->error("  FAIL: {$blankSlug} blank/null slug(s) in news.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK blank/null slugs      : 0');
        }

        if ($whitespaceSlug > 0) {
            $this->error(
                "  FAIL: {$whitespaceSlug} slug(s) with leading/trailing whitespace " .
                'in news — slugs must be preserved exactly.'
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK whitespace slugs      : 0');
        }

        if ($dupSlug > 0) {
            $this->error("  FAIL: {$dupSlug} duplicate slug group(s) in news.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK duplicate slug groups : 0');
        }

        // Count external HTTP/HTTPS image URLs — no network requests made
        $externalCount = DB::connection(self::LEGACY_CONN)
            ->table('news')
            ->where(static function ($q): void {
                $q->where('image', 'LIKE', 'http://%')
                  ->orWhere('image', 'LIKE', 'https://%');
            })
            ->count();

        $this->line(
            "  External HTTP/HTTPS image URLs : {$externalCount} -> planned PRESERVE unchanged"
        );
        $this->line('  (No network requests will be made during import)');
        $this->line(
            '  Slugs, pub_date, all compatible fields, timestamps, deleted_at : planned PRESERVE'
        );
        $this->newLine();
    }

    /**
     * [13j] Our_clients — validate slugs and report preservation plan.
     * Any blank, whitespace, or duplicate slug is a hard failure.
     */
    private function inspectOurClients(): void
    {
        $this->line('-- [13j] Our_clients -------------------------------------');

        $stats = array_change_key_case(
            (array) DB::connection(self::LEGACY_CONN)
                ->table('our_clients')
                ->selectRaw('COUNT(*) AS total, MIN(id) AS min_id, MAX(id) AS max_id')
                ->first(),
            CASE_LOWER
        );

        $this->line("  Source rows : {$stats['total']}");
        $this->line("  ID range    : {$stats['min_id']} - {$stats['max_id']}");

        [$blankSlug, $whitespaceSlug, $dupSlug] = $this->checkSlugIntegrity(
            self::LEGACY_CONN,
            'our_clients'
        );

        if ($blankSlug > 0) {
            $this->error("  FAIL: {$blankSlug} blank/null slug(s) in our_clients.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK blank/null slugs      : 0');
        }

        if ($whitespaceSlug > 0) {
            $this->error(
                "  FAIL: {$whitespaceSlug} slug(s) with leading/trailing whitespace " .
                'in our_clients — slugs must be preserved exactly.'
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK whitespace slugs      : 0');
        }

        if ($dupSlug > 0) {
            $this->error("  FAIL: {$dupSlug} duplicate slug group(s) in our_clients.");
            $this->hadFailure = true;
        } else {
            $this->line('  OK duplicate slug groups : 0');
        }

        $this->line('  Slugs                               : planned PRESERVE exactly');
        $this->line('  All compatible fields, timestamps, deleted_at : planned PRESERVE');
        $this->newLine();
    }

    /**
     * [16] Local media audit — planned transformations are applied in memory
     * before file checks. No files are copied, renamed, deleted, or modified.
     *
     * Transformations applied in memory only:
     * - reviews.image equal to "0"                 → planned null
     * - best_offers.image                          → trim()
     * - partners.logo_partner                      → trim()
     * - countries_images.image_large ID 1          → ABKHAZIA_REPLACEMENT_JPG
     * - countries_images.image_large other IDs     → missing file = planned null
     * - countries_images.documents_url             → only the known validated value
     *   (DOCUMENTS_URL_KNOWN_NULL) is planned as null; unexpected values are
     *   already hard-failed in inspectCountriesImages() before this method runs
     *
     * "0" in any non-reviews media column is a hard failure (invalid path).
     * trim() is applied before external URL detection, path validation, and
     * file existence checks. HTTP/HTTPS detection is case-insensitive.
     *
     * For every local path planned to be preserved:
     * - is_file() is required (not only file_exists());
     * - exact path casing is verified via scandir();
     * - unexpected missing file is a hard failure;
     * - case mismatch is a hard failure.
     *
     * Unsafe paths containing a null byte or a ".." segment are hard failures.
     * storage/app/public/documents/personal is never inspected.
     */
    private function inspectLocalMedia(): void
    {
        $this->line('-- [16] Local media audit --------------------------------');
        $this->line('  Policy : inspect local paths only. No file copy/rename/delete.');
        $this->line('  Transformations applied in memory only. No database writes.');
        $this->line('  External HTTP/HTTPS URLs excluded from local-file checks.');
        $this->line('  Private personal documents excluded from all inspection.');
        $this->newLine();

        $mediaSpec = [
            'reviews'            => ['image'],
            'best_offers'        => ['image'],
            'partners'           => ['logo_partner'],
            'countries_images'   => ['image_small', 'image_large', 'documents_url'],
            'destination_images' => ['image_small', 'image_large'],
            'employees'          => ['image'],
            'awards'             => ['image'],
            'articles'           => ['image'],
            'news'               => ['image'],
            'our_clients'        => ['image'],
        ];

        $totalHardFailures = 0;
        $publicBase        = rtrim(public_path(), '/\\');

        foreach ($mediaSpec as $table => $columns) {
            foreach ($columns as $col) {
                $key = "{$table}.{$col}";

                // Fetch all non-null, non-empty rows.
                // The "0" filter is NOT applied globally — it is handled per-table below.
                $rows = DB::connection(self::LEGACY_CONN)
                    ->table($table)
                    ->select('id', $col)
                    ->whereNotNull($col)
                    ->where($col, '!=', '')
                    ->get()
                    ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
                    ->toArray();

                $total       = count($rows);
                $external    = 0;
                $preserved   = 0;
                $plannedNull = 0;
                $hardFails   = 0;

                foreach ($rows as $row) {
                    $rawVal = (string) $row[$col];
                    $id     = $row['id'];

                    // ── Null byte check on raw value (before any trim) ────────
                    if (str_contains($rawVal, "\0")) {
                        $this->error("  FAIL {$key} id={$id}: path contains null byte.");
                        $this->hadFailure = true;
                        $hardFails++;
                        $totalHardFailures++;
                        continue;
                    }

                    // Apply trim first (covers CR/LF/tab/space normalization)
                    $path = trim($rawVal);

                    // ── "0" value check ───────────────────────────────────────
                    if ($path === '0') {
                        if ($table === 'reviews' && $col === 'image') {
                            // reviews.image "0" is a known planned null
                            $plannedNull++;
                            continue;
                        }
                        // "0" in any other preserved column is an invalid path
                        $this->error(
                            "  FAIL {$key} id={$id}: invalid path value '0' " .
                            'in non-reviews column.'
                        );
                        $this->hadFailure = true;
                        $hardFails++;
                        $totalHardFailures++;
                        continue;
                    }

                    // ── countries_images.image_large ID 1 substitution ────────
                    if ($table === 'countries_images' && $col === 'image_large') {
                        if ((int) $id === 1) {
                            $path = self::ABKHAZIA_REPLACEMENT_JPG;
                        }
                        // Other IDs: use the trimmed original path; missing → planned null
                    }

                    // ── countries_images.documents_url → always planned null ──
                    if ($table === 'countries_images' && $col === 'documents_url') {
                        // Known value and any unexpected value both become null.
                        // Unexpected values were already hard-failed in inspectCountriesImages.
                        $plannedNull++;
                        continue;
                    }

                    if ($path === '') {
                        $plannedNull++;
                        continue;
                    }

                    // ── Reject unsafe paths ───────────────────────────────────
                    $segments = array_filter(
                        explode('/', str_replace('\\', '/', $path)),
                        static fn(string $s): bool => $s !== ''
                    );

                    if (in_array('..', $segments, true)) {
                        $this->error(
                            "  FAIL {$key} id={$id}: path contains '..' segment."
                        );
                        $this->hadFailure = true;
                        $hardFails++;
                        $totalHardFailures++;
                        continue;
                    }

                    // ── Case-insensitive external URL detection ───────────────
                    if (
                        stripos($path, 'http://') === 0 ||
                        stripos($path, 'https://') === 0
                    ) {
                        $external++;
                        continue;
                    }

                    // ── Build absolute local path ─────────────────────────────
                    $fullPath = $publicBase . DIRECTORY_SEPARATOR .
                        ltrim(
                            str_replace('/', DIRECTORY_SEPARATOR, $path),
                            DIRECTORY_SEPARATOR
                        );

                    // ── countries_images.image_large (non-ID-1): conditionally null ──
                    $isConditionallyNull = (
                        $table === 'countries_images' &&
                        $col   === 'image_large'      &&
                        (int) $id !== 1
                    );

                    if ($isConditionallyNull) {
                        // Existing file → preserve (case-check); missing → planned null
                        if (is_file($fullPath)) {
                            if ($this->verifyExactPathCase($fullPath, $publicBase)) {
                                $preserved++;
                            } else {
                                $this->error(
                                    "  FAIL {$key} id={$id}: case mismatch for '{$path}'."
                                );
                                $this->hadFailure = true;
                                $hardFails++;
                                $totalHardFailures++;
                            }
                        } else {
                            $plannedNull++;
                        }
                    } else {
                        // Planned to be preserved: missing or wrong case is a hard failure
                        if (!$this->verifyExactPathCase($fullPath, $publicBase)) {
                            if (!is_file($fullPath)) {
                                $this->error(
                                    "  FAIL {$key} id={$id}: " .
                                    "file not found '{$path}'."
                                );
                            } else {
                                $this->error(
                                    "  FAIL {$key} id={$id}: " .
                                    "case mismatch for '{$path}'."
                                );
                            }
                            $this->hadFailure = true;
                            $hardFails++;
                            $totalHardFailures++;
                        } else {
                            $preserved++;
                        }
                    }
                }

                $summary = "  {$key}: total={$total}";
                if ($external > 0) {
                    $summary .= ", external={$external}";
                }
                $summary .= ", preserved={$preserved}, planned-null={$plannedNull}";
                if ($hardFails > 0) {
                    $summary .= ", hard-failures={$hardFails}";
                }
                $this->line($summary);
            }
        }

        $this->newLine();

        if ($totalHardFailures > 0) {
            $this->error(
                "  FAIL: {$totalHardFailures} hard failure(s) in local media audit."
            );
            $this->hadFailure = true;
        } else {
            $this->line('  OK no hard failures in local media audit.');
        }

        $this->line(
            '  EXCLUDED: storage/app/public/documents/personal (private, not inspected)'
        );
        $this->newLine();
    }

    /**
     * [17] Report the canonical tables that are out of import scope for this stage.
     */
    private function reportExcludedScope(): void
    {
        $this->line('-- [17] Excluded scope -----------------------------------');
        $this->line('  The following canonical tables are out of import scope:');

        foreach (self::EXCLUDED_SCOPE as $table) {
            $this->line("    - {$table}");
        }

        $this->line(
            '  Personal documents in storage/app/public/documents/personal : EXCLUDED'
        );
        $this->line('  No data from excluded tables is included in this selective import stage.');
        $this->newLine();
    }

    // ─── Final verdict ────────────────────────────────────────────────────────

    private function renderFinalVerdict(): int
    {
        $this->info('==========================================================');
        $this->info('  DRY-RUN VERDICT: PASSED');
        $this->info('');
        $this->info('  Confirmations:');
        $this->info('  - No database writes were performed.');
        $this->info('  - No files were modified.');
        $this->info('  - Default active database was restored to: ' . self::LEGACY_DB);
        $this->info('  - Target ' . self::TARGET_DB . ' remains empty.');
        $this->info('  - The project is ready for execute-stage invocation.');
        $this->info('==========================================================');
        $this->newLine();
        return Command::SUCCESS;
    }

    private function renderExecuteFinalVerdict(): int
    {
        $this->newLine();
        $this->line('-- [exec-verdict] Execute mode final verdict -------------');

        if ($this->transactionCommitted) {
            if (
                $this->postCommitVerificationSucceeded &&
                $this->checkpointDPassed &&
                $this->lockReleaseConfirmed &&
                $this->cleanupSucceeded
            ) {
                $this->info('==========================================================');
                $this->info('  EXECUTE VERDICT: SUCCESS');
                $this->info('  Data committed to: ' . self::TARGET_DB);
                $this->info('  Checkpoint D passed.');
                $this->info('  Named lock released.');
                $this->info('  Runtime connections purged.');
                $this->info('==========================================================');
                $this->newLine();
                return Command::SUCCESS;
            }

            // Committed but post-commit or checkpoint D failed
            $this->error('==========================================================');
            $this->error('  EXECUTE VERDICT: COMMITTED — POST-COMMIT ISSUES');
            $this->error('  Data IS committed to: ' . self::TARGET_DB);
            $this->error('  One or more post-commit verifications failed.');
            $this->error('  Manual review of ' . self::TARGET_DB . ' is required.');
            $this->error('==========================================================');
            $this->newLine();
            return Command::FAILURE;
        }

        if ($this->transactionStarted) {
            if ($this->rollbackVerificationSucceeded) {
                $this->error('==========================================================');
                $this->error('  EXECUTE VERDICT: ROLLED BACK — NO DATA WRITTEN');
                $this->error('  Rollback verified: all import tables are empty.');
                $this->error('==========================================================');
            } else {
                $this->error('==========================================================');
                $this->error('  EXECUTE VERDICT: TRANSACTION NOT COMMITTED — TARGET STATE UNCONFIRMED');
                $this->error('  Rollback verification failed. Manual review required.');
                $this->error('==========================================================');
            }
            $this->newLine();
            return Command::FAILURE;
        }

        $this->error('==========================================================');
        $this->error('  EXECUTE VERDICT: FAILED BEFORE COMMIT');
        $this->error('  No writes were performed on: ' . self::TARGET_DB);
        $this->error('  Check stage-prefixed FAIL messages above.');
        $this->error('==========================================================');
        $this->newLine();
        return Command::FAILURE;
    }

    // ─── Execute schema preflight ─────────────────────────────────────────────

    /**
     * Validate schema prerequisites before any snapshot or write operation.
     * All checks use read-only information_schema queries.
     */
    private function runExecuteSchemaPreflight(): bool
    {
        $this->line('-- [exec-preflight] Execute schema preflight ------------');
        $ok = true;

        // 1. Required source columns
        $sourceRequiredCols = [
            'roles'              => ['id', 'role', 'name', 'created_at', 'updated_at', 'deleted_at'],
            'users'              => ['id', 'name', 'email', 'email_verified_at', 'password', 'created_at', 'updated_at'],
            'role_user'          => ['user_id', 'role_id', 'created_at', 'updated_at', 'deleted_at'],
            'reviews'            => ['id', 'name', 'title', 'content', 'image', 'is_published', 'created_at', 'updated_at', 'deleted_at'],
            'best_offers'        => ['id', 'title', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'partners'           => ['id', 'name_partner', 'logo_partner', 'created_at', 'updated_at', 'deleted_at'],
            'countries_images'   => ['id', 'title', 'slug', 'category', 'description', 'image_small', 'image_large', 'documents_url', 'created_at', 'updated_at', 'deleted_at'],
            'destination_images' => ['id', 'title', 'slug', 'description', 'image_small', 'image_large', 'created_at', 'updated_at', 'deleted_at'],
            'employees'          => ['id', 'name', 'position', 'tel', 'email', 'whatsapp', 'vk', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'awards'             => ['id', 'image', 'category', 'created_at', 'updated_at', 'deleted_at'],
            'articles'           => ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'news'               => ['id', 'title', 'slug', 'link', 'description', 'image', 'pub_date', 'created_at', 'updated_at', 'deleted_at'],
            'our_clients'        => ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
        ];

        foreach ($sourceRequiredCols as $table => $columns) {
            $existing = $this->getTableColumns(self::LEGACY_CONN, self::LEGACY_DB, $table);
            $tableOk  = true;
            foreach ($columns as $col) {
                if (!in_array($col, $existing, true)) {
                    $this->error("[schema-preflight] FAIL: Source {$table}.{$col} not found.");
                    $ok      = false;
                    $tableOk = false;
                }
            }
            if ($tableOk) {
                $this->line("[schema-preflight] OK source {$table} columns verified.");
            }
        }

        // 2. Required target columns
        $targetRequiredCols = [
            'roles'              => ['id', 'name', 'description', 'created_at', 'updated_at', 'deleted_at'],
            'users'              => ['id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'password_change_required', 'temp_password', 'phone', 'is_active', 'birth_date', 'gender', 'address', 'passport_number', 'passport_issued_date', 'passport_issued_by', 'notification_settings', 'avatar_path', 'last_login_at', 'created_at', 'updated_at'],
            'role_user'          => ['user_id', 'role_id', 'created_at', 'updated_at', 'deleted_at'],
            'reviews'            => ['id', 'name', 'title', 'content', 'image', 'is_published', 'created_at', 'updated_at', 'deleted_at'],
            'best_offers'        => ['id', 'title', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'partners'           => ['id', 'name_partner', 'logo_partner', 'created_at', 'updated_at', 'deleted_at'],
            'countries_images'   => ['id', 'title', 'slug', 'category', 'description', 'image_small', 'image_large', 'documents_url', 'created_at', 'updated_at', 'deleted_at'],
            'destination_images' => ['id', 'title', 'slug', 'description', 'image_small', 'image_large', 'created_at', 'updated_at', 'deleted_at'],
            'employees'          => ['id', 'name', 'position', 'tel', 'email', 'whatsapp', 'vk', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'awards'             => ['id', 'image', 'category', 'created_at', 'updated_at', 'deleted_at'],
            'articles'           => ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'news'               => ['id', 'title', 'slug', 'link', 'description', 'image', 'pub_date', 'created_at', 'updated_at', 'deleted_at'],
            'our_clients'        => ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
        ];

        foreach ($targetRequiredCols as $table => $columns) {
            $existing  = $this->getTableColumns(self::TARGET_CONN, self::TARGET_DB, $table);
            $tableOk   = true;
            foreach ($columns as $col) {
                if (!in_array($col, $existing, true)) {
                    $this->error("[schema-preflight] FAIL: Target {$table}.{$col} not found.");
                    $ok      = false;
                    $tableOk = false;
                }
            }
            if ($tableOk) {
                $this->line("[schema-preflight] OK target {$table} columns verified.");
            }
        }

        // 3. ENGINE=InnoDB for all 13 import target tables
        $placeholders = implode(',', array_fill(0, count(self::IMPORT_TARGET_TABLES), '?'));
        $engineRows   = DB::connection(self::TARGET_CONN)->select(
            "SELECT TABLE_NAME AS table_name, ENGINE AS engine
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME IN ({$placeholders})",
            array_merge([self::TARGET_DB], self::IMPORT_TARGET_TABLES)
        );

        $engineMap = [];
        foreach ($engineRows as $row) {
            $r                           = array_change_key_case((array) $row, CASE_LOWER);
            $engineMap[$r['table_name']] = strtolower((string) ($r['engine'] ?? ''));
        }

        foreach (self::IMPORT_TARGET_TABLES as $table) {
            $engine = $engineMap[$table] ?? 'unknown';
            if ($engine !== 'innodb') {
                $this->error(
                    "[schema-preflight] FAIL: Target {$table} ENGINE is '{$engine}', expected 'innodb'."
                );
                $ok = false;
            } else {
                $this->line("[schema-preflight] OK ENGINE=InnoDB: {$table}");
            }
        }

        // 4. users.is_active default normalizes to 1
        $isActiveInfo    = $this->getColumnInfo(self::TARGET_CONN, self::TARGET_DB, 'users', 'is_active');
        $isActiveDefault = $isActiveInfo !== null ? (int) ($isActiveInfo['column_default'] ?? -1) : -1;
        if ($isActiveDefault !== 1) {
            $this->error(
                "[schema-preflight] FAIL: users.is_active COLUMN_DEFAULT is '{$isActiveDefault}', expected 1."
            );
            $ok = false;
        } else {
            $this->line('[schema-preflight] OK users.is_active default: 1');
        }

        // 5. users.password_change_required default normalizes to 0
        $pcrInfo    = $this->getColumnInfo(self::TARGET_CONN, self::TARGET_DB, 'users', 'password_change_required');
        $pcrDefault = $pcrInfo !== null ? (int) ($pcrInfo['column_default'] ?? -1) : -1;
        if ($pcrDefault !== 0) {
            $this->error(
                "[schema-preflight] FAIL: users.password_change_required COLUMN_DEFAULT is '{$pcrDefault}', expected 0."
            );
            $ok = false;
        } else {
            $this->line('[schema-preflight] OK users.password_change_required default: 0');
        }

        // 6. All expected excluded target tables exist and contain zero rows
        foreach (self::EXCLUDED_SCOPE as $table) {
            $count = DB::connection(self::TARGET_CONN)->table($table)->count();
            if ($count !== 0) {
                $this->error(
                    "[schema-preflight] FAIL: Excluded target table '{$table}' has {$count} rows."
                );
                $ok = false;
            } else {
                $this->line("[schema-preflight] OK excluded empty: {$table}");
            }
        }

        // 7. profiles excluded — not required in target
        $this->line('[schema-preflight] OK profiles excluded from target scope (dropped in rebuild).');

        if ($ok) {
            $this->line('[schema-preflight] All preflight checks passed.');
        }
        $this->newLine();
        return $ok;
    }

    // ─── Snapshot building and validation ─────────────────────────────────────

    /**
     * Encode an array as canonical JSON.
     * Uses JSON_THROW_ON_ERROR so a failure is never silently cast to false/string.
     * All source and target table hashes must use this helper exclusively.
     */
    private function canonicalJson(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Build the complete transformed source snapshot and validate it.
     * Returns null if any validation check fails.
     */
    private function buildAndValidateCheckpointA(string $executionTimestamp): ?array
    {
        $snapshot = $this->buildSourceSnapshot($executionTimestamp);

        if (!$this->validateSnapshotA($snapshot)) {
            return null;
        }

        return $snapshot;
    }

    /**
     * Build the complete deterministic transformed source snapshot in memory.
     * Applies all explicitly specified data transformations. No writes performed.
     *
     * An unsafe path (null byte or ".." segment) in countries_images.image_large
     * is a hard failure — throws \UnexpectedValueException; never silently converted.
     */
    private function buildSourceSnapshot(string $executionTimestamp): array
    {
        $payload         = [];
        $sourceCounts    = [];
        $transformations = [
            'reviews_blank_title'         => 0,
            'reviews_image_zero'          => 0,
            'best_offers_image_trim'      => 0,
            'partners_logo_trim'          => 0,
            'countries_abkhazia_replaced' => 0,
            'countries_image_large_null'  => 0,
            'countries_documents_null'    => 0,
        ];

        $publicBase = rtrim(public_path(), '/\\');

        // ─── roles ───────────────────────────────────────────────────────────
        $sourceRoles = DB::connection(self::LEGACY_CONN)
            ->table('roles')
            ->select('id', 'role', 'name', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['roles'] = count($sourceRoles);

        $rolesPayload = [];
        foreach ($sourceRoles as $row) {
            $rolesPayload[] = [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'description' => self::CANONICAL_ROLE_DESCRIPTIONS[$row['name']] ?? null,
                'created_at'  => $this->normalizeTimestamp($row['created_at']),
                'updated_at'  => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at'  => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $rolesPayload[] = [
            'id'          => 3,
            'name'        => 'manager',
            'description' => self::CANONICAL_ROLE_DESCRIPTIONS['manager'],
            'created_at'  => $executionTimestamp,
            'updated_at'  => $executionTimestamp,
            'deleted_at'  => null,
        ];
        usort($rolesPayload, static fn($a, $b) => $a['id'] <=> $b['id']);
        $payload['roles'] = $rolesPayload;

        // ─── users ───────────────────────────────────────────────────────────
        $sourceUsers = DB::connection(self::LEGACY_CONN)
            ->table('users')
            ->select('id', 'name', 'email', 'email_verified_at', 'password', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['users'] = count($sourceUsers);

        $usersPayload = [];
        foreach ($sourceUsers as $row) {
            $usersPayload[] = [
                'id'                       => (int) $row['id'],
                'name'                     => (string) $row['name'],
                'email'                    => (string) $row['email'],
                'email_verified_at'        => $row['email_verified_at'] !== null
                    ? $this->normalizeTimestamp($row['email_verified_at']) : null,
                'password'                 => (string) $row['password'],
                'remember_token'           => null,
                'password_change_required' => 0,
                'temp_password'            => null,
                'phone'                    => null,
                'is_active'                => 1,
                'birth_date'               => null,
                'gender'                   => null,
                'address'                  => null,
                'passport_number'          => null,
                'passport_issued_date'     => null,
                'passport_issued_by'       => null,
                'notification_settings'    => null,
                'avatar_path'              => null,
                'last_login_at'            => null,
                'created_at'               => $this->normalizeTimestamp($row['created_at']),
                'updated_at'               => $this->normalizeTimestamp($row['updated_at']),
            ];
        }
        $payload['users'] = $usersPayload;

        // ─── role_user ────────────────────────────────────────────────────────
        $sourceRoleUser = DB::connection(self::LEGACY_CONN)
            ->table('role_user')
            ->select('user_id', 'role_id', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('user_id')
            ->orderBy('role_id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['role_user'] = count($sourceRoleUser);

        $roleUserPayload = [];
        foreach ($sourceRoleUser as $row) {
            $roleUserPayload[] = [
                'user_id'    => (int) $row['user_id'],
                'role_id'    => (int) $row['role_id'],
                'created_at' => $this->normalizeTimestamp($row['created_at']),
                'updated_at' => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at' => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['role_user'] = $roleUserPayload;

        // ─── reviews ─────────────────────────────────────────────────────────
        $sourceReviews = DB::connection(self::LEGACY_CONN)
            ->table('reviews')
            ->select('id', 'name', 'title', 'content', 'image', 'is_published', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['reviews'] = count($sourceReviews);

        $reviewsPayload = [];
        foreach ($sourceReviews as $row) {
            $title = $row['title'];
            if ($title === null || trim((string) $title) === '') {
                $title = null;
                $transformations['reviews_blank_title']++;
            }

            $image = $row['image'];
            if ($image !== null && (string) $image === '0') {
                $image = null;
                $transformations['reviews_image_zero']++;
            }

            $reviewsPayload[] = [
                'id'           => (int) $row['id'],
                'name'         => (string) $row['name'],
                'title'        => $title,
                'content'      => $row['content'] !== null ? (string) $row['content'] : null,
                'image'        => $image,
                'is_published' => (int) (bool) $row['is_published'],
                'created_at'   => $this->normalizeTimestamp($row['created_at']),
                'updated_at'   => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at'   => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['reviews'] = $reviewsPayload;

        // ─── best_offers ─────────────────────────────────────────────────────
        $sourceBestOffers = DB::connection(self::LEGACY_CONN)
            ->table('best_offers')
            ->select('id', 'title', 'content', 'image', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['best_offers'] = count($sourceBestOffers);

        $bestOffersPayload = [];
        foreach ($sourceBestOffers as $row) {
            $image = $row['image'];
            if ($image !== null) {
                $trimmed = trim((string) $image);
                if ($trimmed !== (string) $image) {
                    $transformations['best_offers_image_trim']++;
                }
                $image = $trimmed !== '' ? $trimmed : null;
            }

            $bestOffersPayload[] = [
                'id'         => (int) $row['id'],
                'title'      => (string) $row['title'],
                'content'    => $row['content'] !== null ? (string) $row['content'] : null,
                'image'      => $image,
                'created_at' => $this->normalizeTimestamp($row['created_at']),
                'updated_at' => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at' => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['best_offers'] = $bestOffersPayload;

        // ─── partners ────────────────────────────────────────────────────────
        $sourcePartners = DB::connection(self::LEGACY_CONN)
            ->table('partners')
            ->select('id', 'name_partner', 'logo_partner', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['partners'] = count($sourcePartners);

        $partnersPayload = [];
        foreach ($sourcePartners as $row) {
            $logo = $row['logo_partner'];
            if ($logo !== null) {
                $trimmed = trim((string) $logo);
                if ($trimmed !== (string) $logo) {
                    $transformations['partners_logo_trim']++;
                }
                $logo = $trimmed !== '' ? $trimmed : null;
            }

            $partnersPayload[] = [
                'id'           => (int) $row['id'],
                'name_partner' => (string) $row['name_partner'],
                'logo_partner' => $logo,
                'created_at'   => $this->normalizeTimestamp($row['created_at']),
                'updated_at'   => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at'   => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['partners'] = $partnersPayload;

        // ─── countries_images ─────────────────────────────────────────────────
        $sourceCountries = DB::connection(self::LEGACY_CONN)
            ->table('countries_images')
            ->select('id', 'title', 'slug', 'category', 'description', 'image_small', 'image_large', 'documents_url', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['countries_images'] = count($sourceCountries);

        $countriesPayload = [];
        foreach ($sourceCountries as $row) {
            $id         = (int) $row['id'];
            $imageLarge = $row['image_large'];

            if ($id === 1) {
                $imageLarge = self::ABKHAZIA_REPLACEMENT_JPG;
                $transformations['countries_abkhazia_replaced']++;
            } else {
                if ($imageLarge !== null && $imageLarge !== '') {
                    $rawImageLarge = (string) $imageLarge;

                    // Check the raw value before trim(), because PHP trim() can remove NUL bytes.
                    if (str_contains($rawImageLarge, "\0")) {
                        throw new \UnexpectedValueException('countries-images-unsafe-null-byte');
                    }

                    $path = trim($rawImageLarge);

                    // ".." path segments are hard failures — never silently null.
                    $segs = array_filter(
                        explode('/', str_replace('\\', '/', $path)),
                        static fn(string $s): bool => $s !== ''
                    );
                    if (in_array('..', $segs, true)) {
                        throw new \UnexpectedValueException('countries-images-unsafe-dotdot');
                    }

                    if ($path === '') {
                        $imageLarge = null;
                        $transformations['countries_image_large_null']++;
                    } else {
                        $fullPath = $publicBase . DIRECTORY_SEPARATOR .
                            ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
                        if ($this->verifyExactPathCase($fullPath, public_path())) {
                            $imageLarge = $path; // trimmed preserved path
                        } else {
                            $imageLarge = null;
                            $transformations['countries_image_large_null']++;
                        }
                    }
                } else {
                    $imageLarge = null;
                }
            }

            // documents_url always null; count non-null/non-empty source values
            $docsRaw = $row['documents_url'];
            if ($docsRaw !== null && $docsRaw !== '') {
                $rawDocumentsUrl = (string) $docsRaw;

                // Validate the raw value before trim(), for the same path-safety reason.
                if (str_contains($rawDocumentsUrl, "\0")) {
                    throw new \UnexpectedValueException('countries-documents-unsafe-null-byte');
                }

                $documentSegments = array_filter(
                    explode('/', str_replace('\\', '/', $rawDocumentsUrl)),
                    static fn(string $segment): bool => $segment !== ''
                );
                if (in_array('..', $documentSegments, true)) {
                    throw new \UnexpectedValueException('countries-documents-unsafe-dotdot');
                }

                $transformations['countries_documents_null']++;
            }

            $countriesPayload[] = [
                'id'            => $id,
                'title'         => (string) $row['title'],
                'slug'          => (string) $row['slug'],
                'category'      => $row['category'] !== null ? (string) $row['category'] : null,
                'description'   => $row['description'] !== null ? (string) $row['description'] : null,
                'image_small'   => $row['image_small'],
                'image_large'   => $imageLarge,
                'documents_url' => null,
                'created_at'    => $this->normalizeTimestamp($row['created_at']),
                'updated_at'    => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at'    => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['countries_images'] = $countriesPayload;

        // ─── destination_images ───────────────────────────────────────────────
        $sourceDestImages = DB::connection(self::LEGACY_CONN)
            ->table('destination_images')
            ->select('id', 'title', 'slug', 'description', 'image_small', 'image_large', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['destination_images'] = count($sourceDestImages);

        $destPayload = [];
        foreach ($sourceDestImages as $row) {
            $destPayload[] = [
                'id'          => (int) $row['id'],
                'title'       => (string) $row['title'],
                'slug'        => (string) $row['slug'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'image_small' => $row['image_small'],
                'image_large' => $row['image_large'],
                'created_at'  => $this->normalizeTimestamp($row['created_at']),
                'updated_at'  => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at'  => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['destination_images'] = $destPayload;

        // ─── employees ───────────────────────────────────────────────────────
        $sourceEmployees = DB::connection(self::LEGACY_CONN)
            ->table('employees')
            ->select('id', 'name', 'position', 'tel', 'email', 'whatsapp', 'vk', 'image', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['employees'] = count($sourceEmployees);

        $empPayload = [];
        foreach ($sourceEmployees as $row) {
            $empPayload[] = [
                'id'         => (int) $row['id'],
                'name'       => (string) $row['name'],
                'position'   => $row['position'] !== null ? (string) $row['position'] : null,
                'tel'        => $row['tel']      !== null ? (string) $row['tel']      : null,
                'email'      => $row['email']    !== null ? (string) $row['email']    : null,
                'whatsapp'   => $row['whatsapp'] !== null ? (string) $row['whatsapp'] : null,
                'vk'         => $row['vk']       !== null ? (string) $row['vk']       : null,
                'image'      => $row['image'],
                'created_at' => $this->normalizeTimestamp($row['created_at']),
                'updated_at' => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at' => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['employees'] = $empPayload;

        // ─── awards ──────────────────────────────────────────────────────────
        $sourceAwards = DB::connection(self::LEGACY_CONN)
            ->table('awards')
            ->select('id', 'image', 'category', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['awards'] = count($sourceAwards);

        $awardsPayload = [];
        foreach ($sourceAwards as $row) {
            $awardsPayload[] = [
                'id'         => (int) $row['id'],
                'image'      => $row['image'],
                'category'   => $row['category'] !== null ? (string) $row['category'] : null,
                'created_at' => $this->normalizeTimestamp($row['created_at']),
                'updated_at' => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at' => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['awards'] = $awardsPayload;

        // ─── articles ────────────────────────────────────────────────────────
        $sourceArticles = DB::connection(self::LEGACY_CONN)
            ->table('articles')
            ->select('id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['articles'] = count($sourceArticles);

        $articlesPayload = [];
        foreach ($sourceArticles as $row) {
            $articlesPayload[] = [
                'id'         => (int) $row['id'],
                'title'      => (string) $row['title'],
                'slug'       => (string) $row['slug'],
                'content'    => $row['content'] !== null ? (string) $row['content'] : null,
                'image'      => $row['image'],
                'created_at' => $this->normalizeTimestamp($row['created_at']),
                'updated_at' => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at' => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['articles'] = $articlesPayload;

        // ─── news ────────────────────────────────────────────────────────────
        $sourceNews = DB::connection(self::LEGACY_CONN)
            ->table('news')
            ->select('id', 'title', 'slug', 'link', 'description', 'image', 'pub_date', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['news'] = count($sourceNews);

        $newsPayload = [];
        foreach ($sourceNews as $row) {
            $newsPayload[] = [
                'id'          => (int) $row['id'],
                'title'       => (string) $row['title'],
                'slug'        => (string) $row['slug'],
                'link'        => $row['link']        !== null ? (string) $row['link']        : null,
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'image'       => $row['image'],
                'pub_date'    => $row['pub_date'] !== null
                    ? $this->normalizeTimestamp($row['pub_date']) : null,
                'created_at'  => $this->normalizeTimestamp($row['created_at']),
                'updated_at'  => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at'  => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['news'] = $newsPayload;

        // ─── our_clients ─────────────────────────────────────────────────────
        $sourceOurClients = DB::connection(self::LEGACY_CONN)
            ->table('our_clients')
            ->select('id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->toArray();

        $sourceCounts['our_clients'] = count($sourceOurClients);

        $ourClientsPayload = [];
        foreach ($sourceOurClients as $row) {
            $ourClientsPayload[] = [
                'id'         => (int) $row['id'],
                'title'      => (string) $row['title'],
                'slug'       => (string) $row['slug'],
                'content'    => $row['content'] !== null ? (string) $row['content'] : null,
                'image'      => $row['image'],
                'created_at' => $this->normalizeTimestamp($row['created_at']),
                'updated_at' => $this->normalizeTimestamp($row['updated_at']),
                'deleted_at' => $row['deleted_at'] !== null
                    ? $this->normalizeTimestamp($row['deleted_at']) : null,
            ];
        }
        $payload['our_clients'] = $ourClientsPayload;

        // ─── Canonical per-table payload hashes (via canonicalJson) ───────────
        $tableHashes = [];
        foreach ($payload as $table => $rows) {
            $tableHashes[$table] = hash('sha256', $this->canonicalJson($rows));
        }

        // ─── Source guard fingerprints ────────────────────────────────────────
        // Captures raw (pre-transformation) values so that B/C/D detect changes
        // to validation-only fields even when the transformed payload is unchanged.

        // Roles raw: id, role, name
        $rolesRaw = array_map(
            static fn($r) => [
                'id'   => (int) $r['id'],
                'role' => (string) $r['role'],
                'name' => (string) $r['name'],
            ],
            $sourceRoles
        );

        // Reviews: sorted IDs with raw image = '0'
        $reviewsImageZeroIds = [];
        foreach ($sourceReviews as $r) {
            if ($r['image'] !== null && (string) $r['image'] === '0') {
                $reviewsImageZeroIds[] = (int) $r['id'];
            }
        }
        sort($reviewsImageZeroIds);

        // countries_images: raw image_large and documents_url per row
        $countriesRaw = array_map(
            static fn($r) => [
                'id'            => (int) $r['id'],
                'image_large'   => $r['image_large'],
                'documents_url' => $r['documents_url'],
            ],
            $sourceCountries
        );

        // Users: hash of sorted emails (for dup detection — emails not stored)
        $userEmailsSorted = array_map(static fn($r) => (string) $r['email'], $sourceUsers);
        sort($userEmailsSorted);
        $userEmailDupGroups = count($userEmailsSorted) - count(array_unique($userEmailsSorted));

        // Role_user: sorted pairs
        $roleUserPairs = array_map(
            static fn($r) => [(int) $r['user_id'], (int) $r['role_id']],
            $sourceRoleUser
        );
        usort($roleUserPairs, static fn($a, $b) => $a[0] !== $b[0] ? $a[0] <=> $b[0] : $a[1] <=> $b[1]);

        // Slug stats and sorted slug lists per slug table
        $slugTableNames = ['countries_images', 'destination_images', 'articles', 'news', 'our_clients'];
        $slugStats      = [];
        $slugSorted     = [];
        foreach ($slugTableNames as $tbl) {
            $slugValues = array_column($payload[$tbl], 'slug');
            $blankCnt   = 0;
            $wsCnt      = 0;
            $dupCounts  = [];
            foreach ($slugValues as $sv) {
                $svStr = $sv === null ? '' : (string) $sv;
                if ($sv === null || trim($svStr) === '') {
                    $blankCnt++;
                } elseif (trim($svStr) !== $svStr) {
                    $wsCnt++;
                }
                $dupCounts[$svStr] = ($dupCounts[$svStr] ?? 0) + 1;
            }
            $dupGroups = count(array_filter($dupCounts, static fn($c) => $c > 1));
            $slugStats[$tbl] = ['blank' => $blankCnt, 'whitespace' => $wsCnt, 'dup_groups' => $dupGroups];
            $sorted = $slugValues;
            sort($sorted);
            $slugSorted[$tbl] = $sorted;
        }

        // News: external HTTP/HTTPS image count (no network requests)
        $newsExternalCount = 0;
        foreach ($sourceNews as $r) {
            if ($r['image'] !== null) {
                $img = (string) $r['image'];
                if (stripos($img, 'http://') === 0 || stripos($img, 'https://') === 0) {
                    $newsExternalCount++;
                }
            }
        }

        // countries ID1 raw image_large and documents_url unexpected count
        $countriesId1RawImageLarge     = null;
        $documentsUrlUnexpectedCount   = 0;
        foreach ($sourceCountries as $r) {
            if ((int) $r['id'] === 1) {
                $countriesId1RawImageLarge = $r['image_large'];
            }
            $docsRaw = $r['documents_url'];
            if ($docsRaw !== null && $docsRaw !== '') {
                $docVal = trim((string) $docsRaw);
                if ($docVal !== '' && $docVal !== self::DOCUMENTS_URL_KNOWN_NULL) {
                    $documentsUrlUnexpectedCount++;
                }
            }
        }

        // User null validation counts
        $userNullCounts = [
            'name'     => count(array_filter($sourceUsers, static fn($r) => $r['name']     === null)),
            'email'    => count(array_filter($sourceUsers, static fn($r) => $r['email']    === null)),
            'password' => count(array_filter($sourceUsers, static fn($r) => $r['password'] === null)),
        ];

        // Profiles remain excluded only while the legacy source table is empty.
        $profilesCount = (int) DB::connection(self::LEGACY_CONN)
            ->table('profiles')
            ->count();

        $guardData = [
            'roles_raw'                       => $rolesRaw,
            'reviews_image_zero_ids'          => $reviewsImageZeroIds,
            'countries_raw'                   => $countriesRaw,
            'user_emails_hash'                => hash('sha256', $this->canonicalJson($userEmailsSorted)),
            'user_email_dup_groups'           => $userEmailDupGroups,
            'user_null_counts'                => $userNullCounts,
            'role_user_pairs'                 => $roleUserPairs,
            'slug_stats'                      => $slugStats,
            'slug_sorted'                     => $slugSorted,
            'news_external_count'             => $newsExternalCount,
            'countries_id1_raw_image_large'   => $countriesId1RawImageLarge,
            'documents_url_unexpected_count'  => $documentsUrlUnexpectedCount,
            'profiles_count'                   => $profilesCount,
        ];

        $sourceGuardHash = hash('sha256', $this->canonicalJson($guardData));

        return [
            'payload'             => $payload,
            'source_counts'       => $sourceCounts,
            'transformed_counts'  => array_map('count', $payload),
            'table_hashes'        => $tableHashes,
            'transformations'     => $transformations,
            'source_guard_hash'   => $sourceGuardHash,
            'source_guard_data'   => $guardData,
            'execution_timestamp' => $executionTimestamp,
        ];
    }

    /**
     * Self-contained snapshot validation against known expected baselines.
     * Reads all required values from the snapshot's source_guard_data —
     * does not make additional database calls.
     */
    private function validateSnapshotA(array $snapshot): bool
    {
        $this->line('[source-checkpoint-a] Validating snapshot A...');
        $ok   = true;
        $gd   = $snapshot['source_guard_data'];

        // ── Source counts ─────────────────────────────────────────────────────
        foreach (self::EXPECTED_SOURCE_COUNTS as $table => $expected) {
            $actual = $snapshot['source_counts'][$table] ?? null;
            if ($actual !== $expected) {
                $this->error(
                    "[source-checkpoint-a] FAIL: {$table} source count is {$actual}, expected {$expected}."
                );
                $ok = false;
            } else {
                $this->line("[source-checkpoint-a] OK source {$table}: {$expected}");
            }
        }

        // ── Transformed counts ────────────────────────────────────────────────
        $expectedTransformed = array_merge(self::EXPECTED_SOURCE_COUNTS, ['roles' => 3]);
        foreach ($expectedTransformed as $table => $expected) {
            $actual = $snapshot['transformed_counts'][$table] ?? null;
            if ($actual !== $expected) {
                $this->error(
                    "[source-checkpoint-a] FAIL: {$table} transformed count is {$actual}, expected {$expected}."
                );
                $ok = false;
            }
        }

        // ── Transformation counters ───────────────────────────────────────────
        $counterChecks = [
            'reviews_blank_title'         => 4,
            'reviews_image_zero'          => 2,
            'best_offers_image_trim'      => 4,
            'partners_logo_trim'          => 18,
            'countries_abkhazia_replaced' => 1,
            'countries_image_large_null'  => 53,
            'countries_documents_null'    => 33,
        ];
        foreach ($counterChecks as $key => $expected) {
            $actual = $snapshot['transformations'][$key] ?? 0;
            if ($actual !== $expected) {
                $this->error(
                    "[source-checkpoint-a] FAIL: {$key} = {$actual}, expected {$expected}."
                );
                $ok = false;
            } else {
                $this->line("[source-checkpoint-a] OK {$key}: {$expected}");
            }
        }

        // ── Roles: exactly 2; ID 1 role=user name=tourist; ID 2 role=admin name=admin ──
        $rolesRaw = $gd['roles_raw'] ?? [];
        if (count($rolesRaw) !== 2) {
            $this->error('[source-checkpoint-a] FAIL: Expected exactly 2 source roles.');
            $ok = false;
        }
        $rolesById = [];
        foreach ($rolesRaw as $r) {
            $rolesById[(int) $r['id']] = $r;
        }
        foreach ([1 => ['role' => 'user', 'name' => 'tourist'], 2 => ['role' => 'admin', 'name' => 'admin']] as $id => $expected) {
            $actual = $rolesById[$id] ?? null;
            if ($actual === null) {
                $this->error("[source-checkpoint-a] FAIL: No role ID {$id} in source.");
                $ok = false;
            } else {
                foreach (['role', 'name'] as $f) {
                    if ($actual[$f] !== $expected[$f]) {
                        $this->error(
                            "[source-checkpoint-a] FAIL: Role ID {$id} {$f} is '{$actual[$f]}', expected '{$expected[$f]}'."
                        );
                        $ok = false;
                    } else {
                        $this->line("[source-checkpoint-a] OK roles ID {$id} {$f}: '{$expected[$f]}'");
                    }
                }
            }
        }

        // ── Users: no null name/email/password; no dup emails ─────────────────
        $nullCounts = $gd['user_null_counts'] ?? [];
        foreach (['name', 'email', 'password'] as $field) {
            $count = $nullCounts[$field] ?? 0;
            if ($count !== 0) {
                $this->error("[source-checkpoint-a] FAIL: {$count} user(s) have null {$field}.");
                $ok = false;
            }
        }
        $dupEmailGroups = $gd['user_email_dup_groups'] ?? 0;
        if ($dupEmailGroups !== 0) {
            $this->error("[source-checkpoint-a] FAIL: {$dupEmailGroups} duplicate email group(s).");
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK no duplicate user emails.');
        }

        // ── Reviews: blank title = 4; image-zero IDs = [54, 55] ─────────────
        $zeroIds = $gd['reviews_image_zero_ids'] ?? [];
        if ($zeroIds !== [54, 55]) {
            $this->error(
                '[source-checkpoint-a] FAIL: reviews image="0" IDs are [' .
                implode(', ', $zeroIds) . '], expected [54, 55].'
            );
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK reviews image="0" IDs: [54, 55]');
        }

        // ── Slug tables: no blank/whitespace/duplicate slugs ─────────────────
        foreach ($gd['slug_stats'] ?? [] as $table => $stats) {
            if ($stats['blank'] > 0) {
                $this->error("[source-checkpoint-a] FAIL: {$table} has {$stats['blank']} blank slug(s).");
                $ok = false;
            }
            if ($stats['whitespace'] > 0) {
                $this->error("[source-checkpoint-a] FAIL: {$table} has {$stats['whitespace']} whitespace slug(s).");
                $ok = false;
            }
            if ($stats['dup_groups'] > 0) {
                $this->error("[source-checkpoint-a] FAIL: {$table} has {$stats['dup_groups']} duplicate slug group(s).");
                $ok = false;
            }
        }

        // ── Countries: ID 1 raw image_large must equal known PNG path ─────────
        $id1Raw = $gd['countries_id1_raw_image_large'] ?? null;
        if ($id1Raw !== self::ABKHAZIA_LEGACY_PNG) {
            $this->error(
                "[source-checkpoint-a] FAIL: countries_images ID 1 raw image_large is '{$id1Raw}', " .
                'expected \'' . self::ABKHAZIA_LEGACY_PNG . "'."
            );
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK countries_images ID 1 raw image_large matches.');
        }

        // Replacement file must exist
        $replacementFullPath = public_path(ltrim(self::ABKHAZIA_REPLACEMENT_JPG, '/'));
        if (!$this->verifyExactPathCase($replacementFullPath, public_path())) {
            $this->error(
                '[source-checkpoint-a] FAIL: Replacement file not found or case mismatch: ' .
                self::ABKHAZIA_REPLACEMENT_JPG
            );
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK replacement file exists: ' . self::ABKHAZIA_REPLACEMENT_JPG);
        }

        // ── documents_url: no unexpected values ───────────────────────────────
        $docsUnexpected = $gd['documents_url_unexpected_count'] ?? 0;
        if ($docsUnexpected !== 0) {
            $this->error("[source-checkpoint-a] FAIL: {$docsUnexpected} unexpected documents_url value(s).");
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK no unexpected documents_url values.');
        }

        // ── News: external image count = 138 ──────────────────────────────────
        $newsExt = $gd['news_external_count'] ?? 0;
        if ($newsExt !== 138) {
            $this->error("[source-checkpoint-a] FAIL: news external image count is {$newsExt}, expected 138.");
            $ok = false;
        } else {
            $this->line("[source-checkpoint-a] OK news external image count: 138");
        }

        // ── Profiles remain excluded and must still be empty ─────────────────
        $profilesCount = (int) ($gd['profiles_count'] ?? -1);
        if ($profilesCount !== 0) {
            $this->error(
                "[source-checkpoint-a] FAIL: profiles source count is {$profilesCount}, expected 0."
            );
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK profiles source count: 0');
        }

        // ── role_user referential integrity from payload ───────────────────────
        $userIds   = array_column($snapshot['payload']['users'], 'id');
        $roleIds   = array_column($snapshot['payload']['roles'], 'id');
        $pairsSeen = [];

        foreach ($snapshot['payload']['role_user'] as $row) {
            if (!in_array($row['user_id'], $userIds, true)) {
                $this->error("[source-checkpoint-a] FAIL: role_user orphan user_id={$row['user_id']}.");
                $ok = false;
            }
            if (!in_array($row['role_id'], $roleIds, true)) {
                $this->error("[source-checkpoint-a] FAIL: role_user orphan role_id={$row['role_id']}.");
                $ok = false;
            }
            $pair = "{$row['user_id']}:{$row['role_id']}";
            if (isset($pairsSeen[$pair])) {
                $this->error("[source-checkpoint-a] FAIL: Duplicate role_user pair {$pair}.");
                $ok = false;
            }
            $pairsSeen[$pair] = true;
        }

        // ── Total transformed rows ────────────────────────────────────────────
        $total = (int) array_sum($snapshot['transformed_counts']);
        if ($total !== self::EXPECTED_TARGET_TOTAL) {
            $this->error(
                "[source-checkpoint-a] FAIL: Total transformed rows = {$total}, expected " .
                self::EXPECTED_TARGET_TOTAL . '.'
            );
            $ok = false;
        } else {
            $this->line('[source-checkpoint-a] OK total transformed rows: ' . self::EXPECTED_TARGET_TOTAL);
        }

        if ($ok) {
            $this->line('[source-checkpoint-a] All validations passed.');
        }
        $this->newLine();
        return $ok;
    }

    /**
     * Compare two snapshots for equality across all canonical dimensions:
     * table_hashes, source_guard_hash, source_counts, transformed_counts, transformations.
     * Returns true only when ALL dimensions match exactly.
     */
    private function checkpointsEqual(array $a, array $b): bool
    {
        // Per-table payload hashes
        if (array_keys($a['table_hashes']) !== array_keys($b['table_hashes'])) {
            return false;
        }
        foreach ($a['table_hashes'] as $table => $hashA) {
            if (($b['table_hashes'][$table] ?? null) !== $hashA) {
                return false;
            }
        }

        // Source guard fingerprint (raw pre-transformation values)
        if (($a['source_guard_hash'] ?? null) !== ($b['source_guard_hash'] ?? null)) {
            return false;
        }

        // Source counts
        foreach ($a['source_counts'] as $table => $countA) {
            if (($b['source_counts'][$table] ?? null) !== $countA) {
                return false;
            }
        }

        // Transformed counts
        foreach ($a['transformed_counts'] as $table => $countA) {
            if (($b['transformed_counts'][$table] ?? null) !== $countA) {
                return false;
            }
        }

        // Transformation counters
        foreach ($a['transformations'] as $key => $valA) {
            if (($b['transformations'][$key] ?? null) !== $valA) {
                return false;
            }
        }

        return true;
    }

    // ─── Execute helpers ──────────────────────────────────────────────────────

    /**
     * Recheck that every import target table and excluded target table contains
     * zero rows. Called after lock acquisition and before the transaction.
     */
    private function recheckAllTablesEmpty(): bool
    {
        $ok = true;

        foreach (self::IMPORT_TARGET_TABLES as $table) {
            $count = DB::connection(self::TARGET_CONN)->table($table)->count();
            if ($count !== 0) {
                $this->error(
                    "[target-emptiness-recheck] FAIL: Import table '{$table}' has {$count} rows."
                );
                $ok = false;
            }
        }

        foreach (self::EXCLUDED_SCOPE as $table) {
            $count = DB::connection(self::TARGET_CONN)->table($table)->count();
            if ($count !== 0) {
                $this->error(
                    "[target-emptiness-recheck] FAIL: Excluded table '{$table}' has {$count} rows."
                );
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Insert all payload tables into rebuild_target in fixed canonical order.
     * Every insert uses DB::connection(self::TARGET_CONN) explicitly.
     * Rows are chunked at INSERT_CHUNK_SIZE.
     * $currentStage is updated per-table so the caller can report which table failed.
     */
    private function executeInserts(array $payload, string &$currentStage): void
    {
        $insertOrder = [
            'roles',
            'users',
            'role_user',
            'reviews',
            'best_offers',
            'partners',
            'countries_images',
            'destination_images',
            'employees',
            'awards',
            'articles',
            'news',
            'our_clients',
        ];

        foreach ($insertOrder as $table) {
            $currentStage = "insert-{$table}";
            $rows = $payload[$table] ?? [];
            if (empty($rows)) {
                $this->line("[{$currentStage}] 0 rows — skipped.");
                continue;
            }

            $chunks   = array_chunk($rows, self::INSERT_CHUNK_SIZE);
            $total    = count($rows);
            $inserted = 0;

            foreach ($chunks as $chunk) {
                DB::connection(self::TARGET_CONN)->table($table)->insert($chunk);
                $inserted += count($chunk);
            }

            $this->line("[{$currentStage}] Inserted {$inserted}/{$total} rows.");
        }
    }

    /**
     * Normalize a raw target row for the given table using null-safe canonical rules.
     * Produces the same field structure as buildSourceSnapshot's payload.
     */
    private function normalizeTargetRows(string $table, array $rows): array
    {
        return match ($table) {
            'roles' => array_map(static fn($r) => [
                'id'          => (int) $r['id'],
                'name'        => (string) $r['name'],
                'description' => $r['description'] !== null ? (string) $r['description'] : null,
                'created_at'  => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'  => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at'  => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'users' => array_map(static fn($r) => [
                'id'                       => (int) $r['id'],
                'name'                     => (string) $r['name'],
                'email'                    => (string) $r['email'],
                'email_verified_at'        => $r['email_verified_at'] !== null ? (string) $r['email_verified_at'] : null,
                'password'                 => (string) $r['password'],
                'remember_token'           => $r['remember_token'],
                'password_change_required' => (int) (bool) $r['password_change_required'],
                'temp_password'            => $r['temp_password'],
                'phone'                    => $r['phone'],
                'is_active'                => (int) (bool) $r['is_active'],
                'birth_date'               => $r['birth_date'],
                'gender'                   => $r['gender'],
                'address'                  => $r['address'],
                'passport_number'          => $r['passport_number'],
                'passport_issued_date'     => $r['passport_issued_date'],
                'passport_issued_by'       => $r['passport_issued_by'],
                'notification_settings'    => $r['notification_settings'],
                'avatar_path'              => $r['avatar_path'],
                'last_login_at'            => $r['last_login_at'],
                'created_at'               => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'               => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
            ], $rows),
            'role_user' => array_map(static fn($r) => [
                'user_id'    => (int) $r['user_id'],
                'role_id'    => (int) $r['role_id'],
                'created_at' => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at' => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'reviews' => array_map(static fn($r) => [
                'id'           => (int) $r['id'],
                'name'         => (string) $r['name'],
                'title'        => $r['title'] !== null ? (string) $r['title'] : null,
                'content'      => $r['content'] !== null ? (string) $r['content'] : null,
                'image'        => $r['image'],
                'is_published' => (int) (bool) $r['is_published'],
                'created_at'   => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'   => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at'   => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'best_offers' => array_map(static fn($r) => [
                'id'         => (int) $r['id'],
                'title'      => (string) $r['title'],
                'content'    => $r['content'] !== null ? (string) $r['content'] : null,
                'image'      => $r['image'],
                'created_at' => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at' => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'partners' => array_map(static fn($r) => [
                'id'           => (int) $r['id'],
                'name_partner' => (string) $r['name_partner'],
                'logo_partner' => $r['logo_partner'],
                'created_at'   => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'   => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at'   => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'countries_images' => array_map(static fn($r) => [
                'id'            => (int) $r['id'],
                'title'         => (string) $r['title'],
                'slug'          => (string) $r['slug'],
                'category'      => $r['category'] !== null ? (string) $r['category'] : null,
                'description'   => $r['description'] !== null ? (string) $r['description'] : null,
                'image_small'   => $r['image_small'],
                'image_large'   => $r['image_large'],
                'documents_url' => $r['documents_url'],
                'created_at'    => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'    => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at'    => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'destination_images' => array_map(static fn($r) => [
                'id'          => (int) $r['id'],
                'title'       => (string) $r['title'],
                'slug'        => (string) $r['slug'],
                'description' => $r['description'] !== null ? (string) $r['description'] : null,
                'image_small' => $r['image_small'],
                'image_large' => $r['image_large'],
                'created_at'  => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'  => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at'  => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'employees' => array_map(static fn($r) => [
                'id'         => (int) $r['id'],
                'name'       => (string) $r['name'],
                'position'   => $r['position'] !== null ? (string) $r['position'] : null,
                'tel'        => $r['tel']      !== null ? (string) $r['tel']      : null,
                'email'      => $r['email']    !== null ? (string) $r['email']    : null,
                'whatsapp'   => $r['whatsapp'] !== null ? (string) $r['whatsapp'] : null,
                'vk'         => $r['vk']       !== null ? (string) $r['vk']       : null,
                'image'      => $r['image'],
                'created_at' => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at' => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'awards' => array_map(static fn($r) => [
                'id'         => (int) $r['id'],
                'image'      => $r['image'],
                'category'   => $r['category'] !== null ? (string) $r['category'] : null,
                'created_at' => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at' => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'articles' => array_map(static fn($r) => [
                'id'         => (int) $r['id'],
                'title'      => (string) $r['title'],
                'slug'       => (string) $r['slug'],
                'content'    => $r['content'] !== null ? (string) $r['content'] : null,
                'image'      => $r['image'],
                'created_at' => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at' => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'news' => array_map(static fn($r) => [
                'id'          => (int) $r['id'],
                'title'       => (string) $r['title'],
                'slug'        => (string) $r['slug'],
                'link'        => $r['link']        !== null ? (string) $r['link']        : null,
                'description' => $r['description'] !== null ? (string) $r['description'] : null,
                'image'       => $r['image'],
                'pub_date'    => $r['pub_date'] !== null ? (string) $r['pub_date'] : null,
                'created_at'  => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at'  => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at'  => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            'our_clients' => array_map(static fn($r) => [
                'id'         => (int) $r['id'],
                'title'      => (string) $r['title'],
                'slug'       => (string) $r['slug'],
                'content'    => $r['content'] !== null ? (string) $r['content'] : null,
                'image'      => $r['image'],
                'created_at' => $r['created_at'] !== null ? (string) $r['created_at'] : null,
                'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                'deleted_at' => $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            ], $rows),
            default => $rows,
        };
    }

    /**
     * Read all target import tables and produce a canonical snapshot for comparison.
     */
    private function buildTargetSnapshot(): array
    {
        $tableSpecs = [
            'roles'              => ['id', 'name', 'description', 'created_at', 'updated_at', 'deleted_at'],
            'users'              => ['id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'password_change_required', 'temp_password', 'phone', 'is_active', 'birth_date', 'gender', 'address', 'passport_number', 'passport_issued_date', 'passport_issued_by', 'notification_settings', 'avatar_path', 'last_login_at', 'created_at', 'updated_at'],
            'role_user'          => ['user_id', 'role_id', 'created_at', 'updated_at', 'deleted_at'],
            'reviews'            => ['id', 'name', 'title', 'content', 'image', 'is_published', 'created_at', 'updated_at', 'deleted_at'],
            'best_offers'        => ['id', 'title', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'partners'           => ['id', 'name_partner', 'logo_partner', 'created_at', 'updated_at', 'deleted_at'],
            'countries_images'   => ['id', 'title', 'slug', 'category', 'description', 'image_small', 'image_large', 'documents_url', 'created_at', 'updated_at', 'deleted_at'],
            'destination_images' => ['id', 'title', 'slug', 'description', 'image_small', 'image_large', 'created_at', 'updated_at', 'deleted_at'],
            'employees'          => ['id', 'name', 'position', 'tel', 'email', 'whatsapp', 'vk', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'awards'             => ['id', 'image', 'category', 'created_at', 'updated_at', 'deleted_at'],
            'articles'           => ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
            'news'               => ['id', 'title', 'slug', 'link', 'description', 'image', 'pub_date', 'created_at', 'updated_at', 'deleted_at'],
            'our_clients'        => ['id', 'title', 'slug', 'content', 'image', 'created_at', 'updated_at', 'deleted_at'],
        ];

        $tableHashes   = [];
        $rowCounts     = [];
        $idSets        = [];
        $roleUserPairs = [];
        $slugSets      = [];
        $slugTables    = ['countries_images', 'destination_images', 'articles', 'news', 'our_clients'];

        foreach ($tableSpecs as $table => $columns) {
            $query = DB::connection(self::TARGET_CONN)->table($table)->select($columns);
            if ($table === 'role_user') {
                $query->orderBy('user_id')->orderBy('role_id');
            } else {
                $query->orderBy('id');
            }

            $rawRows    = $query->get()
                ->map(static fn($r) => array_change_key_case((array) $r, CASE_LOWER))
                ->toArray();
            $normalized = $this->normalizeTargetRows($table, $rawRows);

            $tableHashes[$table] = hash('sha256', $this->canonicalJson($normalized));
            $rowCounts[$table]   = count($normalized);

            if ($table === 'role_user') {
                $roleUserPairs = array_map(
                    static fn($r) => ['user_id' => $r['user_id'], 'role_id' => $r['role_id']],
                    $normalized
                );
            } else {
                $idSets[$table] = array_column($normalized, 'id');
                sort($idSets[$table]);
            }

            if (in_array($table, $slugTables, true)) {
                $slugSets[$table] = array_column($normalized, 'slug');
                sort($slugSets[$table]);
            }
        }

        return [
            'table_hashes'    => $tableHashes,
            'row_counts'      => $rowCounts,
            'id_sets'         => $idSets,
            'role_user_pairs' => $roleUserPairs,
            'slug_sets'       => $slugSets,
        ];
    }

    /**
     * Compare a target snapshot against the source checkpoint A.
     * Checks: per-table hashes, row counts, total, ID sets, role_user pairs,
     * slug sets, excluded tables empty.
     * Returns true only when all checks pass.
     */
    private function verifyTargetSnapshotAgainstSource(
        array  $targetSnap,
        array  $checkpointA,
        string $stage
    ): bool {
        $ok             = true;
        $expectedCounts = array_merge(self::EXPECTED_SOURCE_COUNTS, ['roles' => 3]);

        // Per-table hash comparison
        foreach ($checkpointA['table_hashes'] as $table => $expectedHash) {
            $actualHash = $targetSnap['table_hashes'][$table] ?? null;
            if ($actualHash !== $expectedHash) {
                $this->error("[{$stage}] FAIL: {$table} hash mismatch.");
                $ok = false;
            } else {
                $this->line("[{$stage}] OK hash: {$table}");
            }
        }

        // Row count checks
        foreach ($expectedCounts as $table => $expectedCount) {
            $actualCount = $targetSnap['row_counts'][$table] ?? null;
            if ((int) $actualCount !== $expectedCount) {
                $this->error("[{$stage}] FAIL: {$table} count is {$actualCount}, expected {$expectedCount}.");
                $ok = false;
            }
        }

        // Total rows
        $totalRows = (int) array_sum($targetSnap['row_counts']);
        if ($totalRows !== self::EXPECTED_TARGET_TOTAL) {
            $this->error("[{$stage}] FAIL: Total rows = {$totalRows}, expected " . self::EXPECTED_TARGET_TOTAL . '.');
            $ok = false;
        } else {
            $this->line("[{$stage}] OK total rows: " . self::EXPECTED_TARGET_TOTAL);
        }

        // ID set comparison for id-based tables
        foreach ($checkpointA['payload'] as $table => $sourceRows) {
            if ($table === 'role_user') {
                continue;
            }
            $sourceIds = array_column($sourceRows, 'id');
            sort($sourceIds);
            $targetIds = $targetSnap['id_sets'][$table] ?? [];
            if ($sourceIds !== $targetIds) {
                $this->error("[{$stage}] FAIL: {$table} ID sets do not match.");
                $ok = false;
            }
        }

        // role_user pairs
        $sourcePairs = array_map(
            static fn($r) => ['user_id' => $r['user_id'], 'role_id' => $r['role_id']],
            $checkpointA['payload']['role_user']
        );
        usort($sourcePairs, static fn($a, $b) => $a['user_id'] !== $b['user_id']
            ? $a['user_id'] <=> $b['user_id'] : $a['role_id'] <=> $b['role_id']);
        $targetPairs = $targetSnap['role_user_pairs'];
        usort($targetPairs, static fn($a, $b) => $a['user_id'] !== $b['user_id']
            ? $a['user_id'] <=> $b['user_id'] : $a['role_id'] <=> $b['role_id']);
        if ($sourcePairs !== $targetPairs) {
            $this->error("[{$stage}] FAIL: role_user pairs do not match.");
            $ok = false;
        }

        // Slug sets
        foreach (['countries_images', 'destination_images', 'articles', 'news', 'our_clients'] as $table) {
            $sourceSlugs = array_column($checkpointA['payload'][$table], 'slug');
            sort($sourceSlugs);
            $targetSlugs = $targetSnap['slug_sets'][$table] ?? [];
            if ($sourceSlugs !== $targetSlugs) {
                $this->error("[{$stage}] FAIL: {$table} slug sets do not match.");
                $ok = false;
            }
        }

        // Excluded tables still empty
        foreach (self::EXCLUDED_SCOPE as $table) {
            $count = DB::connection(self::TARGET_CONN)->table($table)->count();
            if ($count !== 0) {
                $this->error("[{$stage}] FAIL: Excluded table '{$table}' has {$count} rows.");
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Verify target state inside the transaction (after inserts, before commit).
     * AUTO_INCREMENT metadata is intentionally checked only after commit because
     * information_schema may not expose the future counter for uncommitted rows.
     * Throws \RuntimeException on any failure.
     */
    private function verifyInsertedTarget(array $checkpointA): void
    {
        $this->line('[verify-target] Starting target verification...');

        $targetSnap = $this->buildTargetSnapshot();
        $ok         = $this->verifyTargetSnapshotAgainstSource($targetSnap, $checkpointA, 'verify-target');


        if (!$ok) {
            throw new \RuntimeException('[verify-target] Target verification failed after inserts.');
        }

        $this->line('[verify-target] All target verifications passed.');
    }

    /**
     * Set every real AUTO_INCREMENT counter to MAX(id) + 1 after the import
     * transaction has committed. Some MySQL configurations do not advance the
     * counter when explicit IDs are inserted inside the transaction.
     */
    private function synchronizeTargetAutoIncrementCounters(): void
    {
        foreach (array_diff(self::IMPORT_TARGET_TABLES, ['role_user']) as $table) {
            $columnRow = DB::connection(self::TARGET_CONN)->selectOne(
                'SELECT EXTRA AS extra
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?',
                [self::TARGET_DB, $table, 'id']
            );
            $columnRow = array_change_key_case((array) ($columnRow ?? []), CASE_LOWER);
            $extra     = strtolower((string) ($columnRow['extra'] ?? ''));

            if (!str_contains($extra, 'auto_increment')) {
                $this->line(
                    "[auto-increment-repair] SKIP (not declared): {$table}"
                );
                continue;
            }

            $maxId  = (int) DB::connection(self::TARGET_CONN)->table($table)->max('id');
            $nextId = max(1, $maxId + 1);

            DB::connection(self::TARGET_CONN)->statement(
                "ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}"
            );

            $this->line(
                "[auto-increment-repair] Set {$table} AUTO_INCREMENT={$nextId}."
            );
        }
    }

    /**
     * Read the current AUTO_INCREMENT counter from SHOW CREATE TABLE.
     * This avoids stale INFORMATION_SCHEMA table statistics.
     */
    private function readTargetAutoIncrementFromShowCreate(string $table): ?int
    {
        $row = DB::connection(self::TARGET_CONN)->selectOne(
            "SHOW CREATE TABLE `{$table}`"
        );
        $values    = array_values((array) ($row ?? []));
        $createSql = (string) ($values[1] ?? '');

        if (preg_match('/AUTO_INCREMENT=(\d+)/i', $createSql, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Post-commit verification using the shared buildTargetSnapshot + verifyTargetSnapshotAgainstSource helpers.
     * Also verifies role_user referential integrity, slug uniqueness, and AUTO_INCREMENT values.
     */
    private function runPostCommitVerification(array $checkpointA): bool
    {
        $this->line('[post-commit-verify] Starting post-commit verification...');

        $targetSnap = $this->buildTargetSnapshot();
        $ok         = $this->verifyTargetSnapshotAgainstSource($targetSnap, $checkpointA, 'post-commit-verify');

        // role_user referential integrity
        $orphanUser = (int) DB::connection(self::TARGET_CONN)
            ->table('role_user AS ru')
            ->leftJoin('users AS u', 'ru.user_id', '=', 'u.id')
            ->whereNull('u.id')->count();
        $orphanRole = (int) DB::connection(self::TARGET_CONN)
            ->table('role_user AS ru')
            ->leftJoin('roles AS r', 'ru.role_id', '=', 'r.id')
            ->whereNull('r.id')->count();

        if ($orphanUser > 0 || $orphanRole > 0) {
            $this->error(
                "[post-commit-verify] FAIL: role_user orphans — user={$orphanUser}, role={$orphanRole}."
            );
            $ok = false;
        } else {
            $this->line('[post-commit-verify] OK role_user referential integrity.');
        }

        // Slug uniqueness
        foreach (['countries_images', 'destination_images', 'articles', 'news', 'our_clients'] as $table) {
            $dupSlugs = (int) DB::connection(self::TARGET_CONN)
                ->table($table)->selectRaw('slug')->whereNotNull('slug')
                ->groupBy('slug')->havingRaw('COUNT(*) > 1')->get()->count();
            if ($dupSlugs > 0) {
                $this->error("[post-commit-verify] FAIL: {$table} has {$dupSlugs} duplicate slug(s).");
                $ok = false;
            }
        }

        // AUTO_INCREMENT > MAX(id), but only for tables whose id column
        // is actually declared AUTO_INCREMENT. This metadata check is
        // intentionally post-commit.
        foreach (array_diff(self::IMPORT_TARGET_TABLES, ['role_user']) as $table) {
            $columnRow = DB::connection(self::TARGET_CONN)->selectOne(
                'SELECT EXTRA AS extra
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?',
                [self::TARGET_DB, $table, 'id']
            );
            $columnRow = array_change_key_case((array) ($columnRow ?? []), CASE_LOWER);
            $extra     = strtolower((string) ($columnRow['extra'] ?? ''));

            if (!str_contains($extra, 'auto_increment')) {
                $this->line(
                    "[post-commit-verify] SKIP AUTO_INCREMENT (not declared): {$table}"
                );
                continue;
            }

            $maxId         = (int) DB::connection(self::TARGET_CONN)->table($table)->max('id');
            $autoIncrement = $this->readTargetAutoIncrementFromShowCreate($table);

            if ($autoIncrement === null || $autoIncrement <= $maxId) {
                $displayValue = $autoIncrement === null ? 'NULL' : (string) $autoIncrement;
                $this->error(
                    "[post-commit-verify] FAIL: {$table} AUTO_INCREMENT " .
                    "({$displayValue}) not > MAX(id) ({$maxId})."
                );
                $ok = false;
            } else {
                $this->line(
                    "[post-commit-verify] OK AUTO_INCREMENT > MAX(id): {$table}"
                );
            }
        }

        if ($ok) {
            $this->line('[post-commit-verify] All post-commit checks passed.');
        }
        $this->newLine();
        return $ok;
    }

    /**
     * Return all column names for a table from information_schema.
     *
     * @return string[]
     */
    private function getTableColumns(string $connection, string $database, string $table): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$database, $table]
        );
        return array_map(
            static fn($r) => array_change_key_case((array) $r, CASE_LOWER)['column_name'],
            $rows
        );
    }

    /**
     * Return column metadata from information_schema for a single column.
     * Returns null if the column is not found.
     *
     * @return array<string,mixed>|null
     */
    private function getColumnInfo(
        string $connection,
        string $database,
        string $table,
        string $column
    ): ?array {
        $row = DB::connection($connection)->selectOne(
            'SELECT COLUMN_NAME AS column_name, COLUMN_DEFAULT AS column_default
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, $column]
        );

        if ($row === null) {
            return null;
        }

        return array_change_key_case((array) $row, CASE_LOWER);
    }

    /**
     * Release the named MySQL lock on the rebuild_target connection.
     * Safe to call even if lock was not acquired (guarded by $this->namedLockAcquired).
     */
    private function releaseLock(): void
    {
        if (!$this->namedLockAcquired) {
            return;
        }

        try {
            $releaseRow = array_change_key_case(
                (array) DB::connection(self::TARGET_CONN)
                    ->selectOne('SELECT RELEASE_LOCK(?) AS result', [self::NAMED_LOCK]),
                CASE_LOWER
            );
            $releaseResult = $releaseRow['result'] ?? null;

            if ((int) $releaseResult === 1) {
                $this->lockReleaseConfirmed = true;
                $this->line('[lock-release] Named lock released.');
            } else {
                // Result 0 or null — check if lock is free
                $freeRow = array_change_key_case(
                    (array) DB::connection(self::TARGET_CONN)
                        ->selectOne('SELECT IS_FREE_LOCK(?) AS result', [self::NAMED_LOCK]),
                    CASE_LOWER
                );
                if ((int) ($freeRow['result'] ?? 0) === 1) {
                    $this->lockReleaseConfirmed = true;
                    $this->line('[lock-release] Lock confirmed free (IS_FREE_LOCK=1).');
                } else {
                    $this->error('[lock-release] Could not confirm lock release. Manual review required.');
                }
            }
        } catch (\Throwable $e) {
            $this->error('[lock-release] Exception: ' . get_class($e));
        }
    }

    /**
     * Verify all import target tables and excluded tables are empty.
     * Used after a rollback to confirm clean state.
     */
    private function verifyAllTablesEmpty(): bool
    {
        $ok = true;

        foreach (self::IMPORT_TARGET_TABLES as $table) {
            try {
                $count = DB::connection(self::TARGET_CONN)->table($table)->count();
                if ($count !== 0) {
                    $this->error(
                        "[rollback-check] FAIL: '{$table}' has {$count} rows after rollback."
                    );
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $this->error('[rollback-check] Exception checking table: ' . get_class($e));
                $ok = false;
            }
        }

        foreach (self::EXCLUDED_SCOPE as $table) {
            try {
                $count = DB::connection(self::TARGET_CONN)->table($table)->count();
                if ($count !== 0) {
                    $this->error(
                        "[rollback-check] FAIL: excluded '{$table}' has {$count} rows."
                    );
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $this->error('[rollback-check] Exception: ' . get_class($e));
                $ok = false;
            }
        }

        if ($ok) {
            $this->line('[rollback-check] Rollback verified: all import and excluded tables empty.');
        }

        return $ok;
    }

    /**
     * Normalize a timestamp-like value to 'Y-m-d H:i:s'.
     * Returns null for null or empty input.
     */
    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = (string) $value;

        try {
            return (new \DateTime($str))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $str;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Check that every table in $tables exists in $database using a read-only
     * information_schema query on $connection.
     */
    private function checkTablesExist(
        string $connection,
        string $database,
        array  $tables,
        string $label
    ): bool {
        $placeholders = implode(',', array_fill(0, count($tables), '?'));

        $existing = DB::connection($connection)->select(
            "SELECT TABLE_NAME AS table_name
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME IN ({$placeholders})",
            array_merge([$database], $tables)
        );

        $existingNames = array_map(
            static fn($r) => array_change_key_case((array) $r, CASE_LOWER)['table_name'],
            $existing
        );

        $ok = true;

        foreach ($tables as $table) {
            if (!in_array($table, $existingNames, true)) {
                $this->error(
                    "  FAIL [{$label}]: Table '{$table}' not found in {$database}."
                );
                $ok = false;
            } else {
                $this->line("  OK [{$label}] {$table}");
            }
        }

        $this->newLine();
        return $ok;
    }

    /**
     * Return [blankCount, whitespaceCount, duplicateGroupCount] for the slug column.
     *
     * blankCount      = rows where value is null or PHP trim((string) $value) === ''.
     * whitespaceCount = rows where value is not null, trim((string) $value) is not empty,
     *                   and trim((string) $value) !== (string) $value.
     * duplicateGroupCount = count of slug values that appear more than once
     *                       (evaluated against the stored, not trimmed, value).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function checkSlugIntegrity(string $connection, string $table): array
    {
        $slugValues = DB::connection($connection)
            ->table($table)
            ->pluck('slug')
            ->toArray();

        $blankCount      = 0;
        $whitespaceCount = 0;

        foreach ($slugValues as $slugValue) {
            if ($slugValue === null || trim((string) $slugValue) === '') {
                $blankCount++;
            } elseif (trim((string) $slugValue) !== (string) $slugValue) {
                $whitespaceCount++;
            }
        }

        $dupGroupCount = DB::connection($connection)
            ->table($table)
            ->select('slug')
            ->whereNotNull('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [$blankCount, $whitespaceCount, $dupGroupCount];
    }

    /**
     * Count rows in $table where $column changes after PHP trim().
     * trim() removes CR, LF, tabs, and surrounding whitespace.
     */
    private function countTrimNormalization(
        string $connection,
        string $table,
        string $column
    ): int {
        $values = DB::connection($connection)
            ->table($table)
            ->whereNotNull($column)
            ->pluck($column)
            ->toArray();

        $count = 0;

        foreach ($values as $value) {
            if (trim((string) $value) !== (string) $value) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Log a guard failure, set the failure flag, and return false.
     */
    private function failGuard(string $message): bool
    {
        $this->error("  FAIL: {$message}");
        $this->newLine();
        $this->hadFailure = true;
        return false;
    }

    /**
     * Purge both runtime connections, restore the original config arrays,
     * and verify the restoration. Always called from the finally block.
     *
     * Verifications performed:
     * - temporary connection keys are no longer present in config;
     * - config('database.default') equals $originalDefault;
     * - SELECT DATABASE() through the restored default connection returns turfirma_dev.
     *
     * Returns true only when all verifications succeed.
     * In execute mode, exception messages are sanitized to class name only.
     */
    private function cleanupRuntimeConnections(
        array $originalConnections,
        string $originalDefault
    ): bool {
        $this->newLine();
        $this->line('-- Cleanup -----------------------------------------------');

        $sanitize = $this->executeModeActive;
        $ok       = true;

        foreach ([self::LEGACY_CONN, self::TARGET_CONN] as $conn) {
            try {
                DB::purge($conn);
                $this->line("  purged {$conn}");
            } catch (\Throwable $e) {
                $msg = $sanitize ? get_class($e) : $e->getMessage();
                $this->error("  FAIL: Could not purge connection {$conn}: {$msg}");
                $ok = false;
            }
        }

        Config::set('database.connections', $originalConnections);
        Config::set('database.default', $originalDefault);

        // Verify the complete connections config was restored to the original array
        if (config('database.connections') !== $originalConnections) {
            $this->error(
                '  FAIL: database.connections config does not match the saved original after restore.'
            );
            $ok = false;
        } else {
            $this->line('  OK database.connections config fully restored.');
        }

        // Verify temporary connection keys are no longer present
        foreach ([self::LEGACY_CONN, self::TARGET_CONN] as $conn) {
            if (config("database.connections.{$conn}") !== null) {
                $this->error(
                    "  FAIL: Temporary connection '{$conn}' still present after restore."
                );
                $ok = false;
            }
        }

        // Verify the default connection name equals the original value
        $restoredDefault = config('database.default');
        if ($restoredDefault !== $originalDefault) {
            $this->error(
                "  FAIL: Default connection restoration failed. " .
                "Expected '{$originalDefault}', got '{$restoredDefault}'."
            );
            $ok = false;
        } else {
            $this->line("  OK default connection restored : {$restoredDefault}");
        }
        // Final read-only SELECT DATABASE() through the restored default connection
        try {
            $row = DB::connection($originalDefault)->selectOne('SELECT DATABASE() AS db');
            $row = array_change_key_case((array) $row, CASE_LOWER);
            $activeDb = $row['db'] ?? null;

            if ($activeDb !== self::LEGACY_DB) {
                $this->error(
                    "  FAIL: After cleanup, active database on restored connection " .
                    "'{$originalDefault}' is '{$activeDb}', " .
                    "expected '" . self::LEGACY_DB . "'."
                );
                $ok = false;
            } else {
                $this->line(
                    "  OK active database on restored connection : {$activeDb}"
                );
            }
        } catch (\Throwable $e) {
            $msg = $sanitize ? get_class($e) : $e->getMessage();
            $this->error(
                '  FAIL: Cannot verify active database after cleanup: ' . $msg
            );
            $ok = false;
        }

        $this->newLine();
        return $ok;
    }

    /**
     * Verify that $absolutePath is an actual file with exactly the casing as
     * stored on disk, by traversing every segment of the path relative to
     * $publicBase using scandir() and strict in_array().
     *
     * Returns false for paths containing a null byte or a ".." segment.
     * Returns false if is_file() fails on $absolutePath.
     * Returns false if any path segment's exact case is not found in its
     * parent directory's listing.
     */
    private function verifyExactPathCase(string $absolutePath, string $publicBase): bool
    {
        // Reject unsafe input
        if (str_contains($absolutePath, "\0")) {
            return false;
        }

        // Normalise separators for portable string operations
        $normBase = rtrim(str_replace('\\', '/', $publicBase), '/');
        $normPath = str_replace('\\', '/', $absolutePath);

        // Extract path relative to publicBase
        if (str_starts_with($normPath, $normBase . '/')) {
            $relPart = substr($normPath, strlen($normBase) + 1);
        } else {
            $relPart = ltrim($normPath, '/');
        }

        $segments = array_values(array_filter(
            explode('/', $relPart),
            static fn(string $s): bool => $s !== ''
        ));

        // Reject ".." before any I/O
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        if (!is_file($absolutePath)) {
            return false;
        }

        // Walk each segment, verifying exact case via scandir()
        $current = rtrim($publicBase, '/\\');

        foreach ($segments as $segment) {
            $entries = @scandir($current);
            if ($entries === false) {
                return false;
            }
            if (!in_array($segment, $entries, true)) {
                // Case mismatch or segment not found
                return false;
            }
            $current .= DIRECTORY_SEPARATOR . $segment;
        }

        return true;
    }
}
