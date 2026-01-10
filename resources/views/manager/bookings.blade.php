@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Управление заявками</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Фильтры -->
            <div class="row">
                <div class="col-12">
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-funnel"></i> Фильтры и поиск
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('manager.bookings') }}" method="GET" class="form-inline">
                                <div class="form-group mr-2 mb-2">
                                    <label for="search" class="mr-2">Поиск:</label>
                                    <input type="text" 
                                           name="search" 
                                           id="search" 
                                           class="form-control" 
                                           placeholder="Тур, направление, клиент..." 
                                           value="{{ request('search') }}">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2 mr-2">
                                    <i class="bi bi-search"></i> Поиск
                                </button>
                                <a href="{{ route('manager.bookings') }}" class="btn btn-secondary mb-2">
                                    <i class="bi bi-x-circle"></i> Сбросить
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Фильтры по статусам -->
            <div class="row">
                <div class="col-12">
                    <div class="btn-group mb-3" role="group">
                        <a href="{{ route('manager.bookings', ['status' => 'all']) }}" 
                           class="btn {{ request('status', 'all') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Все ({{ $statusCounts['all'] }})
                        </a>
                        <a href="{{ route('manager.bookings', ['status' => 'pending']) }}" 
                           class="btn {{ request('status') === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">
                            В обработке ({{ $statusCounts['pending'] }})
                        </a>
                        <a href="{{ route('manager.bookings', ['status' => 'confirmed']) }}" 
                           class="btn {{ request('status') === 'confirmed' ? 'btn-info' : 'btn-outline-info' }}">
                            Подтверждены ({{ $statusCounts['confirmed'] }})
                        </a>
                        <a href="{{ route('manager.bookings', ['status' => 'completed']) }}" 
                           class="btn {{ request('status') === 'completed' ? 'btn-success' : 'btn-outline-success' }}">
                            Завершены ({{ $statusCounts['completed'] }})
                        </a>
                        <a href="{{ route('manager.bookings', ['status' => 'cancelled']) }}" 
                           class="btn {{ request('status') === 'cancelled' ? 'btn-danger' : 'btn-outline-danger' }}">
                            Отменены ({{ $statusCounts['cancelled'] }})
                        </a>
                    </div>
                </div>
            </div>

            <!-- Список заявок -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Список заявок</h3>
                            <div class="card-tools">
                                <span class="badge badge-primary">
                                    Найдено: {{ $bookings->total() }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($bookings->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>№</th>
                                                <th>Клиент</th>
                                                <th>Тур</th>
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
                                                        <i class="bi bi-person"></i>
                                                        {{ $booking->user->name ?? 'Неизвестно' }}
                                                        <br>
                                                        <small class="text-muted">{{ $booking->user->email ?? '' }}</small>
                                                    </td>
                                                    <td>{{ $booking->tour_name ?? 'Не указан' }}</td>
                                                    <td>{{ $booking->destination ?? 'Не указано' }}</td>
                                                    <td>
                                                        @if($booking->start_date && $booking->end_date)
                                                            <small>
                                                                {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
                                                                <br>
                                                                {{ \Carbon\Carbon::parse($booking->end_date)->format('d.m.Y') }}
                                                            </small>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $booking->adults_count ?? 0 }} взр.
                                                        @if($booking->children_count > 0)
                                                            <br>{{ $booking->children_count }} дет.
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
                                                        <small>{{ $booking->created_at->format('d.m.Y H:i') }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group-vertical btn-group-sm">
                                                            <a href="{{ route('bookings.show', $booking->id) }}" 
                                                               class="btn btn-info btn-xs mb-1" 
                                                               title="Просмотр">
                                                                <i class="bi bi-eye"></i> Просмотр
                                                            </a>
                                                            <a href="{{ route('manager.chat', ['bookingId' => $booking->id]) }}" 
                                                               class="btn btn-success btn-xs mb-1" 
                                                               title="Чат">
                                                                <i class="bi bi-chat"></i> Чат
                                                            </a>
                                                            @if($booking->status === 'pending')
                                                                <form action="{{ route('bookings.confirm', $booking->id) }}" 
                                                                      method="POST" 
                                                                      style="display: inline;">
                                                                    @csrf
                                                                    <button type="submit" 
                                                                            class="btn btn-primary btn-xs mb-1 w-100" 
                                                                            title="Подтвердить">
                                                                        <i class="bi bi-check-circle"></i> Подтвердить
                                                                    </button>
                                                                </form>
                                                            @endif
                                                            @if(in_array($booking->status, ['confirmed']))
                                                                <form action="{{ route('bookings.complete', $booking->id) }}" 
                                                                      method="POST" 
                                                                      style="display: inline;">
                                                                    @csrf
                                                                    <button type="submit" 
                                                                            class="btn btn-success btn-xs w-100" 
                                                                            title="Завершить">
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
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3">
                                        @if(request('search'))
                                            Ничего не найдено
                                        @else
                                            У вас пока нет заявок
                                        @endif
                                    </h4>
                                    <p>
                                        @if(request('search'))
                                            Попробуйте изменить параметры поиска
                                        @else
                                            Заявки появятся здесь после назначения администратором
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                        @if($bookings->hasPages())
                            <div class="card-footer clearfix">
                                {{ $bookings->appends(request()->query())->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
