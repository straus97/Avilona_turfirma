@extends('cabinet.layouts.app')

@section('title', 'Логи')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Логи системы</h1>
    <p class="page-subtitle">Журнал событий и ошибок приложения</p>
</div>

<div class="card-custom">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="card-title-custom">Последние 200 строк</div>
        <div class="text-muted small">{{ $path }}</div>
    </div>
    <div class="card-body">
        @if(count($lines) > 0)
            <pre style="white-space: pre-wrap; max-height: 60vh; overflow: auto;">{{ implode("\n", $lines) }}</pre>
        @else
            <div class="text-muted">Логи не найдены или пусты.</div>
        @endif
    </div>
</div>
@endsection
