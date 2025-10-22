# Интеграция с Sletat.ru API

## Обзор

Интеграция с [Sletat.ru API](https://wiki.sletat.ru/w/%D0%A8%D0%BB%D1%8E%D0%B7_%D0%BF%D0%BE%D0%B8%D1%81%D0%BA%D0%B0_%D1%82%D1%83%D1%80%D0%BE%D0%B2_(json)) позволяет получить доступ к данным более чем 130 туроператоров России через единый API.

## Архитектура

### 1. **SletatApiService** (`app/Services/Sletat/SletatApiService.php`)
Основной сервис для работы с Sletat.ru API:
- Авторизация через HTTP Basic Auth
- Кэширование справочных данных
- Обработка ошибок и логирование
- Асинхронный поиск туров

### 2. **SletatController** (`app/Http/Controllers/Api/SletatController.php`)
API контроллер для фронтенда:
- Получение справочников
- Создание поисковых запросов
- Получение результатов поиска

### 3. **Маршруты** (`routes/api.php`)
```php
Route::prefix('sletat')->name('sletat.')->group(function () {
    // Справочники
    Route::get('/countries', [SletatController::class, 'getCountries']);
    Route::get('/depart-cities', [SletatController::class, 'getDepartCities']);
    Route::get('/cities', [SletatController::class, 'getCities']);
    Route::get('/hotels', [SletatController::class, 'getHotels']);
    Route::get('/hotel-stars', [SletatController::class, 'getHotelStars']);
    Route::get('/meals', [SletatController::class, 'getMeals']);
    Route::get('/tour-operators', [SletatController::class, 'getTourOperators']);
    Route::get('/tour-dates', [SletatController::class, 'getTourDates']);
    
    // Поиск туров
    Route::post('/search', [SletatController::class, 'searchTours']);
    Route::post('/search/create', [SletatController::class, 'createSearchRequest']);
    Route::get('/search/state', [SletatController::class, 'getLoadState']);
    Route::get('/search/results', [SletatController::class, 'getSearchResults']);
});
```

## Настройка

### 1. **Получение доступа к Sletat.ru**
1. Зарегистрируйтесь на [sletat.ru](https://sletat.ru)
2. Получите логин и пароль от личного кабинета
3. Обратитесь в службу поддержки для получения доступа к API

### 2. **Конфигурация**
Добавьте в `.env` файл:
```env
SLETAT_LOGIN=your_login
SLETAT_PASSWORD=your_password
SLETAT_TIMEOUT=30
```

### 3. **Тестирование подключения**
```bash
php artisan sletat:test
```

## Использование

### **Справочники**

#### Получение стран
```javascript
fetch('/api/sletat/countries')
  .then(response => response.json())
  .then(data => {
    console.log('Countries:', data.data);
  });
```

#### Получение городов вылета
```javascript
fetch('/api/sletat/depart-cities')
  .then(response => response.json())
  .then(data => {
    console.log('Departure cities:', data.data);
  });
```

#### Получение курортов по стране
```javascript
fetch('/api/sletat/cities?country_id=40')
  .then(response => response.json())
  .then(data => {
    console.log('Cities:', data.data);
  });
```

### **Поиск туров**

#### Синхронный поиск (ожидание полных результатов)
```javascript
const searchData = {
  city_from_id: 1,
  country_id: 40,
  check_in: '2025-11-01',
  nights: 7,
  adults: 2,
  children: 0
};

fetch('/api/sletat/search', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(searchData)
})
.then(response => response.json())
.then(data => {
  console.log('Search results:', data.data);
});
```

#### Асинхронный поиск (рекомендуемый)
```javascript
// 1. Создаем поисковый запрос
const searchData = {
  city_from_id: 1,
  country_id: 40,
  check_in: '2025-11-01',
  nights: 7,
  adults: 2,
  children: 0
};

fetch('/api/sletat/search/create', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(searchData)
})
.then(response => response.json())
.then(data => {
  const requestId = data.data.requestId;
  
  // 2. Проверяем статус поиска
  const checkStatus = () => {
    fetch(`/api/sletat/search/state?request_id=${requestId}`)
      .then(response => response.json())
      .then(data => {
        if (data.data.isComplete) {
          // 3. Получаем результаты
          fetch(`/api/sletat/search/results?request_id=${requestId}`)
            .then(response => response.json())
            .then(results => {
              console.log('Search results:', results.data);
            });
        } else {
          // Повторяем проверку через 5 секунд
          setTimeout(checkStatus, 5000);
        }
      });
  };
  
  checkStatus();
});
```

## Структура данных

### **Страна**
```json
{
  "Id": 40,
  "Name": "Турция",
  "Alias": "turkey"
}
```

### **Город вылета**
```json
{
  "Id": 1,
  "Name": "Москва",
  "Alias": "moscow"
}
```

### **Курорт**
```json
{
  "Id": 123,
  "Name": "Анталия",
  "CountryId": 40,
  "Alias": "antalya"
}
```

### **Тур**
```json
{
  "Id": 12345,
  "Title": "Отдых в Турции",
  "Price": 75000,
  "Currency": "RUB",
  "CheckIn": "2025-11-01",
  "Nights": 7,
  "HotelName": "Grand Hotel",
  "HotelStars": 5,
  "MealType": "AI",
  "TourOperator": "Coral Travel",
  "ImageUrl": "https://example.com/image.jpg"
}
```

## Особенности

### **Кэширование**
- Справочники кэшируются на 1 час
- Даты туров кэшируются на 30 минут
- Результаты поиска не кэшируются

### **Обработка ошибок**
- Все ошибки логируются в `storage/logs/laravel.log`
- API возвращает структурированные ошибки
- Автоматические повторы при временных сбоях

### **Ограничения**
- Поиск должен выполняться с одного IP-адреса
- Максимальное время ожидания результатов: 2 минуты
- Рекомендуемый интервал между запросами: 5 секунд

## Интеграция с виджетом поиска

### **Обновление справочников**
```javascript
// Загружаем страны при инициализации
fetch('/api/sletat/countries')
  .then(response => response.json())
  .then(data => {
    const countrySelect = document.getElementById('destination_country');
    data.data.forEach(country => {
      const option = document.createElement('option');
      option.value = country.Id;
      option.textContent = country.Name;
      countrySelect.appendChild(option);
    });
  });
```

### **Динамическая загрузка курортов**
```javascript
document.getElementById('destination_country').addEventListener('change', function() {
  const countryId = this.value;
  
  fetch(`/api/sletat/cities?country_id=${countryId}`)
    .then(response => response.json())
    .then(data => {
      const resortSelect = document.getElementById('resort');
      resortSelect.innerHTML = '<option value="">Выберите курорт</option>';
      
      data.data.forEach(city => {
        const option = document.createElement('option');
        option.value = city.Id;
        option.textContent = city.Name;
        resortSelect.appendChild(option);
      });
    });
});
```

## Дальнейшие шаги

1. **Настройка аккаунта Sletat.ru** - получение доступа к API
2. **Тестирование подключения** - `php artisan sletat:test`
3. **Интеграция с виджетом** - замена статических данных на динамические
4. **Реализация асинхронного поиска** - обновление интерфейса поиска
5. **Система заказов** - интеграция с формой "Связаться с менеджером"

## Поддержка

При возникновении проблем:
1. Проверьте логи: `tail -f storage/logs/laravel.log`
2. Убедитесь в правильности учетных данных
3. Обратитесь в службу поддержки Sletat.ru: 8(800)700-33-09 или support@sletat.ru
