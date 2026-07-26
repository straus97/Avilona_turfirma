@extends('cabinet.layouts.app')

@section('title', 'Настройки')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Настройки</h1>
    <p class="page-subtitle">Управляйте настройками аккаунта и приватностью</p>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Смена пароля -->
        <div class="card-custom mb-4">
            <h5 class="mb-4"><i class="bi bi-shield-lock"></i> Смена пароля</h5>
            
            @if(session('status'))
                @php
                    $statusMessage = session('status');
                    $isPasswordSuccess = str_contains(strtolower($statusMessage), 'пароль') || str_contains(strtolower($statusMessage), 'password');
                @endphp
                @if($isPasswordSuccess)
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <strong>Успешно!</strong> {{ $statusMessage }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            @endif
            
            @if($errors->has('current_password') || $errors->has('password'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Ошибка!</strong> Проверьте правильность введенных данных.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            <form method="POST" action="{{ route('password.update') }}" id="passwordForm">
                @csrf
                @method('PUT')
                
                <div class="mb-3">
                    <label class="form-label">Текущий пароль <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Новый пароль <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Минимум 8 символов</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Подтвердите пароль <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Изменить пароль
                </button>
            </form>
        </div>

        <!-- Уведомления -->
        <div class="card-custom mb-4">
            <h5 class="mb-4"><i class="bi bi-bell"></i> Уведомления</h5>
            
            @if(session('status') && str_contains(session('status'), 'уведомлений'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            <form method="POST" action="{{ route('cabinet.settings.notifications') }}">
                @csrf
                
                @php
                    $settings = json_decode(Auth::user()->notification_settings ?? '{}', true);
                @endphp
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications" 
                               {{ ($settings['email_notifications'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="emailNotifications">
                            Email-уведомления
                        </label>
                    </div>
                    <small class="text-muted ms-4">Получать уведомления на почту</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="bookingUpdates" name="booking_updates" 
                               {{ ($settings['booking_updates'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="bookingUpdates">
                            Изменения в заявках
                        </label>
                    </div>
                    <small class="text-muted ms-4">Уведомления об изменении статуса заявки</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="newMessages" name="new_messages" 
                               {{ ($settings['new_messages'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="newMessages">
                            Новые сообщения
                        </label>
                    </div>
                    <small class="text-muted ms-4">Уведомления о новых сообщениях от менеджера</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="tripReminders" name="trip_reminders" 
                               {{ ($settings['trip_reminders'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="tripReminders">
                            Напоминания о поездках
                        </label>
                    </div>
                    <small class="text-muted ms-4">Напоминания за 14 и 7 дней, за 3 дня и за 1 день до вылета</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="promotions" name="promotions" 
                               {{ ($settings['promotions'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="promotions">
                            Акции и специальные предложения
                        </label>
                    </div>
                    <small class="text-muted ms-4">Рассылка о скидках и акциях</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Сохранить настройки
                </button>
            </form>
        </div>

        <!-- Опасная зона -->
        <div class="card-custom" style="border-color: var(--danger-color);">
            <h5 class="mb-4 text-danger"><i class="bi bi-exclamation-triangle"></i> Опасная зона</h5>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6>Удалить аккаунт</h6>
                    <p class="text-muted small mb-0">Это действие нельзя отменить. Все ваши данные будут удалены.</p>
                </div>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    Удалить аккаунт
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Информация об аккаунте -->
        <div class="card-custom mb-4">
            <h6 class="mb-3">Информация об аккаунте</h6>
            <div class="mb-2">
                <div class="text-muted small">Email</div>
                <div class="fw-bold">{{ Auth::user()->email }}</div>
                @if(Auth::user()->email_verified_at)
                    <span class="badge bg-success">✓ Подтвержден</span>
                @else
                    <span class="badge bg-warning">Не подтвержден</span>
                @endif
            </div>
            <hr>
            <div class="mb-2">
                <div class="text-muted small">Дата регистрации</div>
                <div>{{ Auth::user()->created_at ? Auth::user()->created_at->format('d.m.Y') : 'Не указана' }}</div>
            </div>
            <hr>
            <div>
                <div class="text-muted small">Последний вход</div>
                <div>{{ Auth::user()->last_login_at ? Auth::user()->last_login_at->format('d.m.Y H:i') : (Auth::user()->updated_at ? Auth::user()->updated_at->format('d.m.Y H:i') : 'Неизвестно') }}</div>
            </div>
        </div>

        <!-- Безопасность -->
        <div class="card-custom">
            <h6 class="mb-3">Безопасность</h6>
            <div class="alert alert-success small">
                <i class="bi bi-shield-check"></i> Ваш аккаунт защищен
            </div>
            <div class="d-grid">
                <button class="btn btn-outline-primary btn-sm mb-2" disabled>
                    <i class="bi bi-key"></i> Двухфакторная аутентификация
                    <span class="badge bg-secondary">Скоро</span>
                </button>
                <a href="{{ route('password.request') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat"></i> Сбросить пароль
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно удаления аккаунта -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Удалить аккаунт</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('cabinet.settings.destroy-account') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить свой аккаунт?</p>
                    <p class="text-danger"><strong>Это действие нельзя отменить!</strong></p>
                    <p>Будут удалены:</p>
                    <ul>
                        <li>Все ваши заявки</li>
                        <li>Личные документы</li>
                        <li>История сообщений</li>
                        <li>Бонусные баллы</li>
                    </ul>
                    
                    <div class="mt-3">
                        <label class="form-label">Введите ваш пароль для подтверждения</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-danger">Удалить аккаунт</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
