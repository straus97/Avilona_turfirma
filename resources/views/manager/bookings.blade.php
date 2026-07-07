@extends('cabinet.layouts.app')

@section('title', 'Заявки менеджера')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Заявки менеджера</h1>
    <p class="page-subtitle">Фильтрация, поиск и управление заявками</p>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="bi bi-funnel"></i> Фильтры и поиск</div>
    </div>
    <form action="{{ route('cabinet.manager.bookings') }}" method="GET" class="d-flex flex-wrap gap-2">
        <input type="text"
               name="search"
               id="search"
               class="form-control"
               placeholder="Тур, направление, клиент..."
               value="{{ request('search') }}" style="max-width: 320px;">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Поиск
        </button>
        <a href="{{ route('cabinet.manager.bookings') }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i> Сбросить
        </a>
    </form>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="bi bi-filter"></i> Статусы</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'all']) }}"
           class="btn {{ request('status', 'all') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
            Все ({{ $statusCounts['all'] }})
        </a>
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'new']) }}"
           class="btn {{ request('status') === 'new' ? 'btn-info' : 'btn-outline-info' }}">
            Новые ({{ $statusCounts['new'] ?? 0 }})
        </a>
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'progress']) }}"
           class="btn {{ request('status') === 'progress' ? 'btn-warning' : 'btn-outline-warning' }}">
            В обработке ({{ $statusCounts['progress'] ?? 0 }})
        </a>
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'confirmed']) }}"
           class="btn {{ request('status') === 'confirmed' ? 'btn-success' : 'btn-outline-success' }}">
            Подтверждены ({{ $statusCounts['confirmed'] }})
        </a>
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'completed']) }}"
           class="btn {{ request('status') === 'completed' ? 'btn-secondary' : 'btn-outline-secondary' }}">
            Завершены ({{ $statusCounts['completed'] }})
        </a>
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'cancelled']) }}"
           class="btn {{ request('status') === 'cancelled' ? 'btn-danger' : 'btn-outline-danger' }}">
            Отменены ({{ $statusCounts['cancelled'] }})
        </a>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Список заявок</div>
        <span class="badge bg-primary">Найдено: {{ $bookings->total() }}</span>
    </div>

    @if($bookings->count() > 0)
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Клиент</th>
                        <th>Направление</th>
                        <th>Даты</th>
                        <th>Туристы</th>
                        <th>Цена</th>
                        <th>Статус</th>
                        <th>Создана</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $booking)
                        <tr>
                            <td><strong>#{{ $booking->id }}</strong></td>
                            <td>
                                <div>{{ $booking->user->name ?? 'Неизвестно' }}</div>
                                <div class="text-muted small">{{ $booking->user->email ?? '' }}</div>
                            </td>
                            <td>
                                {{ $booking->destination_country ?? 'Не указано' }}
                                @if($booking->destination_city)
                                    <div class="text-muted small">{{ $booking->destination_city }}</div>
                                @endif
                            </td>
                            <td>
                                @if($booking->start_date)
                                    <small>
                                        {{ $booking->start_date->format('d.m.Y') }}
                                        @if($booking->start_date_end && $booking->start_date_end != $booking->start_date)
                                            <br>{{ $booking->start_date_end->format('d.m.Y') }}
                                        @endif
                                    </small>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                {{ $booking->adults }} {{ str_plural($booking->adults, 'взрослый', 'взрослых', 'взрослых') }}
                                @if($booking->children > 0)
                                    <br>{{ $booking->children }} {{ str_plural($booking->children, 'ребенок', 'ребенка', 'детей') }}
                                @endif
                            </td>
                            <td>
                                @if($booking->total_price)
                                    <strong>{{ number_format($booking->total_price, 0, ',', ' ') }} ₽</strong>
                                @else
                                    <span class="text-muted">Не указана</span>
                                @endif
                            </td>
                            <td>
                                @include('cabinet.components.status-badge', ['status' => $booking->status])
                            </td>
                            <td>
                                <small>{{ $booking->created_at->format('d.m.Y H:i') }}</small>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Просмотр
                                    </a>
                                    <a href="{{ route('cabinet.manager.chat', ['bookingId' => $booking->id]) }}" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-chat"></i> Чат
                                    </a>
                                    @if(in_array($booking->status, ['new', 'progress']))
                                        <form action="{{ route('bookings.confirm', $booking->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="bi bi-check-circle"></i> Подтвердить
                                            </button>
                                        </form>
                                    @endif
                                    @if($booking->status === 'confirmed')
                                        <form action="{{ route('bookings.complete', $booking->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                                <i class="bi bi-calendar-check"></i> Завершить
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
    @else
        @include('cabinet.components.empty-state', [
            'icon' => 'bi-inbox',
            'title' => request('search') ? 'Ничего не найдено' : 'У вас пока нет заявок',
            'description' => request('search') ? 'Попробуйте изменить параметры поиска' : 'Заявки появятся здесь после назначения администратором',
        ])
    @endif

    @if($bookings->hasPages())
        <div class="mt-3">
            {{ $bookings->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
