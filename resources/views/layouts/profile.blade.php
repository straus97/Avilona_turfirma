<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Личный кабинет | Авилона</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
          href="{{asset('https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback')}}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{asset('plugins/fontawesome-free/css/all.min.css')}}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="{{asset('https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css')}}">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet" href="{{asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css')}}">
    <!-- iCheck -->
    <link rel="stylesheet" href="{{asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css')}}">
    <!-- JQVMap -->
    <link rel="stylesheet" href="{{asset('plugins/jqvmap/jqvmap.min.css')}}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{asset('dist/css/adminlte.min.css')}}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{asset('plugins/overlayScrollbars/css/OverlayScrollbars.min.css')}}">
    <!-- Daterange picker -->
    <link rel="stylesheet" href="{{asset('plugins/daterangepicker/daterangepicker.css')}}">
    <!-- summernote -->
    <link rel="stylesheet" href="{{asset('plugins/summernote/summernote-bs4.min.css')}}">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.3/css/all.css">

    {{--    Подключение Apline.js для работы с личным кабинетом, чтобы открывались модальные окна--}}
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <style>
        .custom-nav-item .nav-link.active {
            background-color: #343a40; /* Цвет фона активной ячейки */
            color: #bdc2cc; /* Цвет текста активной ячейки */
            /* Другие стили, если необходимо */
        }

        .custom-nav-item .nav-link.active:hover {
            background-color: #484e53; /* Цвет фона активной ячейки при наведении */
            color: #fafafa; /* Цвет текста активной ячейки при наведении */
            /* Другие стили, если необходимо */
        }

        body {
            /*font-size: 15px;*/
        }
    </style>

</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center">
        <img class="animation__shake" src="{{asset('dist/img/AdminLTELogo.png')}}" alt="AdminLTELogo" height="60"
             width="60">
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light" style="font-size: 1.2em;">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{route('home.index')}}" class="nav-link">Главная</a>
            </li>
            <li class="nav-item dropdown d-none d-sm-inline-block">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    Компания
                </a>
                <ul class="dropdown-menu" aria-labelledby="navbarDropdown" style="font-size: 1.2em;">
                    <li><a class="dropdown-item pb-2" href="{{route('about_company.index')}}">О компании</a>
                    </li>
                    <li><a class="dropdown-item pb-2" href="{{route('employees.index')}}">Сотрудники</a></li>
                    <li><a class="dropdown-item" href="{{route('awards.index')}}">Наши достижения</a></li>
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
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Messages Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-comments"></i>
                    <span class="badge badge-danger navbar-badge">3</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <a href="#" class="dropdown-item">
                        <!-- Message Start -->
                        <div class="media">
                            <img src="{{asset('dist/img/user1-128x128.jpg')}}" alt="User Avatar"
                                 class="img-size-50 mr-3 img-circle">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    Brad Diesel
                                    <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">Call me whenever you can...</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                            </div>
                        </div>
                        <!-- Message End -->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <!-- Message Start -->
                        <div class="media">
                            <img src="{{asset('dist/img/user8-128x128.jpg')}}" alt="User Avatar"
                                 class="img-size-50 img-circle mr-3">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    John Pierce
                                    <span class="float-right text-sm text-muted"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">I got your message bro</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                            </div>
                        </div>
                        <!-- Message End -->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <!-- Message Start -->
                        <div class="media">
                            <img src="{{asset('dist/img/user3-128x128.jpg')}}" alt="User Avatar"
                                 class="img-size-50 img-circle mr-3">
                            <div class="media-body">
                                <h3 class="dropdown-item-title">
                                    Nora Silvester
                                    <span class="float-right text-sm text-warning"><i class="fas fa-star"></i></span>
                                </h3>
                                <p class="text-sm">The subject goes here</p>
                                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                            </div>
                        </div>
                        <!-- Message End -->
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
                </div>
            </li>
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge">15</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">15 Notifications</span>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-envelope mr-2"></i> 4 new messages
                        <span class="float-right text-muted text-sm">3 mins</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-users mr-2"></i> 8 friend requests
                        <span class="float-right text-muted text-sm">12 hours</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-file mr-2"></i> 3 new reports
                        <span class="float-right text-muted text-sm">2 days</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="{{route('profile.dashboard')}}" class="brand-link">
            <img src="{{asset('img/logo.png')}}" alt="Авилона"
                 class="brand-image elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">Авилона</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                    data-accordion="false" style="font-size: 1em;">
                    <li class="nav-item user-panel">

                        <a href="#" class="nav-link">
                            <img src="{{asset('dist/img/user2-160x160.jpg')}}" class="img-circle elevation-2 mr-3"
                                 alt="User Image">
                            <p>
                                {{ Auth::user()->name }}
                                <i class="fas fa-angle-left right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{route('profile.edit')}}" class="nav-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                                         class="bi bi-gear ml-1 mr-2" viewBox="0 0 16 16">
                                        <path
                                            d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                                        <path
                                            d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                                    </svg>
                                    <p>Настройки</p>
                                </a>
                            </li>
                            <li class="nav-item custom-nav-item mb-2">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <a href="{{route('logout')}}"
                                       onclick="event.preventDefault(); this.closest('form').submit();"
                                       class="nav-link active">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                             fill="currentColor" class="bi bi-box-arrow-right ml-1 mr-2 ml-1 mr-2"
                                             viewBox="0 0 16 16">
                                            <path fill-rule="evenodd"
                                                  d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                                            <path fill-rule="evenodd"
                                                  d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                                        </svg>
                                        <p>Выход</p>
                                    </a>
                                </form>
                            </li>
                        </ul>
                    </li>
                    @php
                        $unreadCount = \App\Models\Message::where('receiver_id', Auth::id())
                            ->where('is_read', false)
                            ->count();
                        $isManager = auth()->user()->roles->contains('role', 'manager') || auth()->user()->roles->contains('role', 'admin');
                        $isAdmin = auth()->user()->roles->contains('role', 'admin');
                        $isTourist = auth()->user()->roles->contains('role', 'tourist');
                    @endphp
                    
                    @if($isManager)
                        {{-- Меню для менеджера --}}
                        <li class="nav-header">ПАНЕЛЬ МЕНЕДЖЕРА</li>
                        <li class="nav-item">
                            <a href="{{route('manager.dashboard')}}" class="nav-link {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                                <i class="bi bi-speedometer2 ml-1 mr-2"></i>
                                <p>Главная</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('manager.bookings')}}" class="nav-link {{ request()->routeIs('manager.bookings') ? 'active' : '' }}">
                                <i class="bi bi-bookmark ml-1 mr-2"></i>
                                <p>Заявки</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('manager.clients')}}" class="nav-link {{ request()->routeIs('manager.clients') ? 'active' : '' }}">
                                <i class="bi bi-people ml-1 mr-2"></i>
                                <p>Клиенты</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('manager.chat')}}" class="nav-link {{ request()->routeIs('manager.chat') ? 'active' : '' }}">
                                <i class="bi bi-chat-dots ml-1 mr-2"></i>
                                <p>
                                    Чат с клиентами
                                    @if($unreadCount > 0)
                                        <span class="badge badge-danger right">{{ $unreadCount }}</span>
                                    @endif
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('manager.statistics')}}" class="nav-link {{ request()->routeIs('manager.statistics') ? 'active' : '' }}">
                                <i class="bi bi-graph-up ml-1 mr-2"></i>
                                <p>Статистика</p>
                            </a>
                        </li>
                        
                        @if($isTourist)
                            {{-- Переключение на кабинет туриста --}}
                            <li class="nav-header">МОЙ КАБИНЕТ</li>
                            <li class="nav-item">
                                <a href="{{route('profile.dashboard')}}" class="nav-link">
                                    <i class="bi bi-person ml-1 mr-2"></i>
                                    <p>Личный кабинет</p>
                                </a>
                            </li>
                        @endif
                    @else
                        {{-- Меню для туриста --}}
                        <li class="nav-header">ЛИЧНЫЙ КАБИНЕТ</li>
                        <li class="nav-item">
                            <a href="{{route('profile.dashboard')}}" class="nav-link {{ request()->routeIs('profile.dashboard') ? 'active' : '' }}">
                                <i class="bi bi-speedometer2 ml-1 mr-2"></i>
                                <p>Главная</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('profile.bookings')}}" class="nav-link {{ request()->routeIs('profile.bookings') ? 'active' : '' }}">
                                <i class="bi bi-bookmark ml-1 mr-2"></i>
                                <p>Мои заявки</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('profile.chat')}}" class="nav-link {{ request()->routeIs('profile.chat') ? 'active' : '' }}">
                                <i class="bi bi-chat-dots ml-1 mr-2"></i>
                                <p>
                                    Чат с менеджером
                                    @if($unreadCount > 0)
                                        <span class="badge badge-danger right">{{ $unreadCount }}</span>
                                    @endif
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('profile.documents')}}" class="nav-link {{ request()->routeIs('profile.documents') ? 'active' : '' }}">
                                <i class="bi bi-file-earmark-text ml-1 mr-2"></i>
                                <p>Документы</p>
                            </a>
                        </li>
                    @endif
                    
                    @auth
                        @if($isAdmin)
                            <li class="nav-header">АДМИНИСТРИРОВАНИЕ</li>
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                    <i class="bi bi-speedometer2 ml-1 mr-2"></i>
                                    <p>Главная</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') || request()->routeIs('admin.user-roles') ? 'active' : '' }}">
                                    <i class="bi bi-people ml-1 mr-2"></i>
                                    <p>Пользователи</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.bookings') }}" class="nav-link {{ request()->routeIs('admin.bookings') ? 'active' : '' }}">
                                    <i class="bi bi-bookmark ml-1 mr-2"></i>
                                    <p>Заявки</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.content') }}" class="nav-link {{ request()->routeIs('admin.content') ? 'active' : '' }}">
                                    <i class="bi bi-newspaper ml-1 mr-2"></i>
                                    <p>Контент</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                                    <i class="bi bi-gear ml-1 mr-2"></i>
                                    <p>Настройки</p>
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        @yield('content')
    </div>
    <!-- /.content-wrapper -->

    <footer class="main-footer">
        &copy; {{ date('Y') }} ООО «Авилона». Все
        права защищены. Информация сайта защищена законом об авторских правах.
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

{{--скрипт JS Bootstrap--}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-HwwvtgBNo3bZJJLYd8oVXjrBZt8cqVSpeBNS5n7C8IVInixGAoxmnlMuBnhbgrkm"
        crossorigin="anonymous"></script>

<!-- jQuery -->
<script src="{{asset('plugins/jquery/jquery.min.js')}}"></script>
<!-- jQuery UI 1.11.4 -->
<script src="{{asset('plugins/jquery-ui/jquery-ui.min.js')}}"></script>
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
    $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
{{--<script src="{{asset('plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>--}}
<!-- ChartJS -->
<script src="{{asset('plugins/chart.js/Chart.min.js')}}"></script>
<!-- Sparkline -->
<script src="{{asset('plugins/sparklines/sparkline.js')}}"></script>
<!-- JQVMap -->
<script src="{{asset('plugins/jqvmap/jquery.vmap.min.js')}}"></script>
<script src="{{asset('plugins/jqvmap/maps/jquery.vmap.usa.js')}}"></script>
<!-- jQuery Knob Chart -->
<script src="{{asset('plugins/jquery-knob/jquery.knob.min.js')}}"></script>
<!-- daterangepicker -->
<script src="{{asset('plugins/moment/moment.min.js')}}"></script>
<script src="{{asset('plugins/daterangepicker/daterangepicker.js')}}"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="{{asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js')}}"></script>
<!-- Summernote -->
<script src="{{asset('plugins/summernote/summernote-bs4.min.js')}}"></script>
<!-- overlayScrollbars -->
<script src="{{asset('plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js')}}"></script>
<!-- AdminLTE App -->
<script src="{{asset('dist/js/adminlte.js')}}"></script>
</body>
</html>
