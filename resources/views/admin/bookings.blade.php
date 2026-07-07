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
        <div class="col-md-4">
            <label class="form-label">Поиск</label>
            <input type="text" name="search" class="form-control" placeholder="Поиск..." value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Статус</label>
            <select name="status" class="form-select">
                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Все статусы</option>
                <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>Новые</option>
                <option value="progress" {{ request('status') === 'progress' ? 'selected' : '' }}>В обработке</option>
                <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Подтверждены</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Завершены</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменены</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Менеджер</label>
            <select name="manager" class="form-select">
                <option value="all" {{ request('manager', 'all') === 'all' ? 'selected' : '' }}>Все менеджеры</option>
                <option value="unassigned" {{ request('manager') === 'unassigned' ? 'selected' : '' }}>Не назначен</option>
                @foreach($managers as $mgr)
                    <option value="{{ $mgr->id }}" {{ request('manager') == $mgr->id ? 'selected' : '' }}>
                        {{ $mgr->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Поиск</button>
            <a href="{{ route('cabinet.admin.bookings') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Сбросить</a>
        </div>
    </form>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Счетчики</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-info">Всего: {{ $statusCounts['all'] }}</span>
        <span class="badge bg-primary">Новые: {{ $statusCounts['new'] ?? 0 }}</span>
        <span class="badge bg-warning text-dark">В обработке: {{ $statusCounts['pending'] ?? 0 }}</span>
        <span class="badge bg-success">Подтверждено: {{ $statusCounts['confirmed'] }}</span>
        <span class="badge bg-secondary">Завершено: {{ $statusCounts['completed'] }}</span>
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
                    <th>Тур</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Менеджер</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bookings as $booking)
                    <tr>
                        <td><strong>#{{ $booking->id }}</strong></td>
                        <td>{{ $booking->user->name ?? 'Неизвестно' }}</td>
                        <td>{{ Str::limit($booking->tour_name ?? 'Без названия', 30) }}</td>
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
                                <select name="manager_id" class="form-select form-select-sm">
                                    <option value="">Выбрать...</option>
                                    @foreach($managers as $mgr)
                                        <option value="{{ $mgr->id }}" {{ $booking->manager_id == $mgr->id ? 'selected' : '' }}>
                                            {{ $mgr->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить</button>
                            </form>
                        </td>
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
    @if($bookings->hasPages())
        <div class="card-footer">
            {{ $bookings->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
