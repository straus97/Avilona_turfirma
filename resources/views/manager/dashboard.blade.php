@extends('cabinet.layouts.app')

@section('title', 'Панель менеджера')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Панель менеджера</h1>
    <p class="page-subtitle">Контролируйте заявки, клиентов и сообщения</p>
</div>

<!-- Статистика -->
<div class="row mb-4">
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Всего заявок',
            'value' => $totalBookings,
            'icon' => 'bi-journal-text',
            'color' => 'primary'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'В обработке',
            'value' => $pendingBookings,
            'icon' => 'bi-hourglass-split',
            'color' => 'warning'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Мои клиенты',
            'value' => $totalClients,
            'icon' => 'bi-people',
            'color' => 'success'
        ])
    </div>
    <div class="col-md-3">
        @include('cabinet.components.stat-card', [
            'title' => 'Непрочитанные',
            'value' => $unreadMessages,
            'icon' => 'bi-chat-dots',
            'color' => 'danger'
        ])
    </div>
</div>

<!-- Графики -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="bi bi-pie-chart"></i> Заявки по статусам</div>
            </div>
            <canvas id="statusChart" height="200"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="bi bi-graph-up"></i> Динамика заявок</div>
            </div>
            <canvas id="bookingsChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Последние заявки -->
<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="bi bi-clock-history"></i> Последние заявки</div>
        <a href="{{ route('cabinet.manager.bookings') }}" class="btn btn-sm btn-outline-primary">
            Все заявки
        </a>
    </div>

    @if($recentBookings->count() > 0)
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Клиент</th>
                        <th>Направление</th>
                        <th>Дата вылета</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentBookings as $booking)
                        <tr>
                            <td><a href="{{ route('bookings.show', $booking->id) }}">#{{ $booking->id }}</a></td>
                            <td>
                                <div>{{ $booking->user->name ?? 'Удален' }}</div>
                                <div class="text-muted small">{{ $booking->user->email ?? 'Нет email' }}</div>
                            </td>
                            <td>
                                {{ $booking->destination_country }}
                                @if($booking->destination_city)
                                    <div class="text-muted small">{{ $booking->destination_city }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : '—' }}
                            </td>
                            <td>
                                @include('cabinet.components.status-badge', ['status' => $booking->status])
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('cabinet.manager.chat', $booking->id) }}" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-chat-dots"></i>
                                    </a>
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
            'title' => 'Заявок пока нет',
            'description' => 'Назначенные заявки появятся здесь',
        ])
    @endif
</div>

<!-- Быстрые действия -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="bi bi-lightning"></i> Быстрые действия</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Создать заявку
        </a>
        <a href="{{ route('cabinet.manager.bookings', ['status' => 'progress']) }}" class="btn btn-outline-warning">
            <i class="bi bi-hourglass-split"></i> В обработке
            @if($pendingBookings > 0)
                <span class="badge bg-warning text-dark ms-1">{{ $pendingBookings }}</span>
            @endif
        </a>
        <a href="{{ route('cabinet.manager.chat') }}" class="btn btn-outline-success">
            <i class="bi bi-chat-dots"></i> Чат
            @if($unreadMessages > 0)
                <span class="badge bg-danger ms-1">{{ $unreadMessages }}</span>
            @endif
        </a>
        <a href="{{ route('cabinet.manager.statistics') }}" class="btn btn-outline-primary">
            <i class="bi bi-graph-up"></i> Статистика
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && window.Chart) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    data: {!! json_encode($chartData) !!},
                    backgroundColor: ['#667eea', '#f6c23e', '#1cc88a', '#e74a3b', '#6f42c1'],
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

    const bookingsCtx = document.getElementById('bookingsChart');
    if (bookingsCtx && window.Chart) {
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
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
});
</script>
@endpush
