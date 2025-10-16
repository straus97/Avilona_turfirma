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
