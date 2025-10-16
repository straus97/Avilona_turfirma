<!doctype html>
<html lang="ru">
<head>
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
    <!-- Open Graph теги для улучшения отображения при расшаривании в социальных сетях -->
    <meta property="og:title"
          content="@yield('og_title', 'Туристическая фирма Авилона - Путешествуйте с нами | avilona.ru')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://avilona.ru/">
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
    <meta name="twitter:image" content="https://www.avilona.ru/img/logo.png">
    <link rel="shortcut icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">
    <title>@yield('title', 'Туристическая фирма Авилона - Путешествуйте с нами | avilona.ru')</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/15fb7591d9.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" async></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
          integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.3/css/all.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/style_min.css') }}">
</head>
<body onload="initMap()">
{{-- шапка сайта --}}
<div class="container mt-3">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light ">
            <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
                <a class="navbar-brand mb-2 mb-lg-0" href="{{ route('home.index') }}"><img
                        src="{{ asset('img/logo.png') }}" alt="Company Logo"
                        class="img-fluid"></a>
                <div>
                    <span class="d-block mb-1">Адрес: <br>Санкт-Петербург, ул. Генерала Симоняка, д. 10</span>
                    <!-- Номера телефонов с кнопками для открытия модального окна -->
                    <span class="d-block mb-2">Телефон: <br>
                        <a href="#" onclick="openModal('+79219314345', 'Илона')">+7 (921) 931-43-45</a> <br>
                        <a href="#" onclick="openModal('+79219842022', 'Алла')">+7 (921) 984-20-22</a>
                    </span>
                </div>
                <!-- Модальное окно с менеджером -->
                <div id="contactModal" class="modal1">
                    <div class="modal-content-manager">
                        <span class="close-button">&times;</span>
                        <p id="managerName">Открыть чат с менеджером через:</p>
                        <!-- Идентификатор для изменения текста -->
                        <button class="whatsapp-button" onclick="openWhatsApp(currentNumber)">
                            <i class="fab fa-whatsapp fa-2x" style="color: #006600;"></i> WhatsApp
                        </button>
                        <button class="telegram-button" onclick="openTelegram(currentNumber)">
                            <i class="fa fa-telegram fa-2x" aria-hidden="true"></i> Telegram
                        </button>
                    </div>
                </div>
                {{--                <div class="d-none d-lg-flex mt-2">--}}
                {{--                    <button class="btn btn-primary w-100 me-2" type="button" data-bs-toggle="modal"--}}
                {{--                            data-bs-target="#importantInfoModal">Важная информация--}}
                {{--                    </button>--}}
                {{--                </div>--}}
                @auth
                    <div class="d-none d-lg-flex mt-2">
                        <button class="btn btn-primary w-100 me-2" type="button" data-bs-toggle="modal"
                                data-bs-target="#importantInfoModal">Важная информация
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="userMenuDropdown"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                {{ Auth::user()->name }}
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="userMenuDropdown">
                                <li><a class="dropdown-item" href="{{ route('account') }}">Личный кабинет</a></li>
                                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Настройки</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button class="dropdown-item" type="submit">Выйти</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                @else
                    <div class="d-none d-lg-flex mt-2">
                        <button class="btn btn-primary w-100 me-2" type="button" data-bs-toggle="modal"
                                data-bs-target="#importantInfoModal">Важная информация
                        </button>
                        {{--<a class="btn btn-secondary login-btn" href="{{ route('login') }}">Авторизация</a>--}}
                    </div>
                @endauth
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                        aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
        </nav>
        <nav class="navbar navbar-expand-lg navbar-light ">
            <div class="collapse navbar-collapse justify-content-center" id="navbarSupportedContent">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('home.index')}}">Главная</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Компания
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item h5 pb-2" href="{{route('about_company.index')}}">О компании</a>
                            </li>
                            <li><a class="dropdown-item h5 pb-2" href="{{route('employees.index')}}">Сотрудники</a></li>
                            <li><a class="dropdown-item h5" href="{{route('awards.index')}}">Наши достижения</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('countries.index')}}">Страны</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('destination.index')}}">Направления</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('contact.index')}}">Контакты</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('review.index')}}">Отзывы</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Полезная информация
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item h5 pb-2" href="{{route('interesting_articles.index')}}">Интересные
                                    статьи</a></li>
                            <li><a class="dropdown-item h5 pb-2" href="{{route('helpful_news.index')}}">Новости</a></li>
                            <li><a class="dropdown-item h5 pb-2" href="{{route('for_our_clients.index')}}">Специально
                                    для наших клиентов</a></li>
                            <li><a class="dropdown-item h5" href="{{route('travel_dictionary.index')}}">Туристический
                                    словарь</a></li>
                        </ul>
                    </li>
                    {{--                    <li class="d-lg-none">--}}
                    {{--                        <button class="btn btn-secondary w-100" type="button">Авторизация</button>--}}
                    {{--                    </li>--}}
                </ul>
            </div>
        </nav>
    </header>
</div>
<!-- Важная информация модальное окно -->
<div class="modal fade" id="importantInfoModal" tabindex="-1" aria-labelledby="importantInfoModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importantInfoModalLabel">Важная информация</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="text-center"><img src="{{ asset('/img/remont.png') }}" class="img-fluid" alt="Картинка"
                                            height="500px" width="500px"></p>
                <h3 class="text-center">Уважаемые пользователи сайта Авилона!</h3>
                <p>Мы рады приветствовать вас на нашем новом туристическом портале. Сайт в настоящее время активно
                    обновляется и находится на стадии активной разработки.</p>
                <p>Мы прилагаем все усилия, чтобы предложить вам наиболее удобный и информативный сервис. Однако,
                    учитывая, что наш сайт все еще в процессе разработки, некоторые функции могут временно работать
                    нестабильно или быть недоступны.</p>
                <p>Мы призываем вас, в случае обнаружения каких-либо недочетов или ошибок, связываться с нами по
                    электронной почте
                    <a href="mailto:straus97@mail.ru">straus97@mail.ru</a>. Мы также будем рады получить от вас любые
                    отзывы или предложения по улучшению работы сайта. Ваше мнение очень важно для нас и поможет нам
                    сделать наш сайт еще лучше и удобнее для вас.</p>
                <p>Благодарим за понимание и ценим вашу поддержку в этот период.</p>
                <p><b>С наилучшими пожеланиями, команда туристической фирмы "Авилона".</b></p>
            </div>
            <div class="modal-footer">
                <small class="text-muted">Дата публикации: 15 мая 2024 г.</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
<!-- Main Content -->
@yield('content')

@yield('scripts')
<!-- Footer -->
<footer class="pt-3 pb-1 mt-3">
    <div class="container">
        <div class="row">
            <div class="col-12 col-sm-6 col-md-2 mb-3">
                <a class="navbar-brand mb-2 mb-lg-0" href="{{ route('home.index') }}"><img
                        src="{{ asset('/img/logo.png') }}" alt="Company logo" class="w-75"></a>
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-3 mb-3">
                <p class="mt-2">Контактная информация</p>
                <p>Тел.: <a href="#" onclick="openModal('+79219314345', 'Илона')">+7 (921) 931-43-45</a></p>
                <p>E-mail: <a href="mailto:avilonatur@bk.ru">avilonatur@bk.ru</a></p>
                <p>Адрес: <br>Санкт-Петербург, ул. Генерала Симоняка, д. 10</p>
            </div>
            <div class="col-12 col-sm-6 col-md-2 mb-3">
                <h5>Краткое меню</h5>
                <ul class="list-unstyled">
                    <li><a href="{{route('home.index')}}">Главная</a></li>
                    <li><a href="{{route('countries.index')}}">Страны</a></li>
                    <li><a href="{{route('destination.index')}}">Направления</a></li>
                    <li><a href="{{route('contact.index')}}">Контакты</a></li>
                    <li><a href="{{route('review.index')}}">Отзывы</a></li>
                    <li><a href="{{route('for_our_clients.index')}}">Спец. предложения</a></li>
                </ul>
            </div>
            <div class="col-12 col-sm-6 col-md-3 col-lg-2 mb-3">
                <h5>Социальные сети</h5>
                <a href="https://vk.com/avilona" target="_blank"><img src="{{ asset('/img/vk.png') }}" alt=""
                                                                      width="45px" class="mb-1 mx-1 mt-1"></a>
                {{--                <a href="#" target="_blank"><img src="{{ asset('/img/fb.png') }}" alt="" width="45px" class="mb-1 mx-1 mt-1"></a>--}}
                <a href="#" target="_blank"><img src="{{ asset('/img/ok.png') }}" alt="" width="45px"
                                                 class="mb-1 mx-1 mt-1"></a>
                {{--                <a href="#" target="_blank"><img src="{{ asset('/img/inst.png') }}" alt="" width="45px" class="mb-1 mx-1 mt-1"></a>--}}
                <a href="mailto:avilonatur@bk.ru" target="_blank"><img src="{{ asset('/img/mail.png') }}" alt=""
                                                                       width="45px" class="mb-1 mx-1 mt-1"></a>
            </div>
            <div class="col-12 col-sm-6 col-md-2 col-lg-2 mb-3">
                <div class="row mb-3">
                    <h5>Метрика</h5>
                    <div class="row mb-3">
                        <div class="col-6">
                            {{-- счетчик liveinternet --}}
                            <a href="https://www.liveinternet.ru/click" target="_blank"><img id="licntF51C" width="88"
                                                                                             height="31"
                                                                                             style="border:0"
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
                        </div>
                        <div class="col-6">
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
                            <!-- Top.Mail.Ru logo -->
                            <a href="https://top-fwz1.mail.ru/jump?from=3150807">
                                <img src="https://top-fwz1.mail.ru/counter?id=3150807;t=479;l=1" height="31" width="88"
                                     alt="Top.Mail.Ru" style="border:0;"/></a>
                            <!-- /Top.Mail.Ru logo -->
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            {{-- счетчик Яндекс --}}
                            <!-- Yandex.Metrika informer -->
                            <a href="https://metrika.yandex.ru/stat/?id=56393833&amp;from=informer"
                               target="_blank" rel="nofollow"><img
                                    src="https://informer.yandex.ru/informer/56393833/3_1_FFE222FF_EEC202FF_0_pageviews"
                                    style="width:88px; height:31px; border:0;" alt="Яндекс.Метрика"
                                    title="Яндекс.Метрика: данные за сегодня (просмотры, визиты и уникальные посетители)"
                                    class="ym-advanced-informer" data-cid="56393833" data-lang="ru"/></a>
                            <!-- /Yandex.Metrika informer -->
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
                    </div>
                    <div class="row">
                        <p><a href="https://avilona.ru/sitemap.xml" target="_blank">Карта сайта</a></p>
                    </div>
                </div>
            </div>
            <div class="row">
                <p class="text-center mb-2 p-2">&copy; 2024 ООО «Авилона». Все
                    права защищены. Информация сайта защищена законом об авторских правах. <a
                        href="https://vk.com/id3221404" target="_blank">Shtraus Nikita</a></p>
            </div>
        </div>
    </div>
</footer>
{{-- код для кнопки подьема на верх страницы --}}
<a id="button-up"></a>
<script async="true" src="{{ asset('js/scripts_min.js') }}"></script>
{{--скрипт JS Bootstrap--}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
</body>
</html>
