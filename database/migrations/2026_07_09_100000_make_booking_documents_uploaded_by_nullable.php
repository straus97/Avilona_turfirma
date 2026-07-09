<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(true);

            return;
        }

        DB::statement(
            'ALTER TABLE `booking_documents`
             DROP FOREIGN KEY `booking_documents_uploaded_by_foreign`'
        );

        DB::statement(
            'ALTER TABLE `booking_documents`
             MODIFY `uploaded_by` BIGINT UNSIGNED NULL'
        );

        DB::statement(
            'ALTER TABLE `booking_documents`
             ADD CONSTRAINT `booking_documents_uploaded_by_foreign`
             FOREIGN KEY (`uploaded_by`)
             REFERENCES `users` (`id`)
             ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        if (
            DB::table('booking_documents')
                ->whereNull('uploaded_by')
                ->exists()
        ) {
            throw new \RuntimeException(
                'Cannot restore booking_documents.uploaded_by to NOT NULL while null values exist.'
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(false);

            return;
        }

        DB::statement(
            'ALTER TABLE `booking_documents`
             DROP FOREIGN KEY `booking_documents_uploaded_by_foreign`'
        );

        DB::statement(
            'ALTER TABLE `booking_documents`
             MODIFY `uploaded_by` BIGINT UNSIGNED NOT NULL'
        );

        DB::statement(
            'ALTER TABLE `booking_documents`
             ADD CONSTRAINT `booking_documents_uploaded_by_foreign`
             FOREIGN KEY (`uploaded_by`)
             REFERENCES `users` (`id`)'
        );
    }

    private function rebuildSqliteTable(
        bool $uploaderIsNullable
    ): void {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::create(
                'booking_documents_rebuild',
                function (
                    Blueprint $table
                ) use (
                    $uploaderIsNullable
                ): void {
                    $table->id();

                    $table->foreignId('booking_id')
                        ->constrained()
                        ->cascadeOnDelete();

                    $table->enum('document_type', [
                        'contract',
                        'voucher',
                        'tickets',
                        'insurance',
                        'instructions',
                        'other',
                    ]);

                    $table->string('title');
                    $table->string('file_path');
                    $table->integer('file_size')->nullable();

                    if ($uploaderIsNullable) {
                        $table->foreignId('uploaded_by')
                            ->nullable()
                            ->constrained('users')
                            ->nullOnDelete();
                    } else {
                        $table->foreignId('uploaded_by')
                            ->constrained('users');
                    }

                    $table->timestamp('uploaded_at')
                        ->useCurrent();

                    $table->timestamps();
                    $table->softDeletes();
                }
            );

            DB::statement(
                'INSERT INTO booking_documents_rebuild (
                    id,
                    booking_id,
                    document_type,
                    title,
                    file_path,
                    file_size,
                    uploaded_by,
                    uploaded_at,
                    created_at,
                    updated_at,
                    deleted_at
                )
                SELECT
                    id,
                    booking_id,
                    document_type,
                    title,
                    file_path,
                    file_size,
                    uploaded_by,
                    uploaded_at,
                    created_at,
                    updated_at,
                    deleted_at
                FROM booking_documents'
            );

            Schema::drop('booking_documents');

            Schema::rename(
                'booking_documents_rebuild',
                'booking_documents'
            );

            Schema::table(
                'booking_documents',
                function (Blueprint $table): void {
                    $table->index([
                        'booking_id',
                        'document_type',
                    ]);
                }
            );
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};