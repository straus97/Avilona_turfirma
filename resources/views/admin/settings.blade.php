@extends('layouts.profile')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Системные настройки</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            <!-- Информация о системе -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Информация о системе</h3>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
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
                                <span class="badge {{ $systemInfo['environment'] === 'production' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $systemInfo['environment'] }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Режим отладки:</th>
                            <td>
                                <span class="badge {{ $systemInfo['debug_mode'] ? 'badge-danger' : 'badge-success' }}">
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

            <!-- Управление кэшем -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Управление кэшем</h3>
                </div>
                <div class="card-body">
                    <p><strong>Статус:</strong> 
                        @if($cacheStats['enabled'])
                            <span class="badge badge-success">Включен</span>
                        @else
                            <span class="badge badge-warning">Отключен</span>
                        @endif
                    </p>
                    <p><strong>Драйвер:</strong> {{ $cacheStats['driver'] }}</p>
                    <form action="{{ route('admin.clear-cache') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-trash"></i> Очистить кэш
                        </button>
                    </form>
                </div>
            </div>

            <!-- Быстрые команды -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Быстрые команды</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Для выполнения этих команд используйте терминал на сервере:</p>
                    <ul>
                        <li><code>php artisan cache:clear</code> - Очистить кэш приложения</li>
                        <li><code>php artisan config:clear</code> - Очистить кэш конфигурации</li>
                        <li><code>php artisan route:clear</code> - Очистить кэш маршрутов</li>
                        <li><code>php artisan view:clear</code> - Очистить кэш представлений</li>
                        <li><code>php artisan optimize</code> - Оптимизировать приложение</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
@endsection
