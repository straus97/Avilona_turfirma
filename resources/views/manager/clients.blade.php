@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Мои клиенты</h1>
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
                            <h3 class="card-title">Список клиентов</h3>
                            <div class="card-tools">
                                <span class="badge badge-primary">
                                    Всего клиентов: {{ $clients->total() }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($clients->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Клиент</th>
                                                <th>Email</th>
                                                <th>Телефон</th>
                                                <th>Заявок</th>
                                                <th>Активных</th>
                                                <th>Последняя заявка</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($clients as $client)
                                                <tr>
                                                    <td>{{ $client->id }}</td>
                                                    <td>
                                                        <i class="bi bi-person-circle"></i>
                                                        <strong>{{ $client->name }}</strong>
                                                    </td>
                                                    <td>
                                                        <a href="mailto:{{ $client->email }}">
                                                            <i class="bi bi-envelope"></i> {{ $client->email }}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        @if($client->phone)
                                                            <a href="tel:{{ $client->phone }}">
                                                                <i class="bi bi-telephone"></i> {{ $client->phone }}
                                                            </a>
                                                        @else
                                                            <span class="text-muted">Не указан</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            {{ $client->bookings_count }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($client->active_bookings > 0)
                                                            <span class="badge badge-success">
                                                                {{ $client->active_bookings }}
                                                            </span>
                                                        @else
                                                            <span class="badge badge-secondary">0</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($client->latest_booking)
                                                            <small>
                                                                {{ $client->latest_booking->created_at->format('d.m.Y') }}
                                                                <br>
                                                                <span class="text-muted">
                                                                    {{ $client->latest_booking->tour_name ?? 'Без названия' }}
                                                                </span>
                                                            </small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="{{ route('manager.bookings', ['search' => $client->email]) }}" 
                                                               class="btn btn-sm btn-primary" 
                                                               title="Заявки клиента">
                                                                <i class="bi bi-bookmark"></i>
                                                            </a>
                                                            @if($client->latest_booking)
                                                                <a href="{{ route('manager.chat', ['bookingId' => $client->latest_booking->id]) }}" 
                                                                   class="btn btn-sm btn-success" 
                                                                   title="Написать в чат">
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
                                    <i class="bi bi-people" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3">У вас пока нет клиентов</h4>
                                    <p>Клиенты появятся здесь после назначения вам заявок</p>
                                </div>
                            @endif
                        </div>
                        @if($clients->hasPages())
                            <div class="card-footer clearfix">
                                {{ $clients->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Информационная карточка -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-info-circle"></i> Работа с клиентами
                            </h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Советы по работе с клиентами:</strong></p>
                            <ul>
                                <li>Оперативно отвечайте на сообщения клиентов</li>
                                <li>Следите за статусами заявок и своевременно обновляйте их</li>
                                <li>Убедитесь, что все необходимые документы загружены</li>
                                <li>Поддерживайте профессиональное общение</li>
                                <li>Информируйте клиентов о важных изменениях в их заявках</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
