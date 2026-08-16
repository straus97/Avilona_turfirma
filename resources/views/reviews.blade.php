@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title')
    @if (request()->has('page') && request()->get('page') >= 1)
        Отзывы - Страница {{ request()->get('page') }} | avilona.ru
    @else
        Отзывы - Туристическая фирма Авилона | avilona.ru
    @endif
@endsection
@section('meta_description', 'Добро пожаловать на страницу отзывов наших туристов о туристической фирмы Авилона. Прочитайте отзывы клиентов туристической фирмы Авилона и оставьте свой отзыв. Мы ценим ваше мнение и всегда стремимся к улучшению качества наших услуг.')
@section('meta_keywords', 'отзывы клиентов, туристическая фирма, оставить отзыв, качество услуг, туристическое агентство Авилона, отзывы')

<!-- Main Content -->
@section('content')
    <main>
        <div class="container mt-5">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-12 col-md-10">
                    @if(isset($reviews) && $reviews->count() > 0)
                        @foreach($reviews as $item_review)
                            <div class="row mb-4">
                                <div class="col-3 col-md-2 col-lg-1">
                                    @if($item_review->image)
                                    <img src="{{ asset($item_review->image) }}" class="img-fluid rounded-circle"
                                         alt="User Icon">
                                    @endif
                                </div>
                                <div class="col-9 col-md-10 col-lg-11">
                                    <h5>{{ $item_review->name ?? 'Анонимный пользователь' }}</h5>
                                    <p class="text-muted">{{ $item_review->created_at ? \Carbon\Carbon::parse($item_review->created_at)->translatedFormat('j F Y г.') : '' }}</p>
                                    <p class="content">{{ Str::limit($item_review->content ?? '', 200) }}</p>
                                    @if($item_review->content && strlen($item_review->content) > 200)
                                        <p class="full-content d-none">{{ $item_review->content }}</p>
                                        <button class="btn btn-primary read-more">Читать полностью</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <nav class="d-flex justify-content-center mt-3">
                            <ul class="pagination">
                                {{ $reviews->links() }}
                            </ul>
                        </nav>
                    @else
                        <div class="alert alert-info">
                            <p class="mb-0">Отзывы пока не добавлены в базу данных.</p>
                        </div>
                    @endif
                    <div class="row mb-4">
                        <h3>Напишите нам!</h3>
                        <p>Уважаемые туристы! Если Вы уже отдохнули с турфирмой Авилона, расскажите о Ваших
                            впечатлениях.
                            Все Ваши отзывы с пожеланиями, замечаниями и предложениями Вы можете направить нам, заполнив
                            форму ниже.
                            Мы будем благодарны Вам за письма, нам важно знать Ваше мнение! <br><small>*Данная форма не
                                предназначена для отправки заявок на тур</small>
                        </p>
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
                                        <h5 class="modal-title" id="successModalLabel">Отзыв отправлен</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Ваш отзыв успешно отправлен на модерацию!
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
                                        <h5 class="modal-title" id="errorModalLabel">Ошибка отправки отзыва</h5>
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
                        <div class="alert alert-info">
                            Отзыв сначала поступит на модерацию и не публикуется автоматически. После одобрения он
                            может быть опубликован на avilona.ru с указанным вами именем.
                        </div>
                        <form action="{{ route('review_create.index') }}" id="review_create" method="post"
                              class="needs-validation"
                              novalidate>
                            @csrf
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="name" class="form-label">Ваше имя</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           placeholder="Например, Никита"
                                           value="{{ old('name') }}" required>
                                    <div class="valid-feedback">
                                        Поле заполнено верно!
                                    </div>
                                    <div class="invalid-feedback">
                                        Пожалуйста, введите свое имя
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-secondary">
                                Данные ниже (ФИО и email для оформления согласия) используются только для оформления
                                и, при необходимости, отзыва согласия. Они не публикуются вместе с отзывом.
                            </div>
                            <div class="row">
                                <div class="col-12 col-md-6 mb-3">
                                    <label for="consent_full_name" class="form-label">ФИО для оформления согласия</label>
                                    <input type="text" class="form-control" id="consent_full_name" name="consent_full_name"
                                           placeholder="Иванов Иван Иванович"
                                           value="{{ old('consent_full_name') }}" required>
                                    <div class="valid-feedback">
                                        Поле заполнено верно!
                                    </div>
                                    <div class="invalid-feedback">
                                        Пожалуйста, укажите ФИО для оформления согласия
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 mb-3">
                                    <label for="consent_email" class="form-label">Email для оформления и отзыва согласия</label>
                                    <input type="email" class="form-control" id="consent_email" name="consent_email"
                                           placeholder="example@mail.ru"
                                           value="{{ old('consent_email') }}" required>
                                    <div class="valid-feedback">
                                        Поле заполнено верно!
                                    </div>
                                    <div class="invalid-feedback">
                                        Пожалуйста, укажите корректный email
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Ваш отзыв</label>
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
                            <div class="mb-3">
                                <label for="publication_conditions" class="form-label">Условия или запреты для публикации (необязательно)</label>
                                <textarea class="form-control" id="publication_conditions" rows="3"
                                          name="publication_conditions"
                                          placeholder="Например: не публиковать телефон/адрес и т.п.">{{ old('publication_conditions') }}</textarea>
                                <div class="form-text">
                                    Необязательное поле. Здесь можно указать условия или запреты для публикации отзыва
                                    (это не текст самого отзыва).
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
                                <input type="checkbox" class="form-check-input" id="user_agreement_accepted"
                                       name="user_agreement_accepted" required>
                                <label class="form-check-label" for="user_agreement_accepted">
                                    Я принимаю условия
                                    <a href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank" rel="noopener">
                                        Пользовательского соглашения</a>.
                                </label>
                                <div class="invalid-feedback">
                                    Пожалуйста, примите условия Пользовательского соглашения
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="personal_data_consent_accepted"
                                       name="personal_data_consent_accepted" required>
                                <label class="form-check-label" for="personal_data_consent_accepted">
                                    Я даю согласие на обработку моих персональных данных для направления отзыва.
                                    Подробнее —
                                    <a href="{{ route('review_personal_data_consent.info') }}" target="_blank" rel="noopener">
                                        согласие на обработку персональных данных при направлении отзыва</a>.
                                </label>
                                <div class="invalid-feedback">
                                    Пожалуйста, дайте согласие на обработку персональных данных
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="review_publication_consent_accepted"
                                       name="review_publication_consent_accepted" required>
                                <label class="form-check-label" for="review_publication_consent_accepted">
                                    Я даю согласие на публикацию моего отзыва и указанного мной имени на avilona.ru после модерации.
                                    <a href="{{ route('review_publication_consent.info') }}" target="_blank" rel="noopener">Читать согласие на публикацию</a>.
                                </label>
                                <div class="invalid-feedback">
                                    Пожалуйста, дайте согласие на публикацию отзыва
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </form>
                    </div>
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
            const form = document.querySelector('form#review_create');
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
    </script>
@endsection
