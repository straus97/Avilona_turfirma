@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Мои заявки</h1>
                </div>
                <div class="col-sm-6">
                    <div class="float-right">
                        <a href="{{ route('bookings.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Создать заявку
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Список заявок</h3>
                        </div>
                        <div class="card-body p-0">
                            @if($bookings->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>№</th>
                                                <th>Тур</th>
                                                <th>Направление</th>
                                                <th>Дата поездки</th>
                                                <th>Туристы</th>
                                                <th>Статус</th>
                                                <th>Менеджер</th>
                                                <th>Создана</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($bookings as $booking)
                                                <tr>
                                                    <td><strong>#{{ $booking->id }}</strong></td>
                                                    <td>{{ $booking->tour_name ?? 'Не указан' }}</td>
                                                    <td>{{ $booking->destination ?? 'Не указано' }}</td>
                                                    <td>
                                                        @if($booking->start_date && $booking->end_date)
                                                            {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
                                                            -
                                                            {{ \Carbon\Carbon::parse($booking->end_date)->format('d.m.Y') }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $booking->adults_count ?? 0 }} взр.
                                                        @if($booking->children_count > 0)
                                                            , {{ $booking->children_count }} дет.
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
                                                    <td>
                                                        @if($booking->manager)
                                                            <span class="text-success">
                                                                <i class="bi bi-person-check"></i>
                                                                {{ $booking->manager->name }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">
                                                                <i class="bi bi-hourglass-split"></i>
                                                                Ожидание назначения
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $booking->created_at->format('d.m.Y H:i') }}</td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="{{ route('bookings.show', $booking->id) }}" 
                                                               class="btn btn-sm btn-info" title="Просмотр">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            @if($booking->status === 'pending')
                                                                <a href="{{ route('bookings.edit', $booking->id) }}" 
                                                                   class="btn btn-sm btn-primary" title="Редактировать">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                            @endif
                                                            @if($booking->manager_id)
                                                                <a href="{{ route('profile.chat', ['bookingId' => $booking->id]) }}" 
                                                                   class="btn btn-sm btn-success" title="Чат с менеджером">
                                                                    <i class="bi bi-chat"></i>
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
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3">У вас пока нет заявок</h4>
                                    <p>Создайте первую заявку, чтобы начать планировать свое путешествие</p>
                                    <a href="{{ route('bookings.create') }}" class="btn btn-primary btn-lg mt-3">
                                        <i class="bi bi-plus-circle"></i> Создать заявку
                                    </a>
                                </div>
                            @endif
                        </div>
                        @if($bookings->hasPages())
                            <div class="card-footer clearfix">
                                {{ $bookings->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Информация о статусах -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-secondary collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title">Справка о статусах заявок</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul>
                                <li><span class="badge badge-warning">В обработке</span> - Заявка принята и ожидает обработки менеджером</li>
                                <li><span class="badge badge-info">Подтверждена</span> - Менеджер подтвердил заявку, идет подготовка</li>
                                <li><span class="badge badge-success">Завершена</span> - Тур успешно завершен</li>
                                <li><span class="badge badge-danger">Отменена</span> - Заявка отменена</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
