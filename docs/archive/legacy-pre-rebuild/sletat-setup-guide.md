# Настройка Sletat.ru API

## Шаги для настройки

### 1. Получение доступа к Sletat.ru API

1. **Зарегистрируйтесь на sletat.ru**
   - Перейдите на https://sletat.ru
   - Создайте аккаунт или войдите в существующий

2. **Получите доступ к API**
   - Обратитесь в службу поддержки Sletat.ru
   - Телефон: 8(800)700-33-09
   - Email: support@sletat.ru
   - Укажите, что нужен доступ к API для интеграции

3. **Получите учетные данные**
   - Логин и пароль от личного кабинета
   - Подтверждение доступа к API

### 2. Настройка переменных окружения

Добавьте в ваш `.env` файл следующие строки:

```env
# Sletat.ru API Configuration
SLETAT_LOGIN=your_sletat_login
SLETAT_PASSWORD=your_sletat_password
SLETAT_TIMEOUT=30
```

Замените:
- `your_sletat_login` - на ваш логин от Sletat.ru
- `your_sletat_password` - на ваш пароль от Sletat.ru

### 3. Тестирование подключения

После настройки учетных данных выполните команду:

```bash
php artisan sletat:test
```

Эта команда проверит:
- Подключение к API Sletat.ru
- Получение списка стран
- Получение городов вылета
- Отобразит первые 5 записей из каждого справочника

### 4. Ожидаемый результат

При успешном подключении вы увидите:

```
Testing connection to Sletat.ru API...
Testing GetCountries...
✅ Successfully retrieved 150 countries
First 5 countries:
- Турция (ID: 40)
- Египет (ID: 15)
- ОАЭ (ID: 1)
- Таиланд (ID: 45)
- Испания (ID: 12)

Testing GetDepartCities...
✅ Successfully retrieved 25 departure cities
First 5 departure cities:
- Москва (ID: 1)
- Санкт-Петербург (ID: 2)
- Екатеринбург (ID: 3)
- Новосибирск (ID: 4)
- Казань (ID: 5)

🎉 Sletat.ru API connection test completed successfully!
```

### 5. Возможные ошибки

#### Ошибка авторизации
```
❌ Connection test failed: API request failed: 401 Unauthorized
```
**Решение**: Проверьте правильность логина и пароля в `.env` файле

#### Ошибка доступа к API
```
❌ Connection test failed: API error: Access denied
```
**Решение**: Убедитесь, что у вас есть доступ к API. Обратитесь в поддержку Sletat.ru

#### Таймаут запроса
```
❌ Connection test failed: API request failed: Connection timeout
```
**Решение**: Проверьте интернет-соединение и увеличьте `SLETAT_TIMEOUT` в `.env`

### 6. Следующие шаги

После успешного тестирования:

1. **Интеграция с виджетом поиска**
   - Замена статических данных на динамические
   - Подключение к API для получения стран, городов, курортов

2. **Реализация поиска туров**
   - Асинхронный поиск через Sletat.ru
   - Отображение результатов в реальном времени

3. **Система заказов**
   - Интеграция с формой "Связаться с менеджером"
   - Передача заявок в Sletat.ru

## Поддержка

При возникновении проблем:
- Проверьте логи: `tail -f storage/logs/laravel.log`
- Обратитесь в службу поддержки Sletat.ru: 8(800)700-33-09
- Email: support@sletat.ru
