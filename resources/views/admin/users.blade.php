@extends('layouts.profile')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Управление пользователями</h1>
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
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('error') }}
                </div>
            @endif

            <!-- Фильтры -->
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.users') }}" method="GET" class="form-inline">
                        <input type="text" name="search" class="form-control mr-2" placeholder="Поиск..." value="{{ request('search') }}">
                        <select name="role" class="form-control mr-2">
                            <option value="all" {{ request('role', 'all') === 'all' ? 'selected' : '' }}>Все роли</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->role }}" {{ request('role') === $role->role ? 'selected' : '' }}>
                                    {{ $role->role === 'admin' ? 'Администраторы' : ($role->role === 'manager' ? 'Менеджеры' : 'Туристы') }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary mr-2"><i class="bi bi-search"></i> Поиск</button>
                        <a href="{{ route('admin.users') }}" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Сбросить</a>
                    </form>
                </div>
            </div>

            <!-- Список пользователей -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Пользователи ({{ $users->total() }})</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Роли</th>
                                <th>Зарегистрирован</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td><i class="bi bi-person-circle"></i> {{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->phone ?? '-' }}</td>
                                    <td>
                                        @foreach($user->roles as $role)
                                            @if($role->role === 'admin')
                                                <span class="badge badge-danger">Админ</span>
                                            @elseif($role->role === 'manager')
                                                <span class="badge badge-primary">Менеджер</span>
                                            @else
                                                <span class="badge badge-secondary">Турист</span>
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>{{ $user->created_at->format('d.m.Y') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('admin.user-roles', $user->id) }}" class="btn btn-sm btn-primary" title="Управление ролями">
                                                <i class="bi bi-key"></i>
                                            </a>
                                            @if($user->id !== Auth::id())
                                                <form action="{{ route('admin.delete-user', $user->id) }}" method="POST" 
                                                      onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Удалить">
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
        </div>
    </section>
@endsection
