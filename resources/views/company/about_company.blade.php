@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@php
    $pageTitle = 'О компании - Туристическая фирма Авилона | avilona.ru';
    $pageDescription = 'Добро пожаловать на страницу о нашей туристической фирмы Авилона. Туристическая фирма Авилона предоставляет высококачественные туристические услуги. Ознакомьтесь с нашей офертой, политикой обработки данных, основными направлениями и перспективами развития. Индивидуальный подход к каждому клиенту и гарантированное качество.';

    // E1-FINAL-01 / E2-A8: пять категорийных коллекций AboutController остаются
    // как есть; ссылки строятся из ->slug через route('countries.show_countries_image').
    $directionGroups = [
        ['label' => 'Азия', 'items' => $category_asia],
        ['label' => 'Африка', 'items' => $category_africa],
        ['label' => 'Ближний Восток', 'items' => $category_middle_east],
        ['label' => 'Европа', 'items' => $category_europe],
        ['label' => 'Карибский бассейн', 'items' => $category_caribbean],
    ];

    // Существующие документы: пути и видимые названия сохраняются дословно.
    // Файлы не редактируются; дата — вторичный информационный текст, не
    // авторитетное утверждение об актуальности.
    $legalDocuments = [
        [
            'path' => '/documents/Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf',
            'title' => 'Публичная оферта на заключение договора о реализации туристского продукта',
        ],
        [
            'path' => '/documents/Public_offer_for_ticket_sales_and_hotel_bookings.pdf',
            'title' => 'Публичная оферта на реализацию билетов и бронирование отелей',
        ],
        [
            'path' => '/documents/Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
            'title' => 'Политика в отношении защиты и обработки персональных данных',
        ],
    ];

    $advantages = [
        ['icon' => 'bi-clock-history', 'text' => 'Многолетний опыт'],
        ['icon' => 'bi-passport', 'text' => 'Скорость при оформление виз'],
        ['icon' => 'bi-credit-card-2-front', 'text' => 'Рассрочка и кредиты на путешествия'],
        ['icon' => 'bi-shield-check', 'text' => 'Гарантия на все виды услуг'],
    ];
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('meta_keywords', 'туристическая фирма, о компании, оферта, политика обработки данных, направления, перспективы развития, качество услуг, возврат денежных средств, контактная информация')
@section('og_title', $pageTitle)
@section('og_description', $pageDescription)
@section('twitter_title', $pageTitle)
@section('twitter_description', $pageDescription)

<!-- Main Content -->
@section('content')
    {{-- E2-A4-I1: миграция на систему E2. Легаси includes.sidebar и col-md-10
         больше не подключаются. Ровно один H1, разделы — H2, подпункты — H3.
         Юридический адрес в теле страницы не меняется (ожидается отдельный
         юридический/контентный проход после регистрации в ФНС). PDF-файлы не
         редактируются. --}}
    <main>
        <div class="container">
            <div class="e2-about">
                @include('includes.e2-breadcrumb', ['items' => [
                    ['label' => 'Главная', 'url' => route('home.index')],
                    ['label' => 'О компании', 'url' => null],
                ]])

                <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                    <h1 id="e2-page-hero-title" class="e2-page-hero__title">О компании</h1>
                    <p class="e2-page-hero__intro">Как работает туристическая фирма «Авилона», юридические
                        документы, направления, способы оплаты и условия возврата, а также наши преимущества.</p>
                </section>

                <section class="e2-section" aria-labelledby="about-story-title">
                    <h2 id="about-story-title" class="e2-section__title">Кто мы</h2>
                    {{-- E2-A4 QA polish #2: текст занимает всю ширину контентной
                         области About (.e2-about); внутреннего ограничения ширины
                         (68/82/88ch) нет. Подразделы идут вертикально; заголовки и
                         порядок чтения сохранены. --}}
                    <div class="e2-prose">
                        <p>Наша компания была создана опытными путешественниками, объездившими не один десяток
                            стран, как результат их огромного желания разделить с другими страсть к новым
                            впечатлениям в путешествиях и поиска того, что еще может предложить наша планета.</p>

                        <h3>Перспективы развития</h3>
                        <p>Успех деятельности компании зависит от квалификации сотрудников, поэтому в
                            туристическом агентстве "Авилона" большое внимание уделяется обучению персонала,
                            создаются все условия для профессионального роста сотрудников. Понимая, что инвестиции
                            в квалифицированные кадры составляют основу долгосрочного успеха, в компании формируется
                            команда профессионалов, руководство постоянно заботится о повышении их квалификации,
                            мотивации, социальной защищенности и преданности корпоративным ценностям.</p>

                        <h3>Качество продукта</h3>
                        <p>За качеством услуг для наших клиентов следит команда профессионалов, которая постоянно
                            обновляет растущий ассортимент предложений, отбирают лучшие принимающие компании с
                            едиными стандартами качества обслуживания, а так же лучшие отели, проверенные нашими
                            сотрудниками и туристами, где персонал ориентирован на российский рынок, а также
                            страховое обеспечение.</p>
                    </div>
                </section>

                <section class="e2-section" aria-labelledby="about-docs-title">
                    <h2 id="about-docs-title" class="e2-section__title">Юридическая информация и документы</h2>
                    <p class="e2-section__intro">Публичные оферты и политика обработки персональных данных
                        туристической фирмы «Авилона».</p>
                    @foreach($legalDocuments as $doc)
                        <div class="e2-doc-callout">
                            <i class="bi bi-file-earmark-pdf e2-doc-callout__icon" aria-hidden="true"></i>
                            <a href="{{ asset($doc['path']) }}" target="_blank" rel="noopener">{{ $doc['title'] }}</a>
                            <span class="e2-doc-callout__type">PDF</span>
                            <span class="e2-doc-callout__date">Дата обновления: 22 мая 2024 г.</span>
                        </div>
                    @endforeach
                </section>

                {{-- E1-FINAL-01: ссылки строятся из ->slug (параметр маршрута — {slug}).
                     Если slug отсутствует, показываем название без ссылки — та же
                     защита, что в публичных списках стран/направлений. Ссылки
                     остаются внутри <main> и внутри <li> (test-locked). --}}
                <section class="e2-section" aria-labelledby="about-directions-title">
                    <h2 id="about-directions-title" class="e2-section__title">Основные направления</h2>
                    <p class="e2-section__intro">Мы помогаем организовать комплексные туристические поездки
                        по следующим направлениям.</p>
                    <div class="e2-about-directions">
                        @foreach($directionGroups as $group)
                            <div class="e2-about-directions__group">
                                <h3 class="e2-about-directions__label">{{ $group['label'] }}</h3>
                                @if(count($group['items']) > 0)
                                    <ul class="e2-about-directions__list">
                                        @foreach($group['items'] as $country)
                                            <li class="e2-about-directions__item">
                                                @if($country->slug)
                                                    <a href="{{ route('countries.show_countries_image', $country->slug) }}"
                                                       target="_blank" rel="noopener">{{ $country->title }}</a>
                                                @else
                                                    {{ $country->title }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="e2-about-directions__empty">Уточняйте у менеджера.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="e2-about-directions__actions">
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('countries.index') }}">Все страны</a>
                    </div>
                </section>

                <section class="e2-section" aria-labelledby="about-payment-title">
                    <h2 id="about-payment-title" class="e2-section__title">Оплата, возврат и передача документов</h2>
                    {{-- E2-A4 QA polish #2: естественный вертикальный поток на всю
                         ширину контентной области About, без искусственной
                         полуколонки. Текст, литералы оплаты/возврата, списки и
                         mailto не изменяются. --}}
                    <div class="e2-prose">
                        <h3>Способы оплаты услуг</h3>
                        <p>Оплатить туристические услуги можно наличными, банковской картой через
                            интернет-эквайринг, по QR-коду на расчётный счёт организации, а также банковской
                            картой через терминал в офисе.</p>

                        <h3>Условия возврата денежных средств</h3>
                        <p>Возврат денежных средств производится на банковскую карту клиента. Размер
                            возвращаемой суммы зависит от обстоятельств отмены и условий туроператора. Например,
                            если забронированный отель не был подтверждён, оплаченная сумма возвращается в полном
                            объёме. Если клиент самостоятельно отказывается от тура, туроператор может применить
                            предусмотренные его условиями удержания или штраф, и возврат производится за их
                            вычетом.</p>
                        <p>Конкретные условия и сумма возврата определяются индивидуально в зависимости от
                            бронирования и правил соответствующего туроператора.</p>
                        <p>Для возврата неиспользованных денежных средств необходимо:</p>
                        <ul>
                            <li>Заполнить заявление на возврат денежных средств (Заполняется заказчиком);</li>
                            <li>Направить заполненное отсканированное заявление на электронный адрес
                                <a href="mailto:avilonatur@bk.ru">avilonatur@bk.ru</a>;</li>
                            <li>Получить подтверждение о принятии заявления в работу.</li>
                        </ul>
                        <p>*Срок рассмотрения заявлений составляет 10 рабочих дней.</p>

                        <h3>Условия возврата и обмена товара/услуги</h3>
                        <p>Согласно договору.</p>

                        <h3>Если возникли проблемы с оплатой</h3>
                        <p>Оплата проходит в течение нескольких минут. Оплата может не пройти, потому что:</p>
                        <ul>
                            <li>Вы ввели неверные данные карты;</li>
                            <li>У карты закончился срок действия;</li>
                            <li>На карте недостаточно денег;</li>
                            <li>Нельзя подтвердить операцию по карте одноразовым паролем из СМС;</li>
                            <li>Банк установил запрет на оплату в интернете.</li>
                        </ul>
                        <p>Если оплата не прошла:</p>
                        <ul>
                            <li>Повторите попытку через 20 минут;</li>
                            <li>Обратитесь в банк, выпустивший карту;</li>
                            <li>Попробуйте оплатить другой картой.</li>
                        </ul>

                        <h3>Передача документов по договорённости</h3>
                        <p>Если клиент не может самостоятельно распечатать пакет документов для поездки,
                            сотрудники «Авилоны» могут по индивидуальной договорённости подготовить и распечатать
                            документы — например, страховку, ваучер и билеты — и передать пакет документов
                            клиенту.</p>
                    </div>
                </section>

                <section class="e2-section" aria-labelledby="about-advantages-title">
                    <h2 id="about-advantages-title" class="e2-section__title">Наши преимущества</h2>
                    <ul class="e2-grid e2-grid--4 e2-about-advantages">
                        @foreach($advantages as $advantage)
                            <li class="e2-card e2-about-advantage">
                                <span class="e2-about-advantage__icon">
                                    <i class="bi {{ $advantage['icon'] }}" aria-hidden="true"></i>
                                </span>
                                <p class="e2-about-advantage__text">{{ $advantage['text'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="e2-cta-band" aria-labelledby="about-cta-title">
                    <h2 id="about-cta-title" class="e2-cta-band__title">Готовы обсудить поездку?</h2>
                    <p class="e2-cta-band__text">Подберём тур под ваши даты и бюджет — начните подбор
                        или напишите менеджеру.</p>
                    <div class="e2-cta-band__actions">
                        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                        <button type="button" class="e2-btn e2-btn--secondary"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="all">Связаться с менеджером</button>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('employees.index') }}">Наш коллектив</a>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('awards.index') }}">Наши достижения</a>
                    </div>
                </section>
            </div>
        </div>
    </main>
@endsection
