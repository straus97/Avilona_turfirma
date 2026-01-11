@extends('cabinet.layouts.app')

@section('title', 'Мои заявки')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Мои заявки</h1>
    <p class="page-subtitle">Управляйте своими заявками на туры</p>
    <div class="page-actions">
        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Создать заявку
        </a>
    </div>
</div>

<!-- Фильтры -->
<div class="card-custom mb-4">
    <form method="GET" action="{{ route('cabinet.bookings') }}">
        <div class="row g-3">
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Все статусы</option>
                    <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>Новые</option>
                    <option value="progress" {{ request('status') == 'progress' ? 'selected' : '' }}>В работе</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Подтверждены</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Завершены</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Отменены</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="country" class="form-control" placeholder="Страна" value="{{ request('country') }}">
            </div>
            <div class="col-md-3">
                <input type="date" name="date_from" class="form-control" placeholder="Дата от" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-fill">
                        <i class="bi bi-search"></i> Найти
                    </button>
                    <a href="{{ route('cabinet.bookings') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Статистика -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card-custom text-center">
            <h3 class="mb-1" style="color: var(--primary-color);">{{ $bookings->total() }}</h3>
            <div style="font-size: 0.875rem; color: #6b7280;">Всего заявок</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <h3 class="mb-1" style="color: var(--warning-color);">{{ $activeCount }}</h3>
            <div style="font-size: 0.875rem; color: #6b7280;">Активных</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <h3 class="mb-1" style="color: var(--success-color);">{{ $confirmedCount }}</h3>
            <div style="font-size: 0.875rem; color: #6b7280;">Подтверждено</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <h3 class="mb-1" style="color: var(--info-color);">{{ $completedCount }}</h3>
            <div style="font-size: 0.875rem; color: #6b7280;">Завершено</div>
        </div>
    </div>
</div>

<!-- Список заявок -->
@if($bookings->count() > 0)
    <div class="row">
        @foreach($bookings as $booking)
            <div class="col-md-6 mb-4">
                @include('cabinet.components.booking-card', ['booking' => $booking])
            </div>
        @endforeach
    </div>

    <!-- Пагинация -->
    <div class="d-flex justify-content-center">
        {{ $bookings->links() }}
    </div>
@else
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-inbox',
        'title' => 'Заявок не найдено',
        'description' => 'Попробуйте изменить фильтры или создайте новую заявку',
        'actionUrl' => route('bookings.create'),
        'actionText' => 'Создать заявку'
    ])
@endif
@endsection
