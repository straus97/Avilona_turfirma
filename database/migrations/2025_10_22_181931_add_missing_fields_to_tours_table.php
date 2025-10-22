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
        Schema::table('tours', function (Blueprint $table) {
            // Туроператор
            $table->string('tour_operator', 100)->nullable()->comment('Туроператор');
            
            // Пляжная линия
            $table->string('beach_line', 50)->nullable()->comment('Пляжная линия (первая, вторая, третья)');
            
            // Рейтинг отеля
            $table->decimal('hotel_rating', 3, 1)->nullable()->comment('Рейтинг отеля (1.0-5.0)');
            
            // Тип рейса
            $table->boolean('is_charter')->default(false)->comment('Чартерный рейс');
            $table->boolean('is_direct')->default(true)->comment('Прямой рейс');
            
            // Дополнительные поля для поиска
            $table->string('resort', 100)->nullable()->comment('Курорт');
            $table->text('included_services')->nullable()->comment('Включенные услуги');
            $table->text('not_included_services')->nullable()->comment('Не включенные услуги');
            
            // Индексы для новых полей
            $table->index('tour_operator');
            $table->index('beach_line');
            $table->index('hotel_rating');
            $table->index('resort');
            $table->index(['is_charter', 'is_direct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Удаляем индексы
            $table->dropIndex(['tour_operator']);
            $table->dropIndex(['beach_line']);
            $table->dropIndex(['hotel_rating']);
            $table->dropIndex(['resort']);
            $table->dropIndex(['is_charter', 'is_direct']);
            
            // Удаляем колонки
            $table->dropColumn([
                'tour_operator',
                'beach_line', 
                'hotel_rating',
                'is_charter',
                'is_direct',
                'resort',
                'included_services',
                'not_included_services'
            ]);
        });
    }
};
