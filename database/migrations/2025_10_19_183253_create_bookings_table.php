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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            
            // Связи
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('Турист');
            $table->foreignId('tour_id')->nullable()->constrained()->onDelete('set null')->comment('Тур');
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null')->comment('Менеджер');
            
            // Статус заявки
            $table->enum('status', ['new', 'progress', 'confirmed', 'cancelled', 'completed'])->default('new');
            
            // Детали поездки
            $table->string('departure_city');
            $table->string('destination_country');
            $table->string('destination_city')->nullable();
            $table->date('start_date');
            $table->integer('nights');
            
            // Туристы
            $table->integer('adults')->default(1)->comment('Количество взрослых');
            $table->integer('children')->default(0)->comment('Количество детей');
            $table->json('tourists_data')->nullable()->comment('Данные туристов (ФИО, паспорт и т.д.)');
            
            // Цена
            $table->decimal('total_price', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0)->comment('Оплачено');
            
            // Дополнительно
            $table->text('notes')->nullable()->comment('Заметки и пожелания');
            $table->text('manager_notes')->nullable()->comment('Заметки менеджера');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Индексы
            $table->index('user_id');
            $table->index('manager_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
