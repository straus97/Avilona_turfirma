@extends('cabinet.layouts.app')

@section('title', 'Новое обращение клиента')

@section('sidebar')
    @include(auth()->user()->hasRole('admin') ? 'cabinet.components.sidebar.admin' : 'cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Новое обращение клиента</h1>
    <p class="page-subtitle">Запишите обращение, которое пришло к вам по телефону или в личные сообщения. @if($chooseAssignee)Вы выбираете сотрудника, который будет за него отвечать; создателем останетесь вы.@else Обращение закрепляется за вами автоматически.@endif Бронирование при этом не создаётся, аккаунт туриста не нужен — данные поездки можно заполнить и дополнить позже.</p>
</div>

<section class="card-custom mb-4" aria-labelledby="agency-inquiry-create-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-create-title">Как клиент связался с агентством?</h2>
    </div>

    <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.store') }}" data-submit-once novalidate>
        @csrf
        <input type="hidden" name="creation_token" value="{{ old('creation_token', $creationToken) }}">

        <fieldset class="mb-3">
            <legend class="visually-hidden">Канал обращения</legend>
            @foreach(\App\Models\Inquiry::contactChannelLabels() as $value => $label)
                <div class="form-check mb-2">
                    <input class="form-check-input @error('contact_channel') is-invalid @enderror" type="radio" name="contact_channel"
                           id="agency-inquiry-channel-{{ $value }}" value="{{ $value }}"
                           @checked(old('contact_channel') === $value) required>
                    <label class="form-check-label" for="agency-inquiry-channel-{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
            @error('contact_channel')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('request')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <p class="text-muted small mt-2 mb-0">Содержимое разговора и переписки здесь не сохраняется, контакты клиента не запрашиваются.</p>
        </fieldset>

        @if($chooseAssignee)
        <div class="mb-3">
            <label for="agency-inquiry-assignee" class="form-label">Ответственный сотрудник</label>
            <select id="agency-inquiry-assignee" name="assignee_id" class="form-select @error('assignee_id') is-invalid @enderror" required aria-describedby="agency-inquiry-assignee-help">
                <option value="">— Выберите сотрудника —</option>
                @foreach($eligibleStaff as $employee)
                    <option value="{{ $employee['id'] }}" @selected((int) old('assignee_id', $defaultAssigneeId) === $employee['id'])>{{ $employee['name'] }} ({{ $employee['role'] }})</option>
                @endforeach
            </select>
            <div id="agency-inquiry-assignee-help" class="form-text">Менеджер или администратор, который будет вести обращение. Позже ответственного можно передать другому сотруднику.</div>
            @error('assignee_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        @endif

        <button type="submit" class="btn btn-primary">Создать обращение</button>
        <a href="{{ route('cabinet.manager.agency-inquiries') }}" class="btn btn-link">Отмена</a>
    </form>
</section>
@endsection

@push('scripts')
<script>
    // Защита от двойной отправки: после первой отправки кнопка блокируется
    // (сервер дополнительно не создаёт дубликат при повторе той же формы).
    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
        });
    });
</script>
@endpush
