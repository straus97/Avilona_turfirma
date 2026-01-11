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
        Schema::create('destination_cities', function (Blueprint $table) {
            $table->id();
            $table->string('country'); // Название страны
            $table->string('city'); // Название курорта/города
            $table->boolean('is_popular')->default(false); // Популярное направление
            $table->boolean('is_auto_added')->default(false); // Добавлено автоматически менеджером
            $table->timestamps();
            
            // Уникальность пары страна-город
            $table->unique(['country', 'city']);
            $table->index('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destination_cities');
    }
};
