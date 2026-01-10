@extends('layouts.profile')

@section('title', 'Панель менеджера - Авилона')

@section('content')
<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-chart-line text-primary"></i> Панель менеджера
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.index') }}">Главная</a></li>
                    <li class="breadcrumb-item active">Панель менеджера</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        
        <!-- Статистика -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-info">
                    <div class="inner">
                        <h3>{{ $totalBookings }}</h3>
                        <p>{{ str_plural($totalBookings, 'заявка', 'заявки', 'заявок') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <a href="{{ route('manager.bookings') }}" class="small-box-footer">
                        Все заявки <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-warning">
                    <div class="inner">
                        <h3>{{ $pendingBookings }}</h3>
                        <p>требуют обработки</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <a href="{{ route('manager.bookings', ['status' => 'progress']) }}" class="small-box-footer">
                        Обработать <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-success">
                    <div class="inner">
                        <h3>{{ $totalClients }}</h3>
                        <p>{{ str_plural($totalClients, 'клиент', 'клиента', 'клиентов') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <a href="{{ route('manager.clients') }}" class="small-box-footer">
                        Мои клиенты <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-danger">
                    <div class="inner">
                        <h3>{{ $unreadMessages }}</h3>
                        <p>{{ str_plural($unreadMessages, 'сообщение', 'сообщения', 'сообщений') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <a href="{{ route('manager.chat') }}" class="small-box-footer">
                        Открыть чат <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="row">
            <!-- График по статусам -->
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i> Заявки по статусам
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- График динамики -->
            <div class="col-md-6">
                <div class="card card-success card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i> Динамика заявок
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="bookingsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Последние заявки -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-transparent">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i> Последние заявки
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('manager.bookings') }}" class="btn btn-tool btn-sm">
                                <i class="fas fa-eye"></i> Все заявки
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($latestBookings->count() > 0)
                            <div class="table-responsive">
                                <table class="table m-0">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Клиент</th>
                                            <th>Направление</th>
                                            <th>Дата</th>
                                            <th>Статус</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($latestBookings as $booking)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('bookings.show', $booking->id) }}">
                                                        #{{ $booking->id }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <strong>{{ $booking->user->name }}</strong>
                                                    <br><small class="text-muted">{{ $booking->user->email }}</small>
                                                </td>
                                                <td>
                                                    <strong>{{ $booking->destination_country }}</strong>
                                                    @if($booking->destination_city)
                                                        <br><small class="text-muted">{{ $booking->destination_city }}</small>
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
                                                    <span class="badge badge-{{ $booking->status_color }}">
                                                        {{ $booking->status_label }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('bookings.show', $booking->id) }}" 
                                                           class="btn btn-info" 
                                                           title="Просмотр">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="{{ route('manager.chat', $booking->id) }}" 
                                                           class="btn btn-success" 
                                                           title="Чат">
                                                            <i class="fas fa-comments"></i>
                                                        </a>
                                                        @if(in_array($booking->status, ['new', 'progress']))
                                                            <form action="{{ route('bookings.confirm', $booking->id) }}" 
                                                                  method="POST" 
                                                                  style="display: inline;">
                                                                @csrf
                                                                <button type="submit" 
                                                                        class="btn btn-primary btn-sm" 
                                                                        title="Подтвердить">
                                                                    <i class="fas fa-check"></i>
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
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>У вас пока нет заявок</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Быстрые действия -->
        <div class="row">
            <div class="col-12">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i> Быстрые действия
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('bookings.create') }}" class="btn btn-app btn-primary w-100">
                                    <i class="fas fa-plus-circle"></i> Создать заявку
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('manager.bookings', ['status' => 'progress']) }}" class="btn btn-app btn-warning w-100">
                                    <i class="fas fa-tasks"></i> В обработке
                                    @if($pendingBookings > 0)
                                        <span class="badge badge-danger">{{ $pendingBookings }}</span>
                                    @endif
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('manager.chat') }}" class="btn btn-app btn-success w-100">
                                    <i class="fas fa-comments"></i> Чат
                                    @if($unreadMessages > 0)
                                        <span class="badge badge-danger">{{ $unreadMessages }}</span>
                                    @endif
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('manager.statistics') }}" class="btn btn-app btn-info w-100">
                                    <i class="fas fa-chart-bar"></i> Статистика
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
.small-box {
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
    transition: all .3s;
}

.small-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,.2);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);
}

.btn-app {
    height: auto;
    padding: 15px;
    font-size: 14px;
    border-radius: 10px;
    transition: all .3s;
}

.btn-app:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,.2);
}

.btn-app i {
    font-size: 24px;
    display: block;
    margin-bottom: 5px;
}

.card {
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,.05);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // График по статусам (круговая диаграмма)
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    data: {!! json_encode($chartData) !!},
                    backgroundColor: [
                        '#667eea', // Новая
                        '#f6c23e', // В обработке
                        '#1cc88a', // Подтверждена
                        '#e74a3b'  // Отменена
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // График динамики (линейная диаграмма)
    const bookingsCtx = document.getElementById('bookingsChart');
    if (bookingsCtx) {
        new Chart(bookingsCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($bookingsChartLabels) !!},
                datasets: [{
                    label: 'Заявки',
                    data: {!! json_encode($bookingsChartData) !!},
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
