@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Панель менеджера</h1>
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
                            <h3>{{ $assignedBookings }}</h3>
                            <p>{{ str_plural($assignedBookings, 'назначенная заявка', 'назначенные заявки', 'назначенных заявок') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-bookmark"></i>
                        </div>
                        <a href="{{ route('manager.bookings') }}" class="small-box-footer">
                            Все заявки <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $pendingBookings }}</h3>
                            <p>{{ str_plural($pendingBookings, 'новая', 'новые', 'новых') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <a href="{{ route('manager.bookings', ['status' => 'pending']) }}" class="small-box-footer">
                            Требуют обработки <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $confirmedBookings }}</h3>
                            <p>{{ str_plural($confirmedBookings, 'подтвержденная', 'подтвержденные', 'подтвержденных') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <a href="{{ route('manager.bookings', ['status' => 'confirmed']) }}" class="small-box-footer">
                            В работе <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $unreadMessages }}</h3>
                            <p>{{ str_plural($unreadMessages, 'новое сообщение', 'новых сообщения', 'новых сообщений') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <a href="{{ route('manager.chat') }}" class="small-box-footer">
                            Перейти к сообщениям <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3>{{ $totalClients }}</h3>
                            <p>{{ str_plural($totalClients, 'клиент', 'клиента', 'клиентов') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <a href="{{ route('manager.clients') }}" class="small-box-footer">
                            Все клиенты <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3>{{ $completedBookings }}</h3>
                            <p>{{ str_plural($completedBookings, 'завершенная', 'завершенные', 'завершенных') }}</p>
                        </div>
                        <div class="icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <a href="{{ route('manager.bookings', ['status' => 'completed']) }}" class="small-box-footer">
                            Завершенные <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="info-box bg-gradient-info">
                        <span class="info-box-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Статистика работы</span>
                            <span class="info-box-number">Подробная аналитика доступна</span>
                            <div class="progress">
                                <div class="progress-bar" style="width: {{ $assignedBookings > 0 ? min(($completedBookings / $assignedBookings) * 100, 100) : 0 }}%"></div>
                            </div>
                            <span class="progress-description">
                                @if($assignedBookings > 0)
                                    {{ round(($completedBookings / $assignedBookings) * 100, 1) }}% заявок завершено
                                @else
                                    Нет данных
                                @endif
                            </span>
                        </div>
                        <a href="{{ route('manager.statistics') }}" class="info-box-more">
                            Перейти к статистике <i class="fas fa-arrow-circle-right"></i>
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
                                <a href="{{ route('manager.bookings') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-list"></i> Все заявки
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($recentBookings->count() > 0)
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Клиент</th>
                                            <th>Тур</th>
                                            <th>Направление</th>
                                            <th>Дата поездки</th>
                                            <th>Статус</th>
                                            <th>Создана</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentBookings as $booking)
                                            <tr>
                                                <td><strong>#{{ $booking->id }}</strong></td>
                                                <td>
                                                    <i class="bi bi-person"></i>
                                                    {{ $booking->user->name ?? 'Неизвестно' }}
                                                </td>
                                                <td>{{ $booking->tour_name ?? 'Не указан' }}</td>
                                                <td>{{ $booking->destination ?? 'Не указано' }}</td>
                                                <td>
                                                    @if($booking->start_date)
                                                        {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
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
                                                <td>{{ $booking->created_at->format('d.m.Y H:i') }}</td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="{{ route('bookings.show', $booking->id) }}" 
                                                           class="btn btn-sm btn-info" title="Просмотр">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="{{ route('manager.chat', ['bookingId' => $booking->id]) }}" 
                                                           class="btn btn-sm btn-success" title="Чат">
                                                            <i class="bi bi-chat"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3">У вас пока нет назначенных заявок</h4>
                                    <p>Заявки будут появляться здесь после назначения администратором</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Быстрые действия -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-lightning"></i> Быстрые действия
                            </h3>
                        </div>
                        <div class="card-body">
                            <a href="{{ route('manager.bookings', ['status' => 'pending']) }}" class="btn btn-warning btn-block mb-2">
                                <i class="bi bi-hourglass-split"></i> Обработать новые заявки
                            </a>
                            <a href="{{ route('manager.chat') }}" class="btn btn-success btn-block mb-2">
                                <i class="bi bi-chat-dots"></i> Ответить на сообщения
                            </a>
                            <a href="{{ route('manager.clients') }}" class="btn btn-primary btn-block">
                                <i class="bi bi-people"></i> Мои клиенты
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card card-success card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-info-circle"></i> Информация
                            </h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Добро пожаловать, {{ $manager->name }}!</strong></p>
                            <p>В панели менеджера вы можете:</p>
                            <ul>
                                <li>Просматривать и обрабатывать назначенные заявки</li>
                                <li>Общаться с клиентами через встроенный чат</li>
                                <li>Управлять статусами заявок (подтверждение, отмена)</li>
                                <li>Просматривать статистику вашей работы</li>
                                <li>Управлять списком клиентов</li>
                            </ul>
                            <p class="mb-0">
                                <strong>Ваш статус:</strong> 
                                <span class="badge badge-success">
                                    <i class="bi bi-person-check"></i> Менеджер
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
