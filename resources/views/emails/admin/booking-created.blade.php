@extends('emails.layout')

@section('content')
<div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #2c3e50; margin: 0;">Новая заявка на сайте</h1>
    </div>

    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #2c3e50; margin-top: 0;">Информация о заявке</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6c757d; width: 40%;"><strong>Номер заявки:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">#{{ $booking->id }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Статус:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    @include('cabinet.components.status-badge', ['status' => $booking->status])
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Направление:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    {{ $booking->destination_country }}
                    @if($booking->destination_city)
                        ({{ $booking->destination_city }})
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Город вылета:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">{{ $booking->departure_city }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Дата вылета:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указана' }}
                    @if($booking->start_date_end)
                        - {{ $booking->start_date_end->format('d.m.Y') }}
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Количество ночей:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    {{ $booking->nights }}
                    @if($booking->nights_max && $booking->nights_max != $booking->nights)
                        - {{ $booking->nights_max }}
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Туристы:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    Взрослых: {{ $booking->adults }}
                    @if($booking->children > 0)
                        <br>Детей: {{ $booking->children }}
                        @if($booking->children_ages)
                            (возраст: {{ implode(', ', json_decode($booking->children_ages, true)) }})
                        @endif
                    @endif
                </td>
            </tr>
            @if($booking->notes)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;"><strong>Примечания:</strong></td>
                    <td style="padding: 8px 0; color: #2c3e50;">{{ $booking->notes }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
        <h2 style="color: #1976d2; margin-top: 0;">Информация о туристе</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6c757d; width: 40%;"><strong>ФИО:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">{{ $booking->user->name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Email:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">{{ $booking->user->email }}</td>
            </tr>
            @if($booking->user->phone)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;"><strong>Телефон:</strong></td>
                    <td style="padding: 8px 0; color: #2c3e50;">{{ $booking->user->phone }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Статус регистрации:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    @if($booking->user->email_verified_at)
                        <span style="color: #4caf50; font-weight: bold;">✓ Зарегистрирован на сайте</span>
                    @else
                        <span style="color: #ff9800; font-weight: bold;">⚠ Не зарегистрирован</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6c757d;"><strong>Контактная информация:</strong></td>
                <td style="padding: 8px 0; color: #2c3e50;">
                    <p style="margin: 4px 0;">
                        <strong>Email:</strong> <a href="mailto:{{ $booking->user->email }}" style="color: #2196f3;">{{ $booking->user->email }}</a>
                    </p>
                    @if($booking->user->phone)
                        <p style="margin: 4px 0;">
                            <strong>Телефон:</strong> <a href="tel:{{ $booking->user->phone }}" style="color: #2196f3;">{{ $booking->user->phone }}</a>
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #fff3cd; border-radius: 8px;">
        <p style="margin: 0; color: #856404;">
            <strong>⚠ Важно:</strong> {{ $booking->user->email_verified_at ? 'Турист зарегистрирован на сайте и может управлять заявкой самостоятельно.' : 'Турист не зарегистрирован на сайте. Рекомендуем связаться с ним и предложить зарегистрироваться для удобства взаимодействия.' }}
        </p>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ url('/bookings/' . $booking->id) }}" 
           style="display: inline-block; padding: 12px 30px; background-color: #2196f3; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
            Просмотреть заявку
        </a>
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 12px;">
        <p>Это автоматическое уведомление от системы управления заявками.</p>
        <p>Дата создания заявки: {{ $booking->created_at->format('d.m.Y H:i') }}</p>
    </div>
</div>
@endsection
