@extends('layouts.profile')

@section('title', 'Мои заявки - Авилона')

@section('content')
<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-clipboard-list text-primary"></i> Мои заявки
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.index') }}">Главная</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Личный кабинет</a></li>
                    <li class="breadcrumb-item active">Мои заявки</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">

        <!-- Фильтры -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-primary {{ !request('status') ? 'active' : '' }}">
                                <input type="radio" name="status" onclick="window.location='{{ route('profile.bookings') }}'">
                                <i class="fas fa-list"></i> Все
                            </label>
                            <label class="btn btn-outline-info {{ request('status') === 'new' ? 'active' : '' }}">
                                <input type="radio" name="status" onclick="window.location='{{ route('profile.bookings', ['status' => 'new']) }}'">
                                <i class="fas fa-plus-circle"></i> Новые
                            </label>
                            <label class="btn btn-outline-warning {{ request('status') === 'progress' ? 'active' : '' }}">
                                <input type="radio" name="status" onclick="window.location='{{ route('profile.bookings', ['status' => 'progress']) }}'">
                                <i class="fas fa-hourglass-half"></i> В обработке
                            </label>
                            <label class="btn btn-outline-success {{ request('status') === 'confirmed' ? 'active' : '' }}">
                                <input type="radio" name="status" onclick="window.location='{{ route('profile.bookings', ['status' => 'confirmed']) }}'">
                                <i class="fas fa-check-circle"></i> Подтверждены
                            </label>
                            <label class="btn btn-outline-secondary {{ request('status') === 'completed' ? 'active' : '' }}">
                                <input type="radio" name="status" onclick="window.location='{{ route('profile.bookings', ['status' => 'completed']) }}'">
                                <i class="fas fa-flag-checkered"></i> Завершены
                            </label>
                            <label class="btn btn-outline-danger {{ request('status') === 'cancelled' ? 'active' : '' }}">
                                <input type="radio" name="status" onclick="window.location='{{ route('profile.bookings', ['status' => 'cancelled']) }}'">
                                <i class="fas fa-times-circle"></i> Отменены
                            </label>
                        </div>
                        <a href="{{ route('bookings.create') }}" class="btn btn-primary float-right">
                            <i class="fas fa-plus"></i> Создать заявку
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Список заявок -->
        @if($bookings->count() > 0)
            <div class="row">
                @foreach($bookings as $booking)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 card-booking">
                            <div class="card-header bg-{{ $booking->status_color }} text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bookmark"></i>
                                    Заявка #{{ $booking->id }}
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="booking-info">
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                        <strong>Направление:</strong>
                                        <span>{{ $booking->destination_country }}</span>
                                        @if($booking->destination_city)
                                            <br><small class="text-muted ml-4">{{ $booking->destination_city }}</small>
                                        @endif
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar text-info"></i>
                                        <strong>Дата:</strong>
                                        <span>
                                            @if($booking->start_date)
                                                {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
                                            @else
                                                Не указана
                                            @endif
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-moon text-warning"></i>
                                        <strong>Ночей:</strong>
                                        <span>{{ $booking->nights }}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-users text-success"></i>
                                        <strong>Туристов:</strong>
                                        <span>{{ $booking->adults + $booking->children }}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-user-tie text-secondary"></i>
                                        <strong>Менеджер:</strong>
                                        @if($booking->manager)
                                            <span class="text-success">{{ $booking->manager->name }}</span>
                                        @else
                                            <span class="text-muted">Ожидание назначения</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge badge-{{ $booking->status_color }} badge-lg">
                                        {{ $booking->status_label }}
                                    </span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('bookings.show', $booking->id) }}" 
                                           class="btn btn-info" 
                                           title="Просмотр">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if(in_array($booking->status, ['new', 'progress']))
                                            <a href="{{ route('bookings.edit', $booking->id) }}" 
                                               class="btn btn-primary" 
                                               title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                        @if($booking->manager_id)
                                            <a href="{{ route('profile.chat', $booking->id) }}" 
                                               class="btn btn-success" 
                                               title="Чат">
                                                <i class="fas fa-comments"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Пагинация -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-center">
                        {{ $bookings->links() }}
                    </div>
                </div>
            </div>
        @else
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-5x text-muted mb-4"></i>
                            <h4 class="text-muted">Заявок не найдено</h4>
                            <p class="text-muted">
                                @if(request('status'))
                                    По выбранному фильтру заявок нет
                                @else
                                    У вас пока нет ни одной заявки
                                @endif
                            </p>
                            <a href="{{ route('bookings.create') }}" class="btn btn-primary btn-lg mt-3">
                                <i class="fas fa-plus"></i> Создать заявку
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection

@push('styles')
<style>
.card-booking {
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
    transition: all .3s;
    border: none;
}

.card-booking:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,.15);
}

.card-booking .card-header {
    border-radius: 10px 10px 0 0 !important;
    border-bottom: none;
}

.booking-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.info-item i {
    margin-top: 2px;
    width: 20px;
}

.info-item strong {
    min-width: 90px;
}

.badge-lg {
    font-size: 14px;
    padding: 8px 12px;
}

.btn-group-toggle .btn {
    border-radius: 5px !important;
    margin-right: 5px;
}

.btn-group-toggle .btn.active {
    box-shadow: 0 0 10px rgba(0,0,0,.2);
}
</style>
@endpush
