# 📧 Руководство по Email-уведомлениям

## Обзор

Система автоматических email-уведомлений для туристического агентства "Авилона".

## Типы уведомлений

### 1. Создание заявки (BookingCreated)
- **Когда отправляется**: При создании новой заявки
- **Кому**: Клиенту + всем администраторам
- **Содержит**: Номер заявки, направление, даты, количество туристов, статус

### 2. Изменение статуса (BookingStatusChanged)
- **Когда отправляется**: При изменении статуса заявки
- **Кому**: Клиенту + менеджеру (если назначен)
- **Содержит**: Новый статус, рекомендации по дальнейшим действиям

### 3. Назначение менеджера (ManagerAssigned)
- **Когда отправляется**: При назначении персонального менеджера
- **Кому**: Клиенту + менеджеру
- **Содержит**: Контакты менеджера, ссылка на чат

### 4. Новое сообщение (NewMessageReceived)
- **Когда отправляется**: При получении нового сообщения в чате
- **Кому**: Получателю сообщения
- **Содержит**: Текст сообщения, ссылка на чат

### 5. Напоминание о поездке (TripReminder)
- **Когда отправляется**: За 14, 7, 3 и 1 день до вылета (автоматически)
- **Кому**: Клиенту
- **Содержит**: Детали поездки, чек-лист подготовки

## Настройка

### 1. Конфигурация почты (.env)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@avilona.ru
MAIL_FROM_NAME="Авилона"
```

### 2. Запуск миграций

```bash
php artisan migrate
```

Создаст таблицы `jobs` и `failed_jobs` для очередей.

### 3. Запуск очередей

**Для разработки:**
```bash
php artisan queue:work
```

**Для продакшена (supervisor):**
```ini
[program:avilona-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

### 4. Настройка планировщика (Cron)

Добавьте в crontab:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Это запустит автоматическую отправку напоминаний каждый день в 10:00.

## Тестирование

### Тест всех уведомлений

```bash
php artisan test:email-notifications your-email@example.com
```

Отправит тестовые письма всех типов на указанный адрес.

### Тест напоминаний о поездках

```bash
php artisan bookings:send-trip-reminders
```

Отправит напоминания для всех подтвержденных заявок с датой вылета через 1, 3, 7 или 14 дней.

## Структура файлов

```
app/
├── Mail/                           # Mailable классы
│   ├── BookingCreated.php
│   ├── BookingStatusChanged.php
│   ├── ManagerAssigned.php
│   ├── NewMessageReceived.php
│   └── TripReminder.php
├── Events/                         # События
│   ├── BookingCreated.php
│   ├── BookingStatusChanged.php
│   ├── ManagerAssigned.php
│   └── NewMessageReceived.php
├── Listeners/                      # Слушатели
│   ├── SendBookingCreatedNotification.php
│   ├── SendBookingStatusChangedNotification.php
│   ├── SendManagerAssignedNotification.php
│   └── SendNewMessageNotification.php
└── Console/Commands/
    ├── SendTripReminders.php       # Автоматическая отправка
    └── TestEmailNotifications.php  # Тестирование

resources/views/emails/
├── layout.blade.php                # Базовый макет
├── bookings/
│   ├── created.blade.php
│   ├── status-changed.blade.php
│   ├── manager-assigned.blade.php
│   └── trip-reminder.blade.php
└── messages/
    └── new-message.blade.php
```

## Как это работает

### 1. Автоматическая отправка при создании заявки

```php
// В модели Booking
protected $dispatchesEvents = [
    'created' => \App\Events\BookingCreated::class,
];
```

При создании заявки автоматически вызывается событие `BookingCreated`, которое запускает слушатель `SendBookingCreatedNotification`.

### 2. Отправка при изменении статуса

```php
// В модели Booking
public function confirm(): void
{
    $oldStatus = $this->status;
    $this->update(['status' => self::STATUS_CONFIRMED]);
    
    event(new \App\Events\BookingStatusChanged($this, $oldStatus));
}
```

### 3. Асинхронная отправка через очереди

Все письма отправляются через очереди (метод `queue()` вместо `send()`):

```php
Mail::to($user->email)->queue(new BookingCreated($booking));
```

Это не блокирует выполнение кода и улучшает производительность.

## Дизайн писем

- **Современный градиентный дизайн** (фиолетовый градиент)
- **Адаптивная верстка** (корректно отображается на всех устройствах)
- **Статусные бейджи** с цветовой индикацией
- **Кнопки действий** для быстрого перехода на сайт
- **Информационные блоки** с ключевыми данными
- **Брендинг** с логотипом и контактами компании

## Отладка

### Просмотр очередей

```bash
# Список задач в очереди
php artisan queue:failed

# Повторная попытка выполнения
php artisan queue:retry all

# Очистка неудачных задач
php artisan queue:flush
```

### Логи

Все ошибки отправки записываются в `storage/logs/laravel.log`.

### Использование Mailtrap для тестирования

В `.env` для разработки:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
```

## Рекомендации

1. **Используйте очереди** - никогда не отправляйте письма синхронно
2. **Настройте supervisor** - для автоматического перезапуска воркеров
3. **Мониторьте failed_jobs** - регулярно проверяйте неудачные задачи
4. **Тестируйте на Mailtrap** - перед отправкой реальным пользователям
5. **Используйте rate limiting** - ограничьте количество писем в час
6. **Добавьте unsubscribe** - возможность отписаться от уведомлений

## Будущие улучшения

- [ ] Настройки уведомлений в профиле пользователя
- [ ] SMS-уведомления для критичных событий
- [ ] Push-уведомления в браузере
- [ ] Telegram-бот для уведомлений
- [ ] Статистика открытий и кликов
- [ ] A/B тестирование шаблонов
- [ ] Персонализация контента
- [ ] Многоязычность писем

## Поддержка

При возникновении проблем:
1. Проверьте `.env` конфигурацию
2. Убедитесь, что воркеры очередей запущены
3. Проверьте логи в `storage/logs/`
4. Запустите тестовую команду `php artisan test:email-notifications`
