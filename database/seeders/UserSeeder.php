<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Администратор
        $admin = User::updateOrCreate(
            ['email' => 'admin@avilona.ru'],
            [
                'name' => 'Администратор',
                'password' => Hash::make('password'),
                'phone' => '+79219314345',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole(Role::ADMIN);
        $this->command->info('✓ Администратор создан: admin@avilona.ru / password');

        // Менеджер
        $manager = User::updateOrCreate(
            ['email' => 'manager@avilona.ru'],
            [
                'name' => 'Менеджер Илона',
                'password' => Hash::make('password'),
                'phone' => '+79219314345',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $manager->assignRole(Role::MANAGER);
        $this->command->info('✓ Менеджер создан: manager@avilona.ru / password');

        // Турист
        $tourist = User::updateOrCreate(
            ['email' => 'tourist@avilona.ru'],
            [
                'name' => 'Иван Иванов',
                'password' => Hash::make('password'),
                'phone' => '+79219842022',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $tourist->assignRole(Role::TOURIST);
        $this->command->info('✓ Турист создан: tourist@avilona.ru / password');

        $this->command->info('');
        $this->command->info('Все тестовые пользователи успешно созданы!');
    }
}
