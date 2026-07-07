<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Intentionally empty.
     *
     * The canonical 2026_01_11_133734_create_user_documents_table migration
     * already defines name (string), file_type (nullable string) and
     * file_size (nullable unsignedBigInteger) correctly from the start.
     * The document_type column is handled by the later
     * 2026_01_11_203144_add_document_type_to_user_documents_table migration.
     * Nothing needs to be added, dropped or changed here on a fresh install.
     */
    public function up(): void
    {
        // no-op
    }

    /**
     * Intentionally empty.
     *
     * This migration no longer owns any schema changes, so there is
     * nothing to reverse.
     */
    public function down(): void
    {
        // no-op
    }
};
