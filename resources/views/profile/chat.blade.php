@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Чат с менеджером</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Список заявок с чатами -->
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Ваши заявки</h3>
                        </div>
                        <div class="card-body p-0">
                            @if($bookings->count() > 0)
                                <ul class="nav nav-pills flex-column">
                                    @foreach($bookings as $booking)
                                        <li class="nav-item">
                                            <a href="{{ route('profile.chat', ['bookingId' => $booking->id]) }}" 
                                               class="nav-link {{ $currentBooking && $currentBooking->id == $booking->id ? 'active' : '' }}">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>#{{ $booking->id }}</strong> - {{ Str::limit($booking->tour_name ?? 'Без названия', 30) }}
                                                        @if($booking->manager)
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="bi bi-person"></i> {{ $booking->manager->name }}
                                                            </small>
                                                        @endif
                                                    </div>
                                                    @php
                                                        $unreadCount = \App\Models\Message::where('booking_id', $booking->id)
                                                            ->where('receiver_id', $user->id)
                                                            ->where('is_read', false)
                                                            ->count();
                                                    @endphp
                                                    @if($unreadCount > 0)
                                                        <span class="badge badge-danger">{{ $unreadCount }}</span>
                                                    @endif
                                                </div>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-chat-square-text" style="font-size: 3rem;"></i>
                                    <p class="mt-2">У вас нет заявок с назначенным менеджером</p>
                                    <a href="{{ route('bookings.create') }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-plus-circle"></i> Создать заявку
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Окно чата -->
                <div class="col-md-8">
                    @if($currentBooking)
                        <div class="card card-primary card-outline direct-chat direct-chat-primary">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Заявка #{{ $currentBooking->id }} - {{ $currentBooking->tour_name ?? 'Без названия' }}
                                </h3>
                                <div class="card-tools">
                                    @if($currentBooking->manager)
                                        <span class="badge badge-success">
                                            <i class="bi bi-person"></i> {{ $currentBooking->manager->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="direct-chat-messages" style="height: 400px; overflow-y: auto;" id="chatMessages">
                                    @if($messages->count() > 0)
                                        @foreach($messages->reverse() as $message)
                                            <div class="direct-chat-msg {{ $message->sender_id == $user->id ? 'right' : '' }}">
                                                <div class="direct-chat-infos clearfix">
                                                    <span class="direct-chat-name {{ $message->sender_id == $user->id ? 'float-right' : 'float-left' }}">
                                                        {{ $message->sender->name }}
                                                    </span>
                                                    <span class="direct-chat-timestamp {{ $message->sender_id == $user->id ? 'float-left' : 'float-right' }}">
                                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                                    </span>
                                                </div>
                                                <img class="direct-chat-img" src="{{ asset('dist/img/user2-160x160.jpg') }}" alt="User Image">
                                                <div class="direct-chat-text">
                                                    {{ $message->message }}
                                                    @if($message->attachment_url)
                                                        <br>
                                                        <a href="{{ asset('storage/' . $message->attachment_url) }}" target="_blank" class="btn btn-sm btn-link">
                                                            <i class="bi bi-paperclip"></i> Вложение
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="text-center text-muted p-4">
                                            <i class="bi bi-chat" style="font-size: 3rem;"></i>
                                            <p class="mt-2">Пока нет сообщений</p>
                                            <p>Напишите первое сообщение вашему менеджеру</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="card-footer">
                                <form action="{{ route('messages.store') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="booking_id" value="{{ $currentBooking->id }}">
                                    <input type="hidden" name="receiver_id" value="{{ $currentBooking->manager_id }}">
                                    
                                    <div class="input-group">
                                        <input type="text" name="message" placeholder="Введите сообщение..." 
                                               class="form-control" required>
                                        <div class="input-group-append">
                                            <label for="attachment" class="btn btn-secondary mb-0" title="Прикрепить файл">
                                                <i class="bi bi-paperclip"></i>
                                            </label>
                                            <input type="file" name="attachment" id="attachment" class="d-none">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send"></i> Отправить
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-1" id="fileInfo"></small>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center text-muted p-5">
                                    <i class="bi bi-chat-left-dots" style="font-size: 5rem;"></i>
                                    <h4 class="mt-3">Выберите заявку для начала общения</h4>
                                    <p>Выберите заявку из списка слева, чтобы начать переписку с менеджером</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    // Показать имя выбранного файла
    document.getElementById('attachment').addEventListener('change', function(e) {
        const fileInfo = document.getElementById('fileInfo');
        if (this.files.length > 0) {
            fileInfo.textContent = 'Файл: ' + this.files[0].name;
        } else {
            fileInfo.textContent = '';
        }
    });

    // Автоматическая прокрутка к последнему сообщению
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Автообновление чата каждые 30 секунд
    @if($currentBooking)
        setInterval(function() {
            location.reload();
        }, 30000);
    @endif
</script>
@endpush
