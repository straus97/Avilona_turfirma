<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AOA-04-I1: канал обращения клиента и токен повторной отправки формы создания.
 *
 * Только аддитивные необязательные колонки (inquiries пуста для реальных данных — чистый
 * старт, AOA-03-I7), существующие столбцы и данные не меняются.
 *
 * contact_channel — КАК клиент связался с агентством (phone | messenger). Это не origin:
 * origin говорит, КТО завёл запись (менеджер / администратор / внешнее поступление). Словарь
 * закрытый и проверяется моделью и сервисом создания; провайдерских идентификаторов,
 * содержимого переписки и учётных данных мессенджеров здесь нет и быть не должно.
 *
 * creation_token — случайный идентификатор показа формы создания (UUID). Уникальность в
 * паре с создателем превращает повторную отправку той же формы (двойной клик, повтор
 * браузера) в возврат уже созданного обращения, а не в дубликат. Это не секрет и не данные
 * клиента; для внешних обращений остаётся NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('contact_channel', 16)->nullable()->after('origin');
            $table->string('creation_token', 36)->nullable()->after('contact_channel');

            $table->unique(['created_by', 'creation_token'], 'inquiries_creator_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // Внешний ключ created_by опирается на собственный индекс inquiries_created_by_index.
            $table->dropUnique('inquiries_creator_token_unique');
            $table->dropColumn(['contact_channel', 'creation_token']);
        });
    }
};
