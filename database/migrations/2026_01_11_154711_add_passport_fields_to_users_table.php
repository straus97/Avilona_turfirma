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
            $table->date('birth_date')->nullable()->after('phone');
            $table->enum('gender', ['male', 'female'])->nullable()->after('birth_date');
            $table->text('address')->nullable()->after('gender');
            $table->string('passport_number')->nullable()->after('address');
            $table->date('passport_issued_date')->nullable()->after('passport_number');
            $table->string('passport_issued_by')->nullable()->after('passport_issued_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['passport_number', 'passport_issued_date', 'passport_issued_by', 'birth_date', 'gender', 'address']);
        });
    }
};
