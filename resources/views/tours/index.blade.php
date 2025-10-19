@extends('layouts.main')

@section('styles')
<style>
/* Стили для компактного виджета */
.tour-search-widget .card {
    border: 1px solid #e9ecef;
}

.tour-search-widget .form-select-sm,
.tour-search-widget .form-control-sm {
    font-size: 0.875rem;
    padding: 0.375rem 0.5rem;
}

/* Стили для календаря */
.calendar-container {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.weekday {
    padding: 8px 4px;
    font-weight: 500;
    color: #6c757d;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    font-weight: 500;
    color: #495057;
    background: #fff;
    border: 1px solid transparent;
}

.calendar-day:hover {
    background: #f8f9fa;
    border-color: #dee2e6;
}

.calendar-day.past {
    color: #adb5bd;
    cursor: not-allowed;
}

.calendar-day.past:hover {
    background: #fff;
    border-color: transparent;
}

.calendar-day.today {
    background: #e3f2fd;
    color: #1976d2;
    font-weight: 600;
}

.calendar-day.selected {
    background: #2196f3;
    color: white;
}

.calendar-day.start {
    background: #1976d2;
    color: white;
    border-radius: 4px 0 0 4px;
}

.calendar-day.end {
    background: #1976d2;
    color: white;
    border-radius: 0 4px 4px 0;
}

.calendar-day.start.end {
    border-radius: 4px;
}

.selected-dates {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.selected-range {
    font-size: 1.1rem;
    margin: 0.5rem 0;
}
</style>
@endsection

@section('title', 'Поиск туров - Туристическая фирма Авилона')
@section('meta_description', 'Поиск лучших туров по выгодным ценам. Работаем с ведущими туроператорами России.')

@section('content')
<div class="container my-5">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    
    <!-- Компактный виджет поиска -->
    <div class="search-widget mb-4">
        <div class="card border-0 shadow-sm" style="border-radius: 8px;">
            <div class="card-body p-3" style="background: #f8f9fa;">
                <form action="{{ route('tours.index') }}" method="GET" id="tourSearchForm">
                    <!-- Основные поля в одну строку -->
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Откуда</label>
                            <select name="departure_city" class="form-select form-select-sm" required>
                                <option value="">Выберите город</option>
                                @foreach($departureCities as $city)
                                    <option value="{{ $city }}" {{ request('departure_city') == $city ? 'selected' : '' }}>
                                        {{ $city }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Куда</label>
                            <select name="destination_country" class="form-select form-select-sm" required>
                                <option value="">Выберите страну</option>
                                @foreach($destinationCountries as $country)
                                    <option value="{{ $country }}" {{ request('destination_country') == $country ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Даты вылета</label>
                            <input type="text" name="date_range" class="form-control form-control-sm" placeholder="20 окт - 26 окт 25" readonly style="background: white; cursor: pointer;" onclick="openDateRangePicker()" value="{{ request('date_range') }}">
                            <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Ночей</label>
                            <div class="row g-1">
                                <div class="col-6">
                                    <input type="number" name="nights_min" class="form-control form-control-sm" placeholder="от 7" min="1" max="30" value="{{ request('nights_min') }}">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="nights_max" class="form-control form-control-sm" placeholder="до 14" min="1" max="30" value="{{ request('nights_max') }}">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Туристы</label>
                            <select name="adults" class="form-select form-select-sm">
                                <option value="1" {{ request('adults') == 1 ? 'selected' : '' }}>1 взрослый</option>
                                <option value="2" {{ request('adults', 2) == 2 ? 'selected' : '' }}>2 взрослых</option>
                                <option value="3" {{ request('adults') == 3 ? 'selected' : '' }}>3 взрослых</option>
                                <option value="4" {{ request('adults') == 4 ? 'selected' : '' }}>4 взрослых</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold">
                                <i class="fas fa-search me-1"></i>Найти туры
                            </button>
                        </div>
                    </div>
                    
                    <!-- Дополнительные фильтры (скрытые по умолчанию) -->
                    <div class="row mt-3" id="additionalFilters" style="display: none;">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Курорты</label>
                            <div id="resortsContainer" class="border rounded p-2" style="max-height: 120px; overflow-y: auto; background: white;">
                                <div class="text-muted small">Выберите страну</div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Звездность</label>
                            <select name="hotel_stars" class="form-select form-select-sm">
                                <option value="">Любая</option>
                                <option value="3" {{ request('hotel_stars') == 3 ? 'selected' : '' }}>3★</option>
                                <option value="4" {{ request('hotel_stars') == 4 ? 'selected' : '' }}>4★</option>
                                <option value="5" {{ request('hotel_stars') == 5 ? 'selected' : '' }}>5★</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Питание</label>
                            <select name="meal_type" class="form-select form-select-sm">
                                <option value="">Любое</option>
                                <option value="BB" {{ request('meal_type') == 'BB' ? 'selected' : '' }}>BB</option>
                                <option value="HB" {{ request('meal_type') == 'HB' ? 'selected' : '' }}>HB</option>
                                <option value="FB" {{ request('meal_type') == 'FB' ? 'selected' : '' }}>FB</option>
                                <option value="AI" {{ request('meal_type') == 'AI' ? 'selected' : '' }}>AI</option>
                                <option value="UAI" {{ request('meal_type') == 'UAI' ? 'selected' : '' }}>UAI</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Цена от</label>
                            <input type="number" name="price_min" class="form-control form-control-sm" placeholder="30000" min="0" value="{{ request('price_min') }}">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Цена до</label>
                            <input type="number" name="price_max" class="form-control form-control-sm" placeholder="200000" min="0" value="{{ request('price_max') }}">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label small text-muted mb-1">Туроператор</label>
                            <select name="tour_operator" class="form-select form-select-sm">
                                <option value="">Все</option>
                                <option value="ambotis">Ambotis</option>
                                <option value="anex">Anex</option>
                                <option value="biblio_globus">Biblio Globus</option>
                                <option value="bon_tour">Bon Tour</option>
                                <option value="bsi_group">BSI Group</option>
                                <option value="coral_travel">Coral Travel</option>
                                <option value="delfin">Delfin</option>
                                <option value="express_tours">Express Tours</option>
                                <option value="good_time">Good Time</option>
                                <option value="ics">ICS</option>
                                <option value="intourist">Intourist</option>
                                <option value="itm_group">ITM Group</option>
                                <option value="mouzenidis_travel">Mouzenidis Travel</option>
                                <option value="pac_group">PAC Group</option>
                                <option value="pegas">Pegas</option>
                                <option value="russian_express">Russian Express</option>
                                <option value="sunmar">Sunmar</option>
                                <option value="tez_tour">Tez Tour</option>
                                <option value="tui">TUI</option>
                                <option value="west_travel">West Travel</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Кнопка показать/скрыть дополнительные фильтры -->
                    <div class="text-center mt-2">
                        <button type="button" class="btn btn-link btn-sm text-decoration-none" onclick="toggleAdditionalFilters()">
                            <i class="fas fa-sliders-h me-1"></i>Больше фильтров
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Результаты поиска -->
    <div class="results-section">
        <!-- Заголовок и сортировка -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0 text-primary">Найдено туров: <span class="fw-bold">{{ $tours->total() }}</span></h2>
            <select class="form-select w-auto" onchange="updateSort(this.value)">
                <option value="popularity" {{ request('sort_by', 'popularity') == 'popularity' ? 'selected' : '' }}>По популярности</option>
                <option value="price_asc" {{ request('sort_by') == 'price_asc' ? 'selected' : '' }}>По цене (сначала дешевые)</option>
                <option value="price_desc" {{ request('sort_by') == 'price_desc' ? 'selected' : '' }}>По цене (сначала дорогие)</option>
                <option value="rating" {{ request('sort_by') == 'rating' ? 'selected' : '' }}>По рейтингу</option>
            </select>
        </div>
        
        @if($tours->count() > 0)
            <!-- Сетка туров -->
            <div class="row g-3">
                @foreach($tours as $tour)
                    <div class="col-12">
                        <div class="card border-0 shadow-sm tour-card" style="border-radius: 12px;">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <!-- Левая часть - изображение и основная информация -->
                                    <div class="col-md-4">
                                        <div class="position-relative">
                                            <img src="{{ asset($tour->image_url) }}" class="img-fluid rounded" alt="{{ $tour->title }}" 
                                                 style="height: 180px; width: 100%; object-fit: cover;" 
                                                 onerror="this.src='{{ asset('img/remont.png') }}'">
                                            
                                            @if($tour->is_hot_deal)
                                                <div class="position-absolute top-0 start-0 m-2">
                                                    <span class="badge bg-danger rounded-pill">
                                                        <i class="fas fa-fire me-1"></i>Горящий тур
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Центральная часть - детали тура -->
                                    <div class="col-md-5">
                                        <div class="h5 mb-2 fw-bold">{{ $tour->hotel_name }}</div>
                                        
                                        <!-- Звездность -->
                                        <div class="mb-2">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="fas fa-star {{ $i <= $tour->hotel_stars ? 'text-warning' : 'text-muted' }}"></i>
                                            @endfor
                                        </div>
                                        
                                        <!-- Местоположение -->
                                        <div class="mb-2 text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            {{ $tour->destination_city }}, {{ $tour->destination_country }}
                                        </div>
                                        
                                        <!-- Даты и продолжительность -->
                                        <div class="mb-2">
                                            <i class="fas fa-calendar me-1"></i>
                                            <span class="fw-medium">{{ $tour->start_date->format('d.m.Y') }} - {{ $tour->end_date->format('d.m.Y') }}</span>
                                            <span class="text-muted">({{ $tour->nights }} ночей)</span>
                                        </div>
                                        
                                        <!-- Питание -->
                                        <div class="mb-2">
                                            <i class="fas fa-utensils me-1"></i>
                                            <span class="text-muted">{{ $tour->meal_type ?? 'Не указано' }}</span>
                                        </div>
                                        
                                        <!-- Дополнительная информация -->
                                        <div class="small text-muted">
                                            <i class="fas fa-plane me-1"></i>
                                            Вылет из {{ $tour->departure_city }}
                                        </div>
                                    </div>
                                    
                                    <!-- Правая часть - цена и кнопка -->
                                    <div class="col-md-3 text-end">
                                        <div class="mb-3">
                                            <div class="h3 mb-1 text-primary fw-bold">{{ number_format($tour->price, 0, ',', ' ') }} ₽</div>
                                            <small class="text-muted">за человека</small>
                                        </div>
                                        
                                        <button type="button" class="btn btn-primary btn-lg w-100" onclick="openBookingModal({{ $tour->id }})">
                                            <i class="fas fa-phone me-2"></i>Связаться с менеджером
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Пагинация -->
            <div class="mt-5">
                {{ $tours->links() }}
            </div>
        @else
            <div class="alert alert-info text-center py-4" role="alert">
                <i class="fas fa-info-circle fa-3x mb-3"></i>
                <h4 class="alert-heading">Туры не найдены</h4>
                <p class="mb-0">Попробуйте изменить параметры поиска или свяжитесь с нашим менеджером.</p>
            </div>
        @endif
    </div>
</div>

<!-- Модальное окно для заявки -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bookingModalLabel">Оставить заявку на тур</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('bookings.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="tour_id" id="bookingTourId">
                    
                    @guest
                        <div class="mb-3">
                            <label for="contact_name" class="form-label">Ваше имя</label>
                            <input type="text" class="form-control" id="contact_name" name="contact_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="ivan@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="phoneInput" class="form-control" required placeholder="+7 (999) 123-45-67">
                        </div>
                    @endauth
                    
                    <div class="mb-3">
                        <label class="form-label">Комментарий или пожелания</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Напишите ваши пожелания..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Отправить заявку</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function openBookingModal(tourId) {
    document.getElementById('bookingTourId').value = tourId;
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

function openDateRangePicker() {
    // Создаем красивый календарь как на Sletat.ru
    const modal = document.createElement('div');
    modal.className = 'modal fade show';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выберите даты вылета</h5>
                    <button type="button" class="btn-close" onclick="closeDatePicker()"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="calendar-container">
                                <div class="calendar-header mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="changeMonth(-1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                        </div>
                                        <div class="col-8 text-center">
                                            <h6 class="mb-0" id="currentMonth">Октябрь 2025</h6>
                                        </div>
                                        <div class="col-2 text-end">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="changeMonth(1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="calendar-grid" id="calendarGrid">
                                    <!-- Календарь будет сгенерирован JavaScript -->
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="selected-dates">
                                <h6>Выбранные даты:</h6>
                                <div class="selected-range" id="selectedRange">
                                    <span class="text-muted">Выберите даты</span>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">Минимальная продолжительность: 1 день</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDatePicker()">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="applyDateRange()" id="applyBtn" disabled>Применить</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.classList.add('modal-open');
    
    // Инициализируем календарь
    initCalendar();
}

let selectedStartDate = null;
let selectedEndDate = null;
let currentDate = new Date();

function initCalendar() {
    generateCalendar();
}

function generateCalendar() {
    const calendarGrid = document.getElementById('calendarGrid');
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Обновляем заголовок
    document.getElementById('currentMonth').textContent = 
        new Date(year, month).toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
    
    // Получаем первый день месяца и количество дней
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startDay = firstDay.getDay() === 0 ? 7 : firstDay.getDay(); // Понедельник = 1
    
    let html = '<div class="calendar-weekdays mb-2">';
    const weekdays = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'ВС'];
    weekdays.forEach(day => {
        html += `<div class="weekday text-center text-muted small">${day}</div>`;
    });
    html += '</div>';
    
    html += '<div class="calendar-days">';
    
    // Пустые ячейки для начала месяца
    for (let i = 1; i < startDay; i++) {
        html += '<div class="calendar-day"></div>';
    }
    
    // Дни месяца
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const isToday = isSameDay(date, new Date());
        const isPast = date < new Date();
        const isSelected = selectedStartDate && selectedEndDate && 
            date >= selectedStartDate && date <= selectedEndDate;
        const isStart = selectedStartDate && isSameDay(date, selectedStartDate);
        const isEnd = selectedEndDate && isSameDay(date, selectedEndDate);
        
        let classes = 'calendar-day';
        if (isPast) classes += ' past';
        if (isToday) classes += ' today';
        if (isSelected) classes += ' selected';
        if (isStart) classes += ' start';
        if (isEnd) classes += ' end';
        
        html += `<div class="calendar-day ${classes}" onclick="selectDate(${day})" data-day="${day}">
            <span>${day}</span>
        </div>`;
    }
    
    html += '</div>';
    calendarGrid.innerHTML = html;
}

function selectDate(day) {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const date = new Date(year, month, day);
    
    if (date < new Date()) return; // Нельзя выбрать прошедшие даты
    
    if (!selectedStartDate || (selectedStartDate && selectedEndDate)) {
        // Начинаем новый выбор
        selectedStartDate = date;
        selectedEndDate = null;
    } else if (selectedStartDate && !selectedEndDate) {
        // Завершаем выбор
        if (date < selectedStartDate) {
            selectedEndDate = selectedStartDate;
            selectedStartDate = date;
        } else {
            selectedEndDate = date;
        }
    }
    
    updateCalendar();
    updateSelectedRange();
}

function updateCalendar() {
    generateCalendar();
}

function updateSelectedRange() {
    const rangeElement = document.getElementById('selectedRange');
    const applyBtn = document.getElementById('applyBtn');
    
    if (selectedStartDate && selectedEndDate) {
        const startStr = selectedStartDate.toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'long' 
        });
        const endStr = selectedEndDate.toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'long' 
        });
        rangeElement.innerHTML = `<strong>${startStr} — ${endStr}</strong>`;
        applyBtn.disabled = false;
    } else if (selectedStartDate) {
        const startStr = selectedStartDate.toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'long' 
        });
        rangeElement.innerHTML = `<strong>${startStr}</strong> — выберите дату окончания`;
        applyBtn.disabled = true;
    } else {
        rangeElement.innerHTML = '<span class="text-muted">Выберите даты</span>';
        applyBtn.disabled = true;
    }
}

function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    generateCalendar();
}

function isSameDay(date1, date2) {
    return date1.getDate() === date2.getDate() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getFullYear() === date2.getFullYear();
}

function closeDatePicker() {
    const modal = document.querySelector('.modal.show');
    if (modal) {
        modal.remove();
        document.body.classList.remove('modal-open');
    }
    // Сбрасываем выбор
    selectedStartDate = null;
    selectedEndDate = null;
}

function applyDateRange() {
    if (selectedStartDate && selectedEndDate) {
        document.getElementById('start_date').value = selectedStartDate.toISOString().split('T')[0];
        document.getElementById('end_date').value = selectedEndDate.toISOString().split('T')[0];
        
        // Форматируем даты для отображения
        const startFormatted = selectedStartDate.toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'short' 
        });
        const endFormatted = selectedEndDate.toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'short',
            year: '2-digit'
        });
        
        document.querySelector('input[name="date_range"]').value = `${startFormatted} — ${endFormatted}`;
    }
    
    closeDatePicker();
}

function toggleAdditionalFilters() {
    const filters = document.getElementById('additionalFilters');
    const button = event.target;
    
    if (filters.style.display === 'none') {
        filters.style.display = 'block';
        button.innerHTML = '<i class="fas fa-chevron-up me-1"></i>Скрыть фильтры';
    } else {
        filters.style.display = 'none';
        button.innerHTML = '<i class="fas fa-sliders-h me-1"></i>Больше фильтров';
    }
}

// Маска телефона
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length === 0) {
        input.value = '';
        return;
    }
    
    if (value.length <= 1) {
        input.value = '+7';
    } else if (value.length <= 4) {
        input.value = '+7 (' + value.substring(1);
    } else if (value.length <= 7) {
        input.value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4);
    } else if (value.length <= 9) {
        input.value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7);
    } else {
        input.value = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9) + '-' + value.substring(9, 11);
    }
}

// Автообновление курортов с галочками
function updateResorts() {
    const country = document.querySelector('select[name="destination_country"]').value;
    const resortsContainer = document.getElementById('resortsContainer');
    
    if (!country) {
        resortsContainer.innerHTML = '<div class="text-muted small">Выберите страну</div>';
        return;
    }
    
    resortsContainer.innerHTML = '<div class="text-muted small">Загрузка курортов...</div>';
    
    fetch(`/api/tours/resorts?country=${encodeURIComponent(country)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = '';
                data.data.forEach(resort => {
                    html += `
                        <div class="form-check form-check-sm">
                            <input class="form-check-input" type="checkbox" name="resorts[]" value="${resort}" id="resort_${resort.replace(/\s+/g, '_')}">
                            <label class="form-check-label small" for="resort_${resort.replace(/\s+/g, '_')}">
                                ${resort}
                            </label>
                        </div>
                    `;
                });
                resortsContainer.innerHTML = html;
            } else {
                resortsContainer.innerHTML = '<div class="text-muted small">Курорты не найдены</div>';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки курортов:', error);
            resortsContainer.innerHTML = '<div class="text-muted small">Ошибка загрузки</div>';
        });
}

function updateSort(sortBy) {
    const form = document.getElementById('tourSearchForm');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'sort_by';
    input.value = sortBy;
    form.appendChild(input);
    form.submit();
}

// Стили для карточек туров (без скачков)
document.addEventListener('DOMContentLoaded', function() {
    const tourCards = document.querySelectorAll('.tour-card');
    tourCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });
    
    // Инициализация значений дат если они есть
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && endDate) {
        const startFormatted = new Date(startDate).toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'short' 
        });
        const endFormatted = new Date(endDate).toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'short',
            year: '2-digit'
        });
        document.querySelector('input[name="date_range"]').value = `${startFormatted} — ${endFormatted}`;
    }
    
    // Инициализация маски телефона
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            formatPhoneNumber(this);
        });
    }
    
    // Инициализация автообновления курортов
    const countrySelect = document.querySelector('select[name="destination_country"]');
    if (countrySelect) {
        countrySelect.addEventListener('change', updateResorts);
    }
});
</script>
@endsection