# TODO - Список задач для проекта "Авилона"

## 🎨 Дизайн и интерфейс

### ⚠️ Личный кабинет - ПОЛНАЯ ПЕРЕДЕЛКА
**Приоритет:** Высокий  
**Статус:** Запланировано

**Что нужно сделать:**
- Полностью переделать функционал личного кабинета
- Новый дизайн в светлых теплых тонах
- Улучшить расположение элементов и навигацию
- Сделать более интуитивный и удобный интерфейс

**Что должно быть:**
- Кабинет туриста (просмотр заявок, чат с менеджером, документы)
- Кабинет менеджера (управление клиентами, заявки, статистика)
- Админ панель (управление пользователями, ролями, контентом)

**Цветовая схема:**
- Светлые теплые тона
- Приятная глазу палитра
- Современный минималистичный дизайн

---

## 📋 Представления для создания

### Дашборды
- [ ] `resources/views/manager/dashboard.blade.php` - Дашборд менеджера
- [ ] `resources/views/admin/dashboard.blade.php` - Админ панель
- [ ] Переделать `resources/views/profile/account.blade.php` - Личный кабинет туриста

### Макеты
- [ ] `resources/views/layouts/manager.blade.php` - Макет для менеджера
- [ ] `resources/views/layouts/admin.blade.php` - Макет для админа
- [ ] Переделать `resources/views/layouts/app.blade.php` - Макет для личного кабинета

---

## 🗄️ Модели для создания

### Приоритет 1 (Критичные)
- [ ] **Tour** - Модель туров
  - Поля: title, description, price, departure_city, destination_country, dates, hotel, etc.
  - Связи: belongsTo Country, hasMany Bookings, hasMany Reviews
  
- [ ] **Booking** - Модель заявок/бронирований
  - Поля: user_id, tour_id, manager_id, status, dates, passengers, total_price
  - Статусы: new, progress, confirmed, cancelled, completed
  - Связи: belongsTo User, Tour, Manager; hasMany Messages
  
- [ ] **Message** - Модель сообщений в чате
  - Поля: booking_id, sender_id, receiver_id, message, is_read
  - Связи: belongsTo Booking, User (sender), User (receiver)

### Приоритет 2 (Важные)
- [ ] **Country** - Модель стран
  - Поля: name, slug, code, description, image_url, is_active
  - Связи: hasMany Tours
  
- [ ] **Hotel** - Модель отелей
  - Поля: name, country, city, stars, description, facilities
  - Связи: hasMany Tours

---

## 🎯 Следующие этапы по дорожной карте

### Этап 2: Базовая инфраструктура (ТЕКУЩИЙ)
- [x] Система аутентификации и ролей
- [ ] База данных и модели
- [ ] Frontend инфраструктура

### Этап 3: Поисковый виджет туров
- [ ] Создание модели Tour
- [ ] API для поиска туров
- [ ] Интерфейс виджета
- [ ] Фильтрация и сортировка

### Этап 4: Система заявок и бронирования
- [ ] Модель Booking
- [ ] Управление заявками
- [ ] Email/Telegram уведомления
- [ ] Система чатов

---

## 📝 Заметки

- Middleware для ролей работает корректно ✅
- Тестовые пользователи созданы ✅
- Маршруты защищены по ролям ✅
- Представления будут созданы по мере необходимости

