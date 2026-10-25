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
                Администратор видит все обращения и может фильтровать их по ответственному.
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

<form method="GET" action="{{ route('cabinet.manager.agency-inquiries') }}" class="card-custom mb-3" aria-label="Фильтры списка обращений">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
            <label for="ai-filter-status" class="form-label">Статус</label>
            <select id="ai-filter-status" name="status" class="form-select">
                <option value="">Любой</option>
                @foreach(\App\Models\Inquiry::statusLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="ai-filter-channel" class="form-label">Канал</label>
            <select id="ai-filter-channel" name="channel" class="form-select">
                <option value="">Любой</option>
                @foreach(\App\Models\Inquiry::contactChannelLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['channel'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($isAdmin)
        <div class="col-12 col-md-3">
            <label for="ai-filter-assignee" class="form-label">Ответственный</label>
            <select id="ai-filter-assignee" name="assignee" class="form-select">
                <option value="">Любой</option>
                <option value="none" @selected(($filters['assignee'] ?? null) === 'none')>Не назначен</option>
                @foreach($eligibleStaff as $employee)
                    <option value="{{ $employee['id'] }}" @selected(($filters['assignee'] ?? null) === (string) $employee['id'])>{{ $employee['name'] }} ({{ $employee['role'] }})</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Применить</button>
            @if($filters !== [])
                <a href="{{ route('cabinet.manager.agency-inquiries') }}" class="btn btn-link">Сбросить</a>
            @endif
        </div>
    </div>
    @if($filtersRejected)
        <p class="text-danger small mt-2 mb-0" role="alert">Часть условий фильтра недопустима и не применена.</p>
    @endif
</form>

@if($inquiries->isEmpty() && $filters !== [])
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-funnel',
        'title' => 'По выбранным условиям обращений нет',
        'description' => 'Измените или сбросьте фильтры.',
        'actionUrl' => route('cabinet.manager.agency-inquiries'),
        'actionText' => 'Сбросить фильтры',
    ])
@elseif($inquiries->isEmpty())
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-telephone-inbound',
        'title' => 'Обращений клиентов пока нет',
        'description' => $canCreate ? 'Когда клиент позвонит или напишет, запишите обращение и сохраните данные поездки.' : 'Обращения появятся здесь, когда менеджеры начнут их записывать.',
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
                    <th>Статус</th>
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
                        <td>{{ $inquiry->statusLabel() }}</td>
                        <td>{{ $inquiry->destination_country ?? 'Не указано' }}</td>
                        @if($isAdmin)<td>{{ $inquiry->assignee?->name ?? '—' }}</td>@endif
                        <td class="text-end"><a href="{{ route('cabinet.manager.agency-inquiries.show', ['inquiry' => $inquiry] + $carry) }}">Открыть</a></td>
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
