@extends('cabinet.layouts.app')

@section('title', 'Мой профиль')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Мой профиль</h1>
    <p class="page-subtitle">Контактные и паспортные данные для оформления заявок</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Основная информация --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom"><i class="bi bi-person-circle" aria-hidden="true"></i> Основная информация</h2>
            </div>

            <form method="POST" action="{{ route('cabinet.profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="pf-name" class="form-label">Полное имя <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="pf-name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', Auth::user()->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="pf-email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="pf-email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', Auth::user()->email) }}" required aria-describedby="pf-email-hint">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" id="pf-email-hint">
                        При смене email потребуется подтвердить новый адрес и войти заново.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Телефон</label>
                    <input type="tel" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', Auth::user()->phone) }}" placeholder="+7 (___) ___-__-__">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <label for="pf-birth" class="form-label">Дата рождения</label>
                        <input type="date" name="birth_date" id="pf-birth" class="form-control @error('birth_date') is-invalid @enderror"
                               value="{{ old('birth_date', Auth::user()->birth_date) }}">
                        @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label for="pf-gender" class="form-label">Пол</label>
                        <select name="gender" id="pf-gender" class="form-select @error('gender') is-invalid @enderror">
                            <option value="">Не указано</option>
                            <option value="male" {{ old('gender', Auth::user()->gender) === 'male' ? 'selected' : '' }}>Мужской</option>
                            <option value="female" {{ old('gender', Auth::user()->gender) === 'female' ? 'selected' : '' }}>Женский</option>
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="pf-address" class="form-label">Адрес</label>
                    <textarea name="address" id="pf-address" class="form-control @error('address') is-invalid @enderror" rows="2">{{ old('address', Auth::user()->address) }}</textarea>
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle" aria-hidden="true"></i> Сохранить
                    </button>
                    <a href="{{ route('cabinet.dashboard') }}" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </form>
        </div>

        {{-- Паспортные данные --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom"><i class="bi bi-person-badge" aria-hidden="true"></i> Паспортные данные</h2>
            </div>
            <p class="text-muted small">Используются для быстрого оформления заявок. Заполнять необязательно.</p>

            <form method="POST" action="{{ route('cabinet.profile.update-passport') }}">
                @csrf

                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <label for="passport_number" class="form-label">Серия и номер паспорта</label>
                        <input type="text" name="passport_number" id="passport_number" class="form-control @error('passport_number') is-invalid @enderror"
                               value="{{ old('passport_number', Auth::user()->passport_number) }}" placeholder="__ __ ______">
                        @error('passport_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label for="passport_issued_date" class="form-label">Дата выдачи</label>
                        <input type="date" name="passport_issued_date" id="passport_issued_date" class="form-control @error('passport_issued_date') is-invalid @enderror"
                               value="{{ old('passport_issued_date', Auth::user()->passport_issued_date) }}">
                        @error('passport_issued_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="passport_issued_by" class="form-label">Кем выдан</label>
                    <input type="text" name="passport_issued_by" id="passport_issued_by" class="form-control @error('passport_issued_by') is-invalid @enderror"
                           value="{{ old('passport_issued_by', Auth::user()->passport_issued_by) }}">
                    @error('passport_issued_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="tc-notice mb-3">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    <span>Надёжнее загрузить скан паспорта в разделе
                        <a href="{{ route('cabinet.documents.personal') }}">«Мои документы»</a>,
                        чем вводить данные вручную.</span>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Сохранить паспортные данные
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Аватар --}}
        <div class="card-custom text-center">
            @if(Auth::user()->avatar_path)
                <img src="{{ Storage::url(Auth::user()->avatar_path) }}" alt="Фото профиля" class="rounded-circle mb-3"
                     style="width: 112px; height: 112px; object-fit: cover;">
            @else
                <div class="user-avatar mx-auto mb-3" style="width: 112px; height: 112px; font-size: 2.75rem;" aria-hidden="true">
                    {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                </div>
            @endif
            <h2 style="font-size: 1.05rem; font-weight: 600;">{{ Auth::user()->name }}</h2>
            <div class="text-muted small mb-3">{{ Auth::user()->email }}</div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                <i class="bi bi-camera" aria-hidden="true"></i> Изменить фото
            </button>
        </div>

        {{-- Сводка --}}
        <div class="card-custom">
            <div class="card-header-custom">
                <h2 class="card-title-custom">Ваш профиль</h2>
            </div>
            <div class="tc-info-list">
                <div class="tc-info-list__row">
                    <span class="tc-info-list__label">Регистрация</span>
                    <span class="tc-info-list__value">{{ Auth::user()->created_at ? Auth::user()->created_at->format('d.m.Y') : '—' }}</span>
                </div>
                <div class="tc-info-list__row">
                    <span class="tc-info-list__label">Заявок</span>
                    <span class="tc-info-list__value">{{ Auth::user()->bookings->count() }}</span>
                </div>
                <div class="tc-info-list__row">
                    <span class="tc-info-list__label">Завершённых поездок</span>
                    <span class="tc-info-list__value">{{ Auth::user()->bookings->where('status', 'completed')->count() }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно загрузки аватара -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="avatarModalTitle">Изменить фото профиля</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form action="{{ route('cabinet.profile.upload-avatar') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <label for="avatar-file" class="form-label">Файл изображения</label>
                    <input type="file" name="avatar" id="avatar-file" class="form-control @error('avatar') is-invalid @enderror" accept="image/*" required>
                    <div class="form-text">Максимум 2 МБ. Форматы: JPG, PNG.</div>
                    @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload" aria-hidden="true"></i> Загрузить
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
