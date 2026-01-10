@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Статистика работы</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Общая статистика -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $totalBookings }}</h3>
                            <p>Всего заявок</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-bookmark-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $statusStats['completed'] }}</h3>
                            <p>Завершено</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $statusStats['pending'] + $statusStats['confirmed'] }}</h3>
                            <p>В работе</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3>{{ number_format($totalRevenue, 0, ',', ' ') }} ₽</h3>
                            <p>Общий доход</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Статистика по статусам -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Распределение по статусам</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Статистика по месяцам -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Заявки по месяцам ({{ date('Y') }})</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Топ направлений -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-bar-chart"></i> Топ-10 направлений
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            @if($topDestinations->count() > 0)
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Направление</th>
                                            <th>Заявок</th>
                                            <th>%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topDestinations as $destination)
                                            <tr>
                                                <td>{{ $destination->destination }}</td>
                                                <td>
                                                    <span class="badge badge-primary">{{ $destination->count }}</span>
                                                </td>
                                                <td>
                                                    <div class="progress progress-xs">
                                                        <div class="progress-bar bg-primary" 
                                                             style="width: {{ $totalBookings > 0 ? ($destination->count / $totalBookings * 100) : 0 }}%"></div>
                                                    </div>
                                                    <small>{{ $totalBookings > 0 ? round($destination->count / $totalBookings * 100, 1) : 0 }}%</small>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-bar-chart" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Нет данных</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Последние активности -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-activity"></i> Последние активности
                            </h3>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            @if($recentActivities->count() > 0)
                                <ul class="list-group list-group-flush">
                                    @foreach($recentActivities as $activity)
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>#{{ $activity->id }}</strong> - {{ $activity->user->name ?? 'Неизвестно' }}
                                                    <br>
                                                    <small class="text-muted">
                                                        {{ $activity->tour_name ?? 'Без названия' }}
                                                    </small>
                                                </div>
                                                <div class="text-right">
                                                    @if($activity->status === 'pending')
                                                        <span class="badge badge-warning">В обработке</span>
                                                    @elseif($activity->status === 'confirmed')
                                                        <span class="badge badge-info">Подтверждена</span>
                                                    @elseif($activity->status === 'completed')
                                                        <span class="badge badge-success">Завершена</span>
                                                    @elseif($activity->status === 'cancelled')
                                                        <span class="badge badge-danger">Отменена</span>
                                                    @endif
                                                    <br>
                                                    <small class="text-muted">{{ $activity->updated_at->diffForHumans() }}</small>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-activity" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Нет активностей</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Подробная статистика -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Подробная статистика</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-warning"><i class="bi bi-hourglass-split"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">В обработке</span>
                                            <span class="info-box-number">{{ $statusStats['pending'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info"><i class="bi bi-check-circle"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Подтверждено</span>
                                            <span class="info-box-number">{{ $statusStats['confirmed'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-success"><i class="bi bi-calendar-check"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Завершено</span>
                                            <span class="info-box-number">{{ $statusStats['completed'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-danger"><i class="bi bi-x-circle"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Отменено</span>
                                            <span class="info-box-number">{{ $statusStats['cancelled'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    // График по статусам
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['В обработке', 'Подтверждено', 'Завершено', 'Отменено'],
            datasets: [{
                data: [
                    {{ $statusStats['pending'] }},
                    {{ $statusStats['confirmed'] }},
                    {{ $statusStats['completed'] }},
                    {{ $statusStats['cancelled'] }}
                ],
                backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // График по месяцам
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
            datasets: [{
                label: 'Заявок',
                data: [
                    @for($i = 1; $i <= 12; $i++)
                        {{ $monthlyData[$i]['count'] ?? 0 }},
                    @endfor
                ],
                backgroundColor: '#007bff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
</script>
@endpush
