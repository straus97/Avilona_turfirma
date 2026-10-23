<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-01-I7: запросы менеджера на проверку клиента (решение владельца по I3-F3, вариант C).
 *
 * Менеджер не создаёт аккаунты туристов сам и не узнаёт, существует ли email:
 * он передаёт данные клиента администратору, а тот явно привязывает существующий
 * аккаунт или одобряет создание нового. Только новая таблица (аддитивно).
 *
 * pending_key — уникальный ключ ТОЛЬКО пока запрос на проверке (сбрасывается в NULL
 * при решении): защита от дублей и повторной отправки на уровне БД. В таблице нет
 * паролей, токенов и сведений о том, существовал ли аккаунт ранее.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_verification_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            // booking_form | incoming_inquiry
            $table->string('context_type', 24);
            $table->foreignId('incoming_inquiry_id')->nullable()
                ->constrained('incoming_inquiries')->nullOnDelete();

            // Данные, нужные администратору для решения (email хранится в нижнем регистре).
            $table->string('client_name', 255);
            $table->string('client_email', 255)->nullable();
            $table->string('client_phone', 20)->nullable();

            // pending | approved | rejected
            $table->string('status', 16)->default('pending');
            $table->char('pending_key', 64)->nullable()->unique();

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            // linked_existing | created_new | rejected — видно только администратору.
            $table->string('resolution', 24)->nullable();
            $table->foreignId('resolved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 300)->nullable();

            // Срок рабочего доступа менеджера к подтверждённому клиенту (до первой заявки/обращения).
            $table->timestamp('access_expires_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_verification_requests');
    }
};
