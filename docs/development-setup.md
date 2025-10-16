# Настройка среды разработки

## 🖥 Системные требования

### Минимальные требования
- **ОС**: Windows 10/11, macOS 10.15+, Ubuntu 18.04+
- **RAM**: 4GB (рекомендуется 8GB+)
- **Диск**: 2GB свободного места
- **Процессор**: 2 ядра (рекомендуется 4+)

### Необходимое ПО
- **PHP 8.0+** с расширениями: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML
- **Composer** - менеджер зависимостей PHP
- **Node.js 16+** и npm
- **MySQL 5.7+** или **MariaDB 10.3+**
- **Git** для версионного контроля

## 📦 Установка на Windows

### 1. Установка PHP
```bash
# Скачать PHP 8.1+ с https://windows.php.net/download/
# Распаковать в C:\php
# Добавить C:\php в PATH
```

### 2. Установка Composer
```bash
# Скачать с https://getcomposer.org/download/
# Установить глобально
composer --version
```

### 3. Установка Node.js
```bash
# Скачать с https://nodejs.org/
# Установить LTS версию
node --version
npm --version
```

### 4. Установка MySQL
```bash
# Скачать MySQL Community Server с https://dev.mysql.com/downloads/
# Или использовать XAMPP/WAMP для локальной разработки
```

## 🚀 Настройка проекта

### 1. Клонирование и установка зависимостей
```bash
git clone <repository-url> avilona-turfirma
cd avilona-turfirma
composer install
npm install
```

### 2. Настройка окружения
```bash
# Копировать файл окружения
cp .env.example .env

# Сгенерировать ключ приложения
php artisan key:generate
```

### 3. Настройка базы данных
```bash
# Создать базу данных MySQL
mysql -u root -p
CREATE DATABASE avilona_turfirma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Запустить миграции
php artisan migrate

# Заполнить тестовыми данными (опционально)
php artisan db:seed
```

### 4. Настройка почты (для разработки)
```bash
# В .env файле настроить:
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

## 🔧 Автоматизация разработки

### 1. Автоматический запуск сервера
```bash
# Создать скрипт start-dev.bat (Windows)
@echo off
echo Запуск сервера разработки...
start "Laravel Server" php artisan serve
start "Vite Dev Server" npm run dev
echo Серверы запущены!
pause
```

### 2. Настройка hot reload
```bash
# В package.json добавить скрипт:
"scripts": {
    "dev": "vite",
    "build": "vite build",
    "watch": "vite build --watch"
}
```

### 3. Автоматическая сборка при изменениях
```bash
# Запуск в режиме наблюдения
npm run watch
```

## 🛠 Полезные команды

### Laravel Artisan
```bash
# Очистка кэша
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Оптимизация для продакшена
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Создание компонентов
php artisan make:controller ControllerName
php artisan make:model ModelName -m
php artisan make:migration migration_name
```

### Composer
```bash
# Обновление зависимостей
composer update

# Автозагрузка
composer dump-autoload
```

### NPM
```bash
# Установка зависимостей
npm install

# Сборка для продакшена
npm run build

# Разработка с hot reload
npm run dev
```

## 🔍 Отладка

### 1. Включение режима отладки
```bash
# В .env файле:
APP_DEBUG=true
APP_ENV=local
```

### 2. Логирование
```bash
# Просмотр логов
tail -f storage/logs/laravel.log

# Очистка логов
php artisan log:clear
```

### 3. Профилирование
```bash
# Установка Laravel Debugbar (опционально)
composer require barryvdh/laravel-debugbar --dev
```

## 📱 Мобильная разработка

### 1. Тестирование на мобильных устройствах
```bash
# Запуск сервера с доступом из сети
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Responsive тестирование
- Использовать Chrome DevTools
- Тестировать на реальных устройствах
- Проверять на разных разрешениях экрана

## 🚨 Устранение неполадок

### Частые проблемы
1. **Ошибка "Class not found"** - запустить `composer dump-autoload`
2. **Ошибка миграций** - проверить подключение к БД в `.env`
3. **Проблемы с правами** - настроить права на папки `storage/` и `bootstrap/cache/`
4. **Ошибки сборки** - очистить кэш npm: `npm cache clean --force`

### Проверка установки
```bash
# Проверить все компоненты
php --version
composer --version
node --version
npm --version
mysql --version

# Проверить Laravel
php artisan --version
php artisan route:list
```
