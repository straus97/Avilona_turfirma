<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name'); //255 символов
            $table->text('content'); //большой текст
            $table->string('image')->nullable(); //вывод аватарки, записывается url
            $table->boolean('is_published')->default(0); //логическая переменная, выпущен контент или нет. по умолчанию опубликован
            $table->timestamps();

            $table->softDeletes(); //"мягкое" удаление, тое сть отображаться не будет, но данные можно будет восстановить
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    //функция для устранения проблем в таблице - удаление, исправление и тд.
    public function down()
    {
        Schema::dropIfExists('reviews'); //убить таблицу полностью, которая написана выше
    }
};
