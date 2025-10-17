# План оптимизации производительности сайта "Авилона"

## 🎯 Текущие проблемы

### 1. Внешние виджеты (Критично)
- **Delfin Tour виджет** - блокирует загрузку страницы
- **Infoflot виджет** - асинхронная загрузка, но все равно замедляет
- **Решение**: Сделать виджеты опциональными или заменить на собственный поиск

### 2. Множественные CSS/JS файлы
- **Bootstrap CSS** (CDN) - ~200KB
- **Font Awesome** (CDN) - ~100KB  
- **Google Fonts** - ~50KB
- **jQuery** (локальный) - ~100KB
- **LazySizes** (CDN) - ~20KB
- **Custom CSS** - ~50KB
- **Решение**: Объединить и минифицировать

### 3. Отсутствие оптимизации изображений
- **Логотип** - не оптимизирован
- **Изображения** - нет lazy loading
- **Решение**: Оптимизировать и добавить lazy loading

### 4. Отсутствие кэширования
- **Статические файлы** - не кэшируются
- **API запросы** - нет кэширования
- **Решение**: Настроить кэширование

## 🚀 План оптимизации

### Этап 1: Оптимизация внешних виджетов (Приоритет 1)

#### 1.1 Сделать виджеты опциональными
```html
<!-- Вместо прямого подключения -->
<script async="true" src="//www.delfin-tour.ru/export/frame" data-delfin='10644'></script>

<!-- Сделать кнопку для загрузки -->
<button id="loadDelfinWidget" class="btn btn-primary">Загрузить поиск туров</button>
<div id="delfinWidget" style="display: none;"></div>

<script>
document.getElementById('loadDelfinWidget').addEventListener('click', function() {
    // Загружаем виджет только по клику
    const script = document.createElement('script');
    script.src = '//www.delfin-tour.ru/export/frame';
    script.setAttribute('data-delfin', '10644');
    script.async = true;
    document.getElementById('delfinWidget').appendChild(script);
    this.style.display = 'none';
});
</script>
```

#### 1.2 Создать собственный виджет поиска
```html
<!-- Собственный виджет поиска -->
<div class="tour-search-widget">
    <form action="/tours/search" method="GET">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Откуда</label>
                <select name="departure_city" class="form-select" required>
                    <option value="">Выберите город</option>
                    <option value="moscow">Москва</option>
                    <option value="spb">Санкт-Петербург</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Куда</label>
                <select name="destination_country" class="form-select" required>
                    <option value="">Выберите страну</option>
                    <option value="turkey">Турция</option>
                    <option value="egypt">Египет</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Дата заезда</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ночей</label>
                <select name="nights" class="form-select">
                    <option value="7">7 ночей</option>
                    <option value="10">10 ночей</option>
                    <option value="14">14 ночей</option>
                </select>
            </div>
        </div>
        <div class="text-center mt-3">
            <button type="submit" class="btn btn-primary btn-lg">Найти туры</button>
        </div>
    </form>
</div>
```

### Этап 2: Оптимизация CSS/JS (Приоритет 2)

#### 2.1 Создать объединенный CSS файл
```css
/* public/css/optimized.css */
/* Bootstrap минифицированный */
/* Font Awesome минифицированный */
/* Google Fonts локально */
/* Custom CSS минифицированный */
```

#### 2.2 Создать объединенный JS файл
```javascript
/* public/js/optimized.js */
/* jQuery минифицированный */
/* LazySizes минифицированный */
/* Custom JS минифицированный */
```

#### 2.3 Обновить layout
```html
<!-- Вместо множественных подключений -->
<link rel="stylesheet" href="{{ asset('css/optimized.css') }}">
<script src="{{ asset('js/optimized.js') }}" defer></script>
```

### Этап 3: Оптимизация изображений (Приоритет 3)

#### 3.1 Оптимизировать логотип
```bash
# Сжать логотип
convert public/img/logo.png -quality 85 -strip public/img/logo-optimized.png
```

#### 3.2 Добавить lazy loading
```html
<!-- Вместо обычных img -->
<img src="{{ asset('img/logo.png') }}" alt="Logo" class="img-fluid">

<!-- Использовать lazy loading -->
<img data-src="{{ asset('img/logo.png') }}" alt="Logo" class="img-fluid lazyload">
```

### Этап 4: Настройка кэширования (Приоритет 4)

#### 4.1 Кэширование статических файлов
```apache
# .htaccess
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>
```

#### 4.2 Кэширование в Laravel
```php
// В контроллере
public function index()
{
    return Cache::remember('home_page', 3600, function () {
        return view('home', $data);
    });
}
```

## 📊 Ожидаемые результаты

### До оптимизации
- **Время загрузки**: 3-5 секунд
- **Размер страницы**: 41KB + внешние ресурсы
- **Количество запросов**: 15-20
- **Внешние виджеты**: Блокируют загрузку

### После оптимизации
- **Время загрузки**: 1-2 секунды
- **Размер страницы**: 25KB + минифицированные ресурсы
- **Количество запросов**: 5-8
- **Внешние виджеты**: Загружаются по требованию

## 🛠 Инструменты для оптимизации

### 1. Минификация CSS/JS
```bash
# Установить инструменты
npm install -g clean-css-cli uglify-js

# Минифицировать CSS
cleancss -o public/css/optimized.css public/css/style_min.css

# Минифицировать JS
uglifyjs public/js/jquery-3.7.1.min.js -o public/js/optimized.js
```

### 2. Оптимизация изображений
```bash
# Установить ImageMagick
# Сжать изображения
convert public/img/logo.png -quality 85 -strip public/img/logo-optimized.png
```

### 3. Анализ производительности
```bash
# Lighthouse CLI
npm install -g lighthouse
lighthouse http://localhost:8000 --output html --output-path ./lighthouse-report.html
```

## 📋 Чек-лист оптимизации

### ✅ Этап 1: Внешние виджеты
- [ ] Сделать Delfin Tour виджет опциональным
- [ ] Сделать Infoflot виджет опциональным
- [ ] Создать собственный виджет поиска
- [ ] Протестировать загрузку

### ✅ Этап 2: CSS/JS
- [ ] Объединить CSS файлы
- [ ] Объединить JS файлы
- [ ] Минифицировать ресурсы
- [ ] Обновить layout

### ✅ Этап 3: Изображения
- [ ] Оптимизировать логотип
- [ ] Добавить lazy loading
- [ ] Сжать все изображения
- [ ] Протестировать отображение

### ✅ Этап 4: Кэширование
- [ ] Настроить кэширование статических файлов
- [ ] Добавить кэширование в Laravel
- [ ] Настроить CDN (опционально)
- [ ] Протестировать производительность

## 🎯 Метрики успеха

### Core Web Vitals
- **LCP (Largest Contentful Paint)**: < 2.5s
- **FID (First Input Delay)**: < 100ms
- **CLS (Cumulative Layout Shift)**: < 0.1

### Общие метрики
- **Время загрузки**: < 2s
- **Размер страницы**: < 30KB
- **Количество запросов**: < 10
- **Оценка Lighthouse**: > 90

## 🚀 Следующие шаги

1. **Начать с Этапа 1** - оптимизация внешних виджетов
2. **Протестировать** изменения
3. **Измерить** производительность
4. **Перейти к Этапу 2** - оптимизация CSS/JS
5. **Повторить** процесс для каждого этапа
