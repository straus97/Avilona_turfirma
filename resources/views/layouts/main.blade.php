<!doctype html>
<html lang="ru">
<head>
    @php
        $pageCanonicalUrl = trim(
            htmlspecialchars_decode(
                $__env->yieldContent('canonical_url', url()->current()),
                ENT_QUOTES
            )
        );
        // Stage 13: серверный шлюз для аналитики. App\Support\CookieConsent —
        // единственный источник истины для нормализации согласия, тот же
        // класс использует App\Http\Middleware\CacheResponse для ключа
        // кэша, поэтому эти два места не могут разойтись.
        $avilonaAnalyticsConsent = \App\Support\CookieConsent::allowsAnalytics(
            request()->cookie(\App\Support\CookieConsent::COOKIE_NAME)
        );
    @endphp
    <!-- Кодировка страницы -->
    <meta charset="UTF-8">
    <!-- Установка viewport для адаптивного дизайна -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Совместимость с IE -->
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description"
          content="@yield('meta_description', 'Туристическая фирма Авилона предлагает лучшие туры и путевки для отдыха в России и за границей. Индивидуальный подход к каждому клиенту, горящие предложения, удобный конструктор туров. Подарите себе незабываемые впечатления и отдых вместе с нами.')">
    <meta name="keywords"
          content="@yield('meta_keywords', 'отпуск, отдых, туры, путевки, море, заграница, Россия, горящие предложения, туризм')">
    <meta name="author" content="Авилона">
    <!-- Разрешение для поисковых систем индексировать и следовать за ссылками -->
    <meta name="robots" content="index, follow">
    <!-- Канонический URL страницы -->
    <link rel="canonical" href="{{ $pageCanonicalUrl }}">
    <!-- Open Graph теги для улучшения отображения при расшаривании в социальных сетях -->
    <meta property="og:title"
          content="@yield('og_title', 'Туристическая фирма Авилона - Путешествуйте с нами | avilona.ru')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ $pageCanonicalUrl }}">
    <meta property="og:image" content="https://avilona.ru/img/logo.png">
    <meta property="og:description"
          content="@yield('og_description', 'Туристическая фирма Авилона предлагает лучшие туры и путевки для отдыха в России и за границей. Индивидуальный подход к каждому клиенту, горящие предложения, удобный конструктор туров. Подарите себе незабываемые впечатления и отдых вместе с нами.')">
    <meta property="og:site_name" content="Авилона">
    <meta property="og:locale" content="ru_RU">
    <!-- Теги для Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title"
          content="@yield('twitter_title', 'Туристическая фирма Авилона - Путешествуйте с нами | avilona.ru')">
    <meta name="twitter:description"
          content="@yield('twitter_description', 'Туристическая фирма Авилона предлагает лучшие туры и путевки для отдыха в России и за границей. Индивидуальный подход к каждому клиенту, горящие предложения, удобный конструктор туров. Подарите себе незабываемые впечатления и отдых вместе с нами.')">
    <meta name="twitter:image" content="https://avilona.ru/img/logo.png">
    <link rel="shortcut icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">
    <title>@yield('title', 'Туристическая фирма Авилона - Путешествуйте с нами | avilona.ru')</title>
    
    <!-- Заглушка для предотвращения ошибок Google Maps и других API -->
    <script>
        window.initMap = window.initMap || function() { console.log('initMap stub called'); };
    </script>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/15fb7591d9.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" defer></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
          integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.3/css/all.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/unified.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style_min.css') }}">
    @yield('styles')
    @yield('head_extra')
</head>
<body onload="initMap()">
@include('includes.cookie-consent')
{{-- шапка сайта --}}
@php
    // E2-A1-I1: единая адаптивная шапка. Данные офиса/телефонов вынесены в
    // локальные переменные, чтобы одинаковая разметка контактов
    // (десктопная утилити-полоса + мобильная панель) не разъезжалась.
    // E2-A2-I2: телефонные кнопки открывают общую Bootstrap-модалку
    // #managerContactModal декларативно (data-bs-toggle + data-manager-*),
    // наполнение делает public/js/e2-public.js.
    $officeAddress = 'Санкт-Петербург, ул. Генерала Симоняка, д. 10';
    $managerPhones = [
        ['number' => '+79219314345', 'name' => 'Илона', 'display' => '+7 (921) 931-43-45'],
        ['number' => '+79219842022', 'name' => 'Алла', 'display' => '+7 (921) 984-20-22'],
    ];

    $navHome      = request()->routeIs('home.index');
    $navCompany   = request()->routeIs('about_company.index', 'employees.index', 'awards.index');
    $navCountries = request()->routeIs('countries.index', 'countries.show_countries_image');
    $navDest      = request()->routeIs('destination.index', 'destinations.show_destinations_image');
    $navContact   = request()->routeIs('contact.index');
    $navReview    = request()->routeIs('review.index');
    $navUseful    = request()->routeIs('interesting_articles.index', 'helpful_news.index', 'for_our_clients.index', 'helpful_information.show_special', 'travel_dictionary.index');
@endphp
<header class="e2-header">
    <div class="e2-header__utility d-none d-xxl-block">
        <div class="container">
            <div class="e2-header__contact">
                <span><span class="e2-header__label">Адрес офиса:</span> {{ $officeAddress }}</span>
                <span class="e2-header__phones">
                    <span class="e2-header__label">Телефон:</span>
                    @foreach ($managerPhones as $phone)
                        <button type="button" class="e2-phone-link"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="single" data-manager-name="{{ $phone['name'] }}"
                                data-manager-phone="{{ $phone['number'] }}">{{ $phone['display'] }}</button>
                    @endforeach
                </span>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-xxl e2-navbar" aria-label="Основная навигация">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home.index') }}">
                <img src="{{ asset('img/logo.png') }}" alt="Туристическая фирма Авилона" class="e2-header__logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Открыть меню">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav e2-nav me-xxl-auto">
                    <li class="nav-item">
                        <a class="nav-link @if($navHome) active @endif" href="{{ route('home.index') }}"
                           @if($navHome) aria-current="page" @endif>Главная</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if($navCompany) active @endif" href="#"
                           id="navbarDropdownCompany" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                           @if($navCompany) aria-current="page" @endif>Компания</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownCompany">
                            <li><a class="dropdown-item" href="{{ route('about_company.index') }}">О компании</a></li>
                            <li><a class="dropdown-item" href="{{ route('employees.index') }}">Сотрудники</a></li>
                            <li><a class="dropdown-item" href="{{ route('awards.index') }}">Наши достижения</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if($navCountries) active @endif" href="{{ route('countries.index') }}"
                           @if($navCountries) aria-current="page" @endif>Страны</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if($navDest) active @endif" href="{{ route('destination.index') }}"
                           @if($navDest) aria-current="page" @endif>Направления</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if($navContact) active @endif" href="{{ route('contact.index') }}"
                           @if($navContact) aria-current="page" @endif>Контакты</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if($navReview) active @endif" href="{{ route('review.index') }}"
                           @if($navReview) aria-current="page" @endif>Отзывы</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if($navUseful) active @endif" href="#"
                           id="navbarDropdownUsefulInfo" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                           @if($navUseful) aria-current="page" @endif>Полезная информация</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownUsefulInfo">
                            <li><a class="dropdown-item" href="{{ route('interesting_articles.index') }}">Интересные статьи</a></li>
                            <li><a class="dropdown-item" href="{{ route('helpful_news.index') }}">Новости</a></li>
                            <li><a class="dropdown-item" href="{{ route('for_our_clients.index') }}">Специально для наших клиентов</a></li>
                            <li><a class="dropdown-item" href="{{ route('travel_dictionary.index') }}">Туристический словарь</a></li>
                        </ul>
                    </li>
                </ul>

                <div class="e2-header__account">
                    @auth
                        <div class="dropdown">
                            <button class="btn e2-btn e2-btn--secondary e2-btn--sm dropdown-toggle" type="button" id="userMenuDropdown"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                                @if(Auth::user()->isAdmin())
                                    <li><a class="dropdown-item" href="{{ route('cabinet.admin.bookings') }}">
                                        <i class="bi bi-bookmark"></i> Заявки
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cabinet.admin.dashboard') }}">
                                        <i class="bi bi-house-door"></i> Админ панель
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cabinet.admin.settings') }}">
                                        <i class="bi bi-gear"></i> Настройки
                                    </a></li>
                                @elseif(Auth::user()->isManager())
                                    <li><a class="dropdown-item" href="{{ route('cabinet.manager.bookings') }}">
                                        <i class="bi bi-bookmark"></i> Мои заявки
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cabinet.manager.dashboard') }}">
                                        <i class="bi bi-house-door"></i> Личный кабинет
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cabinet.manager.settings') }}">
                                        <i class="bi bi-gear"></i> Настройки
                                    </a></li>
                                @else
                                    <li><a class="dropdown-item" href="{{ route('cabinet.bookings') }}">
                                        <i class="bi bi-bookmark"></i> Мои заявки
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cabinet.dashboard') }}">
                                        <i class="bi bi-house-door"></i> Личный кабинет
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('cabinet.settings') }}">
                                        <i class="bi bi-gear"></i> Настройки
                                    </a></li>
                                @endif
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button class="dropdown-item text-danger" type="submit">
                                            <i class="bi bi-box-arrow-right"></i> Выйти
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a class="btn e2-btn e2-btn--secondary e2-btn--sm" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right"></i> Войти
                        </a>
                        <a class="btn e2-btn e2-btn--primary e2-btn--sm" href="{{ route('register') }}">
                            <i class="bi bi-person-plus"></i> Регистрация
                        </a>
                    @endauth
                </div>

                <div class="e2-header__panel-extra d-xxl-none">
                    <span><span class="e2-header__label">Адрес офиса:</span> {{ $officeAddress }}</span>
                    <span class="e2-header__phones">
                        <span class="e2-header__label">Телефон:</span>
                        @foreach ($managerPhones as $phone)
                            <button type="button" class="e2-phone-link"
                                    data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                    data-manager-mode="single" data-manager-name="{{ $phone['name'] }}"
                                    data-manager-phone="{{ $phone['number'] }}">{{ $phone['display'] }}</button>
                        @endforeach
                    </span>
                </div>
            </div>
        </div>
    </nav>

</header>
<!-- Main Content -->
@yield('content')

@yield('scripts')
<!-- Footer -->
<footer class="e2-footer">
    <div class="container">
        <div class="e2-footer__grid">
            <div>
                <a class="navbar-brand" href="{{ route('home.index') }}"><img
                        src="{{ asset('/img/logo.png') }}" alt="Туристическая фирма Авилона" class="e2-footer__logo"></a>
            </div>
            <div>
                <h2 class="e2-footer__heading">Контактная информация</h2>
                <p>Тел.: <button type="button" class="e2-phone-link"
                        data-bs-toggle="modal" data-bs-target="#managerContactModal"
                        data-manager-mode="single" data-manager-name="Илона"
                        data-manager-phone="+79219314345">+7 (921) 931-43-45</button></p>
                <p>E-mail: <a href="mailto:avilonatur@bk.ru">avilonatur@bk.ru</a></p>
                <p>Адрес офиса:<br>198261, Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
            </div>
            <nav aria-label="Навигация в подвале сайта">
                <h2 class="e2-footer__heading">Краткое меню</h2>
                <ul class="e2-footer__list">
                    <li><a href="{{route('home.index')}}">Главная</a></li>
                    <li><a href="{{route('countries.index')}}">Страны</a></li>
                    <li><a href="{{route('destination.index')}}">Направления</a></li>
                    <li><a href="{{route('contact.index')}}">Контакты</a></li>
                    <li><a href="{{route('review.index')}}">Отзывы</a></li>
                    <li><a href="{{route('for_our_clients.index')}}">Спец. предложения</a></li>
                    <li><a href="https://avilona.ru/sitemap.xml" target="_blank" rel="noopener">Карта сайта</a></li>
                </ul>
            </nav>
            <div>
                <h2 class="e2-footer__heading">Социальные сети</h2>
                <div class="e2-footer__social">
                    <a class="e2-footer__social-link" href="https://vk.com/avilona" target="_blank" rel="noopener"
                       aria-label="Авилона во ВКонтакте"><i class="fab fa-vk" aria-hidden="true"></i></a>
                    <a class="e2-footer__social-link" href="mailto:avilonatur@bk.ru"
                       aria-label="Написать письмо в Авилону"><i class="bi bi-envelope" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>

        <div class="e2-footer__utility">
            <p class="mb-0"><a href="https://avilona.ru/sitemap.xml" target="_blank" rel="noopener">Карта сайта</a></p>
            @if($avilonaAnalyticsConsent)
            {{-- Аналитика (гейт $avilonaAnalyticsConsent). Инициализация счётчиков
                 сохранена без изменений. Убраны только презентационные бейджи —
                 видимый «логотип» Top.Mail.Ru и информер-картинка Яндекс.Метрики
                 (informer.yandex.ru отдаёт 403 и показывался «битой» картинкой):
                 они не участвуют в инициализации счётчиков. Обязательный пиксель
                 LiveInternet (#licntF51C) и noscript-фолбэки оставлены. --}}
            <div class="e2-analytics" aria-hidden="true">
                {{-- счетчик liveinternet --}}
                <a href="https://www.liveinternet.ru/click" target="_blank"><img id="licntF51C" width="88"
                     height="31" style="border:0"
                     title="LiveInternet: показано число просмотров за 24 часа, посетителей за 24 часа и за сегодня"
                     src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7"
                     alt=""/></a>
                <script>(function (d, s) {
                        d.getElementById("licntF51C").src =
                            "https://counter.yadro.ru/hit?t21.6;r" + escape(d.referrer) +
                            ((typeof (s) == "undefined") ? "" : ";s" + s.width + "*" + s.height + "*" +
                                (s.colorDepth ? s.colorDepth : s.pixelDepth)) + ";u" + escape(d.URL) +
                            ";h" + escape(d.title.substring(0, 150)) + ";" + Math.random()
                    })
                    (document, screen)</script>
                {{-- счетчик Mail.ru --}}
                <!-- Top.Mail.Ru counter -->
                <script type="text/javascript">
                    var _tmr = window._tmr || (window._tmr = []);
                    _tmr.push({id: "3150807", type: "pageView", start: (new Date()).getTime()});
                    (function (d, w, id) {
                        if (d.getElementById(id)) return;
                        var ts = d.createElement("script");
                        ts.type = "text/javascript";
                        ts.async = true;
                        ts.id = id;
                        ts.src = "https://top-fwz1.mail.ru/js/code.js";
                        var f = function () {
                            var s = d.getElementsByTagName("script")[0];
                            s.parentNode.insertBefore(ts, s);
                        };
                        if (w.opera == "[object Opera]") {
                            d.addEventListener("DOMContentLoaded", f, false);
                        } else {
                            f();
                        }
                    })(document, window, "tmr-code");
                </script>
                <noscript>
                    <div><img src="https://top-fwz1.mail.ru/counter?id=3150807;js=na"
                              style="position:absolute;left:-9999px;" alt="Top.Mail.Ru"/></div>
                </noscript>
                <!-- /Top.Mail.Ru counter -->
                {{-- счетчик Яндекс --}}
                <!-- Yandex.Metrika counter -->
                <script type="text/javascript">
                    (function (m, e, t, r, i, k, a) {
                        m[i] = m[i] || function () {
                            (m[i].a = m[i].a || []).push(arguments)
                        };
                        m[i].l = 1 * new Date();
                        for (var j = 0; j < document.scripts.length; j++) {
                            if (document.scripts[j].src === r) {
                                return;
                            }
                        }
                        k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
                    })
                    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

                    ym(56393833, "init", {
                        clickmap: true,
                        trackLinks: true,
                        accurateTrackBounce: true
                    });
                </script>
                <noscript>
                    <div><img src="https://mc.yandex.ru/watch/56393833"
                              style="position:absolute; left:-9999px;" alt=""/></div>
                </noscript>
                <!-- /Yandex.Metrika counter -->
            </div>
            @endif
        </div>

        <div class="e2-footer__bottom">
            <p>&copy; {{ date('Y') }} ООО «Авилона». Все права защищены. Информация сайта защищена законом об авторских правах.</p>
            <span class="e2-footer__bottom-links">
                <a href="{{ route('cookies.info') }}">Использование cookie</a>
                <span aria-hidden="true">·</span>
                <button type="button" id="cookie-settings-open"
                        class="btn btn-link p-0 align-baseline">Настройки cookie</button>
            </span>
        </div>
    </div>
</footer>

{{-- E2-A2-I2: единая Bootstrap-модалка контактов менеджера. Одна на весь
     публичный сайт. Строки всех менеджеров присутствуют в разметке сразу;
     public/js/e2-public.js по data-атрибутам нажатого триггера показывает
     либо одного менеджера (телефоны шапки/подвала, data-manager-mode="single"),
     либо всех (обобщённые CTA, data-manager-mode="all"). Открытие/закрытие,
     фокус-трап, Escape, backdrop и возврат фокуса — штатный Bootstrap. --}}
<div class="modal fade e2-manager-modal" id="managerContactModal" tabindex="-1"
     aria-labelledby="managerContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="managerContactModalLabel">Связаться с менеджером</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="e2-manager-modal__intro" data-manager-intro
                   data-intro-all="Выберите менеджера и удобный мессенджер — откроется чат."
                   data-intro-single="Выберите удобный мессенджер — откроется чат с менеджером.">Выберите менеджера и удобный мессенджер — откроется чат.</p>
                <ul class="e2-manager-modal__list">
                    @foreach ($managerPhones as $phone)
                        @php $phoneDigits = preg_replace('/[^\d+]/', '', $phone['number']); @endphp
                        <li class="e2-manager-modal__row" data-manager-row
                            data-manager-name="{{ $phone['name'] }}" data-manager-phone="{{ $phone['number'] }}">
                            <span class="e2-manager-modal__manager">
                                <span class="e2-manager-modal__name">{{ $phone['name'] }}</span>
                                <span class="e2-manager-modal__phone">{{ $phone['display'] }}</span>
                            </span>
                            <span class="e2-manager-modal__actions">
                                <a class="e2-manager-modal__action e2-manager-modal__action--wa"
                                   href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp
                                </a>
                                <a class="e2-manager-modal__action e2-manager-modal__action--tg"
                                   href="https://t.me/{{ $phoneDigits }}" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-telegram" aria-hidden="true"></i> Telegram
                                </a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Кнопка подъёма наверх. id="button-up" сохранён дословно: на него завязан
     show/hide/click в public/js/e2-public.js. --}}
<button type="button" id="button-up" class="e2-button-up" aria-label="Наверх">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>
{{--скрипт JS Bootstrap--}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
{{-- Cookie consent banner: сохранение выбора, перезагрузка, повторное открытие --}}
<script src="{{ asset('js/cookie-consent.js') }}"></script>
{{-- E2-A2-I2: общий интерактивный слой (модалка контактов менеджера + кнопка
     «Наверх»). Заменяет в живом рантайме public/js/scripts_min.js. Грузится
     после bootstrap.bundle, поэтому Bootstrap уже доступен. --}}
<script src="{{ asset('js/e2-public.js') }}"></script>

{{-- Дополнительные скрипты из дочерних шаблонов --}}
@stack('scripts')

</body>
</html>
