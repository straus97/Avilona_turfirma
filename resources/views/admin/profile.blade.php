@extends('cabinet.layouts.app')

@section('title', 'Профиль администратора')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Профиль администратора</h1>
    <p class="page-subtitle">Личные данные и безопасность</p>
</div>

<div class="admin-profile-layout">
    <div class="card-custom admin-profile-layout__account">
        <div class="card-header-custom">
            <div class="card-title-custom">Данные аккаунта</div>
        </div>
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('cabinet.admin.profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-name">Имя</label>
                            <input type="text" id="admin-profile-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', Auth::user()->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-email">Email</label>
                            <input type="email" id="admin-profile-email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', Auth::user()->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">При смене email потребуется повторное подтверждение.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Телефон</label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', Auth::user()->phone) }}" placeholder="+7 (___) ___-__-__">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-birth-date">Дата рождения</label>
                            <input type="date" id="admin-profile-birth-date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', Auth::user()->birth_date) }}">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="passport_number">Паспорт (серия и номер)</label>
                            <input type="text" id="passport_number" name="passport_number" class="form-control @error('passport_number') is-invalid @enderror" value="{{ old('passport_number', Auth::user()->passport_number) }}" placeholder="__ __ ______">
                            @error('passport_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-passport-issued-date">Дата выдачи</label>
                            <input type="date" id="admin-profile-passport-issued-date" name="passport_issued_date" class="form-control @error('passport_issued_date') is-invalid @enderror" value="{{ old('passport_issued_date', Auth::user()->passport_issued_date) }}">
                            @error('passport_issued_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-passport-issued-by">Кем выдан</label>
                            <input type="text" id="admin-profile-passport-issued-by" name="passport_issued_by" class="form-control @error('passport_issued_by') is-invalid @enderror" value="{{ old('passport_issued_by', Auth::user()->passport_issued_by) }}">
                            @error('passport_issued_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-role">Роль</label>
                            <input type="text" id="admin-profile-role" class="form-control" value="Администратор" disabled>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Сохранить
                </button>
            </form>
        </div>

        <div class="card-custom admin-profile-layout__avatar">
            <div class="text-center">
                @if(Auth::user()->avatar_path)
                    <img src="{{ Storage::url(Auth::user()->avatar_path) }}" alt="avatar" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                @else
                    <div class="user-avatar mx-auto mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                        {{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
                <h5>{{ Auth::user()->name }}</h5>
                <div class="text-muted small mb-3 text-break">{{ Auth::user()->email }}</div>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                    <i class="bi bi-camera"></i> Изменить фото
                </button>
            </div>
        </div>

        <div class="card-custom admin-profile-layout__info">
            <h6 class="mb-3">Информация об аккаунте</h6>
            <div class="d-flex justify-content-between gap-2 mb-2">
                <span class="text-muted">Email:</span>
                <span class="fw-bold text-break text-end">{{ Auth::user()->email }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Подтвержден:</span>
                @if(Auth::user()->email_verified_at)
                    <span class="badge bg-success">Да</span>
                @else
                    <span class="badge bg-warning text-dark">Нет</span>
                @endif
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Последний вход:</span>
                <span class="fw-bold">{{ Auth::user()->last_login_at ? Auth::user()->last_login_at->format('d.m.Y H:i') : 'Неизвестно' }}</span>
            </div>
        </div>

        <div class="card-custom admin-profile-layout__security">
            <div class="card-header-custom">
                <div class="card-title-custom">Безопасность</div>
            </div>
            <form method="POST" action="{{ route('cabinet.admin.settings.password') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="admin-profile-current-password">Текущий пароль</label>
                    <input type="password" id="admin-profile-current-password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="admin-profile-new-password">Новый пароль</label>
                    <input type="password" id="admin-profile-new-password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Минимум 8 символов</small>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="admin-profile-password-confirmation">Подтверждение пароля</label>
                    <input type="password" id="admin-profile-password-confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-key"></i> Сменить пароль
                </button>
            </form>

            <div class="d-flex align-items-center justify-content-between p-3 rounded mt-4" style="background: #ecfdf3; border: 1px solid #d1fae5;">
                <div>
                    <div style="font-weight: 600;">Двухфакторная аутентификация</div>
                    <div class="text-muted small">Скоро будет доступно</div>
                </div>
                <span class="badge bg-secondary">Скоро</span>
            </div>
        </div>

        <div class="card-custom admin-profile-layout__notifications">
            <div class="card-header-custom">
                <div class="card-title-custom">Уведомления</div>
            </div>
            @php
                $notificationSettings = json_decode(Auth::user()->notification_settings ?? '{}', true);
            @endphp
            <form method="POST" action="{{ route('cabinet.admin.settings.notifications') }}">
                @csrf
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" {{ ($notificationSettings['email_notifications'] ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="email_notifications">Email-уведомления</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="booking_updates" id="booking_updates" {{ ($notificationSettings['booking_updates'] ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="booking_updates">Изменения по заявкам</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="new_messages" id="new_messages" {{ ($notificationSettings['new_messages'] ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="new_messages">Новые сообщения</label>
                </div>
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-check-circle"></i> Сохранить
                </button>
            </form>
        </div>

        <div class="card-custom admin-profile-layout__delete" style="border-color: #fee2e2;">
            <div class="card-header-custom">
                <div class="card-title-custom text-danger">Удаление аккаунта</div>
            </div>
            <p class="text-muted">Удаление необратимо. Требуется подтверждение паролем.</p>
            <form method="POST" action="{{ route('cabinet.admin.destroy-account') }}" onsubmit="return confirm('Вы уверены, что хотите удалить аккаунт? Это действие необратимо!')">
                @csrf
                @method('DELETE')
                <div class="mb-3">
                    <label class="form-label" for="admin-profile-delete-password">Пароль</label>
                    <input type="password" id="admin-profile-delete-password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Удалить аккаунт
                </button>
            </form>
        </div>
</div>

<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Изменить фото профиля</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('cabinet.admin.profile.avatar') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <label class="form-label" for="admin-profile-avatar">Файл фотографии</label>
                    <input type="file" id="admin-profile-avatar" name="avatar" class="form-control" accept="image/*" required>
                    <small class="text-muted">Максимум 2 МБ. Форматы: JPG, PNG</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Загрузить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/imask@6.4.3/dist/imask.min.js"></script>
<script>
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        IMask(phoneInput, { mask: '+{7} (000) 000-00-00' });
    }

    const passportInput = document.getElementById('passport_number');
    if (passportInput) {
        IMask(passportInput, { mask: '00 00 000000' });
    }
</script>
@endpush
@endsection
