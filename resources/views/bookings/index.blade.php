@extends('layouts.main')

@section('title', 'Мои заявки - Авилона')
@section('meta_description', 'Список ваших заявок на туры')

@section('content')
<main>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        @if(auth()->user()->isAdmin())
                            Все заявки
                        @elseif(auth()->user()->isManager())
                            Мои клиенты
                        @else
                            Мои заявки
                        @endif
                    </h1>
                    <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Создать заявку
                    </a>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($bookings->count() > 0)
                    <!-- Фильтры по статусу -->
                    <div class="mb-4">
                        <div class="btn-group" role="group">
                            <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary {{ !request('status') ? 'active' : '' }}">
                                Все
                            </a>
                            <a href="{{ route('bookings.index', ['status' => 'new']) }}" class="btn btn-outline-primary {{ request('status') === 'new' ? 'active' : '' }}">
                                Новые
                            </a>
                            <a href="{{ route('bookings.index', ['status' => 'progress']) }}" class="btn btn-outline-warning {{ request('status') === 'progress' ? 'active' : '' }}">
                                В обработке
                            </a>
                            <a href="{{ route('bookings.index', ['status' => 'confirmed']) }}" class="btn btn-outline-success {{ request('status') === 'confirmed' ? 'active' : '' }}">
                                Подтверждены
                            </a>
                        </div>
                    </div>

                    <!-- Список заявок -->
                    <div class="row">
                        @foreach($bookings as $booking)
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-{{ $booking->status_color }} text-white d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="bi bi-bookmark-fill"></i> 
                                            Заявка #{{ $booking->id }}
                                        </span>
                                        <span class="badge bg-white text-{{ $booking->status_color }}">
                                            {{ $booking->status_label }}
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-geo-alt-fill text-primary"></i>
                                            {{ $booking->destination_country }}
                                        </h5>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3"></i> 
                                                {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указано' }}
                                            </small>
                                            <small class="text-muted ms-2">
                                                <i class="bi bi-moon-stars"></i> 
                                                {{ $booking->nights }} {{ str_plural($booking->nights, 'ночь', 'ночи', 'ночей') }}
                                            </small>
                                        </div>

                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-people-fill"></i> 
                                                {{ $booking->total_tourists }} {{ str_plural($booking->total_tourists, 'турист', 'туриста', 'туристов') }}
                                            </small>
                                        </div>

                                        @if(auth()->user()->isAdmin() || auth()->user()->isManager())
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> 
                                                    Клиент: {{ $booking->user->name }}
                                                </small>
                                            </div>
                                        @endif

                                        @if($booking->manager)
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-person-badge"></i> 
                                                    Менеджер: {{ $booking->manager->name }}
                                                </small>
                                            </div>
                                        @endif

                                        @if($booking->total_price)
                                            <div class="mt-3">
                                                <strong class="text-success">
                                                    <i class="bi bi-cash-stack"></i> 
                                                    {{ $booking->formatted_total_price }}
                                                </strong>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="card-footer bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                {{ $booking->created_at->diffForHumans() }}
                                            </small>
                                            <a href="{{ route('bookings.show', $booking) }}" class="btn btn-sm btn-primary">
                                                Подробнее <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Пагинация -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $bookings->links() }}
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle fs-1"></i>
                        <h4 class="mt-3">Заявок пока нет</h4>
                        <p>
                            @if(auth()->user()->isAdmin())
                                В системе пока нет заявок.
                            @elseif(auth()->user()->isManager())
                                Вам пока не назначены клиенты.
                            @else
                                Вы еще не создали ни одной заявки на тур.
                            @endif
                        </p>
                        @if(!auth()->user()->isManager() && !auth()->user()->isAdmin())
                            <a href="{{ route('bookings.create') }}" class="btn btn-primary mt-2">
                                Создать первую заявку
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection

@push('styles')
<style>
.btn-group .btn {
    border-radius: 0;
}
.btn-group .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}
.btn-group .btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}
</style>
@endpush
