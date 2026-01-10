@extends('layouts.profile')

@section('title', 'Чат с менеджером - Авилона')

@section('content')
<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-comments text-primary"></i> Чат с менеджером
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.index') }}">Главная</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Личный кабинет</a></li>
                    <li class="breadcrumb-item active">Чат</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Список заявок с чатами -->
            <div class="col-md-4">
                <div class="card card-primary card-outline direct-chat direct-chat-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i> Мои заявки
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        @if($bookings->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($bookings as $item)
                                    <a href="{{ route('profile.chat', $item->id) }}" 
                                       class="list-group-item list-group-item-action {{ $currentBooking && $currentBooking->id === $item->id ? 'active' : '' }}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <i class="fas fa-bookmark"></i> Заявка #{{ $item->id }}
                                            </h6>
                                            <small>{{ $item->created_at->diffForHumans() }}</small>
                                        </div>
                                        <p class="mb-1">
                                            <i class="fas fa-map-marker-alt"></i> {{ $item->destination_country }}
                                            @if($item->destination_city)
                                                - {{ $item->destination_city }}
                                            @endif
                                        </p>
                                        @if($item->manager)
                                            <small class="text-muted">
                                                <i class="fas fa-user-tie"></i> {{ $item->manager->name }}
                                            </small>
                                        @else
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> Ожидание менеджера
                                            </small>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>У вас нет заявок с активными чатами</p>
                                <a href="{{ route('bookings.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Создать заявку
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
                                <i class="fas fa-bookmark"></i> Заявка #{{ $currentBooking->id }}
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-{{ $currentBooking->status_color }}">
                                    {{ $currentBooking->status_label }}
                                </span>
                                <a href="{{ route('bookings.show', $currentBooking->id) }}" 
                                   class="btn btn-tool" 
                                   title="Просмотр заявки">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <!-- Информация о заявке -->
                            <div class="alert alert-info border-left-info mb-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-map-marker-alt"></i> Направление:</strong>
                                        {{ $currentBooking->destination_country }}
                                        @if($currentBooking->destination_city)
                                            - {{ $currentBooking->destination_city }}
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-calendar"></i> Дата вылета:</strong>
                                        @if($currentBooking->start_date)
                                            {{ \Carbon\Carbon::parse($currentBooking->start_date)->format('d.m.Y') }}
                                        @else
                                            Не указана
                                        @endif
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-users"></i> Туристов:</strong>
                                        {{ $currentBooking->adults + $currentBooking->children }}
                                    </div>
                                    @if($currentBooking->manager)
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-user-tie"></i> Менеджер:</strong>
                                            {{ $currentBooking->manager->name }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Сообщения -->
                            <div class="direct-chat-messages" id="chatMessages" style="height: 400px; overflow-y: auto;">
                                @if($messages->count() > 0)
                                    @foreach($messages as $message)
                                        @if($message->sender_id === Auth::id())
                                            <!-- Сообщение от пользователя (справа) -->
                                            <div class="direct-chat-msg right">
                                                <div class="direct-chat-infos clearfix">
                                                    <span class="direct-chat-name float-right">Вы</span>
                                                    <span class="direct-chat-timestamp float-left">
                                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                                    </span>
                                                </div>
                                                <img class="direct-chat-img" src="{{ asset('dist/img/user2-160x160.jpg') }}" alt="user">
                                                <div class="direct-chat-text">
                                                    {{ $message->message }}
                                                    @if($message->attachment_url)
                                                        <div class="mt-2">
                                                            <a href="{{ Storage::url($message->attachment_url) }}" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-paperclip"></i> Вложение
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <!-- Сообщение от менеджера (слева) -->
                                            <div class="direct-chat-msg">
                                                <div class="direct-chat-infos clearfix">
                                                    <span class="direct-chat-name float-left">
                                                        {{ $message->sender->name }}
                                                    </span>
                                                    <span class="direct-chat-timestamp float-right">
                                                        {{ $message->created_at->format('d.m.Y H:i') }}
                                                    </span>
                                                </div>
                                                <img class="direct-chat-img" src="{{ asset('dist/img/user1-128x128.jpg') }}" alt="manager">
                                                <div class="direct-chat-text">
                                                    {{ $message->message }}
                                                    @if($message->attachment_url)
                                                        <div class="mt-2">
                                                            <a href="{{ Storage::url($message->attachment_url) }}" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-outline-info">
                                                                <i class="fas fa-paperclip"></i> Вложение
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <p>Начните общение с менеджером</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card-footer">
                            @if($currentBooking->manager_id)
                                <form action="{{ route('messages.store') }}" method="POST" enctype="multipart/form-data" id="messageForm">
                                    @csrf
                                    <input type="hidden" name="booking_id" value="{{ $currentBooking->id }}">
                                    <input type="hidden" name="receiver_id" value="{{ $currentBooking->manager_id }}">
                                    
                                    <div class="input-group">
                                        <input type="text" 
                                               name="message" 
                                               placeholder="Введите сообщение..." 
                                               class="form-control"
                                               required>
                                        <label class="btn btn-default btn-file ml-2">
                                            <i class="fas fa-paperclip"></i>
                                            <input type="file" name="attachment" style="display: none;" id="attachmentInput">
                                        </label>
                                        <span class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> Отправить
                                            </button>
                                        </span>
                                    </div>
                                    <small id="attachmentName" class="text-muted"></small>
                                </form>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Менеджер еще не назначен на вашу заявку. Ожидайте назначения.
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-comment-slash fa-5x text-muted mb-4"></i>
                            <h4 class="text-muted">Выберите заявку для общения</h4>
                            <p class="text-muted">Выберите заявку из списка слева, чтобы начать общение с менеджером</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.border-left-info {
    border-left: 4px solid #36b9cc;
}

.list-group-item.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
}

.direct-chat-messages {
    background: #f8f9fc;
    border-radius: 10px;
    padding: 15px;
}

.direct-chat-text {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 10px 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,.05);
}

.direct-chat-msg.right .direct-chat-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-color: #667eea;
}

.direct-chat-img {
    border: 3px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}

.card {
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
}

#attachmentInput::-webkit-file-upload-button {
    visibility: hidden;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Прокрутка к последнему сообщению
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Отображение имени файла
    $('#attachmentInput').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $('#attachmentName').text(fileName ? 'Файл: ' + fileName : '');
    });

    // Автообновление чата каждые 10 секунд
    @if($currentBooking)
        setInterval(function() {
            location.reload();
        }, 10000);
    @endif
});
</script>
@endpush
