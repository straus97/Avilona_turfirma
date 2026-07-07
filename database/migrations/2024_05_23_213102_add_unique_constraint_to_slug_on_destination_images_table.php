<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    // The preceding migration (2024_05_23_212821) already creates the slug column
    // in its final non-null unique form: string('slug')->unique()->after('title').
    // This migration intentionally owns no schema changes and exists solely
    // to preserve migration-history continuity.

    public function up(): void
    {
        // no-op: slug uniqueness is established in migration 212821
    }

    public function down(): void
    {
        // no-op: nothing was changed here
    }
};
