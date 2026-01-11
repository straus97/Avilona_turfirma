# 🔒 РУКОВОДСТВО ПО РЕЗЕРВНОМУ КОПИРОВАНИЮ БД

**Дата создания:** 2026-01-11  
**Приоритет:** 🔴 КРИТИЧЕСКИЙ

---

## ⚠️ ВАЖНО!

**ВСЕГДА создавайте резервную копию перед:**
- `php artisan migrate:fresh`
- `php artisan migrate:rollback`
- Любыми изменениями в структуре БД
- Обновлением Laravel или зависимостей

---

## 📋 Способ 1: Через BAT-скрипт (Windows)

### Использование:

1. Откройте `backup-db.bat` в корне проекта
2. **ВАЖНО:** Откройте файл и проверьте путь к `mysqldump.exe`:
   ```bat
   "c:\wamp64\bin\mariadb\mariadb10.4.10\bin\mysqldump.exe"
   ```
3. Настройте под свою версию MariaDB/MySQL
4. Дважды кликните на файл или запустите в терминале:
   ```bash
   .\backup-db.bat
   ```

### Результат:
Бэкап будет сохранен в `database/backups/backup-YYYYMMDD-HHMMSS.sql`

---

## 📋 Способ 2: Через phpMyAdmin (РЕКОМЕНДУЕТСЯ)

### Экспорт:

1. Откройте http://localhost/phpmyadmin5.2.3
2. Выберите базу `avilona_turfirma`
3. Вкладка **"Экспорт"**
4. Выберите метод: **"Быстрый"** или **"Обычный"**
5. Формат: **SQL**
6. Нажмите **"Вперед"**
7. Сохраните файл в `database/backups/` с понятным именем

### Импорт (восстановление):

1. Откройте http://localhost/phpmyadmin5.2.3
2. Выберите базу `avilona_turfirma`
3. Вкладка **"Импорт"**
4. Выберите файл бэкапа
5. Нажмите **"Вперед"**

---

## 📋 Способ 3: Через командную строку

### Экспорт:

```bash
# MariaDB/MySQL (найдите свой путь к mysqldump)
"c:\wamp64\bin\mariadb\mariadb10.4.10\bin\mysqldump.exe" -u root --no-tablespaces avilona_turfirma > database/backups/backup-$(date +%Y%m%d).sql

# Или с паролем
"c:\wamp64\bin\mariadb\mariadb10.4.10\bin\mysqldump.exe" -u root -p --no-tablespaces avilona_turfirma > database/backups/backup.sql
```

### Импорт:

```bash
# MariaDB/MySQL
"c:\wamp64\bin\mariadb\mariadb10.4.10\bin\mysql.exe" -u root avilona_turfirma < database/backups/backup.sql
```

---

## 🔄 Автоматизация

### Создайте Artisan-команду для бэкапа:

```php
// app/Console/Commands/BackupDatabase.php
php artisan make:command BackupDatabase

// Использование:
php artisan backup:db
```

### Добавьте в расписание (scheduler):

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Бэкап каждый день в 3:00
    $schedule->command('backup:db')->daily();
}
```

---

## 📂 Структура папки backups

```
database/
  backups/
    ├── backup-20260111-140530.sql    # Автоматический (по дате-времени)
    ├── backup-before-migration.sql   # Перед миграцией
    ├── backup-production-2026.sql    # Важный бэкап
    └── old-database-backup.sql       # Старый бэкап (уже есть)
```

---

## ✅ Чеклист перед migrate:fresh

- [ ] Создал бэкап БД
- [ ] Проверил, что бэкап создан и файл не пустой
- [ ] Записал, какие данные важны (users, tours, countries_images и т.д.)
- [ ] Имею план восстановления на случай проблем
- [ ] Понимаю, что `migrate:fresh` удалит ВСЕ данные

---

## 🚨 Что делать, если данные удалены?

### Шаг 1: Не паниковать!
Если есть бэкап - данные восстановимы.

### Шаг 2: Восстановить из бэкапа
```bash
# Через phpMyAdmin (рекомендуется)
# 1. Импорт → Выбрать файл → Вперед

# Или через командную строку
mysql -u root avilona_turfirma < database/backups/backup.sql
```

### Шаг 3: Запустить только новые миграции
```bash
php artisan migrate
```

### Шаг 4: Проверить данные
- Зайти на сайт
- Проверить, что страны/направления на месте
- Проверить пользователей
- Проверить заявки

---

## 📊 Что важно сохранять

### Критически важные таблицы:
- ✅ `users` - пользователи
- ✅ `roles` + `role_user` - роли
- ✅ `countries_images` - страны
- ✅ `destination_images` - направления
- ✅ `articles` - статьи
- ✅ `news` - новости
- ✅ `reviews` - отзывы
- ✅ `partners` - партнеры
- ✅ `employees` - сотрудники
- ✅ `awards` - награды
- ✅ `best_offers` - лучшие предложения
- ✅ `our_clients` - наши клиенты

### Пользовательские данные:
- ✅ `bookings` - заявки
- ✅ `messages` - сообщения
- ✅ `tours` - туры
- ✅ `user_documents` - документы пользователей
- ✅ `bonus_accounts` - бонусы

---

## 💡 Рекомендации

1. **Регулярные бэкапы:** Делайте бэкап раз в день/неделю в зависимости от активности
2. **Храните несколько версий:** Не удаляйте старые бэкапы сразу
3. **Тестируйте восстановление:** Периодически проверяйте, что бэкапы работают
4. **Облачное хранение:** Дублируйте важные бэкапы в облако (Google Drive, Dropbox)
5. **Документируйте изменения:** Записывайте, что изменилось в БД после миграций

---

## 🎯 ИТОГ

**ЗОЛОТОЕ ПРАВИЛО:**
> Перед `migrate:fresh` ВСЕГДА делай бэкап через phpMyAdmin!

Это займет 30 секунд, но может сэкономить часы работы. 💾

---

**Последнее обновление:** 2026-01-11  
**Следующая проверка:** При любых изменениях в структуре БД
