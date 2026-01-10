@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Личный кабинет</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Информационные карточки -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $bookingsCount }}</h3>
                            <p>{{ str_plural($bookingsCount, 'заявка', 'заявки', 'заявок') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-bookmark"></i>
                        </div>
                        <a href="{{ route('profile.bookings') }}" class="small-box-footer">
                            Все заявки <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $activeBookings }}</h3>
                            <p>{{ str_plural($activeBookings, 'активная', 'активные', 'активных') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <a href="{{ route('profile.bookings') }}" class="small-box-footer">
                            Подробнее <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $completedBookings }}</h3>
                            <p>{{ str_plural($completedBookings, 'завершенная', 'завершенные', 'завершенных') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <a href="{{ route('profile.bookings') }}" class="small-box-footer">
                            Подробнее <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $unreadMessages }}</h3>
                            <p>{{ str_plural($unreadMessages, 'непрочитанное', 'непрочитанных', 'непрочитанных') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <a href="{{ route('profile.chat') }}" class="small-box-footer">
                            Перейти к сообщениям <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Последние заявки -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Последние заявки</h3>
                            <div class="card-tools">
                                <a href="{{ route('bookings.create') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle"></i> Создать заявку
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($recentBookings->count() > 0)
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Тур</th>
                                            <th>Направление</th>
                                            <th>Дата поездки</th>
                                            <th>Статус</th>
                                            <th>Менеджер</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentBookings as $booking)
                                            <tr>
                                                <td>#{{ $booking->id }}</td>
                                                <td>{{ $booking->tour_name ?? 'Не указан' }}</td>
                                                <td>{{ $booking->destination ?? 'Не указано' }}</td>
                                                <td>{{ $booking->start_date ? \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') : '-' }}</td>
                                                <td>
                                                    @if($booking->status === 'pending')
                                                        <span class="badge badge-warning">В обработке</span>
                                                    @elseif($booking->status === 'confirmed')
                                                        <span class="badge badge-info">Подтверждена</span>
                                                    @elseif($booking->status === 'completed')
                                                        <span class="badge badge-success">Завершена</span>
                                                    @elseif($booking->status === 'cancelled')
                                                        <span class="badge badge-danger">Отменена</span>
                                                    @endif
                                                </td>
                                                <td>{{ $booking->manager->name ?? 'Не назначен' }}</td>
                                                <td>
                                                    <a href="{{ route('bookings.show', $booking->id) }}" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">У вас пока нет заявок</p>
                                    <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Создать первую заявку
                                    </a>
                                </div>
                            @endif
                        </div>
                        @if($recentBookings->count() > 0)
                            <div class="card-footer clearfix">
                                <a href="{{ route('profile.bookings') }}" class="btn btn-sm btn-secondary float-right">
                                    Все заявки
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Приветственная информация -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-info-circle"></i> Полезная информация
                            </h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Добро пожаловать, {{ $user->name }}!</strong></p>
                            <p>В личном кабинете вы можете:</p>
                            <ul>
                                <li>Создавать и отслеживать заявки на туры</li>
                                <li>Общаться с вашим менеджером через чат</li>
                                <li>Просматривать и загружать документы</li>
                                <li>Редактировать свой профиль</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-success card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-person"></i> Ваш профиль
                            </h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Имя:</strong> {{ $user->name }}</p>
                            <p><strong>Email:</strong> {{ $user->email }}</p>
                            <p><strong>Телефон:</strong> {{ $user->phone ?? 'Не указан' }}</p>
                            <p><strong>Дата регистрации:</strong> {{ $user->created_at->format('d.m.Y') }}</p>
                            <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-pencil"></i> Редактировать профиль
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
