# Интеграция с API туроператоров

## 🏗 Архитектура системы

### Модели
- **`TourOperator`** - информация о туроператорах и их API
- **`Tour`** - туры с полем `tour_operator` для связи

### Сервисы
- **`BaseTourOperatorService`** - базовый класс для всех интеграций
- **`CoralTravelService`** - конкретная реализация для Coral Travel

### Команды
- **`tours:sync`** - синхронизация туров
- **`operators:seed`** - создание туроператоров

## 🔧 Настройка туроператора

### 1. Добавление в базу данных
```php
TourOperator::create([
    'name' => 'Новый Туроператор',
    'api_endpoint' => 'https://api.example.com',
    'api_key' => 'your_api_key',
    'api_secret' => 'your_api_secret',
    'api_config' => [
        'timeout' => 30,
        'retry_attempts' => 3,
        'rate_limit' => 100,
    ],
    'sync_interval' => 60, // минуты
]);
```

### 2. Создание сервиса
Создать класс в `app/Services/TourOperators/`:

```php
<?php

namespace App\Services\TourOperators;

class ExampleTourOperatorService extends BaseTourOperatorService
{
    public function fetchTours(array $filters = []): array
    {
        // Реализация получения туров
    }

    protected function normalizeTourData(array $tourData): array
    {
        // Нормализация данных для нашей БД
    }

    public function getSupportedCountries(): array
    {
        // Список поддерживаемых стран
    }

    public function getSupportedDepartureCities(): array
    {
        // Список городов отправления
    }
}
```

### 3. Регистрация в команде синхронизации
Добавить в `SyncToursCommand::getServiceForOperator()`:

```php
case 'Новый Туроператор':
    return new ExampleTourOperatorService($operator);
```

## 🚀 Использование

### Синхронизация всех туроператоров
```bash
php artisan tours:sync
```

### Синхронизация конкретного туроператора
```bash
php artisan tours:sync --operator="Coral Travel"
```

### Синхронизация с фильтрами
```bash
php artisan tours:sync --filters='{"destination_country":"Турция","nights":7}'
```

## 📊 Мониторинг

### Статус синхронизации
```php
$operator = TourOperator::find(1);
echo $operator->last_sync_at; // Последняя синхронизация
echo $operator->sync_errors_count; // Количество ошибок
echo $operator->last_error; // Последняя ошибка
```

### Проверка готовности к синхронизации
```php
if ($operator->canSync()) {
    // Можно синхронизировать
}
```

## 🔄 Автоматическая синхронизация

### Настройка cron
Добавить в `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('tours:sync')
             ->everyHour()
             ->withoutOverlapping();
}
```

### Настройка очередей (опционально)
```php
// В команде синхронизации
dispatch(new SyncToursJob($operator, $filters));
```

## 🛡 Безопасность

### API ключи
- Хранить в `.env` файле
- Использовать Laravel Vault для продакшена
- Ротировать ключи регулярно

### Rate Limiting
- Настроить лимиты в `api_config`
- Использовать очереди для больших объемов
- Мониторить использование API

## 📈 Производительность

### Кэширование
```php
// В сервисе туроператора
$cacheKey = "tours_{$operator->id}_" . md5(serialize($filters));
return Cache::remember($cacheKey, 300, function() use ($filters) {
    return $this->fetchTours($filters);
});
```

### Оптимизация запросов
- Использовать пагинацию
- Фильтровать на стороне API
- Кэшировать результаты

## 🐛 Отладка

### Логирование
```php
Log::info("Syncing tours from {$operator->name}");
Log::error("API request failed: " . $response->body());
```

### Тестирование
```php
// Тестовый запрос к API
$service = new CoralTravelService($operator);
$tours = $service->fetchTours(['destination_country' => 'Турция']);
```

## 📋 Чек-лист для нового туроператора

- [ ] Создать запись в `tour_operators`
- [ ] Реализовать сервис-класс
- [ ] Добавить в команду синхронизации
- [ ] Протестировать API подключение
- [ ] Настроить нормализацию данных
- [ ] Добавить обработку ошибок
- [ ] Настроить мониторинг
- [ ] Документировать API туроператора
