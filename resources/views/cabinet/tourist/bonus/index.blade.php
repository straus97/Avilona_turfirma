@extends('cabinet.layouts.app')

@section('title', 'Бонусные баллы')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
@php
    $levelNames = [
        'newbie' => 'Новичок',
        'silver' => 'Серебро',
        'gold' => 'Золото',
        'platinum' => 'Платина',
    ];
    $levelLabel = $levelNames[$bonusAccount->level ?? 'newbie'] ?? ($bonusAccount->level ?? 'Новичок');

    // Единая подача бонусных сумм: сохраняем дробную часть (модели используют decimal:2),
    // убираем только незначимое «.00».
    $bonusAmount = function ($value): string {
        $formatted = number_format((float) $value, 2, '.', '');

        return str_ends_with($formatted, '.00') ? substr($formatted, 0, -3) : $formatted;
    };
@endphp

<div class="page-header">
    <h1 class="page-title">Бонусные баллы</h1>
    <p class="page-subtitle">Баланс и история начислений по вашему бонусному счёту</p>
</div>

{{-- Реальные данные бонусного счёта --}}
<div class="tc-summary">
    <div class="tc-metric">
        <div class="tc-metric__icon"><i class="bi bi-coin" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value">{{ $bonusAmount($bonusAccount->balance ?? 0) }}</div>
            <div class="tc-metric__label">Баллов на счёте</div>
        </div>
    </div>
    <div class="tc-metric">
        <div class="tc-metric__icon tc-metric__icon--muted"><i class="bi bi-award" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value" style="font-size: 1.15rem;">{{ $levelLabel }}</div>
            <div class="tc-metric__label">Уровень счёта</div>
        </div>
    </div>
    <div class="tc-metric">
        <div class="tc-metric__icon tc-metric__icon--success"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value">{{ $bonusAmount($bonusAccount->total_earned ?? 0) }}</div>
            <div class="tc-metric__label">Всего начислено</div>
        </div>
    </div>
    <div class="tc-metric">
        <div class="tc-metric__icon tc-metric__icon--warning"><i class="bi bi-graph-down-arrow" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value">{{ $bonusAmount($bonusAccount->total_spent ?? 0) }}</div>
            <div class="tc-metric__label">Всего списано</div>
        </div>
    </div>
</div>

<div class="tc-notice mb-4">
    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
    <span>
        Здесь отображаются текущий баланс и история операций по бонусному счёту.
        По вопросам о бонусах обратитесь к менеджеру в
        <a href="{{ route('cabinet.chat') }}">чат</a>.
    </span>
</div>

{{-- История операций — реальные транзакции --}}
<div class="card-custom">
    <div class="card-header-custom">
        <h2 class="card-title-custom">История операций</h2>
    </div>

    @if($transactions->count() > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Дата</th>
                        <th scope="col">Операция</th>
                        <th scope="col">Сумма</th>
                        <th scope="col">Баланс после</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $transaction->reason }}</td>
                            <td class="{{ $transaction->type === 'earn' ? 'text-success' : 'text-danger' }}">
                                {{ $transaction->type === 'earn' ? '+' : '−' }}{{ $bonusAmount($transaction->amount) }}
                            </td>
                            <td>{{ $transaction->balance_after !== null ? $bonusAmount($transaction->balance_after) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            {{ $transactions->links() }}
        </div>
    @else
        <p class="text-muted mb-0">Операций по бонусному счёту пока не было.</p>
    @endif
</div>
@endsection
