@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Контакты - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на страницу контактов туристической фирмы Авилона. Свяжитесь с туристической фирмой Авилона. Контактная информация, реквизиты и форма обратной связи для ваших вопросов и предложений.')
@section('meta_keywords', 'контакты, туристическая фирма, контактная информация, реквизиты, форма обратной связи, туристическое агентство Авилона')

<!-- Main Content -->
@section('content')
    {{--говорим какой блок будет отображаться как контент, каждый на своей странице будет разный--}}
    <main class="container py-5">
        <div class="row">
            <div class="col-md-6">
                <h1 class="mb-3">Есть вопросы — спрашивайте!</h1>
                <p>Менеджеры туристического агентства «Авилона» с радостью помогут Вам найти ответы на интересующие Вас
                    вопросы, окажут консультацию или запишут Вас на посещение нашего туристического офиса.<br>
                    Мы всегда рады Вам!</p>
                {{-- проверка правильного отправления формы и открытие всплывающего окна--}}
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
                <form action="{{ route('contact.send_contact') }}" id="send_contact" method="post"
                      class="needs-validation"
                      novalidate>
                    @csrf
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="name" class="form-label">Ваше имя</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Например, Никита"
                                   value="{{ old('name') }}" required>
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
                                  placeholder="Введите свое сообщение..."
                                  minlength="50" required>{{ old('message') }}</textarea>
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
                                        <button class="btn btn-outline-secondary refresh-captcha" type="button"><i
                                                class="fas fa-sync-alt"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <label class="form-check-label" for="agree">Нажимая кнопку, я принимаю условия <a
                                href="{{ asset('/documents/User_Agreement.pdf') }}"
                                target="_blank">Пользовательского
                                соглашения</a> и даю своё согласие на обработку моих персональных данных, в соответствии
                            с Федеральным законом от 27.07.2006 года №152-ФЗ «О персональных данных»</label>
                        <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                        <div class="invalid-feedback">
                            Пожалуйста, прочтите и отметьте свое согласие с условиями Пользовательского соглашения
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Отправить</button>
                </form>
            </div>
            <div class="col-md-6">
                <h1 class="mb-3">Туристическое агентство «Авилона»</h1>
                <p><strong>Адрес:</strong><br>198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
                <p><strong>Телефоны:</strong><br>+7 (921) 931-43-45, +7 (921) 984-20-22</p>
                <p><strong>Эл. почта:</strong><br>avilonatur@bk.ru</p>
                <p><strong>Режим работы (по предварительной записи):</strong><br>Понедельник - пятница, с 11:00 до
                    20:00,<br>Суббота - воскресенье,
                    с 12:00 до 19:00</p>
                <h1 class="mb-3">Реквизиты компании</h1>
                <p><strong>ИНН / КПП:</strong> 7805502454 / 780501001</p>
                <p><strong>ОКТМО:</strong> 40337000</p>
                <p><strong>ОКПО:</strong> 62981592</p>
                <p><strong>ОГРН:</strong> 1097847289803</p>
                <p><strong>Банк:</strong> Северо-Западный банк ПАО Сбербанк</p>
                <p><strong>Р/сч.:</strong> 40702810155000046636</p>
                <p><strong>К/сч.:</strong> 30101810500000000653</p>
                <p><strong>БИК:</strong> 044030653</p>
                <div class="document-container">
                    <img src="{{ asset('/img/pdf.png') }}" alt="PDF" style="width: 40px; vertical-align: middle;">
                    <a href="{{ asset('/documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf') }}"
                       target="_blank">
                        Публичная оферта на заключение договора о реализации туристского продукта
                    </a>
                    <span class="document-date">Дата обновления: 22 мая 2024 г.</span>
                </div>
                <div class="document-container">
                    <img src="{{ asset('/img/pdf.png') }}" alt="PDF" style="width: 40px; vertical-align: middle;">
                    <a href="{{ asset('/documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf') }}"
                       target="_blank">
                        Публичная оферта на реализацию билетов и бронирование отелей
                    </a>
                    <span class="document-date">Дата обновления: 22 мая 2024 г.</span>
                </div>
                <div class="document-container">
                    <img src="{{ asset('/img/pdf.png') }}" alt="PDF" style="width: 40px; vertical-align: middle;">
                    <a href="{{ asset('/documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf') }}"
                       target="_blank">
                        Политика в отношении защиты и обработки персональных данных
                    </a>
                    <span class="document-date">Дата обновления: 22 мая 2024 г.</span>
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
            const form = document.querySelector('form#send_contact');
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
    </script>
@endsection
