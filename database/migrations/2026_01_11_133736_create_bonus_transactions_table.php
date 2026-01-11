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
        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_account_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'earn' or 'spend'
            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->timestamps();
            
            $table->index(['bonus_account_id', 'type']);
            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_transactions');
    }
};
