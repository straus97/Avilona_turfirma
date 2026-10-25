@extends('cabinet.layouts.app')

@section('title', 'Обращения клиентов')

@section('sidebar')
    @include(auth()->user()->hasRole('admin') ? 'cabinet.components.sidebar.admin' : 'cabinet.components.sidebar.manager')
@endsection

@section('content')
@php($isAdmin = auth()->user()->hasRole('admin'))
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
        <h1 class="page-title">Обращения клиентов</h1>
        <p class="page-subtitle">Обращения агентства, записанные вручную: звонки и сообщения в мессенджерах. Это не бронирования и не технические входящие из Tourvisor (они в разделе «Входящие обращения»).
            @if($isAdmin)
                Администратор видит все обращения.
            @else
                Здесь показаны только ваши обращения.
            @endif
        </p>
    </div>
    @if($canCreate)
        <a href="{{ route('cabinet.manager.agency-inquiries.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Новое обращение
        </a>
    @endif
</div>

@if($inquiries->isEmpty())
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-telephone-inbound',
        'title' => 'Обращений клиентов пока нет',
        'description' => $canCreate ? 'Когда клиент позвонит или напишет вам лично, запишите обращение и сохраните данные поездки.' : 'Обращения появятся здесь, когда менеджеры начнут их записывать.',
        'actionUrl' => $canCreate ? route('cabinet.manager.agency-inquiries.create') : null,
        'actionText' => $canCreate ? 'Новое обращение' : null,
    ])
@else
<div class="card-custom">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Создано</th>
                    <th>Канал</th>
                    <th>Направление</th>
                    @if($isAdmin)<th>Ответственный</th>@endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($inquiries as $inquiry)
                    <tr>
                        <td class="text-nowrap">{{ $inquiry->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $inquiry->contactChannelLabel() }}</td>
                        <td>{{ $inquiry->destination_country ?? 'Не указано' }}</td>
                        @if($isAdmin)<td>{{ $inquiry->assignee?->name ?? '—' }}</td>@endif
                        <td class="text-end"><a href="{{ route('cabinet.manager.agency-inquiries.show', $inquiry) }}">Открыть</a></td>
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
