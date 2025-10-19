@extends('layouts.main')

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
    <!-- Виджет поиска -->
    <div class="search-widget mb-4">
        <div class="card border-0 shadow-lg">
            <div class="card-body p-4 p-md-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px;">
                <div class="text-center text-white mb-4">
                    <i class="fas fa-plane-departure fa-3x mb-3" style="opacity: 0.9;"></i>
                    <h1 class="h2 mb-2 fw-bold">Поиск туров</h1>
                    <p class="mb-0 opacity-75">Найдите свое идеальное путешествие</p>
                </div>
                
                <form action="{{ route('tours.index') }}" method="GET" id="tourSearchForm">
                    <!-- Основные фильтры -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-map-marker-alt me-1"></i>Откуда
                            </label>
                            <select name="departure_city" class="form-select form-select-sm" required>
                                <option value="">Выберите город</option>
                                @foreach($departureCities as $city)
                                    <option value="{{ $city }}" {{ request('departure_city') == $city ? 'selected' : '' }}>
                                        {{ $city }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-globe me-1"></i>Куда
                            </label>
                            <select name="destination_country" class="form-select form-select-sm" required>
                                <option value="">Выберите страну</option>
                                @foreach($destinationCountries as $country)
                                    <option value="{{ $country }}" {{ request('destination_country') == $country ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-calendar-alt me-1"></i>Даты вылета
                            </label>
                            <div class="input-group">
                                <input type="text" name="date_range" class="form-control form-control-sm" placeholder="26 окт – 27 окт 25" readonly style="background: white; cursor: pointer;" onclick="openDateRangePicker()" value="{{ request('date_range') }}">
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="openDateRangePicker()">
                                    <i class="fas fa-calendar"></i>
                                </button>
                            </div>
                            <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-moon me-1"></i>Ночей
                            </label>
                            <div class="row g-1">
                                <div class="col-6">
                                    <input type="number" name="nights_min" class="form-control form-control-sm" placeholder="от 7" min="1" max="30" value="{{ request('nights_min') }}">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="nights_max" class="form-control form-control-sm" placeholder="до 14" min="1" max="30" value="{{ request('nights_max') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Дополнительные фильтры -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-map-marker-alt me-1"></i>Курорт
                            </label>
                            <select name="destination_city" id="resortSelect" class="form-select form-select-sm">
                                <option value="">Выберите курорт</option>
                                @if(request('destination_country'))
                                    @php
                                        $resorts = \App\Models\Tour::where('destination_country', request('destination_country'))
                                            ->select('destination_city')
                                            ->distinct()
                                            ->pluck('destination_city')
                                            ->filter();
                                    @endphp
                                    @foreach($resorts as $resort)
                                        <option value="{{ $resort }}" {{ request('destination_city') == $resort ? 'selected' : '' }}>
                                            {{ $resort }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-star me-1"></i>Звездность
                            </label>
                            <select name="hotel_stars" class="form-select form-select-sm">
                                <option value="">Любая</option>
                                <option value="3" {{ request('hotel_stars') == 3 ? 'selected' : '' }}>3★</option>
                                <option value="4" {{ request('hotel_stars') == 4 ? 'selected' : '' }}>4★</option>
                                <option value="5" {{ request('hotel_stars') == 5 ? 'selected' : '' }}>5★</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-utensils me-1"></i>Питание
                            </label>
                            <select name="meal_type" class="form-select form-select-sm">
                                <option value="">Любое</option>
                                <option value="BB" {{ request('meal_type') == 'BB' ? 'selected' : '' }}>BB</option>
                                <option value="HB" {{ request('meal_type') == 'HB' ? 'selected' : '' }}>HB</option>
                                <option value="FB" {{ request('meal_type') == 'FB' ? 'selected' : '' }}>FB</option>
                                <option value="AI" {{ request('meal_type') == 'AI' ? 'selected' : '' }}>AI</option>
                                <option value="UAI" {{ request('meal_type') == 'UAI' ? 'selected' : '' }}>UAI</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-ruble-sign me-1"></i>Цена от
                            </label>
                            <input type="number" name="price_min" class="form-control form-control-sm" placeholder="30000" min="0" value="{{ request('price_min') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-1 small">
                                <i class="fas fa-ruble-sign me-1"></i>Цена до
                            </label>
                            <input type="number" name="price_max" class="form-control form-control-sm" placeholder="200000" min="0" value="{{ request('price_max') }}">
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button type="submit" class="btn btn-light btn-lg px-4 py-2 fw-bold shadow" style="font-size: 1rem;">
                            <i class="fas fa-search me-2"></i>Найти туры
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
            <h2 class="mb-0">
                Найдено туров: <span class="text-primary">{{ $tours->total() }}</span>
            </h2>
            
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0">Сортировка:</label>
                <select class="form-select form-select-sm" style="width: auto;" onchange="updateSort(this.value)">
                    <option value="popular" {{ request('sort_by') == 'popular' ? 'selected' : '' }}>По популярности</option>
                    <option value="price_asc" {{ request('sort_by') == 'price_asc' ? 'selected' : '' }}>Цена (возрастание)</option>
                    <option value="price_desc" {{ request('sort_by') == 'price_desc' ? 'selected' : '' }}>Цена (убывание)</option>
                    <option value="rating" {{ request('sort_by') == 'rating' ? 'selected' : '' }}>По рейтингу</option>
                </select>
            </div>
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
            <!-- Нет результатов -->
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                <h4>Туры не найдены</h4>
                <p class="mb-0">Попробуйте изменить параметры поиска</p>
            </div>
        @endif
    </div>
</div>

<!-- Модальное окно для создания заявки -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Создать заявку на тур</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bookingForm" action="{{ route('bookings.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="tour_id" id="bookingTourId">
                    
                    @auth
                        <!-- Для авторизованных - данные предзаполнены -->
                        <div class="mb-3">
                            <label class="form-label">ФИО</label>
                            <input type="text" class="form-control" value="{{ auth()->user()->name }}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="{{ auth()->user()->email }}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="tel" class="form-control" value="{{ auth()->user()->phone }}" readonly>
                        </div>
                    @else
                        <!-- Для неавторизованных -->
                        <div class="mb-3">
                            <label class="form-label">ФИО <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Иван Иванов">
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
                    
                    @guest
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Совет:</strong> Зарегистрируйтесь на сайте, чтобы отслеживать статус заявки и общаться с менеджером онлайн!
                        </div>
                    @endguest
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" form="bookingForm" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i>Отправить заявку
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function updateSort(sortBy) {
    const form = document.getElementById('tourSearchForm');
    const url = new URL(form.action);
    const formData = new FormData(form);
    
    // Добавляем все параметры формы в URL
    for (let [key, value] of formData.entries()) {
        if (value) url.searchParams.set(key, value);
    }
    
    // Добавляем сортировку
    url.searchParams.set('sort_by', sortBy);
    
    window.location.href = url.toString();
}

function openBookingModal(tourId) {
    document.getElementById('bookingTourId').value = tourId;
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

function openDateRangePicker() {
    // Создаем модальное окно с календарем
    const modal = document.createElement('div');
    modal.className = 'modal fade show';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выберите даты вылета</h5>
                    <button type="button" class="btn-close" onclick="closeDatePicker()"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Дата от</label>
                            <input type="date" id="startDatePicker" class="form-control" min="{{ date('Y-m-d') }}" value="{{ request('start_date') ?: date('Y-m-d', strtotime('+1 week')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Дата до</label>
                            <input type="date" id="endDatePicker" class="form-control" min="{{ date('Y-m-d') }}" value="{{ request('end_date') ?: date('Y-m-d', strtotime('+2 weeks')) }}">
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Выберите диапазон дат для поиска туров</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDatePicker()">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="applyDateRange()">Применить</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.classList.add('modal-open');
}

function closeDatePicker() {
    const modal = document.querySelector('.modal.show');
    if (modal) {
        modal.remove();
        document.body.classList.remove('modal-open');
    }
}

function applyDateRange() {
    const startDate = document.getElementById('startDatePicker').value;
    const endDate = document.getElementById('endDatePicker').value;
    
    if (startDate && endDate) {
        document.getElementById('start_date').value = startDate;
        document.getElementById('end_date').value = endDate;
        
        // Форматируем даты для отображения
        const startFormatted = new Date(startDate).toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'short' 
        });
        const endFormatted = new Date(endDate).toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'short',
            year: '2-digit'
        });
        
        document.querySelector('input[name="date_range"]').value = `${startFormatted} – ${endFormatted}`;
    }
    
    closeDatePicker();
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

// Автообновление курортов
function updateResorts() {
    const country = document.querySelector('select[name="destination_country"]').value;
    const resortSelect = document.getElementById('resortSelect');
    
    if (!country) {
        resortSelect.innerHTML = '<option value="">Выберите курорт</option>';
        return;
    }
    
    // Показываем загрузку
    resortSelect.innerHTML = '<option value="">Загрузка курортов...</option>';
    
    // Загружаем курорты через API
    fetch(`/api/tours/resorts?country=${encodeURIComponent(country)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resortSelect.innerHTML = '<option value="">Выберите курорт</option>';
                data.data.forEach(resort => {
                    const option = document.createElement('option');
                    option.value = resort;
                    option.textContent = resort;
                    resortSelect.appendChild(option);
                });
            } else {
                resortSelect.innerHTML = '<option value="">Курорты не найдены</option>';
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки курортов:', error);
            resortSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
        });
}

// Стили для карточек туров
document.addEventListener('DOMContentLoaded', function() {
    const tourCards = document.querySelectorAll('.tour-card');
    tourCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'all 0.3s ease';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
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
        document.querySelector('input[name="date_range"]').value = `${startFormatted} – ${endFormatted}`;
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


