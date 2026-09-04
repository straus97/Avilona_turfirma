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
@section('og_title', 'Отзывы туристов — Туристическая фирма Авилона')
@section('og_description', 'Отзывы клиентов туристического агентства «Авилона» после поездок. Оставьте свой отзыв.')

{{-- E2-A6-I1: миграция страницы «Отзывы» на систему E2. Убраны: легаси
     легаси-сайдбар (partial остаётся в репозитории для travel_dictionary),
     bootstrap-обёртка контентной колонки, инлайновые модалки Bootstrap-4
     (.modal('show') не существует в Bootstrap 5). Добавлены: хлебные крошки,
     hero с единственным
     <h1>, адаптивный список карточек отзывов, E2-форма.

     ЗАМОРОЖЕННЫЕ контракты Stage 13 сохранены дословно:
       — модерация: публикуются только is_published = 1 и без отозванного
         согласия (фильтр в Review\IndexController, не здесь);
       — три отдельных обязательных чекбокса согласия в утверждённом порядке
         (user_agreement_accepted, personal_data_consent_accepted,
         review_publication_consent_accepted);
       — приватные поля согласия (consent_full_name / consent_email /
         publication_conditions) не публикуются;
       — «Тема»/title не выводится публично;
       — имя и текст отзыва экранируются ({{ }}, не {!! !!});
       — маркер модераторской правки и его точный текст;
       — UX ошибок валидации (возврат к форме, сводка, фокус первого поля,
         якорь review-form, scrollIntoView только при $errors->any(), без AJAX
         кроме обновления капчи). --}}
@section('content')
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Отзывы', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Отзывы туристов</h1>
                <p class="e2-page-hero__intro">Впечатления клиентов туристического агентства «Авилона» после поездок.
                    Публикуются только отзывы, прошедшие модерацию. Уже отдыхали с нами — расскажите, как всё прошло.</p>
                <div class="e2-page-hero__actions">
                    <a class="e2-btn e2-btn--primary" href="#review-form">Оставить отзыв</a>
                </div>
            </section>

            @if (session('success'))
                <div class="e2-alert e2-alert--success" role="status">
                    Ваш отзыв отправлен на модерацию. После проверки он может быть опубликован на avilona.ru
                    с указанным вами именем.
                </div>
            @endif

            <section class="e2-section" aria-labelledby="e2-reviews-list-title">
                <div class="e2-section__head">
                    <h2 id="e2-reviews-list-title" class="e2-section__title">Что говорят наши клиенты</h2>
                </div>

                @if(isset($reviews) && $reviews->count() > 0)
                    <div class="e2-review-list">
                        @foreach($reviews as $item_review)
                            {{-- Классы `row mb-4` сохранены как стабильный якорь карточки
                                 отзыва для замороженного теста раскрытия модераторской
                                 правки; визуально карточку задаёт .e2-review-item/.e2-card. --}}
                            <article class="e2-review-item e2-card row mb-4">
                                <div class="e2-card__body">
                                    <div class="e2-review__head">
                                        @if($item_review->image)
                                            <img src="{{ asset($item_review->image) }}" class="e2-review__avatar"
                                                 alt="Фото клиента {{ $item_review->name ?? '' }}" loading="lazy">
                                        @else
                                            {{-- Нейтральная заглушка-аватар (без предположений о поле):
                                                 существующая иконка Bootstrap Icons, decorative
                                                 (aria-hidden), т.к. имя рецензента уже озвучено в <h3>. --}}
                                            <span class="e2-review__avatar e2-review__avatar--placeholder" aria-hidden="true">
                                                <i class="bi bi-person-fill"></i>
                                            </span>
                                        @endif
                                        {{-- Имя и дата — один компактный идентити-кластер рядом с
                                             аватаром, а не отдельный блок в конце карточки. --}}
                                        <div class="e2-review__identity">
                                            <h3 class="e2-card__title">{{ $item_review->name ?? 'Анонимный пользователь' }}</h3>
                                            <p class="e2-review__date">{{ $item_review->created_at ? \Carbon\Carbon::parse($item_review->created_at)->translatedFormat('j F Y г.') : '' }}</p>
                                        </div>
                                    </div>
                                    <p class="content e2-card__text">{{ Str::limit($item_review->content ?? '', 200) }}</p>
                                    @if($item_review->content && strlen($item_review->content) > 200)
                                        <p class="full-content e2-card__text d-none">{{ $item_review->content }}</p>
                                        <button type="button" class="e2-btn e2-btn--tertiary read-more">Читать полностью</button>
                                    @endif
                                    @if($item_review->is_moderator_edited)
                                        <p class="e2-card__note">Текст отзыва отредактирован модератором без изменения общего смысла.</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="e2-pagination">
                        {{ $reviews->links() }}
                    </div>
                @else
                    <div class="e2-cta-band" aria-labelledby="e2-reviews-empty-title">
                        <h3 id="e2-reviews-empty-title" class="e2-cta-band__title">Пока нет опубликованных отзывов</h3>
                        <p class="e2-cta-band__text">Как только появятся отзывы, прошедшие модерацию, они появятся
                            здесь. Вы можете стать первым — форма ниже.</p>
                        <div class="e2-cta-band__actions">
                            <a class="e2-btn e2-btn--primary" href="#review-form">Оставить отзыв</a>
                        </div>
                    </div>
                @endif
            </section>

            <section class="e2-section e2-section--warm" aria-labelledby="e2-review-form-title">
                <div class="e2-contact-block">
                    <h2 id="e2-review-form-title" class="e2-section__title mb-3">Напишите нам!</h2>
                    <p>Уважаемые туристы! Если вы уже отдохнули с турфирмой «Авилона», расскажите о ваших
                        впечатлениях. Все отзывы с пожеланиями, замечаниями и предложениями вы можете направить нам,
                        заполнив форму ниже. Нам важно знать ваше мнение!<br>
                        <small>*Данная форма не предназначена для отправки заявок на тур.</small></p>

                    <div class="e2-alert e2-alert--info" role="note">
                        Отзыв сначала поступит на модерацию и не публикуется автоматически. После одобрения он
                        может быть опубликован на avilona.ru с указанным вами именем.
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            Отзыв не отправлен. Проверьте выделенные поля и попробуйте ещё раз.
                        </div>
                    @endif

                    <form action="{{ route('review_create.index') }}" id="review-form" method="post"
                          class="needs-validation e2-form" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Ваше имя
                                <span class="e2-form__req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   placeholder="Например, Никита" value="{{ old('name') }}" required>
                            <div class="valid-feedback">Поле заполнено верно!</div>
                            <div class="invalid-feedback">Пожалуйста, введите свое имя</div>
                        </div>

                        <p class="e2-form__note">
                            Данные ниже (ФИО и email для оформления согласия) используются только для оформления
                            и, при необходимости, отзыва согласия. Они не публикуются вместе с отзывом.
                        </p>
                        <div class="row g-3">
                            <div class="col-12 col-md-6 mb-3">
                                <label for="consent_full_name" class="form-label">ФИО для оформления согласия
                                    <span class="e2-form__req" aria-hidden="true">*</span></label>
                                <input type="text" class="form-control" id="consent_full_name" name="consent_full_name"
                                       placeholder="Иванов Иван Иванович" value="{{ old('consent_full_name') }}" required>
                                <div class="valid-feedback">Поле заполнено верно!</div>
                                <div class="invalid-feedback">Пожалуйста, укажите ФИО для оформления согласия</div>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label for="consent_email" class="form-label">Email для оформления и отзыва согласия
                                    <span class="e2-form__req" aria-hidden="true">*</span></label>
                                <input type="email" class="form-control" id="consent_email" name="consent_email"
                                       placeholder="example@mail.ru" value="{{ old('consent_email') }}" required>
                                <div class="valid-feedback">Поле заполнено верно!</div>
                                <div class="invalid-feedback">Пожалуйста, укажите корректный email</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Ваш отзыв
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
                            <label for="publication_conditions" class="form-label">Условия или запреты для публикации
                                (необязательно)</label>
                            <textarea class="form-control" id="publication_conditions" rows="3"
                                      name="publication_conditions"
                                      placeholder="Например: не публиковать телефон/адрес и т.п.">{{ old('publication_conditions') }}</textarea>
                            <div class="form-text">
                                Необязательное поле. Здесь можно указать условия или запреты для публикации отзыва
                                (это не текст самого отзыва).
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
                            <input type="checkbox" class="form-check-input" id="user_agreement_accepted"
                                   name="user_agreement_accepted" required>
                            <label class="form-check-label" for="user_agreement_accepted">
                                Я принимаю условия
                                <a href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank" rel="noopener noreferrer">
                                    Пользовательского соглашения</a>.
                            </label>
                            <div class="invalid-feedback">
                                Пожалуйста, примите условия Пользовательского соглашения
                            </div>
                        </div>
                        <div class="mb-2 form-check">
                            <input type="checkbox" class="form-check-input" id="personal_data_consent_accepted"
                                   name="personal_data_consent_accepted" required>
                            <label class="form-check-label" for="personal_data_consent_accepted">
                                Я даю согласие на обработку моих персональных данных для направления отзыва. Подробнее —
                                <a href="{{ route('review_personal_data_consent.info') }}" target="_blank" rel="noopener noreferrer">
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
                                <a href="{{ route('review_publication_consent.info') }}" target="_blank" rel="noopener noreferrer">Читать согласие на публикацию</a>.
                            </label>
                            <div class="invalid-feedback">
                                Пожалуйста, дайте согласие на публикацию отзыва
                            </div>
                        </div>

                        <div class="e2-form__actions">
                            <button type="submit" class="e2-btn e2-btn--primary">Отправить</button>
                        </div>
                    </form>
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
            var form = document.getElementById('review-form');
            if (form) {
                form.addEventListener('submit', function (event) {
                    var controls = form.querySelectorAll('input, textarea');
                    var valid = true;
                    controls.forEach(function (control) {
                        // Необязательные поля не помечаем ни зелёным, ни красным.
                        if (control.id === 'publication_conditions') {
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
            }

            // Раскрытие полного текста длинного отзыва.
            document.querySelectorAll('.read-more').forEach(function (button) {
                button.addEventListener('click', function () {
                    var body = button.closest('.e2-card__body');
                    if (!body) {
                        return;
                    }
                    var teaser = body.querySelector('.content');
                    var full = body.querySelector('.full-content');
                    if (!teaser || !full) {
                        return;
                    }
                    var expanded = !full.classList.contains('d-none');
                    full.classList.toggle('d-none', expanded);
                    teaser.classList.toggle('d-none', !expanded);
                    button.textContent = expanded ? 'Читать полностью' : 'Скрыть отзыв';
                });
            });
        });
    </script>
    @if ($errors->any())
        {{-- Форма отзывов расположена внизу страницы: после редиректа с ошибками
             валидации возвращаем посетителя к форме и ставим фокус на первое
             поле с серверной ошибкой. Обычная отправка формы не перехватывается. --}}
        <script>
            (function () {
                var firstErrorField = @json($errors->keys()[0] ?? null);

                function returnToReviewForm() {
                    try {
                        var form = document.getElementById('review-form');
                        if (!form) {
                            return;
                        }
                        if (typeof form.scrollIntoView === 'function') {
                            form.scrollIntoView({behavior: 'smooth', block: 'start'});
                        }
                        if (!firstErrorField || !form.elements) {
                            return;
                        }
                        var target = form.elements.namedItem(firstErrorField);
                        // namedItem может вернуть коллекцию одноимённых полей.
                        if (target && !target.tagName && typeof target.length === 'number') {
                            target = target[0] || null;
                        }
                        if (!target || typeof target.focus !== 'function') {
                            return;
                        }
                        try {
                            target.focus({preventScroll: true});
                        } catch (ignored) {
                            target.focus();
                        }
                    } catch (ignored) {
                        // Только улучшение UX: ошибки здесь не должны ломать страницу.
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', returnToReviewForm);
                } else {
                    returnToReviewForm();
                }
            })();
        </script>
    @endif
@endsection
