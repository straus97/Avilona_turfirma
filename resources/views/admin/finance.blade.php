@extends('cabinet.layouts.app')

@section('title', 'Финансы')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Финансы</h1>
    <p class="page-subtitle">Раздел финансовой аналитики и отчетов</p>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Выручка (завершено)',
            'value' => number_format($completedRevenue, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-cash-stack',
            'color' => 'success'
        ])
    </div>
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Оплачено всего',
            'value' => number_format($totalPaid, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-credit-card',
            'color' => 'primary'
        ])
    </div>
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Задолженность',
            'value' => number_format(max($totalOutstanding, 0), 0, ',', ' ') . ' ₽',
            'icon' => 'bi-exclamation-triangle',
            'color' => 'warning'
        ])
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Динамика по месяцам</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Месяц</th>
                            <th>Заявок</th>
                            <th>Выручка</th>
                            <th>Оплачено</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthlyStats as $row)
                            <tr>
                                <td>{{ $row->month }}</td>
                                <td>{{ $row->count }}</td>
                                <td>{{ number_format($row->revenue ?? 0, 0, ',', ' ') }} ₽</td>
                                <td>{{ number_format($row->paid ?? 0, 0, ',', ' ') }} ₽</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom">Выручка по менеджерам</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Менеджер</th>
                            <th>Завершено заявок</th>
                            <th>Выручка</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($managerStats as $manager)
                            <tr>
                                <td>{{ $manager->name }}</td>
                                <td>{{ $manager->completed_bookings_count }}</td>
                                <td>{{ number_format($manager->completed_revenue ?? 0, 0, ',', ' ') }} ₽</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Последние заявки</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Клиент</th>
                    <th>Менеджер</th>
                    <th>Статус</th>
                    <th>Стоимость</th>
                    <th>Оплачено</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentBookings as $booking)
                    <tr>
                        <td>#{{ $booking->id }}</td>
                        <td>{{ $booking->user->name ?? '—' }}</td>
                        <td>{{ $booking->manager?->name ?? '—' }}</td>
                        <td>@include('cabinet.components.status-badge', ['status' => $booking->status])</td>
                        <td>{{ number_format($booking->total_price ?? 0, 0, ',', ' ') }} ₽</td>
                        <td>{{ number_format($booking->paid_amount ?? 0, 0, ',', ' ') }} ₽</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
