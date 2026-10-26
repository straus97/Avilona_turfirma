<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-04-I4: запрос на проверку клиента может быть подан из самостоятельного обращения агентства
 * (Inquiry) — третий контекст рядом с booking_form и incoming_inquiry. EXPAND: одна необязательная
 * колонка, существующие строки и их поведение не меняются, перенос данных не выполняется.
 *
 * Колонка называется agency_inquiry_id, чтобы не путать с incoming_inquiry_id (внешнее поступление
 * Tourvisor). nullOnDelete: удаление обращения не удаляет запрос, но делает его неактуальным
 * (контекст проверяется при решении). context_type — обычная строка, значение 'inquiry' миграции
 * словаря не требует.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_verification_requests', function (Blueprint $table) {
            $table->foreignId('agency_inquiry_id')->nullable()->after('incoming_inquiry_id')
                ->constrained('inquiries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_verification_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_inquiry_id');
        });
    }
};
