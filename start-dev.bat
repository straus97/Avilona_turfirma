@echo off
chcp 65001 >nul
echo ========================================
echo    Starting Avilona Development Server
echo ========================================
echo.

echo Checking dependencies...
if not exist "vendor" (
    echo Installing PHP dependencies...
    C:\wamp\bin\php\php8.4.13\php.exe C:\wamp\bin\composer\composer.phar install
)

if not exist "node_modules" (
    echo Installing Node.js dependencies...
    npm install
)

echo.
echo Checking .env file...
if not exist ".env" (
    echo Creating .env file...
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
    echo .env file created!
    echo.
    echo Generating application key...
    C:\wamp\bin\php\php8.4.13\php.exe artisan key:generate
)

echo.
echo Optimizing Laravel for production...
C:\wamp\bin\php\php8.4.13\php.exe artisan config:cache
C:\wamp\bin\php\php8.4.13\php.exe artisan route:cache
C:\wamp\bin\php\php8.4.13\php.exe artisan view:cache

echo.
echo Starting Laravel server...
echo.

echo Opening Laravel server at http://localhost:8000
start "Laravel Server - Avilona" cmd /k "cd /d %~dp0 && C:\wamp\bin\php\php8.4.13\php.exe artisan serve --host=127.0.0.1 --port=8000"

timeout /t 3 /nobreak >nul

echo.
echo ========================================
echo    Server started successfully!
echo ========================================
echo.
echo Website: http://localhost:8000
echo.
echo To stop the server, close the terminal window
echo or press Ctrl+C in the server window
echo.
echo Press any key to exit this window...
pause >nul
