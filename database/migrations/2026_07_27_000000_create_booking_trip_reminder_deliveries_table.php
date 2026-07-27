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
        Schema::create('booking_trip_reminder_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('reminder_days');
            $table->date('trip_start_date');
            $table->foreignId('recipient_user_id')->constrained('users')->onDelete('cascade');
            $table->string('recipient_email');
            $table->timestamp('claimed_at');
            $table->timestamp('queued_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['booking_id', 'reminder_days', 'trip_start_date'],
                'btrd_booking_days_date_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_trip_reminder_deliveries');
    }
};
