<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-03-I1: независимое от провайдера обращение агентства (Inquiry) — фундамент EXPAND.
 *
 * Только новая таблица (аддитивно): incoming_inquiries, bookings и users не меняются,
 * существующие записи НЕ переносятся (backfill — отдельный срез AOA-03-I2, а
 * runtime по-прежнему работает с IncomingInquiry).
 *
 * Происхождение (provenance): внешний провайдер и его идентификаторы остаются ТОЛЬКО в
 * incoming_inquiries (provider + external_type + string external_id). Здесь они не
 * копируются, поэтому ведущие нули и тип не могут исказиться; связь — уникальный
 * incoming_inquiry_id (одно бизнес-обращение на одно входящее). Внешний ключ RESTRICT:
 * источник нельзя удалить, пока по нему есть бизнес-обращение.
 *
 * Связь с Booking (booking_id, уникальный) — тот же контракт, что у IncomingInquiry:
 * заявка не создаётся неявно, колонка заполняется только явным оформлением (AOA-05).
 *
 * Черновик — типизированные ограниченные колонки (без свободного JSON, кроме списка
 * возрастов детей) и без контактов клиента: контакты непроверенного клиента живут в
 * client_verification_requests, а не здесь. Паролей, токенов и паспортных данных нет.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();

            // manager_created | admin_created | external_intake
            $table->string('origin', 24);
            // new | in_progress | converted | closed
            $table->string('status', 16)->default('new');

            // Источник внешнего обращения (NULL у созданных вручную).
            $table->foreignId('incoming_inquiry_id')->nullable()->unique()
                ->constrained('incoming_inquiries')->restrictOnDelete();

            // Кто создал (NULL у внешнего обращения) и кто отвечает сейчас.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // Явное решение сотрудника: какой аккаунт туриста относится к обращению.
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('client_linked_at')->nullable();

            // Оформленная заявка (не более одной) — только явная конверсия.
            $table->foreignId('booking_id')->nullable()->unique()
                ->constrained('bookings')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();

            // Закрытие без бронирования.
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('close_reason', 500)->nullable();

            // Черновик поездки (все поля необязательны; границы проверяет InquiryDraftData).
            $table->string('destination_country', 128)->nullable();
            $table->string('destination_city', 128)->nullable();
            $table->string('departure_city', 128)->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedTinyInteger('nights_min')->nullable();
            $table->unsignedTinyInteger('nights_max')->nullable();
            $table->unsignedTinyInteger('adults')->nullable();
            $table->unsignedTinyInteger('children')->nullable();
            $table->json('children_ages')->nullable();
            $table->string('preferences', 1000)->nullable();
            $table->text('internal_notes')->nullable();

            // Оптимистичная версия черновика: сохранение с устаревшей версией отклоняется.
            $table->unsignedInteger('draft_revision')->default(0);
            $table->foreignId('draft_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('draft_updated_at')->nullable();

            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index('status');
            $table->index('origin');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
