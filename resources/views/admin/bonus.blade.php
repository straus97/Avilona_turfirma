@extends('cabinet.layouts.app')

@section('title', 'Бонусная программа')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Бонусная программа</h1>
    <p class="page-subtitle">Управление бонусами и начислениями</p>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Баланс всего',
            'value' => number_format($totalBalance, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-wallet2',
            'color' => 'primary'
        ])
    </div>
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Начислено',
            'value' => number_format($totalEarned, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-plus-circle',
            'color' => 'success'
        ])
    </div>
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Списано',
            'value' => number_format($totalSpent, 0, ',', ' ') . ' ₽',
            'icon' => 'bi-dash-circle',
            'color' => 'warning'
        ])
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Бонусные счета</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Уровень</th>
                    <th>Баланс</th>
                    <th>Начислено</th>
                    <th>Списано</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                    <tr>
                        <td>{{ $account->user?->name ?? '—' }}</td>
                        <td>{{ $account->level ?? '—' }}</td>
                        <td>{{ number_format($account->balance ?? 0, 0, ',', ' ') }} ₽</td>
                        <td>{{ number_format($account->total_earned ?? 0, 0, ',', ' ') }} ₽</td>
                        <td>{{ number_format($account->total_spent ?? 0, 0, ',', ' ') }} ₽</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($accounts->hasPages())
        <div class="card-footer">
            {{ $accounts->links() }}
        </div>
    @endif
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Последние операции</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Пользователь</th>
                    <th>Тип</th>
                    <th>Сумма</th>
                    <th>Причина</th>
                    <th>Баланс</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $trx)
                    <tr>
                        <td>{{ $trx->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $trx->bonusAccount?->user?->name ?? '—' }}</td>
                        <td>
                            @if($trx->type === 'earn')
                                <span class="badge bg-success">Начисление</span>
                            @else
                                <span class="badge bg-danger">Списание</span>
                            @endif
                        </td>
                        <td>{{ number_format($trx->amount ?? 0, 0, ',', ' ') }} ₽</td>
                        <td>{{ $trx->reason ?? '—' }}</td>
                        <td>{{ number_format($trx->balance_after ?? 0, 0, ',', ' ') }} ₽</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
