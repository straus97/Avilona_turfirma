@extends('cabinet.layouts.app')

@section('title', 'Проверка клиентов')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Проверка клиентов</h1>
    <p class="page-subtitle">Если нужного клиента нет в вашем списке, отправьте его данные администратору. Он проверит клиента и откроет вам доступ. Ответ на запрос не зависит от того, есть ли такой клиент в системе.</p>
</div>

<section class="card-custom mb-4" aria-labelledby="cr-form-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-form-title">Новый запрос</h2>
    </div>

    <form method="POST" action="{{ route('cabinet.manager.client-requests.store') }}" class="row g-3" data-submit-once>
        @csrf
        <div class="col-12 col-md-4">
            <label for="cr-name" class="form-label">Имя клиента <span class="booking-req">*</span></label>
            <input type="text" id="cr-name" name="client_name" value="{{ old('client_name') }}" class="form-control @error('client_name') is-invalid @enderror" maxlength="255" required autocomplete="off">
            @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label for="cr-email" class="form-label">Email (необязательно)</label>
            <input type="email" id="cr-email" name="client_email" value="{{ old('client_email') }}" class="form-control @error('client_email') is-invalid @enderror" maxlength="255" autocomplete="off">
            @error('client_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label for="cr-phone" class="form-label">Телефон (необязательно)</label>
            <input type="text" id="cr-phone" name="client_phone" value="{{ old('client_phone') }}" class="form-control @error('client_phone') is-invalid @enderror" maxlength="20" autocomplete="off">
            @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Отправить запрос на проверку клиента</button>
            <p class="text-muted small mt-2 mb-0">Пароль не создаётся и никому не отправляется. Заявка при этом не создаётся: после решения администратора клиент появится в списке «Мои клиенты» и в форме новой заявки.</p>
        </div>
    </form>
</section>

<section class="card-custom" aria-labelledby="cr-list-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-list-title">Мои запросы</h2>
    </div>

    @if($requests->isEmpty())
        @include('cabinet.components.empty-state', [
            'icon' => 'bi-person-check',
            'title' => 'Запросов пока нет',
            'description' => 'Отправленные запросы и их состояние появятся здесь.',
        ])
    @else
        <ul class="list-group list-group-flush" aria-label="Мои запросы на проверку клиентов">
            @foreach($requests as $item)
                <li class="list-group-item">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                        <strong class="text-break">{{ $item->client_name }}</strong>
                        <span class="badge {{ $item->status === 'approved' ? 'text-bg-success' : ($item->status === 'rejected' ? 'text-bg-warning' : 'text-bg-info') }}">{{ $item->managerStatusLabel() }}</span>
                    </div>
                    <div class="small text-muted text-break">
                        {{ $item->contextLabel() }} · отправлен {{ $item->created_at?->format('d.m.Y H:i') }}
                        @if($item->client_email) · {{ $item->client_email }}@endif
                    </div>
                    @if($item->status === 'approved')
                        <div class="small mt-1">
                            Клиент подтверждён и доступен вам@if($item->access_expires_at) до {{ $item->access_expires_at->format('d.m.Y') }}@endif.
                            <a href="{{ route('bookings.create') }}">Оформить заявку</a> ·
                            <a href="{{ route('cabinet.manager.clients') }}">Мои клиенты</a>
                        </div>
                    @elseif($item->status === 'rejected')
                        <div class="small mt-1">Запрос отклонён@if($item->resolution_note). Комментарий администратора: {{ $item->resolution_note }}@endif</div>
                    @else
                        <div class="small mt-1">Администратор ещё не принял решение. Повторно отправлять запрос не нужно.</div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if($requests->hasPages())
            <div class="mt-3">{{ $requests->links() }}</div>
        @endif
    @endif
</section>
@endsection
