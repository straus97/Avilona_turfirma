@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Настройки профиля</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            @if (session('status') === 'profile-updated')
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="bi bi-check-circle"></i> Профиль успешно обновлен!
                </div>
            @endif

            <div class="row">
                <!-- Обновление информации профиля -->
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-person"></i> Информация профиля
                            </h3>
                        </div>
                        <form method="post" action="{{ route('profile.update') }}">
                            @csrf
                            @method('patch')
                            
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="name">Имя <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $user->name) }}" 
                                           required 
                                           autofocus>
                                    @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Ваше полное имя</small>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email <span class="text-danger">*</span></label>
                                    <input type="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           id="email" 
                                           name="email" 
                                           value="{{ old('email', $user->email) }}" 
                                           required>
                                    @error('email')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                        <div class="mt-2">
                                            <p class="text-sm text-warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                Ваш email не подтвержден.
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="phone">Телефон</label>
                                    <input type="tel" 
                                           class="form-control @error('phone') is-invalid @enderror" 
                                           id="phone" 
                                           name="phone" 
                                           value="{{ old('phone', $user->phone) }}" 
                                           placeholder="+7 (XXX) XXX-XX-XX">
                                    @error('phone')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Контактный телефон для связи</small>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Сохранить изменения
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Обновление пароля -->
                <div class="col-md-6">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-shield-lock"></i> Изменить пароль
                            </h3>
                        </div>
                        <form method="post" action="{{ route('password.update') }}">
                            @csrf
                            @method('put')
                            
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="current_password">Текущий пароль <span class="text-danger">*</span></label>
                                    <input type="password" 
                                           class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" 
                                           id="current_password" 
                                           name="current_password" 
                                           required>
                                    @error('current_password', 'updatePassword')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="password">Новый пароль <span class="text-danger">*</span></label>
                                    <input type="password" 
                                           class="form-control @error('password', 'updatePassword') is-invalid @enderror" 
                                           id="password" 
                                           name="password" 
                                           required>
                                    @error('password', 'updatePassword')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Минимум 8 символов</small>
                                </div>

                                <div class="form-group">
                                    <label for="password_confirmation">Подтвердите пароль <span class="text-danger">*</span></label>
                                    <input type="password" 
                                           class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror" 
                                           id="password_confirmation" 
                                           name="password_confirmation" 
                                           required>
                                    @error('password_confirmation', 'updatePassword')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                @if (session('status') === 'password-updated')
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i> Пароль успешно изменен!
                                    </div>
                                @endif
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-key"></i> Изменить пароль
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Удаление аккаунта -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-danger collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-exclamation-triangle"></i> Удалить аккаунт
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-danger">
                                <strong>Внимание!</strong> После удаления аккаунта все ваши данные будут безвозвратно удалены. 
                                Перед удалением рекомендуем сохранить все важные документы и информацию.
                            </p>
                            
                            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteAccountModal">
                                <i class="bi bi-trash"></i> Удалить аккаунт
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Модальное окно подтверждения удаления -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Подтверждение удаления
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post" action="{{ route('profile.destroy') }}">
                    @csrf
                    @method('delete')
                    
                    <div class="modal-body">
                        <p>Вы уверены, что хотите удалить свой аккаунт?</p>
                        <p class="text-danger">
                            <strong>Это действие необратимо!</strong> Все ваши данные, заявки и история будут удалены.
                        </p>
                        
                        <div class="form-group">
                            <label for="password_delete">Введите ваш пароль для подтверждения</label>
                            <input type="password" 
                                   class="form-control @error('password', 'userDeletion') is-invalid @enderror" 
                                   id="password_delete" 
                                   name="password" 
                                   placeholder="Ваш пароль" 
                                   required>
                            @error('password', 'userDeletion')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Да, удалить аккаунт
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
