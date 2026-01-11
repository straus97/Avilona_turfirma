@extends('cabinet.layouts.app')

@section('title', 'Чат с менеджером')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Чат с менеджером</h1>
    <p class="page-subtitle">Общайтесь с вашим менеджером по заявкам</p>
</div>

<div class="row">
    <!-- Список чатов -->
    <div class="col-md-4">
        <div class="card-custom" style="height: calc(100vh - 200px); overflow-y: auto;">
            <h5 class="mb-3">Мои заявки</h5>
            
            @if($bookings->count() > 0)
                @foreach($bookings as $booking)
                    <a href="{{ route('cabinet.chat', $booking->id) }}" 
                       class="d-block p-3 mb-2 rounded {{ $currentBooking && $currentBooking->id == $booking->id ? 'bg-primary text-white' : 'bg-light' }}"
                       style="text-decoration: none; transition: all 0.2s; position: relative;">
                        <div class="d-flex align-items-start gap-2">
                            @if($booking->manager)
                                <div class="user-avatar" style="width: 40px; height: 40px;">
                                    {{ strtoupper(substr($booking->manager->name, 0, 1)) }}
                                </div>
                            @else
                                <div class="user-avatar" style="width: 40px; height: 40px; background: #6c757d;">
                                    <i class="bi bi-person-x"></i>
                                </div>
                            @endif
                            <div style="flex: 1; min-width: 0;">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div style="font-weight: 600; font-size: 0.875rem;">
                                        Заявка #{{ $booking->id }}
                                    </div>
                                    @if($booking->unread_count > 0)
                                        <span class="badge bg-danger rounded-pill">{{ $booking->unread_count }}</span>
                                    @endif
                                </div>
                                <div style="font-size: 0.75rem; opacity: 0.8;">
                                    {{ $booking->destination_country }}
                                    @if($booking->destination_city)
                                        • {{ $booking->destination_city }}
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    @include('cabinet.components.status-badge', ['status' => $booking->status])
                                    @if($booking->manager)
                                        <div style="font-size: 0.75rem; opacity: 0.7;">
                                            {{ $booking->manager->name }}
                                        </div>
                                    @else
                                        <div style="font-size: 0.75rem; opacity: 0.7; font-style: italic;">
                                            Менеджер не назначен
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-3">У вас пока нет заявок</p>
                    <a href="{{ route('bookings.create') }}" class="btn btn-sm btn-primary">
                        Создать заявку
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Область чата -->
    <div class="col-md-8">
        @if($currentBooking)
            <div class="card-custom" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                <!-- Заголовок чата -->
                <div class="d-flex align-items-center gap-3 pb-3 border-bottom">
                    @if($currentBooking->manager)
                        <div class="user-avatar" style="width: 48px; height: 48px;">
                            {{ strtoupper(substr($currentBooking->manager->name, 0, 1)) }}
                        </div>
                        <div style="flex: 1;">
                            <h5 class="mb-0">{{ $currentBooking->manager->name }}</h5>
                            <div style="font-size: 0.875rem; color: #6b7280;">
                                Заявка #{{ $currentBooking->id }} • {{ $currentBooking->destination_country }}
                                @if($currentBooking->destination_city)
                                    • {{ $currentBooking->destination_city }}
                                @endif
                            </div>
                            <div class="mt-1">
                                @include('cabinet.components.status-badge', ['status' => $currentBooking->status])
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0 flex-fill">
                            <i class="bi bi-exclamation-triangle"></i> Менеджер еще не назначен на эту заявку
                        </div>
                    @endif
                </div>

                <!-- Сообщения -->
                <div id="chatMessages" style="flex: 1; overflow-y: auto; padding: 1.5rem 0;">
                    @if($messages->count() > 0)
                        @foreach($messages->reverse() as $message)
                            <div class="mb-3 d-flex {{ $message->sender_id == Auth::id() ? 'justify-content-end' : 'justify-content-start' }}">
                                <div style="max-width: 70%;">
                                    <div class="p-3 rounded {{ $message->sender_id == Auth::id() ? 'bg-primary text-white' : 'bg-light' }}">
                                        <div style="font-size: 0.875rem;">{{ $message->message }}</div>
                                        @if($message->attachment_url)
                                            <div class="mt-2">
                                                <a href="{{ Storage::url($message->attachment_url) }}" target="_blank" class="text-decoration-underline">
                                                    <i class="bi bi-paperclip"></i> Вложение
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;" class="{{ $message->sender_id == Auth::id() ? 'text-end' : '' }}">
                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-dots" style="font-size: 3rem;"></i>
                            <p class="mt-3">Пока нет сообщений</p>
                            <p class="small">Начните беседу с вашим менеджером</p>
                        </div>
                    @endif
                </div>

                <!-- Форма отправки -->
                @if($currentBooking->manager)
                    <div class="border-top pt-3">
                        <form action="{{ route('messages.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="booking_id" value="{{ $currentBooking->id }}">
                            <input type="hidden" name="receiver_id" value="{{ $currentBooking->manager_id }}">
                            
                            <div class="d-flex gap-2">
                                <input type="text" name="message" class="form-control" placeholder="Введите сообщение..." required>
                                <label class="btn btn-outline-secondary" style="cursor: pointer;">
                                    <i class="bi bi-paperclip"></i>
                                    <input type="file" name="attachment" style="display: none;">
                                </label>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @else
            <div class="card-custom text-center" style="height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center;">
                <div>
                    <i class="bi bi-chat-square-text" style="font-size: 4rem; color: #d1d5db;"></i>
                    <h4 class="mt-4">Выберите заявку</h4>
                    <p class="text-muted">Выберите заявку слева, чтобы начать общение с менеджером</p>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    // Автопрокрутка к последнему сообщению
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Автообновление чата каждые 10 секунд
    @if($currentBooking)
    setInterval(() => {
        window.location.reload();
    }, 10000);
    @endif
</script>
@endpush
@endsection
