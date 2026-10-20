@extends('cabinet.layouts.app')

@section('title', 'Логи')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Логи системы</h1>
    <p class="page-subtitle">Безопасная сводка технических сообщений приложения. Бизнес-события — в <a href="{{ route('cabinet.admin.audit') }}">журнале действий</a>.</p>
</div>

<div class="card-custom">
    <div class="card-header-custom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="card-title-custom">Последние записи</div>
        <div class="text-muted small">Журнал приложения</div>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Показаны только время, уровень и краткое очищенное сообщение (не более {{ \App\Services\Diagnostics\TechnicalLogSummary::MAX_ENTRIES }} последних записей).
            Трассировки, пути к файлам сервера, параметры запросов и данные контекста скрыты.
            Полный журнал доступен администратору сервера в файловой системе сервера.
        </p>

        @if(! empty($summary['entries']))
            <p class="small mb-3">
                @foreach($summary['counts'] as $level => $count)
                    <span class="me-3 text-nowrap">{{ $level }}: <strong>{{ $count }}</strong></span>
                @endforeach
            </p>

            <ul class="list-group list-group-flush" aria-label="Сводка технического журнала">
                @foreach($summary['entries'] as $entry)
                    <li class="list-group-item px-0">
                        <div class="d-flex flex-wrap gap-2 align-items-baseline">
                            <span class="small text-muted text-nowrap">{{ $entry['time'] }}</span>
                            <span class="small fw-bold text-nowrap">{{ $entry['level'] }}</span>
                        </div>
                        <div class="text-break">{{ $entry['message'] !== '' ? $entry['message'] : '—' }}</div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-muted">Записей в техническом журнале нет.</div>
        @endif
    </div>
</div>
@endsection
