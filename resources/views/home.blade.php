@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

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
                    <h1 class="text-center mb-3 p-3 border-top border-2 border-bottom">Поиск туров - санатории</h1>
                    <script async="true" src="//www.delfin-tour.ru/export/frame" data-delfin='10644'></script>
                    <h1 class="text-center mb-3 p-3 border-top border-2 border-bottom">Поиск туров - круизы</h1>
                    <div class="infoflotWidget"
                         data-id="YTo0OntzOjI6IklEIjtzOjQ6IjI3NDkiO3M6NDoiVVNFUiI7czoyNDoiWVhacGJHOXVZWFIxY2tCdFlXbHNMbkoxIjtzOjY6IlJBTkRPTSI7czo4OiJpZGVuN29tZSI7czoxNToiSU5GT0ZMT1QtQVBJS0VZIjtzOjQwOiJiYTA2Mjc5Yjc5N2IwOTcwMTZjMTUxZTA5YjY4NjdiODMxMzBiMTFiIjt9"
                         data-index="1"></div>
                    <script async>(function (d, w) {
                            var h = d.getElementsByTagName("script")[0];
                            s = d.createElement("script");
                            s.src = "https://bitrix.infoflot.com/local/templates/infoflot/frontend/js/infoflotIframe.js";
                            s.async = !0;
                            s.onload = function () {
                                w.createInfoflotWidget("https://bitrix.infoflot.com/rest/api/search.filter/", {
                                    key: "YTo0OntzOjI6IklEIjtzOjQ6IjI3NDkiO3M6NDoiVVNFUiI7czoyNDoiWVhacGJHOXVZWFIxY2tCdFlXbHNMbkoxIjtzOjY6IlJBTkRPTSI7czo4OiJpZGVuN29tZSI7czoxNToiSU5GT0ZMT1QtQVBJS0VZIjtzOjQwOiJiYTA2Mjc5Yjc5N2IwOTcwMTZjMTUxZTA5YjY4NjdiODMxMzBiMTFiIjt9",
                                    referer: encodeURIComponent(location.href)
                                })
                            };
                            h.parentNode.insertBefore(s, h);
                        })(document, window);</script>
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
@endsection
