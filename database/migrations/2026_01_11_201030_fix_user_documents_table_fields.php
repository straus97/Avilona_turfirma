<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_documents', function (Blueprint $table) {
            // Проверяем и удаляем старые колонки если они существуют
            if (Schema::hasColumn('user_documents', 'document_type')) {
                $table->dropColumn('document_type');
            }
            if (Schema::hasColumn('user_documents', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('user_documents', 'uploaded_at')) {
                $table->dropColumn('uploaded_at');
            }
            if (Schema::hasColumn('user_documents', 'version')) {
                $table->dropColumn('version');
            }
            if (Schema::hasColumn('user_documents', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('user_documents', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
            
            // Добавляем новые колонки если их нет
            if (!Schema::hasColumn('user_documents', 'name')) {
                $table->string('name')->after('user_id');
            }
            if (!Schema::hasColumn('user_documents', 'file_type')) {
                $table->string('file_type')->nullable()->after('file_path');
            }
            
            // Изменяем тип file_size если нужно
            $table->unsignedBigInteger('file_size')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_documents', function (Blueprint $table) {
            // Возвращаем обратно (если нужно)
            if (Schema::hasColumn('user_documents', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('user_documents', 'file_type')) {
                $table->dropColumn('file_type');
            }
        });
    }
};
