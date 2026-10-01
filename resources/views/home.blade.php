@extends('layouts.main')

@section('title', 'Главная - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на главную страницу туристической фирмы Авилона. Туристическая фирма Авилона предлагает удобный поиск туров, лучшие горящие предложения, последние новости и отзывы клиентов. Наши опытные менеджеры помогут вам выбрать идеальный отпуск. Индивидуальный подход, проверенные отели и высокий уровень сервиса.')
@section('meta_keywords', 'поиск туров, горящие предложения, отзывы клиентов, новости туризма, туристическое агентство, контактная информация, карта местоположения, туристическая фирма Авилона, главная, туристическая фирма, туры, путевки, акции')

<!-- Main Content -->
@section('content')
    {{-- E2-A2-I2: локальная модалка менеджеров удалена. Обобщённые CTA
         «Связаться с менеджером» открывают общую Bootstrap-модалку
         #managerContactModal из layouts/main.blade.php (data-manager-mode="all"). --}}
    <main>
        <div class="container">
            <section class="e2-hero" aria-labelledby="e2-hero-title">
                <div class="e2-hero__body">
                    <div class="e2-hero__content">
                        <h1 id="e2-hero-title" class="e2-hero__title">Поможем выбрать и организовать ваше путешествие</h1>
                        <p class="e2-hero__lead">Индивидуальный подбор туров, проверенные отели и поддержка опытного менеджера —
                            от выбора направления до вылета из Санкт-Петербурга.</p>
                        <div class="e2-hero__actions">
                            <a class="e2-btn e2-btn--primary" href="{{ route('tours.index') }}">Подобрать тур</a>
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
            {{-- E5-A4: прежняя «форма поиска» отправляла на /tours параметры, которые модуль Tourvisor не
                 принимает. Здесь честная точка входа: без полей и без переноса значений. Якорь #tour-search
                 оставлен для старых внешних ссылок. --}}
            <section class="e2-search-entry" id="tour-search" aria-labelledby="e2-search-entry-title">
                <div class="e2-search-entry__body">
                    <h2 id="e2-search-entry-title" class="e2-search-entry__title">Подберите тур онлайн</h2>
                    <p class="e2-search-entry__text">Выберите город вылета, направление, даты и состав туристов на странице
                        поиска туров. Понравившееся предложение можно отправить заявкой — менеджер Авилоны проверит
                        цену и наличие мест и свяжется с вами.</p>
                </div>
                <div class="e2-search-entry__actions">
                    <a class="e2-btn e2-search-entry__btn" href="{{ route('tours.index') }}">Открыть поиск туров</a>
                </div>
            </section>
            <!-- Best offers -->
            @if(isset($best_offers) && $best_offers->count() > 0)
            <section class="e2-section" id="block_best_offers" aria-labelledby="e2-best-offers-title">
                <div class="e2-section__head">
                    <h2 id="e2-best-offers-title" class="e2-section__title">Лучшие предложения</h2>
                    <p class="e2-section__intro">Подборка актуальных предложений от туристического агентства «Авилона».
                        Менеджер уточнит детали и подберёт вариант под ваши даты.</p>
                </div>
                <div class="e2-grid e2-grid--4">
                    @foreach($best_offers as $item_best_offers)
                        <article class="e2-card">
                            @if($item_best_offers->image)
                            <div class="e2-card__media">
                                <img src="{{ asset($item_best_offers->image) }}"
                                     alt="{{ $item_best_offers->title ?? 'Предложение тура' }}" loading="lazy">
                            </div>
                            @endif
                            <div class="e2-card__body">
                                <h3 class="e2-card__title">{{ $item_best_offers->title ?? 'Без названия' }}</h3>
                                <p class="e2-card__text">{{ Str::limit(strip_tags($item_best_offers->content ?? ''), 120) }}</p>
                                <p class="e2-card__meta">{{ $item_best_offers->created_at ? \Carbon\Carbon::parse($item_best_offers->created_at)->translatedFormat('j F Y г.') : '' }}</p>
                            </div>
                            <div class="e2-card__footer">
                                <button type="button" class="e2-btn e2-btn--primary e2-btn--sm"
                                        data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                        data-manager-mode="all">Связаться с менеджером</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            @endif
            <!-- Reviews of our customers -->
            @if(isset($reviews) && $reviews->count() > 0)
            <section class="e2-section" id="block_reviews" aria-labelledby="e2-reviews-title">
                <div class="e2-section__head">
                    <h2 id="e2-reviews-title" class="e2-section__title">Отзывы</h2>
                    <p class="e2-section__intro">Что рассказывают клиенты туристического агентства «Авилона» после поездок.</p>
                </div>
                <div class="e2-grid e2-grid--3">
                    @foreach($reviews as $item_reviews)
                        {{-- Легаси-классы `card h-100 d-flex flex-column` сохранены как
                             стабильный якорь карточки отзыва для существующих тестов;
                             визуально карточку задаёт .e2-card (переопределяет .card). --}}
                        <article class="e2-card e2-review card h-100 d-flex flex-column">
                            <div class="e2-card__body">
                                <div class="e2-review__head">
                                    @if($item_reviews->image)
                                    <img src="{{ asset($item_reviews->image) }}" class="e2-review__avatar"
                                         alt="Фото клиента {{ $item_reviews->name ?? '' }}" loading="lazy">
                                    @endif
                                    <h3 class="e2-card__title">{{ $item_reviews->name ?? 'Анонимный пользователь' }}</h3>
                                </div>
                                <p class="content e2-card__text">{{ Str::limit($item_reviews->content ?? '', 200) }}</p>
                                @if($item_reviews->content && strlen($item_reviews->content) > 200)
                                    <p class="full-content e2-card__text d-none">{{ $item_reviews->content }}</p>
                                    <button type="button" class="e2-btn e2-btn--tertiary read-more">Читать полностью</button>
                                @endif
                                @if($item_reviews->is_moderator_edited)
                                    <p class="e2-card__note">Текст отзыва отредактирован модератором без изменения общего смысла.</p>
                                @endif
                                <p class="e2-card__meta">{{ $item_reviews->created_at ? \Carbon\Carbon::parse($item_reviews->created_at)->translatedFormat('j F Y г.') : '' }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            @endif
            {{-- Единый конверсионный fallback: показывается только когда И «Лучшие
                 предложения», И «Отзывы» пусты одновременно, чтобы страница не
                 выглядела тонкой. Не заявляет причин отсутствия контента. --}}
            @if((!isset($best_offers) || $best_offers->count() === 0) && (!isset($reviews) || $reviews->count() === 0))
            <section class="e2-cta-band" aria-labelledby="e2-cta-band-title">
                <h2 id="e2-cta-band-title" class="e2-cta-band__title">Поможем подобрать тур</h2>
                <p class="e2-cta-band__text">Расскажите о желаемой поездке — менеджеры туристического агентства
                    «Авилона» подберут варианты под ваши даты и бюджет.</p>
                <div class="e2-cta-band__actions">
                    <a class="e2-btn e2-btn--primary" href="{{ route('tours.index') }}">Подобрать тур</a>
                    <button type="button" class="e2-btn e2-btn--secondary"
                            data-bs-toggle="modal" data-bs-target="#managerContactModal"
                            data-manager-mode="all">Связаться с менеджером</button>
                </div>
            </section>
            @endif
            @if(isset($news) && $news->count() > 0)
            <section class="e2-section" aria-labelledby="e2-news-title">
                <div class="e2-section__head">
                    <h2 id="e2-news-title" class="e2-section__title">Новости</h2>
                </div>
                <div class="e2-grid e2-grid--4">
                    @foreach($news as $item_news)
                        <article class="e2-card">
                            @if($item_news->image)
                            <div class="e2-card__media">
                                <img src="{{ $item_news->image }}" alt="{{ $item_news->title ?? 'Новость' }}" loading="lazy">
                            </div>
                            @endif
                            <div class="e2-card__body">
                                <h3 class="e2-card__title">{{ $item_news->title ?? 'Без названия' }}</h3>
                                <p class="e2-card__text">{{ Str::limit(strip_tags($item_news->description ?? ''), 100) }}</p>
                                <p class="e2-card__meta">{{ $item_news->pub_date ? \Carbon\Carbon::parse($item_news->pub_date)->translatedFormat('j F Y г.') : '' }}</p>
                                @if($item_news->slug)
                                <a href="{{ route('helpful_news_id.index', $item_news->slug) }}"
                                   class="e2-btn e2-btn--tertiary">Подробнее</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            @endif
            @if(isset($partners) && $partners->count() > 0)
            <section class="e2-section" aria-labelledby="e2-partners-title">
                <div class="e2-section__head">
                    <h2 id="e2-partners-title" class="e2-section__title">Наши партнёры</h2>
                    <p class="e2-section__intro">Туроператоры и сервисы, с которыми работает туристическое агентство «Авилона».</p>
                </div>
                <div class="e2-partners">
                    @foreach ($partners as $item_partner)
                        @if($item_partner->logo_partner)
                        <div class="e2-partner">
                            <img src="{{ $item_partner->logo_partner }}"
                                 alt="{{ $item_partner->name_partner ? $item_partner->name_partner . ' — логотип' : 'Логотип партнёра' }}"
                                 loading="lazy">
                        </div>
                        @endif
                    @endforeach
                </div>
            </section>
            @endif
            <section class="e2-section" aria-labelledby="e2-contact-title">
                <div class="e2-contact-cols">
                    <div class="e2-contact-block">
                        <h2 id="e2-contact-title" class="e2-section__title mb-3">Туристическое агентство «Авилона»</h2>
                        <dl class="e2-contact-list">
                            <div>
                                <dt><i class="bi bi-geo-alt" aria-hidden="true"></i></dt>
                                <dd><span class="e2-contact-list__label">Адрес офиса:</span>198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10</dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-telephone" aria-hidden="true"></i></dt>
                                <dd><span class="e2-contact-list__label">Телефоны:</span>
                                    <span class="e2-phone-values">
                                        <span class="e2-phone-values__item">+7 (921) 931-43-45</span>
                                        <span class="e2-phone-values__item">+7 (921) 984-20-22</span>
                                    </span></dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-envelope" aria-hidden="true"></i></dt>
                                <dd><span class="e2-contact-list__label">Эл. почта:</span><a href="mailto:avilonatur@bk.ru">avilonatur@bk.ru</a></dd>
                            </div>
                            <div>
                                <dt><i class="bi bi-clock" aria-hidden="true"></i></dt>
                                <dd><span class="e2-contact-list__label">Режим работы:</span>Понедельник - пятница, с 10:00 до 20:00,<br>Суббота -
                                    воскресенье, с 12:00 до 19:00</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="e2-contact-block">
                        <h2 class="e2-section__title mb-3">Есть вопросы — спрашивайте!</h2>
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
                              class="needs-validation e2-form" novalidate>
                            @csrf
                            <div class="row g-3">
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="name" class="form-label">Ваше имя
                                        <span class="e2-form__req" aria-hidden="true">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           placeholder="Например, Никита" value="{{ old('name') }}" required>
                                    <div class="valid-feedback">
                                        Поле заполнено верно!
                                    </div>
                                    <div class="invalid-feedback">
                                        Пожалуйста, введите свое имя
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 mb-3">
                                    <label for="email" class="form-label">Email
                                        <span class="e2-form__req" aria-hidden="true">*</span></label>
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
                                <div class="form-text">Можно оставить пустым, если не знаете, какую тему указать.</div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Ваше сообщение
                                    <span class="e2-form__req" aria-hidden="true">*</span></label>
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
                            <div class="mb-3">
                                <label for="captcha" class="form-label">Проверка капчи
                                    <span class="e2-form__req" aria-hidden="true">*</span></label>
                                <div class="e2-form__captcha">
                                    <div>
                                        <input type="text" name="captcha" id="captcha"
                                               class="form-control @error('captcha') is-invalid @enderror" maxlength="6"
                                               autocomplete="off" required>
                                        @error('captcha')
                                        <div class="invalid-feedback d-block">
                                            Пожалуйста, введите корректную капчу
                                        </div>
                                        @enderror
                                        <div class="form-text">Введите символы с картинки.</div>
                                    </div>
                                    <div class="input-group w-auto">
                                        {!! Captcha::img('flat', ['class' => 'captcha-image', 'alt' => 'Код с картинки для проверки капчи']) !!}
                                        <button class="btn btn-outline-secondary refresh-captcha" type="button"
                                                aria-label="Обновить изображение капчи">
                                            <i class="fas fa-sync-alt" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2 form-check">
                                <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                                <label class="form-check-label" for="agree">Я принимаю условия <a
                                        href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank"
                                        rel="noopener noreferrer">Пользовательского соглашения</a></label>
                                <div class="invalid-feedback">
                                    Пожалуйста, прочтите и отметьте свое согласие с условиями Пользовательского
                                    соглашения
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="personal_data_consent" name="personal_data_consent" required>
                                <label class="form-check-label" for="personal_data_consent">Я даю <a
                                        href="{{ route('personal_data_consent.info') }}" target="_blank"
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
            <section class="e2-section" aria-labelledby="e2-map-title">
                @php
                    // E1-A1-F3: локальная проверка согласия для карты. Не переиспользует
                    // $avilonaAnalyticsConsent из layouts/main.blade.php — секция content
                    // этого шаблона выполняется раньше, чем @php родительского layout,
                    // поэтому эта переменная там ещё не определена.
                    $avilonaMapConsent = \App\Support\CookieConsent::allowsAnalytics(
                        request()->cookie(\App\Support\CookieConsent::COOKIE_NAME)
                    );
                @endphp
                <div class="e2-section__head">
                    <h2 id="e2-map-title" class="e2-section__title">Как нас найти</h2>
                </div>
                @if($avilonaMapConsent)
                <div class="e2-map">
                    <div class="e2-map__frame ratio ratio-16x9">
                        <iframe
                            title="Google Карта: офис туристического агентства «Авилона», Санкт-Петербург, ул. Генерала Симоняка, д. 10"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2005.2877751084227!2d30.202907216066!3d59.82775128183606!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4696311aec4c423b%3A0x7f0d41bbdff2477a!2z0KLRg9GA0LjRgdGC0LjRh9C10YHQutC-0LUg0LDQs9C10L3RgtGB0YLQstC-INCQ0LLQuNC70L7QvdCw!5e0!3m2!1sru!2sru!4v1677875357483!5m2!1sru!2sru"
                            style="border:0;" allowfullscreen="" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
                @else
                <div id="google-map-consent-placeholder" class="e2-map">
                    <p class="e2-map__text"><strong>Адрес офиса:</strong><br>198261, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
                    <p class="e2-map__text">Интерактивная карта Google не загружается автоматически без вашего согласия на использование необязательных cookie.</p>
                    <div class="e2-map__actions">
                        <a class="e2-btn e2-btn--secondary" href="https://www.google.com/maps/search/?api=1&query=198261%2C+%D0%A0%D0%BE%D1%81%D1%81%D0%B8%D1%8F%2C+%D0%A1%D0%B0%D0%BD%D0%BA%D1%82-%D0%9F%D0%B5%D1%82%D0%B5%D1%80%D0%B1%D1%83%D1%80%D0%B3%2C+%D1%83%D0%BB.+%D0%93%D0%B5%D0%BD%D0%B5%D1%80%D0%B0%D0%BB%D0%B0+%D0%A1%D0%B8%D0%BC%D0%BE%D0%BD%D1%8F%D0%BA%D0%B0%2C+%D0%B4.+10"
                           target="_blank" rel="noopener noreferrer">Открыть в Google Картах</a>
                        <button type="button" class="e2-btn e2-btn--tertiary" data-cookie-settings-open>Настроить cookie</button>
                    </div>
                </div>
                @endif
            </section>
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
                    // Необязательное поле «Тема»: пустое значение валидно, но не
                    // показываем зелёный success-стейт — только нейтральный хелпер.
                    if (input.id === 'subject') {
                        input.classList.remove('is-valid', 'is-invalid');
                        return;
                    }
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
                    // E2-A2-I1: при клиентской ошибке валидации полагаемся на
                    // инлайновые .invalid-feedback у полей и не открываем общий
                    // модал «ошибка отправки» — он подразумевает сбой отправки,
                    // а не незаполненные поля. Серверные session('error') по-прежнему
                    // показывают #errorModal (см. блок выше).
                    event.preventDefault();
                    event.stopPropagation();
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
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
        {{-- E2-A2-I2: инлайновый скрипт локальной модалки менеджеров удалён.
             Общую модалку #managerContactModal обслуживает public/js/e2-public.js. --}}
    </script>
    
@endsection
