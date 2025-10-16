#!/bin/bash

echo "========================================"
echo "   Запуск сервера разработки Авилона"
echo "========================================"
echo

echo "Проверка зависимостей..."
if [ ! -d "vendor" ]; then
    echo "Установка PHP зависимостей..."
    composer install
fi

if [ ! -d "node_modules" ]; then
    echo "Установка Node.js зависимостей..."
    npm install
fi

echo
echo "Запуск серверов..."
echo

echo "[1/2] Запуск Laravel сервера на http://localhost:8000"
php artisan serve &
LARAVEL_PID=$!

echo "[2/2] Запуск Vite dev server для hot reload"
npm run dev &
VITE_PID=$!

echo
echo "✅ Серверы запущены!"
echo
echo "🌐 Веб-сайт: http://localhost:8000"
echo "🔥 Hot reload: http://localhost:5173"
echo
echo "Для остановки серверов нажмите Ctrl+C"

# Функция для корректного завершения
cleanup() {
    echo
    echo "Остановка серверов..."
    kill $LARAVEL_PID 2>/dev/null
    kill $VITE_PID 2>/dev/null
    echo "Серверы остановлены"
    exit 0
}

# Перехват сигнала завершения
trap cleanup SIGINT SIGTERM

# Ожидание завершения
wait
