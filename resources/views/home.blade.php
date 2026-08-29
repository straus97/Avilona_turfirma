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
    border-color: #01466f;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.7);
    outline: none;
}

/* E2-A1-I1: кнопка поиска приведена к первичной кнопке E2 — сплошной
   «закатный» оранжевый, без цветного свечения и без принудительного
   верхнего регистра. Механика поиска не затрагивается. */
.modern-btn {
    background: #b8500f;
    border: none;
    border-radius: 10px;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.2px;
    color: #fff;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.12);
}

.modern-btn:hover {
    background: #98410b;
    color: #fff;
    box-shadow: 0 4px 12px rgba(16, 24, 40, 0.18);
}

.modern-btn:active {
    transform: none;
}

@media (prefers-reduced-motion: reduce) {
    .modern-select,
    .modern-input,
    .modern-btn {
        transition: none;
    }
}

/* Стили для выпадающего списка туристов */
.tourist-popup-modal {
    position: absolute !important;
    top: calc(100% + 5px) !important;
    left: 0 !important;
    right: 0 !important;
    background: white !important;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3) !important;
    z-index: 10001 !important;
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
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    padding: 4px 6px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    color: #333333;
}

.tourist-popup-age-btn:hover {
    background: #007bff;
    border-color: #007bff;
    color: white;
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

/* Адаптивность */
@media (max-width: 768px) {
    .modern-select,
    .modern-input {
        font-size: 0.8rem;
    }
    
    .modern-btn {
        font-size: 0.9rem;
    }
}
</style>
@endsection {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Главная - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на главную страницу туристической фирмы Авилона. Туристическая фирма Авилона предлагает удобный поиск туров, лучшие горящие предложения, последние новости и отзывы клиентов. Наши опытные менеджеры помогут вам выбрать идеальный отпуск. Индивидуальный подход, проверенные отели и высокий уровень сервиса.')
@section('meta_keywords', 'поиск туров, горящие предложения, отзывы клиентов, новости туризма, туристическое агентство, контактная информация, карта местоположения, туристическая фирма Авилона, главная, туристическая фирма, туры, путевки, акции')

<!-- Main Content -->
@section('content')
    <!-- Модальное окно для связи с менеджерами -->
    <div id="contactManagersModal" class="modal-managers">
        <div class="modal-content-managers">
            <span class="close-button-managers">&times;</span>
            <div>
                <p>Связаться с менеджером Илона через:</p>
                <button class="whatsapp-button-managers" onclick="openWhatsAppManagers('+79219314345')">
                    <i class="fab fa-whatsapp fa-2x" style="color: #006600;"></i> WhatsApp
                </button>
                <button class="telegram-button-managers" onclick="openTelegramManagers('+79219314345')">
                    <i class="fa fa-telegram fa-2x" aria-hidden="true"></i> Telegram
                </button>
            </div>
            <hr>
            <div>
                <p>Связаться с менеджером Алла через:</p>
                <button class="whatsapp-button-managers" onclick="openWhatsAppManagers('+79219842022')">
                    <i class="fab fa-whatsapp fa-2x" style="color: #006600;"></i> WhatsApp
                </button>
                <button class="telegram-button-managers" onclick="openTelegramManagers('+79219842022')">
                    <i class="fa fa-telegram fa-2x" aria-hidden="true"></i> Telegram
                </button>
            </div>
        </div>
    </div>
    <main>
        <div class="container">
            <section class="e2-hero" aria-labelledby="e2-hero-title">
                <div class="e2-hero__body">
                    <div class="e2-hero__content">
                        <h1 id="e2-hero-title" class="e2-hero__title">Поможем выбрать и организовать ваше путешествие</h1>
                        <p class="e2-hero__lead">Индивидуальный подбор туров, проверенные отели и поддержка опытного менеджера —
                            от выбора направления до вылета из Санкт-Петербурга.</p>
                        <div class="e2-hero__actions">
                            <a class="e2-btn e2-btn--primary" href="#tour-search">Подобрать тур</a>
                            <a class="e2-btn e2-btn--secondary" href="{{ route('countries.index') }}">Смотреть направления</a>
                        </div>
                    </div>
                    {{-- Поддерживающий визуальный блок хиро: только XL+, декоративная опора,
                         не набор конкурирующих CTA. Иконки — уже подключённый Bootstrap Icons. --}}
                    <div class="e2-hero__aside d-none d-xl-block">
                        <ul class="e2-hero__benefits">
                            <li class="e2-hero__benefit">
                                <span class="e2-hero__benefit-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                                <span>
                                    <span class="e2-hero__benefit-title">Подбор направления</span>
                                    <span class="e2-hero__benefit-text">Куда поехать под ваши даты, бюджет и пожелания</span>
                                </span>
                            </li>
                            <li class="e2-hero__benefit">
                                <span class="e2-hero__benefit-icon e2-hero__benefit-icon--sea"><i class="bi bi-airplane" aria-hidden="true"></i></span>
                                <span>
                                    <span class="e2-hero__benefit-title">Отель и перелёт</span>
                                    <span class="e2-hero__benefit-text">Собираем перелёт и проживание в одну поездку</span>
                                </span>
                            </li>
                            <li class="e2-hero__benefit">
                                <span class="e2-hero__benefit-icon"><i class="bi bi-headset" aria-hidden="true"></i></span>
                                <span>
                                    <span class="e2-hero__benefit-title">Поддержка менеджера</span>
                                    <span class="e2-hero__benefit-text">Личный менеджер на связи от заявки до вылета</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
            <div class="row my-3">
                <div class="col">
                    <!-- Современный виджет поиска туров (временный блок, механика — E5) -->
                    <div class="tour-search-widget e2-search mb-4" id="tour-search" style="position: relative; z-index: 1;">
                        <div class="card border-0 e2-search-card">
                            <div class="card-body p-4" style="overflow: visible; position: relative;">
                                <form action="{{ route('tours.index') }}" method="GET" id="tourSearchForm" onsubmit="return validateTourSearchForm()">
                                    <!-- Основные поля в одну строку -->
                                    <div class="row g-3 align-items-center">
                                        <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                                            <label class="form-label text-white fw-bold mb-2">Откуда</label>
                                            <div class="position-relative">
                                                <select name="departure_city" class="form-select form-select-lg modern-select" required>
                                                    <option value="">Выберите город</option>
                                                    <option value="Санкт-Петербург" selected>Санкт-Петербург</option>
                                                    <option value="Москва">Москва</option>
                                                    <option value="Екатеринбург">Екатеринбург</option>
                                                    <option value="Новосибирск">Новосибирск</option>
                                                    <option value="Казань">Казань</option>
                                                    <option value="Ставрополь">Ставрополь</option>
                                                    <option value="Самара">Самара</option>
                                                    <option value="Тюмень">Тюмень</option>
                                                    <option value="Уфа">Уфа</option>
                                                    <option value="Нижний Новгород">Нижний Новгород</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                                            <label class="form-label text-white fw-bold mb-2">Куда</label>
                                            <div class="position-relative">
                                                <select name="destination_country" class="form-select form-select-lg modern-select" required>
                                                    <option value="">Выберите страну</option>
                                                    <option value="Турция">Турция</option>
                                                    <option value="Египет">Египет</option>
                                                    <option value="ОАЭ">ОАЭ</option>
                                                    <option value="Тайланд">Таиланд</option>
                                                    <option value="Испания">Испания</option>
                                                    <option value="Россия">Россия</option>
                                                    <option value="Абхазия">Абхазия</option>
                                                    <option value="Китай">Китай</option>
                                                    <option value="Вьетнам">Вьетнам</option>
                                                    <option value="Куба">Куба</option>
                                                    <option value="Мальдивы">Мальдивы</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                                            <label class="form-label text-white fw-bold mb-2">Интервал дат вылета</label>
                                            <div class="position-relative">
                                                <input type="text" name="date_range" class="form-control form-control-lg modern-input" placeholder="Выберите даты" readonly>
                                                <input type="hidden" name="start_date" id="start_date">
                                                <input type="hidden" name="end_date" id="end_date">
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                                            <label class="form-label text-white fw-bold mb-2">Количество ночей</label>
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <select name="nights_min" class="form-select form-select-lg modern-select">
                                                        <option value="">от</option>
                                                        @for($i = 1; $i <= 30; $i++)
                                                            <option value="{{ $i }}">{{ $i }}</option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select name="nights_max" class="form-select form-select-lg modern-select">
                                                        <option value="">до</option>
                                                        @for($i = 1; $i <= 30; $i++)
                                                            <option value="{{ $i }}">{{ $i }}</option>
                                                        @endfor
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                                            <label class="form-label text-white fw-bold mb-2">Количество туристов</label>
                                            <div class="position-relative" style="z-index: 10000;">
                                                <input type="text" name="tourist_summary" class="form-control form-control-lg modern-input" placeholder="2 взрослых" readonly onclick="toggleTouristDropdown()" id="touristSummary">
                                                <input type="hidden" name="adults" id="adults" value="2">
                                                <input type="hidden" name="children" id="children" value="0">
                                                <div id="childrenAges"></div>
                                                
                                                <!-- Выпадающий список как в примере -->
                                                <div id="touristDropdown" class="tourist-popup-modal" style="display: none; position: absolute !important; z-index: 10001 !important;">
                                                    <div class="tourist-popup-content">
                                                        <!-- Взрослые -->
                                                        <div class="tourist-popup-section">
                                                            <div class="tourist-popup-row">
                                                                <button type="button" class="tourist-popup-btn" onclick="changeAdults(-1)">−</button>
                                                                <span class="tourist-popup-count" id="adultsCount">2</span>
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
                                        
                                        <div class="col-12 col-sm-6 col-lg-4 col-xl-2 d-flex align-items-end">
                                            <div class="w-100">
                                                <div class="mb-2 d-none d-xl-block" style="height: 21px;"></div> <!-- Выравнивание с полями только в одну строку (xl) -->
                                                <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold modern-btn">
                                                    Найти туры
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Best offers -->
            @if(isset($best_offers) && $best_offers->count() > 0)
            <div class="row" id="block_best_offers">
                <div class="col-12">
                    <h2 class="text-center mb-3 p-3 border-top border-2 border-bottom e2-section-title">Лучшие предложения</h2>
                    <div class="row">
                        @foreach($best_offers as $item_best_offers)
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
                                <div class="card h-100 d-flex flex-column">
                                    @if($item_best_offers->image)
                                    <img src="{{ asset($item_best_offers->image) }}" class="card-img-top"
                                         alt="{{ $item_best_offers->title }}"
                                         style="height: 200px; object-fit: cover;">
                                    @endif
                                    <div class="card-body flex-grow-1">
                                        <h5 class="card-title">{{ $item_best_offers->title ?? 'Без названия' }}</h5>
                                        <p class="card-text">{!! Str::limit($item_best_offers->content ?? '', 100) !!}</p>
                                    </div>
                                    <div class="card-footer">
                                        <small
                                            class="text-muted">{{ $item_best_offers->created_at ? \Carbon\Carbon::parse($item_best_offers->created_at)->translatedFormat('j F Y г.') : '' }}</small>
                                    </div>
                                    <div class="card-footer">
                                        <small class=""><a href="#" onclick="openContactModal()"
                                                           class="btn btn-primary btn-sm w-100">Связаться с менеджером</a></small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
            <!-- Reviews of our customers -->
            @if(isset($reviews) && $reviews->count() > 0)
            <div class="row" id="block_reviews">
                <div class="col-md-12">
                    <h2 class="text-center mb-3 p-3 border-top border-2 border-bottom e2-section-title">Отзывы</h2>
                </div>
            </div>
            <div class="row">
                @foreach($reviews as $item_reviews)
                    <div class="col-12 col-sm-6 col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 d-flex flex-column">
                            <div class="card-body flex-grow-1">
                                <div class="d-flex align-items-center mb-3">
                                    @if($item_reviews->image)
                                    <img src="{{ asset($item_reviews->image) }}" class="mr-2" alt="User Avatar"
                                         style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;">
                                    @endif
                                    <h5 class="card-title mb-0">{{ $item_reviews->name ?? 'Анонимный пользователь' }}</h5>
                                </div>
                                <p class="content card-text">{{ Str::limit($item_reviews->content ?? '', 200) }}</p>
                                @if($item_reviews->content && strlen($item_reviews->content) > 200)
                                    <p class="full-content d-none">{{ $item_reviews->content }}</p>
                                    <button class="btn btn-primary btn-sm read-more mt-2">Читать полностью</button>
                                @endif
                                @if($item_reviews->is_moderator_edited)
                                    <p class="small text-muted fst-italic mb-0 mt-2">Текст отзыва отредактирован модератором без изменения общего смысла.</p>
                                @endif
                            </div>
                            <div class="card-footer">
                                <small
                                    class="text-muted">{{ $item_reviews->created_at ? \Carbon\Carbon::parse($item_reviews->created_at)->translatedFormat('j F Y г.') : '' }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
            @if(isset($news) && $news->count() > 0)
            <h2 class="text-center mb-3 p-3 border-top border-2 border-bottom e2-section-title">Новости</h2>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 mt-3">
                @foreach($news as $item_news)
                    <div class="col-12 col-md-4 col-lg-3">
                        <div class="card h-100 d-flex flex-column">
                            @if($item_news->image)
                            <img src="{{ $item_news->image }}" class="card-img-top" alt="{{ $item_news->title ?? 'Новость' }}"
                                 style="height: 200px; object-fit: cover;">
                            @endif
                            <div class="card-body flex-grow-1 d-flex flex-column">
                                <h5 class="card-title">{{ $item_news->title ?? 'Без названия' }}</h5>
                                <p class="card-text flex-grow-1">{{ Str::limit(strip_tags($item_news->description ?? ''), 100) }}</p>
                                @if($item_news->slug)
                                <a href="{{ route('helpful_news_id.index', $item_news->slug) }}"
                                   class="btn btn-primary btn-sm mt-auto">Подробнее</a>
                                @endif
                            </div>
                            <div class="card-footer text-muted">{{ $item_news->pub_date ? \Carbon\Carbon::parse($item_news->pub_date)->translatedFormat('j F Y г.') : '' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
            @if(isset($partners) && $partners->count() > 0)
            <div class="container my-3">
                <h2 class="text-center mb-3 p-3 border-top border-2 border-bottom e2-section-title">Наши партнеры</h2>
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-4">
                    @foreach ($partners as $item_partner)
                        <div class="col">
                            <div class="h-100 d-flex align-items-center justify-content-center bg-light rounded p-3" style="min-height: 120px;">
                                @if($item_partner->logo_partner)
                                <img src="{{ $item_partner->logo_partner }}" 
                                     class="img-fluid"
                                     alt="{{ $item_partner->name_partner ?? 'Партнер' }} logo"
                                     style="max-height: 100px; max-width: 100%; object-fit: contain;">
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            <div class="container py-5">
                <div class="row">
                    <div class="col-md-6">
                        <h2 class="mb-4 e2-section-title">Туристическое агентство «Авилона»</h2>
                        <p><strong>Адрес офиса:</strong><br>198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
                        <p><strong>Телефоны:</strong><br>+7 (921) 931-43-45, +7 (921) 984-20-22</p>
                        <p><strong>Эл. почта:</strong><br>avilonatur@bk.ru</p>
                        <p><strong>Режим работы:</strong><br>Понедельник - пятница, с 10:00 до 20:00,<br>Суббота -
                            воскресенье, с 12:00 до 19:00</p>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-4 e2-section-title">Есть вопросы — спрашивайте!</h2>
                        <p>Менеджеры туристического агентства «Авилона» с радостью помогут Вам найти ответы на
                            интересующие Вас вопросы, окажут консультацию или запишут Вас на посещение нашего
                            туристического офиса.<br>Мы всегда рады Вам!</p>
                        {{-- проверка правильного отправления формы и открытие всплывающего окна --}}
                        @if (session('success'))
                            <script>
                                $(function () {
                                    $('#successModal').modal('show');
                                });
                            </script>
                        @endif
                        @if (session('error'))
                            <script>
                                $(function () {
                                    $('#errorModal').modal('show');
                                });
                            </script>
                        @endif
                        <!-- Модальное окно с сообщением об успешной отправки -->
                        <div class="modal fade" id="successModal" tabindex="-1" role="dialog"
                             aria-labelledby="successModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="successModalLabel">Сообщение отправлено</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Ваше сообщение успешно отправлено!
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Закрыть
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Модальное окно с сообщением об ошибке -->
                        <div class="modal fade" id="errorModal" tabindex="-1" role="dialog"
                             aria-labelledby="errorModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="errorModalLabel">Ошибка отправки сообщения</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Извините, возникла ошибка при отправке сообщения. Попробуйте еще раз позже.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Закрыть
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form action="{{ route('contact.send_home') }}" id="send_home" method="post"
                              class="needs-validation" novalidate>
                            @csrf
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="name" class="form-label">Ваше имя</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           placeholder="Например, Никита" value="{{ old('name') }}" required>
                                    <div class="valid-feedback">
                                        Поле заполнено верно!
                                    </div>
                                    <div class="invalid-feedback">
                                        Пожалуйста, введите свое имя
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           placeholder="Например, test@mail.ru" value="{{ old('email') }}" required>
                                    <div class="valid-feedback">
                                        Поле заполнено верно!
                                    </div>
                                    <div class="invalid-feedback">
                                        Пожалуйста, введите корректный email
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Тема</label>
                                <input type="text" class="form-control" id="subject" name="subject"
                                       placeholder="Тема вашего обращения..." value="{{ old('subject') }}">
                                <div class="valid-feedback">
                                    Вы можете оставить пустым это поле, если не знаете какую тему указать
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Ваше сообщение</label>
                                <textarea class="form-control" id="message" rows="5" name="message"
                                          placeholder="Введите свое сообщение..." minlength="50"
                                          required>{{ old('message') }}</textarea>
                                <div class="valid-feedback">
                                    Поле заполнено верно!
                                </div>
                                <div class="invalid-feedback">
                                    Пожалуйста, введите свое сообщение. Минимум 50 символов. Сейчас <span class="count">0</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="captcha">Проверка капчи</label>
                                <div class="row">
                                    <div class="col-auto mb-3">
                                        <input type="text" name="captcha" id="captcha"
                                               class="form-control @error('captcha') is-invalid @enderror" maxlength="6"
                                               required>
                                        @error('captcha')
                                        <div class="invalid-feedback">
                                            Пожалуйста, введите корректную капчу
                                        </div>
                                        @enderror
                                    </div>
                                    <div class="col-auto mb-3">
                                        <div class="input-group mb-3">
                                            {!! Captcha::img('flat', ['class' => 'captcha-image']) !!}
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary refresh-captcha" type="button">
                                                    <i class="fas fa-sync-alt"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                                <label class="form-check-label" for="agree">Я принимаю условия <a
                                        href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank">Пользовательского
                                        соглашения</a></label>
                                <div class="invalid-feedback">
                                    Пожалуйста, прочтите и отметьте свое согласие с условиями Пользовательского
                                    соглашения
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="personal_data_consent" name="personal_data_consent" required>
                                <label class="form-check-label" for="personal_data_consent">Я даю <a
                                        href="{{ route('personal_data_consent.info') }}" target="_blank">согласие на
                                        обработку персональных данных</a></label>
                                <div class="invalid-feedback">
                                    Пожалуйста, дайте согласие на обработку персональных данных
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="container">
                @php
                    // E1-A1-F3: локальная проверка согласия для карты. Не переиспользует
                    // $avilonaAnalyticsConsent из layouts/main.blade.php — секция content
                    // этого шаблона выполняется раньше, чем @php родительского layout,
                    // поэтому эта переменная там ещё не определена.
                    $avilonaMapConsent = \App\Support\CookieConsent::allowsAnalytics(
                        request()->cookie(\App\Support\CookieConsent::COOKIE_NAME)
                    );
                @endphp
                @if($avilonaMapConsent)
                <div class="embed-responsive embed-responsive-16by9">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2005.2877751084227!2d30.202907216066!3d59.82775128183606!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4696311aec4c423b%3A0x7f0d41bbdff2477a!2z0KLRg9GA0LjRgdGC0LjRh9C10YHQutC-0LUg0LDQs9C10L3RgtGB0YLQstC-INCQ0LLQuNC70L7QvdCw!5e0!3m2!1sru!2sru!4v1677875357483!5m2!1sru!2sru"
                        width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                @else
                <div id="google-map-consent-placeholder" class="border rounded p-4 text-center bg-light">
                    <p class="mb-2">Интерактивная карта Google не загружается автоматически без вашего согласия на использование необязательных cookie.</p>
                    <p class="mb-2"><strong>Адрес офиса:</strong><br>198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
                    <a href="https://www.google.com/maps/search/?api=1&query=198261%2C+%D0%A0%D0%BE%D1%81%D1%81%D0%B8%D1%8F%2C+%D0%A1%D0%B0%D0%BD%D0%BA%D1%82-%D0%9F%D0%B5%D1%82%D0%B5%D1%80%D0%B1%D1%83%D1%80%D0%B3%2C+%D1%83%D0%BB.+%D0%93%D0%B5%D0%BD%D0%B5%D1%80%D0%B0%D0%BB%D0%B0+%D0%A1%D0%B8%D0%BC%D0%BE%D0%BD%D1%8F%D0%BA%D0%B0%2C+%D0%B4.+10"
                       target="_blank" rel="noopener noreferrer">Открыть в Google Картах</a>
                </div>
                @endif
            </div>
        </div>
    </main>
@endsection

@section('scripts')
    <script>
        $(function () {
            // Refresh captcha image when refresh button is clicked
            $('.refresh-captcha').on('click', function () {
                $.ajax({
                    type: 'GET',
                    url: '{{ route('captcha_reload.index') }}',
                    success: function (data) {
                        $('.captcha-image').attr('src', data.captcha + '?' + Math.random());
                    }
                });
            });
        });
        $(document).ready(function () {
            // Добавьте этот код для проверки формы
            const form = document.querySelector('form#send_home');
            form.addEventListener('submit', function (event) {
                const inputs = form.querySelectorAll('input, textarea');
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.checkValidity()) {
                        isValid = false;
                        input.classList.add('is-invalid');
                        input.classList.remove('is-valid');
                    } else {
                        input.classList.add('is-valid');
                        input.classList.remove('is-invalid');
                    }
                });
                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                    $('#errorModal').modal('show'); // Показываем модальное окно с ошибкой
                }
            });
            // Добавьте этот код для отображения количества символов в поле сообщения
            const textarea = document.querySelector("textarea");
            const count = document.querySelector(".count");
            textarea.addEventListener('input', function () {
                const textLength = textarea.value.length;
                count.innerText = `${textLength}`;
            });
        });
        // Скрипт для раскрытия полного отзыва
        $(document).ready(function () {
            $('.read-more').on("click", function () {
                var $btn = $(this);
                var $content = $btn.parent().find('.content');
                var $fullContent = $btn.parent().find('.full-content');
                var $hideBtn = $btn.parent().find('.hide-review');
                var linkText = $btn.text().toUpperCase();
                if (linkText === "ЧИТАТЬ ПОЛНОСТЬЮ") {
                    $content.addClass('d-none');
                    $fullContent.removeClass('d-none');
                    $btn.text('Скрыть отзыв');
                    $hideBtn.removeClass('d-none');
                } else {
                    $content.removeClass('d-none');
                    $fullContent.addClass('d-none');
                    $btn.text('Читать полностью');
                    $hideBtn.addClass('d-none');
                }
                return false;
            });
            $('.hide-review').on("click", function () {
                var $btn = $(this);
                var $content = $btn.parent().find('.content');
                var $fullContent = $btn.parent().find('.full-content');
                var $readBtn = $btn.parent().find('.read-more');
                $content.removeClass('d-none');
                $fullContent.addClass('d-none');
                $readBtn.text('Читать полностью');
                $btn.addClass('d-none');
                return false;
            });
        });
        // Скрипт для открытия модального окна менеджеров
        var modalManagers = document.getElementById('contactManagersModal');
        var closeButtonManagers = document.querySelector('.close-button-managers');

        function openContactModal() {
            modalManagers.style.display = 'block';
        }

        function openWhatsAppManagers(number) {
            window.open(`https://wa.me/${number}`, '_blank');
            modalManagers.style.display = 'none';
        }

        function openTelegramManagers(number) {
            window.open(`https://t.me/${number}`, '_blank');
            modalManagers.style.display = 'none';
        }

        closeButtonManagers.onclick = function () {
            modalManagers.style.display = "none";
        }
        window.onclick = function (event) {
            if (event.target === modalManagers) {
                modalManagers.style.display = "none";
            }
        }
        closeButtonManagers.addEventListener('click', function () {
            modalManagers.style.display = "none";
        });
        window.addEventListener('click', function (event) {
            if (event.target === modalManagers) {
                modalManagers.style.display = "none";
            }
        });
    </script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    
    <script>
    $(document).ready(function() {
        // Инициализация daterangepicker
        $('input[name="date_range"]').daterangepicker({
            opens: 'left',
            autoUpdateInput: false,
            locale: {
                format: 'DD.MM.YY',
                separator: ' – ',
                applyLabel: 'Применить',
                cancelLabel: 'Отмена',
                fromLabel: 'От',
                toLabel: 'До',
                customRangeLabel: 'Выбрать период',
                weekLabel: 'Н',
                daysOfWeek: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                           'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                firstDay: 1
            },
            startDate: moment().add(1, 'day'),
            endDate: moment().add(7, 'day'),
            minDate: moment().add(1, 'day')
        });
        
        // Обработка выбора дат
        $('input[name="date_range"]').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('DD.MM.YY') + ' – ' + picker.endDate.format('DD.MM.YY'));
            $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
            $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
        });
        
        // Обработка отмены выбора
        $('input[name="date_range"]').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            $('#start_date').val('');
            $('#end_date').val('');
        });
        
        // Функции для управления количеством туристов
        let adultsCount = 2;
        let childrenList = [];
        let currentChildIndex = -1; // Для выбора возраста

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
            updatePopupSummary();
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

        window.selectAge = function(age) {
            if (currentChildIndex === -1) {
                // Добавляем нового ребенка
                if (childrenList.length < 10) {
                    childrenList.push(age);
                }
            } else {
                // Изменяем возраст существующего ребенка
                childrenList[currentChildIndex] = age;
            }
            
            hideAgeSelection();
            renderChildrenList();
            updateChildrenAgesHidden();
            updateTouristSummary();
            updatePopupSummary();
        };

        window.removeTouristChild = function(index, event) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
                event.stopImmediatePropagation();
            }
            childrenList.splice(index, 1);
            renderChildrenList();
            updateChildrenAgesHidden();
            updateTouristSummary();
            updatePopupSummary();
            // Убеждаемся, что dropdown остается открытым
            const dropdown = document.getElementById('touristDropdown');
            if (dropdown) {
                dropdown.style.display = 'block';
            }
        };

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
                        <button type="button" class="tourist-popup-child-remove" onclick="removeTouristChild(${index}, event)" title="Удалить">×</button>
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

        function updatePopupSummary() {
            const summary = document.getElementById('touristPopupSummary');
            let text = `${adultsCount} взр`;
            
            if (childrenList.length > 0) {
                text += ` и ${childrenList.length} реб`;
            }
            
            summary.textContent = text;
        }

        // Закрытие модального окна при клике вне его
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('touristDropdown');
            const input = document.getElementById('touristSummary');
            
            // Игнорируем клики на кнопки удаления детей
            if (event.target && event.target.classList.contains('tourist-popup-child-remove')) {
                return;
            }
            
            // Проверяем, что клик был вне выпадающего списка и поля ввода
            if (dropdown && dropdown.style.display === 'block' && 
                !dropdown.contains(event.target) && 
                !input.contains(event.target)) {
                closeTouristDropdown();
            }
        });

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            updateTouristSummary();
            updateChildrenAgesHidden();
            renderChildrenList();
            updatePopupSummary();
        });
        
        // Валидация формы поиска туров
        window.validateTourSearchForm = function() {
            const nightsMin = document.querySelector('select[name="nights_min"]').value;
            const nightsMax = document.querySelector('select[name="nights_max"]').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            // Проверка дат
            if (!startDate || !endDate) {
                alert('Пожалуйста, выберите даты вылета');
                return false;
            }
            
            // Проверка количества ночей
            if (!nightsMin && !nightsMax) {
                alert('Пожалуйста, выберите количество ночей (от и до)');
                return false;
            }
            
            if (nightsMin && nightsMax && parseInt(nightsMin) > parseInt(nightsMax)) {
                alert('Количество ночей "от" не может быть больше чем "до"');
                return false;
            }
            
            return true;
        };
        });
    </script>
@endsection
