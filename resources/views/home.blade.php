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
                    <!-- Минималистичный виджет поиска туров -->
                    <div class="tour-search-widget mb-4">
                        <div class="card border-0 shadow-sm" style="border-radius: 8px;">
                            <div class="card-body p-3" style="background: #f8f9fa;">
                                <form action="{{ route('tours.index') }}" method="GET">
                                    <!-- Основные поля в одну строку -->
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-1">Откуда</label>
                                            <select name="departure_city" class="form-select form-select-sm" required>
                                                <option value="">Выберите город</option>
                                                <option value="Санкт-Петербург" selected>Санкт-Петербург</option>
                                                <option value="Москва">Москва</option>
                                                <option value="Екатеринбург">Екатеринбург</option>
                                                <option value="Новосибирск">Новосибирск</option>
                                                <option value="Казань">Казань</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-1">Куда</label>
                                            <select name="destination_country" class="form-select form-select-sm" required>
                                                <option value="">Выберите страну</option>
                                                <option value="Турция">Турция</option>
                                                <option value="Египет">Египет</option>
                                                <option value="ОАЭ">ОАЭ</option>
                                                <option value="Тайланд">Тайланд</option>
                                                <option value="Испания">Испания</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-1">Даты вылета</label>
                                            <input type="text" name="date_range" class="form-control form-control-sm" placeholder="20 окт - 26 окт 25" readonly style="background: white; cursor: pointer;" onclick="openSimpleCalendar()">
                                            <input type="hidden" name="start_date" id="start_date">
                                            <input type="hidden" name="end_date" id="end_date">
                                        </div>
                                        
                                        <div class="col-md-1">
                                            <label class="form-label small text-muted mb-1">Ночей</label>
                                            <select name="nights" class="form-select form-select-sm" required>
                                                <option value="">Любое</option>
                                                @for($i = 1; $i <= 30; $i++)
                                                    <option value="{{ $i }}">{{ $i }} {{ $i == 1 ? 'ночь' : ($i < 5 ? 'ночи' : 'ночей') }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-1">
                                            <label class="form-label small text-muted mb-1">Взрослых</label>
                                            <select name="adults" class="form-select form-select-sm" required>
                                                <option value="1">1</option>
                                                <option value="2" selected>2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-1">
                                            <label class="form-label small text-muted mb-1">Детей</label>
                                            <select name="children" class="form-select form-select-sm" required>
                                                <option value="0" selected>0</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold">
                                                <i class="fas fa-search me-1"></i>Найти туры
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
    
    <script>
    function openSimpleCalendar() {
        // Создаем простой календарь прямо на странице
        const calendar = document.createElement('div');
        calendar.id = 'simpleCalendar';
        calendar.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            padding: 15px;
            min-width: 300px;
        `;
        
        calendar.innerHTML = `
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
            
            <div class="mt-3 text-center">
                <button type="button" class="btn btn-primary btn-sm" onclick="applyDateRange()" id="applyBtn" disabled>Применить</button>
                <button type="button" class="btn btn-secondary btn-sm ms-2" onclick="closeSimpleCalendar()">Отмена</button>
            </div>
        `;
        
        // Добавляем календарь к полю дат
        const dateField = document.querySelector('input[name="date_range"]');
        dateField.parentElement.style.position = 'relative';
        dateField.parentElement.appendChild(calendar);
        
        // Инициализируем календарь
        initSimpleCalendar();
    }

    let selectedStartDate = null;
    let selectedEndDate = null;
    let currentDate = new Date();

    function initSimpleCalendar() {
        generateSimpleCalendar();
    }

    function generateSimpleCalendar() {
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
            html += `<div class="weekday text-center text-muted small fw-bold">${day}</div>`;
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
        
        updateSimpleCalendar();
        updateApplyButton();
    }

    function updateSimpleCalendar() {
        generateSimpleCalendar();
    }

    function updateApplyButton() {
        const applyBtn = document.getElementById('applyBtn');
        applyBtn.disabled = !(selectedStartDate && selectedEndDate);
    }

    function changeMonth(direction) {
        currentDate.setMonth(currentDate.getMonth() + direction);
        generateSimpleCalendar();
    }

    function isSameDay(date1, date2) {
        return date1.getDate() === date2.getDate() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getFullYear() === date2.getFullYear();
    }

    function closeSimpleCalendar() {
        const calendar = document.getElementById('simpleCalendar');
        if (calendar) {
            calendar.remove();
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
        
        closeSimpleCalendar();
    }

    // Закрываем календарь при клике вне его
    document.addEventListener('click', function(event) {
        const calendar = document.getElementById('simpleCalendar');
        const dateField = document.querySelector('input[name="date_range"]');
        
        if (calendar && !calendar.contains(event.target) && !dateField.contains(event.target)) {
            closeSimpleCalendar();
        }
    });
    </script>
@endsection
