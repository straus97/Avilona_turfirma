<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Dry-run selective import audit from turfirma_dev to turfirma_rebuild_v4.
 *
 * This command is STRICTLY READ-ONLY. It never executes INSERT, UPDATE, DELETE,
 * TRUNCATE, ALTER, CREATE, DROP, REPLACE, migrations, or seeders.
 * No Eloquent models are used for database inspection to prevent slug generation,
 * casts, mutators, observers, and model events from modifying source values.
 */
class ImportLegacyDataToV4 extends Command
{
    protected $signature = 'legacy:import-v4';

    protected $description = 'Dry-run selective import audit from turfirma_dev to turfirma_rebuild_v4';

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

    // ─── State ────────────────────────────────────────────────────────────────

    private bool $hadFailure = false;

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->renderHeader();

        $originalConnections = config('database.connections');
        $originalDefault     = config('database.default');

        $inspectionCompleted = false;
        $cleanupOk           = false;

        try {
            do {
                // ── Hard guard chain ──────────────────────────────────────────

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

                // ── Read-only inspections ─────────────────────────────────────

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

                $inspectionCompleted = true;

            } while (false);

        } catch (\Throwable $e) {
            $this->error('Unexpected exception: ' . $e->getMessage());
        } finally {
            $cleanupOk = $this->cleanupRuntimeConnections($originalConnections, $originalDefault);
        }

        $this->line('-- [18] Final verdict ------------------------------------');

        if (!$inspectionCompleted || $this->hadFailure || !$cleanupOk) {
            $this->error('  DRY-RUN VERDICT: FAILED');
            if (!$cleanupOk) {
                $this->error('  Cleanup or connection restoration failed.');
            }
            if (!$inspectionCompleted) {
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

    // ─── Header ───────────────────────────────────────────────────────────────

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
        $this->info('  - The project is ready for a separately reviewed');
        $this->info('    execute-stage implementation.');
        $this->info('==========================================================');
        $this->newLine();
        return Command::SUCCESS;
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
     */
    private function cleanupRuntimeConnections(
        array  $originalConnections,
        string $originalDefault
    ): bool {
        $this->newLine();
        $this->line('-- Cleanup -----------------------------------------------');

        $ok = true;

        foreach ([self::LEGACY_CONN, self::TARGET_CONN] as $conn) {
            try {
                DB::purge($conn);
                $this->line("  purged {$conn}");
            } catch (\Throwable $e) {
                $this->error("  FAIL: Could not purge connection {$conn}: " . $e->getMessage());
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
            $this->error(
                '  FAIL: Cannot verify active database after cleanup: ' .
                $e->getMessage()
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
