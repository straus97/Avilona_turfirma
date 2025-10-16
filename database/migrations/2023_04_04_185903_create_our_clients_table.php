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
        Schema::create('our_clients', function (Blueprint $table) {
            $table->id();
            $table->string('title'); //255 символов
            $table->text('content'); //большой текст
            $table->string('image')->nullable(); //вывод фотки, записывается url
            $table->timestamps();

            $table->softDeletes(); //"мягкое" удаление, тое сть отображаться не будет, но данные можно будет восстановить
        });
    }

    /**
     * Reverse the migrations.
     */
    //функция для устранения проблем в таблице - удаление, исправление и тд.
    public function down(): void
    {
        Schema::dropIfExists('our_clients'); //убить таблицу полностью, которая написана выше
    }
};
