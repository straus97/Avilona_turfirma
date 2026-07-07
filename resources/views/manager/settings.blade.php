@extends('cabinet.layouts.app')

@section('title', 'Настройки менеджера')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Настройки</h1>
    <p class="page-subtitle">Управление доступом и безопасностью</p>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Безопасность</div>
    </div>
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('cabinet.manager.settings.password') }}">
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

<div class="card-custom mt-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Уведомления</div>
    </div>
    @php
        $settings = json_decode(Auth::user()->notification_settings ?? '{}', true);
    @endphp
    <form method="POST" action="{{ route('cabinet.manager.settings.notifications') }}">
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

<div class="card-custom mt-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Двухфакторная аутентификация</div>
    </div>
    <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background: #f3f4f6; border: 1px solid #e5e7eb;">
        <div>
            <div style="font-weight: 600;">Скоро будет доступно</div>
            <div class="text-muted small">Усиленная защита аккаунта</div>
        </div>
        <span class="badge bg-secondary">Скоро</span>
    </div>
</div>

<div class="card-custom mt-4" style="border-color: #fee2e2;">
    <div class="card-header-custom">
        <div class="card-title-custom text-danger">Удаление аккаунта</div>
    </div>
    <p class="text-muted">Удаление необратимо. Требуется подтверждение паролем.</p>
    <form method="POST" action="{{ route('cabinet.manager.destroy-account') }}" onsubmit="return confirm('Вы уверены, что хотите удалить аккаунт? Это действие необратимо!')">
        @csrf
        @method('DELETE')
        <div class="mb-3">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash"></i> Удалить аккаунт
        </button>
    </form>
</div>
@endsection
