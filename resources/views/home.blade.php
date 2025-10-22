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
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
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
            <div class="row my-3">
                <div class="col">
                    <!-- Современный виджет поиска туров -->
                    <div class="tour-search-widget mb-4">
                        <div class="card border-0 shadow-lg" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body p-4">
                                <form action="{{ route('tours.index') }}" method="GET" id="tourSearchForm">
                                    <!-- Основные поля в одну строку -->
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-2">
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
                                        
                                        <div class="col-md-2">
                                            <label class="form-label text-white fw-bold mb-2">Куда</label>
                                            <div class="position-relative">
                                                <select name="destination_country" class="form-select form-select-lg modern-select" required>
                                                    <option value="">Выберите страну</option>
                                                    <option value="Турция">🇹🇷 Турция</option>
                                                    <option value="Египет">🇪🇬 Египет</option>
                                                    <option value="ОАЭ">🇦🇪 ОАЭ</option>
                                                    <option value="Тайланд">🇹🇭 Таиланд</option>
                                                    <option value="Испания">🇪🇸 Испания</option>
                                                    <option value="Россия">🇷🇺 Россия</option>
                                                    <option value="Абхазия">🇦🇧 Абхазия</option>
                                                    <option value="Китай">🇨🇳 Китай</option>
                                                    <option value="Вьетнам">🇻🇳 Вьетнам</option>
                                                    <option value="Куба">🇨🇺 Куба</option>
                                                    <option value="Мальдивы">🇲🇻 Мальдивы</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label text-white fw-bold mb-2">Интервал дат вылета</label>
                                            <div class="position-relative">
                                                <input type="text" name="date_range" class="form-control form-control-lg modern-input" placeholder="22 окт – 26 окт 25" readonly onclick="openDateRangePicker()">
                                                <input type="hidden" name="start_date" id="start_date">
                                                <input type="hidden" name="end_date" id="end_date">
                                                <i class="fas fa-calendar-alt position-absolute top-50 end-0 translate-middle-y me-3 text-muted"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
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
                                        
                                        <div class="col-md-2">
                                            <label class="form-label text-white fw-bold mb-2">Количество туристов</label>
                                            <div class="position-relative">
                                                <select name="tourists" class="form-select form-select-lg modern-select" required>
                                                    <option value="1">1 взрослый</option>
                                                    <option value="2" selected>2 взрослых</option>
                                                    <option value="3">3 взрослых</option>
                                                    <option value="4">4 взрослых</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold modern-btn">
                                                <i class="fas fa-search me-2"></i>Найти туры
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Best offers -->
            <div class="row" id="block_best_offers">
                <div class="col-12">
                    <h1 class="text-center mb-3 p-3 border-top border-2 border-bottom">Лучшие предложения</h1>
                    <div class="row">
                        @foreach($best_offers as $item_best_offers)
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
                                <div class="card">
                                    <img src="{{ asset($item_best_offers->image) }}" class="card-img-top"
                                         alt="{{ $item_best_offers->title }}">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $item_best_offers->title }}</h5>
                                        <p class="card-text">{!! Str::limit($item_best_offers->content, 100) !!}</p>
                                    </div>
                                    <div class="card-footer">
                                        <small
                                            class="text-muted">{{ Date::parse($item_best_offers->created_at)->format('j F Y г.') }}</small>
                                    </div>
                                    <div class="card-footer">
                                        <small class=""><a href="#" onclick="openContactModal()"
                                                           class="btn btn-primary">Связаться с менеджером</a></small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- Reviews of our customers -->
            <div class="row" id="block_reviews">
                <div class="col-md-12">
                    <h1 class="text-center mb-3 p-3 border-top border-2 border-bottom">Отзывы</h1>
                </div>
            </div>
            <div class="row">
                @foreach($reviews as $item_reviews)
                    <div class="col-12 col-sm-6 col-md-6 col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="{{ asset($item_reviews->image) }}" class="mr-2" alt="User Avatar"
                                         style="width: 80px; height: 80px;">
                                    <h5 class="card-title mb-0">{!! $item_reviews->name !!}</h5>
                                </div>
                                <p class="content card-text">{!! Str::limit($item_reviews->content, 200) !!}</p>
                                @if(strlen($item_reviews->content) > 200)
                                    <p class="full-content d-none">{!! $item_reviews->content !!}</p>
                                    <button class="btn btn-primary read-more">Читать полностью</button>
                                @endif
                            </div>
                            <div class="card-footer">
                                <small
                                    class="text-muted">{{ Date::parse($item_reviews->created_at)->format('j F Y г.') }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <h1 class="text-center mb-3 p-3 border-top border-2 border-bottom">Новости</h1>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 mt-3">
                @foreach($news as $item_news)
                    <div class="col-12 col-md-4 col-lg-3">
                        <div class="card h-100">
                            <img src="{{ $item_news->image }}" class="card-img-top" alt="{{ $item_news->title }}"
                                 style="height: 200px;">
                            <div class="card-body">
                                <h5 class="card-title">{{ $item_news->title }}</h5>
                                <p class="card-text">{{ Str::limit($item_news->content, 150) }}</p>
                                <a href="{{ route('helpful_news_id.index', $item_news->slug) }}"
                                   class="btn btn-primary">Подробнее</a>
                            </div>
                            <div class="card-footer text-muted">{{ $item_news->created_at->format('F j, Y') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="container my-3">
                <h1 class="text-center mb-3 p-3 border-top border-2 border-bottom">Наши партнеры</h1>
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-4">
                    @foreach ($partners as $item_partner)
                        <div class="col">
                            <div class="h-100">
                                <img src="{{ $item_partner->logo_partner }}" class="card-img-top p-3"
                                     alt="{{ $item_partner->name_partner }} logo">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="container py-5">
                <div class="row">
                    <div class="col-md-6">
                        <h1 class="mb-4">Туристическое агентство «Авилона»</h1>
                        <p><strong>Адрес:</strong><br>198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
                        <p><strong>Телефоны:</strong><br>+7 (921) 931-43-45, +7 (921) 984-20-22</p>
                        <p><strong>Эл. почта:</strong><br>avilonatur@bk.ru</p>
                        <p><strong>Режим работы:</strong><br>Понедельник - пятница, с 10:00 до 20:00,<br>Суббота -
                            воскресенье, с 12:00 до 19:00</p>
                    </div>
                    <div class="col-md-6">
                        <h1 class="mb-4">Есть вопросы — спрашивайте!</h1>
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
                                <label class="form-check-label" for="agree">Нажимая кнопку, я принимаю условия <a
                                        href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank">Пользовательского
                                        соглашения</a> и даю своё согласие на
                                    обработку моих персональных данных, в соответствии с Федеральным законом от
                                    27.07.2006 года №152-ФЗ «О персональных данных»</label>
                                <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                                <div class="invalid-feedback">
                                    Пожалуйста, прочтите и отметьте свое согласие с условиями Пользовательского
                                    соглашения
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="embed-responsive embed-responsive-16by9">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2005.2877751084227!2d30.202907216066!3d59.82775128183606!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4696311aec4c423b%3A0x7f0d41bbdff2477a!2z0KLRg9GA0LjRgdGC0LjRh9C10YHQutC-0LUg0LDQs9C10L3RgtGB0YLQstC-INCQ0LLQuNC70L7QvdCw!5e0!3m2!1sru!2sru!4v1677875357483!5m2!1sru!2sru"
                        width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
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
    <script>
    $(document).ready(function() {
        let selectedStartDate = null;
        let selectedEndDate = null;
        let currentDate = new Date();
        
        // Открытие календаря диапазона дат
        window.openDateRangePicker = function() {
            const dateField = $('input[name="date_range"]');
            const container = dateField.closest('.position-relative');
            
            // Удаляем существующий календарь если есть
            container.find('.date-range-picker').remove();
            
            // Создаем календарь
            const calendar = $(`
                <div class="date-range-picker">
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button type="button" class="calendar-nav-btn" onclick="changeMonth(-1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="calendar-title" id="currentMonth">${getMonthName(currentDate.getMonth())} ${currentDate.getFullYear()}</div>
                            <button type="button" class="calendar-nav-btn" onclick="changeMonth(1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                            ${generateCalendarHTML()}
                        </div>
                        <div class="calendar-footer">
                            <div class="selected-range-display" id="selectedRangeDisplay">
                                ${selectedStartDate && selectedEndDate ? 
                                    `${formatDate(selectedStartDate)} – ${formatDate(selectedEndDate)}` : 
                                    'Выберите диапазон дат'
                                }
                            </div>
                            <button type="button" class="apply-btn" id="applyDateBtn" ${!selectedStartDate || !selectedEndDate ? 'disabled' : ''}>
                                Применить
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            container.append(calendar);
            
            // Закрытие календаря при клике вне его
            $(document).on('click.datePicker', function(e) {
                if (!$(e.target).closest('.date-range-picker, input[name="date_range"]').length) {
                    closeDateRangePicker();
                }
            });
        };
        
        // Генерация HTML календаря
        function generateCalendarHTML() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startDay = firstDay.getDay() === 0 ? 7 : firstDay.getDay(); // Понедельник = 1
            
            let html = '';
            
            // Дни недели
            const weekdays = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'ВС'];
            weekdays.forEach(day => {
                html += `<div class="calendar-weekday">${day}</div>`;
            });
            
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
                const inRange = selectedStartDate && selectedEndDate && 
                    date > selectedStartDate && date < selectedEndDate;
                
                let classes = 'calendar-day';
                if (isPast) classes += ' past';
                if (isToday) classes += ' today';
                if (isSelected) classes += ' selected';
                if (isStart) classes += ' range-start';
                if (isEnd) classes += ' range-end';
                if (inRange) classes += ' in-range';
                
                html += `<div class="calendar-day ${classes}" data-day="${day}" onclick="selectDate(${day})">
                    <span>${day}</span>
                </div>`;
            }
            
            return html;
        }
        
        // Выбор даты
        window.selectDate = function(day) {
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
            updateSelectedRangeDisplay();
        };
        
        // Обновление календаря
        function updateCalendar() {
            $('#calendarGrid').html(generateCalendarHTML());
        }
        
        // Обновление отображения выбранного диапазона
        function updateSelectedRangeDisplay() {
            const display = $('#selectedRangeDisplay');
            const applyBtn = $('#applyDateBtn');
            
            if (selectedStartDate && selectedEndDate) {
                display.text(`${formatDate(selectedStartDate)} – ${formatDate(selectedEndDate)}`);
                applyBtn.prop('disabled', false);
            } else if (selectedStartDate) {
                display.text(`${formatDate(selectedStartDate)} – выберите дату окончания`);
                applyBtn.prop('disabled', true);
            } else {
                display.text('Выберите диапазон дат');
                applyBtn.prop('disabled', true);
            }
        }
        
        // Смена месяца
        window.changeMonth = function(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            $('#currentMonth').text(`${getMonthName(currentDate.getMonth())} ${currentDate.getFullYear()}`);
            updateCalendar();
        };
        
        // Применение выбранных дат
        $('#applyDateBtn').on('click', function() {
            if (selectedStartDate && selectedEndDate) {
                $('#start_date').val(selectedStartDate.toISOString().split('T')[0]);
                $('#end_date').val(selectedEndDate.toISOString().split('T')[0]);
                
                const startFormatted = formatDate(selectedStartDate);
                const endFormatted = formatDate(selectedEndDate);
                
                $('input[name="date_range"]').val(`${startFormatted} – ${endFormatted}`);
            }
            
            closeDateRangePicker();
        });
        
        // Закрытие календаря
        window.closeDateRangePicker = function() {
            $('.date-range-picker').remove();
            $(document).off('click.datePicker');
        };
        
        // Вспомогательные функции
        function isSameDay(date1, date2) {
            return date1.getDate() === date2.getDate() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getFullYear() === date2.getFullYear();
        }
        
        function getMonthName(month) {
            const months = [
                'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
            ];
            return months[month];
        }
        
        function formatDate(date) {
            return date.toLocaleDateString('ru-RU', { 
                day: 'numeric', 
                month: 'short',
                year: '2-digit'
            });
            }
        });
    </script>
@endsection
