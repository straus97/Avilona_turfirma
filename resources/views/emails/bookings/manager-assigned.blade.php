@extends('emails.layout')

@section('title', 'Менеджер назначен')

@section('content')
    <h2>👤 К вашей заявке назначен менеджер</h2>
    
    <p>Здравствуйте, <strong>{{ $booking->user->name ?? 'Уважаемый клиент' }}</strong>!</p>
    
    <p>Отличная новость! К вашей заявке #{{ $booking->id }} был назначен персональный менеджер.</p>
    
    <div class="info-box">
        <p><strong>Ваш менеджер:</strong> {{ $booking->manager->name }}</p>
        @if($booking->manager->email)
            <p><strong>Email:</strong> <a href="mailto:{{ $booking->manager->email }}">{{ $booking->manager->email }}</a></p>
        @endif
        <p><strong>Город вылета:</strong> {{ $booking->departure_city }}</p>
        <p><strong>Направление:</strong> {{ $booking->destination_country }}
            @if($booking->destination_city)
                - {{ $booking->destination_city }}
            @endif
        </p>
    </div>
    
    <p>Ваш менеджер поможет вам:</p>
    <ul>
        <li>Подобрать идеальный тур по вашим пожеланиям</li>
        <li>Оформить все необходимые документы</li>
        <li>Ответить на все ваши вопросы</li>
        <li>Сопровождать вас до самого вылета</li>
    </ul>
    
    <p style="text-align: center;">
        <a href="{{ $chatUrl }}" class="button">
            Написать менеджеру
        </a>
    </p>
    
    <p style="text-align: center;">
        <a href="{{ route('bookings.show', $booking->id) }}" class="button">
            Посмотреть заявку
        </a>
    </p>
    
    <p><strong>{{ $booking->manager->name }}</strong> уже ознакомился(лась) с вашей заявкой и скоро свяжется с вами!</p>
    
    <p>С уважением,<br><strong>Команда Авилона</strong></p>
@endsection
