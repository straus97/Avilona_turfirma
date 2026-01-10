@extends('emails.layout')

@section('title', 'Новое сообщение')

@section('content')
    <h2>💬 Новое сообщение в чате</h2>
    
    <p>Здравствуйте, <strong>{{ $message->receiver->name }}</strong>!</p>
    
    <p>Вы получили новое сообщение от <strong>{{ $message->sender->name }}</strong> по заявке #{{ $message->booking_id }}.</p>
    
    <div class="info-box">
        <p><strong>От кого:</strong> {{ $message->sender->name }}</p>
        <p><strong>Заявка:</strong> #{{ $message->booking_id }}</p>
        <p><strong>Город вылета:</strong> {{ $message->booking->departure_city }}</p>
        <p><strong>Направление:</strong> {{ $message->booking->destination_country }}</p>
        <p><strong>Дата:</strong> {{ $message->created_at->format('d.m.Y H:i') }}</p>
    </div>
    
    <div class="info-box" style="background-color: #fff; border-left-color: #764ba2;">
        <p><strong>Сообщение:</strong></p>
        <p>{{ Str::limit($message->message, 200) }}</p>
    </div>
    
    @if($message->attachment_url)
    <p><strong>📎 К сообщению прикреплен файл</strong></p>
    @endif
    
    <p style="text-align: center;">
        <a href="{{ route('profile.chat', $message->booking_id) }}" class="button">
            Открыть чат и ответить
        </a>
    </p>
    
    <p style="text-align: center;">
        <a href="{{ route('bookings.show', $message->booking_id) }}" class="button">
            Посмотреть заявку
        </a>
    </p>
    
    <p style="color: #999; font-size: 12px;">
        💡 Совет: Для более быстрой коммуникации используйте чат на сайте. 
        Уведомления на email могут приходить с небольшой задержкой.
    </p>
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
