<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Добавляем новое поле name
            $table->string('name')->after('id')->nullable();
            // Добавляем поле description
            $table->text('description')->nullable()->after('role');
        });

        // Копируем данные из role в name
        DB::statement('UPDATE roles SET name = role WHERE name IS NULL');

        // Делаем name обязательным и уникальным
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name')->nullable(false)->unique()->change();
        });

        // Удаляем старое поле role
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Возвращаем поле role
            $table->string('role')->after('name')->nullable();
        });

        // Копируем данные обратно
        DB::statement('UPDATE roles SET role = name WHERE role IS NULL');

        // Удаляем новые поля
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });
    }
};
