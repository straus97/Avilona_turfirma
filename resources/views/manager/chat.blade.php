@extends('cabinet.layouts.app')

@section('title', 'Чат с клиентами')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Чат с клиентами</h1>
    <p class="page-subtitle">Общайтесь с клиентами по заявкам</p>
</div>

<div class="row"
     data-chat-root
     data-chat-context="manager"
     data-chat-user-id="{{ $manager->id }}"
     data-chat-current-booking-id="{{ $currentBooking?->id }}"
     data-chat-messages-url="{{ route('messages.index') }}"
     data-chat-unread-url="{{ route('messages.unread-count') }}"
     data-chat-peer-name="{{ $currentBooking?->user?->name ?? 'Клиент' }}"
     data-chat-poll-ms="5000">

    <p class="visually-hidden" data-chat-status role="status" aria-live="polite"></p>

    <div class="col-md-4">
        <div class="card-custom" style="height: calc(100vh - 200px); overflow-y: auto;">
            <h5 class="mb-3">Клиенты</h5>
            @if($bookings->count() > 0)
                <div data-chat-threads>
                @foreach($bookings as $booking)
                    @php
                        $unreadCount = $unreadCounts->get($booking->id, 0);
                    @endphp
                    <a href="{{ route('cabinet.manager.chat', ['bookingId' => $booking->id]) }}"
                       data-chat-thread
                       @if($currentBooking && $currentBooking->id == $booking->id) aria-current="page" @endif
                       class="d-block p-3 mb-2 rounded {{ $currentBooking && $currentBooking->id == $booking->id ? 'bg-primary text-white' : 'bg-light' }}"
                       style="text-decoration: none; transition: all 0.2s; position: relative;">
                        <div class="d-flex align-items-start gap-2">
                            <div class="user-avatar" style="width: 40px; height: 40px;">
                                {{ strtoupper(substr($booking->user->name ?? 'К', 0, 1)) }}
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div style="font-weight: 600; font-size: 0.875rem;">
                                        {{ $booking->user->name ?? 'Неизвестно' }}
                                    </div>
                                    @if($unreadCount > 0)
                                        <span class="badge bg-danger rounded-pill">{{ $unreadCount }}</span>
                                    @endif
                                </div>
                                <div style="font-size: 0.75rem; opacity: 0.8;">
                                    Заявка #{{ $booking->id }} • {{ $booking->destination_country ?? '—' }}
                                    @if($booking->destination_city)
                                        • {{ $booking->destination_city }}
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    @include('cabinet.components.status-badge', ['status' => $booking->status])
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-square-text" style="font-size: 3rem;"></i>
                    <p class="mt-3">У вас нет назначенных заявок</p>
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-8">
        @if($currentBooking)
            <div class="card-custom" style="height: calc(100vh - 200px); display: flex; flex-direction: column;" data-chat-window tabindex="-1">
                <div class="d-flex align-items-center gap-3 pb-3 border-bottom">
                    <div class="user-avatar" style="width: 48px; height: 48px;">
                        {{ strtoupper(substr($currentBooking->user->name ?? 'К', 0, 1)) }}
                    </div>
                    <div style="flex: 1;">
                        <h5 class="mb-0">{{ $currentBooking->user->name ?? 'Клиент' }}</h5>
                        <div style="font-size: 0.875rem; color: #6b7280;">
                            Заявка #{{ $currentBooking->id }} • {{ $currentBooking->destination_country ?? '—' }}
                            @if($currentBooking->destination_city)
                                • {{ $currentBooking->destination_city }}
                            @endif
                        </div>
                        <div class="mt-1">
                            @include('cabinet.components.status-badge', ['status' => $currentBooking->status])
                        </div>
                    </div>
                    <a href="{{ route('bookings.show', $currentBooking->id) }}" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i> Заявка
                    </a>
                </div>

                <div id="chatMessages" style="flex: 1; overflow-y: auto; padding: 1.5rem 0;" data-chat-messages>
                    @if($messages->count() > 0)
                        @foreach($messages as $message)
                            <div class="mb-3 d-flex {{ $message->sender_id == $manager->id ? 'justify-content-end' : 'justify-content-start' }}" data-message-id="{{ $message->id }}">
                                <div style="max-width: 70%;">
                                    <div class="p-3 rounded {{ $message->sender_id == $manager->id ? 'bg-primary text-white' : 'bg-light' }}">
                                        @if($message->message)
                                            <div style="font-size: 0.875rem;">{{ $message->message }}</div>
                                        @endif
                                        @if($message->hasAttachment())
                                            <div class="mt-2">
                                                <a href="{{ route('messages.attachment', $message) }}" target="_blank" rel="noopener" class="text-decoration-underline {{ $message->sender_id == $manager->id ? 'text-white' : 'text-primary' }}">
                                                    <i class="bi bi-paperclip"></i> Вложение
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;" class="{{ $message->sender_id == $manager->id ? 'text-end' : '' }}">
                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                            <p class="mt-3">Пока нет сообщений</p>
                            <p class="small">Напишите первое сообщение клиенту</p>
                        </div>
                    @endif
                </div>

                <div class="border-top pt-3">
                    <p class="alert alert-danger py-2 px-3 mb-2 small" data-chat-error role="alert" hidden></p>
                    <form action="{{ route('messages.store') }}" method="POST" enctype="multipart/form-data" data-chat-composer>
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $currentBooking->id }}">
                        <input type="hidden" name="receiver_id" value="{{ $currentBooking->user_id }}">

                        <div class="d-flex gap-2">
                            <input type="text" name="message" class="form-control" placeholder="Введите сообщение..." id="messageInput" data-chat-input autocomplete="off">
                            <label class="btn btn-outline-secondary" style="cursor: pointer;" title="Прикрепить файл">
                                <i class="bi bi-paperclip"></i>
                                <input type="file" name="attachment" style="display: none;" id="attachmentInput"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.bmp,.webp"
                                       data-chat-attachment>
                            </label>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                        <div class="mt-2 text-muted small" data-chat-attachment-name hidden>
                            <i class="bi bi-file-earmark"></i> <span data-chat-attachment-filename></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" data-chat-attachment-clear>
                                <i class="bi bi-x-circle"></i>
                                <span class="visually-hidden">Убрать файл</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="card-custom text-center" style="height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center;">
                <div>
                    <i class="bi bi-chat-square-text" style="font-size: 4rem; color: #d1d5db;"></i>
                    <h4 class="mt-4">Выберите заявку</h4>
                    <p class="text-muted">Выберите заявку слева, чтобы начать общение</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
