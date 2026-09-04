@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Контакты - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на страницу контактов туристической фирмы Авилона. Свяжитесь с туристической фирмой Авилона. Контактная информация, реквизиты и форма обратной связи для ваших вопросов и предложений.')
@section('meta_keywords', 'контакты, туристическая фирма, контактная информация, реквизиты, форма обратной связи, туристическое агентство Авилона')
@section('og_title', 'Контакты — Туристическая фирма Авилона')
@section('og_description', 'Контактная информация, реквизиты и форма обратной связи туристического агентства «Авилона».')

{{-- E2-A6-I1: миграция страницы «Контакты» на систему E2. Легаси-разметка из
     двух bootstrap-колонок с ~4 конкурирующими <h1>, инлайновые модалки
     Bootstrap-4 (.modal('show') не существует в Bootstrap 5 и не работала) и
     инлайновые стили заменены на E2-примитивы: хлебные крошки, компактный
     hero с единственным <h1>, адаптивные секции/карточки, .e2-form, .e2-alert,
     .e2-map. Бизнес-факты (фактический/юридический адреса, реквизиты ЕГРЮЛ,
     телефоны, e-mail, режим работы, документы-оферты) сохранены дословно.

     PENDING_BUSINESS_DECISION_OPENING_HOURS: режим работы на этой странице
     (пн–пт 11:00–20:00, по предварительной записи) расходится с формулировкой
     на главной/в шапке (пн–пт 10:00–20:00). Единый источник истины
     отсутствует; E2-A6-I1 намеренно НЕ выбирает вариант и НЕ синхронизирует
     страницы — здесь сохранена ровно текущая формулировка «Контактов».
     Финальное решение — до E6. --}}
@section('content')
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Контакты', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Контакты</h1>
                <p class="e2-page-hero__intro">Менеджеры туристического агентства «Авилона» помогут найти ответы на
                    ваши вопросы, проконсультируют и запишут на посещение офиса. Напишите нам через форму ниже или
                    свяжитесь напрямую — мы всегда рады общению.</p>
            </section>

            <section class="e2-section" aria-labelledby="e2-contact-title">
                <div class="e2-contact-cols">
                    <div class="e2-contact-block">
                        <h2 id="e2-contact-title" class="e2-section__title mb-3">Туристическое агентство «Авилона»</h2>
                        <dl class="e2-contact-list">
                            <div>
                                <dt><i class="bi bi-geo-alt" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Фактический адрес / офис:</span>
                                    198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10
                                </dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-building" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Юридический адрес:</span>
                                    191119, Россия, Санкт-Петербург, ул. Звенигородская, д. 22, литера А, офис 053, пом. 7Н
                                </dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-telephone" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Телефоны:</span>
                                    <span class="e2-phone-values">
                                        <a class="e2-phone-values__item" href="tel:+79219314345">+7 (921) 931-43-45</a>
                                        <a class="e2-phone-values__item" href="tel:+79219842022">+7 (921) 984-20-22</a>
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-envelope" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Эл. почта:</span>
                                    <a href="mailto:avilonatur@bk.ru">avilonatur@bk.ru</a>
                                </dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-clock" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Режим работы (по предварительной записи):</span>
                                    Понедельник - пятница, с 11:00 до 20:00,<br>Суббота - воскресенье, с 12:00 до 19:00
                                </dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-vector-pen" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Передача документов:</span>
                                    Передача документов по договорённости
                                </dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-share" aria-hidden="true"></i></dt>
                                <dd>
                                    <span class="e2-contact-list__label">Мы в соцсетях:</span>
                                    <a href="https://vk.com/avilona" target="_blank" rel="noopener noreferrer">ВКонтакте</a>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="e2-contact-block">
                        <h2 class="e2-section__title mb-3">Есть вопросы — спрашивайте!</h2>
                        <p>Заполните форму, и менеджеры «Авилоны» ответят вам по электронной почте. Поля со звёздочкой
                            обязательны.</p>

                        @if (session('success'))
                            <div class="e2-alert e2-alert--success" role="status">
                                Ваше сообщение успешно отправлено!
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="e2-alert e2-alert--error" role="alert">
                                Извините, возникла ошибка при отправке сообщения. Попробуйте ещё раз позже.
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="e2-alert e2-alert--error" role="alert">
                                Сообщение не отправлено. Проверьте выделенные поля и попробуйте ещё раз.
                            </div>
                        @endif

                        <form action="{{ route('contact.send_contact') }}" id="send_contact" method="post"
                              class="needs-validation e2-form" novalidate>
                            @csrf
                            <div class="row g-3">
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="name" class="form-label">Ваше имя
                                        <span class="e2-form__req" aria-hidden="true">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           placeholder="Например, Никита" value="{{ old('name') }}" required>
                                    <div class="valid-feedback">Поле заполнено верно!</div>
                                    <div class="invalid-feedback">Пожалуйста, введите свое имя</div>
                                </div>
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="email" class="form-label">Email
                                        <span class="e2-form__req" aria-hidden="true">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           placeholder="Например, test@mail.ru" value="{{ old('email') }}" required>
                                    <div class="valid-feedback">Поле заполнено верно!</div>
                                    <div class="invalid-feedback">Пожалуйста, введите корректный email</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Тема</label>
                                <input type="text" class="form-control" id="subject" name="subject"
                                       placeholder="Тема вашего обращения..." value="{{ old('subject') }}"
                                       maxlength="150">
                                <div class="form-text">Можно оставить пустым, если не знаете, какую тему указать.</div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Ваше сообщение
                                    <span class="e2-form__req" aria-hidden="true">*</span></label>
                                <textarea class="form-control" id="message" rows="5" name="message"
                                          placeholder="Введите свое сообщение..." minlength="50"
                                          required>{{ old('message') }}</textarea>
                                <div class="valid-feedback">Поле заполнено верно!</div>
                                <div class="invalid-feedback">
                                    Пожалуйста, введите свое сообщение. Минимум 50 символов. Сейчас <span class="count">0</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="captcha" class="form-label">Проверка капчи
                                    <span class="e2-form__req" aria-hidden="true">*</span></label>
                                <div class="e2-form__captcha">
                                    <div>
                                        <input type="text" name="captcha" id="captcha"
                                               class="form-control @error('captcha') is-invalid @enderror" maxlength="6"
                                               autocomplete="off" required>
                                        @error('captcha')
                                        <div class="invalid-feedback d-block">Пожалуйста, введите корректную капчу</div>
                                        @enderror
                                        <div class="form-text">Введите символы с картинки.</div>
                                    </div>
                                    <div class="input-group w-auto">
                                        {!! Captcha::img('flat', ['class' => 'captcha-image']) !!}
                                        <button class="btn btn-outline-secondary refresh-captcha" type="button"
                                                aria-label="Обновить изображение капчи">
                                            <i class="fas fa-sync-alt" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2 form-check">
                                <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                                <label class="form-check-label" for="agree">Я принимаю условия
                                    <a href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank"
                                       rel="noopener noreferrer">Пользовательского соглашения</a></label>
                                <div class="invalid-feedback">
                                    Пожалуйста, прочтите и отметьте свое согласие с условиями Пользовательского соглашения
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="personal_data_consent"
                                       name="personal_data_consent" required>
                                <label class="form-check-label" for="personal_data_consent">Я даю
                                    <a href="{{ route('personal_data_consent.info') }}" target="_blank"
                                       rel="noopener noreferrer">согласие на обработку персональных данных</a></label>
                                <div class="invalid-feedback">
                                    Пожалуйста, дайте согласие на обработку персональных данных
                                </div>
                            </div>
                            <p class="form-text mb-3">Данные из формы используются только для ответа на ваше обращение.</p>
                            <div class="e2-form__actions">
                                <button type="submit" class="e2-btn e2-btn--primary">Отправить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="e2-section" aria-labelledby="e2-requisites-title">
                {{-- E2-A6-I1 (browser-QA polish): на десктопе/планшете от 992px карточка
                     реквизитов раскладывается в две колонки (реквизиты слева, документы
                     справа), чтобы не оставлять пустую правую половину и не растягивать
                     секцию по высоте. Ниже 992px — обычный вертикальный поток. Только
                     раскладка; тексты реквизитов и документов не меняются. --}}
                <div class="e2-contact-block e2-requisites">
                    <h2 id="e2-requisites-title" class="e2-section__title mb-3">Реквизиты компании</h2>
                    <dl class="e2-contact-list">
                        <div>
                            <dt><i class="bi bi-hash" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">ИНН / КПП:</span>7805502454 / 784001001</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-hash" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">ОКТМО:</span>40337000</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-hash" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">ОКПО:</span>62981592</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-hash" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">ОГРН:</span>1097847289803</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-bank" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">Банк:</span>Северо-Западный банк ПАО Сбербанк</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-bank" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">Р/сч.:</span>40702810155000046636</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-bank" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">К/сч.:</span>30101810500000000653</dd>
                        </div>
                        <div>
                            <dt><i class="bi bi-bank" aria-hidden="true"></i></dt>
                            <dd><span class="e2-contact-list__label">БИК:</span>044030653</dd>
                        </div>
                    </dl>

                    <ul class="e2-doc-list">
                        <li>
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                            <a href="{{ asset('/documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf') }}"
                               target="_blank" rel="noopener noreferrer">
                                Публичная оферта на заключение договора о реализации туристского продукта</a>
                            <span class="e2-doc-list__date">Дата обновления: 22 мая 2024 г.</span>
                        </li>
                        <li>
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                            <a href="{{ asset('/documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf') }}"
                               target="_blank" rel="noopener noreferrer">
                                Публичная оферта на реализацию билетов и бронирование отелей</a>
                            <span class="e2-doc-list__date">Дата обновления: 22 мая 2024 г.</span>
                        </li>
                        <li>
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                            <a href="{{ asset('/documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf') }}"
                               target="_blank" rel="noopener noreferrer">
                                Политика в отношении защиты и обработки персональных данных</a>
                            <span class="e2-doc-list__date">Дата обновления: 22 мая 2024 г.</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="e2-section" aria-labelledby="e2-map-title">
                <div class="e2-section__head">
                    <h2 id="e2-map-title" class="e2-section__title">Как нас найти</h2>
                </div>
                {{-- E2-A6-I1: без встраивания сторонних картографических API/провайдеров.
                     Статическая направляющая секция: фактический адрес офиса + внешняя
                     ссылка на карту (обычный переход, не интеграция). --}}
                <div class="e2-map">
                    <p class="e2-map__text">
                        <strong>Адрес офиса:</strong><br>
                        198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10
                    </p>
                    <p class="e2-map__text">Офис работает по предварительной записи — пожалуйста, согласуйте визит по
                        телефону заранее.</p>
                    <div class="e2-map__actions">
                        <a class="e2-btn e2-btn--secondary"
                           href="https://www.google.com/maps/search/?api=1&query=198261%2C+%D0%A0%D0%BE%D1%81%D1%81%D0%B8%D1%8F%2C+%D0%A1%D0%B0%D0%BD%D0%BA%D1%82-%D0%9F%D0%B5%D1%82%D0%B5%D1%80%D0%B1%D1%83%D1%80%D0%B3%2C+%D1%83%D0%BB.+%D0%93%D0%B5%D0%BD%D0%B5%D1%80%D0%B0%D0%BB%D0%B0+%D0%A1%D0%B8%D0%BC%D0%BE%D0%BD%D1%8F%D0%BA%D0%B0%2C+%D0%B4.+10"
                           target="_blank" rel="noopener noreferrer">Открыть в Google Картах</a>
                    </div>
                </div>
            </section>
        </div>
    </main>
@endsection

@section('scripts')
    <script>
        $(function () {
            // Обновление изображения капчи по кнопке (единственный jQuery-AJAX на странице).
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
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('send_contact');
            if (!form) {
                return;
            }
            form.addEventListener('submit', function (event) {
                var controls = form.querySelectorAll('input, textarea');
                var valid = true;
                controls.forEach(function (control) {
                    // Необязательное поле «Тема»: пустое значение валидно, зелёный
                    // success-стейт не показываем — только нейтральный хелпер.
                    if (control.id === 'subject') {
                        control.classList.remove('is-valid', 'is-invalid');
                        return;
                    }
                    if (!control.checkValidity()) {
                        valid = false;
                        control.classList.add('is-invalid');
                        control.classList.remove('is-valid');
                    } else {
                        control.classList.add('is-valid');
                        control.classList.remove('is-invalid');
                    }
                });
                if (!valid) {
                    event.preventDefault();
                    event.stopPropagation();
                    var firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid && typeof firstInvalid.focus === 'function') {
                        firstInvalid.focus();
                    }
                }
            });

            var textarea = form.querySelector('#message');
            var count = form.querySelector('.count');
            if (textarea && count) {
                textarea.addEventListener('input', function () {
                    count.innerText = String(textarea.value.length);
                });
            }
        });
    </script>
@endsection
