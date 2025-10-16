@echo off
echo ========================================
echo   Запуск сервера разработки Авилона
echo ========================================
echo.

echo Проверка зависимостей...
if not exist "vendor" (
    echo Установка PHP зависимостей...
    composer install
)

if not exist "node_modules" (
    echo Установка Node.js зависимостей...
    npm install
)

echo.
echo Запуск серверов...
echo.

echo [1/2] Запуск Laravel сервера на http://localhost:8000
start "Laravel Server" cmd /k "php artisan serve"

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
