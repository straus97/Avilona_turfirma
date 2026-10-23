@extends('cabinet.layouts.app')

@section('title', 'Запрос на проверку клиента')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Запрос на проверку клиента №{{ $clientRequest->id }}</h1>
    <p class="page-subtitle"><a href="{{ route('cabinet.admin.client-requests') }}">← К списку запросов</a></p>
</div>

<section class="card-custom mb-4" aria-labelledby="cr-data-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-data-title">Данные запроса</h2>
        <span class="badge {{ $clientRequest->status === 'approved' ? 'text-bg-success' : ($clientRequest->status === 'rejected' ? 'text-bg-warning' : 'text-bg-info') }}">{{ $clientRequest->managerStatusLabel() }}</span>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Менеджер</dt><dd class="col-sm-8 text-break">{{ $clientRequest->requester?->name ?? '—' }}</dd>
        <dt class="col-sm-4">Основание</dt><dd class="col-sm-8">{{ $clientRequest->contextLabel() }}@if($clientRequest->inquiry) · <a href="{{ route('cabinet.manager.inquiries.show', $clientRequest->inquiry) }}">открыть обращение</a>@endif</dd>
        <dt class="col-sm-4">Имя клиента</dt><dd class="col-sm-8 text-break">{{ $clientRequest->client_name }}</dd>
        <dt class="col-sm-4">Email</dt><dd class="col-sm-8 text-break">{{ $clientRequest->client_email ?? '—' }}</dd>
        <dt class="col-sm-4">Телефон</dt><dd class="col-sm-8 text-break">{{ $clientRequest->client_phone ?? '—' }}</dd>
        <dt class="col-sm-4">Отправлен</dt><dd class="col-sm-8">{{ $clientRequest->created_at?->format('d.m.Y H:i') }}</dd>
        @if(! $clientRequest->isPending())
            <dt class="col-sm-4">Решение</dt><dd class="col-sm-8">{{ $clientRequest->resolutionLabel() }} · {{ $clientRequest->resolver?->name ?? '—' }} · {{ $clientRequest->resolved_at?->format('d.m.Y H:i') }}</dd>
            @if($clientRequest->resolvedUser)
                <dt class="col-sm-4">Аккаунт клиента</dt><dd class="col-sm-8"><a href="{{ route('cabinet.admin.user-show', $clientRequest->resolvedUser) }}">{{ $clientRequest->resolvedUser->name }}</a></dd>
            @endif
            @if($clientRequest->resolution_note)
                <dt class="col-sm-4">Комментарий</dt><dd class="col-sm-8 text-break">{{ $clientRequest->resolution_note }}</dd>
            @endif
        @endif
    </dl>
</section>

@if($clientRequest->isPending())
<section class="card-custom mb-4" aria-labelledby="cr-match-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-match-title">Совпадения среди существующих аккаунтов</h2>
    </div>

    @if($candidates->isEmpty())
        <p class="text-muted">По email и телефону из запроса аккаунтов не найдено.</p>
    @else
        <ul class="list-group mb-3">
            @foreach($candidates as $entry)
                @php($candidate = $entry['user'])
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong class="text-break">{{ $candidate->name }}</strong>
                        <div class="small text-muted text-break">
                            {{ $candidate->hasTechnicalEmail() ? 'email не указан' : $candidate->email }}@if($candidate->phone) · {{ $candidate->phone }}@endif
                            · роль: {{ $candidate->roles->pluck('name')->implode(', ') ?: 'нет' }}
                            · {{ $candidate->is_active ? 'активен' : 'неактивен' }}
                        </div>
                        <div class="small"><span class="badge text-bg-info">{{ implode(', ', $entry['reasons']) }}</span>
                            @unless($entry['eligible'])<span class="badge text-bg-secondary">нельзя привязать: не активный турист</span>@endunless
                        </div>
                    </div>
                    @if($entry['eligible'])
                        <form method="POST" action="{{ route('cabinet.admin.client-requests.link', $clientRequest) }}" data-submit-once>
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $candidate->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Привязать клиента {{ $candidate->name }} к менеджеру">Привязать к менеджеру</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <form method="GET" action="{{ route('cabinet.admin.client-requests.show', $clientRequest) }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <label for="cr-search" class="form-label">Найти другого туриста (имя, email или телефон)</label>
            <input type="search" id="cr-search" name="client_q" value="{{ $searchTerm }}" class="form-control" minlength="2" maxlength="100">
        </div>
        <div class="col-12 col-md-auto"><button type="submit" class="btn btn-outline-primary">Найти</button></div>
    </form>

    @if($searchTerm !== '')
        <div class="mt-3">
            @if($searchResults->isEmpty())
                <p class="text-muted mb-0">По запросу «{{ $searchTerm }}» активных туристов не найдено.</p>
            @else
                <ul class="list-group">
                    @foreach($searchResults as $found)
                        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <strong class="text-break">{{ $found->name }}</strong>
                                <div class="small text-muted text-break">{{ $found->hasTechnicalEmail() ? 'email не указан' : $found->email }}@if($found->phone) · {{ $found->phone }}@endif</div>
                            </div>
                            <form method="POST" action="{{ route('cabinet.admin.client-requests.link', $clientRequest) }}" data-submit-once>
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $found->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Привязать клиента {{ $found->name }} к менеджеру">Привязать к менеджеру</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>

<section class="card-custom mb-4" aria-labelledby="cr-new-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-new-title">Создать нового клиента</h2>
    </div>
    <p class="text-muted">Аккаунт создаётся по данным запроса: пароль случайный и нигде не показывается, email не считается подтверждённым, письма не отправляются. Если email уже принадлежит аккаунту, создание будет отказано.</p>
    <form method="POST" action="{{ route('cabinet.admin.client-requests.create', $clientRequest) }}" data-submit-once>
        @csrf
        <button type="submit" class="btn btn-primary">Одобрить создание и привязать к менеджеру</button>
    </form>
</section>

<section class="card-custom mb-4" aria-labelledby="cr-reject-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-reject-title">Отклонить запрос</h2>
    </div>
    <form method="POST" action="{{ route('cabinet.admin.client-requests.reject', $clientRequest) }}" class="row g-3" data-submit-once>
        @csrf
        <div class="col-12">
            <label for="cr-note" class="form-label">Причина (её увидит менеджер)</label>
            <textarea id="cr-note" name="note" rows="2" maxlength="1200" class="form-control @error('note') is-invalid @enderror" required>{{ old('note') }}</textarea>
            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Не указывайте, существует ли такой аккаунт. От 5 до 300 символов, без email, телефонов и ссылок.</div>
        </div>
        <div class="col-12"><button type="submit" class="btn btn-outline-danger">Отклонить запрос</button></div>
    </form>
</section>
@endif

@if($history->isNotEmpty())
<section class="card-custom" aria-labelledby="cr-history-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="cr-history-title">История</h2>
    </div>
    <ul class="list-unstyled small mb-0">
        @foreach($history as $entry)
            <li class="mb-2"><strong>{{ $entry['type'] }}</strong> · {{ $entry['at']?->format('d.m.Y H:i') }} · {{ $entry['actor'] ?? 'система' }}</li>
        @endforeach
    </ul>
</section>
@endif
@endsection
