@extends('layouts.profile')

@section('title', 'Личный кабинет - Авилона')

@section('content')
<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-tachometer-alt text-primary"></i> Добро пожаловать, {{ Auth::user()->name }}!
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.index') }}">Главная</a></li>
                    <li class="breadcrumb-item active">Личный кабинет</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        
        <!-- Статистика -->
        <div class="row">
            <!-- Всего заявок -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-info">
                    <div class="inner">
                        <h3>{{ $bookingsCount }}</h3>
                        <p>{{ str_plural($bookingsCount, 'заявка', 'заявки', 'заявок') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <a href="{{ route('profile.bookings') }}" class="small-box-footer">
                        Все заявки <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <!-- Новые заявки -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-primary">
                    <div class="inner">
                        <h3>{{ $pendingBookings }}</h3>
                        <p>{{ str_plural($pendingBookings, 'новая', 'новые', 'новых') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <a href="{{ route('profile.bookings', ['status' => 'new']) }}" class="small-box-footer">
                        Просмотреть <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <!-- Подтвержденные заявки -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-success">
                    <div class="inner">
                        <h3>{{ $confirmedBookings }}</h3>
                        <p>{{ str_plural($confirmedBookings, 'подтверждена', 'подтверждены', 'подтверждено') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <a href="{{ route('profile.bookings', ['status' => 'confirmed']) }}" class="small-box-footer">
                        Просмотреть <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <!-- Непрочитанные сообщения -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-gradient-warning">
                    <div class="inner">
                        <h3>{{ $unreadMessages }}</h3>
                        <p>{{ str_plural($unreadMessages, 'сообщение', 'сообщения', 'сообщений') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <a href="{{ route('profile.chat') }}" class="small-box-footer">
                        Открыть чат <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Быстрые действия -->
        <div class="row">
            <div class="col-12">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i> Быстрые действия
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('bookings.create') }}" class="btn btn-app btn-primary w-100">
                                    <i class="fas fa-plus-circle"></i> Создать заявку
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('profile.bookings') }}" class="btn btn-app btn-info w-100">
                                    <i class="fas fa-list"></i> Мои заявки
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('profile.chat') }}" class="btn btn-app btn-success w-100">
                                    <i class="fas fa-comments"></i> Чат
                                    @if($unreadMessages > 0)
                                        <span class="badge badge-danger">{{ $unreadMessages }}</span>
                                    @endif
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route('profile.documents') }}" class="btn btn-app btn-warning w-100">
                                    <i class="fas fa-file-alt"></i> Документы
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Последние заявки -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-transparent">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i> Последние заявки
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('profile.bookings') }}" class="btn btn-tool btn-sm">
                                <i class="fas fa-eye"></i> Все заявки
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($latestBookings->count() > 0)
                            <div class="table-responsive">
                                <table class="table m-0">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Направление</th>
                                            <th>Дата</th>
                                            <th>Статус</th>
                                            <th>Менеджер</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($latestBookings as $booking)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('bookings.show', $booking->id) }}">
                                                        #{{ $booking->id }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <strong>{{ $booking->destination_country }}</strong>
                                                    @if($booking->destination_city)
                                                        <br><small class="text-muted">{{ $booking->destination_city }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $booking->start_date ? \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') : '-' }}
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $booking->status_color }}">
                                                        {{ $booking->status_label }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($booking->manager)
                                                        <span class="text-success">
                                                            <i class="fas fa-user-tie"></i>
                                                            {{ $booking->manager->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">
                                                            <i class="fas fa-clock"></i>
                                                            Ожидание
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('bookings.show', $booking->id) }}" 
                                                           class="btn btn-info" 
                                                           title="Просмотр">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        @if($booking->manager_id)
                                                            <a href="{{ route('profile.chat', $booking->id) }}" 
                                                               class="btn btn-success" 
                                                               title="Чат">
                                                                <i class="fas fa-comments"></i>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>У вас пока нет заявок</p>
                                <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Создать первую заявку
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Полезная информация -->
        <div class="row">
            <div class="col-md-6">
                <div class="card card-widget widget-user-2">
                    <div class="widget-user-header bg-gradient-info">
                        <h3 class="widget-user-username">Как это работает?</h3>
                        <h5 class="widget-user-desc">Процесс бронирования тура</h5>
                    </div>
                    <div class="card-body">
                        <ol class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success"></i>
                                <strong>Шаг 1:</strong> Создайте заявку на тур
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success"></i>
                                <strong>Шаг 2:</strong> Менеджер свяжется с вами
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success"></i>
                                <strong>Шаг 3:</strong> Обсудите детали в чате
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success"></i>
                                <strong>Шаг 4:</strong> Подтвердите бронирование
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success"></i>
                                <strong>Шаг 5:</strong> Получите документы и отправляйтесь в путешествие!
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-widget widget-user-2">
                    <div class="widget-user-header bg-gradient-success">
                        <h3 class="widget-user-username">Нужна помощь?</h3>
                        <h5 class="widget-user-desc">Мы всегда на связи</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-phone text-primary"></i>
                            <strong>Телефон:</strong>
                            <a href="tel:+74951234567">+7 (495) 123-45-67</a>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-envelope text-primary"></i>
                            <strong>Email:</strong>
                            <a href="mailto:info@avilona.ru">info@avilona.ru</a>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-clock text-primary"></i>
                            <strong>Режим работы:</strong>
                            Пн-Пт: 9:00-20:00, Сб-Вс: 10:00-18:00
                        </div>
                        <div>
                            <a href="{{ route('contact.index') }}" class="btn btn-success btn-block">
                                <i class="fas fa-paper-plane"></i> Написать нам
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
.small-box {
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
    transition: all .3s;
}

.small-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,.2);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
}

.btn-app {
    height: auto;
    padding: 15px;
    font-size: 14px;
    border-radius: 10px;
    transition: all .3s;
}

.btn-app:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,.2);
}

.btn-app i {
    font-size: 24px;
    display: block;
    margin-bottom: 5px;
}

.card {
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,.05);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.widget-user-2 .widget-user-header {
    border-radius: 10px 10px 0 0;
}
</style>
@endpush
