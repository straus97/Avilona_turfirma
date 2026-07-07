<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    // No-op: the preceding add_slug migration (2024_05_23_220006) now creates
    // the slug column in its final non-null unique form. This migration owns
    // no schema changes and is retained only for migration-history continuity.

    public function up(): void
    {
        // intentionally empty
    }

    public function down(): void
    {
        // intentionally empty
    }
};
