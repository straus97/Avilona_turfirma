@extends('layouts.main')

@section('title', 'Поиск туров - Туристическая фирма Авилона')
@section('meta_description', 'Поиск лучших туров по выгодным ценам. Работаем с ведущими туроператорами России.')

@section('content')
<div class="container my-5">
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
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-map-marker-alt me-1"></i>Откуда
                            </label>
                            <select name="departure_city" class="form-select" required>
                                <option value="">Выберите город</option>
                                @foreach($departureCities as $city)
                                    <option value="{{ $city }}" {{ request('departure_city') == $city ? 'selected' : '' }}>
                                        {{ $city }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-globe me-1"></i>Куда
                            </label>
                            <select name="destination_country" class="form-select" required>
                                <option value="">Выберите страну</option>
                                @foreach($destinationCountries as $country)
                                    <option value="{{ $country }}" {{ request('destination_country') == $country ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-calendar-alt me-1"></i>Дата от
                            </label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" required min="{{ date('Y-m-d') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-calendar-alt me-1"></i>Дата до
                            </label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" min="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                    
                    <!-- Дополнительные фильтры -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-moon me-1"></i>Ночей (от)
                            </label>
                            <input type="number" name="nights_min" class="form-control" placeholder="7" min="1" max="30" value="{{ request('nights_min') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-moon me-1"></i>Ночей (до)
                            </label>
                            <input type="number" name="nights_max" class="form-control" placeholder="14" min="1" max="30" value="{{ request('nights_max') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-star me-1"></i>Звездность
                            </label>
                            <select name="hotel_stars" class="form-select">
                                <option value="">Любая</option>
                                <option value="3" {{ request('hotel_stars') == 3 ? 'selected' : '' }}>3★</option>
                                <option value="4" {{ request('hotel_stars') == 4 ? 'selected' : '' }}>4★</option>
                                <option value="5" {{ request('hotel_stars') == 5 ? 'selected' : '' }}>5★</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-utensils me-1"></i>Питание
                            </label>
                            <select name="meal_type" class="form-select">
                                <option value="">Любое</option>
                                <option value="BB" {{ request('meal_type') == 'BB' ? 'selected' : '' }}>BB (завтрак)</option>
                                <option value="HB" {{ request('meal_type') == 'HB' ? 'selected' : '' }}>HB (полупансион)</option>
                                <option value="FB" {{ request('meal_type') == 'FB' ? 'selected' : '' }}>FB (полный пансион)</option>
                                <option value="AI" {{ request('meal_type') == 'AI' ? 'selected' : '' }}>AI (всё включено)</option>
                                <option value="UAI" {{ request('meal_type') == 'UAI' ? 'selected' : '' }}>UAI (ультра)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Цены -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-ruble-sign me-1"></i>Цена от
                            </label>
                            <input type="number" name="price_min" class="form-control" placeholder="30000" min="0" value="{{ request('price_min') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-ruble-sign me-1"></i>Цена до
                            </label>
                            <input type="number" name="price_max" class="form-control" placeholder="200000" min="0" value="{{ request('price_max') }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-users me-1"></i>Взрослых
                            </label>
                            <input type="number" name="adults" class="form-control" placeholder="2" min="1" max="10" value="{{ request('adults', 2) }}">
                        </div>
                        
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label text-white fw-bold mb-2">
                                <i class="fas fa-child me-1"></i>Детей
                            </label>
                            <input type="number" name="children" class="form-control" placeholder="0" min="0" max="10" value="{{ request('children', 0) }}">
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-light btn-lg px-5 py-3 fw-bold shadow" style="font-size: 1.1rem;">
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
            <div class="row g-4">
                @foreach($tours as $tour)
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="card h-100 shadow-sm tour-card">
                            @if($tour->is_hot_deal)
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-danger">
                                        <i class="fas fa-fire me-1"></i>Горящий тур
                                    </span>
                                </div>
                            @endif
                            
                            <img src="{{ asset($tour->image_url) }}" class="card-img-top" alt="{{ $tour->title }}" 
                                 style="height: 200px; object-fit: cover;" 
                                 onerror="this.src='{{ asset('img/remont.png') }}'">
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title h6 mb-2">{{ $tour->hotel_name }}</h5>
                                <div class="mb-2">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star {{ $i <= $tour->hotel_stars ? 'text-warning' : 'text-muted' }}"></i>
                                    @endfor
                                </div>
                                
                                <p class="card-text small text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    {{ $tour->destination_city }}, {{ $tour->destination_country }}
                                </p>
                                
                                <p class="card-text small mb-2">
                                    <i class="fas fa-calendar me-1"></i>
                                    {{ $tour->start_date->format('d.m.Y') }} - {{ $tour->end_date->format('d.m.Y') }}
                                    <br>
                                    <i class="fas fa-moon me-1"></i>
                                    {{ $tour->nights }} ночей
                                </p>
                                
                                <p class="card-text small mb-3">
                                    <i class="fas fa-utensils me-1"></i>
                                    {{ $tour->meal_type ?? 'Не указано' }}
                                </p>
                                
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="h4 mb-0 text-primary fw-bold">{{ number_format($tour->price, 0, ',', ' ') }} ₽</div>
                                            <small class="text-muted">за человека</small>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary w-100" onclick="openBookingModal({{ $tour->id }})">
                                        <i class="fas fa-phone me-1"></i>Связаться с менеджером
                                    </button>
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
                            <input type="tel" name="phone" class="form-control" required placeholder="+7 (999) 123-45-67">
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

// Стили для карточек туров
document.addEventListener('DOMContentLoaded', function() {
    const tourCards = document.querySelectorAll('.tour-card');
    tourCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'all 0.3s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>
@endsection

