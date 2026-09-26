@extends('cabinet.layouts.app')

@section('title', 'Входящие обращения')

@section('sidebar')
    @include(auth()->user()->hasRole('admin') ? 'cabinet.components.sidebar.admin' : 'cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Входящие обращения</h1>
    <p class="page-subtitle">Обращения туристов из модуля поиска туров (Tourvisor). Это не бронирования: цену и наличие мест проверяет менеджер.</p>
</div>

@if($inquiries->isEmpty())
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-inbox',
        'title' => 'Входящих обращений пока нет',
        'description' => 'Здесь появятся обращения, полученные от Tourvisor.',
    ])
@else
<div class="card-custom">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Получено</th>
                    <th>Источник</th>
                    <th>Клиент</th>
                    <th>Направление</th>
                    <th>Состояние</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($inquiries as $inquiry)
                    <tr>
                        <td class="text-nowrap">{{ $inquiry->first_notified_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>Tourvisor #{{ $inquiry->external_id }}@if($inquiry->isOnline()) <span class="text-muted">(online)</span>@endif</td>
                        <td>{{ $inquiry->client_name ?? '—' }}</td>
                        <td>{{ $inquiry->destination_country ?? '—' }}</td>
                        <td>{{ $inquiry->stateLabel() }}</td>
                        <td class="text-end"><a href="{{ route('cabinet.manager.inquiries.show', $inquiry->id) }}">Открыть</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($inquiries->hasPages())
        <div class="mt-3">
            {{ $inquiries->links() }}
        </div>
    @endif
</div>
@endif
@endsection
