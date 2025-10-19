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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            
            // Индексы для оптимизации
            $table->index('phone');
            $table->index('is_active');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_login_at']);
            
            $table->dropColumn(['phone', 'is_active', 'last_login_at']);
        });
    }
};
