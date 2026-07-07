@extends('cabinet.layouts.app')

@section('title', 'Профиль менеджера')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Профиль менеджера</h1>
    <p class="page-subtitle">Информация об аккаунте</p>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom">Данные аккаунта</div>
            </div>
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('cabinet.manager.profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Имя</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', Auth::user()->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', Auth::user()->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">При смене email потребуется повторное подтверждение.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', Auth::user()->phone) }}" placeholder="+7 (___) ___-__-__">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Дата рождения</label>
                            <input type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', Auth::user()->birth_date) }}">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Паспорт (серия и номер)</label>
                            <input type="text" id="passport_number" name="passport_number" class="form-control @error('passport_number') is-invalid @enderror" value="{{ old('passport_number', Auth::user()->passport_number) }}" placeholder="__ __ ______">
                            @error('passport_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Дата выдачи</label>
                            <input type="date" name="passport_issued_date" class="form-control @error('passport_issued_date') is-invalid @enderror" value="{{ old('passport_issued_date', Auth::user()->passport_issued_date) }}">
                            @error('passport_issued_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Кем выдан</label>
                            <input type="text" name="passport_issued_by" class="form-control @error('passport_issued_by') is-invalid @enderror" value="{{ old('passport_issued_by', Auth::user()->passport_issued_by) }}">
                            @error('passport_issued_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Роль</label>
                            <input type="text" class="form-control" value="Менеджер" disabled>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Сохранить
                </button>
            </form>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Безопасность</div>
            </div>
            <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background: #ecfdf3; border: 1px solid #d1fae5;">
                <div>
                    <div style="font-weight: 600;">Двухфакторная аутентификация</div>
                    <div class="text-muted small">Скоро будет доступно</div>
                </div>
                <span class="badge bg-secondary">Скоро</span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-custom mb-4">
            <div class="text-center">
                @if(Auth::user()->avatar_path)
                    <img src="{{ Storage::url(Auth::user()->avatar_path) }}" alt="avatar" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                @else
                    <div class="user-avatar mx-auto mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
                <h5>{{ Auth::user()->name }}</h5>
                <div class="text-muted small mb-3">{{ Auth::user()->email }}</div>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                    <i class="bi bi-camera"></i> Изменить фото
                </button>
            </div>
        </div>

        <div class="card-custom">
            <h6 class="mb-3">Информация об аккаунте</h6>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Email:</span>
                <span class="fw-bold">{{ Auth::user()->email }}</span>
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
    </div>
</div>

<!-- Модальное окно загрузки аватара -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Изменить фото профиля</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('cabinet.manager.profile.avatar') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <input type="file" name="avatar" class="form-control" accept="image/*" required>
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
