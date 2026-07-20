@extends('layouts.main')

@section('styles')
<style>
/* Современные стили для виджета поиска туров */
.tour-search-widget {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.modern-select,
.modern-input {
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    padding: 0.5rem 0.75rem;
}

.modern-select:focus,
.modern-input:focus {
    background: white;
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
    outline: none;
}

.modern-btn {
    background: linear-gradient(45deg, #ffc107, #ff8c00);
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
}

.modern-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 193, 7, 0.6);
    background: linear-gradient(45deg, #ff8c00, #ffc107);
}

.modern-btn:active {
    transform: translateY(0);
}

/* Стили для выпадающего списка туристов */
.tourist-popup-modal {
    position: absolute;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    min-width: 300px;
    animation: fadeIn 0.2s ease;
}

.tourist-popup-content {
    padding: 12px;
}

.tourist-popup-section {
    margin-bottom: 8px;
}

.tourist-popup-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
}

.tourist-popup-btn {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: bold;
    transition: all 0.2s ease;
}

.tourist-popup-btn:hover {
    background: #0056b3;
}

.tourist-popup-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.tourist-popup-count {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    min-width: 20px;
    text-align: center;
}

.tourist-popup-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #333;
}

.tourist-popup-children {
    margin-bottom: 8px;
}

.tourist-popup-child-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #f0f0f0;
}

.tourist-popup-child-item:last-child {
    border-bottom: none;
}

.tourist-popup-child-label {
    font-size: 0.8rem;
    color: #666;
}

.tourist-popup-child-age {
    font-size: 0.8rem;
    color: #333;
    font-weight: 500;
}

.tourist-popup-child-remove {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 3px;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.6rem;
    margin-left: 6px;
}

.tourist-popup-child-remove:hover {
    background: #c82333;
}

.tourist-popup-add-child {
    margin-bottom: 8px;
}

.tourist-popup-add-btn {
    background: transparent;
    color: #007bff;
    border: 1px solid #007bff;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    font-weight: 500;
}

.tourist-popup-add-btn:hover {
    background: #007bff;
    color: white;
}

.tourist-popup-add-btn:disabled {
    background: #6c757d;
    border-color: #6c757d;
    color: white;
    cursor: not-allowed;
}

.tourist-popup-age-grid {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #eee;
}

.tourist-popup-age-title {
    font-size: 0.75rem;
    font-weight: 500;
    color: #333;
    margin-bottom: 6px;
}

.tourist-popup-age-buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
}

.tourist-popup-age-btn {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    padding: 4px 6px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.tourist-popup-age-btn:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.tourist-popup-age-btn.selected {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

@keyframes slideIn {
    from { 
        opacity: 0; 
        transform: translateY(-20px) scale(0.95); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0) scale(1); 
    }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Стили для календаря диапазона дат */
.date-range-picker {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    z-index: 1000;
    padding: 20px;
    margin-top: 5px;
}

.calendar-container {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.calendar-nav-btn {
    background: #667eea;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.calendar-nav-btn:hover {
    background: #5a6fd8;
    transform: scale(1.1);
}

.calendar-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-bottom: 20px;
}

.calendar-weekday {
    text-align: center;
    font-weight: 600;
    color: #666;
    padding: 10px 0;
    font-size: 0.9rem;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    font-weight: 500;
    color: #333;
    background: #f8f9fa;
    border: 2px solid transparent;
    position: relative;
}

.calendar-day:hover {
    background: #e3f2fd;
    border-color: #2196f3;
    transform: scale(1.05);
}

.calendar-day.past {
    color: #ccc;
    background: #f5f5f5;
    cursor: not-allowed;
}

.calendar-day.past:hover {
    background: #f5f5f5;
    border-color: transparent;
    transform: none;
}

.calendar-day.today {
    background: #e3f2fd;
    color: #1976d2;
    font-weight: 700;
    border-color: #1976d2;
}

.calendar-day.selected {
    background: #2196f3;
    color: white;
    font-weight: 700;
}

.calendar-day.range-start {
    background: #1976d2;
    color: white;
    border-radius: 8px 0 0 8px;
}

.calendar-day.range-end {
    background: #1976d2;
    color: white;
    border-radius: 0 8px 8px 0;
}

.calendar-day.in-range {
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 0;
}

.calendar-day.range-start.range-end {
    border-radius: 8px;
}

.calendar-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 2px solid #f0f0f0;
}

.selected-range-display {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
}

.apply-btn {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.apply-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.apply-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Стили для графика цен */
.price-chart-widget {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.price-chart-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.chart-timeline {
    position: relative;
    margin-bottom: 15px;
}

.chart-bars {
    display: flex;
    align-items: end;
    justify-content: space-between;
    height: 120px;
    margin-bottom: 10px;
}

.chart-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.chart-bar:hover {
    transform: translateY(-2px);
}

.chart-bar.selected {
    background: rgba(102, 126, 234, 0.1);
    border-radius: 4px;
    padding: 2px;
}

.bar-fill {
    width: 100%;
    background: linear-gradient(to top, #667eea, #764ba2);
    border-radius: 2px 2px 0 0;
    min-height: 4px;
    transition: all 0.3s ease;
}

.chart-bar:hover .bar-fill {
    background: linear-gradient(to top, #5a6fd8, #6a4c93);
}

.bar-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #666;
    margin-top: 5px;
}

.chart-bar.selected .bar-label {
    color: #667eea;
    font-weight: 700;
}

.chart-months {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #666;
    font-weight: 500;
}

.chart-info {
    text-align: center;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
}

/* Анимации */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.date-range-picker {
    animation: fadeIn 0.3s ease;
}

/* Адаптивность */
@media (max-width: 768px) {
    .modern-select,
    .modern-input {
        font-size: 0.9rem;
    }
    
    .modern-btn {
        font-size: 1rem;
    }
    
    .calendar-grid {
        gap: 4px;
    }
    
    .calendar-day {
        font-size: 0.8rem;
    }
    
    .chart-bars {
        height: 80px;
    }
    
    .bar-label {
        font-size: 0.7rem;
    }
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
    
    <!-- Полная форма поиска туров -->
    <div class="tour-search-widget mb-4">
        <div class="card border-0 shadow-lg" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body p-4">
                <form action="{{ route('tours.index') }}" method="GET" id="tourSearchForm">
                    <!-- Основные поля в одну строку -->
                    <div class="row g-3 align-items-center mb-4">
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Откуда</label>
                            <div class="position-relative">
                                <select name="departure_city" class="form-select form-select-lg modern-select" required>
                                    <option value="">Выберите город</option>
                                    @foreach($departureCities as $city)
                                        <option value="{{ $city }}" {{ request('departure_city') == $city ? 'selected' : '' }}>
                                            {{ $city }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Куда</label>
                            <div class="position-relative">
                                <select name="destination_country" class="form-select form-select-lg modern-select" required>
                                    <option value="">Выберите страну</option>
                                    @foreach($destinationCountries as $country)
                                        <option value="{{ $country }}" {{ request('destination_country') == $country ? 'selected' : '' }}>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Интервал дат вылета</label>
                            <div class="position-relative">
                                <input type="text" name="date_range" class="form-control form-control-lg modern-input" placeholder="22 окт – 26 окт 25" readonly value="{{ request('date_range') }}">
                                <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
                                <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Количество ночей</label>
                            <div class="row g-1">
                                <div class="col-6">
                                    <select name="nights_min" class="form-select form-select-lg modern-select">
                                        <option value="">от</option>
                                        @for($i = 1; $i <= 30; $i++)
                                            <option value="{{ $i }}" {{ request('nights_min') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select name="nights_max" class="form-select form-select-lg modern-select">
                                        <option value="">до</option>
                                        @for($i = 1; $i <= 30; $i++)
                                            <option value="{{ $i }}" {{ request('nights_max') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Количество туристов</label>
                            <div class="position-relative">
                                <input type="text" name="tourist_summary" class="form-control form-control-lg modern-input" placeholder="2 взрослых" readonly onclick="toggleTouristDropdown()" id="touristSummary">
                                <input type="hidden" name="adults" id="adults" value="{{ request('adults', 2) }}">
                                <input type="hidden" name="children" id="children" value="{{ request('children', 0) }}">
                                <div id="childrenAges"></div>
                                
                                                <!-- Выпадающий список как в примере -->
                                                <div id="touristDropdown" class="tourist-popup-modal" style="display: none;">
                                                    <div class="tourist-popup-content">
                                                        <!-- Взрослые -->
                                                        <div class="tourist-popup-section">
                                                            <div class="tourist-popup-row">
                                                                <button type="button" class="tourist-popup-btn" onclick="changeAdults(-1)">−</button>
                                                                <span class="tourist-popup-count" id="adultsCount">{{ request('adults', 2) }}</span>
                                                                <span class="tourist-popup-label">взрослый</span>
                                                                <button type="button" class="tourist-popup-btn" onclick="changeAdults(1)">+</button>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Список детей -->
                                                        <div id="childrenList" class="tourist-popup-children"></div>
                                                        
                                                        <!-- Кнопка добавить ребенка -->
                                                        <div class="tourist-popup-add-child">
                                                            <button type="button" class="tourist-popup-add-btn" onclick="addChild()" id="addChildBtn">
                                                                Добавить ребёнка →
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- Сетка возрастов (показывается при добавлении ребенка) -->
                                                        <div id="ageSelectionGrid" class="tourist-popup-age-grid" style="display: none;">
                                                            <div class="tourist-popup-age-title">Возраст на момент окончания поездки:</div>
                                                            <div class="tourist-popup-age-buttons" id="ageButtons">
                                                                <!-- Кнопки возрастов будут добавлены динамически -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="w-100">
                                <div class="mb-2" style="height: 21px;"></div> <!-- Пустой div для выравнивания -->
                                <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold modern-btn">
                                    Найти туры
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Дополнительные фильтры -->
                    <div class="row g-3 mb-4">
                        <!-- Курорты -->
                        <div class="col-md-3">
                            <label class="form-label text-white fw-bold mb-2">Курорты</label>
                            <div id="resortsContainer" class="border rounded p-3" style="max-height: 150px; overflow-y: auto; background: rgba(255,255,255,0.95);">
                                <div class="text-muted small">Выберите страну</div>
                            </div>
                        </div>
                        
                        <!-- Категория отеля -->
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Категория отеля</label>
                            <select name="hotel_stars" class="form-select form-select-lg modern-select">
                                <option value="">Любая</option>
                                <option value="5" {{ request('hotel_stars') == 5 ? 'selected' : '' }}>5★</option>
                                <option value="4" {{ request('hotel_stars') == 4 ? 'selected' : '' }}>4★</option>
                                <option value="3" {{ request('hotel_stars') == 3 ? 'selected' : '' }}>3★</option>
                                <option value="2" {{ request('hotel_stars') == 2 ? 'selected' : '' }}>2★</option>
                                <option value="1" {{ request('hotel_stars') == 1 ? 'selected' : '' }}>1★</option>
                            </select>
                        </div>
                        
                        <!-- Питание -->
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Питание</label>
                            <select name="meal_type" class="form-select form-select-lg modern-select">
                                <option value="">Любое</option>
                                <option value="BB" {{ request('meal_type') == 'BB' ? 'selected' : '' }}>BB</option>
                                <option value="HB" {{ request('meal_type') == 'HB' ? 'selected' : '' }}>HB</option>
                                <option value="FB" {{ request('meal_type') == 'FB' ? 'selected' : '' }}>FB</option>
                                <option value="AI" {{ request('meal_type') == 'AI' ? 'selected' : '' }}>AI</option>
                                <option value="UAI" {{ request('meal_type') == 'UAI' ? 'selected' : '' }}>UAI</option>
                            </select>
                        </div>
                        
                        <!-- Пляжная линия -->
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Пляжная линия</label>
                            <select name="beach_line" class="form-select form-select-lg modern-select">
                                <option value="">Любая</option>
                                <option value="1" {{ request('beach_line') == 1 ? 'selected' : '' }}>1-я <100м</option>
                                <option value="2" {{ request('beach_line') == 2 ? 'selected' : '' }}>2-я <500м</option>
                                <option value="3" {{ request('beach_line') == 3 ? 'selected' : '' }}>3-я <2км</option>
                            </select>
                        </div>
                        
                        <!-- Рейтинг отеля -->
                        <div class="col-md-2">
                            <label class="form-label text-white fw-bold mb-2">Рейтинг отеля</label>
                            <select name="hotel_rating" class="form-select form-select-lg modern-select">
                                <option value="">Любой</option>
                                <option value="7" {{ request('hotel_rating') == 7 ? 'selected' : '' }}>7+</option>
                                <option value="8" {{ request('hotel_rating') == 8 ? 'selected' : '' }}>8+</option>
                                <option value="9" {{ request('hotel_rating') == 9 ? 'selected' : '' }}>9+</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Диапазон цен и дополнительные опции -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label text-white fw-bold mb-2">Диапазон цен</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="price_min" class="form-control form-control-lg modern-input" placeholder="ОТ" min="0" value="{{ request('price_min') }}">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="price_max" class="form-control form-control-lg modern-input" placeholder="ДО" min="0" value="{{ request('price_max') }}">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Туроператоры -->
                        <div class="col-md-3">
                            <label class="form-label text-white fw-bold mb-2">Туроператоры</label>
                            <div id="tourOperatorsContainer" class="border rounded p-3" style="max-height: 150px; overflow-y: auto; background: rgba(255,255,255,0.95);">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Ambotis" id="ambotis" {{ in_array('Ambotis', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ambotis">Ambotis</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Anex Tour" id="anex" {{ in_array('Anex Tour', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="anex">Anex Tour</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Biblio Globus" id="biblio_globus" {{ in_array('Biblio Globus', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="biblio_globus">Библио Глобус</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Bon Tour" id="bon_tour" {{ in_array('Bon Tour', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="bon_tour">Bon Tour</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="BSI Group" id="bsi_group" {{ in_array('BSI Group', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="bsi_group">BSI Group</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Coral Travel" id="coral_travel" {{ in_array('Coral Travel', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="coral_travel">Coral Travel</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Delfin" id="delfin" {{ in_array('Delfin', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="delfin">Delfin</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Express Tours" id="express_tours" {{ in_array('Express Tours', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="express_tours">Express Tours</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Good Time" id="good_time" {{ in_array('Good Time', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="good_time">Good Time</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="ICS" id="ics" {{ in_array('ICS', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ics">ICS Travel Group</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Intourist" id="intourist" {{ in_array('Intourist', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="intourist">Интурист</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="ITM Group" id="itm_group" {{ in_array('ITM Group', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="itm_group">ITM Group</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Mouzenidis Travel" id="mouzenidis_travel" {{ in_array('Mouzenidis Travel', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mouzenidis_travel">Mouzenidis Travel</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="PAC Group" id="pac_group" {{ in_array('PAC Group', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="pac_group">PAC Group</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Pegas" id="pegas" {{ in_array('Pegas', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="pegas">Pegas Touristik</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Russian Express" id="russian_express" {{ in_array('Russian Express', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="russian_express">Russian Express</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Sunmar" id="sunmar" {{ in_array('Sunmar', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sunmar">Sunmar</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="Tez Tour" id="tez_tour" {{ in_array('Tez Tour', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tez_tour">Tez Tour</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="TUI" id="tui" {{ in_array('TUI', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tui">TUI</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tour_operators[]" value="West Travel" id="west_travel" {{ in_array('West Travel', request('tour_operators', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="west_travel">West Travel</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label text-white fw-bold mb-2">Дополнительные опции</label>
                            <div class="d-flex flex-wrap gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="charter" id="charter" {{ request('charter') ? 'checked' : '' }}>
                                    <label class="form-check-label text-white" for="charter">Чартерные</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="direct" id="direct" {{ request('direct') ? 'checked' : '' }}>
                                    <label class="form-check-label text-white" for="direct">Прямые</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="nonstop" id="nonstop" {{ request('nonstop') ? 'checked' : '' }}>
                                    <label class="form-check-label text-white" for="nonstop">Без стопов</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-light btn-lg w-100" onclick="resetFilters()">
                                Сбросить фильтры
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- График низких цен -->
    <div class="price-chart-widget mb-4">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">График низких цен на туры в {{ request('destination_country', 'выбранную страну') }} для {{ request('adults', 2) }} взрослых с вылетом из {{ request('departure_city', 'выбранного города') }} на {{ request('nights_min', 7) }}-{{ request('nights_max', 14) }} ночей</h6>
                <div class="price-chart-container">
                    <div class="chart-timeline">
                        <div class="chart-bars">
                            @for($i = 0; $i < 14; $i++)
                                @php
                                    $date = now()->addDays($i);
                                    $isSelected = request('start_date') && request('end_date') && 
                                        $date->between(request('start_date'), request('end_date'));
                                    $price = rand(25000, 150000);
                                @endphp
                                <div class="chart-bar {{ $isSelected ? 'selected' : '' }}" data-date="{{ $date->format('Y-m-d') }}" data-price="{{ $price }}">
                                    <div class="bar-fill" style="height: {{ ($price - 25000) / 125000 * 100 }}%"></div>
                                    <div class="bar-label">{{ $date->format('d') }}</div>
                                </div>
                            @endfor
                        </div>
                        <div class="chart-months">
                            <span>Октябрь 25</span>
                            <span>Ноябрь 25</span>
                        </div>
                    </div>
                    <div class="chart-info">
                        <small class="text-muted">Указаны минимальные цены за человека в рублях</small>
                    </div>
                </div>
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
                                                        🔥 Горящий тур
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
                                                <span class="{{ $i <= $tour->hotel_stars ? 'text-warning' : 'text-muted' }}">★</span>
                                            @endfor
                                        </div>
                                        
                                        <!-- Местоположение -->
                                        <div class="mb-2 text-muted">
                                            📍 {{ $tour->destination_city }}, {{ $tour->destination_country }}
                                        </div>
                                        
                                        <!-- Даты и продолжительность -->
                                        <div class="mb-2">
                                            📅 <span class="fw-medium">{{ $tour->start_date->format('d.m.Y') }} - {{ $tour->end_date->format('d.m.Y') }}</span>
                                            <span class="text-muted">({{ $tour->nights }} ночей)</span>
                                        </div>
                                        
                                        <!-- Питание -->
                                        <div class="mb-2">
                                            🍽️ <span class="text-muted">{{ $tour->meal_type ?? 'Не указано' }}</span>
                                        </div>
                                        
                                        <!-- Дополнительная информация -->
                                        <div class="small text-muted">
                                            ✈️ Вылет из {{ $tour->departure_city }}
                                        </div>
                                    </div>
                                    
                                    <!-- Правая часть - цена и кнопка -->
                                    <div class="col-md-3 text-end">
                                        <div class="mb-3">
                                            <div class="h3 mb-1 text-primary fw-bold">{{ number_format($tour->price, 0, ',', ' ') }} ₽</div>
                                            <small class="text-muted">за человека</small>
                                        </div>
                                        
                                        <button type="button" class="btn btn-primary btn-lg w-100" onclick="openBookingModal({{ $tour->id }})">
                                            📞 Связаться с менеджером
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
                <div class="mb-3">ℹ️</div>
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
<!-- Подключаем jQuery и библиотеки для выбора дат -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" />

<script>
function openBookingModal(tourId) {
    document.getElementById('bookingTourId').value = tourId;
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

// Инициализация daterangepicker
$(document).ready(function() {
    // Настройка локали для moment.js
    moment.locale('ru', {
        months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
        monthsShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
        weekdays: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
        weekdaysShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
        weekdaysMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб']
    });

    // Инициализация daterangepicker
    $('input[name="date_range"]').daterangepicker({
        opens: 'left',
        autoUpdateInput: false,
        locale: {
            format: 'DD.MM.YYYY',
            separator: ' - ',
            applyLabel: 'Применить',
            cancelLabel: 'Отмена',
            fromLabel: 'От',
            toLabel: 'До',
            customRangeLabel: 'Выбрать',
            weekLabel: 'Нед',
            daysOfWeek: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
            monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
            firstDay: 1
        },
        startDate: moment().add(1, 'day'),
        endDate: moment().add(8, 'days'),
        minDate: moment(),
        maxDate: moment().add(2, 'years')
    });

    // Устанавливаем значения из запроса если они есть
    @if(request('start_date') && request('end_date'))
        const startDate = moment('{{ request('start_date') }}');
        const endDate = moment('{{ request('end_date') }}');
        $('input[name="date_range"]').val(startDate.format('DD.MM.YYYY') + ' - ' + endDate.format('DD.MM.YYYY'));
    @endif

    // Обработчик применения дат
    $('input[name="date_range"]').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('DD.MM.YYYY') + ' - ' + picker.endDate.format('DD.MM.YYYY'));
        $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
        $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
    });

    // Обработчик отмены выбора дат
    $('input[name="date_range"]').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        $('#start_date').val('');
        $('#end_date').val('');
    });
});

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
        .then(response => {
            console.log('Resorts API response:', response);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Resorts data:', data);
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

function resetFilters() {
    console.log('Reset filters clicked');
    
    // Сбрасываем все поля формы
    const form = document.getElementById('tourSearchForm');
    if (form) {
        form.reset();
        console.log('Form reset');
    } else {
        console.error('Form not found');
    }
    
    // Очищаем скрытые поля дат
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const dateRange = document.querySelector('input[name="date_range"]');
    
    if (startDate) startDate.value = '';
    if (endDate) endDate.value = '';
    if (dateRange) dateRange.value = '';
    
    // Очищаем курорты
    const resortsContainer = document.getElementById('resortsContainer');
    if (resortsContainer) {
        resortsContainer.innerHTML = '<div class="text-muted small">Выберите страну</div>';
    }
    
    // Восстанавливаем курорты если страна выбрана
    const countrySelect = document.querySelector('select[name="destination_country"]');
    if (countrySelect && countrySelect.value) {
        console.log('Restoring resorts for country:', countrySelect.value);
        updateResorts();
    }
    
    // Сбрасываем туроператоров
    const tourOperatorCheckboxes = document.querySelectorAll('input[name="tour_operators[]"]');
    tourOperatorCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Сбрасываем виджет туристов
    adultsCount = 2;
    childrenList = [];
    const adultsField = document.getElementById('adults');
    const childrenField = document.getElementById('children');
    
    if (adultsField) adultsField.value = 2;
    if (childrenField) childrenField.value = 0;
    
    updateTouristSummary();
    updateChildrenAgesHidden();
    renderChildrenList();
    updatePopupSummary();
    
    // Перезагружаем страницу для полного сброса
    console.log('Redirecting to tours index');
    window.location.href = '{{ route("tours.index") }}';
}

// Делаем функцию доступной глобально
window.resetFilters = resetFilters;

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
        
        // Загружаем курорты если страна уже выбрана при загрузке страницы
        if (countrySelect.value) {
            console.log('Loading resorts for initial country:', countrySelect.value);
            updateResorts();
        }
    }

    // Функции для управления количеством туристов
    let adultsCount = {{ request('adults', 2) }};
    let childrenList = [];
    let currentChildIndex = -1; // Для выбора возраста

    // Инициализация детей из запроса
    @if(request('children', 0) > 0)
        @for($i = 1; $i <= request('children', 0); $i++)
            childrenList.push({{ request("children_age_$i", 5) }});
        @endfor
    @endif

    window.toggleTouristDropdown = function(event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        console.log('Toggle tourist dropdown clicked');
        const dropdown = document.getElementById('touristDropdown');
        console.log('Dropdown element:', dropdown);
        console.log('Current display:', dropdown.style.display);
        
        if (dropdown.style.display === 'none' || dropdown.style.display === '') {
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
        console.log('New display:', dropdown.style.display);
    };

    window.closeTouristDropdown = function() {
        document.getElementById('touristDropdown').style.display = 'none';
        updateTouristSummary();
    };

    window.changeAdults = function(delta) {
        adultsCount = Math.max(1, Math.min(20, adultsCount + delta));
        document.getElementById('adultsCount').textContent = adultsCount;
        document.getElementById('adults').value = adultsCount;
        updateTouristSummary();
    };

    window.addChild = function() {
        if (childrenList.length < 10) {
            currentChildIndex = childrenList.length;
            childrenList.push(5); // Возраст по умолчанию
            showAgeSelection();
            updateTouristSummary();
            updatePopupSummary();
        }
    };

    window.removeChild = function(index) {
        childrenList.splice(index, 1);
        renderChildrenList();
        updateTouristSummary();
    };

    window.updateChildAge = function(index, age) {
        childrenList[index] = parseInt(age);
        updateChildrenAgesHidden();
    };

    window.selectAge = function(age) {
        if (currentChildIndex >= 0) {
            childrenList[currentChildIndex] = parseInt(age);
            hideAgeSelection();
            renderChildrenList();
            updateChildrenAgesHidden();
            updateTouristSummary();
            updatePopupSummary();
        }
    };

    function showAgeSelection() {
        const ageGrid = document.getElementById('ageSelectionGrid');
        const ageButtons = document.getElementById('ageButtons');
        
        ageButtons.innerHTML = '';
        
        const ages = [
            { value: 0, text: 'До года' },
            { value: 1, text: '1 год' },
            { value: 2, text: '2 года' },
            { value: 3, text: '3 года' },
            { value: 4, text: '4 года' },
            { value: 5, text: '5 лет' },
            { value: 6, text: '6 лет' },
            { value: 7, text: '7 лет' },
            { value: 8, text: '8 лет' },
            { value: 9, text: '9 лет' },
            { value: 10, text: '10 лет' },
            { value: 11, text: '11 лет' },
            { value: 12, text: '12 лет' },
            { value: 13, text: '13 лет' },
            { value: 14, text: '14 лет' },
            { value: 15, text: '15 лет' },
            { value: 16, text: '16 лет' },
            { value: 17, text: '17 лет' }
        ];
        
        ages.forEach(age => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tourist-popup-age-btn';
            button.textContent = age.text;
            button.onclick = () => selectAge(age.value);
            ageButtons.appendChild(button);
        });
        
        ageGrid.style.display = 'block';
    }

    function hideAgeSelection() {
        document.getElementById('ageSelectionGrid').style.display = 'none';
        currentChildIndex = -1;
    }

    function updatePopupSummary() {
        const summary = document.getElementById('touristPopupSummary');
        let text = `${adultsCount} взр`;
        
        if (childrenList.length > 0) {
            text += ` и ${childrenList.length} реб`;
        }
        
        summary.textContent = text;
    }

    function renderChildrenList() {
        const container = document.getElementById('childrenList');
        container.innerHTML = '';
        
        childrenList.forEach((age, index) => {
            const childItem = document.createElement('div');
            childItem.className = 'tourist-popup-child-item';
            childItem.innerHTML = `
                <span class="tourist-popup-child-label">Ребёнок ${index + 1}</span>
                <div style="display: flex; align-items: center;">
                    <span class="tourist-popup-child-age">${age} лет</span>
                    <button type="button" class="tourist-popup-child-remove" onclick="removeChild(${index})" title="Удалить">×</button>
                </div>
            `;
            container.appendChild(childItem);
        });
        
        // Обновляем кнопку "Добавить ребенка"
        const addBtn = document.getElementById('addChildBtn');
        if (childrenList.length >= 10) {
            addBtn.disabled = true;
            addBtn.textContent = 'Максимум 10 детей';
        } else {
            addBtn.disabled = false;
            addBtn.textContent = '+ Добавить ребёнка';
        }
    }

    function updateChildrenAgesHidden() {
        const container = document.getElementById('childrenAges');
        container.innerHTML = '';
        
        childrenList.forEach((age, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `children_age_${index + 1}`;
            input.value = age;
            container.appendChild(input);
        });
        
        // Обновляем скрытое поле количества детей
        document.getElementById('children').value = childrenList.length;
    }

    function updateTouristSummary() {
        const summary = document.getElementById('touristSummary');
        let text = `${adultsCount} взросл${adultsCount === 1 ? 'ый' : adultsCount < 5 ? 'ых' : 'ых'}`;
        
        if (childrenList.length > 0) {
            text += ` + ${childrenList.length} ребен${childrenList.length === 1 ? 'ок' : childrenList.length < 5 ? 'ка' : 'ок'}`;
        }
        
        summary.value = text;
    }

    // Закрытие модального окна при клике вне его
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('touristDropdown');
        const input = document.getElementById('touristSummary');
        
        // Проверяем, что клик был вне выпадающего списка и поля ввода
        if (dropdown && dropdown.style.display === 'block' && 
            !dropdown.contains(event.target) && 
            !input.contains(event.target)) {
            closeTouristDropdown();
        }
    });

    // Инициализация виджета туристов
    updateTouristSummary();
    updateChildrenAgesHidden();
    renderChildrenList();
    updatePopupSummary();
});
</script>
@endsection