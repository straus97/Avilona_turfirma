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
    <p class="page-subtitle">Обращение агентства, записанное вручную. Это не бронирование и не входящее из Tourvisor: заявка создаётся только отдельным действием оформления, которое появится позже.</p>
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
                <p class="text-muted mb-0">Обращение закрыто для редактирования.</p>
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
