@extends('cabinet.layouts.app')

@section('title', 'Системные настройки')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Системные настройки</h1>
    <p class="page-subtitle">Состояние системы и управление кэшем</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Информация о системе</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <tr>
                <th width="30%">PHP версия:</th>
                <td>{{ $systemInfo['php_version'] }}</td>
            </tr>
            <tr>
                <th>Laravel версия:</th>
                <td>{{ $systemInfo['laravel_version'] }}</td>
            </tr>
            <tr>
                <th>Окружение:</th>
                <td>
                    <span class="badge {{ $systemInfo['environment'] === 'production' ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $systemInfo['environment'] }}
                    </span>
                </td>
            </tr>
            <tr>
                <th>Режим отладки:</th>
                <td>
                    <span class="badge {{ $systemInfo['debug_mode'] ? 'bg-danger' : 'bg-success' }}">
                        {{ $systemInfo['debug_mode'] ? 'Включен' : 'Выключен' }}
                    </span>
                </td>
            </tr>
            <tr>
                <th>Драйвер кэша:</th>
                <td>{{ $systemInfo['cache_driver'] }}</td>
            </tr>
            <tr>
                <th>Драйвер сессий:</th>
                <td>{{ $systemInfo['session_driver'] }}</td>
            </tr>
            <tr>
                <th>Драйвер очередей:</th>
                <td>{{ $systemInfo['queue_driver'] }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Управление кэшем</div>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Очистка кэша обновляет страницы, конфигурацию и маршруты после изменений. Полезно при обновлениях и сбоях отображения.
        </p>
        <div class="d-flex align-items-center gap-3 mb-3">
            <div><strong>Статус:</strong></div>
            @if($cacheStats['enabled'])
                <span class="badge bg-success">Включен</span>
            @else
                <span class="badge bg-warning text-dark">Отключен</span>
            @endif
            <div class="text-muted">Драйвер: {{ $cacheStats['driver'] }}</div>
        </div>
        <form action="{{ route('cabinet.admin.clear-cache') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-trash"></i> Очистить кэш
            </button>
        </form>
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Безопасность</div>
    </div>
    <form method="POST" action="{{ route('cabinet.admin.settings.password') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Текущий пароль</label>
            <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
            @error('current_password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Новый пароль</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Минимум 8 символов</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Подтверждение пароля</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-key"></i> Сменить пароль
        </button>
    </form>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Уведомления</div>
    </div>
    @php
        $settings = json_decode(Auth::user()->notification_settings ?? '{}', true);
    @endphp
    <form method="POST" action="{{ route('cabinet.admin.settings.notifications') }}">
        @csrf
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" {{ ($settings['email_notifications'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="email_notifications">Email-уведомления</label>
        </div>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="booking_updates" id="booking_updates" {{ ($settings['booking_updates'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="booking_updates">Изменения по заявкам</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="new_messages" id="new_messages" {{ ($settings['new_messages'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="new_messages">Новые сообщения</label>
        </div>
        <button type="submit" class="btn btn-outline-primary">
            <i class="bi bi-check-circle"></i> Сохранить
        </button>
    </form>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Быстрые команды</div>
    </div>
    <div class="card-body">
        <p class="text-muted">Для выполнения этих команд используйте терминал на сервере:</p>
        <ul class="mb-0">
            <li><code>php artisan cache:clear</code> — Очистить кэш приложения</li>
            <li><code>php artisan config:clear</code> — Очистить кэш конфигурации</li>
            <li><code>php artisan route:clear</code> — Очистить кэш маршрутов</li>
            <li><code>php artisan view:clear</code> — Очистить кэш представлений</li>
            <li><code>php artisan optimize</code> — Оптимизировать приложение</li>
        </ul>
    </div>
</div>
@endsection
