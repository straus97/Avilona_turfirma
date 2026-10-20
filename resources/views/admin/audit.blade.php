@extends('cabinet.layouts.app')

@section('title', 'Журнал действий')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Журнал действий</h1>
    <p class="page-subtitle">Важные бизнес-события, записанные системой. Записи нельзя изменить или удалить из приложения. Технические сообщения приложения — на странице <a href="{{ route('cabinet.admin.logs') }}">«Логи»</a>.</p>
</div>

@if($events->isEmpty())
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-journal-check',
        'title' => 'Записей в журнале действий пока нет',
        'description' => 'Журнал заполняется по мере подключения бизнес-операций. Прошлые события не восстанавливаются.',
    ])
@else
<div class="card-custom">
    <ul class="list-group list-group-flush" aria-label="События журнала действий">
        @foreach($events as $event)
            <li class="list-group-item">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                    <strong class="text-break">{{ $event->typeLabel() }}</strong>
                    <time class="text-muted small text-nowrap" datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('d.m.Y H:i:s') }}</time>
                </div>
                <div class="small text-break">
                    Кто:
                    @if($event->actor_id === null)
                        система
                    @else
                        {{ $actorNames[$event->actor_id] ?? 'Пользователь' }} <span class="text-muted">(#{{ $event->actor_id }})</span>
                    @endif
                </div>
                <div class="small text-break">
                    Объект: {{ $event->subject_type }}@if($event->subject_id !== null) #{{ $event->subject_id }}@endif
                </div>
                @if(! empty($event->metadata))
                    <dl class="row small mb-0 mt-1">
                        @foreach($event->metadata as $key => $value)
                            <dt class="col-sm-4 col-md-3 text-muted fw-normal text-break">{{ $key }}</dt>
                            <dd class="col-sm-8 col-md-9 mb-1 text-break">
                                @if(is_bool($value)){{ $value ? 'да' : 'нет' }}@elseif(is_scalar($value)){{ $value }}@else—@endif
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </li>
        @endforeach
    </ul>
</div>

<div class="mt-3">
    {{ $events->links() }}
</div>
@endif
@endsection
