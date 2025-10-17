@echo off
chcp 65001 >nul
echo ========================================
echo   Запуск сервера разработки Авилона
echo ========================================
echo.

echo Проверка зависимостей...
if not exist "vendor" (
    echo Установка PHP зависимостей...
    C:\wamp\bin\php\php8.4.13\php.exe C:\wamp\bin\composer\composer.phar install
)

if not exist "node_modules" (
    echo Установка Node.js зависимостей...
    npm install
)

echo.
echo Создание файла .env...
if not exist ".env" (
    echo APP_NAME=Авилона > .env
    echo APP_ENV=local >> .env
    echo APP_KEY= >> .env
    echo APP_DEBUG=true >> .env
    echo APP_URL=http://localhost:8000 >> .env
    echo. >> .env
    echo DB_CONNECTION=mysql >> .env
    echo DB_HOST=127.0.0.1 >> .env
    echo DB_PORT=3306 >> .env
    echo DB_DATABASE=turfirma >> .env
    echo DB_USERNAME=root >> .env
    echo DB_PASSWORD= >> .env
    echo Файл .env создан!
)

echo.
echo Генерация ключа приложения...
C:\wamp\bin\php\php8.4.13\php.exe artisan key:generate

echo.
echo Запуск серверов...
echo.

echo [1/2] Запуск Laravel сервера на http://localhost:8000
start "Laravel Server" cmd /k "C:\wamp\bin\php\php8.4.13\php.exe artisan serve"

echo [2/2] Запуск Vite dev server для hot reload
start "Vite Dev Server" cmd /k "npm run dev"

echo.
echo ✅ Серверы запущены!
echo.
echo 🌐 Веб-сайт: http://localhost:8000
echo 🔥 Hot reload: http://localhost:5173
echo.
echo Для остановки серверов закройте окна терминалов
echo.
pause
