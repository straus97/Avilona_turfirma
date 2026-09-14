<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Идентификатор текущего пользователя — только числовой id, нужен для
         локального неймспейса черновиков чата и их очистки при выходе. --}}
    <meta name="cabinet-user-id" content="{{ Auth::id() }}">
    <title>@yield('title', 'Личный кабинет') | Авилона</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Font Awesome (для дополнительных иконок) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Общая визуальная система кабинета (E3) — после Bootstrap и иконок -->
    <link href="{{ asset('css/cabinet-e3.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body class="cabinet-shell"
      x-data="{
          sidebarOpen: false,
          closeSidebar() { this.sidebarOpen = false; },
          toggleSidebar() { this.sidebarOpen = !this.sidebarOpen; }
      }"
      x-init="
          const cabinetDesktopQuery = window.matchMedia('(min-width: 992px)');
          const closeDrawerOnDesktop = (event) => { if (event.matches) { sidebarOpen = false; } };
          cabinetDesktopQuery.addEventListener('change', closeDrawerOnDesktop);
      "
      x-effect="document.body.classList.toggle('cabinet-no-scroll', sidebarOpen)"
      @keydown.escape.window="closeSidebar()">

    <a class="cabinet-skip-link" href="#cabinet-main-content">Перейти к основному содержимому</a>

    <!-- Header -->
    <header class="cabinet-header" role="banner">
        <button type="button"
                class="sidebar-toggle"
                aria-label="Открыть меню"
                :aria-label="sidebarOpen ? 'Закрыть меню' : 'Открыть меню'"
                aria-controls="cabinet-sidebar"
                :aria-expanded="sidebarOpen ? 'true' : 'false'"
                @click="toggleSidebar()">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <a href="/" class="header-brand">
            <i class="bi bi-airplane-fill" aria-hidden="true"></i>
            Авилона
        </a>

        <div class="header-actions">

            <!-- Notifications -->
            @php
                $__unreadNotificationCount = Auth::user()->unreadNotifications()
                    ->where('type', \App\Notifications\NewMessageDatabaseNotification::class)
                    ->count();

                $__latestUnreadNotification = Auth::user()->unreadNotifications()
                    ->where('type', \App\Notifications\NewMessageDatabaseNotification::class)
                    ->latest()
                    ->first();
            @endphp
            <div class="dropdown header-notifications">
                <button type="button"
                        class="btn btn-link p-0 text-decoration-none"
                        data-bs-toggle="dropdown"
                        aria-label="Уведомления"
                        title="Уведомления">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    @if($__unreadNotificationCount > 0)
                        <span class="notification-badge">{{ $__unreadNotificationCount }}</span>
                    @endif
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if($__latestUnreadNotification)
                        @php
                            $__latestNotificationData = is_array($__latestUnreadNotification->data)
                                ? $__latestUnreadNotification->data
                                : [];
                            $__latestNotificationSenderName = $__latestNotificationData['sender_name'] ?? 'Пользователь';
                            $__latestNotificationPreview = $__latestNotificationData['preview'] ?? 'Новое сообщение';
                        @endphp
                        <li>
                            <form method="POST" action="{{ route('cabinet.notifications.open', ['notification' => $__latestUnreadNotification->id]) }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <div style="font-weight: 700; font-size: 0.8125rem;">Новое сообщение</div>
                                    <div style="font-size: 0.75rem; color: #6b7280;">Отправитель: {{ $__latestNotificationSenderName }}</div>
                                    <div style="font-size: 0.75rem; color: #6b7280;">{{ \Illuminate\Support\Str::limit($__latestNotificationPreview, 60) }}</div>
                                </button>
                            </form>
                        </li>
                    @else
                        <li><span class="dropdown-item-text text-muted">Нет новых уведомлений</span></li>
                    @endif
                </ul>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
                <button type="button" class="header-user" data-bs-toggle="dropdown" aria-label="Меню пользователя">
                    <span class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </span>
                    <span class="d-none d-md-block">
                        <span style="display: block; font-weight: 600; font-size: 0.875rem;">{{ Auth::user()->name }}</span>
                        <span style="display: block; font-size: 0.75rem; color: #6b7280;">
                            @if(Auth::user()->hasAnyRole(['admin']))
                                Администратор
                            @elseif(Auth::user()->hasAnyRole(['manager']))
                                Менеджер
                            @else
                                Турист
                            @endif
                        </span>
                    </span>
                    <i class="bi bi-chevron-down" style="font-size: 0.75rem;" aria-hidden="true"></i>
                </button>
                @php
                    if (Auth::user()->hasAnyRole(['admin'])) {
                        $headerProfileRoute = 'cabinet.admin.profile';
                        // Admin-only: personal "Настройки" merges into "Мой профиль" (E3-A5);
                        // "Система" stays a sidebar-only destination, not a personal menu item.
                        $headerSettingsRoute = null;
                    } elseif (Auth::user()->hasAnyRole(['manager'])) {
                        $headerProfileRoute = 'cabinet.manager.profile';
                        $headerSettingsRoute = 'cabinet.manager.settings';
                    } else {
                        $headerProfileRoute = 'cabinet.profile';
                        $headerSettingsRoute = 'cabinet.settings';
                    }
                @endphp
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route($headerProfileRoute) }}">
                        <i class="bi bi-person me-2" aria-hidden="true"></i> Мой профиль
                    </a></li>
                    @if($headerSettingsRoute)
                    <li><a class="dropdown-item" href="{{ route($headerSettingsRoute) }}">
                        <i class="bi bi-gear me-2" aria-hidden="true"></i> Настройки
                    </a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/">
                        <i class="bi bi-house me-2" aria-hidden="true"></i> На главную
                    </a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" data-cabinet-logout>
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Выйти
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Mobile drawer backdrop -->
    <div class="cabinet-sidebar-backdrop"
         x-cloak
         x-show="sidebarOpen"
         x-transition.opacity
         @click="closeSidebar()"
         aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside id="cabinet-sidebar"
           class="cabinet-sidebar"
           :class="{ 'is-open': sidebarOpen }">
        <nav class="sidebar-menu"
             aria-label="Разделы кабинета"
             @click="if ($event.target.closest('a')) closeSidebar()">
            @yield('sidebar')
        </nav>
    </aside>

    <!-- Main Content -->
    <main id="cabinet-main-content" class="cabinet-main" role="main" tabindex="-1">
        @include('cabinet.components.flash')

        @yield('content')
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Общее прогрессивное улучшение чата кабинета (E3): очистка черновиков при
         выходе на всех страницах + плавное переключение веток там, где есть
         [data-chat-root]. -->
    <script defer src="{{ asset('js/cabinet-chat.js') }}"></script>

    <!-- AJAX Setup -->
    <script>
        // Setup AJAX CSRF Token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Helper для AJAX запросов
        window.ajax = {
            get: (url) => fetch(url, {
                headers: { 'X-CSRF-TOKEN': csrfToken }
            }),
            post: (url, data) => fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(data)
            }),
            delete: (url) => fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
        };

        // Auto-hide legacy toasts, если они где-то ещё используются постранично
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 5000);
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
