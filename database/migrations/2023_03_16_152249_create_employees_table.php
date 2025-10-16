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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name'); //255 символов
            $table->text('position'); //большой текст
            $table->string('tel'); //255 символов
            $table->string('email'); //255 символов
            $table->string('whatsapp'); //255 символов
            $table->string('vk'); //255 символов
            $table->string('image')->nullable(); //вывод фотки, записывается url
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
        Schema::dropIfExists('employees'); //убить таблицу полностью, которая написана выше
    }
};
