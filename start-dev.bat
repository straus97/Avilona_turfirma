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
echo Проверка файла .env...
if not exist ".env" (
    echo Создание файла .env...
    copy .env.example .env >nul 2>&1
    if errorlevel 1 (
        echo APP_NAME=Avilona > .env
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
    )
    echo Файл .env создан!
    echo.
    echo Генерация ключа приложения...
    C:\wamp\bin\php\php8.4.13\php.exe artisan key:generate
)

echo.
echo Запуск Laravel сервера...
echo.

echo Открываю Laravel сервер на http://localhost:8000
start "Laravel Server - Avilona" cmd /k "cd /d "%~dp0" && C:\wamp\bin\php\php8.4.13\php.exe artisan serve"

timeout /t 3 /nobreak >nul

echo.
echo ========================================
echo   ✅ Сервер запущен успешно!
echo ========================================
echo.
echo 🌐 Веб-сайт: http://localhost:8000
echo.
echo Для остановки сервера закройте окно терминала
echo или нажмите Ctrl+C в окне сервера
echo.
echo Нажмите любую клавишу для выхода из этого окна...
pause >nul
