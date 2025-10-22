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
        Schema::create('tour_operators', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Название туроператора');
            $table->string('slug')->unique()->comment('URL slug');
            $table->string('api_endpoint')->nullable()->comment('API endpoint для получения туров');
            $table->string('api_key')->nullable()->comment('API ключ');
            $table->string('api_secret')->nullable()->comment('API секрет');
            $table->json('api_config')->nullable()->comment('Дополнительная конфигурация API');
            $table->boolean('is_active')->default(true)->comment('Активен ли туроператор');
            $table->boolean('auto_sync')->default(true)->comment('Автоматическая синхронизация');
            $table->integer('sync_interval')->default(60)->comment('Интервал синхронизации в минутах');
            $table->timestamp('last_sync_at')->nullable()->comment('Последняя синхронизация');
            $table->timestamp('last_successful_sync_at')->nullable()->comment('Последняя успешная синхронизация');
            $table->integer('sync_errors_count')->default(0)->comment('Количество ошибок синхронизации');
            $table->text('last_error')->nullable()->comment('Последняя ошибка');
            $table->timestamps();
            
            // Индексы
            $table->index('is_active');
            $table->index('auto_sync');
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_operators');
    }
};
