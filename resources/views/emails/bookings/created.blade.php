@extends('emails.layout')

@section('title', 'Заявка создана')

@section('content')
    <h2>✅ Ваша заявка успешно создана!</h2>
    
    <p>Здравствуйте, <strong>{{ $booking->user->name }}</strong>!</p>
    
    <p>Мы получили вашу заявку на бронирование тура. Наш менеджер свяжется с вами в ближайшее время для уточнения деталей.</p>
    
    <div class="info-box">
        <p><strong>Номер заявки:</strong> #{{ $booking->id }}</p>
        <p><strong>Город вылета:</strong> {{ $booking->departure_city }}</p>
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
    
    @if(!$booking->user->email_verified_at)
        {{-- Для незарегистрированных пользователей --}}
        <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <h3 style="color: #856404; margin-top: 0;">🎯 Зарегистрируйтесь для удобства!</h3>
            <p style="color: #856404;">
                Создайте личный кабинет на нашем сайте, чтобы:
            </p>
            <ul style="text-align: left; color: #856404; display: inline-block;">
                <li>Отслеживать статус заявки онлайн</li>
                <li>Общаться с менеджером в чате</li>
                <li>Получать уведомления об изменениях</li>
                <li>Хранить все документы в одном месте</li>
            </ul>
            <p style="text-align: center; margin-top: 20px;">
                <a href="{{ route('register') }}" class="button" style="background-color: #28a745; border-color: #28a745;">
                    📝 Зарегистрироваться
                </a>
            </p>
            <p style="font-size: 12px; color: #856404; margin-bottom: 0;">
                При регистрации используйте email: <strong>{{ $booking->user->email }}</strong><br>
                Заявка автоматически появится в вашем личном кабинете
            </p>
        </div>
    @else
        {{-- Для зарегистрированных пользователей --}}
        <p style="text-align: center;">
            <a href="{{ route('bookings.show', $booking->id) }}" class="button">
                Посмотреть заявку
            </a>
        </p>
    @endif
    
    <p><strong>Что дальше?</strong></p>
    <ol>
        <li>Менеджер свяжется с вами в течение <span class="highlight">24 часов</span></li>
        <li>Мы подберем лучшие варианты туров по вашим критериям</li>
        <li>Согласуем детали и оформим бронирование</li>
        <li>Подготовим все необходимые документы</li>
    </ol>
    
    <p>Если у вас возникнут вопросы, вы всегда можете связаться с нами по телефону @if($booking->user->email_verified_at)или написать в чат на сайте @endif.</p>
    
    <p style="font-size: 11px; color: #999; margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee;">
        <em>Это письмо сформировано автоматически. Пожалуйста, не отвечайте на него.</em>
    </p>
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
