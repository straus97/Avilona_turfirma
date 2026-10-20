<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-01-I1: минимальный append-only журнал бизнес-аудита.
 *
 * Только новая таблица (аддитивно), без изменения существующих. Историю не
 * восстанавливаем и не выдумываем: журнал изначально пуст.
 *
 * actor_id намеренно БЕЗ внешнего ключа: удаление пользователя не должно ни
 * каскадно удалять, ни обнулять (UPDATE) строки журнала. subject_type — стабильное
 * доменное имя (например `incoming_inquiry`), а не имя PHP-класса.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id')->nullable();

            // Небольшой проверенный набор скалярных значений; см. AuditMetadata.
            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');

            $table->index('event_type');
            $table->index('actor_id');
            $table->index(['subject_type', 'subject_id']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
