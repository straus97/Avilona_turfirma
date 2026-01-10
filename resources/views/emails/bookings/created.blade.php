@extends('emails.layout')

@section('title', 'Заявка создана')

@section('content')
    <h2>✅ Ваша заявка успешно создана!</h2>
    
    <p>Здравствуйте, <strong>{{ $booking->user->name }}</strong>!</p>
    
    <p>Мы получили вашу заявку на бронирование тура. Наш менеджер свяжется с вами в ближайшее время для уточнения деталей.</p>
    
    <div class="info-box">
        <p><strong>Номер заявки:</strong> #{{ $booking->id }}</p>
        <p><strong>Направление:</strong> {{ $booking->destination_country }}
            @if($booking->destination_city)
                - {{ $booking->destination_city }}
            @endif
        </p>
        <p><strong>Дата вылета:</strong> 
            @if($booking->start_date)
                {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
            @else
                Не указана
            @endif
        </p>
        <p><strong>Количество ночей:</strong> {{ $booking->nights }}</p>
        <p><strong>Количество туристов:</strong> {{ $booking->adults + $booking->children }}</p>
        <p><strong>Статус:</strong> 
            <span class="status-badge status-{{ $booking->status }}">
                {{ $booking->status_label }}
            </span>
        </p>
    </div>
    
    @if($booking->notes)
    <div class="info-box">
        <p><strong>Ваши пожелания:</strong></p>
        <p>{{ $booking->notes }}</p>
    </div>
    @endif
    
    <p style="text-align: center;">
        <a href="{{ route('bookings.show', $booking->id) }}" class="button">
            Посмотреть заявку
        </a>
    </p>
    
    <p><strong>Что дальше?</strong></p>
    <ol>
        <li>Менеджер свяжется с вами в течение <span class="highlight">24 часов</span></li>
        <li>Мы подберем лучшие варианты туров по вашим критериям</li>
        <li>Согласуем детали и оформим бронирование</li>
        <li>Подготовим все необходимые документы</li>
    </ol>
    
    <p>Если у вас возникнут вопросы, вы всегда можете связаться с нами по телефону или написать в чат на сайте.</p>
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
