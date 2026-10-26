<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-04-I4: непроверенный контакт клиента, принадлежащий обращению (Inquiry), — EXPAND.
 *
 * Только новая таблица (аддитивно): inquiries и существующие данные не меняются, перенос или
 * догрузка исторических записей не выполняется. Строка 1:1 с обращением (первичный ключ — само
 * inquiry_id), контактов нет у обращений, для которых их ещё не вводили.
 *
 * Допустимые поля утверждены владельцем: отображаемое имя (до 120), телефон для обратной связи
 * (до 20), контакт в мессенджере (до 120, свободный текст). Все необязательны. Email, паспорт,
 * дата рождения и адрес здесь НЕТ и быть не должно. Это НЕ учётная запись и НЕ проверенный
 * турист: связь с аккаунтом — только явная (inquiries.client_user_id).
 *
 * revision — монотонная версия контакта в пределах обращения. Строка НИКОГДА не удаляется
 * приложением: «очистка» обнуляет три поля и увеличивает revision (строка-«надгробие»), поэтому
 * счётчик не сбрасывается и устаревшая форма, открытая до очистки, не может стать валидной после
 * повторного ввода (защита от ABA). Строка исчезает только вместе с обращением (cascade).
 *
 * Индексов по имени/телефону нет намеренно: поиска, дедупликации и сопоставления по контактам
 * не существует. Срок хранения остаётся ОТКРЫТЫМ условием выпуска (R-B/U-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_contacts', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->primary()->constrained('inquiries')->cascadeOnDelete();

            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_messenger', 120)->nullable();

            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_contacts');
    }
};
