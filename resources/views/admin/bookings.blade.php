@extends('cabinet.layouts.app')

@section('title', 'Управление заявками')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Управление заявками</h1>
    <p class="page-subtitle">Поиск, фильтры и назначение менеджеров</p>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Фильтры</div>
    </div>
    <form action="{{ route('cabinet.admin.bookings') }}" method="GET" class="row g-2 align-items-end">
        <div class="col-md-6 col-xl-3">
            <label class="form-label" for="admin-bookings-search">Поиск</label>
            <input type="text" id="admin-bookings-search" name="search" class="form-control" placeholder="Поиск..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="admin-bookings-status">Статус</label>
            <select id="admin-bookings-status" name="status" class="form-select">
                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Все статусы</option>
                <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>Новые</option>
                <option value="progress" {{ request('status') === 'progress' ? 'selected' : '' }}>В обработке</option>
                <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Подтверждены</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Завершены</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменены</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="admin-bookings-manager">Менеджер</label>
            <select id="admin-bookings-manager" name="manager" class="form-select">
                <option value="all" {{ request('manager', 'all') === 'all' ? 'selected' : '' }}>Все менеджеры</option>
                <option value="unassigned" {{ request('manager') === 'unassigned' ? 'selected' : '' }}>Не назначен</option>
                @foreach($managers as $mgr)
                    <option value="{{ $mgr->id }}" {{ request('manager') == $mgr->id ? 'selected' : '' }}>
                        {{ $mgr->name }}{{ $mgr->roles->contains('name', 'admin') ? ' (Админ)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-auto admin-filter-actions">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Поиск</button>
            <a href="{{ route('cabinet.admin.bookings') }}" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Сбросить</a>
        </div>
    </form>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Счетчики</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-info">Всего: {{ $statusCounts['all'] }}</span>
        <span class="badge bg-primary">Новые: {{ $statusCounts['new'] }}</span>
        <span class="badge bg-warning text-dark">В обработке: {{ $statusCounts['progress'] }}</span>
        <span class="badge bg-success">Подтверждено: {{ $statusCounts['confirmed'] }}</span>
        <span class="badge bg-secondary">Завершено: {{ $statusCounts['completed'] }}</span>
        <span class="badge bg-dark">Отменено: {{ $statusCounts['cancelled'] }}</span>
        <span class="badge bg-danger">Не назначено: {{ $statusCounts['unassigned'] }}</span>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Список заявок</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Клиент</th>
                    <th>Направление</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Менеджер</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td><strong>#{{ $booking->id }}</strong></td>
                        <td>{{ $booking->user->name ?? 'Неизвестно' }}</td>
                        <td>
                            @if($booking->destination_country)
                                {{ $booking->destination_country }}
                                @if($booking->destination_city)
                                    <div class="text-muted small">{{ $booking->destination_city }}</div>
                                @endif
                            @elseif($booking->tour)
                                {{ $booking->tour->title }}
                            @else
                                Не указано
                            @endif
                        </td>
                        <td>
                            @if($booking->start_date)
                                {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @include('cabinet.components.status-badge', ['status' => $booking->status])
                        </td>
                        <td>
                            <form action="{{ route('bookings.assign-manager', $booking->id) }}" method="POST" class="d-flex gap-2 align-items-center">
                                @csrf
                                @php
                                    // Список кандидатов — только $managers (единый источник правды,
                                    // User::assignableToBookings()). Если текущий ответственный туда
                                    // не входит (стал неактивен), он не пропадает из select молча —
                                    // добавляем его отдельной disabled-опцией только для отображения:
                                    // это не расширяет право назначения, backend всё равно
                                    // перепроверяет допустимость независимо от разметки.
                                    $currentAssigneeIsEligible = $booking->manager_id !== null
                                        && $managers->contains('id', $booking->manager_id);
                                @endphp
                                <select name="manager_id" class="form-select form-select-sm" aria-label="Менеджер для заявки №{{ $booking->id }}">
                                    <option value="">Выбрать...</option>
                                    @if($booking->manager_id !== null && ! $currentAssigneeIsEligible && $booking->manager)
                                        <option value="{{ $booking->manager_id }}" selected disabled>
                                            {{ $booking->manager->name }} (неактивен)
                                        </option>
                                    @endif
                                    @foreach($managers as $mgr)
                                        <option value="{{ $mgr->id }}" {{ $booking->manager_id == $mgr->id ? 'selected' : '' }}>
                                            {{ $mgr->name }}{{ $mgr->roles->contains('name', 'admin') ? ' (Админ)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                            </form>
                        </td>
                        <td>
                            <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Просмотреть заявку №{{ $booking->id }}">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Заявки не найдены.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($bookings->hasPages())
        <div class="card-footer">
            {{ $bookings->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
