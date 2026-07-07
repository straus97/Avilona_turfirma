@extends('cabinet.layouts.app')

@section('title', 'Управление ролями')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Управление ролями: {{ $user->name }}</h1>
    <p class="page-subtitle">Назначение и удаление ролей</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Текущие роли</div>
            </div>
            <div class="card-body">
                @if($user->roles->count() > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($user->roles as $role)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                @if($role->name === 'admin')
                                    <span class="badge bg-danger">Администратор</span>
                                @elseif($role->name === 'manager')
                                    <span class="badge bg-primary">Менеджер</span>
                                @else
                                    <span class="badge bg-secondary">Турист</span>
                                @endif
                                <form action="{{ route('cabinet.admin.remove-role', [$user->id, $role->id]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Удалить
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">У пользователя нет ролей</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Назначить роль</div>
            </div>
            <div class="card-body">
                <form action="{{ route('cabinet.admin.assign-role', $user->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Выберите роль</label>
                        <select name="role" class="form-select" required>
                            @foreach($allRoles as $role)
                                <option value="{{ $role->name }}">
                                    {{ $role->name === 'admin' ? 'Администратор' : ($role->name === 'manager' ? 'Менеджер' : 'Турист') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Назначить
                        </button>
                        <a href="{{ route('cabinet.admin.users') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Назад
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
