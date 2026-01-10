# 🚀 Инструкция по настройке Email-уведомлений

## Шаг 1: Настройка Gmail для отправки писем

### 1.1. Включите двухфакторную аутентификацию

1. Перейдите в настройки Google аккаунта: https://myaccount.google.com/
2. Безопасность → Двухэтапная аутентификация
3. Включите двухэтапную аутентификацию

### 1.2. Создайте пароль приложения

1. Перейдите: https://myaccount.google.com/apppasswords
2. Выберите "Почта" и "Другое устройство"
3. Введите название: "Avilona Website"
4. Скопируйте сгенерированный пароль (16 символов)

### 1.3. Настройте .env файл

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=xxxx xxxx xxxx xxxx  # Пароль приложения из шага 1.2
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@avilona.ru
MAIL_FROM_NAME="Авилона"
```

## Шаг 2: Запуск миграций

```bash
php artisan migrate
```

Это создаст таблицы `jobs` и `failed_jobs`.

## Шаг 3: Настройка очередей

### 3.1. В .env установите

```env
QUEUE_CONNECTION=database
```

### 3.2. Запустите воркер очередей

**Для разработки (в отдельном терминале):**
```bash
php artisan queue:work
```

**Для продакшена (через supervisor):**

Создайте файл `/etc/supervisor/conf.d/avilona-worker.conf`:

```ini
[program:avilona-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/avilona/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/avilona/storage/logs/worker.log
stopwaitsecs=3600
```

Затем:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start avilona-queue-worker:*
```

## Шаг 4: Настройка планировщика (для автоматических напоминаний)

### 4.1. Откройте crontab

```bash
crontab -e
```

### 4.2. Добавьте строку

```bash
* * * * * cd /var/www/avilona && php artisan schedule:run >> /dev/null 2>&1
```

Замените `/var/www/avilona` на путь к вашему проекту.

## Шаг 5: Тестирование

### 5.1. Тест всех уведомлений

```bash
php artisan test:email-notifications your-email@example.com
```

Вы должны получить 5 тестовых писем:
1. Создание заявки
2. Изменение статуса
3. Назначение менеджера
4. Напоминание о поездке
5. Новое сообщение

### 5.2. Проверка очередей

```bash
# Просмотр задач в очереди
php artisan queue:failed

# Повторная попытка
php artisan queue:retry all
```

## Альтернатива: Mailtrap для тестирования

Если вы хотите тестировать без реальной отправки писем:

1. Зарегистрируйтесь на https://mailtrap.io/
2. Создайте inbox
3. Скопируйте SMTP credentials
4. В .env:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
```

## Проверка работы

### Создайте тестовую заявку

1. Зарегистрируйтесь на сайте
2. Создайте заявку на тур
3. Проверьте почту - должно прийти письмо "Заявка успешно создана"

### Проверьте логи

```bash
tail -f storage/logs/laravel.log
```

## Решение проблем

### Письма не отправляются

1. Проверьте, что воркер очередей запущен:
   ```bash
   ps aux | grep queue:work
   ```

2. Проверьте таблицу `jobs`:
   ```sql
   SELECT * FROM jobs;
   ```

3. Проверьте `failed_jobs`:
   ```sql
   SELECT * FROM failed_jobs;
   ```

### Ошибка аутентификации Gmail

- Убедитесь, что включена двухфакторная аутентификация
- Используйте пароль приложения, а не обычный пароль
- Проверьте, что нет лишних пробелов в .env

### Письма попадают в спам

1. Настройте SPF запись для домена
2. Настройте DKIM
3. Используйте корпоративную почту вместо Gmail
4. Добавьте unsubscribe ссылку

## Рекомендации для продакшена

1. **Используйте профессиональный SMTP сервис:**
   - SendGrid (бесплатно до 100 писем/день)
   - Mailgun (бесплатно до 5000 писем/месяц)
   - Amazon SES (очень дешево)

2. **Настройте мониторинг:**
   - Laravel Horizon для визуализации очередей
   - Sentry для отслеживания ошибок

3. **Резервное копирование:**
   - Регулярно делайте backup таблицы `failed_jobs`

4. **Rate limiting:**
   - Ограничьте количество писем в час
   - Используйте throttling для предотвращения спама

## Пример конфигурации для SendGrid

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@avilona.ru
MAIL_FROM_NAME="Авилона"
```

## Готово! 🎉

Теперь ваша система email-уведомлений полностью настроена и готова к работе.
