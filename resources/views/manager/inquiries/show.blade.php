@extends('cabinet.layouts.app')

@section('title', 'Входящее обращение')

@section('sidebar')
    @include(auth()->user()->hasRole('admin') ? 'cabinet.components.sidebar.admin' : 'cabinet.components.sidebar.manager')
@endsection

@php
    $dash = fn ($value) => ($value === null || $value === '') ? '—' : $value;
    $isConverted = $inquiry->workflow_state === \App\Models\IncomingInquiry::WORKFLOW_CONVERTED;
    $isClosed = $inquiry->workflow_state === \App\Models\IncomingInquiry::WORKFLOW_CLOSED;
    $isNew = $inquiry->workflow_state === \App\Models\IncomingInquiry::WORKFLOW_NEW;
    $currency = strtoupper(trim((string) $inquiry->currency));
    $isRub = in_array($currency, ['RUB', 'RUR'], true);
    $referencePrice = $inquiry->price !== null ? number_format((float) $inquiry->price, 0, ',', ' ') . ' ' . $currency : null;
    $client = $inquiry->clientUser;

    // Предзаполнение формы — только справочное; менеджер проверяет и правит каждое поле.
    $defaultNotes = trim(implode("\n", array_filter([
        $inquiry->hotel_name ? 'Отель: ' . $inquiry->hotel_name : null,
        $inquiry->client_comment ? 'Комментарий клиента: ' . $inquiry->client_comment : null,
    ])));
    $defaultManagerNotes = trim(implode("\n", array_filter([
        'Источник: входящее обращение Tourvisor #' . $inquiry->external_id . '.',
        $inquiry->operator_name ? 'Оператор (по обращению): ' . $inquiry->operator_name . '.' : null,
        $referencePrice ? 'Справочная цена из обращения: ' . $referencePrice . ' (не подтверждена).' : null,
    ])));
@endphp

@section('content')
<div class="page-header">
    <h1 class="page-title">Входящее обращение · Tourvisor #{{ $inquiry->external_id }}</h1>
    @if($isConverted)
        <p class="page-subtitle">Обращение оформлено как заявка Avilona. Дальнейшая работа ведётся в заявке.</p>
    @else
        <p class="page-subtitle">Входящее обращение, не бронирование. Источник: Tourvisor. Проверьте цену и наличие, свяжитесь с клиентом и при необходимости оформите заявку в Avilona вручную. Бронирование у туроператора и оплата здесь не выполняются.</p>
    @endif
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    @if($inquiry->isActionable())
        <span class="badge text-bg-primary fs-6">{{ $inquiry->workflowLabel() }}</span>
    @else
        <span class="badge text-bg-secondary fs-6">{{ $inquiry->stateLabel() }}</span>
    @endif
    <span class="text-muted">Ответственный: {{ $inquiry->assignee?->name ?? 'не назначен' }}</span>
</div>

{{-- 1. Импортированные данные --}}
<section class="card-custom mb-4" aria-labelledby="inq-imported-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-imported-title">1. Данные из Tourvisor</h2>
    </div>
    <dl class="row mx-0 mb-0">
        <dt class="col-sm-4">Клиент (имя)</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_name) }}</dd>
        <dt class="col-sm-4">Телефон</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_phone) }}</dd>
        <dt class="col-sm-4">Email</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_email) }}</dd>
        <dt class="col-sm-4">Комментарий клиента</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->client_comment) }}</dd>
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
        <dd class="col-sm-8">{{ $referencePrice ?? '—' }}</dd>
    </dl>
    <p class="text-muted mt-3 mb-0">Эти данные не связаны с аккаунтом Avilona автоматически. Цена — справочная (стоимость предложения целиком). Цена и наличие мест не подтверждены — их проверяет менеджер.</p>
    <dl class="row mx-0 mb-0 mt-3 small text-muted">
        <dt class="col-sm-4">Загрузка данных</dt>
        <dd class="col-sm-8">{{ $inquiry->stateLabel() }}@if($inquiry->last_error_code) · ошибка: {{ $inquiry->last_error_code }}@endif</dd>
        <dt class="col-sm-4">Уведомление получено</dt>
        <dd class="col-sm-8">{{ $inquiry->first_notified_at?->format('d.m.Y H:i') ?? '—' }} (уведомлений: {{ $inquiry->webhook_count }})</dd>
        <dt class="col-sm-4">Данные загружены</dt>
        <dd class="col-sm-8">{{ $inquiry->imported_at?->format('d.m.Y H:i') ?? '—' }}</dd>
    </dl>
</section>

@if(! $inquiry->isActionable())
    <div class="alert alert-secondary" role="status">Это обращение пока нельзя обработать: данные не загружены или тип обращения не поддерживается.</div>
@else

{{-- 2. Ответственный --}}
<section class="card-custom mb-4" aria-labelledby="inq-owner-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-owner-title">2. Ответственный</h2>
    </div>

    @if($inquiry->assignee)
        <p class="mb-2">В работе у: <strong>{{ $inquiry->assignee->name }}</strong>@if($inquiry->claimed_at), с {{ $inquiry->claimed_at->format('d.m.Y H:i') }}@endif.</p>
    @else
        <p class="mb-2">Обращение никем не взято в работу.</p>
    @endif

    @if($canClaim)
        <form method="POST" action="{{ route('cabinet.manager.inquiries.claim', $inquiry) }}" data-submit-once>
            @csrf
            <button type="submit" class="btn btn-primary">Взять в работу</button>
        </form>
    @endif

    @if($canReassign)
        <form method="POST" action="{{ route('cabinet.manager.inquiries.reassign', $inquiry) }}" class="row g-2 align-items-end mt-2" data-submit-once>
            @csrf
            <div class="col-12 col-md-6">
                <label for="inq-assigned-to" class="form-label">Передать другому сотруднику</label>
                <select id="inq-assigned-to" name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
                    @foreach($assignableStaff as $staff)
                        <option value="{{ $staff->id }}" @selected((int) old('assigned_to', $inquiry->assigned_to) === $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
                @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-outline-primary">Назначить</button>
            </div>
        </form>
    @endif
</section>

@if($canProcess)
{{-- 3. Клиент --}}
<section class="card-custom mb-4" aria-labelledby="inq-client-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-client-title">3. Клиент (аккаунт в Avilona)</h2>
    </div>

    @if($client)
        <div class="alert alert-success" role="status">
            Выбран клиент: <strong>{{ $client->name }}</strong> · {{ $client->hasTechnicalEmail() ? 'email не указан' : $client->email }}@if($client->phone) · {{ $client->phone }}@endif
        </div>
        @if(! $client->hasTechnicalEmail() && $client->email_verified_at === null && $client->last_login_at === null)
            <form method="POST" action="{{ route('cabinet.manager.inquiries.client.password-setup', $inquiry) }}" class="mb-3" data-submit-once>
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Отправить клиенту ссылку для установки пароля</button>
                <span class="text-muted small d-block mt-1">Клиент ещё не входил в кабинет. Письмо уйдёт на {{ $client->email }} только по вашему нажатию.</span>
            </form>
        @endif
    @else
        <p class="text-muted">Аккаунт не выбран. Совпадение контактов — только подсказка: выберите клиента явно.</p>
    @endif

    @if($candidates->isNotEmpty())
        <h3 class="h6">Возможные совпадения по контактам обращения</h3>
        @include('manager.inquiries._client-list', ['entries' => $candidates->map(fn ($c) => ['user' => $c['user'], 'reasons' => $c['reasons']]), 'inquiry' => $inquiry, 'client' => $client])
    @endif

    <form method="GET" action="{{ route('cabinet.manager.inquiries.show', $inquiry) }}" class="row g-2 align-items-end mt-2">
        <div class="col-12 col-md-6">
            <label for="inq-client-q" class="form-label">Найти существующего клиента (имя, email или телефон)</label>
            <input type="search" id="inq-client-q" name="client_q" value="{{ $searchTerm }}" class="form-control" minlength="2" maxlength="100">
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-outline-primary">Найти</button>
        </div>
    </form>

    @if($searchTerm !== '')
        <div class="mt-3">
            @if($searchResults->isEmpty())
                <p class="text-muted mb-0">По запросу «{{ $searchTerm }}» активных туристов не найдено.</p>
            @else
                @include('manager.inquiries._client-list', ['entries' => $searchResults->map(fn ($u) => ['user' => $u, 'reasons' => []]), 'inquiry' => $inquiry, 'client' => $client])
            @endif
        </div>
    @endif

    <hr>
    <h3 class="h6">Нового клиента создавать, только если аккаунта точно нет</h3>
    <form method="POST" action="{{ route('cabinet.manager.inquiries.client.create', $inquiry) }}" class="row g-3" data-submit-once>
        @csrf
        <div class="col-12 col-md-4">
            <label for="inq-new-name" class="form-label">Имя клиента</label>
            <input type="text" id="inq-new-name" name="client_name" value="{{ old('client_name', $inquiry->client_name) }}" class="form-control @error('client_name') is-invalid @enderror" maxlength="255" required>
            @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label for="inq-new-email" class="form-label">Email (необязательно)</label>
            <input type="email" id="inq-new-email" name="client_email" value="{{ old('client_email', $inquiry->client_email) }}" class="form-control @error('client_email') is-invalid @enderror" maxlength="255">
            @error('client_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label for="inq-new-phone" class="form-label">Телефон (необязательно)</label>
            <input type="text" id="inq-new-phone" name="client_phone" value="{{ old('client_phone', $inquiry->client_phone) }}" class="form-control @error('client_phone') is-invalid @enderror" maxlength="20">
            @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-outline-primary">Создать клиента и выбрать</button>
            <p class="text-muted small mt-2 mb-0">Пароль не создаётся и не показывается. Email считается неподтверждённым. Если такой email уже есть в системе, аккаунт не будет изменён — выберите его из списка.</p>
        </div>
    </form>
</section>

{{-- 4. Оформление заявки --}}
<section class="card-custom mb-4" aria-labelledby="inq-convert-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-convert-title">4. Оформить заявку Avilona</h2>
    </div>

    @if(! $client)
        <p class="text-muted mb-0">Сначала выберите существующего клиента или создайте нового (шаг 3).</p>
    @else
        <p class="text-muted">Создаётся обычная заявка Avilona для клиента <strong>{{ $client->name }}</strong>, ответственный — <strong>{{ $inquiry->assignee?->name }}</strong>, статус «В обработке». Бронирование у туроператора и оплата этим действием не выполняются. Проверьте и при необходимости исправьте все поля.</p>

        <form method="POST" action="{{ route('cabinet.manager.inquiries.convert', $inquiry) }}" class="row g-3" data-submit-once>
            @csrf
            <div class="col-12 col-md-4">
                <label for="cv-departure" class="form-label">Город вылета</label>
                <input type="text" id="cv-departure" name="departure_city" value="{{ old('departure_city', $inquiry->departure_city) }}" class="form-control @error('departure_city') is-invalid @enderror" maxlength="255" required>
                @error('departure_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="cv-country" class="form-label">Страна</label>
                <input type="text" id="cv-country" name="destination_country" value="{{ old('destination_country', $inquiry->destination_country) }}" class="form-control @error('destination_country') is-invalid @enderror" maxlength="255" required>
                @error('destination_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="cv-city" class="form-label">Курорт / город (необязательно)</label>
                <input type="text" id="cv-city" name="destination_city" value="{{ old('destination_city') }}" class="form-control @error('destination_city') is-invalid @enderror" maxlength="255">
                @error('destination_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-start" class="form-label">Дата вылета (с)</label>
                <input type="date" id="cv-start" name="start_date" value="{{ old('start_date', $inquiry->fly_date?->format('Y-m-d')) }}" class="form-control @error('start_date') is-invalid @enderror" required>
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-start-end" class="form-label">Дата вылета (по)</label>
                <input type="date" id="cv-start-end" name="start_date_end" value="{{ old('start_date_end') }}" class="form-control @error('start_date_end') is-invalid @enderror">
                @error('start_date_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-nights" class="form-label">Ночей (от)</label>
                <input type="number" id="cv-nights" name="nights" value="{{ old('nights', $inquiry->nights) }}" class="form-control @error('nights') is-invalid @enderror" min="1" max="30" required>
                @error('nights')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-nights-max" class="form-label">Ночей (до)</label>
                <input type="number" id="cv-nights-max" name="nights_max" value="{{ old('nights_max') }}" class="form-control @error('nights_max') is-invalid @enderror" min="1" max="30">
                @error('nights_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-adults" class="form-label">Взрослых</label>
                <input type="number" id="cv-adults" name="adults" value="{{ old('adults') }}" class="form-control @error('adults') is-invalid @enderror" min="1" max="10" required>
                @error('adults')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="cv-children" class="form-label">Детей</label>
                <input type="number" id="cv-children" name="children" value="{{ old('children', 0) }}" class="form-control @error('children') is-invalid @enderror" min="0" max="10">
                @error('children')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="cv-ages" class="form-label">Возраст детей (через запятую)</label>
                <input type="text" id="cv-ages" name="children_ages" value="{{ old('children_ages') ? (is_array(old('children_ages')) ? implode(', ', old('children_ages')) : old('children_ages')) : '' }}" class="form-control @error('children_ages') is-invalid @enderror @error('children_ages.*') is-invalid @enderror" placeholder="например: 5, 8">
                @error('children_ages')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('children_ages.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <div class="border rounded p-3">
                    <label for="cv-total" class="form-label fw-semibold">Итоговая стоимость всей заявки, ₽ (проверенная менеджером)</label>
                    <div class="input-group">
                        <input type="number" id="cv-total" name="total_price" value="{{ old('total_price') }}" class="form-control @error('total_price') is-invalid @enderror" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" aria-describedby="cv-total-help" required>
                        <span class="input-group-text">₽</span>
                        @error('total_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div id="cv-total-help" class="form-text">
                        Это общая сумма за всю заявку (все туристы, весь тур), а не цена за человека или за ночь. На число ничего не умножается.
                        @if($referencePrice)
                            <br>Справочно из Tourvisor: <strong>{{ $referencePrice }}</strong> — не подтверждено, проверьте актуальную цену у оператора.
                            @if(! $isRub)
                                <br><strong>Валюта обращения не рубли.</strong> Заявка хранится в рублях — введите итоговую сумму в рублях вручную.
                            @endif
                        @else
                            <br>Справочной цены в обращении нет.
                        @endif
                    </div>
                    @if($referencePrice && $isRub)
                        <button type="button" class="btn btn-link btn-sm px-0" data-fill-reference="{{ (float) $inquiry->price }}" data-target="cv-total">Подставить справочную цену ({{ $referencePrice }}) и проверить</button>
                    @endif
                    <div class="form-check mt-3">
                        <input class="form-check-input @error('price_verified') is-invalid @enderror" type="checkbox" id="cv-verified" name="price_verified" value="1" @checked(old('price_verified')) required>
                        <label class="form-check-label" for="cv-verified">Я проверил актуальную цену и наличие мест, указанная сумма — итоговая стоимость всей заявки.</label>
                        @error('price_verified')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label for="cv-notes" class="form-label">Информация для клиента (видна клиенту в заявке)</label>
                <textarea id="cv-notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" maxlength="5000">{{ old('notes', $defaultNotes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="cv-manager-notes" class="form-label">Заметки менеджера (только для сотрудников)</label>
                <textarea id="cv-manager-notes" name="manager_notes" rows="4" class="form-control @error('manager_notes') is-invalid @enderror" maxlength="5000">{{ old('manager_notes', $defaultManagerNotes) }}</textarea>
                @error('manager_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">Создать заявку Avilona</button>
            </div>
        </form>
    @endif
</section>
@endif

@endif

{{-- Итог: оформлено / закрыто --}}
@if($isConverted)
<section class="card-custom mb-4" aria-labelledby="inq-result-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-result-title">Результат: заявка Avilona</h2>
    </div>
    <dl class="row mx-0 mb-0">
        <dt class="col-sm-4">Заявка</dt>
        <dd class="col-sm-8">
            @if($inquiry->booking)
                @can('view', $inquiry->booking)
                    <a href="{{ route('bookings.show', $inquiry->booking) }}">Заявка №{{ $inquiry->booking->id }}</a>
                @else
                    Заявка №{{ $inquiry->booking->id }} (доступна ответственному сотруднику и администратору)
                @endcan
                @if($inquiry->booking->trashed()) <span class="text-muted">(удалена)</span>@endif
            @else
                <span class="text-muted">заявка удалена из системы</span>
            @endif
        </dd>
        <dt class="col-sm-4">Клиент</dt>
        <dd class="col-sm-8">{{ $client?->name ?? '—' }}</dd>
        <dt class="col-sm-4">Оформлено</dt>
        <dd class="col-sm-8">{{ $inquiry->converted_at?->format('d.m.Y H:i') ?? '—' }}@if($inquiry->convertedBy), {{ $inquiry->convertedBy->name }}@endif</dd>
        <dt class="col-sm-4">Ответственный</dt>
        <dd class="col-sm-8">{{ $inquiry->assignee?->name ?? '—' }}</dd>
    </dl>
</section>
@elseif($isClosed)
<section class="card-custom mb-4" aria-labelledby="inq-result-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-result-title">Результат: закрыто без бронирования</h2>
    </div>
    <dl class="row mx-0 mb-0">
        <dt class="col-sm-4">Закрыто</dt>
        <dd class="col-sm-8">{{ $inquiry->closed_at?->format('d.m.Y H:i') ?? '—' }}@if($inquiry->closedBy), {{ $inquiry->closedBy->name }}@endif</dd>
        <dt class="col-sm-4">Причина</dt>
        <dd class="col-sm-8">{{ $dash($inquiry->close_reason) }}</dd>
    </dl>
</section>
@endif

@if($canClose)
<section class="card-custom mb-4" aria-labelledby="inq-close-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="inq-close-title">Закрыть без бронирования</h2>
    </div>
    <p class="text-muted">Обращение и данные Tourvisor сохраняются, заявка не создаётся.</p>
    <form method="POST" action="{{ route('cabinet.manager.inquiries.close', $inquiry) }}" class="row g-3" data-submit-once>
        @csrf
        <div class="col-12 col-md-8">
            <label for="inq-close-reason" class="form-label">Причина (необязательно, видна только сотрудникам)</label>
            <input type="text" id="inq-close-reason" name="close_reason" value="{{ old('close_reason') }}" class="form-control @error('close_reason') is-invalid @enderror" maxlength="500">
            @error('close_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input @error('close_confirmed') is-invalid @enderror" type="checkbox" id="inq-close-confirmed" name="close_confirmed" value="1" required>
                <label class="form-check-label" for="inq-close-confirmed">Да, закрыть обращение без бронирования</label>
                @error('close_confirmed')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-outline-danger">Закрыть без бронирования</button>
        </div>
    </form>
</section>
@endif

<div class="mt-3">
    <a href="{{ route('cabinet.manager.inquiries') }}">← К списку обращений</a>
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
    // Подстановка справочной цены в поле — только по явному нажатию; проверку всё равно делает менеджер.
    document.querySelectorAll('[data-fill-reference]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.target);
            if (input) { input.value = button.dataset.fillReference; input.focus(); }
        });
    });
</script>
@endpush
