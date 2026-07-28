# Настройка системы аутентификации и ролей - Проект "Авилона"

## 🎯 Цели

Создать полнофункциональную систему аутентификации с тремя типами пользователей:
- **Admin** (Администратор) - полный доступ к системе
- **Manager** (Менеджер) - управление заявками и клиентами
- **Tourist** (Турист) - просмотр и создание заявок

## 📋 Текущее состояние

### ✅ Что уже есть:
- Базовая Laravel аутентификация
- Модель User с базовыми полями
- Модель Role с soft deletes
- Связь many-to-many между User и Role через таблицу role_user
- Метод hasRole() в модели User

### ⚠️ Что нужно улучшить:
- Добавить поля в модель User (phone, is_active)
- Создать константы для ролей
- Улучшить middleware для проверки ролей
- Создать сидеры для ролей
- Добавить защиту маршрутов

## 🏗 План реализации

### Этап 1: Улучшение модели User

#### 1.1 Создать миграцию для добавления полей
```php
php artisan make:migration add_additional_fields_to_users_table --table=users
```

Добавить поля:
- `phone` (string, nullable) - телефон пользователя
- `is_active` (boolean, default true) - активность аккаунта
- `last_login_at` (timestamp, nullable) - последний вход

#### 1.2 Обновить модель User
```php
// Добавить fillable
protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
    'is_active',
];

// Добавить casts
protected $casts = [
    'email_verified_at' => 'datetime',
    'last_login_at' => 'datetime',
    'is_active' => 'boolean',
];

// Константы для ролей
const ROLE_ADMIN = 'admin';
const ROLE_MANAGER = 'manager';
const ROLE_TOURIST = 'tourist';

// Улучшить метод hasRole
public function hasRole(string|array $roles): bool
{
    if (is_array($roles)) {
        return $this->roles()->whereIn('name', $roles)->exists();
    }
    return $this->roles()->where('name', $roles)->exists();
}

// Добавить метод hasAnyRole
public function hasAnyRole(array $roles): bool
{
    return $this->roles()->whereIn('name', $roles)->exists();
}

// Добавить метод assignRole
public function assignRole(string $role): void
{
    $roleModel = Role::where('name', $role)->firstOrFail();
    $this->roles()->syncWithoutDetaching([$roleModel->id]);
}

// Добавить метод removeRole
public function removeRole(string $role): void
{
    $roleModel = Role::where('name', $role)->first();
    if ($roleModel) {
        $this->roles()->detach($roleModel->id);
    }
}

// Добавить scope для активных пользователей
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```

### Этап 2: Улучшение модели Role

#### 2.1 Обновить модель Role
```php
// Константы для ролей
const ADMIN = 'admin';
const MANAGER = 'manager';
const TOURIST = 'tourist';

// Добавить fillable
protected $fillable = ['name', 'description'];

// Добавить метод для получения всех доступных ролей
public static function availableRoles(): array
{
    return [
        self::ADMIN => 'Администратор',
        self::MANAGER => 'Менеджер',
        self::TOURIST => 'Турист',
    ];
}
```

### Этап 3: Создание middleware для ролей

#### 3.1 Создать middleware CheckRole
```php
php artisan make:middleware CheckRole
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login')
                ->with('error', 'Необходимо войти в систему');
        }

        if (!$request->user()->hasAnyRole($roles)) {
            abort(403, 'У вас нет доступа к этой странице');
        }

        return $next($request);
    }
}
```

#### 3.2 Зарегистрировать middleware
```php
// В app/Http/Kernel.php или bootstrap/app.php (Laravel 11)
protected $middlewareAliases = [
    'role' => \App\Http\Middleware\CheckRole::class,
];
```

### Этап 4: Создание сидеров

#### 4.1 Создать сидер для ролей
```php
php artisan make:seeder RoleSeeder
```

```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
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
    }
}
```

#### 4.2 Создать сидер для пользователей (тестовые)
```php
php artisan make:seeder UserSeeder
```

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
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
    }
}
```

### Этап 5: Защита маршрутов

#### 5.1 Обновить routes/web.php
```php
// Маршруты только для туристов
Route::middleware(['auth', 'verified', 'role:tourist,manager,admin'])->group(function () {
    Route::get('/profile/account', [ProfileController::class, 'account'])->name('account');
    Route::get('/profile/message', [ProfileController::class, 'message'])->name('message');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/settings', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/settings', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Маршруты только для менеджеров
Route::middleware(['auth', 'role:manager,admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/bookings', [ManagerBookingController::class, 'index'])->name('bookings');
    Route::get('/bookings/{booking}', [ManagerBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}', [ManagerBookingController::class, 'update'])->name('bookings.update');
});

// Маршруты только для администраторов
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', AdminUserController::class);
    Route::resource('roles', AdminRoleController::class);
    Route::resource('tours', AdminTourController::class);
    Route::resource('bookings', AdminBookingController::class);
});
```

### Этап 6: Создание контроллеров для ролей

#### 6.1 Создать контроллеры
```bash
# Для менеджеров
php artisan make:controller Manager/DashboardController
php artisan make:controller Manager/BookingController

# Для администраторов
php artisan make:controller Admin/DashboardController
php artisan make:controller Admin/UserController --resource
php artisan make:controller Admin/RoleController --resource
```

### Этап 7: Создание представлений

#### 7.1 Создать макеты для разных ролей
```
resources/views/
├── layouts/
│   ├── main.blade.php           # Для неавторизованных
│   ├── app.blade.php            # Для туристов
│   ├── manager.blade.php        # Для менеджеров
│   └── admin.blade.php          # Для администраторов
├── profile/
│   ├── account.blade.php
│   └── settings/
│       └── edit.blade.php
├── manager/
│   ├── dashboard.blade.php
│   └── bookings/
│       ├── index.blade.php
│       └── show.blade.php
└── admin/
    ├── dashboard.blade.php
    ├── users/
    │   ├── index.blade.php
    │   ├── create.blade.php
    │   └── edit.blade.php
    └── roles/
        ├── index.blade.php
        ├── create.blade.php
        └── edit.blade.php
```

## 🧪 Тестирование

### Тестовые пользователи
- **Администратор**: admin@avilona.ru / password
- **Менеджер**: manager@avilona.ru / password
- **Турист**: tourist@avilona.ru / password

### Проверка доступа
1. Войти как турист → Доступ к профилю
2. Войти как менеджер → Доступ к профилю + дашборд менеджера
3. Войти как админ → Доступ ко всему

## 📊 Результат

### Что будет создано:
- ✅ Улучшенная модель User с дополнительными полями
- ✅ Улучшенная модель Role с константами
- ✅ Middleware для проверки ролей
- ✅ Сидеры для ролей и тестовых пользователей
- ✅ Защищенные маршруты для разных ролей
- ✅ Контроллеры для менеджеров и администраторов
- ✅ Представления для разных типов пользователей

### Преимущества:
- 🔒 Безопасность - каждая роль имеет доступ только к своим функциям
- 🎯 Гибкость - легко добавить новые роли
- 🧪 Тестируемость - тестовые пользователи для всех ролей
- 📝 Документация - полное описание системы

## 🚀 Команды для выполнения

```bash
# 1. Создать миграцию
php artisan make:migration add_additional_fields_to_users_table --table=users

# 2. Создать middleware
php artisan make:middleware CheckRole

# 3. Создать сидеры
php artisan make:seeder RoleSeeder
php artisan make:seeder UserSeeder

# 4. Создать контроллеры
php artisan make:controller Manager/DashboardController
php artisan make:controller Admin/DashboardController

# 5. Выполнить миграции
php artisan migrate

# 6. Запустить сидеры
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=UserSeeder

# 7. Очистить кэш
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```
