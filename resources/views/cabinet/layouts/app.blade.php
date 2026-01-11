<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    <!-- Alpine.js (загружаем после Bootstrap для избежания конфликтов) -->

    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 260px;
            --header-height: 60px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
        }

        /* Header */
        .cabinet-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .header-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-notifications {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: #fff;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
            font-weight: 600;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .header-user:hover {
            background: var(--light-color);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        /* Sidebar */
        .cabinet-sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: #fff;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
            z-index: 999;
            transition: transform 0.3s ease;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-section-title {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            color: #9ca3af;
            padding: 0 1.5rem;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }

        .menu-item i {
            width: 20px;
            font-size: 18px;
        }

        .menu-item:hover {
            background: #f9fafb;
            color: var(--primary-color);
        }

        .menu-item.active {
            background: #eff6ff;
            color: var(--primary-color);
            font-weight: 600;
        }

        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary-color);
        }

        .menu-badge {
            margin-left: auto;
            background: var(--danger-color);
            color: #fff;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Main Content */
        .cabinet-main {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 2rem;
            min-height: calc(100vh - var(--header-height));
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .page-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        /* Cards */
        .card-custom {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }

        .card-header-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-title-custom {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-new {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-progress {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-completed {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* Mobile Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #4b5563;
            cursor: pointer;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .cabinet-sidebar {
                transform: translateX(-100%);
            }

            .cabinet-sidebar.active {
                transform: translateX(0);
            }

            .cabinet-main {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .header-brand {
                font-size: 1.25rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .cabinet-main {
                padding: 1rem;
            }
        }

        /* Scrollbar */
        .cabinet-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .cabinet-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .cabinet-sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .cabinet-sidebar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Loading Spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: calc(var(--header-height) + 1rem);
            right: 1rem;
            z-index: 1050;
        }
    </style>

    @stack('styles')
</head>
<body x-data="{ sidebarOpen: false }">
    <!-- Header -->
    <header class="cabinet-header">
        <button class="sidebar-toggle" @click="sidebarOpen = !sidebarOpen">
            <i class="bi bi-list"></i>
        </button>

        <a href="/" class="header-brand">
            <i class="bi bi-airplane-fill"></i>
            Авилона
        </a>

        <div class="header-actions">

            <!-- User Menu -->
            <div class="dropdown">
                <div class="header-user" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="d-none d-md-block">
                        <div style="font-weight: 600; font-size: 0.875rem;">{{ Auth::user()->name }}</div>
                        <div style="font-size: 0.75rem; color: #6b7280;">
                            @if(Auth::user()->hasAnyRole(['admin']))
                                Администратор
                            @elseif(Auth::user()->hasAnyRole(['manager']))
                                Менеджер
                            @else
                                Турист
                            @endif
                        </div>
                    </div>
                    <i class="bi bi-chevron-down" style="font-size: 0.75rem;"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('cabinet.profile') }}">
                        <i class="bi bi-person me-2"></i> Мой профиль
                    </a></li>
                    <li><a class="dropdown-item" href="{{ route('cabinet.settings') }}">
                        <i class="bi bi-gear me-2"></i> Настройки
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/">
                        <i class="bi bi-house me-2"></i> На главную
                    </a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Выйти
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="cabinet-sidebar" :class="{ 'active': sidebarOpen }">
        <nav class="sidebar-menu">
            @yield('sidebar')
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="cabinet-main">
        <!-- Toast Container -->
        <div class="toast-container">
            @if(session('success'))
                <div class="toast show" role="alert">
                    <div class="toast-header bg-success text-white">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong class="me-auto">Успешно</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="toast show" role="alert">
                    <div class="toast-header bg-danger text-white">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong class="me-auto">Ошибка</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        {{ session('error') }}
                    </div>
                </div>
            @endif
        </div>

        @yield('content')
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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

        // Auto-hide toasts
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
