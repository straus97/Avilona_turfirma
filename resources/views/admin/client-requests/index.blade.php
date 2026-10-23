@extends('cabinet.layouts.app')

@section('title', 'Проверка клиентов')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Проверка клиентов</h1>
    <p class="page-subtitle">Запросы менеджеров на проверку клиента. Менеджер не видит, существует ли такой аккаунт: решение принимаете вы — привязать существующего туриста, создать нового или отклонить запрос.</p>
</div>

<nav class="mb-3 d-flex flex-wrap gap-2" aria-label="Фильтр запросов по состоянию">
    @foreach(['pending' => 'На проверке', 'approved' => 'Подтверждены', 'rejected' => 'Отклонены', 'all' => 'Все'] as $value => $label)
        <a href="{{ route('cabinet.admin.client-requests', ['status' => $value]) }}"
           class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline-primary' }}"
           @if($status === $value) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if($requests->isEmpty())
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-person-check',
        'title' => 'Запросов нет',
        'description' => 'В этом состоянии запросов на проверку клиентов нет.',
    ])
@else
<div class="card-custom">
    <ul class="list-group list-group-flush" aria-label="Запросы на проверку клиентов">
        @foreach($requests as $item)
            <li class="list-group-item">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                    <a href="{{ route('cabinet.admin.client-requests.show', $item) }}" class="fw-bold text-break">{{ $item->client_name }}</a>
                    <span class="badge {{ $item->status === 'approved' ? 'text-bg-success' : ($item->status === 'rejected' ? 'text-bg-warning' : 'text-bg-info') }}">{{ $item->managerStatusLabel() }}</span>
                </div>
                <div class="small text-muted text-break">
                    Менеджер: {{ $item->requester?->name ?? '—' }} · {{ $item->contextLabel() }} · {{ $item->created_at?->format('d.m.Y H:i') }}
                    @if($item->client_email) · {{ $item->client_email }}@endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
<div class="mt-3">{{ $requests->links() }}</div>
@endif
@endsection
