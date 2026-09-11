@extends('cabinet.layouts.app')

@section('title', 'Редактировать заявку №' . $booking->id)
@section('meta_description', 'Редактирование заявки на тур')

@section('sidebar')
    @if(Auth::user()->isAdmin())
        @include('cabinet.components.sidebar.admin')
    @elseif(Auth::user()->isManager())
        @include('cabinet.components.sidebar.manager')
    @elseif(Auth::user()->isTourist())
        @include('cabinet.components.sidebar.tourist')
    @endif
@endsection

@section('content')
@php
    // Эффективная роль: admin > manager > tourist, как в BookingController::update().
    $isStaffEditor = auth()->user()->isAdmin() || auth()->user()->isManager();
    $canEditTouristFields = !$isStaffEditor
        && auth()->user()->isTourist()
        && $booking->status === \App\Models\Booking::STATUS_NEW;

    $allowedStatuses = $booking->allowedStatusesForUpdate();
    $allStatusLabels = \App\Models\Booking::availableStatuses();
    $selectedStatus = in_array(old('status'), $allowedStatuses, true) ? old('status') : $booking->status;

    $direction = trim($booking->departure_city . ' → ' . $booking->destination_country
        . ($booking->destination_city ? ', ' . $booking->destination_city : ''));
@endphp

<div class="page-header">
    <h1 class="page-title">Редактировать заявку №{{ $booking->id }}</h1>
    <p class="page-subtitle">
        @if($isStaffEditor)
            Статус, стоимость и служебные заметки
        @else
            Дополнительные пожелания по заявке
        @endif
    </p>
</div>

<div class="booking-form">
    {{-- Неизменяемая сводка по заявке --}}
    <section class="card-custom" aria-labelledby="booking-edit-summary-title">
        <div class="card-header-custom">
            <h2 class="card-title-custom" id="booking-edit-summary-title">
                <i class="bi bi-info-circle" aria-hidden="true"></i> Параметры поездки
            </h2>
        </div>
        @include('cabinet.components.booking-facts', ['facts' => [
            ['label' => 'Направление', 'value' => $direction, 'wide' => true],
            ['label' => 'Дата вылета', 'value' => $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указана'],
            ['label' => 'Ночей', 'value' => (string) $booking->nights],
            ['label' => 'Туристов', 'value' => $booking->adults . ' взр.' . ($booking->children > 0 ? ' + ' . $booking->children . ' дет.' : '')],
        ]])
        <p class="booking-optional mt-3 mb-0">
            Чтобы изменить детали тура, свяжитесь с менеджером — здесь эти поля не редактируются.
        </p>
    </section>

    <form action="{{ route('bookings.update', $booking) }}" method="POST">
        @csrf
        @method('PUT')

        <section class="card-custom" aria-labelledby="booking-edit-fields-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="booking-edit-fields-title">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    {{ $isStaffEditor ? 'Служебные данные' : 'Ваши пожелания' }}
                </h2>
            </div>

            {{-- Статус --}}
            <div class="mb-3">
                <label for="status" class="form-label">
                    Статус заявки <span class="booking-req">*</span>
                </label>
                <select class="form-select @error('status') is-invalid @enderror"
                        id="status"
                        name="status"
                        required
                        aria-describedby="status-help">
                    @foreach($allowedStatuses as $statusKey)
                        <option value="{{ $statusKey }}" {{ $selectedStatus === $statusKey ? 'selected' : '' }}>
                            {{ $allStatusLabels[$statusKey] ?? $statusKey }}
                        </option>
                    @endforeach
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text" id="status-help">
                    Доступны только переходы, разрешённые из текущего статуса.
                </div>
            </div>

            {{-- Поля менеджера и администратора --}}
            @if($isStaffEditor)
                <div class="mb-3">
                    <label for="total_price" class="form-label">
                        Стоимость, ₽ <span class="booking-optional">— необязательно</span>
                    </label>
                    <input type="number"
                           class="form-control @error('total_price') is-invalid @enderror"
                           id="total_price"
                           name="total_price"
                           value="{{ old('total_price', $booking->total_price) }}"
                           min="0"
                           step="0.01">
                    @error('total_price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="manager_notes" class="form-label">
                        Заметки менеджера <span class="booking-optional">— клиент их не видит</span>
                    </label>
                    <textarea class="form-control @error('manager_notes') is-invalid @enderror"
                              id="manager_notes"
                              name="manager_notes"
                              rows="4"
                              placeholder="Внутренние заметки по заявке">{{ old('manager_notes', $booking->manager_notes) }}</textarea>
                    @error('manager_notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endif

            {{-- Поле туриста: только для владельца с ролью tourist и статусом «Новая» --}}
            @if($canEditTouristFields)
                <div class="mb-3">
                    <label for="notes" class="form-label">
                        Дополнительные пожелания <span class="booking-optional">— необязательно</span>
                    </label>
                    <textarea class="form-control @error('notes') is-invalid @enderror"
                              id="notes"
                              name="notes"
                              rows="4"
                              placeholder="Пожелания по отелю, питанию, расположению и т. д.">{{ old('notes', $booking->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <p class="booking-actions__note">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    После начала обработки заявки менеджером, редактирование будет недоступно.
                </p>
            @endif

            <div class="booking-actions booking-actions--split">
                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Отмена
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Сохранить изменения
                </button>
            </div>
        </section>
    </form>
</div>
@endsection
