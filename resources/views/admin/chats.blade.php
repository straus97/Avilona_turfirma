@extends('cabinet.layouts.app')

@section('title', 'Все чаты')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Все чаты</h1>
    <p class="page-subtitle">Контроль переписки по заявкам</p>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card-custom" style="height: calc(100vh - 200px); overflow-y: auto;">
            <h5 class="mb-3">Заявки</h5>
            @if($bookings->count() > 0)
                @foreach($bookings as $booking)
                    @php
                        $counts = $unreadByBooking[$booking->id] ?? ['manager' => 0, 'tourist' => 0];
                    @endphp
                    <a href="{{ route('cabinet.admin.chats', ['bookingId' => $booking->id]) }}"
                       class="d-block p-3 mb-2 rounded {{ $currentBooking && $currentBooking->id == $booking->id ? 'bg-primary text-white' : 'bg-light' }}"
                       style="text-decoration: none; transition: all 0.2s; position: relative;">
                        <div class="d-flex align-items-start gap-2">
                            <div class="user-avatar" style="width: 40px; height: 40px;">
                                {{ strtoupper(substr($booking->user->name ?? 'К', 0, 1)) }}
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; font-size: 0.875rem;">Заявка #{{ $booking->id }}</div>
                                <div style="font-size: 0.75rem; opacity: 0.8;">
                                    {{ $booking->user->name ?? 'Клиент' }}
                                    @if($booking->manager)
                                        • {{ $booking->manager->name }}
                                    @else
                                        • Менеджер не назначен
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    @include('cabinet.components.status-badge', ['status' => $booking->status])
                                    <div class="d-flex gap-2">
                                        @if($counts['manager'] > 0)
                                            <span class="badge bg-danger">Менеджер: {{ $counts['manager'] }}</span>
                                        @endif
                                        @if($counts['tourist'] > 0)
                                            <span class="badge bg-warning text-dark">Турист: {{ $counts['tourist'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-square-text" style="font-size: 3rem;"></i>
                    <p class="mt-3">Нет заявок</p>
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-8">
        @if($currentBooking)
            <div class="card-custom" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
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
                    <div class="text-end">
                        <div class="small text-muted">Менеджер</div>
                        <div>{{ $currentBooking->manager?->name ?? 'Не назначен' }}</div>
                    </div>
                </div>

                <div id="chatMessages" style="flex: 1; overflow-y: auto; padding: 1.5rem 0;">
                    @if($messages->count() > 0)
                        @foreach($messages as $message)
                            <div class="mb-3 d-flex {{ $message->sender_id == $currentBooking->user_id ? 'justify-content-start' : 'justify-content-end' }}">
                                <div style="max-width: 70%;">
                                    <div class="p-3 rounded {{ $message->sender_id == $currentBooking->user_id ? 'bg-light' : 'bg-primary text-white' }}">
                                        @if($message->message)
                                            <div style="font-size: 0.875rem;">{{ $message->message }}</div>
                                        @endif
                                        @if($message->attachment_url)
                                            <div class="mt-2">
                                                <a href="{{ Storage::url($message->attachment_url) }}" target="_blank" rel="noopener" class="text-decoration-underline {{ $message->sender_id == $currentBooking->user_id ? 'text-primary' : 'text-white' }}">
                                                    <i class="bi bi-paperclip"></i> Вложение
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;" class="{{ $message->sender_id == $currentBooking->user_id ? '' : 'text-end' }}">
                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                            <p class="mt-3">Пока нет сообщений</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="card-custom text-center" style="height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center;">
                <div>
                    <i class="bi bi-chat-square-text" style="font-size: 4rem; color: #d1d5db;"></i>
                    <h4 class="mt-4">Выберите заявку</h4>
                    <p class="text-muted">Выберите заявку слева, чтобы просмотреть переписку</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
