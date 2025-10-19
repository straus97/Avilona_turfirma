<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => Role::ADMIN,
                'description' => 'Администратор - полный доступ к системе',
            ],
            [
                'name' => Role::MANAGER,
                'description' => 'Менеджер - управление заявками и клиентами',
            ],
            [
                'name' => Role::TOURIST,
                'description' => 'Турист - просмотр и создание заявок',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }

        $this->command->info('Роли успешно созданы!');
    }
}
