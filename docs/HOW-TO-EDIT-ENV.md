# 📝 Как найти и отредактировать .env файл

## 📂 Где находится файл .env?

**Путь:** `C:\wamp\www\Avilona_turfirma\.env`

Это файл в **корне проекта**, рядом с файлами `composer.json`, `artisan`, `README.md` и т.д.

---

## 🔍 Как найти строку QUEUE_CONNECTION?

### Вариант 1: Через текстовый редактор (рекомендую)

1. Откройте файл `.env` в **Блокноте**, **Notepad++** или **VS Code**
2. Нажмите `Ctrl + F` (поиск)
3. Введите: `QUEUE_CONNECTION`
4. Нажмите Enter

**Если нашли строку:**
```
QUEUE_CONNECTION=sync
```
→ Замените на: `QUEUE_CONNECTION=database`

**Если НЕ нашли строку:**
→ Добавьте в конец файла:
```
QUEUE_CONNECTION=database
```

---

### Вариант 2: Через поиск в проводнике Windows

1. Откройте папку проекта: `C:\wamp\www\Avilona_turfirma`
2. В поиске введите: `.env`
3. Откройте найденный файл

---

## 📋 Что нужно сделать в .env файле:

### 1. Найти или добавить строку с QUEUE_CONNECTION

**Ищите строку:**
```env
QUEUE_CONNECTION=sync
```

**Замените на:**
```env
QUEUE_CONNECTION=database
```

**Если строки нет — добавьте её в конец файла!**

---

### 2. Добавить настройки почты (Gmail)

Добавьте в конец файла `.env` следующие строки:

```env
# ═══════════════════════════════════════════════════════════════
# EMAIL НАСТРОЙКИ (GMAIL)
# ═══════════════════════════════════════════════════════════════
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ваш_email@gmail.com
MAIL_PASSWORD=ваш_16_значный_пароль_приложения
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@avilona.ru
MAIL_FROM_NAME="Авилона"
```

**⚠️ ВАЖНО:**
- Замените `ваш_email@gmail.com` на ваш реальный Gmail
- Замените `ваш_16_значный_пароль_приложения` на пароль приложения из Gmail (БЕЗ пробелов!)

---

## 💡 Пример как должен выглядеть .env файл:

```env
APP_NAME="Авилона"
APP_ENV=local
APP_KEY=base64:xxxx...
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=avilona_turfirma
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

# ═══════════════════════════════════════════════════════════════
# EMAIL НАСТРОЙКИ (GMAIL)
# ═══════════════════════════════════════════════════════════════
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@avilona.ru
MAIL_FROM_NAME="Авилона"
```

---

## ✅ После редактирования:

1. **Сохраните файл** (Ctrl + S)
2. **Выполните команду** для очистки кэша:
   ```bash
   php artisan config:clear
   ```

---

## 🐛 Если файл .env не найден:

1. Создайте новый файл `.env` в корне проекта
2. Скопируйте содержимое из `.env.example` (если есть)
3. Добавьте все необходимые настройки

---

**Готово! Теперь можно переходить к шагу 3.**
