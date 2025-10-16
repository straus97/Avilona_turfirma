<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('link');
            $table->text('description');
            $table->string('image')->nullable(); //вывод фотки, записывается url
            $table->timestamp('pub_date')->nullable();
            $table->timestamps();

            $table->softDeletes(); //"мягкое" удаление, тое сть отображаться не будет, но данные можно будет восстановить
        });
    }

    /**
     * Reverse the migrations.
     */
    //функция для устранения проблем в таблице - удаление, исправление и тд.
    public function down()
    {
        Schema::dropIfExists('news'); //убить таблицу полностью, которая написана выше
    }
};
