@extends('cabinet.layouts.app')

@section('title', 'Входящее обращение')

@section('sidebar')
    @include(auth()->user()->hasRole('admin') ? 'cabinet.components.sidebar.admin' : 'cabinet.components.sidebar.manager')
@endsection

@php
    $dash = fn ($value) => ($value === null || $value === '') ? '—' : $value;
@endphp

@section('content')
<div class="page-header">
    <h1 class="page-title">Входящее обращение · Tourvisor #{{ $inquiry->external_id }}</h1>
    <p class="page-subtitle">Входящее обращение, не бронирование. Источник: Tourvisor. Проверьте цену и наличие, свяжитесь с клиентом и при необходимости оформите бронирование в Avilona вручную.</p>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Состояние загрузки</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Состояние</dt>
        <dd class="col-sm-8">{{ $inquiry->stateLabel() }}</dd>
        <dt class="col-sm-4">Уведомление получено</dt>
        <dd class="col-sm-8">{{ $inquiry->first_notified_at?->format('d.m.Y H:i') ?? '—' }} (уведомлений: {{ $inquiry->webhook_count }})</dd>
        <dt class="col-sm-4">Данные загружены</dt>
        <dd class="col-sm-8">{{ $inquiry->imported_at?->format('d.m.Y H:i') ?? '—' }}</dd>
        @if($inquiry->last_error_code)
            <dt class="col-sm-4">Последняя ошибка</dt>
            <dd class="col-sm-8">{{ $inquiry->last_error_code }}</dd>
        @endif
    </dl>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Клиент (данные из обращения)</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Имя</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_name) }}</dd>
        <dt class="col-sm-4">Телефон</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_phone) }}</dd>
        <dt class="col-sm-4">Email</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_email) }}</dd>
        <dt class="col-sm-4">Комментарий</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_comment) }}</dd>
    </dl>
    <p class="text-muted mt-3 mb-0">Эти данные не связаны с аккаунтом Avilona автоматически.</p>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Тур (по данным Tourvisor)</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Оператор</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->operator_name) }}</dd>
        <dt class="col-sm-4">Вылет из</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->departure_city) }}</dd>
        <dt class="col-sm-4">Страна</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->destination_country) }}</dd>
        <dt class="col-sm-4">Отель</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->hotel_name) }}</dd>
        <dt class="col-sm-4">Дата вылета</dt>
        <dd class="col-sm-8">{{ $inquiry->fly_date?->format('d.m.Y') ?? '—' }}</dd>
        <dt class="col-sm-4">Ночей</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->nights) }}</dd>
        <dt class="col-sm-4">Цена по данным Tourvisor</dt>
        <dd class="col-sm-8">{{ $inquiry->price !== null ? number_format((float) $inquiry->price, 0, ',', ' ') . ' ' . ($inquiry->currency ?? '') : '—' }}</dd>
    </dl>
    <p class="text-muted mt-3 mb-0">Цена и наличие мест не подтверждены — их проверяет менеджер.</p>
</div>

<div class="mt-3">
    <a href="{{ route('cabinet.manager.inquiries') }}">← К списку обращений</a>
</div>
@endsection
