@extends('cabinet.layouts.app')

@section('title', 'Админ панель')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Админ панель</h1>
    <p class="page-subtitle">Контроль пользователей, заявок и контента</p>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Пользователи',
            'value' => $totalUsers,
            'icon' => 'bi-people',
            'color' => 'primary'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Заявки',
            'value' => $totalBookings,
            'icon' => 'bi-journal-text',
            'color' => 'success'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Без менеджера',
            'value' => $unassignedBookings,
            'icon' => 'bi-exclamation-triangle',
            'color' => 'warning'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Доход',
            'value' => number_format($totalRevenue, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-cash-stack',
            'color' => 'danger'
        ])
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Пользователи по ролям</div>
            </div>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Роль</th>
                        <th>Пользователей</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usersByRole as $role)
                        <tr>
                            <td>
                                @if($role->name === 'admin')
                                    <span class="badge bg-danger">Администратор</span>
                                @elseif($role->name === 'manager')
                                    <span class="badge bg-primary">Менеджер</span>
                                @else
                                    <span class="badge bg-secondary">Турист</span>
                                @endif
                            </td>
                            <td><strong>{{ $role->users_count }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Заявки по статусам</div>
            </div>
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <td>@include('cabinet.components.status-badge', ['status' => 'progress'])</td>
                        <td><strong>{{ $bookingsByStatus['pending'] }}</strong></td>
                    </tr>
                    <tr>
                        <td>@include('cabinet.components.status-badge', ['status' => 'confirmed'])</td>
                        <td><strong>{{ $bookingsByStatus['confirmed'] }}</strong></td>
                    </tr>
                    <tr>
                        <td>@include('cabinet.components.status-badge', ['status' => 'completed'])</td>
                        <td><strong>{{ $bookingsByStatus['completed'] }}</strong></td>
                    </tr>
                    <tr>
                        <td>@include('cabinet.components.status-badge', ['status' => 'cancelled'])</td>
                        <td><strong>{{ $bookingsByStatus['cancelled'] }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Последние пользователи</div>
            </div>
            <table class="table table-sm">
                <tbody>
                    @foreach($recentUsers as $user)
                        <tr>
                            <td>
                                <div>{{ $user->name }}</div>
                                <div class="text-muted small">{{ $user->email }}</div>
                            </td>
                            <td class="text-end">
                                @foreach($user->roles as $role)
                                    @if($role->name === 'admin')
                                        <span class="badge bg-danger">Админ</span>
                                    @elseif($role->name === 'manager')
                                        <span class="badge bg-primary">Менеджер</span>
                                    @else
                                        <span class="badge bg-secondary">Турист</span>
                                    @endif
                                @endforeach
                                <div class="text-muted small">
                                    {{ $user->created_at ? $user->created_at->diffForHumans() : '—' }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Последние заявки</div>
            </div>
            <table class="table table-sm">
                <tbody>
                    @foreach($recentBookings as $booking)
                        <tr>
                            <td>
                                <strong>#{{ $booking->id }}</strong> — {{ $booking->user->name ?? 'Неизвестно' }}
                                <div class="text-muted small">{{ $booking->tour_name ?? 'Без названия' }}</div>
                            </td>
                            <td class="text-end">
                                @include('cabinet.components.status-badge', ['status' => $booking->status])
                                <div class="text-muted small">{{ $booking->manager ? $booking->manager->name : 'Не назначен' }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Менеджеры и нагрузка</div>
    </div>
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Менеджер</th>
                <th>Email</th>
                <th>Назначено заявок</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @foreach($managers as $manager)
                <tr>
                    <td>{{ $manager->name }}</td>
                    <td>{{ $manager->email }}</td>
                    <td><span class="badge bg-primary">{{ $manager->managed_bookings_count }}</span></td>
                    <td>
                        <a href="{{ route('cabinet.admin.bookings', ['manager' => $manager->id]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Заявки
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
