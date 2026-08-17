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
        Schema::table('review_consents', function (Blueprint $table) {
            $table->timestamp('publication_conditions_satisfied_at')->nullable()->after('publication_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_consents', function (Blueprint $table) {
            $table->dropColumn('publication_conditions_satisfied_at');
        });
    }
};
