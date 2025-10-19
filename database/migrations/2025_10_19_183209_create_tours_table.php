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
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            
            // Основная информация
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('price_child', 10, 2)->nullable()->comment('Цена для ребенка');
            
            // Направление
            $table->string('departure_city', 100)->comment('Город отправления');
            $table->string('destination_country', 100)->comment('Страна назначения');
            $table->string('destination_city', 100)->nullable()->comment('Город назначения');
            
            // Даты
            $table->date('start_date')->comment('Дата начала тура');
            $table->date('end_date')->comment('Дата окончания тура');
            $table->integer('nights')->comment('Количество ночей');
            
            // Отель
            $table->string('hotel_name')->nullable();
            $table->integer('hotel_stars')->nullable()->comment('Звездность отеля (1-5)');
            $table->enum('meal_type', ['BB', 'HB', 'FB', 'AI', 'UAI'])->nullable()->comment('Тип питания');
            
            // Дополнительно
            $table->integer('max_tourists')->default(10)->comment('Максимальное количество туристов');
            $table->integer('available_seats')->nullable()->comment('Доступных мест');
            $table->json('facilities')->nullable()->comment('Удобства отеля');
            $table->string('image_url')->nullable();
            $table->json('gallery')->nullable()->comment('Галерея изображений');
            
            // Статус
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hot_deal')->default(false)->comment('Горящее предложение');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Индексы для оптимизации поиска
            $table->index(['departure_city', 'destination_country']);
            $table->index(['start_date', 'end_date']);
            $table->index('price');
            $table->index('is_active');
            $table->index('is_hot_deal');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
