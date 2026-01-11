@extends('cabinet.layouts.app')

@section('title', 'Мой профиль')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Мой профиль</h1>
    <p class="page-subtitle">Управляйте своей личной информацией</p>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Основная информация -->
        <div class="card-custom mb-4">
            <h5 class="mb-4"><i class="bi bi-person-circle"></i> Основная информация</h5>
            <form method="POST" action="{{ route('cabinet.profile.update') }}">
                @csrf
                @method('PATCH')
                
                <div class="mb-3">
                    <label class="form-label">Полное имя <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name', Auth::user()->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                           value="{{ old('email', Auth::user()->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Телефон</label>
                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                           value="{{ old('phone', Auth::user()->phone) }}" placeholder="+7 (___) ___-__-__">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Дата рождения</label>
                        <input type="date" name="birth_date" class="form-control" 
                               value="{{ old('birth_date', Auth::user()->birth_date) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Пол</label>
                        <select name="gender" class="form-select">
                            <option value="">Не указано</option>
                            <option value="male" {{ old('gender', Auth::user()->gender) == 'male' ? 'selected' : '' }}>Мужской</option>
                            <option value="female" {{ old('gender', Auth::user()->gender) == 'female' ? 'selected' : '' }}>Женский</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Адрес</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address', Auth::user()->address) }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Сохранить
                    </button>
                    <a href="{{ route('cabinet.dashboard') }}" class="btn btn-outline-secondary">
                        Отмена
                    </a>
                </div>
            </form>
        </div>

        <!-- Паспортные данные -->
        <div class="card-custom">
            <h5 class="mb-4"><i class="bi bi-person-badge"></i> Паспортные данные</h5>
            <p class="text-muted small mb-3">Эти данные используются для быстрого оформления заявок</p>
            
            <form method="POST" action="{{ route('cabinet.profile.update-passport') }}">
                @csrf
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Серия и номер паспорта</label>
                        <input type="text" name="passport_number" class="form-control" 
                               placeholder="__ __ ______">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Дата выдачи</label>
                        <input type="date" name="passport_issued_date" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Кем выдан</label>
                    <input type="text" name="passport_issued_by" class="form-control">
                </div>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Внимание!</strong> Рекомендуем загружать скан паспорта в разделе "Мои документы" вместо ручного ввода.
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Сохранить
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Аватар -->
        <div class="card-custom mb-4">
            <div class="text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <h5>{{ Auth::user()->name }}</h5>
                <div class="text-muted small mb-3">{{ Auth::user()->email }}</div>
                
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                    <i class="bi bi-camera"></i> Изменить фото
                </button>
            </div>
        </div>

        <!-- Статистика -->
        <div class="card-custom">
            <h6 class="mb-3">Статистика</h6>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Регистрация:</span>
                <span class="fw-bold">{{ Auth::user()->created_at->format('d.m.Y') }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Заявок:</span>
                <span class="fw-bold">{{ Auth::user()->bookings->count() }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Поездок:</span>
                <span class="fw-bold">{{ Auth::user()->bookings->where('status', 'completed')->count() }}</span>
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
            <form action="{{ route('cabinet.profile.upload-avatar') }}" method="POST" enctype="multipart/form-data">
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
@endsection
