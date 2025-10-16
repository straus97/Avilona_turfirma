@echo off
echo ========================================
echo   Остановка серверов разработки
echo ========================================
echo.

echo Остановка Laravel сервера...
taskkill /f /im php.exe 2>nul
if %errorlevel% equ 0 (
    echo ✅ Laravel сервер остановлен
) else (
    echo ⚠️ Laravel сервер не был запущен
)

echo.
echo Остановка Vite dev server...
taskkill /f /im node.exe 2>nul
if %errorlevel% equ 0 (
    echo ✅ Vite сервер остановлен
) else (
    echo ⚠️ Vite сервер не был запущен
)

echo.
echo 🛑 Все серверы остановлены
echo.
pause
