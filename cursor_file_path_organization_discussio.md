# File path organization discussion
_Exported on 10/17/2025 at 09:46:04 GMT+3 from Cursor (1.7.46)_

---

**User**

@начало.txt @docs/ @.cursor/ 

---

**Cursor**

Анализирую проект туристического агентства "Авилона" и создам необходимую документацию и правила для эффективной разработки.


Теперь создам документацию проекта и оптимизирую правила:


```markdown
# Туристическое агентство "Авилона" - Документация проекта

## 📋 Обзор проекта

Проект представляет собой многофункциональный веб-сайт туристического агентства "Авилона", построенный на Laravel 10 с использованием современных технологий веб-разработки.

### 🎯 Основные цели
- Создание удобного интерфейса для поиска и бронирования туров
- Реализация системы управления пользователями с ролевой моделью
- Автоматизация процессов обработки заявок и уведомлений
- Обеспечение SEO-оптимизации и мобильной адаптивности

## 🛠 Технологический стек

### Backend
- **PHP 8.0+** - основной язык программирования
- **Laravel 10** - веб-фреймворк
- **MySQL** - база данных
- **Laravel Sanctum** - аутентификация API
- **PHPMailer** - отправка email
- **Spatie Sluggable** - генерация URL-слаг

### Frontend
- **Bootstrap 5** - CSS фреймворк
- **TailwindCSS** - utility-first CSS
- **Alpine.js** - легковесный JavaScript фреймворк
- **Vite** - сборщик модулей
- **Sass** - препроцессор CSS

### Дополнительные пакеты
- **Mews Captcha** - защита от спама
- **Jenssegers Date** - локализация дат
- **Laravel UI** - компоненты интерфейса

## 📁 Структура проекта

```
app/
├── Console/Commands/     # Artisan команды
├── Http/
│   ├── Controllers/      # Контроллеры (34 файла)
│   ├── Middleware/       # Промежуточное ПО
│   ├── Requests/         # Валидация запросов
│   └── Filters/          # Фильтры
├── Models/               # Eloquent модели
├── Mail/                 # Email классы
└── View/Components/      # Blade компоненты

resources/
├── views/                # Blade шаблоны (82 файла)
├── css/                  # Стили
├── js/                   # JavaScript
└── sass/                 # Sass файлы

public/
├── build/                # Собранные ресурсы
├── img/                  # Изображения (213 файлов)
├── documents/            # Документы для скачивания
└── plugins/              # Внешние библиотеки
```

## 🚀 Быстрый старт

### Требования
- PHP 8.0+
- Composer
- Node.js 16+
- MySQL 5.7+

### Установка
1. Клонировать репозиторий
2. Установить зависимости: `composer install && npm install`
3. Настроить `.env` файл
4. Запустить миграции: `php artisan migrate`
5. Собрать ресурсы: `npm run build`

## 📊 Функциональность

### Для неавторизованных пользователей
- Просмотр информации о компании
- Поиск туров через виджет
- Просмотр отзывов и новостей
- Отправка заявок через формы

### Для авторизованных пользователей
- Личный кабинет с заявками
- Чат с менеджерами
- Загрузка/скачивание документов
- История бронирований

### Для администраторов
- Панель управления сайтом
- Управление пользователями и ролями
- Модерация контента
- Статистика и отчеты
- Уведомления в Telegram

## 🔧 Конфигурация

### Основные настройки
- Локаль: русский (`ru`)
- Часовой пояс: UTC
- Режим отладки: настраивается через `.env`

### Кэширование
- Используется middleware `cache.response` для статических страниц
- Настроено кэширование конфигурации и маршрутов

## 📝 Следующие шаги

1. Оптимизация существующего кода
2. Реализация поискового виджета туров
3. Интеграция с Telegram API
4. Настройка автоматических уведомлений
5. Мобильная адаптация
6. SEO оптимизация

## 🔗 Полезные ссылки

- [Laravel Documentation](https://laravel.com/docs)
- [Bootstrap Documentation](https://getbootstrap.com/docs)
- [TailwindCSS Documentation](https://tailwindcss.com/docs)
- [Alpine.js Documentation](https://alpinejs.dev/)
```

```markdown
# Настройка среды разработки

## 🖥 Системные требования

### Минимальные требования
- **ОС**: Windows 10/11, macOS 10.15+, Ubuntu 18.04+
- **RAM**: 4GB (рекомендуется 8GB+)
- **Диск**: 2GB свободного места
- **Процессор**: 2 ядра (рекомендуется 4+)

### Необходимое ПО
- **PHP 8.0+** с расширениями: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML
- **Composer** - менеджер зависимостей PHP
- **Node.js 16+** и npm
- **MySQL 5.7+** или **MariaDB 10.3+**
- **Git** для версионного контроля

## 📦 Установка на Windows

### 1. Установка PHP
```bash
# Скачать PHP 8.1+ с https://windows.php.net/download/
# Распаковать в C:\php
# Добавить C:\php в PATH
```

### 2. Установка Composer
```bash
# Скачать с https://getcomposer.org/download/
# Установить глобально
composer --version
```

### 3. Установка Node.js
```bash
# Скачать с https://nodejs.org/
# Установить LTS версию
node --version
npm --version
```

### 4. Установка MySQL
```bash
# Скачать MySQL Community Server с https://dev.mysql.com/downloads/
# Или использовать XAMPP/WAMP для локальной разработки
```

## 🚀 Настройка проекта

### 1. Клонирование и установка зависимостей
```bash
git clone <repository-url> avilona-turfirma
cd avilona-turfirma
composer install
npm install
```

### 2. Настройка окружения
```bash
# Копировать файл окружения
cp .env.example .env

# Сгенерировать ключ приложения
php artisan key:generate
```

### 3. Настройка базы данных
```bash
# Создать базу данных MySQL
mysql -u root -p
CREATE DATABASE avilona_turfirma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Запустить миграции
php artisan migrate

# Заполнить тестовыми данными (опционально)
php artisan db:seed
```

### 4. Настройка почты (для разработки)
```bash
# В .env файле настроить:
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

## 🔧 Автоматизация разработки

### 1. Автоматический запуск сервера
```bash
# Создать скрипт start-dev.bat (Windows)
@echo off
echo Запуск сервера разработки...
start "Laravel Server" php artisan serve
start "Vite Dev Server" npm run dev
echo Серверы запущены!
pause
```

### 2. Настройка hot reload
```bash
# В package.json добавить скрипт:
"scripts": {
    "dev": "vite",
    "build": "vite build",
    "watch": "vite build --watch"
}
```

### 3. Автоматическая сборка при изменениях
```bash
# Запуск в режиме наблюдения
npm run watch
```

## 🛠 Полезные команды

### Laravel Artisan
```bash
# Очистка кэша
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Оптимизация для продакшена
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Создание компонентов
php artisan make:controller ControllerName
php artisan make:model ModelName -m
php artisan make:migration migration_name
```

### Composer
```bash
# Обновление зависимостей
composer update

# Автозагрузка
composer dump-autoload
```

### NPM
```bash
# Установка зависимостей
npm install

# Сборка для продакшена
npm run build

# Разработка с hot reload
npm run dev
```

## 🔍 Отладка

### 1. Включение режима отладки
```bash
# В .env файле:
APP_DEBUG=true
APP_ENV=local
```

### 2. Логирование
```bash
# Просмотр логов
tail -f storage/logs/laravel.log

# Очистка логов
php artisan log:clear
```

### 3. Профилирование
```bash
# Установка Laravel Debugbar (опционально)
composer require barryvdh/laravel-debugbar --dev
```

## 📱 Мобильная разработка

### 1. Тестирование на мобильных устройствах
```bash
# Запуск сервера с доступом из сети
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Responsive тестирование
- Использовать Chrome DevTools
- Тестировать на реальных устройствах
- Проверять на разных разрешениях экрана

## 🚨 Устранение неполадок

### Частые проблемы
1. **Ошибка "Class not found"** - запустить `composer dump-autoload`
2. **Ошибка миграций** - проверить подключение к БД в `.env`
3. **Проблемы с правами** - настроить права на папки `storage/` и `bootstrap/cache/`
4. **Ошибки сборки** - очистить кэш npm: `npm cache clean --force`

### Проверка установки
```bash
# Проверить все компоненты
php --version
composer --version
node --version
npm --version
mysql --version

# Проверить Laravel
php artisan --version
php artisan route:list
```
```

```markdown
# Дорожная карта проекта "Авилона"

## 🎯 Этап 1: Анализ и оптимизация (Текущий)

### ✅ Завершено
- [x] Анализ существующей структуры проекта
- [x] Создание базовой документации
- [x] Определение технологического стека

### 🔄 В процессе
- [ ] Оптимизация правил .cursor/
- [ ] Настройка среды разработки
- [ ] Настройка GitHub и автоматизации

### 📋 Планируется
- [ ] Аудит существующего кода
- [ ] Рефакторинг устаревших компонентов
- [ ] Оптимизация производительности

## 🏗 Этап 2: Базовая инфраструктура

### 🔧 Система аутентификации и ролей
- [ ] Улучшение модели пользователей
- [ ] Реализация ролевой системы (admin, manager, tourist)
- [ ] Настройка middleware для ролей
- [ ] Система подтверждения email

### 📊 База данных и модели
- [ ] Оптимизация существующих моделей
- [ ] Создание недостающих моделей (Tours, Bookings, Messages)
- [ ] Настройка связей между моделями
- [ ] Миграции для новых таблиц

### 🎨 Frontend инфраструктура
- [ ] Настройка TailwindCSS
- [ ] Создание базовых Blade компонентов
- [ ] Адаптивная верстка для мобильных устройств
- [ ] Оптимизация загрузки изображений

## 🔍 Этап 3: Поисковый виджет туров

### 🎯 Основной функционал
- [ ] Создание модели Tour с полями:
  - Город отправления/прибытия
  - Даты заезда/выезда
  - Количество ночей
  - Тип питания
  - Звездность отеля
  - Цена
- [ ] API для поиска туров
- [ ] Фильтрация по параметрам
- [ ] Пагинация результатов

### 🔌 Интеграции
- [ ] Подключение к базам туроператоров
- [ ] API для получения актуальных цен
- [ ] Синхронизация данных отеля
- [ ] Кэширование результатов поиска

### 🎨 Интерфейс
- [ ] Интерактивный виджет поиска
- [ ] Карточки туров с изображениями
- [ ] Модальные окна с деталями
- [ ] Система сравнения туров

## 📝 Этап 4: Система заявок и бронирования

### 📋 Управление заявками
- [ ] Модель Booking для заявок
- [ ] Статусы заявок (новая, в обработке, подтверждена, отменена)
- [ ] Привязка заявок к менеджерам
- [ ] История изменений заявок

### 📧 Уведомления
- [ ] Email уведомления для клиентов
- [ ] Telegram уведомления для администраторов
- [ ] SMS уведомления (опционально)
- [ ] Push уведомления в браузере

### 💬 Система чатов
- [ ] Модель Message для сообщений
- [ ] WebSocket для real-time чата
- [ ] Файловые вложения в чатах
- [ ] История переписок

## 👥 Этап 5: Личные кабинеты

### 🏠 Кабинет туриста
- [ ] Просмотр заявок и их статусов
- [ ] Чат с назначенным менеджером
- [ ] Загрузка документов
- [ ] История бронирований
- [ ] Профиль пользователя

### 👨‍💼 Кабинет менеджера
- [ ] Список назначенных клиентов
- [ ] Управление заявками
- [ ] Чат с клиентами
- [ ] Подготовка документов
- [ ] Статистика работы

### 🔧 Админ панель
- [ ] Управление пользователями и ролями
- [ ] Модерация контента (отзывы, новости)
- [ ] Статистика и аналитика
- [ ] Управление турами и направлениями
- [ ] Системные настройки

## 📰 Этап 6: Контент-менеджмент

### 📄 Управление страницами
- [ ] Редактор для статических страниц
- [ ] SEO оптимизация страниц
- [ ] Мультиязычность (русский/английский)
- [ ] Система мета-тегов

### 📰 Новости и статьи
- [ ] CRUD для новостей
- [ ] RSS лента новостей
- [ ] Автоматическое получение новостей
- [ ] Категории и теги

### ⭐ Отзывы и рейтинги
- [ ] Система модерации отзывов
- [ ] Рейтинговая система
- [ ] Ответы на отзывы
- [ ] Интеграция с внешними платформами

## 🔧 Этап 7: Оптимизация и безопасность

### ⚡ Производительность
- [ ] Кэширование страниц и запросов
- [ ] Оптимизация изображений
- [ ] Минификация CSS/JS
- [ ] CDN для статических файлов

### 🔒 Безопасность
- [ ] Защита от SQL инъекций
- [ ] CSRF защита
- [ ] Rate limiting для API
- [ ] Логирование безопасности

### 📊 Аналитика
- [ ] Google Analytics интеграция
- [ ] Яндекс.Метрика
- [ ] Отслеживание конверсий
- [ ] A/B тестирование

## 🚀 Этап 8: Деплой и мониторинг

### 🌐 Развертывание
- [ ] Настройка продакшен сервера
- [ ] CI/CD пайплайн
- [ ] Автоматические бэкапы
- [ ] SSL сертификаты

### 📈 Мониторинг
- [ ] Логирование ошибок
- [ ] Мониторинг производительности
- [ ] Алерты о проблемах
- [ ] Статистика использования

## 📅 Временные рамки

| Этап | Длительность | Приоритет |
|------|-------------|-----------|
| Этап 1 | 1-2 недели | Высокий |
| Этап 2 | 2-3 недели | Высокий |
| Этап 3 | 3-4 недели | Критический |
| Этап 4 | 2-3 недели | Высокий |
| Этап 5 | 3-4 недели | Высокий |
| Этап 6 | 2-3 недели | Средний |
| Этап 7 | 2-3 недели | Средний |
| Этап 8 | 1-2 недели | Низкий |

## 🎯 Критерии готовности

### MVP (Минимально жизнеспособный продукт)
- [ ] Работающий поиск туров
- [ ] Система заявок
- [ ] Базовые личные кабинеты
- [ ] Мобильная адаптация

### Полная версия
- [ ] Все функции из технического задания
- [ ] Высокая производительность
- [ ] Полная безопасность
- [ ] Готовность к продакшену

## 🔄 Итерационный подход

Каждый этап разбивается на спринты по 1-2 недели:
1. **Планирование** - определение задач спринта
2. **Разработка** - реализация функционала
3. **Тестирование** - проверка работоспособности
4. **Деплой** - развертывание на тестовом сервере
5. **Ретроспектива** - анализ результатов и планирование следующего спринта
```

```markdown
# Техническое задание - Туристическое агентство "Авилона"

## 📋 Общее описание

Создание многофункционального веб-сайта туристического агентства с системой управления пользователями, поиском туров и автоматизацией бизнес-процессов.

## 🎯 Целевая аудитория

### Неавторизованные пользователи
- Просмотр информации о компании
- Поиск туров через виджет
- Просмотр отзывов и новостей
- Отправка заявок через формы

### Авторизованные пользователи (Туристы)
- Личный кабинет с заявками
- Чат с менеджерами
- Загрузка/скачивание документов
- История бронирований

### Менеджеры
- Управление назначенными клиентами
- Обработка заявок
- Чат с клиентами
- Подготовка документов

### Администраторы
- Полное управление сайтом
- Управление пользователями и ролями
- Модерация контента
- Статистика и аналитика

## 🏗 Архитектура системы

### Backend (Laravel 10)
- **Модели**: User, Role, Tour, Booking, Message, Review, News, Article
- **Контроллеры**: RESTful API + Web контроллеры
- **Middleware**: Auth, Role, Cache, Rate Limiting
- **События**: Email уведомления, Telegram уведомления

### Frontend
- **Blade шаблоны** с компонентами
- **Bootstrap 5** + **TailwindCSS**
- **Alpine.js** для интерактивности
- **Vite** для сборки ресурсов

### База данных (MySQL)
- Нормализованная структура
- Индексы для оптимизации поиска
- Связи между таблицами

## 📄 Функциональные требования

### 1. Главная страница

#### Поисковый виджет туров
**Приоритет: Критический**

**Функционал:**
- Город отправления (автокомплит)
- Страна/город прибытия (автокомплит)
- Даты заезда/выезда (календарь)
- Количество туристов (взрослые/дети)
- Звездность отеля (1-5 звезд)
- Тип питания (BB, HB, FB, AI, UAI)
- Ценовой диапазон
- Специальные предложения

**Технические требования:**
- AJAX поиск без перезагрузки страницы
- Кэширование результатов на 15 минут
- Пагинация результатов (12 туров на страницу)
- Сортировка по цене, рейтингу, дате
- Фильтрация по дополнительным параметрам

**API Endpoints:**
```
GET /api/tours/search - поиск туров
GET /api/cities - список городов
GET /api/countries - список стран
GET /api/hotels - список отелей по стране
```

#### Блок лучших предложений
**Приоритет: Высокий**

**Функционал:**
- Карточки туров с изображениями
- Краткое описание и цена
- Кнопка "Забронировать"
- Форма быстрой заявки

**Технические требования:**
- Адаптивная сетка (3-4 карточки на десктопе, 1-2 на мобильном)
- Lazy loading изображений
- Анимации при наведении

#### Блок отзывов
**Приоритет: Средний**

**Функционал:**
- Последние 6 отзывов
- Сокращенный текст (150 символов)
- Кнопка "Читать полностью"
- Рейтинг звездами
- Фото автора отзыва

#### Блок новостей
**Приоритет: Средний**

**Функционал:**
- Последние 4 новости
- Изображение, заголовок, краткое описание
- Дата публикации
- Кнопка "Читать далее"

#### Блок партнеров
**Приоритет: Низкий**

**Функционал:**
- Логотипы партнеров
- Ссылки на сайты партнеров
- Слайдер для большого количества партнеров

#### Контактная информация
**Приоритет: Средний**

**Функционал:**
- Информация о компании
- Форма обратной связи
- Валидация полей
- Captcha защита

#### Карта местонахождения
**Приоритет: Низкий**

**Функционал:**
- Интерактивная карта
- Маркер с адресом офиса
- Информационное окно с контактами

### 2. Страница "Компания"

#### 2.1 О компании
**Приоритет: Средний**

**Контент:**
- История компании
- Миссия и ценности
- Важные документы (PDF)
- Основные направления (ссылки)
- Перспективы развития
- Качество услуг
- Условия возврата
- Преимущества

#### 2.2 Сотрудники
**Приоритет: Средний**

**Функционал:**
- Карточки сотрудников
- Фото, ФИО, должность
- Краткая биография
- Контакты для связи
- Социальные сети

#### 2.3 Награды
**Приоритет: Низкий**

**Функционал:**
- Галерея сертификатов
- Лицензии и дипломы
- Награды и достижения
- Lightbox для просмотра

### 3. Страница "Страны"

**Приоритет: Высокий**

**Функционал:**
- Краткая информация о странах
- Памятка выезжающего за рубеж
- Фильтр по странам
- Карточки стран с флагами
- Детальная страница страны:
  - Информация о стране
  - Культура и традиции
  - Национальные блюда
  - Достопримечательности
  - Специфическая памятка

### 4. Страница "Направления"

**Приоритет: Высокий**

**Функционал:**
- Краткая информация о направлениях
- Карточки направлений
- Детальная страница направления:
  - Описание направления
  - Популярные туры
  - Сезонность
  - Боковое меню с другими направлениями

### 5. Страница "Контакты"

**Приоритет: Высокий**

**Контент:**
- Полная контактная информация
- Адрес офиса
- Телефоны и email
- Режим работы
- Реквизиты компании
- Важные документы
- Форма обратной связи

### 6. Страница "Отзывы"

**Приоритет: Высокий**

**Функционал:**
- Список всех отзывов
- Пагинация (10 отзывов на страницу)
- Сокращенный текст с кнопкой "Читать полностью"
- Форма добавления отзыва
- Модерация отзывов администратором
- Рейтинговая система

### 7. Страница "Полезная информация"

#### 7.1 Интересные статьи
**Приоритет: Средний**

**Функционал:**
- CRUD для статей
- Категории статей
- Теги
- Поиск по статьям
- Пагинация

#### 7.2 Новости
**Приоритет: Средний**

**Функционал:**
- Автоматическое получение новостей
- RSS лента
- Категории новостей
- Пагинация (6 новостей на страницу)
- Сортировка по дате

#### 7.3 Специальные предложения
**Приоритет: Средний**

**Функционал:**
- Карточки продуктов
- Детальные страницы
- Категории предложений
- Форма заявки на продукт

#### 7.4 Туристический словарь
**Приоритет: Низкий**

**Функционал:**
- Поиск по терминам
- Алфавитная навигация
- Раскрывающиеся списки
- Категории терминов

### 8. Система авторизации

**Приоритет: Критический**

**Функционал:**
- Регистрация с подтверждением email
- Авторизация через email/пароль
- Восстановление пароля
- Ролевая система (tourist, manager, admin)
- Middleware для проверки ролей

**Поля регистрации:**
- ФИО (обязательно)
- Email (обязательно, уникальный)
- Телефон (обязательно)
- Пароль (минимум 8 символов)

**Дополнительные поля в профиле:**
- Паспортные данные
- Дата рождения
- Адрес
- Загруженные документы

### 9. Личные кабинеты

#### 9.1 Кабинет туриста
**Приоритет: Высокий**

**Функционал:**
- Просмотр заявок и их статусов
- Чат с назначенным менеджером
- Загрузка документов
- История бронирований
- Редактирование профиля
- Уведомления

#### 9.2 Кабинет менеджера
**Приоритет: Высокий**

**Функционал:**
- Список назначенных клиентов
- Управление заявками
- Чат с клиентами
- Подготовка документов
- Статистика работы
- Календарь встреч

#### 9.3 Админ панель
**Приоритет: Высокий**

**Функционал:**
- Управление пользователями
- Назначение ролей
- Модерация контента
- Статистика сайта
- Управление турами
- Системные настройки
- Логи системы

### 10. Система заявок и бронирования

**Приоритет: Критический**

**Модель Booking:**
```php
- id
- user_id (связь с User)
- manager_id (связь с User)
- tour_id (связь с Tour)
- status (new, processing, confirmed, cancelled)
- contact_info (JSON)
- preferences (JSON)
- created_at
- updated_at
```

**Статусы заявок:**
- `new` - новая заявка
- `processing` - в обработке
- `confirmed` - подтверждена
- `cancelled` - отменена

**Функционал:**
- Создание заявки из виджета поиска
- Назначение менеджера администратором
- Уведомления в Telegram
- История изменений статуса
- Прикрепление документов

### 11. Система чатов

**Приоритет: Высокий**

**Модель Message:**
```php
- id
- booking_id (связь с Booking)
- sender_id (связь с User)
- receiver_id (связь с User)
- message (текст)
- attachments (JSON)
- is_read (boolean)
- created_at
```

**Функционал:**
- Real-time чат через WebSocket
- Файловые вложения
- История сообщений
- Уведомления о новых сообщениях
- Статус прочтения

### 12. Уведомления

**Приоритет: Высокий**

**Типы уведомлений:**
- Email подтверждение регистрации
- Email уведомления о статусе заявки
- Telegram уведомления для админов
- Push уведомления в браузере

**Telegram интеграция:**
- Бот для уведомлений
- Webhook для получения сообщений
- Команды для управления

## 🔧 Технические требования

### Производительность
- Время загрузки страницы < 3 секунд
- Время ответа API < 500ms
- Кэширование статических страниц
- Оптимизация изображений
- CDN для статических файлов

### Безопасность
- HTTPS на всех страницах
- CSRF защита
- SQL injection защита
- Rate limiting для API
- Валидация всех входных данных
- Логирование безопасности

### SEO оптимизация
- Semantic HTML разметка
- Meta теги для каждой страницы
- Sitemap.xml
- Robots.txt
- Open Graph теги
- Schema.org разметка

### Мобильная адаптация
- Responsive дизайн
- Touch-friendly интерфейс
- Оптимизация для мобильных устройств
- Быстрая загрузка на мобильном

## 📊 Нефункциональные требования

### Масштабируемость
- Поддержка до 1000 одновременных пользователей
- Горизонтальное масштабирование
- Кэширование на уровне приложения
- Оптимизация запросов к БД

### Надежность
- Uptime 99.9%
- Автоматические бэкапы
- Мониторинг ошибок
- Graceful degradation

### Совместимость
- Браузеры: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- Мобильные браузеры: iOS Safari 14+, Chrome Mobile 90+
- Разрешения: от 320px до 2560px

## 🧪 Тестирование

### Типы тестов
- Unit тесты для моделей и сервисов
- Feature тесты для API
- Browser тесты для критических пользовательских сценариев
- Performance тесты

### Покрытие тестами
- Минимум 80% покрытие кода
- 100% покрытие критических функций
- Тесты для всех API endpoints

## 📈 Метрики успеха

### Пользовательские метрики
- Время на сайте > 3 минут
- Bounce rate < 40%
- Конверсия в заявки > 5%
- Удовлетворенность пользователей > 4.5/5

### Технические метрики
- Время загрузки < 3 секунд
- Uptime > 99.9%
- Ошибки < 0.1%
- Время ответа API < 500ms
```

Теперь оптимизирую правила .cursor/ для проекта Laravel:


```markdown
# Laravel Development Rules

## 🎯 Общие принципы

### Архитектура
- Следуй принципам MVC (Model-View-Controller)
- Используй Service Layer для бизнес-логики
- Применяй Repository Pattern для работы с данными
- Реализуй SOLID принципы

### Структура проекта
- Модели в `app/Models/`
- Контроллеры в `app/Http/Controllers/`
- Сервисы в `app/Services/`
- Репозитории в `app/Repositories/`
- Middleware в `app/Http/Middleware/`
- Requests в `app/Http/Requests/`

## 📝 Кодирование

### PHP стандарты
- Следуй PSR-12 стандарту кодирования
- Используй строгую типизацию (type hints)
- Применяй DocBlock комментарии
- Именуй методы в camelCase
- Именуй классы в PascalCase

### Laravel конвенции
- Используй Eloquent ORM для работы с БД
- Применяй Laravel Collections
- Используй Form Requests для валидации
- Применяй Resource Controllers
- Используй Laravel Events и Listeners

### Примеры кода

#### Модель
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    protected $fillable = [
        'title',
        'description',
        'price',
        'departure_city',
        'destination_country',
        'start_date',
        'end_date',
        'hotel_stars',
        'meal_type',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

#### Контроллер
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TourSearchRequest;
use App\Services\TourSearchService;
use Illuminate\Http\JsonResponse;

class TourController extends Controller
{
    public function __construct(
        private TourSearchService $tourSearchService
    ) {}

    public function search(TourSearchRequest $request): JsonResponse
    {
        $tours = $this->tourSearchService->search($request->validated());
        
        return response()->json([
            'data' => $tours,
            'meta' => [
                'total' => $tours->total(),
                'per_page' => $tours->perPage(),
            ]
        ]);
    }
}
```

#### Form Request
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TourSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure_city' => 'required|string|max:255',
            'destination_country' => 'required|string|max:255',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'adults' => 'required|integer|min:1|max:10',
            'children' => 'integer|min:0|max:10',
            'hotel_stars' => 'integer|min:1|max:5',
            'meal_type' => 'string|in:BB,HB,FB,AI,UAI',
            'price_min' => 'numeric|min:0',
            'price_max' => 'numeric|min:0|gte:price_min',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.after' => 'Дата начала должна быть в будущем',
            'end_date.after' => 'Дата окончания должна быть после даты начала',
        ];
    }
}
```

## 🗄 База данных

### Миграции
- Используй описательные имена миграций
- Добавляй индексы для часто используемых полей
- Используй foreign key constraints
- Применяй soft deletes где необходимо

### Пример миграции
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('departure_city');
            $table->string('destination_country');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('hotel_stars')->nullable();
            $table->enum('meal_type', ['BB', 'HB', 'FB', 'AI', 'UAI'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['departure_city', 'destination_country']);
            $table->index(['start_date', 'end_date']);
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
```

### Eloquent отношения
- Используй правильные типы отношений
- Применяй eager loading для оптимизации
- Используй accessors и mutators
- Применяй scopes для часто используемых запросов

## 🎨 Frontend (Blade)

### Blade шаблоны
- Используй компоненты для переиспользуемых элементов
- Применяй layouts для общей структуры
- Используй includes для повторяющихся блоков
- Применяй директивы Blade для логики

### Пример компонента
```blade
{{-- resources/views/components/tour-card.blade.php --}}
@props(['tour'])

<div class="tour-card bg-white rounded-lg shadow-md overflow-hidden">
    <img src="{{ $tour->image_url }}" alt="{{ $tour->title }}" class="w-full h-48 object-cover">
    
    <div class="p-4">
        <h3 class="text-lg font-semibold mb-2">{{ $tour->title }}</h3>
        <p class="text-gray-600 text-sm mb-3">{{ Str::limit($tour->description, 100) }}</p>
        
        <div class="flex justify-between items-center">
            <span class="text-2xl font-bold text-blue-600">{{ number_format($tour->price) }} ₽</span>
            <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Забронировать
            </button>
        </div>
    </div>
</div>
```

### Использование компонента
```blade
@foreach($tours as $tour)
    <x-tour-card :tour="$tour" />
@endforeach
```

## 🔒 Безопасность

### Валидация
- Всегда валидируй входные данные
- Используй Form Requests
- Применяй sanitization
- Используй prepared statements

### Аутентификация
- Используй Laravel Sanctum для API
- Применяй middleware для защиты маршрутов
- Используй rate limiting
- Применяй CSRF защиту

### Пример middleware
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserRole
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        if (!$request->user() || !$request->user()->hasRole($role)) {
            abort(403, 'Доступ запрещен');
        }

        return $next($request);
    }
}
```

## ⚡ Производительность

### Кэширование
- Используй кэш для часто запрашиваемых данных
- Применяй query caching
- Используй view caching
- Применяй route caching

### Оптимизация запросов
- Используй eager loading
- Применяй lazy loading где необходимо
- Используй database indexes
- Применяй pagination

### Пример оптимизации
```php
// Плохо - N+1 проблема
$tours = Tour::all();
foreach ($tours as $tour) {
    echo $tour->bookings->count();
}

// Хорошо - eager loading
$tours = Tour::with('bookings')->get();
foreach ($tours as $tour) {
    echo $tour->bookings->count();
}
```

## 🧪 Тестирование

### Unit тесты
- Тестируй модели и сервисы
- Используй factories для тестовых данных
- Применяй assertions
- Тестируй edge cases

### Feature тесты
- Тестируй API endpoints
- Тестируй пользовательские сценарии
- Используй database transactions
- Применяй authentication в тестах

### Пример теста
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_tours(): void
    {
        Tour::factory()->create([
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'price' => 50000,
        ]);

        $response = $this->getJson('/api/tours/search?' . http_build_query([
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-08',
            'adults' => 2,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'price',
                        'departure_city',
                        'destination_country',
                    ]
                ]
            ]);
    }
}
```

## 📱 Мобильная адаптация

### Responsive дизайн
- Используй Bootstrap grid system
- Применяй TailwindCSS responsive utilities
- Тестируй на разных устройствах
- Оптимизируй изображения

### Touch-friendly интерфейс
- Используй достаточно большие кнопки
- Применяй swipe жесты
- Оптимизируй формы для мобильных
- Используй мобильные паттерны навигации

## 🔍 SEO оптимизация

### Meta теги
- Используй уникальные title и description
- Применяй Open Graph теги
- Используй Schema.org разметку
- Оптимизируй URL структуру

### Пример SEO оптимизации
```blade
@section('title', $tour->title . ' - Туристическое агентство Авилона')
@section('description', Str::limit($tour->description, 160))

@section('meta')
    <meta property="og:title" content="{{ $tour->title }}">
    <meta property="og:description" content="{{ Str::limit($tour->description, 160) }}">
    <meta property="og:image" content="{{ $tour->image_url }}">
    <meta property="og:type" content="product">
@endsection
```

## 📊 Мониторинг и логирование

### Логирование
- Используй Laravel Log facade
- Применяй разные уровни логирования
- Логируй важные события
- Используй structured logging

### Мониторинг
- Настрой мониторинг ошибок
- Отслеживай производительность
- Мониторь использование ресурсов
- Настрой алерты

## 🚀 Деплой

### Подготовка к продакшену
- Оптимизируй конфигурацию
- Настрой кэширование
- Используй environment variables
- Настрой мониторинг

### Команды для продакшена
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```
```

```markdown
# PHP Development Rules

## 🎯 Общие принципы

### Стандарты кодирования
- Следуй PSR-12 стандарту
- Используй строгую типизацию
- Применяй DocBlock комментарии
- Соблюдай принципы SOLID
- Используй meaningful имена переменных и функций

### Структура кода
- Один класс на файл
- Используй namespaces
- Применяй autoloading
- Следуй принципу единственной ответственности

## 📝 Синтаксис и стиль

### Объявление переменных
```php
// Хорошо
$userName = 'john_doe';
$isActive = true;
$userCount = 0;

// Плохо
$u = 'john_doe';
$flag = true;
$cnt = 0;
```

### Функции и методы
```php
// Хорошо - с типизацией и документацией
/**
 * Вычисляет общую стоимость тура
 *
 * @param float $basePrice Базовая цена
 * @param int $nights Количество ночей
 * @param float $discount Скидка в процентах
 * @return float Общая стоимость
 */
public function calculateTotalPrice(float $basePrice, int $nights, float $discount = 0.0): float
{
    $total = $basePrice * $nights;
    
    if ($discount > 0) {
        $total -= $total * ($discount / 100);
    }
    
    return round($total, 2);
}

// Плохо - без типизации и документации
public function calc($price, $nights, $disc = 0)
{
    $total = $price * $nights;
    if ($disc > 0) {
        $total -= $total * ($disc / 100);
    }
    return $total;
}
```

### Условные операторы
```php
// Хорошо
if ($user->isActive() && $user->hasPermission('book_tour')) {
    $this->processBooking($booking);
}

// Плохо
if($user->isActive()==true && $user->hasPermission('book_tour')==true){
    $this->processBooking($booking);
}
```

### Циклы
```php
// Хорошо
foreach ($tours as $tour) {
    if ($tour->isAvailable()) {
        $availableTours[] = $tour;
    }
}

// Плохо
for($i=0;$i<count($tours);$i++){
    if($tours[$i]->isAvailable()==true){
        $availableTours[]=$tours[$i];
    }
}
```

## 🏗 Архитектурные паттерны

### Service Layer
```php
<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\Booking;
use App\Repositories\TourRepository;
use Illuminate\Support\Collection;

class TourBookingService
{
    public function __construct(
        private TourRepository $tourRepository
    ) {}

    public function searchAvailableTours(array $criteria): Collection
    {
        $tours = $this->tourRepository->search($criteria);
        
        return $tours->filter(function (Tour $tour) {
            return $this->isTourAvailable($tour);
        });
    }

    private function isTourAvailable(Tour $tour): bool
    {
        $bookedSlots = $tour->bookings()
            ->where('status', '!=', 'cancelled')
            ->sum('participants_count');
            
        return $bookedSlots < $tour->max_participants;
    }
}
```

### Repository Pattern
```php
<?php

namespace App\Repositories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TourRepository
{
    public function search(array $criteria): Collection
    {
        $query = Tour::query();

        if (isset($criteria['departure_city'])) {
            $query->where('departure_city', $criteria['departure_city']);
        }

        if (isset($criteria['destination_country'])) {
            $query->where('destination_country', $criteria['destination_country']);
        }

        if (isset($criteria['start_date'])) {
            $query->where('start_date', '>=', $criteria['start_date']);
        }

        if (isset($criteria['price_max'])) {
            $query->where('price', '<=', $criteria['price_max']);
        }

        return $query->get();
    }

    public function findById(int $id): ?Tour
    {
        return Tour::find($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Tour::paginate($perPage);
    }
}
```

## 🔒 Безопасность

### Валидация входных данных
```php
// Хорошо
public function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

public function sanitizeInput(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Плохо
public function processInput($data)
{
    return $data; // Без валидации и санитизации
}
```

### Работа с паролями
```php
// Хорошо
public function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

public function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// Плохо
public function hashPassword($password)
{
    return md5($password); // Устаревший и небезопасный
}
```

### SQL Injection защита
```php
// Хорошо - используй prepared statements
public function findUserByEmail(string $email): ?User
{
    $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    
    return $stmt->fetchObject(User::class);
}

// Плохо - уязвимо для SQL injection
public function findUserByEmail($email)
{
    $sql = "SELECT * FROM users WHERE email = '$email'";
    return $this->pdo->query($sql)->fetchObject(User::class);
}
```

## ⚡ Производительность

### Оптимизация запросов
```php
// Хорошо - используй индексы и ограничения
public function getActiveTours(): Collection
{
    return Tour::select(['id', 'title', 'price', 'start_date'])
        ->where('is_active', true)
        ->where('start_date', '>', now())
        ->orderBy('start_date')
        ->limit(20)
        ->get();
}

// Плохо - загружает все данные
public function getActiveTours()
{
    return Tour::where('is_active', true)->get();
}
```

### Кэширование
```php
// Хорошо
public function getPopularDestinations(): Collection
{
    return Cache::remember('popular_destinations', 3600, function () {
        return Tour::select('destination_country')
            ->groupBy('destination_country')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->pluck('destination_country');
    });
}

// Плохо - без кэширования
public function getPopularDestinations()
{
    return Tour::select('destination_country')
        ->groupBy('destination_country')
        ->orderByRaw('COUNT(*) DESC')
        ->limit(10)
        ->pluck('destination_country');
}
```

### Обработка ошибок
```php
// Хорошо
try {
    $result = $this->externalApiService->getTourData($tourId);
    
    if (!$result) {
        throw new TourNotFoundException("Тур с ID {$tourId} не найден");
    }
    
    return $result;
} catch (ApiException $e) {
    Log::error('Ошибка API при получении данных тура', [
        'tour_id' => $tourId,
        'error' => $e->getMessage()
    ]);
    
    throw new ServiceUnavailableException('Сервис временно недоступен');
}

// Плохо
public function getTourData($tourId)
{
    $result = $this->externalApiService->getTourData($tourId);
    return $result; // Без обработки ошибок
}
```

## 🧪 Тестирование

### Unit тесты
```php
<?php

namespace Tests\Unit;

use App\Services\PriceCalculatorService;
use PHPUnit\Framework\TestCase;

class PriceCalculatorServiceTest extends TestCase
{
    private PriceCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new PriceCalculatorService();
    }

    public function test_calculates_total_price_without_discount(): void
    {
        $result = $this->service->calculateTotalPrice(1000.0, 7, 0.0);
        
        $this->assertEquals(7000.0, $result);
    }

    public function test_calculates_total_price_with_discount(): void
    {
        $result = $this->service->calculateTotalPrice(1000.0, 7, 10.0);
        
        $this->assertEquals(6300.0, $result);
    }

    public function test_throws_exception_for_negative_price(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Цена не может быть отрицательной');
        
        $this->service->calculateTotalPrice(-100.0, 7);
    }
}
```

## 📱 Мобильная оптимизация

### Обработка данных для мобильных
```php
// Хорошо - оптимизированные данные для мобильных
public function getMobileTourData(Tour $tour): array
{
    return [
        'id' => $tour->id,
        'title' => $tour->title,
        'price' => $tour->price,
        'image' => $tour->getMobileImageUrl(),
        'duration' => $tour->getDurationInDays(),
        'rating' => $tour->average_rating,
        'is_available' => $tour->isAvailable(),
    ];
}

// Плохо - полные данные для мобильных
public function getMobileTourData(Tour $tour)
{
    return $tour->toArray(); // Слишком много данных
}
```

## 🔍 SEO и оптимизация

### Генерация мета-данных
```php
// Хорошо
public function generateMetaData(Tour $tour): array
{
    return [
        'title' => "{$tour->title} - Туристическое агентство Авилона",
        'description' => $this->truncateText($tour->description, 160),
        'keywords' => implode(', ', [
            $tour->destination_country,
            $tour->departure_city,
            'туры',
            'отдых',
            'путешествия'
        ]),
        'og_title' => $tour->title,
        'og_description' => $this->truncateText($tour->description, 160),
        'og_image' => $tour->getSocialImageUrl(),
    ];
}

private function truncateText(string $text, int $length): string
{
    return mb_strlen($text) > $length 
        ? mb_substr($text, 0, $length) . '...'
        : $text;
}
```

## 📊 Логирование и мониторинг

### Структурированное логирование
```php
// Хорошо
public function logBookingCreation(Booking $booking): void
{
    Log::info('Создана новая заявка', [
        'booking_id' => $booking->id,
        'user_id' => $booking->user_id,
        'tour_id' => $booking->tour_id,
        'price' => $booking->total_price,
        'created_at' => $booking->created_at->toISOString(),
    ]);
}

// Плохо
public function logBookingCreation($booking)
{
    Log::info("Booking created: {$booking->id}"); // Неструктурированное логирование
}
```

## 🚀 Оптимизация для продакшена

### Конфигурация
```php
// Хорошо - используй конфигурационные файлы
public function getApiEndpoint(): string
{
    return config('services.tour_api.endpoint');
}

public function getCacheTtl(): int
{
    return config('cache.tour_search_ttl', 3600);
}

// Плохо - хардкод значений
public function getApiEndpoint()
{
    return 'https://api.tours.com/v1'; // Хардкод
}
```

### Обработка исключений
```php
// Хорошо
public function handleApiException(ApiException $e): void
{
    if ($e->getCode() === 429) {
        // Rate limit exceeded
        Log::warning('API rate limit exceeded', [
            'service' => 'tour_api',
            'retry_after' => $e->getRetryAfter(),
        ]);
        
        throw new ServiceTemporarilyUnavailableException(
            'Сервис временно недоступен. Попробуйте позже.'
        );
    }
    
    Log::error('API error occurred', [
        'service' => 'tour_api',
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage(),
    ]);
    
    throw new ServiceUnavailableException('Сервис недоступен');
}
```
```

```markdown
# Frontend Development Rules (Bootstrap + TailwindCSS)

## 🎯 Общие принципы

### Подход к стилизации
- Используй Bootstrap 5 для базовой сетки и компонентов
- Применяй TailwindCSS для кастомных стилей и утилит
- Следуй mobile-first подходу
- Используй semantic HTML
- Применяй accessibility стандарты

### Структура стилей
- Bootstrap для layout и базовых компонентов
- TailwindCSS для кастомных стилей
- CSS переменные для цветовой схемы
- Компонентный подход к стилизации

## 🎨 Bootstrap 5

### Grid система
```html
<!-- Хорошо - responsive grid -->
<div class="container">
    <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <!-- Card content -->
            </div>
        </div>
    </div>
</div>

<!-- Плохо - фиксированные размеры -->
<div class="container">
    <div class="row">
        <div class="col-4">
            <!-- Content -->
        </div>
    </div>
</div>
```

### Компоненты
```html
<!-- Хорошо - используй Bootstrap компоненты -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="/">Авилона</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/tours">Туры</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Плохо - кастомная навигация без Bootstrap -->
<div class="custom-nav">
    <div class="nav-brand">Авилона</div>
    <div class="nav-links">
        <a href="/tours">Туры</a>
    </div>
</div>
```

### Формы
```html
<!-- Хорошо - Bootstrap формы с валидацией -->
<form class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" required>
        <div class="invalid-feedback">
            Пожалуйста, введите корректный email.
        </div>
    </div>
    
    <div class="mb-3">
        <label for="phone" class="form-label">Телефон</label>
        <input type="tel" class="form-control" id="phone" required>
    </div>
    
    <button type="submit" class="btn btn-primary">Отправить</button>
</form>

<!-- Плохо - без Bootstrap стилей -->
<form>
    <div>
        <label>Email</label>
        <input type="email">
    </div>
    <button>Отправить</button>
</form>
```

## 🎨 TailwindCSS

### Utility-first подход
```html
<!-- Хорошо - используй Tailwind утилиты -->
<div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-300">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Заголовок</h3>
    <p class="text-gray-600 leading-relaxed">Описание тура...</p>
    <button class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
        Забронировать
    </button>
</div>

<!-- Плохо - кастомные CSS классы -->
<div class="custom-card">
    <h3 class="card-title">Заголовок</h3>
    <p class="card-description">Описание тура...</p>
    <button class="custom-button">Забронировать</button>
</div>
```

### Responsive дизайн
```html
<!-- Хорошо - responsive с Tailwind -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white p-4 rounded-lg shadow">
        <!-- Card content -->
    </div>
</div>

<!-- Плохо - фиксированные размеры -->
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded-lg shadow">
        <!-- Card content -->
    </div>
</div>
```

### Цветовая схема
```html
<!-- Хорошо - используй семантические цвета -->
<button class="bg-primary-500 text-white hover:bg-primary-600">
    Основная кнопка
</button>

<button class="bg-success-500 text-white hover:bg-success-600">
    Успех
</button>

<button class="bg-danger-500 text-white hover:bg-danger-600">
    Опасность
</button>

<!-- Плохо - хардкод цветов -->
<button class="bg-blue-500 text-white hover:bg-blue-600">
    Кнопка
</button>
```

## 📱 Мобильная адаптация

### Touch-friendly интерфейс
```html
<!-- Хорошо - большие кнопки для мобильных -->
<button class="w-full md:w-auto bg-blue-500 text-white px-6 py-3 rounded-lg text-lg font-medium hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
    Забронировать тур
</button>

<!-- Плохо - маленькие кнопки -->
<button class="bg-blue-500 text-white px-2 py-1 rounded">
    Забронировать
</button>
```

### Мобильная навигация
```html
<!-- Хорошо - мобильная навигация -->
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="/" class="text-xl font-bold text-gray-800">Авилона</a>
            </div>
            
            <!-- Desktop menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/tours" class="text-gray-600 hover:text-gray-900">Туры</a>
                <a href="/countries" class="text-gray-600 hover:text-gray-900">Страны</a>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button class="text-gray-600 hover:text-gray-900 focus:outline-none focus:text-gray-900">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</nav>
```

## 🎯 Компоненты

### Карточка тура
```html
<!-- Хорошо - переиспользуемый компонент -->
<div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
    <div class="relative">
        <img src="{{ $tour->image_url }}" alt="{{ $tour->title }}" 
             class="w-full h-48 object-cover">
        <div class="absolute top-4 right-4 bg-white bg-opacity-90 px-2 py-1 rounded">
            <span class="text-sm font-medium text-gray-800">{{ $tour->duration }} дней</span>
        </div>
    </div>
    
    <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $tour->title }}</h3>
        <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ $tour->description }}</p>
        
        <div class="flex items-center justify-between">
            <div>
                <span class="text-2xl font-bold text-blue-600">{{ number_format($tour->price) }} ₽</span>
                <span class="text-sm text-gray-500">за человека</span>
            </div>
            <button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
                Подробнее
            </button>
        </div>
    </div>
</div>
```

### Форма поиска туров
```html
<!-- Хорошо - адаптивная форма поиска -->
<form class="bg-white p-6 rounded-lg shadow-md">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Откуда</label>
            <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Выберите город</option>
                <option value="moscow">Москва</option>
                <option value="spb">Санкт-Петербург</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Куда</label>
            <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Выберите страну</option>
                <option value="turkey">Турция</option>
                <option value="egypt">Египет</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Дата заезда</label>
            <input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Количество ночей</label>
            <select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="7">7 ночей</option>
                <option value="10">10 ночей</option>
                <option value="14">14 ночей</option>
            </select>
        </div>
    </div>
    
    <div class="flex justify-center">
        <button type="submit" class="bg-blue-500 text-white px-8 py-3 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            Найти туры
        </button>
    </div>
</form>
```

## ⚡ Производительность

### Оптимизация изображений
```html
<!-- Хорошо - lazy loading и оптимизация -->
<img src="{{ $tour->image_url }}" 
     alt="{{ $tour->title }}"
     class="w-full h-48 object-cover"
     loading="lazy"
     decoding="async">

<!-- Плохо - без оптимизации -->
<img src="{{ $tour->image_url }}" alt="{{ $tour->title }}">
```

### Минификация CSS/JS
```html
<!-- Хорошо - минифицированные файлы -->
<link rel="stylesheet" href="{{ mix('css/app.css') }}">
<script src="{{ mix('js/app.js') }}" defer></script>

<!-- Плохо - не минифицированные файлы -->
<link rel="stylesheet" href="/css/style.css">
<script src="/js/script.js"></script>
```

## 🔍 SEO оптимизация

### Semantic HTML
```html
<!-- Хорошо - семантическая разметка -->
<article class="tour-card bg-white rounded-lg shadow-md p-6">
    <header>
        <h2 class="text-xl font-semibold text-gray-800 mb-2">{{ $tour->title }}</h2>
        <div class="flex items-center mb-4">
            <span class="text-yellow-400">★★★★☆</span>
            <span class="text-sm text-gray-600 ml-2">4.2 (127 отзывов)</span>
        </div>
    </header>
    
    <div class="tour-content">
        <p class="text-gray-600 mb-4">{{ $tour->description }}</p>
        
        <footer class="flex items-center justify-between">
            <div class="price">
                <span class="text-2xl font-bold text-blue-600">{{ number_format($tour->price) }} ₽</span>
                <span class="text-sm text-gray-500">за человека</span>
            </div>
            <button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                Забронировать
            </button>
        </footer>
    </div>
</article>

<!-- Плохо - не семантическая разметка -->
<div class="tour-card">
    <div class="title">{{ $tour->title }}</div>
    <div class="description">{{ $tour->description }}</div>
    <div class="price">{{ $tour->price }} ₽</div>
    <div class="button">Забронировать</div>
</div>
```

### Meta теги
```html
<!-- Хорошо - полные meta теги -->
<head>
    <title>{{ $tour->title }} - Туристическое агентство Авилона</title>
    <meta name="description" content="{{ Str::limit($tour->description, 160) }}">
    <meta name="keywords" content="{{ $tour->destination_country }}, туры, отдых, путешествия">
    
    <!-- Open Graph -->
    <meta property="og:title" content="{{ $tour->title }}">
    <meta property="og:description" content="{{ Str::limit($tour->description, 160) }}">
    <meta property="og:image" content="{{ $tour->image_url }}">
    <meta property="og:type" content="product">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $tour->title }}">
    <meta name="twitter:description" content="{{ Str::limit($tour->description, 160) }}">
    <meta name="twitter:image" content="{{ $tour->image_url }}">
</head>
```

## 🎨 Анимации и интерактивность

### Плавные переходы
```html
<!-- Хорошо - плавные анимации -->
<div class="tour-card bg-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
    <!-- Card content -->
</div>

<button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
    Кнопка
</button>

<!-- Плохо - резкие изменения -->
<div class="tour-card hover:shadow-lg">
    <!-- Card content -->
</div>
```

### Loading состояния
```html
<!-- Хорошо - loading состояния -->
<button class="bg-blue-500 text-white px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed" 
        :disabled="loading">
    <span v-if="loading" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
    {{ loading ? 'Загрузка...' : 'Отправить' }}
</button>

<!-- Плохо - без loading состояний -->
<button class="bg-blue-500 text-white px-4 py-2 rounded-lg">
    Отправить
</button>
```

## 🔧 JavaScript интеграция

### Alpine.js компоненты
```html
<!-- Хорошо - Alpine.js компонент -->
<div x-data="tourSearch()" class="tour-search-form">
    <form @submit.prevent="searchTours">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Откуда</label>
                <select x-model="filters.departureCity" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Выберите город</option>
                    <option value="moscow">Москва</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Куда</label>
                <select x-model="filters.destinationCountry" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Выберите страну</option>
                    <option value="turkey">Турция</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="bg-blue-500 text-white px-8 py-3 rounded-lg hover:bg-blue-600" 
                :disabled="loading">
            <span x-show="loading" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
            <span x-text="loading ? 'Поиск...' : 'Найти туры'"></span>
        </button>
    </form>
    
    <div x-show="results.length > 0" class="mt-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="tour in results" :key="tour.id">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 x-text="tour.title" class="text-lg font-semibold mb-2"></h3>
                    <p x-text="tour.description" class="text-gray-600 mb-4"></p>
                    <div class="flex justify-between items-center">
                        <span x-text="tour.price + ' ₽'" class="text-xl font-bold text-blue-600"></span>
                        <button @click="bookTour(tour)" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            Забронировать
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function tourSearch() {
    return {
        filters: {
            departureCity: '',
            destinationCountry: '',
            startDate: '',
            nights: 7
        },
        results: [],
        loading: false,
        
        async searchTours() {
            this.loading = true;
            try {
                const response = await fetch('/api/tours/search', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.filters)
                });
                
                const data = await response.json();
                this.results = data.tours;
            } catch (error) {
                console.error('Ошибка поиска туров:', error);
            } finally {
                this.loading = false;
            }
        },
        
        bookTour(tour) {
            // Логика бронирования
            window.location.href = `/tours/${tour.id}/book`;
        }
    }
}
</script>
```

## 🧪 Тестирование

### Browser тесты
```javascript
// Хорошо - тестирование пользовательских сценариев
test('can search for tours', async ({ page }) => {
    await page.goto('/');
    
    await page.selectOption('[data-testid="departure-city"]', 'moscow');
    await page.selectOption('[data-testid="destination-country"]', 'turkey');
    await page.fill('[data-testid="start-date"]', '2024-06-01');
    
    await page.click('[data-testid="search-button"]');
    
    await expect(page.locator('[data-testid="tour-results"]')).toBeVisible();
    await expect(page.locator('[data-testid="tour-card"]')).toHaveCount.greaterThan(0);
});

// Плохо - тестирование без data-testid
test('can search for tours', async ({ page }) => {
    await page.goto('/');
    
    await page.selectOption('select', 'moscow');
    await page.click('button');
    
    await expect(page.locator('.tour-card')).toBeVisible();
});
```
```

Теперь создам скрипты для автоматизации разработки:


```bat
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
```

```shellscript
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
```

```bat
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
```

```markdown
# Git Workflow для проекта "Авилона"

## 🌿 Структура веток

### Основные ветки
- `main` - продакшен версия (защищена)
- `develop` - ветка разработки
- `feature/*` - ветки для новых функций
- `hotfix/*` - ветки для критических исправлений
- `release/*` - ветки для релизов

### Примеры названий веток
```
feature/tour-search-widget
feature/user-authentication
feature/admin-panel
hotfix/booking-form-validation
release/v1.0.0
```

## 📝 Commit сообщения

### Формат сообщений
```
<type>(<scope>): <description>

<body>

<footer>
```

### Типы коммитов
- `feat` - новая функция
- `fix` - исправление бага
- `docs` - изменения в документации
- `style` - форматирование кода
- `refactor` - рефакторинг кода
- `test` - добавление тестов
- `chore` - обновление зависимостей, конфигурации

### Примеры коммитов
```bash
feat(tours): добавить поисковый виджет туров

- Реализован API для поиска туров
- Добавлены фильтры по стране и дате
- Создан компонент TourSearchWidget
- Добавлена пагинация результатов

Closes #123

fix(auth): исправлена валидация email при регистрации

- Добавлена проверка уникальности email
- Исправлено сообщение об ошибке
- Добавлены unit тесты

Fixes #456

docs(readme): обновлена документация по установке

- Добавлены инструкции для Windows
- Обновлены требования к системе
- Добавлены примеры конфигурации
```

## 🔄 Workflow процесс

### 1. Создание feature ветки
```bash
# Переключение на develop
git checkout develop
git pull origin develop

# Создание новой ветки
git checkout -b feature/tour-search-widget

# Работа над функцией...
git add .
git commit -m "feat(tours): добавить базовую структуру поиска"
```

### 2. Push и создание Pull Request
```bash
# Push ветки
git push origin feature/tour-search-widget

# Создание PR через GitHub/GitLab интерфейс
```

### 3. Code Review процесс
- Проверка кода коллегами
- Исправление замечаний
- Обновление PR

### 4. Merge в develop
```bash
# После одобрения PR
git checkout develop
git pull origin develop
git branch -d feature/tour-search-widget
```

## 🏷 Версионирование

### Semantic Versioning (SemVer)
- `MAJOR.MINOR.PATCH`
- `1.0.0` - первый релиз
- `1.1.0` - новая функция
- `1.1.1` - исправление бага

### Создание релиза
```bash
# Создание release ветки
git checkout -b release/v1.1.0

# Обновление версии в файлах
# composer.json, package.json, CHANGELOG.md

git add .
git commit -m "chore(release): подготовка к релизу v1.1.0"

# Push и создание PR в main
git push origin release/v1.1.0
```

## 📋 Pull Request шаблон

### Заголовок PR
```
feat(tours): добавить поисковый виджет туров
```

### Описание PR
```markdown
## 📝 Описание
Добавлен поисковый виджет туров на главную страницу с возможностью фильтрации по различным параметрам.

## 🎯 Что изменено
- [x] Создан API endpoint `/api/tours/search`
- [x] Добавлен компонент `TourSearchWidget`
- [x] Реализованы фильтры по стране, дате, цене
- [x] Добавлена пагинация результатов
- [x] Созданы unit тесты

## 🧪 Тестирование
- [x] Unit тесты для API
- [x] Feature тесты для виджета
- [x] Тестирование на мобильных устройствах

## 📱 Скриншоты
![Поисковый виджет](screenshots/tour-search-widget.png)

## 🔗 Связанные задачи
Closes #123
```

## 🔧 Git Hooks

### Pre-commit hook
```bash
#!/bin/bash
# Проверка кода перед коммитом

echo "Запуск pre-commit проверок..."

# Проверка PHP синтаксиса
php -l app/Http/Controllers/TourController.php

# Запуск тестов
php artisan test

# Проверка стиля кода
./vendor/bin/pint --test

echo "✅ Все проверки пройдены"
```

### Commit-msg hook
```bash
#!/bin/bash
# Проверка формата commit сообщения

commit_regex='^(feat|fix|docs|style|refactor|test|chore)(\(.+\))?: .{1,50}'

if ! grep -qE "$commit_regex" "$1"; then
    echo "❌ Неверный формат commit сообщения!"
    echo "Используйте формат: type(scope): description"
    echo "Пример: feat(tours): добавить поисковый виджет"
    exit 1
fi

echo "✅ Commit сообщение соответствует стандарту"
```

## 📊 Автоматизация

### GitHub Actions workflow
```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        
    - name: Install dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Run tests
      run: php artisan test
      
    - name: Check code style
      run: ./vendor/bin/pint --test
```

## 🚀 Деплой процесс

### Автоматический деплой
```bash
# После merge в main
git checkout main
git pull origin main

# Создание тега
git tag -a v1.1.0 -m "Release version 1.1.0"
git push origin v1.1.0

# Деплой на продакшен сервер
ssh production-server "cd /var/www/avilona && git pull origin main && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache"
```

## 📈 Мониторинг

### Метрики Git
- Количество коммитов в день
- Время code review
- Количество багов в релизе
- Покрытие тестами

### Инструменты
- GitHub Insights
- GitLab Analytics
- SonarQube для анализа кода
- CodeClimate для качества кода

## 🔒 Безопасность

### Защита веток
- Запрет прямого push в main
- Обязательный code review
- Проверка статуса CI/CD
- Защита от удаления веток

### Секреты
- Использование GitHub Secrets
- Переменные окружения для конфиденциальных данных
- Ротация токенов доступа
- Логирование доступа к репозиторию
```

Теперь настрою GitHub и создам финальную документацию:


```markdown
# Настройка GitHub для проекта "Авилона"

## 🚀 Инициализация репозитория

### 1. Создание репозитория на GitHub
1. Перейти на https://github.com/new
2. Название: `avilona-turfirma`
3. Описание: `Туристическое агентство Авилона - многофункциональный веб-сайт`
4. Тип: Private (для коммерческого проекта)
5. Добавить README.md: ✅
6. Добавить .gitignore: ✅ (выбрать Laravel)
7. Выбрать лицензию: MIT

### 2. Локальная настройка Git
```bash
# Инициализация Git (если еще не инициализирован)
git init

# Добавление remote репозитория
git remote add origin https://github.com/YOUR_USERNAME/avilona-turfirma.git

# Проверка подключения
git remote -v
```

### 3. Первый коммит
```bash
# Добавление всех файлов
git add .

# Создание первого коммита
git commit -m "feat(init): начальная настройка проекта Laravel

- Настроена базовая структура Laravel 10
- Добавлены необходимые зависимости
- Создана документация проекта
- Настроены правила разработки
- Добавлены скрипты автоматизации"

# Push в репозиторий
git push -u origin main
```

## 🔧 Настройка веток

### 1. Создание ветки разработки
```bash
# Создание и переключение на develop
git checkout -b develop

# Push ветки develop
git push -u origin develop
```

### 2. Настройка защиты веток
В настройках репозитория GitHub:
- **main**: Требовать Pull Request, Требовать статус CI, Требовать review
- **develop**: Требовать Pull Request, Требовать статус CI

## 🤖 GitHub Actions

### 1. Создание workflow файла
```yaml
# .github/workflows/ci.yml
name: CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: avilona_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - name: Checkout code
      uses: actions/checkout@v3
      
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, dom, fileinfo, mysql, pdo_mysql
        
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
        
    - name: Install PHP dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Install Node dependencies
      run: npm ci
      
    - name: Copy environment file
      run: cp .env.example .env
      
    - name: Generate application key
      run: php artisan key:generate
      
    - name: Run database migrations
      run: php artisan migrate --force
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: avilona_test
        DB_USERNAME: root
        DB_PASSWORD: password
        
    - name: Run tests
      run: php artisan test
      
    - name: Check code style
      run: ./vendor/bin/pint --test
      
    - name: Build assets
      run: npm run build
      
    - name: Upload coverage reports
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
```

### 2. Настройка секретов
В настройках репозитория → Secrets and variables → Actions:
- `DB_PASSWORD` - пароль базы данных
- `MAIL_PASSWORD` - пароль для email
- `TELEGRAM_BOT_TOKEN` - токен Telegram бота
- `TELEGRAM_CHAT_ID` - ID чата для уведомлений

## 📋 Issue и Project Management

### 1. Создание шаблонов Issues
```markdown
# .github/ISSUE_TEMPLATE/bug_report.md
---
name: Bug report
about: Создать отчет об ошибке
title: '[BUG] '
labels: bug
assignees: ''

---

## 🐛 Описание ошибки
Краткое описание проблемы.

## 🔄 Шаги для воспроизведения
1. Перейти к '...'
2. Нажать на '...'
3. Прокрутить до '...'
4. Увидеть ошибку

## ✅ Ожидаемое поведение
Что должно было произойти.

## 📱 Окружение
- ОС: [e.g. Windows 10]
- Браузер: [e.g. Chrome 90]
- Версия PHP: [e.g. 8.1]

## 📸 Скриншоты
Если применимо, добавьте скриншоты.

## 📝 Дополнительная информация
Любая другая информация об ошибке.
```

### 2. Создание Project Board
1. Перейти в Projects → New project
2. Название: "Авилона Development"
3. Шаблон: "Automated kanban"
4. Колонки: Backlog, In Progress, Review, Done

## 🔒 Настройка безопасности

### 1. Branch Protection Rules
```bash
# Для ветки main
- Require a pull request before merging
- Require status checks to pass before merging
- Require branches to be up to date before merging
- Require review from code owners
- Restrict pushes that create files larger than 100MB
```

### 2. Code Owners
```bash
# .github/CODEOWNERS
# Глобальные владельцы
* @your-username

# Backend код
/app/ @backend-team
/database/ @backend-team

# Frontend код
/resources/views/ @frontend-team
/resources/css/ @frontend-team
/resources/js/ @frontend-team

# Документация
/docs/ @documentation-team
```

## 📊 Настройка мониторинга

### 1. GitHub Insights
- Перейти в Insights → Pulse
- Настроить уведомления о активности
- Отслеживать метрики коммитов и PR

### 2. Dependabot
```yaml
# .github/dependabot.yml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 10
    
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 10
```

## 🚀 Автоматический деплой

### 1. Настройка продакшен сервера
```bash
# На сервере создать пользователя
sudo adduser avilona
sudo usermod -aG www-data avilona

# Создать директорию проекта
sudo mkdir -p /var/www/avilona
sudo chown avilona:www-data /var/www/avilona

# Клонировать репозиторий
cd /var/www/avilona
git clone https://github.com/YOUR_USERNAME/avilona-turfirma.git .
```

### 2. Deploy скрипт
```bash
#!/bin/bash
# deploy.sh

echo "🚀 Начало деплоя..."

# Переключение на ветку main
git checkout main
git pull origin main

# Установка зависимостей
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Миграции базы данных
php artisan migrate --force

# Очистка кэша
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Перезапуск сервисов
sudo systemctl reload nginx
sudo systemctl reload php8.1-fpm

echo "✅ Деплой завершен!"
```

### 3. GitHub Action для деплоя
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]
    tags: [ 'v*' ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.SSH_KEY }}
        script: |
          cd /var/www/avilona
          ./deploy.sh
```

## 📈 Аналитика и отчеты

### 1. Настройка уведомлений
- Email уведомления о новых Issues и PR
- Slack интеграция для команды
- Telegram уведомления о деплоях

### 2. Метрики проекта
- Время разработки функций
- Количество багов в релизе
- Покрытие тестами
- Производительность приложения

## 🔧 Полезные команды

### Работа с репозиторием
```bash
# Клонирование репозитория
git clone https://github.com/YOUR_USERNAME/avilona-turfirma.git
cd avilona-turfirma

# Создание feature ветки
git checkout -b feature/tour-search-widget

# Синхронизация с удаленным репозиторием
git fetch origin
git merge origin/develop

# Отправка изменений
git add .
git commit -m "feat(tours): добавить поисковый виджет"
git push origin feature/tour-search-widget
```

### Работа с Issues
```bash
# Создание Issue через CLI
gh issue create --title "Добавить поисковый виджет туров" --body "Описание задачи" --label "enhancement"

# Закрытие Issue через коммит
git commit -m "feat(tours): добавить поисковый виджет

Closes #123"
```

## 📚 Дополнительные ресурсы

### Инструменты
- [GitHub CLI](https://cli.github.com/) - командная строка для GitHub
- [GitHub Desktop](https://desktop.github.com/) - GUI для Git
- [GitKraken](https://www.gitkraken.com/) - продвинутый Git клиент

### Документация
- [GitHub Docs](https://docs.github.com/)
- [Git Flow](https://nvie.com/posts/a-successful-git-branching-model/)
- [Conventional Commits](https://www.conventionalcommits.org/)
```

```markdown
# Руководство по проверке проекта "Авилона"

## 🔍 Быстрая проверка установки

### 1. Проверка системных требований
```bash
# Проверка версии PHP
php --version
# Должно быть PHP 8.0+ с расширениями: mbstring, dom, fileinfo, mysql, pdo_mysql

# Проверка Composer
composer --version
# Должно быть Composer 2.0+

# Проверка Node.js
node --version
# Должно быть Node.js 16+

# Проверка npm
npm --version
# Должно быть npm 8.0+

# Проверка MySQL
mysql --version
# Должно быть MySQL 5.7+ или MariaDB 10.3+
```

### 2. Проверка установки зависимостей
```bash
# Проверка PHP зависимостей
composer show
# Должны быть установлены: laravel/framework, laravel/sanctum, spatie/laravel-sluggable

# Проверка Node.js зависимостей
npm list
# Должны быть установлены: vite, tailwindcss, alpinejs, bootstrap
```

### 3. Проверка конфигурации
```bash
# Проверка .env файла
cat .env
# Должны быть настроены: APP_NAME, APP_URL, DB_*, MAIL_*

# Проверка ключа приложения
php artisan key:generate --show
# Должен быть сгенерирован 32-символьный ключ

# Проверка подключения к БД
php artisan migrate:status
# Должны быть выполнены все миграции
```

## 🚀 Проверка запуска серверов

### 1. Автоматический запуск
```bash
# Windows
start-dev.bat

# Linux/macOS
chmod +x start-dev.sh
./start-dev.sh
```

### 2. Ручной запуск
```bash
# Laravel сервер
php artisan serve
# Должен запуститься на http://localhost:8000

# Vite dev server (в другом терминале)
npm run dev
# Должен запуститься на http://localhost:5173
```

### 3. Проверка доступности
- Открыть http://localhost:8000 в браузере
- Должна загрузиться главная страница сайта
- Проверить отсутствие ошибок в консоли браузера

## 🧪 Проверка функциональности

### 1. Тестирование основных страниц
```bash
# Запуск тестов
php artisan test

# Проверка конкретных тестов
php artisan test --filter=TourSearchTest
php artisan test --filter=UserRegistrationTest
```

### 2. Проверка API endpoints
```bash
# Проверка поиска туров
curl -X GET "http://localhost:8000/api/tours/search?departure_city=Москва&destination_country=Турция"

# Проверка регистрации пользователя
curl -X POST "http://localhost:8000/api/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123"}'
```

### 3. Проверка форм
- Открыть страницу контактов
- Заполнить форму обратной связи
- Проверить отправку email (если настроено)
- Проверить валидацию полей

## 🔧 Проверка производительности

### 1. Проверка времени загрузки
```bash
# Проверка времени ответа главной страницы
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost:8000"

# Создать файл curl-format.txt:
echo "     time_namelookup:  %{time_namelookup}\n
        time_connect:  %{time_connect}\n
     time_appconnect:  %{time_appconnect}\n
    time_pretransfer:  %{time_pretransfer}\n
       time_redirect:  %{time_redirect}\n
  time_starttransfer:  %{time_starttransfer}\n
                     ----------\n
          time_total:  %{time_total}\n" > curl-format.txt
```

### 2. Проверка кэширования
```bash
# Очистка кэша
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Включение кэширования
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Проверка работы кэша
php artisan tinker
>>> Cache::put('test', 'value', 60)
>>> Cache::get('test')
```

## 📱 Проверка мобильной адаптации

### 1. Responsive тестирование
- Открыть сайт в Chrome DevTools
- Переключиться в режим мобильного устройства
- Проверить адаптивность на разных разрешениях:
  - iPhone SE (375x667)
  - iPhone 12 Pro (390x844)
  - iPad (768x1024)
  - Desktop (1920x1080)

### 2. Touch-friendly интерфейс
- Проверить размер кнопок (минимум 44px)
- Проверить расстояние между кликабельными элементами
- Проверить работу форм на мобильном

## 🔒 Проверка безопасности

### 1. Проверка CSRF защиты
```bash
# Проверка наличия CSRF токена
curl -X GET "http://localhost:8000/contact" | grep csrf-token
```

### 2. Проверка валидации
```bash
# Попытка отправить невалидные данные
curl -X POST "http://localhost:8000/api/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"","email":"invalid-email","password":"123"}'
# Должны вернуться ошибки валидации
```

### 3. Проверка прав доступа
```bash
# Попытка доступа к защищенным маршрутам
curl -X GET "http://localhost:8000/admin"
# Должен быть редирект на страницу входа
```

## 📊 Проверка SEO

### 1. Проверка мета-тегов
```bash
# Проверка title и description
curl -s "http://localhost:8000" | grep -E "<title>|<meta.*description"
```

### 2. Проверка структуры URL
- Проверить наличие sitemap.xml
- Проверить robots.txt
- Проверить корректность URL структуры

### 3. Проверка Open Graph тегов
```bash
# Проверка OG тегов
curl -s "http://localhost:8000" | grep -E "og:|twitter:"
```

## 🐛 Отладка проблем

### 1. Проверка логов
```bash
# Просмотр логов Laravel
tail -f storage/logs/laravel.log

# Просмотр логов веб-сервера (если используется)
tail -f /var/log/nginx/error.log
```

### 2. Проверка конфигурации
```bash
# Проверка конфигурации приложения
php artisan config:show

# Проверка маршрутов
php artisan route:list

# Проверка middleware
php artisan route:list --middleware=auth
```

### 3. Проверка базы данных
```bash
# Подключение к БД
mysql -u root -p
USE avilona_turfirma;
SHOW TABLES;
DESCRIBE users;
```

## ✅ Чек-лист готовности

### Системные требования
- [ ] PHP 8.0+ установлен
- [ ] Composer установлен
- [ ] Node.js 16+ установлен
- [ ] MySQL 5.7+ установлен
- [ ] Все расширения PHP доступны

### Установка проекта
- [ ] Репозиторий клонирован
- [ ] PHP зависимости установлены
- [ ] Node.js зависимости установлены
- [ ] .env файл настроен
- [ ] База данных создана
- [ ] Миграции выполнены

### Функциональность
- [ ] Главная страница загружается
- [ ] Формы работают корректно
- [ ] API endpoints отвечают
- [ ] Тесты проходят
- [ ] Мобильная версия адаптивна

### Производительность
- [ ] Время загрузки < 3 секунд
- [ ] Кэширование работает
- [ ] Изображения оптимизированы
- [ ] CSS/JS минифицированы

### Безопасность
- [ ] CSRF защита активна
- [ ] Валидация работает
- [ ] Права доступа настроены
- [ ] HTTPS настроен (для продакшена)

## 🚨 Частые проблемы и решения

### Проблема: "Class not found"
```bash
# Решение
composer dump-autoload
```

### Проблема: "Database connection failed"
```bash
# Проверить настройки в .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=avilona_turfirma
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Проблема: "Permission denied"
```bash
# Решение для Linux/macOS
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Проблема: "Vite dev server not working"
```bash
# Решение
npm install
npm run dev
```

### Проблема: "Email not sending"
```bash
# Проверить настройки в .env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```
```

Теперь создам финальный коммит и отправлю изменения:


---

**User**

Забыл сказать, что у меня уже создан репозиторий, вот ссылка на него @https://github.com/straus97/Avilona_turfirma 

---

**Cursor**

Отлично! Вижу, что у вас уже создан репозиторий на GitHub: [https://github.com/straus97/Avilona_turfirma](https://github.com/straus97/Avilona_turfirma). Сейчас настрою подключение к вашему репозиторию и отправлю все созданные файлы.


Вижу, что Git добавил все файлы, но есть много предупреждений о замене LF на CRLF (это нормально для Windows). Теперь создам коммит и отправлю в репозиторий:


Отлично! Коммит создан успешно. Теперь отправлю изменения в ваш GitHub репозиторий:


Превосходно! 🎉 Все файлы успешно отправлены в ваш GitHub репозиторий [https://github.com/straus97/Avilona_turfirma](https://github.com/straus97/Avilona_turfirma).

## 📋 Краткий лог выполненной работы

### ✅ Завершено:

1. **Анализ проекта** - изучена существующая структура Laravel проекта
2. **Создана полная документация** в папке `docs/`:
   - `README.md` - обзор проекта и технологий
   - `development-setup.md` - руководство по настройке среды разработки
   - `roadmap.md` - дорожная карта проекта с этапами разработки
   - `technical-requirements.md` - детальное техническое задание
   - `git-workflow.md` - правила работы с Git и GitHub
   - `github-setup.md` - инструкции по настройке GitHub
   - `testing-guide.md` - руководство по тестированию и проверке

3. **Оптимизированы правила .cursor/**:
   - Удалены ненужные правила (Python, Django, FastAPI, React Native, Expo)
   - Созданы специализированные правила для проекта:
     - `01-laravel.mdc` - правила разработки на Laravel
     - `02-php.mdc` - стандарты PHP кодирования
     - `03-frontend.mdc` - правила Bootstrap + TailwindCSS

4. **Настроена автоматизация разработки**:
   - `start-dev.bat` - скрипт запуска для Windows
   - `start-dev.sh` - скрипт запуска для Linux/macOS
   - `stop-dev.bat` - скрипт остановки серверов

5. **Настроен Git и GitHub**:
   - Подключен к вашему репозиторию
   - Создан первый коммит с полной документацией
   - Отправлены все файлы в GitHub

## 🚀 Следующие шаги

### Немедленные действия:
1. **Запустите сервер разработки**:
   ```bash
   # Windows
   start-dev.bat
   
   # Linux/macOS
   chmod +x start-dev.sh
   ./start-dev.sh
   ```

2. **Проверьте работу сайта**:
   - Откройте http://localhost:8000
   - Убедитесь, что все страницы загружаются

3. **Настройте базу данных**:
   ```bash
   php artisan migrate
   ```

### Планируемые этапы разработки:
1. **Этап 1**: Оптимизация существующего кода (1-2 недели)
2. **Этап 2**: Реализация поискового виджета туров (3-4 недели)
3. **Этап 3**: Система заявок и бронирования (2-3 недели)
4. **Этап 4**: Личные кабинеты пользователей (3-4 недели)

## 📚 Полезные ссылки

- **Репозиторий**: [https://github.com/straus97/Avilona_turfirma](https://github.com/straus97/Avilona_turfirma)
- **Документация**: `/docs/` - все руководства в папке проекта
- **Правила разработки**: `/.cursor/rules/` - оптимизированные правила для проекта

Теперь у вас есть полная инфраструктура для эффективной разработки проекта туристического агентства "Авилона"! 🎯

```shellscript
   # Windows
   start-dev.bat
   
   # Linux/macOS
   chmod +x start-dev.sh
   ./start-dev.sh
```

```shellscript
   php artisan migrate
   ```

### Планируемые этапы разработки:
1. **Этап 1**: Оптимизация существующего кода (1-2 недели)
2. **Этап 2**: Реализация поискового виджета туров (3-4 недели)
3. **Этап 3**: Система заявок и бронирования (2-3 недели)
4. **Этап 4**: Личные кабинеты пользователей (3-4 недели)

## 📚 Полезные ссылки

- **Репозиторий**: [https://github.com/straus97/Avilona_turfirma](https://github.com/straus97/Avilona_turfirma)
- **Документация**: `/docs/` - все руководства в папке проекта
- **Правила разработки**: `/.cursor/rules/` - оптимизированные правила для проекта

Теперь у вас есть полная инфраструктура для эффективной разработки проекта туристического агентства "Авилона"! 🎯
```

---

**User**

@start-dev.bat я запустил этот файл, у меня показались иероглифы и он резко закрылся, страница http://localhost:8000/ не работает.
Также есть такие файлы, проверь нужны ли они нам, если да, то добавь куда нужно или скажи, что мне сделать.
@c:\wamp\bin\mariadb\mariadb11.5.2\data\turfirma/ 

---

**Cursor**

Вижу проблему! У вас есть база данных MariaDB с данными, но сервер не запускается из-за проблем с кодировкой и конфигурацией. Давайте исправим это.


Вижу, что у вас нет файла `.env`. Создам его и настрою подключение к вашей базе данных MariaDB:


---

**User**

Кажется есть каие-то проблемы с подключением? У меня не запущен сервер, бд тоже нету, наверное ты из-за этого и не можешь подключиться

---

**Cursor**

Да, вы правы! Проблема в том, что у вас не запущены серверы. Давайте пошагово настроим все необходимое для работы проекта.


## 🔧 Пошаговая настройка проекта

### 1. Сначала создам правильный файл .env


---

**User**

Мы зависли на создании, что-то не выходит?

---

**Cursor**

Да, команда зависла. Давайте попробуем другой подход - создам файл .env через простые команды:


---

**User**

Не получается. Может сначала стоит исправить @start-dev.bat чтобы он запускался и не показывал иероглифы или ты уже это сделал?

---

**Cursor**

Да, вы правы! Проблема в том, что скрипт `start-dev.bat` показывает иероглифы из-за проблем с кодировкой. Давайте исправим его:


```bat
@echo off
chcp 65001 >nul
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
echo Создание файла .env...
if not exist ".env" (
    echo APP_NAME=Авилона > .env
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
    echo Файл .env создан!
)

echo.
echo Генерация ключа приложения...
php artisan key:generate

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

```

Отлично! Теперь скрипт исправлен. Вот что я изменил:

## 🔧 Исправления в start-dev.bat:

1. **Добавил `chcp 65001 >nul`** - устанавливает UTF-8 кодировку для корректного отображения русских символов
2. **Добавил автоматическое создание .env файла** - скрипт сам создаст файл конфигурации
3. **Добавил генерацию ключа приложения** - `php artisan key:generate`

## 📋 Что нужно сделать перед запуском:

### 1. Запустите MariaDB сервер
У вас есть WAMP, поэтому:
- Откройте WAMP
- Убедитесь, что MariaDB запущен (зеленый значок)
- Если не запущен, нажмите на значок WAMP и выберите "Start All Services"

### 2. Проверьте настройки базы данных
В WAMP:
- Откройте phpMyAdmin (http://localhost/phpmyadmin)
- Убедитесь, что база данных `turfirma` существует
- Если нет, создайте её

### 3. Запустите исправленный скрипт
Теперь можете запустить `start-dev.bat` - он должен работать без иероглифов и автоматически:
- Создать файл .env
- Сгенерировать ключ приложения
- Запустить Laravel сервер
- Запустить Vite dev server

## 🚀 Попробуйте сейчас:

1. **Запустите WAMP** (если не запущен)
2. **Запустите `start-dev.bat`**
3. **Откройте http://localhost:8000**

Если что-то не работает, покажите мне сообщения об ошибках из терминалов, которые откроются после запуска скрипта.

