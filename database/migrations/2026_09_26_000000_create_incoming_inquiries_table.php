<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E5-A2A: минимальное хранилище входящих обращений внешних провайдеров.
 *
 * Это НЕ бронирование: обращение не связано с пользователем/Booking и не
 * меняет деньги или статусы. Набор колонок сводки — только поля публичного
 * примера экспорта Tourvisor; остальное лежит в ограниченном vendor_payload
 * до сверки с живым ответом (E5-A2B, normalizer_version).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_inquiries', function (Blueprint $table) {
            $table->id();

            // Идентичность: provider + тип + внешний ID (гарантия идемпотентности на уровне БД).
            $table->string('provider', 32);
            $table->unsignedTinyInteger('external_type');
            $table->string('external_id', 64);

            // pending | importing | imported | failed | unsupported
            $table->string('state', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('webhook_count')->default(1);
            $table->timestamp('first_notified_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('last_error_code', 32)->nullable();
            $table->timestamp('last_error_at')->nullable();

            // Сводка из авторитетной выгрузки (все необязательны).
            $table->string('client_name')->nullable();
            $table->string('client_phone', 64)->nullable();
            $table->string('client_email')->nullable();
            $table->text('client_comment')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('operator_name', 128)->nullable();
            $table->string('departure_city', 128)->nullable();
            $table->string('destination_country', 128)->nullable();
            $table->string('hotel_name')->nullable();
            $table->date('fly_date')->nullable();
            $table->unsignedSmallInteger('nights')->nullable();

            // Ограниченный снимок ответа Tourvisor (без ключей/заголовков) и версия правил.
            $table->json('vendor_payload')->nullable();
            $table->unsignedSmallInteger('normalizer_version')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'external_type', 'external_id'], 'incoming_inquiries_identity_unique');
            $table->index('state');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_inquiries');
    }
};
