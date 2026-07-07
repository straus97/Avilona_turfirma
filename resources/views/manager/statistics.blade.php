@extends('cabinet.layouts.app')

@section('title', 'Статистика менеджера')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Статистика работы</h1>
    <p class="page-subtitle">Ключевые метрики и динамика заявок</p>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Всего заявок',
            'value' => $totalBookings,
            'icon' => 'bi-bookmark-check',
            'color' => 'primary'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Завершено',
            'value' => $statusStats['completed'],
            'icon' => 'bi-check-circle',
            'color' => 'success'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'В работе',
            'value' => $statusStats['pending'] + $statusStats['confirmed'],
            'icon' => 'bi-hourglass-split',
            'color' => 'warning'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Общий доход',
            'value' => number_format($totalRevenue, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-cash-stack',
            'color' => 'info'
        ])
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Распределение по статусам</div>
            </div>
            <canvas id="statusChart" style="height: 300px;"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Заявки по месяцам ({{ date('Y') }})</div>
            </div>
            <canvas id="monthlyChart" style="height: 300px;"></canvas>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="bi bi-bar-chart"></i> Топ-10 направлений</div>
            </div>
            @if($topDestinations->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
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
                                    <td><span class="badge bg-primary">{{ $destination->count }}</span></td>
                                    <td>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" style="width: {{ $totalBookings > 0 ? ($destination->count / $totalBookings * 100) : 0 }}%"></div>
                                        </div>
                                        <small>{{ $totalBookings > 0 ? round($destination->count / $totalBookings * 100, 1) : 0 }}%</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-bar-chart" style="font-size: 3rem;"></i>
                    <p class="mt-2">Нет данных</p>
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="bi bi-activity"></i> Последние активности</div>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                @if($recentActivities->count() > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($recentActivities as $activity)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>#{{ $activity->id }}</strong> - {{ $activity->user->name ?? 'Неизвестно' }}
                                        <br>
                                        <small class="text-muted">{{ $activity->tour_name ?? 'Без названия' }}</small>
                                    </div>
                                    <div class="text-end">
                                        @include('cabinet.components.status-badge', ['status' => $activity->status])
                                        <br>
                                        <small class="text-muted">{{ $activity->updated_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-activity" style="font-size: 3rem;"></i>
                        <p class="mt-2">Нет активностей</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Подробная статистика</div>
    </div>
    <div class="row">
        <div class="col-md-3">
            @include('cabinet.components.stat-card', [
                'title' => 'В обработке',
                'value' => $statusStats['pending'],
                'icon' => 'bi-hourglass-split',
                'color' => 'warning'
            ])
        </div>
        <div class="col-md-3">
            @include('cabinet.components.stat-card', [
                'title' => 'Подтверждено',
                'value' => $statusStats['confirmed'],
                'icon' => 'bi-check-circle',
                'color' => 'info'
            ])
        </div>
        <div class="col-md-3">
            @include('cabinet.components.stat-card', [
                'title' => 'Завершено',
                'value' => $statusStats['completed'],
                'icon' => 'bi-check-all',
                'color' => 'success'
            ])
        </div>
        <div class="col-md-3">
            @include('cabinet.components.stat-card', [
                'title' => 'Отменено',
                'value' => $statusStats['cancelled'],
                'icon' => 'bi-x-circle',
                'color' => 'danger'
            ])
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && window.Chart) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['В работе', 'Подтверждено', 'Завершено', 'Отменено'],
                datasets: [{
                    data: [
                        {{ $statusStats['pending'] }},
                        {{ $statusStats['confirmed'] }},
                        {{ $statusStats['completed'] }},
                        {{ $statusStats['cancelled'] }}
                    ],
                    backgroundColor: ['#f6c23e', '#36b9cc', '#1cc88a', '#e74a3b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx && window.Chart) {
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
                    backgroundColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
</script>
@endpush
