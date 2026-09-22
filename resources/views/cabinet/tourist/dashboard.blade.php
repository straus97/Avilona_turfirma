@extends('cabinet.layouts.app')

@section('title', 'Главная')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Здравствуйте, {{ Auth::user()->name }}!</h1>
    <p class="page-subtitle">Обзор ваших заявок, поездок и сообщений от менеджера</p>
</div>

@if($upcomingTrip)
    {{-- Приоритет 1: подтверждённая ближайшая поездка --}}
    <div class="tc-trip">
        <div class="tc-trip__icon">
            <i class="bi bi-airplane-fill" aria-hidden="true"></i>
        </div>
        <div class="tc-trip__body">
            <div class="tc-trip__eyebrow">Ближайшая поездка</div>
            <h2 class="tc-trip__title">
                {{ $upcomingTrip->destination_country }}@if($upcomingTrip->destination_city), {{ $upcomingTrip->destination_city }}@endif
            </h2>
            <div class="tc-trip__meta">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                Вылет {{ $upcomingTrip->start_date->format('d.m.Y') }}
                <span class="mx-1">•</span>
                Заявка #{{ $upcomingTrip->id }}
            </div>
        </div>
        <div class="tc-trip__action">
            <a href="{{ route('bookings.show', $upcomingTrip->id) }}" class="btn btn-primary">
                Подробнее о поездке
            </a>
        </div>
    </div>
@elseif($bookingsCount === 0)
    {{-- Приоритет 1 (нет заявок): честный старт --}}
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-compass',
        'title' => 'Начните планировать поездку',
        'description' => 'У вас пока нет заявок. Оставьте заявку — менеджер подберёт тур и свяжется с вами.',
        'actionUrl' => route('bookings.create'),
        'actionText' => 'Оставить заявку',
    ])
@endif

{{-- Приоритет 2: ключевые показатели --}}
<div class="tc-summary">
    <div class="tc-metric">
        <div class="tc-metric__icon"><i class="bi bi-journal-text" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value">{{ $bookingsCount }}</div>
            <div class="tc-metric__label">Всего заявок</div>
        </div>
    </div>
    <div class="tc-metric">
        <div class="tc-metric__icon tc-metric__icon--warning"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value">{{ $activeBookings }}</div>
            <div class="tc-metric__label">Активные заявки</div>
        </div>
    </div>
    <div class="tc-metric">
        <div class="tc-metric__icon tc-metric__icon--success"><i class="bi bi-check-circle" aria-hidden="true"></i></div>
        <div>
            <div class="tc-metric__value">{{ $completedBookings }}</div>
            <div class="tc-metric__label">Завершённых поездок</div>
        </div>
    </div>
</div>

{{-- Приоритет 3: что можно сделать сейчас --}}
<div class="card-custom">
    <div class="card-header-custom">
        <h2 class="card-title-custom">Быстрые действия</h2>
    </div>
    <div class="tc-quick-actions">
        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> Новая заявка
        </a>
        @if($unreadMessagesCount > 0)
            <a href="{{ route('cabinet.chat') }}" class="btn btn-outline-primary">
                <i class="bi bi-chat-dots" aria-hidden="true"></i>
                Непрочитанные сообщения
                <span class="badge bg-danger ms-1">{{ $unreadMessagesCount }}</span>
            </a>
        @else
            <a href="{{ route('cabinet.chat') }}" class="btn btn-outline-secondary">
                <i class="bi bi-chat-dots" aria-hidden="true"></i> Чат с менеджером
            </a>
        @endif
        <a href="{{ route('cabinet.documents.personal') }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-person" aria-hidden="true"></i> Мои документы
        </a>
    </div>
</div>

{{-- Приоритет 4: последние заявки --}}
<div class="card-custom">
    <div class="card-header-custom">
        <h2 class="card-title-custom">Последние заявки</h2>
        @if($latestBookings->count() > 0)
            <a href="{{ route('cabinet.bookings') }}" class="btn btn-sm btn-outline-primary">
                Все заявки
            </a>
        @endif
    </div>

    @if($latestBookings->count() > 0)
        <div class="row">
            @foreach($latestBookings as $booking)
                <div class="col-md-6 col-lg-4 mb-3">
                    @include('cabinet.components.booking-card', ['booking' => $booking])
                </div>
            @endforeach
        </div>
    @else
        <p class="text-muted mb-0">
            Здесь появятся ваши заявки после оформления.
            <a href="{{ route('bookings.create') }}">Оставить заявку</a>.
        </p>
    @endif
</div>
@endsection
