@extends('layouts.main')

@section('title', 'Смена пароля - Авилона')

@section('content')
<main>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">
                            <i class="bi bi-shield-lock"></i> Требуется смена пароля
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Добро пожаловать!</strong><br>
                            Вы используете временный пароль. Пожалуйста, придумайте свой собственный пароль для безопасности вашего аккаунта.
                        </div>

                        @if(session('warning'))
                            <div class="alert alert-warning">
                                {{ session('warning') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.change.update') }}">
                            @csrf

                            <!-- Текущий пароль -->
                            <div class="mb-3">
                                <label for="current_password" class="form-label">
                                    Текущий (временный) пароль <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control @error('current_password') is-invalid @enderror" 
                                       id="current_password" 
                                       name="current_password" 
                                       required 
                                       autofocus>
                                @error('current_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Введите временный пароль, который вы получили на email
                                </small>
                            </div>

                            <!-- Новый пароль -->
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Новый пароль <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control @error('password') is-invalid @enderror" 
                                       id="password" 
                                       name="password" 
                                       required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Минимум 8 символов
                                </small>
                            </div>

                            <!-- Подтверждение пароля -->
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">
                                    Подтвердите новый пароль <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirmation" 
                                       name="password_confirmation" 
                                       required>
                                <small class="form-text text-muted">
                                    Введите пароль еще раз
                                </small>
                            </div>

                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i>
                                После смены пароля:
                                <ul class="mb-0 mt-2">
                                    <li>Ваш email будет автоматически подтвержден</li>
                                    <li>Вы получите полный доступ к личному кабинету</li>
                                    <li>Ваши заявки будут доступны в разделе "Мои заявки"</li>
                                </ul>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Сменить пароль
                                </button>
                                <a href="{{ route('logout') }}" 
                                   class="btn btn-outline-secondary"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right"></i> Выйти
                                </a>
                            </div>
                        </form>

                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
