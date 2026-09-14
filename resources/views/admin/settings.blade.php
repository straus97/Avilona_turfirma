@extends('cabinet.layouts.app')

@section('title', 'Система')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Система</h1>
    <p class="page-subtitle">Состояние приложения и системные инструменты</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Информация о системе</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <tr>
                <th width="30%">PHP версия:</th>
                <td>{{ $systemInfo['php_version'] }}</td>
            </tr>
            <tr>
                <th>Laravel версия:</th>
                <td>{{ $systemInfo['laravel_version'] }}</td>
            </tr>
            <tr>
                <th>Окружение:</th>
                <td>
                    <span class="badge {{ $systemInfo['environment'] === 'production' ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $systemInfo['environment'] }}
                    </span>
                </td>
            </tr>
            <tr>
                <th>Режим отладки:</th>
                <td>
                    <span class="badge {{ $systemInfo['debug_mode'] ? 'bg-danger' : 'bg-success' }}">
                        {{ $systemInfo['debug_mode'] ? 'Включен' : 'Выключен' }}
                    </span>
                </td>
            </tr>
            <tr>
                <th>Драйвер кэша:</th>
                <td>{{ $systemInfo['cache_driver'] }}</td>
            </tr>
            <tr>
                <th>Драйвер сессий:</th>
                <td>{{ $systemInfo['session_driver'] }}</td>
            </tr>
            <tr>
                <th>Драйвер очередей:</th>
                <td>{{ $systemInfo['queue_driver'] }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Управление кэшем</div>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="text-muted">Драйвер кэша: {{ $cacheStats['driver'] }}</div>
        </div>
        <p class="text-muted">
            Очищает временные данные приложения. Кэш конфигурации, маршрутов и представлений при этом не затрагивается.
        </p>
        <form action="{{ route('cabinet.admin.clear-cache') }}" method="POST" onsubmit="return confirm('Очистить весь кэш приложения?');">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-trash"></i> Очистить кэш
            </button>
        </form>
    </div>
</div>
@endsection
