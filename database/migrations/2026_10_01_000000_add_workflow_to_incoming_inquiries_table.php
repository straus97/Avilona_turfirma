<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E5-A3: бизнес-процесс обработки входящего обращения.
 *
 * Отделён от технического состояния загрузки (incoming_inquiries.state):
 * workflow_state отвечает на вопрос «что делает с обращением менеджер»,
 * state — «загружены ли данные из внешнего провайдера».
 *
 * Связь с Booking — только со стороны обращения (booking_id, уникальный):
 * одно обращение даёт не более одной заявки, а сама заявка не знает о
 * внешнем провайдере.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_inquiries', function (Blueprint $table) {
            // new | in_progress | converted | closed
            $table->string('workflow_state', 16)->default('new')->after('state');

            // Ответственный сотрудник (взято в работу).
            $table->foreignId('assigned_to')->nullable()->after('workflow_state')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('assigned_to');

            // Явное решение сотрудника: какой аккаунт туриста относится к обращению.
            $table->foreignId('client_user_id')->nullable()->after('claimed_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('client_linked_by')->nullable()->after('client_user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('client_linked_at')->nullable()->after('client_linked_by');

            // Созданная по обращению заявка Avilona (не более одной).
            $table->foreignId('booking_id')->nullable()->unique()->after('client_linked_at')
                ->constrained('bookings')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('booking_id');
            $table->foreignId('converted_by')->nullable()->after('converted_at')
                ->constrained('users')->nullOnDelete();

            // Закрытие без бронирования.
            $table->timestamp('closed_at')->nullable()->after('converted_by');
            $table->foreignId('closed_by')->nullable()->after('closed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('close_reason', 500)->nullable()->after('closed_by');

            $table->index('workflow_state');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_inquiries', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['client_user_id']);
            $table->dropForeign(['client_linked_by']);
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['converted_by']);
            $table->dropForeign(['closed_by']);
            $table->dropIndex(['workflow_state']);
            $table->dropUnique(['booking_id']);
            $table->dropColumn([
                'workflow_state', 'assigned_to', 'claimed_at',
                'client_user_id', 'client_linked_by', 'client_linked_at',
                'booking_id', 'converted_at', 'converted_by',
                'closed_at', 'closed_by', 'close_reason',
            ]);
        });
    }
};
