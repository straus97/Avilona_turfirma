@extends('cabinet.layouts.app')

@section('title', 'Бонусная программа')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Бонусная программа</h1>
    <p class="page-subtitle">Накапливайте баллы и получайте скидки на туры</p>
</div>

<!-- Баланс и уровень -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card-custom" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="text-center">
                <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Ваш баланс</div>
                <h1 style="font-size: 3rem; font-weight: 700; margin: 0;">{{ $bonusAccount->balance ?? 0 }}</h1>
                <div style="font-size: 0.875rem; opacity: 0.9;">баллов = {{ $bonusAccount->balance ?? 0 }} ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom">
            <div class="text-center">
                <div style="font-size: 0.875rem; color: #9ca3af; margin-bottom: 0.5rem;">Ваш уровень</div>
                <h2 style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--primary-color);">
                    @switch($bonusAccount->level ?? 'newbie')
                        @case('platinum') <i class="bi bi-gem"></i> Платина @break
                        @case('gold') <i class="bi bi-star-fill"></i> Золото @break
                        @case('silver') <i class="bi bi-award"></i> Серебро @break
                        @default <i class="bi bi-trophy"></i> Новичок
                    @endswitch
                </h2>
                <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                    @php
                        $nextLevel = ['newbie' => ['name' => 'Серебро', 'points' => 5000], 
                                      'silver' => ['name' => 'Золото', 'points' => 20000], 
                                      'gold' => ['name' => 'Платина', 'points' => 50000], 
                                      'platinum' => null];
                        $current = $nextLevel[$bonusAccount->level ?? 'newbie'] ?? null;
                    @endphp
                    @if($current)
                        До {{ $current['name'] }}: {{ $current['points'] - ($bonusAccount->total_earned ?? 0) }} баллов
                    @else
                        Максимальный уровень!
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom">
            <div class="text-center">
                <div style="font-size: 0.875rem; color: #9ca3af; margin-bottom: 0.5rem;">Всего заработано</div>
                <h2 style="font-size: 2rem; font-weight: 700; margin: 0; color: var(--success-color);">
                    {{ $bonusAccount->total_earned ?? 0 }}
                </h2>
                <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                    Потрачено: {{ $bonusAccount->total_spent ?? 0 }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Как работает программа -->
<div class="card-custom mb-4">
    <h5 class="mb-3"><i class="bi bi-info-circle"></i> Как работает бонусная программа</h5>
    <div class="row">
        <div class="col-md-3">
            <div class="text-center p-3">
                <div style="width: 60px; height: 60px; background: #eff6ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="bi bi-cart-check" style="font-size: 1.75rem; color: var(--primary-color);"></i>
                </div>
                <h6 style="font-weight: 600;">Совершайте покупки</h6>
                <p style="font-size: 0.875rem; color: #6b7280;">1 рубль = 1 балл</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center p-3">
                <div style="width: 60px; height: 60px; background: #f0fdf4; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="bi bi-piggy-bank" style="font-size: 1.75rem; color: var(--success-color);"></i>
                </div>
                <h6 style="font-weight: 600;">Накапливайте баллы</h6>
                <p style="font-size: 0.875rem; color: #6b7280;">За заказы и бонусы</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center p-3">
                <div style="width: 60px; height: 60px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="bi bi-percent" style="font-size: 1.75rem; color: var(--warning-color);"></i>
                </div>
                <h6 style="font-weight: 600;">Получайте скидки</h6>
                <p style="font-size: 0.875rem; color: #6b7280;">100 баллов = 100 ₽</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center p-3">
                <div style="width: 60px; height: 60px; background: #fce7f3; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="bi bi-gift" style="font-size: 1.75rem; color: #ec4899;"></i>
                </div>
                <h6 style="font-weight: 600;">Приглашайте друзей</h6>
                <p style="font-size: 0.875rem; color: #6b7280;">500 баллов за друга</p>
            </div>
        </div>
    </div>
</div>

<!-- Реферальная программа -->
<div class="card-custom mb-4">
    <h5 class="mb-3"><i class="bi bi-people"></i> Реферальная программа</h5>
    <div class="row">
        <div class="col-md-6">
            <div class="alert alert-info">
                <strong>Ваша реферальная ссылка:</strong>
                <div class="input-group mt-2">
                    <input type="text" class="form-control" value="{{ url('/') }}/ref/{{ $bonusAccount->referral_code ?? 'ABC123' }}" readonly id="refLink">
                    <button class="btn btn-outline-primary" onclick="copyRefLink()">
                        <i class="bi bi-clipboard"></i> Копировать
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row text-center">
                <div class="col-6">
                    <h3 style="color: var(--primary-color);">{{ $referralsCount ?? 0 }}</h3>
                    <div style="font-size: 0.875rem; color: #6b7280;">Приглашено друзей</div>
                </div>
                <div class="col-6">
                    <h3 style="color: var(--success-color);">{{ ($referralsCount ?? 0) * 500 }}</h3>
                    <div style="font-size: 0.875rem; color: #6b7280;">Заработано баллов</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- История операций -->
<div class="card-custom">
    <div class="card-header-custom">
        <h5 class="card-title-custom">История операций</h5>
    </div>
    
    @if($transactions->count() > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Операция</th>
                        <th>Сумма</th>
                        <th>Баланс</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $transaction->reason }}</td>
                            <td class="text-{{ $transaction->type == 'earn' ? 'success' : 'danger' }}">
                                {{ $transaction->type == 'earn' ? '+' : '-' }}{{ $transaction->amount }}
                            </td>
                            <td>{{ $transaction->balance_after ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-content-center mt-3">
            {{ $transactions->links() }}
        </div>
    @else
        <div class="text-center py-4 text-muted">
            <i class="bi bi-receipt" style="font-size: 3rem;"></i>
            <p class="mt-3">История операций пуста</p>
        </div>
    @endif
</div>

@push('scripts')
<script>
    function copyRefLink() {
        const input = document.getElementById('refLink');
        input.select();
        document.execCommand('copy');
        alert('Ссылка скопирована!');
    }
</script>
@endpush
@endsection
