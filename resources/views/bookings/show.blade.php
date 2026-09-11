@extends('cabinet.layouts.app')

@section('title', 'Заявка №' . $booking->id)
@section('meta_description', 'Детали заявки на тур')

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
    // Полномочия рассчитываются для конкретной заявки, а не глобально: менеджер
    // без назначения на эту заявку не получает staff-права только из-за роли.
    // Эффективная роль — единый приоритет admin > manager > tourist.
    $isBookingAdmin = auth()->user()->isAdmin();
    $isBookingAssignedManager = !$isBookingAdmin
        && auth()->user()->isManager()
        && $booking->manager_id === auth()->id();
    $isBookingStaff = $isBookingAdmin || $isBookingAssignedManager;
    $isBookingOwner = $booking->user_id === auth()->id();
    $isBookingOwnerFacing = auth()->user()->isTourist() && $isBookingOwner && !$isBookingStaff;

    $direction = trim($booking->destination_country
        . ($booking->destination_city ? ', ' . $booking->destination_city : ''));

    $datesLabel = $booking->start_date
        ? ($booking->start_date_end && $booking->start_date_end->ne($booking->start_date)
            ? $booking->start_date->format('d.m.Y') . ' — ' . $booking->start_date_end->format('d.m.Y')
            : $booking->start_date->format('d.m.Y'))
        : null;

    $nightsLabel = $booking->nights_max && $booking->nights_max != $booking->nights
        ? $booking->nights . '–' . $booking->nights_max
        : (string) $booking->nights;

    $touristsLabel = $booking->adults . ' ' . str_plural($booking->adults, 'взрослый', 'взрослых', 'взрослых');
    if ($booking->children > 0) {
        $touristsLabel .= ', ' . $booking->children . ' ' . str_plural($booking->children, 'ребёнок', 'ребёнка', 'детей');
    }

    $childrenAgesLabel = null;
    if ($booking->children > 0 && $booking->children_ages && count($booking->children_ages) > 0) {
        $childrenAgesLabel = collect($booking->children_ages)
            ->map(fn ($age) => $age . ' ' . str_plural((int) $age, 'год', 'года', 'лет'))
            ->implode(', ');
    }

    // Следующий шаг — только информационный текст. Учитывает эффективную роль,
    // наличие назначенного сотрудника, является ли текущий сотрудник этим
    // назначенным лицом, и статус заявки. Backend-полномочия и переходы
    // жизненного цикла здесь не меняются.
    $assigneeExists = (bool) $booking->manager_id;
    $currentUserIsAssignee = $assigneeExists && $booking->manager_id === auth()->id();

    $nextStep = match (true) {
        // --- Турист-владелец ---
        $isBookingOwnerFacing && $booking->status === \App\Models\Booking::STATUS_NEW && !$assigneeExists
            => 'Заявка принята и ожидает назначения ответственного сотрудника.',
        $isBookingOwnerFacing && $booking->status === \App\Models\Booking::STATUS_NEW
            => 'Заявка принята и передана ответственному сотруднику. Детали можно уточнить в чате.',
        $isBookingOwnerFacing && $booking->status === \App\Models\Booking::STATUS_PROGRESS
            => 'Заявка в обработке: ответственный сотрудник уточняет детали и подбирает тур.',
        $isBookingOwnerFacing && $booking->status === \App\Models\Booking::STATUS_CONFIRMED
            => 'Бронирование подтверждено. Ответственный сотрудник подготовит документы к поездке.',
        $isBookingOwnerFacing && $booking->status === \App\Models\Booking::STATUS_CANCELLED
            => 'Заявка отменена.',
        $isBookingOwnerFacing && $booking->status === \App\Models\Booking::STATUS_COMPLETED
            => 'Поездка завершена. Спасибо, что путешествуете с Авилоной!',
        $isBookingOwnerFacing
            => 'Ответственный сотрудник работает с вашей заявкой.',

        // --- Назначенный менеджер (не администратор) ---
        $isBookingAssignedManager && $booking->status === \App\Models\Booking::STATUS_NEW
            => 'Заявка назначена вам и ожидает начала обработки.',
        $isBookingAssignedManager && $booking->status === \App\Models\Booking::STATUS_PROGRESS
            => 'Заявка в обработке: уточните детали с клиентом и подтвердите бронирование.',
        $isBookingAssignedManager && $booking->status === \App\Models\Booking::STATUS_CONFIRMED
            => 'Бронирование подтверждено. Подготовьте документы и завершите заявку после поездки.',
        $isBookingAssignedManager && $booking->status === \App\Models\Booking::STATUS_CANCELLED
            => 'Заявка отменена.',
        $isBookingAssignedManager && $booking->status === \App\Models\Booking::STATUS_COMPLETED
            => 'Заявка завершена.',

        // --- Администратор ---
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_NEW && !$assigneeExists
            => 'Новая заявка. Назначьте ответственного сотрудника для начала обработки.',
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_NEW && $currentUserIsAssignee
            => 'Заявка назначена вам и ожидает начала обработки.',
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_NEW
            => 'Ответственный сотрудник уже назначен. Заявка ожидает начала обработки.',
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_PROGRESS
            => 'Заявка в обработке: ответственный сотрудник уточняет детали с клиентом.',
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_CONFIRMED
            => 'Бронирование подтверждено. Ответственный сотрудник готовит документы к поездке.',
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_CANCELLED
            => 'Заявка отменена. Административные действия по заявке по-прежнему доступны.',
        $isBookingAdmin && $booking->status === \App\Models\Booking::STATUS_COMPLETED
            => 'Заявка завершена.',

        default => 'Заявка доступна для просмотра.',
    };

    // Роль-адресный маршрут чата по этой заявке (существующие маршруты, доступ
    // к которым у текущего пользователя уже есть).
    $bookingChatUrl = match (true) {
        $isBookingAdmin => route('cabinet.admin.chats', ['bookingId' => $booking->id]),
        $isBookingAssignedManager => route('cabinet.manager.chat', ['bookingId' => $booking->id]),
        $isBookingOwnerFacing && $booking->manager_id => route('cabinet.chat', $booking->id),
        default => null,
    };

    $docTypeLabels = [
        'contract'     => 'Договор',
        'voucher'      => 'Ваучер',
        'tickets'      => 'Билеты',
        'insurance'    => 'Страховка',
        'instructions' => 'Инструкция',
        'other'        => 'Другое',
    ];
@endphp

<div class="page-header">
    <h1 class="page-title">Заявка №{{ $booking->id }}</h1>
    <p class="page-subtitle">{{ $direction }}</p>
</div>

@if($errors->has('status'))
    <div class="alert alert-danger" role="alert">
        {{ $errors->first('status') }}
    </div>
@endif

<div class="booking-layout">
    <div class="booking-layout__main">

        {{-- 1. Идентификатор заявки, статус и следующий шаг --}}
        <section class="card-custom booking-hero" aria-labelledby="booking-hero-title">
            <div class="booking-hero__top">
                <div>
                    <div class="booking-hero__eyebrow">Заявка на тур</div>
                    <h2 class="booking-hero__title" id="booking-hero-title">
                        №{{ $booking->id }}@if($direction) · {{ $direction }}@endif
                    </h2>
                    <div class="booking-hero__meta">
                        Создана {{ $booking->created_at->format('d.m.Y') }}
                        @if($booking->updated_at->ne($booking->created_at))
                            · обновлена {{ $booking->updated_at->format('d.m.Y') }}
                        @endif
                    </div>
                </div>
                @include('cabinet.components.status-badge', ['status' => $booking->status])
            </div>
            <p class="booking-hero__next">
                <i class="bi bi-flag" aria-hidden="true"></i>
                <span>{{ $nextStep }}</span>
            </p>
        </section>

        {{-- 2. Параметры поездки --}}
        <section class="card-custom" aria-labelledby="booking-trip-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="booking-trip-title">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Параметры поездки
                </h2>
            </div>
            @include('cabinet.components.booking-facts', ['facts' => [
                ['label' => 'Город вылета', 'value' => $booking->departure_city],
                ['label' => 'Страна', 'value' => $booking->destination_country],
                ['label' => 'Курорт / город', 'value' => $booking->destination_city],
                ['label' => 'Даты вылета', 'value' => $datesLabel ?: 'Не указаны', 'variant' => $datesLabel ? null : 'muted'],
                ['label' => 'Ночей', 'value' => $nightsLabel],
                ['label' => 'Туристы', 'value' => $touristsLabel],
                ['label' => 'Возраст детей', 'value' => $childrenAgesLabel, 'wide' => true],
            ]])
            @if($booking->tour)
                <p class="booking-note mt-3">
                    <i class="bi bi-signpost-split" aria-hidden="true"></i>
                    По каталожному туру: {{ $booking->tour->title }}
                </p>
            @endif
        </section>

        {{-- 3. Стоимость (только если она заполнена) --}}
        @if($booking->total_price)
            <section class="card-custom" aria-labelledby="booking-price-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="booking-price-title">
                        <i class="bi bi-cash-stack" aria-hidden="true"></i> Стоимость и оплата
                    </h2>
                </div>
                @include('cabinet.components.booking-facts', ['facts' => [
                    ['label' => 'Стоимость тура', 'value' => $booking->formatted_total_price, 'variant' => 'price'],
                    ['label' => 'Оплачено', 'value' => $booking->paid_amount > 0 ? number_format($booking->paid_amount, 0, ',', ' ') . ' ₽' : null],
                    ['label' => 'Осталось внести', 'value' => ($booking->paid_amount > 0 && !$booking->is_fully_paid) ? number_format($booking->remaining_amount, 0, ',', ' ') . ' ₽' : null],
                ]])
                <p class="booking-note mt-3">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    Оплата возможна наличными, через интернет-эквайринг, по QR-коду на расчётный счёт организации или через терминал в офисе. Возврат средств производится на банковскую карту; итоговая сумма зависит от условий и решения туроператора.
                </p>
            </section>
        @endif

        {{-- 4. Клиент (только для назначенного менеджера / администратора) --}}
        @if($isBookingStaff)
            <section class="card-custom" aria-labelledby="booking-client-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="booking-client-title">
                        <i class="bi bi-person" aria-hidden="true"></i> Клиент
                    </h2>
                </div>
                <div class="booking-person">
                    <span class="booking-person__avatar" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($booking->user?->name ?? '—', 0, 1)) }}
                    </span>
                    <div class="booking-person__body">
                        <div class="booking-person__name">{{ $booking->user?->name ?? 'Пользователь удалён' }}</div>
                        <div class="booking-person__role">{{ $booking->user?->email ?? 'Email не указан' }}</div>
                    </div>
                </div>
            </section>
        @endif

        {{-- 5. Пожелания клиента / заметки менеджера --}}
        @if($booking->notes)
            <section class="card-custom" aria-labelledby="booking-notes-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="booking-notes-title">
                        <i class="bi bi-chat-left-text" aria-hidden="true"></i> Пожелания клиента
                    </h2>
                </div>
                <p class="booking-note">{{ $booking->notes }}</p>
            </section>
        @endif

        @if($booking->manager_notes && $isBookingStaff)
            <section class="card-custom" aria-labelledby="booking-manager-notes-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="booking-manager-notes-title">
                        <i class="bi bi-clipboard-check" aria-hidden="true"></i> Заметки менеджера
                    </h2>
                </div>
                <p class="booking-note">{{ $booking->manager_notes }}</p>
                <p class="booking-optional mt-2">Внутренние заметки — клиент их не видит.</p>
            </section>
        @endif

        {{-- 6. Документы по заявке --}}
        <section class="card-custom" aria-labelledby="booking-docs-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="booking-docs-title">
                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Документы по заявке
                </h2>
            </div>

            @if($booking->bookingDocuments->isEmpty())
                <p class="booking-note">
                    <i class="bi bi-inbox" aria-hidden="true"></i> Документы по этой заявке пока не загружены.
                </p>
            @else
                <div class="booking-doc-list">
                    @foreach($booking->bookingDocuments as $document)
                        @php
                            $uploadDate = $document->uploaded_at ?? $document->created_at;
                            $sizeBytes  = (int) ($document->file_size ?? 0);
                            if ($sizeBytes >= 1048576) {
                                $sizeLabel = number_format($sizeBytes / 1048576, 1) . ' МБ';
                            } elseif ($sizeBytes > 0) {
                                $sizeLabel = ceil($sizeBytes / 1024) . ' КБ';
                            } else {
                                $sizeLabel = null;
                            }
                        @endphp
                        <div class="booking-doc">
                            <span class="booking-doc__icon" aria-hidden="true">
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </span>
                            <div class="booking-doc__body">
                                <div class="booking-doc__name">
                                    {{ $document->title }}
                                    @if($document->file_type)
                                        <span class="badge bg-secondary ms-1">{{ strtoupper($document->file_type) }}</span>
                                    @endif
                                </div>
                                <div class="booking-doc__meta">
                                    <span>{{ $docTypeLabels[$document->document_type] ?? $document->document_type }}</span>
                                    @if($sizeLabel)<span>{{ $sizeLabel }}</span>@endif
                                    @if($uploadDate)<span>{{ $uploadDate->format('d.m.Y H:i') }}</span>@endif
                                    @if($isBookingStaff)
                                        <span>Загрузил: {{ $document->uploadedBy?->name ?? 'Не указан' }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="booking-doc__actions">
                                @if($isBookingStaff)
                                    <a href="{{ route('bookings.documents.download', [$booking, $document]) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       aria-label="Скачать документ «{{ $document->title }}»">
                                        <i class="bi bi-download" aria-hidden="true"></i>
                                    </a>
                                    <form action="{{ route('bookings.documents.destroy', [$booking, $document]) }}"
                                          method="POST"
                                          onsubmit="return confirm('Удалить этот документ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                aria-label="Удалить документ «{{ $document->title }}»">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @elseif($isBookingOwnerFacing)
                                    <a href="{{ route('cabinet.documents.bookings.download', [$booking, $document]) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       aria-label="Скачать документ «{{ $document->title }}»">
                                        <i class="bi bi-download" aria-hidden="true"></i> Скачать
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($isBookingStaff)
                <div class="card-footer-custom">
                    <h3 class="card-title-custom mb-3">Загрузить документ</h3>
                    <form action="{{ route('bookings.documents.store', $booking) }}"
                          method="POST"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="doc_title" class="form-label">Название <span class="booking-req">*</span></label>
                            <input type="text" name="title" id="doc_title"
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}"
                                   placeholder="Например: Договор №123">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="doc_type" class="form-label">Тип документа <span class="booking-req">*</span></label>
                            <select name="document_type" id="doc_type"
                                    class="form-select @error('document_type') is-invalid @enderror">
                                <option value="">— Выберите тип —</option>
                                @foreach($docTypeLabels as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" {{ old('document_type') === $typeKey ? 'selected' : '' }}>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            @error('document_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="doc_file" class="form-label">Файл <span class="booking-req">*</span></label>
                            <input type="file" name="file" id="doc_file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <div class="form-text">Форматы: PDF, DOC, DOCX, JPG, JPEG, PNG. Максимум 10 МБ.</div>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload" aria-hidden="true"></i> Загрузить
                        </button>
                    </form>
                </div>
            @endif
        </section>

        {{-- 7. Действия по заявке --}}
        <section class="card-custom" aria-labelledby="booking-actions-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="booking-actions-title">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i> Действия
                </h2>
            </div>

            <div class="booking-actions booking-actions--split">
                <a href="{{ route('cabinet.bookings') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> К списку заявок
                </a>

                <div class="booking-actions">
                    {{-- Действия туриста-владельца --}}
                    @if($isBookingOwnerFacing)
                        @if($booking->canTransitionTo(\App\Models\Booking::STATUS_CANCELLED))
                            @can('cancel', $booking)
                                <form action="{{ route('bookings.cancel', $booking) }}" method="POST"
                                      onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i> Отменить заявку
                                    </button>
                                </form>
                            @endcan
                        @endif
                    @endif

                    {{-- Действия назначенного менеджера / администратора --}}
                    @if($isBookingStaff)
                        @php
                            $managerCanEdit = !$isBookingAssignedManager
                                || !in_array($booking->status, [\App\Models\Booking::STATUS_CANCELLED, \App\Models\Booking::STATUS_COMPLETED], true);
                        @endphp
                        @if($managerCanEdit)
                            <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Редактировать
                            </a>
                        @endif

                        @if($booking->canTransitionTo(\App\Models\Booking::STATUS_CONFIRMED))
                            <form action="{{ route('bookings.confirm', $booking) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle" aria-hidden="true"></i> Подтвердить
                                </button>
                            </form>
                        @endif

                        @if($booking->canTransitionTo(\App\Models\Booking::STATUS_COMPLETED))
                            <form action="{{ route('bookings.complete', $booking) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-all" aria-hidden="true"></i> Завершить
                                </button>
                            </form>
                        @endif

                        @if($booking->canTransitionTo(\App\Models\Booking::STATUS_CANCELLED))
                            <form action="{{ route('bookings.cancel', $booking) }}" method="POST"
                                  onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                @csrf
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-x-circle" aria-hidden="true"></i> Отменить
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Пояснения для туриста --}}
            @if($isBookingOwnerFacing)
                @if($booking->status === \App\Models\Booking::STATUS_NEW && !$booking->manager_id)
                    <p class="booking-actions__note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        Вы можете отменить заявку до назначения менеджера.
                    </p>
                @elseif($booking->manager_id && in_array($booking->status, [\App\Models\Booking::STATUS_PROGRESS, \App\Models\Booking::STATUS_CONFIRMED], true))
                    <p class="booking-actions__note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        Заявка взята в работу менеджером. Для отмены свяжитесь с менеджером.
                    </p>
                @endif
            @endif

            {{-- Удаление — только администратор, визуально отделено --}}
            @if($isBookingAdmin)
                <div class="booking-actions booking-actions--danger">
                    <form action="{{ route('bookings.destroy', $booking) }}" method="POST"
                          onsubmit="return confirm('Удалить заявку? Это действие необратимо.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash" aria-hidden="true"></i> Удалить заявку
                        </button>
                    </form>
                    <span class="booking-actions__note">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        Удаление скрывает заявку из системы без возможности восстановления через интерфейс.
                    </span>
                </div>
            @endif
        </section>
    </div>

    {{-- Боковая колонка --}}
    <aside class="booking-layout__side" aria-label="Сводка по заявке">

        {{-- Назначение ответственного — только администратор --}}
        @if($isBookingAdmin)
            <section class="card-custom" aria-labelledby="booking-assign-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="booking-assign-title">
                        <i class="bi bi-person-plus" aria-hidden="true"></i> Ответственный
                    </h2>
                </div>
                <form action="{{ route('bookings.assign-manager', $booking) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="manager_id" class="form-label">Сотрудник (менеджер или администратор)</label>
                        <select name="manager_id" id="manager_id" class="form-select" required>
                            <option value="">— Выберите —</option>
                            @foreach($assignableEmployees as $employee)
                                <option value="{{ $employee->id }}" {{ $booking->manager_id == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        {{ $booking->manager ? 'Сменить ответственного' : 'Назначить' }}
                    </button>
                </form>
            </section>
        @endif

        {{-- Текущий статус --}}
        <section class="card-custom" aria-labelledby="booking-status-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="booking-status-title">
                    <i class="bi bi-clock-history" aria-hidden="true"></i> Статус
                </h2>
            </div>
            @include('cabinet.components.status-badge', ['status' => $booking->status])
            <p class="booking-optional mt-2 mb-0">
                Обновлён {{ $booking->updated_at->format('d.m.Y H:i') }}
                ({{ $booking->updated_at->diffForHumans() }})
            </p>
        </section>

        {{-- Участники и связь --}}
        <section class="card-custom" aria-labelledby="booking-contact-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="booking-contact-title">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    {{ $isBookingStaff ? 'Участники и связь' : 'Ваш менеджер' }}
                </h2>
            </div>

            @if($booking->manager)
                <div class="booking-person mb-3">
                    <span class="booking-person__avatar" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($booking->manager->name, 0, 1)) }}
                    </span>
                    <div class="booking-person__body">
                        <div class="booking-person__name">{{ $booking->manager->name }}</div>
                        <div class="booking-person__role">Ответственный{{ $isBookingStaff && $booking->manager->email ? ' · ' . $booking->manager->email : '' }}</div>
                    </div>
                </div>
            @else
                <p class="booking-note mb-3">
                    <i class="bi bi-hourglass" aria-hidden="true"></i>
                    Ответственный ещё не назначен@if(!$isBookingStaff) — чат откроется после назначения@endif.
                </p>
            @endif

            @if($bookingChatUrl)
                <a href="{{ $bookingChatUrl }}" class="btn btn-outline-primary w-100">
                    <i class="bi bi-chat-dots" aria-hidden="true"></i>
                    {{ $isBookingStaff ? 'Открыть переписку' : 'Чат с менеджером' }}
                </a>
            @endif
        </section>

        {{-- Информационная памятка — только для туриста-владельца --}}
        @if($isBookingOwnerFacing)
            <section class="card-custom" aria-labelledby="booking-info-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="booking-info-title">
                        <i class="bi bi-info-circle" aria-hidden="true"></i> Памятка
                    </h2>
                </div>
                @if($booking->manager_id)
                    <p class="booking-optional mb-0">
                        По вопросам по заявке используйте чат с ответственным сотрудником.
                        Документы по поездке появятся здесь после того, как сотрудник их загрузит.
                    </p>
                @else
                    <p class="booking-optional mb-0">
                        Ответственный сотрудник ещё не назначен. Чат станет доступен после назначения.
                    </p>
                @endif
            </section>
        @endif
    </aside>
</div>
@endsection
