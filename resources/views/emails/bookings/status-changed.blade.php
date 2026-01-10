@extends('emails.layout')

@section('title', 'Статус заявки изменен')

@section('content')
    <h2>🔔 Статус вашей заявки изменен</h2>
    
    <p>Здравствуйте, <strong>{{ $booking->user->name }}</strong>!</p>
    
    <p>Статус вашей заявки #{{ $booking->id }} был изменен.</p>
    
    <div class="info-box">
        <p><strong>Номер заявки:</strong> #{{ $booking->id }}</p>
        <p><strong>Город вылета:</strong> {{ $booking->departure_city }}</p>
        <p><strong>Направление:</strong> {{ $booking->destination_country }}</p>
        <p><strong>Новый статус:</strong> 
            <span class="status-badge status-{{ $booking->status }}">
                {{ $booking->status_label }}
            </span>
        </p>
    </div>
    
    @if($booking->status === 'confirmed')
        <p><strong>🎉 Отличная новость!</strong></p>
        <p>Ваша заявка подтверждена! Мы начинаем оформление всех необходимых документов.</p>
        <p><strong>Следующие шаги:</strong></p>
        <ul>
            <li>Загрузите копии паспортов в личном кабинете</li>
            <li>Следите за обновлениями в чате с менеджером</li>
            <li>Подготовьтесь к отличному отдыху! 🌴</li>
        </ul>
    @elseif($booking->status === 'progress')
        <p>Ваша заявка находится в обработке. Менеджер работает над подбором лучших вариантов для вас.</p>
    @elseif($booking->status === 'completed')
        <p><strong>✅ Ваша поездка завершена!</strong></p>
        <p>Надеемся, вы отлично отдохнули! Мы будем рады видеть вас снова.</p>
        <p>Пожалуйста, оставьте отзыв о вашей поездке на нашем сайте.</p>
    @elseif($booking->status === 'cancelled')
        <p><strong>❌ Заявка отменена</strong></p>
        <p>Ваша заявка была отменена. Если это произошло по ошибке, пожалуйста, свяжитесь с нами.</p>
    @endif
    
    <p style="text-align: center;">
        <a href="{{ route('bookings.show', $booking->id) }}" class="button">
            Посмотреть детали заявки
        </a>
    </p>
    
    @if($booking->manager)
    <p>Ваш менеджер: <strong>{{ $booking->manager->name }}</strong></p>
    <p>
        <a href="{{ route('profile.chat', $booking->id) }}">Написать в чат</a>
    </p>
    @endif
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
