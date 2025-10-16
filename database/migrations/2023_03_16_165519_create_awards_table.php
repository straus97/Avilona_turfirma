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
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable(); //вывод фотки, записывается url
            $table->string('category'); //вывод фотки, записывается url
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
        Schema::dropIfExists('awards'); //убить таблицу полностью, которая написана выше
    }
};
