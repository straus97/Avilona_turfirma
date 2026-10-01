<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E5-A3.1: в users.temp_password прежний код хранил пароли клиентов открытым
 * текстом. Приложение больше ничего туда не пишет — очищаем накопленные значения.
 *
 * Колонка остаётся (nullable): её ожидает схема импорта (ImportLegacyDataToV4).
 * users.password (хеш), password_change_required и остальные поля не меняются.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('temp_password')
            ->update(['temp_password' => null]);
    }

    public function down(): void
    {
        // Открытые пароли восстановить нельзя и не нужно.
    }
};
