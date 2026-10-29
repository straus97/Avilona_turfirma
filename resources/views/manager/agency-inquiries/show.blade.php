@extends('cabinet.layouts.app')

@section('title', 'Обращение клиента')

@section('sidebar')
    @include(auth()->user()->hasRole('admin') ? 'cabinet.components.sidebar.admin' : 'cabinet.components.sidebar.manager')
@endsection

@php
    $isAdminActor = auth()->user()->hasRole('admin');
    $draftLabels = [
        'destination_country' => 'Страна',
        'destination_city' => 'Город назначения',
        'departure_city' => 'Город вылета',
        'date_from' => 'Дата начала',
        'date_to' => 'Дата окончания',
        'nights_min' => 'Ночей (от)',
        'nights_max' => 'Ночей (до)',
        'adults' => 'Взрослых',
        'children' => 'Детей',
        'children_ages' => 'Возраст детей',
        'preferences' => 'Пожелания',
        'internal_notes' => 'Заметки',
    ];
    $agesDefault = is_array($draft['children_ages']) ? implode(', ', $draft['children_ages']) : '';
@endphp

@section('content')
<div class="page-header">
    <h1 class="page-title">Обращение клиента №{{ $inquiry->id }}</h1>
    <p class="page-subtitle">Обращение агентства, записанное вручную. Это не бронирование и не входящее из Tourvisor: заявка создаётся только отдельным явным действием оформления: сохранение черновика и привязка клиента её не создают.</p>
</div>

<section class="card-custom mb-4" aria-labelledby="agency-inquiry-info-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-info-title">Сведения</h2>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Канал обращения</dt><dd class="col-sm-8">{{ $inquiry->contactChannelLabel() }}</dd>
        <dt class="col-sm-4">Статус</dt><dd class="col-sm-8">{{ $inquiry->statusLabel() }}</dd>
        <dt class="col-sm-4">Создано</dt><dd class="col-sm-8">{{ $inquiry->created_at?->format('d.m.Y H:i') ?? '—' }}@if($inquiry->creator) · {{ $inquiry->creator->name }}@endif</dd>
        <dt class="col-sm-4">Ответственный</dt><dd class="col-sm-8">{{ $inquiry->assignee?->name ?? '—' }}</dd>
        <dt class="col-sm-4">Клиент</dt><dd class="col-sm-8 {{ $clientLinked ? '' : 'text-muted' }}">{{ $clientLinked ? ($client ? $client->name : 'Аккаунт привязан (профиль недоступен)') : 'Аккаунт не привязан' }}</dd>
        <dt class="col-sm-4">Версия черновика</dt><dd class="col-sm-8">{{ $draft['revision'] }}@if($inquiry->draft_updated_at) · сохранено {{ $inquiry->draft_updated_at->format('d.m.Y H:i') }}@endif</dd>
    </dl>
</section>

@if($inquiry->status === \App\Models\Inquiry::STATUS_CLOSED)
<section class="alert alert-secondary" role="region" aria-labelledby="agency-inquiry-closed-title">
    <h2 class="h6" id="agency-inquiry-closed-title">Обращение закрыто без заявки</h2>
    <p class="mb-1">Карточка доступна только для чтения: черновик, контакт и клиента изменить нельзя, заявка не создавалась.</p>
    <p class="mb-0 small" style="overflow-wrap: anywhere;">
        @if($inquiry->closed_at)<time datetime="{{ $inquiry->closed_at->toIso8601String() }}">{{ $inquiry->closed_at->format('d.m.Y H:i') }}</time>@endif
        @if($closureEvidence['actor']) · {{ $closureEvidence['actor'] }}@endif
        @if($closureEvidence['reason'])<br><span class="text-muted">Причина:</span> {{ $closureEvidence['reason'] }}@else<br><span class="text-muted">Причина закрытия не записана.</span>@endif
    </p>
</section>
@endif

@if($inquiry->status === \App\Models\Inquiry::STATUS_CONVERTED)
<section class="alert alert-success" role="region" aria-labelledby="agency-inquiry-converted-title" id="agency-inquiry-converted">
    <h2 class="h6" id="agency-inquiry-converted-title">Обращение оформлено в заявку</h2>
    <p class="mb-1">Карточка доступна только для чтения: черновик, контакт, ответственного и клиента изменить нельзя. История обращения сохранена.</p>
    <p class="mb-1 small" style="overflow-wrap: anywhere;">
        @if($convertedEvidence['at'])<time datetime="{{ $convertedEvidence['at']->toIso8601String() }}">{{ $convertedEvidence['at']->format('d.m.Y H:i') }}</time>@endif
        @if($convertedEvidence['actor']) · оформил(а): {{ $convertedEvidence['actor'] }}@endif
    </p>
    @if($convertedBookingUrl)
        <a href="{{ $convertedBookingUrl }}" class="btn btn-success btn-sm" id="agency-inquiry-booking-link">Открыть заявку</a>
    @elseif($convertedBookingDeleted)
        <p class="mb-0 small">Созданная заявка удалена (администратором или вместе с аккаунтом клиента). Запись об оформлении сохранена.</p>
    @else
        <p class="mb-0 small">Заявка ведётся другим сотрудником — ссылка недоступна.</p>
    @endif
</section>
@endif

@if($canConvert)
<section class="card-custom mb-4" aria-labelledby="agency-inquiry-convert-title" id="agency-inquiry-convert">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-convert-title">Оформить заявку</h2>
    </div>
    @error('request')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('conversion')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('conversion_client')<div class="alert alert-warning" role="alert" id="agency-inquiry-convert-client-error"><strong>Нужен клиент.</strong> {{ $message }}</div>@enderror
    @error('expected_status')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('expected_owner_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('expected_client_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <p class="text-muted small">Оформление создаёт одну заявку Avilona со статусом «В работе» и необратимо переводит обращение в «Конвертировано в заявку». Цена, оплата и бронирование у туроператора при этом не создаются. Повторная отправка формы вторую заявку не создаст.</p>
    <dl class="row small mb-3">
        <dt class="col-sm-4">Клиент (владелец заявки)</dt>
        <dd class="col-sm-8 text-break">{{ $clientLinked ? ($client?->name ?? 'Аккаунт привязан') : 'не привязан' }}</dd>
        <dt class="col-sm-4">Ответственный по заявке</dt>
        <dd class="col-sm-8 text-break">{{ $inquiry->assignee?->name ?? '—' }}</dd>
    </dl>

    @if(! $convertClientReady)
        <div class="alert alert-secondary" role="status" id="agency-inquiry-convert-blocked">
            @if($convertPendingVerification)
                Запрос на проверку клиента ожидает решения администратора. Пока он не решён или не отклонён, оформить заявку нельзя.
            @else
                Чтобы оформить заявку, сначала привяжите к обращению существующий аккаунт клиента (блок «Клиент» ниже). Имя, телефон и email из контакта аккаунт не выбирают, и клиент автоматически не создаётся.
            @endif
        </div>
    @endif

    <details @if($errors->any() && ! $errors->has('conversion_client')) open @endif>
        <summary class="mb-2">Проверить данные поездки и оформить</summary>
        <p class="text-muted small">Значения подставлены из черновика. Проверьте их и при необходимости исправьте: в заявку попадут именно эти значения. Внутренние заметки черновика в заявку не переносятся.</p>
        <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.convert', $inquiry) }}" class="row g-3" data-submit-once novalidate>
            @csrf
            <input type="hidden" name="expected_status" value="{{ $inquiry->status }}">
            <input type="hidden" name="expected_owner_id" value="{{ $inquiry->assigned_to }}">
            <input type="hidden" name="expected_client_id" value="{{ $inquiry->client_user_id }}">

            <div class="col-12 col-md-4">
                <label for="cv-destination-country" class="form-label">Страна <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="cv-destination-country" name="booking[destination_country]" value="{{ old('booking.destination_country', $convertDefaults['destination_country']) }}" class="form-control @error('booking.destination_country') is-invalid @enderror" maxlength="255" required>
                @error('booking.destination_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="cv-destination-city" class="form-label">Город или курорт</label>
                <input type="text" id="cv-destination-city" name="booking[destination_city]" value="{{ old('booking.destination_city', $convertDefaults['destination_city']) }}" class="form-control @error('booking.destination_city') is-invalid @enderror" maxlength="255">
                @error('booking.destination_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="cv-departure-city" class="form-label">Город вылета <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="cv-departure-city" name="booking[departure_city]" value="{{ old('booking.departure_city', $convertDefaults['departure_city']) }}" class="form-control @error('booking.departure_city') is-invalid @enderror" maxlength="255" required>
                @error('booking.departure_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="cv-start-date" class="form-label">Дата вылета (с) <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" id="cv-start-date" name="booking[start_date]" value="{{ old('booking.start_date', $convertDefaults['start_date']) }}" class="form-control @error('booking.start_date') is-invalid @enderror" required>
                @error('booking.start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-start-date-end" class="form-label">Дата вылета (по)</label>
                <input type="date" id="cv-start-date-end" name="booking[start_date_end]" value="{{ old('booking.start_date_end', $convertDefaults['start_date_end']) }}" class="form-control @error('booking.start_date_end') is-invalid @enderror">
                @error('booking.start_date_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-nights" class="form-label">Ночей (от) <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" id="cv-nights" name="booking[nights]" value="{{ old('booking.nights', $convertDefaults['nights']) }}" class="form-control @error('booking.nights') is-invalid @enderror" min="1" max="30" inputmode="numeric" required>
                @error('booking.nights')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-nights-max" class="form-label">Ночей (до)</label>
                <input type="number" id="cv-nights-max" name="booking[nights_max]" value="{{ old('booking.nights_max', $convertDefaults['nights_max']) }}" class="form-control @error('booking.nights_max') is-invalid @enderror" min="1" max="30" inputmode="numeric">
                @error('booking.nights_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="cv-adults" class="form-label">Взрослых <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" id="cv-adults" name="booking[adults]" value="{{ old('booking.adults', $convertDefaults['adults']) }}" class="form-control @error('booking.adults') is-invalid @enderror" min="1" max="10" inputmode="numeric" required>
                @error('booking.adults')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-children" class="form-label">Детей</label>
                <input type="number" id="cv-children" name="booking[children]" value="{{ old('booking.children', $convertDefaults['children']) }}" class="form-control @error('booking.children') is-invalid @enderror" min="0" max="10" inputmode="numeric">
                @error('booking.children')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="cv-children-ages" class="form-label">Возраст детей</label>
                <input type="text" id="cv-children-ages" name="booking[children_ages]" value="{{ is_array(old('booking.children_ages')) ? implode(', ', old('booking.children_ages')) : old('booking.children_ages', $convertDefaults['children_ages']) }}" class="form-control @error('booking.children_ages') is-invalid @enderror @error('booking.children_ages.*') is-invalid @enderror" autocomplete="off" aria-describedby="cv-children-ages-help">
                <div id="cv-children-ages-help" class="form-text">Через запятую, от 0 до 17 лет, например: 5, 8.</div>
                @error('booking.children_ages')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('booking.children_ages.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="cv-notes" class="form-label">Информация для клиента</label>
                <textarea id="cv-notes" name="booking[notes]" rows="2" maxlength="5000" class="form-control @error('booking.notes') is-invalid @enderror" aria-describedby="cv-notes-help">{{ old('booking.notes', $convertDefaults['notes']) }}</textarea>
                <div id="cv-notes-help" class="form-text">Видна клиенту в его заявке. Не указывайте пароли и паспортные данные.</div>
                @error('booking.notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="cv-manager-notes" class="form-label">Заметки менеджера</label>
                <textarea id="cv-manager-notes" name="booking[manager_notes]" rows="2" maxlength="5000" class="form-control @error('booking.manager_notes') is-invalid @enderror">{{ old('booking.manager_notes') }}</textarea>
                @error('booking.manager_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input @error('confirm_conversion') is-invalid @enderror" type="checkbox" id="cv-confirm" name="confirm_conversion" value="1" required @checked(old('confirm_conversion'))>
                    <label class="form-check-label" for="cv-confirm">Клиент согласен, я оформляю заявку по этому обращению. Это действие необратимо.</label>
                    @error('confirm_conversion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success" @disabled(! $convertClientReady)>Оформить заявку</button>
            </div>
        </form>
    </details>
</section>
@endif

@if($canClose)
<section class="card-custom mb-4" aria-labelledby="agency-inquiry-close-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-close-title">Закрыть обращение</h2>
    </div>
    <details @if($errors->has('reason') || $errors->has('verification')) open @endif>
        <summary class="mb-2">Клиент отказался или обращение неактуально — закрыть без заявки</summary>
        <p class="text-muted small">Закрытие необратимо: после него нельзя менять черновик, контакт и клиента, а вернуть обращение в работу или оформить его нельзя. Причина сохраняется в истории и видна сотрудникам, имеющим доступ к обращению.</p>
        <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.close', $inquiry) }}" data-submit-once novalidate>
            @csrf
            <input type="hidden" name="expected_status" value="{{ $inquiry->status }}">
            <input type="hidden" name="expected_owner_id" value="{{ $inquiry->assigned_to }}">
            <div class="mb-3">
                <label for="ai-close-reason" class="form-label">Причина закрытия</label>
                <textarea id="ai-close-reason" name="reason" rows="2" required minlength="{{ \App\Services\Inquiries\InquiryClosure::REASON_MIN_LENGTH }}" maxlength="{{ \App\Services\Inquiries\InquiryClosure::REASON_MAX_LENGTH }}" class="form-control @error('reason') is-invalid @enderror" aria-describedby="ai-close-reason-help">{{ old('reason') }}</textarea>
                <div id="ai-close-reason-help" class="form-text">От {{ \App\Services\Inquiries\InquiryClosure::REASON_MIN_LENGTH }} до {{ \App\Services\Inquiries\InquiryClosure::REASON_MAX_LENGTH }} символов. Не указывайте пароли, паспортные данные, телефоны и email клиентов.</div>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('verification')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-danger">Закрыть обращение без заявки</button>
        </form>
    </details>
</section>
@endif

@if($canAssign)
<section class="card-custom mb-4" aria-labelledby="agency-inquiry-assignment-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-assignment-title">Ответственный менеджер</h2>
    </div>
    <p class="mb-3"><span class="text-muted">Сейчас отвечает:</span> <strong>{{ $inquiry->assignee?->name ?? 'не назначен' }}</strong></p>

    @if($assignmentOpen)
        @php
            $hasAssignee = $inquiry->assigned_to !== null;
        @endphp
        <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.assign', $inquiry) }}" data-submit-once novalidate>
            @csrf
            <input type="hidden" name="expected_assignee_id" value="{{ $inquiry->assigned_to }}">
            <div class="mb-3">
                <label for="ai-assignee" class="form-label">{{ $hasAssignee ? 'Передать обращение' : 'Назначить сотрудника' }}</label>
                <select id="ai-assignee" name="assignee_id" class="form-select @error('assignee_id') is-invalid @enderror" required>
                    <option value="">— Выберите сотрудника —</option>
                    @foreach($eligibleStaff as $employee)
                        @continue($employee['id'] === (int) $inquiry->assigned_to)
                        <option value="{{ $employee['id'] }}" @selected((int) old('assignee_id') === $employee['id'])>{{ $employee['name'] }} ({{ $employee['role'] }})</option>
                    @endforeach
                </select>
                @error('assignee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('expected_assignee_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="ai-assignment-reason" class="form-label">{{ $hasAssignee ? 'Причина переназначения' : 'Причина назначения' }}</label>
                <textarea id="ai-assignment-reason" name="reason" rows="2" required minlength="{{ \App\Services\Inquiries\InquiryAssignment::REASON_MIN_LENGTH }}" maxlength="{{ \App\Services\Inquiries\InquiryAssignment::REASON_MAX_LENGTH }}" class="form-control @error('reason') is-invalid @enderror" aria-describedby="ai-assignment-reason-help">{{ old('reason') }}</textarea>
                <div id="ai-assignment-reason-help" class="form-text">От {{ \App\Services\Inquiries\InquiryAssignment::REASON_MIN_LENGTH }} до {{ \App\Services\Inquiries\InquiryAssignment::REASON_MAX_LENGTH }} символов; сохраняется в истории назначений и видна администраторам. Не указывайте пароли, паспортные данные, телефоны и email клиентов.</div>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ $hasAssignee ? 'Передать обращение' : 'Назначить сотрудника' }}</button>
        </form>
    @else
        <p class="text-muted mb-0">Обращение не находится в работе — ответственного изменить нельзя.</p>
    @endif

    <h3 class="h6 mt-4" id="agency-inquiry-history-title">История назначений</h3>
    @if($assignmentHistory->isEmpty())
        <p class="text-muted small mb-0">Изменений ответственного пока не записано. Первоначальный ответственный, назначенный администратором при создании, и передачи отображаются здесь из записанных событий; для обращений менеджеров ответственный при создании — сам создатель.</p>
    @else
        <ul class="list-unstyled small mb-0" aria-labelledby="agency-inquiry-history-title">
            @foreach($assignmentHistory as $entry)
                @php
                    $who = fn (?int $id, ?string $name, string $none) => $id === null ? $none : ($name ?? 'учётная запись удалена');
                @endphp
                <li class="mb-2" style="overflow-wrap: anywhere;">
                    <strong>{{ $entry['created'] ? 'Создано администратором' : $entry['type'] }}</strong>
                    · <time datetime="{{ $entry['at']?->toIso8601String() }}">{{ $entry['at']?->format('d.m.Y H:i') }}</time>
                    · {{ $entry['actor'] ?? 'администратор не определён' }}:
                    @if($entry['created'])
                        первый ответственный — {{ $who($entry['new_id'], $entry['new'], '—') }}
                    @else
                        {{ $who($entry['previous_id'], $entry['previous'], 'не назначен') }} → {{ $who($entry['new_id'], $entry['new'], '—') }}
                    @endif
                    @if($entry['reason'])<br><span class="text-muted">Причина: {{ $entry['reason'] }}</span>@endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif

@include('manager.agency-inquiries.partials.contact')

@include('manager.agency-inquiries.partials.client')

@if($draftConflict)
<section class="alert alert-warning" role="region" aria-labelledby="agency-inquiry-conflict-title">
    <h2 class="h6" id="agency-inquiry-conflict-title">Ваши несохранённые значения</h2>
    @if($draftConflict === [])
        <p class="mb-0">В отправленной форме не было заполненных полей.</p>
    @else
        <p class="small">Эти значения не записаны. Сверьте их с актуальной версией ниже и при необходимости внесите правки заново.</p>
        <dl class="row mb-0 small">
            @foreach($draftConflict as $field => $value)
                @if(isset($draftLabels[$field]))
                    <dt class="col-sm-4">{{ $draftLabels[$field] }}</dt><dd class="col-sm-8" style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $value }}</dd>
                @endif
            @endforeach
        </dl>
    @endif
</section>
@endif

<section class="card-custom mb-4" aria-labelledby="agency-inquiry-draft-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-draft-title">Данные поездки (черновик)</h2>
    </div>
    <p class="text-muted small">Все поля необязательны: заполняйте то, что клиент уже сообщил. Паспортные данные, пароли и содержимое переписки сюда не записываются.
        @if($isAdminActor) Вы вносите изменения как администратор. @endif
    </p>

    <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.draft.update', $inquiry) }}" class="row g-3" data-submit-once novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" value="{{ $draft['revision'] }}">
        <fieldset class="col-12 border-0 p-0 m-0" @disabled(! $canEdit)>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="ai-destination-country" class="form-label">Страна</label>
                <input type="text" id="ai-destination-country" name="destination_country" value="{{ old('destination_country', $draft['destination_country']) }}" class="form-control @error('destination_country') is-invalid @enderror" maxlength="128">
                @error('destination_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="ai-destination-city" class="form-label">Город или курорт</label>
                <input type="text" id="ai-destination-city" name="destination_city" value="{{ old('destination_city', $draft['destination_city']) }}" class="form-control @error('destination_city') is-invalid @enderror" maxlength="128">
                @error('destination_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="ai-departure-city" class="form-label">Город вылета</label>
                <input type="text" id="ai-departure-city" name="departure_city" value="{{ old('departure_city', $draft['departure_city']) }}" class="form-control @error('departure_city') is-invalid @enderror" maxlength="128">
                @error('departure_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="ai-date-from" class="form-label">Дата начала</label>
                <input type="date" id="ai-date-from" name="date_from" value="{{ old('date_from', $draft['date_from']) }}" class="form-control @error('date_from') is-invalid @enderror">
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="ai-date-to" class="form-label">Дата окончания</label>
                <input type="date" id="ai-date-to" name="date_to" value="{{ old('date_to', $draft['date_to']) }}" class="form-control @error('date_to') is-invalid @enderror">
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="ai-nights-min" class="form-label">Ночей (от)</label>
                <input type="number" id="ai-nights-min" name="nights_min" value="{{ old('nights_min', $draft['nights_min']) }}" class="form-control @error('nights_min') is-invalid @enderror" min="1" max="30" inputmode="numeric">
                @error('nights_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="ai-nights-max" class="form-label">Ночей (до)</label>
                <input type="number" id="ai-nights-max" name="nights_max" value="{{ old('nights_max', $draft['nights_max']) }}" class="form-control @error('nights_max') is-invalid @enderror" min="1" max="30" inputmode="numeric">
                @error('nights_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="ai-adults" class="form-label">Взрослых</label>
                <input type="number" id="ai-adults" name="adults" value="{{ old('adults', $draft['adults']) }}" class="form-control @error('adults') is-invalid @enderror" min="1" max="10" inputmode="numeric">
                @error('adults')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="ai-children" class="form-label">Детей</label>
                <input type="number" id="ai-children" name="children" value="{{ old('children', $draft['children']) }}" class="form-control @error('children') is-invalid @enderror" min="0" max="10" inputmode="numeric">
                @error('children')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="ai-children-ages" class="form-label">Возраст детей</label>
                <input type="text" id="ai-children-ages" name="children_ages" value="{{ old('children_ages', $agesDefault) }}" class="form-control @error('children_ages') is-invalid @enderror @error('children_ages.*') is-invalid @enderror" aria-describedby="ai-children-ages-help" autocomplete="off">
                <div id="ai-children-ages-help" class="form-text">Через запятую, от 0 до 17 лет. Количество возрастов должно совпадать с числом детей, например: 5, 8.</div>
                @error('children_ages')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('children_ages.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="ai-preferences" class="form-label">Пожелания клиента</label>
                <textarea id="ai-preferences" name="preferences" rows="3" maxlength="{{ \App\Services\Inquiries\InquiryDraftData::PREFERENCES_MAX_LENGTH }}" class="form-control @error('preferences') is-invalid @enderror">{{ old('preferences', $draft['preferences']) }}</textarea>
                @error('preferences')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="ai-internal-notes" class="form-label">Внутренние заметки</label>
                <textarea id="ai-internal-notes" name="internal_notes" rows="4" maxlength="{{ \App\Services\Inquiries\InquiryDraftData::NOTES_MAX_LENGTH }}" class="form-control @error('internal_notes') is-invalid @enderror" aria-describedby="ai-notes-help">{{ old('internal_notes', $draft['internal_notes']) }}</textarea>
                <div id="ai-notes-help" class="form-text">Видны только сотрудникам агентства. Не записывайте сюда паспортные данные и пароли.</div>
                @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        </fieldset>

        <div class="col-12">
            @error('draft')<div class="text-danger small mb-2" role="alert">{{ $message }}</div>@enderror
            @if($canEdit)
                <button type="submit" class="btn btn-primary">Сохранить черновик</button>
            @else
                <p class="text-muted mb-0">Обращение закрыто или оформлено в заявку — черновик доступен только для чтения.</p>
            @endif
        </div>
    </form>
</section>

<div class="mt-3">
    <a href="{{ $backUrl }}">← К списку обращений клиентов</a>
</div>
@endsection

@push('scripts')
<script>
    // Защита от двойной отправки: после первой отправки кнопка блокируется.
    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
        });
    });
</script>
@endpush
