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
            <form method="POST" action="{{ route('password.update') }}">
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
            <form method="POST" action="{{ route('cabinet.settings.notifications') }}">
                @csrf
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications" checked>
                        <label class="form-check-label" for="emailNotifications">
                            Email-уведомления
                        </label>
                    </div>
                    <small class="text-muted ms-4">Получать уведомления на почту</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="bookingUpdates" name="booking_updates" checked>
                        <label class="form-check-label" for="bookingUpdates">
                            Изменения в заявках
                        </label>
                    </div>
                    <small class="text-muted ms-4">Уведомления об изменении статуса заявки</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="newMessages" name="new_messages" checked>
                        <label class="form-check-label" for="newMessages">
                            Новые сообщения
                        </label>
                    </div>
                    <small class="text-muted ms-4">Уведомления о новых сообщениях от менеджера</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="tripReminders" name="trip_reminders" checked>
                        <label class="form-check-label" for="tripReminders">
                            Напоминания о поездках
                        </label>
                    </div>
                    <small class="text-muted ms-4">Напоминание за 7 дней до вылета</small>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="promotions" name="promotions">
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

        <!-- Приватность -->
        <div class="card-custom mb-4">
            <h5 class="mb-4"><i class="bi bi-eye-slash"></i> Приватность</h5>
            
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="showInReviews">
                    <label class="form-check-label" for="showInReviews">
                        Показывать мое имя в отзывах
                    </label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="allowMarketing">
                    <label class="form-check-label" for="allowMarketing">
                        Согласие на обработку персональных данных для маркетинга
                    </label>
                </div>
            </div>
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
                <div>{{ Auth::user()->created_at->format('d.m.Y') }}</div>
            </div>
            <hr>
            <div>
                <div class="text-muted small">Последний вход</div>
                <div>{{ Auth::user()->updated_at->format('d.m.Y H:i') }}</div>
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
            <form method="POST" action="{{ route('profile.destroy') }}">
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
