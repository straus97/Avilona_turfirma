@extends('cabinet.layouts.app')

@section('title', 'Управление пользователями')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Управление пользователями</h1>
    <p class="page-subtitle">Поиск и управление ролями</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Фильтры</div>
    </div>
    <form action="{{ route('cabinet.admin.users') }}" method="GET" class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label">Поиск</label>
            <input type="text" name="search" class="form-control" placeholder="Поиск..." value="{{ request('search') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Роль</label>
            <select name="role" class="form-select">
                <option value="all" {{ request('role', 'all') === 'all' ? 'selected' : '' }}>Все роли</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                        {{ $role->name === 'admin' ? 'Администраторы' : ($role->name === 'manager' ? 'Менеджеры' : 'Туристы') }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Поиск</button>
            <a href="{{ route('cabinet.admin.users') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Сбросить</a>
        </div>
    </form>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Пользователи ({{ $users->total() }})</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Email</th>
                    <th>Подтвержден</th>
                    <th>Телефон</th>
                    <th>Роли</th>
                    <th>Быстрая роль</th>
                    <th>Зарегистрирован</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if($user->email_verified_at)
                                <span class="badge bg-success">Да</span>
                            @else
                                <span class="badge bg-warning text-dark">Нет</span>
                            @endif
                        </td>
                        <td>{{ $user->phone ?? '-' }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                @if($role->name === 'admin')
                                    <span class="badge bg-danger">Админ</span>
                                @elseif($role->name === 'manager')
                                    <span class="badge bg-primary">Менеджер</span>
                                @else
                                    <span class="badge bg-secondary">Турист</span>
                                @endif
                            @endforeach
                        </td>
                        <td>
                            <form action="{{ route('cabinet.admin.user-update-role', $user->id) }}" method="POST" class="d-flex gap-2">
                                @csrf
                                <select name="role" class="form-select form-select-sm">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                                            {{ $role->name === 'admin' ? 'Админ' : ($role->name === 'manager' ? 'Менеджер' : 'Турист') }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                            </form>
                        </td>
                        <td>{{ $user->created_at ? $user->created_at->format('d.m.Y') : '—' }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('cabinet.admin.user-show', $user->id) }}" class="btn btn-sm btn-outline-success" title="Карточка пользователя">
                                    <i class="bi bi-person-badge"></i>
                                </a>
                                <a href="{{ route('cabinet.admin.user-roles', $user->id) }}" class="btn btn-sm btn-outline-primary" title="Управление ролями">
                                    <i class="bi bi-key"></i>
                                </a>
                                @if($user->id !== Auth::id())
                                    <form action="{{ route('cabinet.admin.delete-user', $user->id) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
        <div class="card-footer">
            {{ $users->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
