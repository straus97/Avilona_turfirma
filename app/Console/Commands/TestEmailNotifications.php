<?php

namespace App\Console\Commands;

use App\Mail\BookingCreated;
use App\Mail\BookingStatusChanged;
use App\Mail\ManagerAssigned;
use App\Mail\NewMessageReceived;
use App\Mail\TripReminder;
use App\Models\Booking;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-notifications {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование отправки всех типов email-уведомлений';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        
        $this->info("Начинаем тестирование email-уведомлений на адрес: {$email}");
        $this->newLine();

        // Получаем первую заявку для тестирования
        $booking = Booking::with(['user', 'tour', 'manager'])->first();
        
        if (!$booking) {
            $this->error('В базе данных нет заявок для тестирования!');
            $this->info('Создайте хотя бы одну заявку через сайт.');
            return Command::FAILURE;
        }

        $this->info("Используем заявку #{$booking->id} для тестирования");
        $this->newLine();

        // 1. Тест создания заявки
        try {
            $this->info('1. Отправка уведомления о создании заявки...');
            Mail::to($email)->send(new BookingCreated($booking));
            $this->info('   ✓ Успешно отправлено');
        } catch (\Exception $e) {
            $this->error('   ✗ Ошибка: ' . $e->getMessage());
        }
        $this->newLine();

        // 2. Тест изменения статуса
        try {
            $this->info('2. Отправка уведомления об изменении статуса...');
            Mail::to($email)->send(new BookingStatusChanged($booking, 'new', $booking->user));
            $this->info('   ✓ Успешно отправлено');
        } catch (\Exception $e) {
            $this->error('   ✗ Ошибка: ' . $e->getMessage());
        }
        $this->newLine();

        // 3. Тест назначения менеджера
        if ($booking->manager) {
            try {
                $this->info('3. Отправка уведомления о назначении менеджера...');
                Mail::to($email)->send(new ManagerAssigned($booking, $booking->user));
                $this->info('   ✓ Успешно отправлено');
            } catch (\Exception $e) {
                $this->error('   ✗ Ошибка: ' . $e->getMessage());
            }
        } else {
            $this->warn('3. Пропущено (у заявки нет менеджера)');
        }
        $this->newLine();

        // 4. Тест напоминания о поездке
        try {
            $this->info('4. Отправка напоминания о поездке...');
            Mail::to($email)->send(new TripReminder($booking, 7));
            $this->info('   ✓ Успешно отправлено');
        } catch (\Exception $e) {
            $this->error('   ✗ Ошибка: ' . $e->getMessage());
        }
        $this->newLine();

        // 5. Тест нового сообщения
        $message = Message::with(['booking', 'sender', 'receiver'])->first();
        if ($message) {
            try {
                $this->info('5. Отправка уведомления о новом сообщении...');
                Mail::to($email)->send(new NewMessageReceived($message));
                $this->info('   ✓ Успешно отправлено');
            } catch (\Exception $e) {
                $this->error('   ✗ Ошибка: ' . $e->getMessage());
            }
        } else {
            $this->warn('5. Пропущено (в базе нет сообщений)');
        }
        $this->newLine();

        $this->info('Тестирование завершено!');
        $this->info("Проверьте почтовый ящик: {$email}");
        
        return Command::SUCCESS;
    }
}
