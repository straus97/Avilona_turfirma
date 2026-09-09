@extends('cabinet.layouts.app')

@section('title', 'Чат с менеджером')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Чат с менеджером</h1>
    <p class="page-subtitle">Переписка с менеджером по каждой из ваших заявок</p>
</div>

@if($bookings->count() === 0)
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-chat-square-text',
        'title' => 'Пока не с кем переписываться',
        'description' => 'Чат появится, когда по вашей заявке будет назначен менеджер.',
        'actionUrl' => route('bookings.create'),
        'actionText' => 'Создать заявку',
    ])
@else
<div class="tc-chat"
     data-chat-root
     data-chat-context="tourist"
     data-chat-user-id="{{ Auth::id() }}"
     data-chat-current-booking-id="{{ $currentBooking?->id }}"
     data-chat-messages-url="{{ route('messages.index') }}"
     data-chat-unread-url="{{ route('messages.unread-count') }}"
     data-chat-peer-name="{{ $currentBooking?->manager?->name ?? 'Менеджер' }}"
     data-chat-poll-ms="5000">

    <p class="visually-hidden" data-chat-status role="status" aria-live="polite"></p>

    {{-- Список переписок --}}
    <div class="tc-chat__panel">
        <h2 class="tc-chat__panel-title">Мои заявки</h2>
        <div class="tc-chat__threads" data-chat-threads>
            @foreach($bookings as $booking)
                <a href="{{ route('cabinet.chat', $booking->id) }}"
                   data-chat-thread
                   @class(['tc-thread', 'is-active' => $currentBooking && $currentBooking->id === $booking->id])
                   @if($currentBooking && $currentBooking->id === $booking->id) aria-current="page" @endif>
                    @if($booking->manager)
                        <span class="user-avatar" style="width: 40px; height: 40px;" aria-hidden="true">
                            {{ strtoupper(mb_substr($booking->manager->name, 0, 1)) }}
                        </span>
                    @else
                        <span class="user-avatar" style="width: 40px; height: 40px; background: var(--cabinet-muted);" aria-hidden="true">
                            <i class="bi bi-person-dash"></i>
                        </span>
                    @endif
                    <span class="tc-thread__body">
                        <span class="tc-thread__line">
                            <span class="tc-thread__title">Заявка #{{ $booking->id }}</span>
                            @if($booking->unread_count > 0)
                                <span class="badge bg-danger rounded-pill">{{ $booking->unread_count }}<span class="visually-hidden"> непрочитанных</span></span>
                            @endif
                        </span>
                        <span class="tc-thread__sub d-block">
                            {{ $booking->manager?->name ?? 'Менеджер не назначен' }}
                        </span>
                        <span class="tc-thread__sub d-block">
                            {{ $booking->destination_country }}@if($booking->destination_city) • {{ $booking->destination_city }}@endif
                        </span>
                        <span class="d-inline-flex mt-1">
                            @include('cabinet.components.status-badge', ['status' => $booking->status])
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Активная переписка --}}
    <div class="tc-chat__panel tc-chat__window" data-chat-window tabindex="-1">
        @if($currentBooking)
            <div class="tc-chat__header">
                @if($currentBooking->manager)
                    <span class="user-avatar" style="width: 44px; height: 44px;" aria-hidden="true">
                        {{ strtoupper(mb_substr($currentBooking->manager->name, 0, 1)) }}
                    </span>
                    <div style="flex: 1; min-width: 0;">
                        <h2 class="mb-0" style="font-size: 1rem; font-weight: 600;">{{ $currentBooking->manager->name }}</h2>
                        <div class="tc-thread__sub">
                            Заявка #{{ $currentBooking->id }} • {{ $currentBooking->destination_country }}@if($currentBooking->destination_city), {{ $currentBooking->destination_city }}@endif
                        </div>
                    </div>
                    @include('cabinet.components.status-badge', ['status' => $currentBooking->status])
                @else
                    <div class="tc-notice" style="width: 100%;">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span>По этой заявке ещё не назначен менеджер. Как только менеджер появится, здесь откроется переписка.</span>
                    </div>
                @endif
            </div>

            <div id="chatMessages" class="tc-chat__messages" data-chat-messages>
                @forelse($messages as $message)
                    @php $mine = $message->sender_id === Auth::id(); @endphp
                    <div @class(['tc-msg', 'tc-msg--own' => $mine, 'tc-msg--other' => ! $mine]) data-message-id="{{ $message->id }}">
                        <div class="tc-msg__sender">{{ $mine ? 'Вы' : ($message->sender->name ?? 'Менеджер') }}</div>
                        <div class="tc-msg__bubble">
                            @if($message->message)
                                <div>{{ $message->message }}</div>
                            @endif
                            @if($message->hasAttachment())
                                <a href="{{ route('messages.attachment', $message) }}" target="_blank" rel="noopener"
                                   class="tc-msg__attachment {{ $mine ? 'text-white' : '' }}">
                                    <i class="bi bi-paperclip" aria-hidden="true"></i> Вложение
                                </a>
                            @endif
                        </div>
                        <div class="tc-msg__time">{{ $message->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted m-auto">
                        <i class="bi bi-chat-dots" style="font-size: 2.5rem;" aria-hidden="true"></i>
                        <p class="mt-3 mb-0">Сообщений пока нет</p>
                        <p class="small">Напишите менеджеру первым</p>
                    </div>
                @endforelse
            </div>

            @if($currentBooking->manager)
                <div class="tc-chat__composer">
                    <p class="alert alert-danger py-2 px-3 mb-2 small" data-chat-error role="alert" hidden></p>
                    <form action="{{ route('messages.store') }}" method="POST" enctype="multipart/form-data" data-chat-composer>
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $currentBooking->id }}">
                        <input type="hidden" name="receiver_id" value="{{ $currentBooking->manager_id }}">

                        <div class="tc-chat__composer-row">
                            <label for="messageInput" class="visually-hidden">Текст сообщения</label>
                            <input type="text" name="message" class="form-control" placeholder="Введите сообщение…" id="messageInput" data-chat-input autocomplete="off">
                            <label for="attachmentInput" class="tc-attach btn btn-outline-secondary mb-0" title="Прикрепить файл">
                                <i class="bi bi-paperclip" aria-hidden="true"></i>
                                <span class="visually-hidden">Прикрепить файл</span>
                                <input type="file" name="attachment" class="tc-attach__input" id="attachmentInput"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.bmp,.webp"
                                       data-chat-attachment>
                            </label>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send" aria-hidden="true"></i>
                                <span class="visually-hidden">Отправить</span>
                            </button>
                        </div>
                        <div class="mt-2 text-muted small" data-chat-attachment-name hidden>
                            <i class="bi bi-file-earmark" aria-hidden="true"></i> <span data-chat-attachment-filename></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" data-chat-attachment-clear>
                                <i class="bi bi-x-circle" aria-hidden="true"></i>
                                <span class="visually-hidden">Убрать файл</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @else
            <div class="tc-chat__messages text-center" style="justify-content: center;">
                <div class="text-muted m-auto">
                    <i class="bi bi-chat-square-text" style="font-size: 3rem; color: var(--cabinet-border-strong);" aria-hidden="true"></i>
                    <h2 class="mt-3" style="font-size: 1.1rem;">Выберите заявку</h2>
                    <p class="text-muted mb-0">Слева выберите заявку, чтобы открыть переписку с менеджером.</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endif
@endsection
