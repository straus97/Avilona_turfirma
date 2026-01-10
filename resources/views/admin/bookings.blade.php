@extends('layouts.profile')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Управление заявками</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Фильтры -->
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.bookings') }}" method="GET" class="form-inline">
                        <input type="text" name="search" class="form-control mr-2" placeholder="Поиск..." value="{{ request('search') }}">
                        <select name="status" class="form-control mr-2">
                            <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Все статусы</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>В обработке</option>
                            <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Подтверждены</option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Завершены</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменены</option>
                        </select>
                        <select name="manager" class="form-control mr-2">
                            <option value="all" {{ request('manager', 'all') === 'all' ? 'selected' : '' }}>Все менеджеры</option>
                            <option value="unassigned" {{ request('manager') === 'unassigned' ? 'selected' : '' }}>Не назначен</option>
                            @foreach($managers as $mgr)
                                <option value="{{ $mgr->id }}" {{ request('manager') == $mgr->id ? 'selected' : '' }}>
                                    {{ $mgr->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary mr-2"><i class="bi bi-search"></i> Поиск</button>
                        <a href="{{ route('admin.bookings') }}" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Сбросить</a>
                    </form>
                </div>
            </div>

            <!-- Счетчики -->
            <div class="row mb-3">
                <div class="col">
                    <span class="badge badge-info">Всего: {{ $statusCounts['all'] }}</span>
                    <span class="badge badge-warning">В обработке: {{ $statusCounts['pending'] }}</span>
                    <span class="badge badge-success">Подтверждено: {{ $statusCounts['confirmed'] }}</span>
                    <span class="badge badge-secondary">Завершено: {{ $statusCounts['completed'] }}</span>
                    <span class="badge badge-danger">Не назначено: {{ $statusCounts['unassigned'] }}</span>
                </div>
            </div>

            <!-- Список заявок -->
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped">
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
                                        @if($booking->status === 'pending')
                                            <span class="badge badge-warning">В обработке</span>
                                        @elseif($booking->status === 'confirmed')
                                            <span class="badge badge-info">Подтверждена</span>
                                        @elseif($booking->status === 'completed')
                                            <span class="badge badge-success">Завершена</span>
                                        @elseif($booking->status === 'cancelled')
                                            <span class="badge badge-danger">Отменена</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($booking->manager)
                                            {{ $booking->manager->name }}
                                        @else
                                            <form action="{{ route('bookings.assign-manager', $booking->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <select name="manager_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                                    <option value="">Выбрать...</option>
                                                    @foreach($managers as $mgr)
                                                        <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-sm btn-info">
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
        </div>
    </section>
@endsection
