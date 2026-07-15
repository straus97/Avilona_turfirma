@extends('emails.layout')

@section('title', 'Новое сообщение')

@section('content')
    <h2>💬 Новое сообщение в чате</h2>
    
    <p>Здравствуйте, <strong>{{ $chatMessage->receiver->name }}</strong>!</p>

    <p>Вы получили новое сообщение от <strong>{{ $chatMessage->sender->name }}</strong> по заявке #{{ $chatMessage->booking_id }}.</p>

    <div class="info-box">
        <p><strong>От кого:</strong> {{ $chatMessage->sender->name }}</p>
        <p><strong>Заявка:</strong> #{{ $chatMessage->booking_id }}</p>
        <p><strong>Город вылета:</strong> {{ $chatMessage->booking->departure_city }}</p>
        <p><strong>Направление:</strong> {{ $chatMessage->booking->destination_country }}</p>
        <p><strong>Дата:</strong> {{ $chatMessage->created_at->format('d.m.Y H:i') }}</p>
    </div>

    <div class="info-box" style="background-color: #fff; border-left-color: #764ba2;">
        <p><strong>Сообщение:</strong></p>
        <p>{{ Str::limit($chatMessage->message, 200) }}</p>
    </div>

    @if($chatMessage->attachment_url)
    <p><strong>📎 К сообщению прикреплен файл</strong></p>
    @endif
    
    <p style="text-align: center;">
        <a href="{{ $chatUrl }}" class="button">
            Открыть чат и ответить
        </a>
    </p>
    
    <p style="text-align: center;">
        <a href="{{ route('bookings.show', $chatMessage->booking_id) }}" class="button">
            Посмотреть заявку
        </a>
    </p>
    
    <p style="color: #999; font-size: 12px;">
        💡 Совет: Для более быстрой коммуникации используйте чат на сайте. 
        Уведомления на email могут приходить с небольшой задержкой.
    </p>
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
