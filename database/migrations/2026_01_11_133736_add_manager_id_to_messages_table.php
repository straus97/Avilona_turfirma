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
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('receiver_id')->constrained('users')->onDelete('set null');
            $table->index(['booking_id', 'manager_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['booking_id', 'manager_id']);
            $table->dropColumn('manager_id');
        });
    }
};
