@extends('cabinet.layouts.app')

@section('title', 'Главная')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Добро пожаловать, {{ Auth::user()->name }}!</h1>
    <p class="page-subtitle">Управляйте своими поездками и следите за заявками</p>
</div>

<!-- Статистика -->
<div class="row mb-4">
    <div class="col-md-4">
        <x-cabinet.stat-card 
            title="Всего заявок" 
            :value="$bookingsCount" 
            icon="bi-journal-text" 
            color="primary" 
        />
    </div>
    <div class="col-md-4">
        <x-cabinet.stat-card 
            title="Активных" 
            :value="$activeBookings" 
            icon="bi-hourglass-split" 
            color="warning" 
        />
    </div>
    <div class="col-md-4">
        <x-cabinet.stat-card 
            title="Завершенных" 
            :value="$completedBookings" 
            icon="bi-check-circle" 
            color="success" 
        />
    </div>
</div>

@if($upcomingTrip)
<!-- Ближайшая поездка -->
<div class="card-custom mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <div class="d-flex align-items-center gap-3">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-airplane-fill" style="font-size: 2rem;"></i>
        </div>
        <div style="flex: 1;">
            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.25rem;">Ближайшая поездка</div>
            <h4 style="margin: 0; font-weight: 700;">{{ $upcomingTrip->destination_country }}@if($upcomingTrip->destination_city), {{ $upcomingTrip->destination_city }}@endif</h4>
            <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">
                <i class="bi bi-calendar3"></i> {{ $upcomingTrip->start_date->format('d.m.Y') }}
            </div>
        </div>
        <a href="{{ route('bookings.show', $upcomingTrip->id) }}" class="btn btn-light">
            Подробнее
        </a>
    </div>
</div>
@endif

<!-- Быстрые действия -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card-custom">
            <div class="card-header-custom">
                <h5 class="card-title-custom">Быстрые действия</h5>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Новая заявка
                </a>
                <a href="{{ route('cabinet.bookings') }}" class="btn btn-outline-primary">
                    <i class="bi bi-journal-text"></i> Мои заявки
                </a>
                @if($unreadMessagesCount > 0)
                    <a href="{{ route('cabinet.chat') }}" class="btn btn-outline-danger">
                        <i class="bi bi-chat-dots"></i> Непрочитанные сообщения 
                        <span class="badge bg-danger">{{ $unreadMessagesCount }}</span>
                    </a>
                @else
                    <a href="{{ route('cabinet.chat') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-chat-dots"></i> Чат с менеджером
                    </a>
                @endif
                <a href="{{ route('cabinet.documents.personal') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-person"></i> Мои документы
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Последние заявки -->
<div class="card-custom">
    <div class="card-header-custom">
        <h5 class="card-title-custom">Последние заявки</h5>
        <a href="{{ route('cabinet.bookings') }}" class="btn btn-sm btn-outline-primary">
            Все заявки
        </a>
    </div>
    
    @if($latestBookings->count() > 0)
        <div class="row">
            @foreach($latestBookings as $booking)
                <div class="col-md-6 col-lg-4 mb-3">
                    <x-cabinet.booking-card :booking="$booking" />
                </div>
            @endforeach
        </div>
    @else
        <x-cabinet.empty-state 
            icon="bi-journal-plus"
            title="У вас пока нет заявок"
            description="Создайте первую заявку и начните планировать свой отпуск"
            actionUrl="{{ route('bookings.create') }}"
            actionText="Создать заявку"
        />
    @endif
</div>
@endsection
