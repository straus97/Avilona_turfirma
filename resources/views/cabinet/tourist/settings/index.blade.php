@extends('cabinet.layouts.app')

@section('title', 'Настройки')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
@php
    $settings = json_decode(Auth::user()->notification_settings ?? '{}', true) ?: [];
@endphp

<div class="page-header">
    <h1 class="page-title">Настройки</h1>
    <p class="page-subtitle">Пароль, уведомления и управление аккаунтом</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Смена пароля --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom"><i class="bi bi-shield-lock" aria-hidden="true"></i> Смена пароля</h2>
            </div>

            <form method="POST" action="{{ route('password.update') }}" id="passwordForm">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">Текущий пароль <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" id="current_password" autocomplete="current-password"
                           class="form-control @error('current_password') is-invalid @enderror" required>
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Новый пароль <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="password" autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror" required aria-describedby="password-hint">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" id="password-hint">Минимум 8 символов.</div>
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Подтвердите новый пароль <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
                           class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Изменить пароль
                </button>
            </form>
        </div>

        {{-- Уведомления --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom"><i class="bi bi-bell" aria-hidden="true"></i> Уведомления</h2>
            </div>

            <form method="POST" action="{{ route('cabinet.settings.notifications') }}">
                @csrf

                <div class="tc-switch">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications"
                               {{ ($settings['email_notifications'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="emailNotifications">Email-уведомления</label>
                    </div>
                    <p class="tc-switch__hint">Получать уведомления на почту</p>
                </div>

                <div class="tc-switch">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="bookingUpdates" name="booking_updates"
                               {{ ($settings['booking_updates'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="bookingUpdates">Изменения в заявках</label>
                    </div>
                    <p class="tc-switch__hint">Уведомления об изменении статуса заявки</p>
                </div>

                <div class="tc-switch">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="newMessages" name="new_messages"
                               {{ ($settings['new_messages'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="newMessages">Новые сообщения</label>
                    </div>
                    <p class="tc-switch__hint">Уведомления о новых сообщениях от менеджера</p>
                </div>

                <div class="tc-switch">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="tripReminders" name="trip_reminders"
                               {{ ($settings['trip_reminders'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="tripReminders">Напоминания о поездках</label>
                    </div>
                    <p class="tc-switch__hint">Напоминания за 14 и 7 дней, за 3 дня и за 1 день до вылета</p>
                </div>

                <div class="tc-switch">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="promotions" name="promotions"
                               {{ ($settings['promotions'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="promotions">Акции и специальные предложения</label>
                    </div>
                    <p class="tc-switch__hint">Рассылка о скидках и акциях</p>
                </div>

                <button type="submit" class="btn btn-primary mt-2">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Сохранить настройки
                </button>
            </form>
        </div>

        {{-- Опасная зона --}}
        <div class="card-custom tc-danger-zone">
            <div class="card-header-custom">
                <h2 class="card-title-custom text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Опасная зона</h2>
            </div>
            <div class="tc-danger-zone__row">
                <div>
                    <h3 style="font-size: 1rem; font-weight: 600;">Удалить аккаунт</h3>
                    <p class="text-muted small mb-0">Действие необратимо. Все ваши данные будут удалены.</p>
                </div>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    Удалить аккаунт
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Информация об аккаунте --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom">Аккаунт</h2>
            </div>
            <div class="tc-info-list">
                <div class="tc-info-list__row">
                    <span class="tc-info-list__label">Email</span>
                    <span class="tc-info-list__value">{{ Auth::user()->email }}</span>
                    <span>
                        @if(Auth::user()->email_verified_at)
                            <span class="status-badge status-confirmed"><i class="bi bi-check-circle" aria-hidden="true"></i> Подтверждён</span>
                        @else
                            <span class="status-badge status-progress"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Не подтверждён</span>
                        @endif
                    </span>
                </div>
                <div class="tc-info-list__row">
                    <span class="tc-info-list__label">Дата регистрации</span>
                    <span class="tc-info-list__value">{{ Auth::user()->created_at ? Auth::user()->created_at->format('d.m.Y') : '—' }}</span>
                </div>
                <div class="tc-info-list__row">
                    <span class="tc-info-list__label">Последний вход</span>
                    <span class="tc-info-list__value">
                        {{ Auth::user()->last_login_at
                            ? Auth::user()->last_login_at->format('d.m.Y H:i')
                            : (Auth::user()->updated_at ? Auth::user()->updated_at->format('d.m.Y H:i') : 'неизвестно') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Безопасность --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom">Безопасность</h2>
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="bi bi-key" aria-hidden="true"></i> Двухфакторная аутентификация
                    <span class="badge bg-secondary">Скоро</span>
                </button>
                <a href="{{ route('password.request') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Восстановить пароль по email
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно удаления аккаунта -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 text-danger" id="deleteAccountTitle">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Удаление аккаунта
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form method="POST" action="{{ route('cabinet.settings.destroy-account') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p>Вы действительно хотите удалить аккаунт? <strong class="text-danger">Это действие нельзя отменить.</strong></p>
                    <p class="mb-1">Будут удалены:</p>
                    <ul>
                        <li>все ваши заявки;</li>
                        <li>личные документы;</li>
                        <li>история сообщений;</li>
                        <li>бонусные баллы.</li>
                    </ul>
                    <div class="mt-3">
                        <label for="delete-password" class="form-label">Введите пароль для подтверждения</label>
                        <input type="password" name="password" id="delete-password" autocomplete="current-password"
                               class="form-control" required>
                        <div class="form-text">Если пароль неверный, вверху страницы появится сообщение об ошибке.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-danger">Удалить аккаунт</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
