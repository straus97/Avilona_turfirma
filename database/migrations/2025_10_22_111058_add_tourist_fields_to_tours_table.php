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
            $table->integer('adults')->default(2)->comment('Количество взрослых');
            $table->integer('children')->default(0)->comment('Количество детей');
            $table->json('children_ages')->nullable()->comment('Возрасты детей');
            
            // Индексы для фильтрации
            $table->index('adults');
            $table->index('children');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['adults']);
            $table->dropIndex(['children']);
            $table->dropColumn(['adults', 'children', 'children_ages']);
        });
    }
};
