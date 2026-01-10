@extends('layouts.profile')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Управление ролями: {{ $user->name }}</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Текущие роли</h3>
                        </div>
                        <div class="card-body">
                            @if($user->roles->count() > 0)
                                <ul class="list-group">
                                    @foreach($user->roles as $role)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            @if($role->role === 'admin')
                                                <span class="badge badge-danger">Администратор</span>
                                            @elseif($role->role === 'manager')
                                                <span class="badge badge-primary">Менеджер</span>
                                            @else
                                                <span class="badge badge-secondary">Турист</span>
                                            @endif
                                            <form action="{{ route('admin.remove-role', [$user->id, $role->id]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i> Удалить
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted">У пользователя нет ролей</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Назначить роль</h3>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.assign-role', $user->id) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>Выберите роль</label>
                                    <select name="role" class="form-control" required>
                                        @foreach($allRoles as $role)
                                            <option value="{{ $role->role }}">
                                                {{ $role->role === 'admin' ? 'Администратор' : ($role->role === 'manager' ? 'Менеджер' : 'Турист') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Назначить
                                </button>
                                <a href="{{ route('admin.users') }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Назад
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
