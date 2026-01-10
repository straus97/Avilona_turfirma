@extends('layouts.profile')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Админ панель</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Статистические карточки -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $totalUsers }}</h3>
                            <p>{{ str_plural($totalUsers, 'пользователь', 'пользователя', 'пользователей') }}</p>
                        </div>
                        <div class="icon"><i class="bi bi-people"></i></div>
                        <a href="{{ route('admin.users') }}" class="small-box-footer">
                            Управление <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $totalBookings }}</h3>
                            <p>{{ str_plural($totalBookings, 'заявка', 'заявки', 'заявок') }}</p>
                        </div>
                        <div class="icon"><i class="bi bi-bookmark-fill"></i></div>
                        <a href="{{ route('admin.bookings') }}" class="small-box-footer">
                            Управление <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $unassignedBookings }}</h3>
                            <p>без менеджера</p>
                        </div>
                        <div class="icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <a href="{{ route('admin.bookings', ['manager' => 'unassigned']) }}" class="small-box-footer">
                            Назначить <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ number_format($totalRevenue, 0, ',', ' ') }} ₽</h3>
                            <p>Общий доход</p>
                        </div>
                        <div class="icon"><i class="bi bi-currency-dollar"></i></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Пользователи по ролям -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Пользователи по ролям</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Роль</th>
                                        <th>Пользователей</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($usersByRole as $role)
                                        <tr>
                                            <td>
                                                @if($role->role === 'admin')
                                                    <span class="badge badge-danger">Администратор</span>
                                                @elseif($role->role === 'manager')
                                                    <span class="badge badge-primary">Менеджер</span>
                                                @else
                                                    <span class="badge badge-secondary">Турист</span>
                                                @endif
                                            </td>
                                            <td><strong>{{ $role->users_count }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Заявки по статусам -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Заявки по статусам</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td><span class="badge badge-warning">В обработке</span></td>
                                        <td><strong>{{ $bookingsByStatus['pending'] }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-info">Подтверждено</span></td>
                                        <td><strong>{{ $bookingsByStatus['confirmed'] }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-success">Завершено</span></td>
                                        <td><strong>{{ $bookingsByStatus['completed'] }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-danger">Отменено</span></td>
                                        <td><strong>{{ $bookingsByStatus['cancelled'] }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Последние пользователи и заявки -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Последние пользователи</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped">
                                <tbody>
                                    @foreach($recentUsers as $user)
                                        <tr>
                                            <td>
                                                <i class="bi bi-person-circle"></i>
                                                {{ $user->name }}
                                                <br>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </td>
                                            <td class="text-right">
                                                @foreach($user->roles as $role)
                                                    @if($role->role === 'admin')
                                                        <span class="badge badge-danger">Админ</span>
                                                    @elseif($role->role === 'manager')
                                                        <span class="badge badge-primary">Менеджер</span>
                                                    @else
                                                        <span class="badge badge-secondary">Турист</span>
                                                    @endif
                                                @endforeach
                                                <br>
                                                <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Последние заявки</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped">
                                <tbody>
                                    @foreach($recentBookings as $booking)
                                        <tr>
                                            <td>
                                                <strong>#{{ $booking->id }}</strong> - {{ $booking->user->name ?? 'Неизвестно' }}
                                                <br>
                                                <small class="text-muted">{{ $booking->tour_name ?? 'Без названия' }}</small>
                                            </td>
                                            <td class="text-right">
                                                @if($booking->status === 'pending')
                                                    <span class="badge badge-warning">В обработке</span>
                                                @elseif($booking->status === 'confirmed')
                                                    <span class="badge badge-info">Подтверждена</span>
                                                @elseif($booking->status === 'completed')
                                                    <span class="badge badge-success">Завершена</span>
                                                @elseif($booking->status === 'cancelled')
                                                    <span class="badge badge-danger">Отменена</span>
                                                @endif
                                                <br>
                                                <small class="text-muted">
                                                    {{ $booking->manager ? $booking->manager->name : 'Не назначен' }}
                                                </small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Менеджеры -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Менеджеры и нагрузка</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Менеджер</th>
                                        <th>Email</th>
                                        <th>Назначено заявок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($managers as $manager)
                                        <tr>
                                            <td><i class="bi bi-person-badge"></i> {{ $manager->name }}</td>
                                            <td>{{ $manager->email }}</td>
                                            <td>
                                                <span class="badge badge-primary">{{ $manager->managed_bookings_count }}</span>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.bookings', ['manager' => $manager->id]) }}" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> Заявки
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
