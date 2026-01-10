@extends('emails.layout')

@section('title', 'Напоминание о поездке')

@section('content')
    <h2>✈️ Напоминание о вашей поездке</h2>
    
    <p>Здравствуйте, <strong>{{ $booking->user->name }}</strong>!</p>
    
    @if($daysUntilTrip == 1)
        <p><strong class="highlight">Завтра ваш вылет!</strong> 🎉</p>
    @elseif($daysUntilTrip <= 3)
        <p><strong class="highlight">Ваша поездка уже через {{ $daysUntilTrip }} {{ str_plural($daysUntilTrip, 'день', 'дня', 'дней') }}!</strong> 🎉</p>
    @else
        <p>До вашей поездки осталось <strong>{{ $daysUntilTrip }}</strong> {{ str_plural($daysUntilTrip, 'день', 'дня', 'дней') }}!</p>
    @endif
    
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
    </div>
    
    <p><strong>Чек-лист перед поездкой:</strong></p>
    <ul>
        <li>✓ Проверьте срок действия загранпаспортов (должен быть действителен минимум 6 месяцев)</li>
        <li>✓ Распечатайте или сохраните электронные билеты и ваучеры</li>
        <li>✓ Оформите туристическую страховку (если еще не оформлена)</li>
        <li>✓ Проверьте валюту и лимиты по картам</li>
        <li>✓ Узнайте контакты посольства в стране пребывания</li>
        <li>✓ Сделайте копии всех важных документов</li>
        <li>✓ Проверьте прогноз погоды и соберите подходящую одежду</li>
        <li>✓ Зарегистрируйтесь на рейс (обычно за 24 часа)</li>
    </ul>
    
    <p style="text-align: center;">
        <a href="{{ route('profile.documents', $booking->id) }}" class="button">
            Мои документы
        </a>
    </p>
    
    <p style="text-align: center;">
        <a href="{{ route('bookings.show', $booking->id) }}" class="button">
            Детали поездки
        </a>
    </p>
    
    @if($booking->manager)
    <p>Если у вас возникнут вопросы, ваш менеджер <strong>{{ $booking->manager->name }}</strong> всегда на связи!</p>
    <p style="text-align: center;">
        <a href="{{ route('profile.chat', $booking->id) }}">Написать менеджеру</a>
    </p>
    @endif
    
    <p><strong>Желаем вам приятного путешествия и незабываемых впечатлений! 🌴☀️</strong></p>
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
