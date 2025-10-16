# Руководство по проверке проекта "Авилона"

## 🔍 Быстрая проверка установки

### 1. Проверка системных требований
```bash
# Проверка версии PHP
php --version
# Должно быть PHP 8.0+ с расширениями: mbstring, dom, fileinfo, mysql, pdo_mysql

# Проверка Composer
composer --version
# Должно быть Composer 2.0+

# Проверка Node.js
node --version
# Должно быть Node.js 16+

# Проверка npm
npm --version
# Должно быть npm 8.0+

# Проверка MySQL
mysql --version
# Должно быть MySQL 5.7+ или MariaDB 10.3+
```

### 2. Проверка установки зависимостей
```bash
# Проверка PHP зависимостей
composer show
# Должны быть установлены: laravel/framework, laravel/sanctum, spatie/laravel-sluggable

# Проверка Node.js зависимостей
npm list
# Должны быть установлены: vite, tailwindcss, alpinejs, bootstrap
```

### 3. Проверка конфигурации
```bash
# Проверка .env файла
cat .env
# Должны быть настроены: APP_NAME, APP_URL, DB_*, MAIL_*

# Проверка ключа приложения
php artisan key:generate --show
# Должен быть сгенерирован 32-символьный ключ

# Проверка подключения к БД
php artisan migrate:status
# Должны быть выполнены все миграции
```

## 🚀 Проверка запуска серверов

### 1. Автоматический запуск
```bash
# Windows
start-dev.bat

# Linux/macOS
chmod +x start-dev.sh
./start-dev.sh
```

### 2. Ручной запуск
```bash
# Laravel сервер
php artisan serve
# Должен запуститься на http://localhost:8000

# Vite dev server (в другом терминале)
npm run dev
# Должен запуститься на http://localhost:5173
```

### 3. Проверка доступности
- Открыть http://localhost:8000 в браузере
- Должна загрузиться главная страница сайта
- Проверить отсутствие ошибок в консоли браузера

## 🧪 Проверка функциональности

### 1. Тестирование основных страниц
```bash
# Запуск тестов
php artisan test

# Проверка конкретных тестов
php artisan test --filter=TourSearchTest
php artisan test --filter=UserRegistrationTest
```

### 2. Проверка API endpoints
```bash
# Проверка поиска туров
curl -X GET "http://localhost:8000/api/tours/search?departure_city=Москва&destination_country=Турция"

# Проверка регистрации пользователя
curl -X POST "http://localhost:8000/api/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123"}'
```

### 3. Проверка форм
- Открыть страницу контактов
- Заполнить форму обратной связи
- Проверить отправку email (если настроено)
- Проверить валидацию полей

## 🔧 Проверка производительности

### 1. Проверка времени загрузки
```bash
# Проверка времени ответа главной страницы
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost:8000"

# Создать файл curl-format.txt:
echo "     time_namelookup:  %{time_namelookup}\n
        time_connect:  %{time_connect}\n
     time_appconnect:  %{time_appconnect}\n
    time_pretransfer:  %{time_pretransfer}\n
       time_redirect:  %{time_redirect}\n
  time_starttransfer:  %{time_starttransfer}\n
                     ----------\n
          time_total:  %{time_total}\n" > curl-format.txt
```

### 2. Проверка кэширования
```bash
# Очистка кэша
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Включение кэширования
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Проверка работы кэша
php artisan tinker
>>> Cache::put('test', 'value', 60)
>>> Cache::get('test')
```

## 📱 Проверка мобильной адаптации

### 1. Responsive тестирование
- Открыть сайт в Chrome DevTools
- Переключиться в режим мобильного устройства
- Проверить адаптивность на разных разрешениях:
  - iPhone SE (375x667)
  - iPhone 12 Pro (390x844)
  - iPad (768x1024)
  - Desktop (1920x1080)

### 2. Touch-friendly интерфейс
- Проверить размер кнопок (минимум 44px)
- Проверить расстояние между кликабельными элементами
- Проверить работу форм на мобильном

## 🔒 Проверка безопасности

### 1. Проверка CSRF защиты
```bash
# Проверка наличия CSRF токена
curl -X GET "http://localhost:8000/contact" | grep csrf-token
```

### 2. Проверка валидации
```bash
# Попытка отправить невалидные данные
curl -X POST "http://localhost:8000/api/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"","email":"invalid-email","password":"123"}'
# Должны вернуться ошибки валидации
```

### 3. Проверка прав доступа
```bash
# Попытка доступа к защищенным маршрутам
curl -X GET "http://localhost:8000/admin"
# Должен быть редирект на страницу входа
```

## 📊 Проверка SEO

### 1. Проверка мета-тегов
```bash
# Проверка title и description
curl -s "http://localhost:8000" | grep -E "<title>|<meta.*description"
```

### 2. Проверка структуры URL
- Проверить наличие sitemap.xml
- Проверить robots.txt
- Проверить корректность URL структуры

### 3. Проверка Open Graph тегов
```bash
# Проверка OG тегов
curl -s "http://localhost:8000" | grep -E "og:|twitter:"
```

## 🐛 Отладка проблем

### 1. Проверка логов
```bash
# Просмотр логов Laravel
tail -f storage/logs/laravel.log

# Просмотр логов веб-сервера (если используется)
tail -f /var/log/nginx/error.log
```

### 2. Проверка конфигурации
```bash
# Проверка конфигурации приложения
php artisan config:show

# Проверка маршрутов
php artisan route:list

# Проверка middleware
php artisan route:list --middleware=auth
```

### 3. Проверка базы данных
```bash
# Подключение к БД
mysql -u root -p
USE avilona_turfirma;
SHOW TABLES;
DESCRIBE users;
```

## ✅ Чек-лист готовности

### Системные требования
- [ ] PHP 8.0+ установлен
- [ ] Composer установлен
- [ ] Node.js 16+ установлен
- [ ] MySQL 5.7+ установлен
- [ ] Все расширения PHP доступны

### Установка проекта
- [ ] Репозиторий клонирован
- [ ] PHP зависимости установлены
- [ ] Node.js зависимости установлены
- [ ] .env файл настроен
- [ ] База данных создана
- [ ] Миграции выполнены

### Функциональность
- [ ] Главная страница загружается
- [ ] Формы работают корректно
- [ ] API endpoints отвечают
- [ ] Тесты проходят
- [ ] Мобильная версия адаптивна

### Производительность
- [ ] Время загрузки < 3 секунд
- [ ] Кэширование работает
- [ ] Изображения оптимизированы
- [ ] CSS/JS минифицированы

### Безопасность
- [ ] CSRF защита активна
- [ ] Валидация работает
- [ ] Права доступа настроены
- [ ] HTTPS настроен (для продакшена)

## 🚨 Частые проблемы и решения

### Проблема: "Class not found"
```bash
# Решение
composer dump-autoload
```

### Проблема: "Database connection failed"
```bash
# Проверить настройки в .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=avilona_turfirma
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Проблема: "Permission denied"
```bash
# Решение для Linux/macOS
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Проблема: "Vite dev server not working"
```bash
# Решение
npm install
npm run dev
```

### Проблема: "Email not sending"
```bash
# Проверить настройки в .env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```
