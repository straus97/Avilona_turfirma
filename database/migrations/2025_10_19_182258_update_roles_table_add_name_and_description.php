<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * No-op: the canonical name/description schema is now created directly
     * in 2023_05_14_211945_create_roles_table.php. This file is kept for
     * migration-history continuity only.
     */
    public function up(): void
    {
        // intentionally empty
    }

    /**
     * No-op: nothing was changed by up().
     */
    public function down(): void
    {
        // intentionally empty
    }
};
