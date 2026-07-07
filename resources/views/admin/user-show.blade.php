@extends('cabinet.layouts.app')

@section('title', 'Карточка пользователя')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Карточка пользователя</h1>
    <p class="page-subtitle">{{ $user->name }} • {{ $user->email }}</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom">Основные данные</div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Имя:</strong> {{ $user->name }}</p>
                    <p><strong>Email:</strong> {{ $user->email }}</p>
                    <p><strong>Телефон:</strong> {{ $user->phone ?? '—' }}</p>
                    <p><strong>Дата рождения:</strong> {{ $user->birth_date ?? '—' }}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Адрес:</strong> {{ $user->address ?? '—' }}</p>
                    <p><strong>Паспорт:</strong> {{ $user->passport_number ?? '—' }}</p>
                    <p><strong>Кем выдан:</strong> {{ $user->passport_issued_by ?? '—' }}</p>
                    <p><strong>Дата выдачи:</strong> {{ $user->passport_issued_date ?? '—' }}</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3">
                <div>
                    <span class="text-muted">Email подтвержден:</span>
                    @if($user->email_verified_at)
                        <span class="badge bg-success">Да</span>
                    @else
                        <span class="badge bg-warning text-dark">Нет</span>
                    @endif
                </div>
                <div>
                    <span class="text-muted">Активен:</span>
                    @if($user->is_active)
                        <span class="badge bg-success">Да</span>
                    @else
                        <span class="badge bg-secondary">Нет</span>
                    @endif
                </div>
                <div>
                    <span class="text-muted">Регистрация:</span>
                    <span class="fw-semibold">{{ $user->created_at ? $user->created_at->format('d.m.Y H:i') : '—' }}</span>
                </div>
                <div>
                    <span class="text-muted">Последний вход:</span>
                    <span class="fw-semibold">{{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : '—' }}</span>
                </div>
            </div>
        </div>

        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom">Заявки пользователя</div>
            </div>
            @if($user->bookings->count() > 0)
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Направление</th>
                                <th>Статус</th>
                                <th>Создана</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->bookings as $booking)
                                <tr>
                                    <td>#{{ $booking->id }}</td>
                                    <td>
                                        {{ $booking->destination_country ?? '—' }}
                                        @if($booking->destination_city)
                                            <div class="text-muted small">{{ $booking->destination_city }}</div>
                                        @endif
                                    </td>
                                    <td>@include('cabinet.components.status-badge', ['status' => $booking->status])</td>
                                    <td>{{ $booking->created_at ? $booking->created_at->format('d.m.Y') : '—' }}</td>
                                    <td>
                                        <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-muted">Заявок нет.</div>
            @endif
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Документы пользователя</div>
            </div>
            @if($user->userDocuments->count() > 0)
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Тип</th>
                                <th>Размер</th>
                                <th>Файл</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->userDocuments as $document)
                                <tr>
                                    <td>{{ $document->name }}</td>
                                    <td>{{ $document->document_type }}</td>
                                    <td>{{ number_format($document->file_size / 1024, 1, '.', ' ') }} KB</td>
                                    <td>
                                        <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary" rel="noopener">
                                            <i class="bi bi-paperclip"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-muted">Документы отсутствуют.</div>
            @endif
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div class="card-title-custom">Роли пользователя</div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($user->roles as $role)
                    @if($role->name === 'admin')
                        <span class="badge bg-danger">Администратор</span>
                    @elseif($role->name === 'manager')
                        <span class="badge bg-primary">Менеджер</span>
                    @else
                        <span class="badge bg-secondary">Турист</span>
                    @endif
                @endforeach
            </div>
            <form action="{{ route('cabinet.admin.user-update-role', $user->id) }}" method="POST" class="d-flex gap-2">
                @csrf
                <select name="role" class="form-select">
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                            {{ $role->name === 'admin' ? 'Админ' : ($role->name === 'manager' ? 'Менеджер' : 'Турист') }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary">Сохранить</button>
            </form>
            <a href="{{ route('cabinet.admin.users') }}" class="btn btn-link mt-3">← Назад к списку</a>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Действия</div>
            </div>
            <div class="d-grid gap-2">
                <a href="{{ route('cabinet.admin.user-roles', $user->id) }}" class="btn btn-outline-secondary">Управление ролями</a>
                @if($user->id !== Auth::id())
                    <form action="{{ route('cabinet.admin.delete-user', $user->id) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Удалить пользователя</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
