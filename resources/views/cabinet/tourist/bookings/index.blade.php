@extends('cabinet.layouts.app')

@section('title', 'Мои заявки')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
@php
    $statusLabels = [
        'new' => 'Новые',
        'progress' => 'В обработке',
        'confirmed' => 'Подтверждены',
        'completed' => 'Завершены',
        'cancelled' => 'Отменены',
    ];
    $hasFilters = request()->filled('status') || request()->filled('country') || request()->filled('date_from');

    // Строгий разбор date_from: не роняем рендер на мусорном GET-параметре и не
    // отдаём невалидную строку в нативный input[type=date].
    $rawDateFrom = (string) request('date_from', '');
    $parsedDateFrom = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDateFrom);
    $dateFromErrors = \DateTimeImmutable::getLastErrors();
    $dateFromValid = $rawDateFrom !== '' && $parsedDateFrom !== false
        && ($dateFromErrors === false || ($dateFromErrors['warning_count'] === 0 && $dateFromErrors['error_count'] === 0));
    $dateFromInputValue = $dateFromValid ? $parsedDateFrom->format('Y-m-d') : '';
@endphp

<div class="page-header">
    <h1 class="page-title">Мои заявки</h1>
    <p class="page-subtitle">Все ваши заявки на подбор тура и их статусы</p>
    <div class="page-actions">
        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> Создать заявку
        </a>
    </div>
</div>

@if($totalCount > 0)
    {{-- Сводка по аккаунту (без учёта фильтров) --}}
    <div class="tc-metric-strip">
        <div class="tc-metric-strip__item">
            <div class="tc-metric-strip__value">{{ $totalCount }}</div>
            <div class="tc-metric-strip__label">Всего</div>
        </div>
        <div class="tc-metric-strip__item">
            <div class="tc-metric-strip__value">{{ $activeCount }}</div>
            <div class="tc-metric-strip__label">Активные</div>
        </div>
        <div class="tc-metric-strip__item">
            <div class="tc-metric-strip__value">{{ $confirmedCount }}</div>
            <div class="tc-metric-strip__label">Подтверждено</div>
        </div>
        <div class="tc-metric-strip__item">
            <div class="tc-metric-strip__value">{{ $completedCount }}</div>
            <div class="tc-metric-strip__label">Завершено</div>
        </div>
    </div>

    {{-- Фильтры --}}
    <div class="card-custom">
        <form method="GET" action="{{ route('cabinet.bookings') }}">
            <div class="tc-filter__grid">
                <div>
                    <label for="filter-status" class="form-label">Статус</label>
                    <select name="status" id="filter-status" class="form-select" onchange="this.form.submit()">
                        <option value="">Все статусы</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-country" class="form-label">Страна</label>
                    <input type="text" name="country" id="filter-country" class="form-control"
                           placeholder="Например: Турция" value="{{ request('country') }}">
                </div>
                <div>
                    <label for="filter-date-from" class="form-label">Вылет не раньше</label>
                    <input type="date" name="date_from" id="filter-date-from" class="form-control"
                           value="{{ $dateFromInputValue }}">
                </div>
                <div class="tc-filter__actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel" aria-hidden="true"></i> Применить
                    </button>
                    @if($hasFilters)
                        <a href="{{ route('cabinet.bookings') }}" class="btn btn-outline-secondary">Сбросить</a>
                    @endif
                </div>
            </div>

            @if($hasFilters)
                <div class="tc-active-filters">
                    <span class="tc-active-filters__label">Активные фильтры:</span>
                    @if(request()->filled('status'))
                        <span class="tc-chip">Статус: {{ $statusLabels[request('status')] ?? request('status') }}</span>
                    @endif
                    @if(request()->filled('country'))
                        <span class="tc-chip">Страна: {{ request('country') }}</span>
                    @endif
                    @if(request()->filled('date_from'))
                        <span class="tc-chip">Вылет с {{ $dateFromValid ? $parsedDateFrom->format('d.m.Y') : $rawDateFrom }}</span>
                    @endif
                </div>
            @endif
        </form>
    </div>
@endif

{{-- Список заявок --}}
@if($bookings->count() > 0)
    <div class="row">
        @foreach($bookings as $booking)
            <div class="col-md-6 mb-4">
                @include('cabinet.components.booking-card', ['booking' => $booking])
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center">
        {{ $bookings->links() }}
    </div>
@elseif($totalCount > 0)
    {{-- Аккаунт не пуст, но под фильтры ничего не попало --}}
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-funnel',
        'title' => 'Под выбранные фильтры ничего не нашлось',
        'description' => 'Попробуйте изменить условия или сбросить фильтры.',
        'actionUrl' => route('cabinet.bookings'),
        'actionText' => 'Сбросить фильтры',
    ])
@else
    {{-- Заявок нет вообще --}}
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-journal-plus',
        'title' => 'У вас пока нет заявок',
        'description' => 'Оставьте первую заявку — менеджер подберёт тур и свяжется с вами.',
        'actionUrl' => route('bookings.create'),
        'actionText' => 'Создать заявку',
    ])
@endif
@endsection
