@extends('cabinet.layouts.app')

@section('title', 'Мои клиенты')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Мои клиенты</h1>
    <p class="page-subtitle">Список клиентов и их активность</p>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Список клиентов</div>
        <span class="badge bg-primary">Всего клиентов: {{ $clients->total() }}</span>
    </div>

    @if($clients->count() > 0)
        <div class="table-responsive">
            <table class="table align-middle">
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
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                        {{ strtoupper(substr($client->name, 0, 1)) }}
                                    </div>
                                    <strong>{{ $client->name }}</strong>
                                </div>
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
                            <td><span class="badge bg-info">{{ $client->bookings_count }}</span></td>
                            <td>
                                <span class="badge {{ $client->active_bookings > 0 ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $client->active_bookings }}
                                </span>
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
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('cabinet.manager.bookings', ['search' => $client->email]) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Заявки клиента">
                                        <i class="bi bi-bookmark"></i>
                                    </a>
                                    @if($client->latest_booking)
                                        <a href="{{ route('cabinet.manager.chat', ['bookingId' => $client->latest_booking->id]) }}"
                                           class="btn btn-sm btn-outline-success"
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
        @include('cabinet.components.empty-state', [
            'icon' => 'bi-people',
            'title' => 'У вас пока нет клиентов',
            'description' => 'Клиенты появятся здесь после назначения вам заявок',
        ])
    @endif

    @if($clients->hasPages())
        <div class="mt-3">
            {{ $clients->links() }}
        </div>
    @endif
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="bi bi-info-circle"></i> Работа с клиентами</div>
    </div>
    <ul class="mb-0">
        <li>Оперативно отвечайте на сообщения клиентов</li>
        <li>Следите за статусами заявок и своевременно обновляйте их</li>
        <li>Убедитесь, что все необходимые документы загружены</li>
        <li>Поддерживайте профессиональное общение</li>
        <li>Информируйте клиентов о важных изменениях в их заявках</li>
    </ul>
</div>
@endsection
