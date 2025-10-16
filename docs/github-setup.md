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
