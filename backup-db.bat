@echo off
REM Скрипт для бэкапа базы данных перед migrate:fresh
REM Использование: backup-db.bat

echo ===============================================
echo  Резервное копирование базы данных
echo ===============================================
echo.

REM Получаем текущую дату и время
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set datetime=%datetime:~0,8%-%datetime:~8,6%

REM Создаем папку для бэкапов если её нет
if not exist "database\backups" mkdir "database\backups"

REM Имя файла бэкапа
set backup_file=database\backups\backup-%datetime%.sql

echo Создание резервной копии...
echo Файл: %backup_file%
echo.

REM Экспорт базы данных (настройте параметры под свою БД)
REM Для MariaDB/MySQL через WAMP
"c:\wamp64\bin\mariadb\mariadb10.4.10\bin\mysqldump.exe" -u root --no-tablespaces avilona_turfirma > %backup_file%

if %errorlevel% equ 0 (
    echo.
    echo ✓ Резервная копия успешно создана!
    echo Файл сохранен: %backup_file%
    echo.
) else (
    echo.
    echo ✗ Ошибка при создании резервной копии!
    echo Проверьте путь к mysqldump и параметры подключения
    echo.
)

pause
