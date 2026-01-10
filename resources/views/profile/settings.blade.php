@extends('layouts.profile')

@section('title', 'Настройки профиля - Авилона')

@section('content')
<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-user-cog text-primary"></i> Настройки профиля
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.index') }}">Главная</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Личный кабинет</a></li>
                    <li class="breadcrumb-item active">Настройки</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">

        @if(session('status') === 'profile-updated')
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-check-circle"></i> Профиль успешно обновлен!
            </div>
        @endif

        @if(session('status') === 'password-updated')
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-check-circle"></i> Пароль успешно изменен!
            </div>
        @endif

        <div class="row">
            <!-- Личная информация -->
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i> Личная информация
                        </h3>
                    </div>
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')
                        
                        <div class="card-body">
                            <!-- Имя -->
                            <div class="form-group">
                                <label for="name">
                                    Имя <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       value="{{ old('name', $user->name) }}" 
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="form-group">
                                <label for="email">
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       id="email" 
                                       name="email" 
                                       value="{{ old('email', $user->email) }}" 
                                       required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                                    <small class="form-text text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Ваш email не подтвержден.
                                        <a href="{{ route('verification.send') }}">Отправить письмо повторно</a>
                                    </small>
                                @endif
                            </div>

                            <!-- Телефон -->
                            <div class="form-group">
                                <label for="phone">Телефон</label>
                                <input type="tel" 
                                       class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" 
                                       name="phone" 
                                       value="{{ old('phone', $user->phone) }}" 
                                       placeholder="+7 (999) 123-45-67">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Сохранить изменения
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Смена пароля -->
            <div class="col-md-6">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-key"></i> Смена пароля
                        </h3>
                    </div>
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="card-body">
                            <!-- Текущий пароль -->
                            <div class="form-group">
                                <label for="current_password">
                                    Текущий пароль <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" 
                                       id="current_password" 
                                       name="current_password" 
                                       required>
                                @error('current_password', 'updatePassword')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Новый пароль -->
                            <div class="form-group">
                                <label for="password">
                                    Новый пароль <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control @error('password', 'updatePassword') is-invalid @enderror" 
                                       id="password" 
                                       name="password" 
                                       required>
                                @error('password', 'updatePassword')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Минимум 8 символов
                                </small>
                            </div>

                            <!-- Подтверждение пароля -->
                            <div class="form-group">
                                <label for="password_confirmation">
                                    Подтвердите пароль <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation" 
                                       required>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-lock"></i> Изменить пароль
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Удаление аккаунта -->
        <div class="row">
            <div class="col-12">
                <div class="card card-danger card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> Опасная зона
                        </h3>
                    </div>
                    <div class="card-body">
                        <h5>Удалить аккаунт</h5>
                        <p class="text-muted">
                            После удаления аккаунта все ваши данные будут безвозвратно удалены. 
                            Пожалуйста, загрузите все необходимые данные перед удалением аккаунта.
                        </p>
                        <button type="button" 
                                class="btn btn-danger" 
                                data-toggle="modal" 
                                data-target="#deleteAccountModal">
                            <i class="fas fa-trash-alt"></i> Удалить аккаунт
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal удаления аккаунта -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fas fa-exclamation-triangle"></i> Подтверждение удаления
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('profile.destroy') }}">
                @csrf
                @method('DELETE')
                
                <div class="modal-body">
                    <p><strong>Вы уверены, что хотите удалить свой аккаунт?</strong></p>
                    <p class="text-muted">Это действие необратимо. Все ваши данные будут удалены.</p>
                    
                    <div class="form-group">
                        <label for="password_delete">
                            Введите ваш пароль для подтверждения
                        </label>
                        <input type="password" 
                               class="form-control @error('password', 'userDeletion') is-invalid @enderror" 
                               id="password_delete" 
                               name="password" 
                               required>
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        Отмена
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Удалить аккаунт
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.card {
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}
</style>
@endpush
