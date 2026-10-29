{{-- AOA-04-I4: непроверенный контакт клиента. Это не аккаунт и не проверенный турист: аккаунт привязывается отдельно (раздел «Клиент»). --}}
@php
    $contactEmpty = $contact['name'] === null && $contact['phone'] === null && $contact['messenger'] === null;
    $contactLabels = ['contact_name' => 'Имя', 'contact_phone' => 'Телефон', 'contact_messenger' => 'Контакт в мессенджере'];
@endphp

@if($contactConflict)
<section class="alert alert-warning" role="region" aria-labelledby="agency-inquiry-contact-conflict-title">
    <h2 class="h6" id="agency-inquiry-contact-conflict-title">Ваши несохранённые значения контакта</h2>
    @if($contactConflict === [])
        <p class="mb-0">В отправленной форме не было заполненных полей.</p>
    @else
        <p class="small">Эти значения не записаны. Сверьте их с актуальным контактом ниже и при необходимости внесите правки заново.</p>
        <dl class="row mb-0 small">
            @foreach($contactConflict as $field => $value)
                @if(isset($contactLabels[$field]))
                    <dt class="col-sm-4">{{ $contactLabels[$field] }}</dt><dd class="col-sm-8 text-break">{{ $value }}</dd>
                @endif
            @endforeach
        </dl>
    @endif
</section>
@endif

<section class="card-custom mb-4" aria-labelledby="agency-inquiry-contact-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-contact-title">Контакт клиента</h2>
    </div>
    <p class="text-muted small">Как связаться с клиентом по этому обращению. Данные не проверены и аккаунтом клиента не являются. Все поля необязательны. Паспортные данные, email, дату рождения и адрес сюда не записывайте.</p>

    @if($contactEmpty)
        <p class="text-muted" id="agency-inquiry-contact-empty">Контакт не указан.</p>
    @endif

    <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.contact.update', $inquiry) }}" class="row g-3" data-submit-once novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" value="{{ $contact['revision'] }}">
        <fieldset class="col-12 border-0 p-0 m-0" @disabled(! $canEditContact)>
            <legend class="visually-hidden">Контакт клиента</legend>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="ai-contact-name" class="form-label">Имя клиента</label>
                    <input type="text" id="ai-contact-name" name="contact_name" value="{{ old('contact_name', $contact['name']) }}" class="form-control @error('contact_name') is-invalid @enderror" maxlength="{{ \App\Services\Inquiries\InquiryContactData::NAME_MAX_LENGTH }}" autocomplete="off">
                    @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="ai-contact-phone" class="form-label">Телефон для связи</label>
                    <input type="text" id="ai-contact-phone" name="contact_phone" value="{{ old('contact_phone', $contact['phone']) }}" class="form-control @error('contact_phone') is-invalid @enderror" maxlength="{{ \App\Services\Inquiries\InquiryContactData::PHONE_MAX_LENGTH }}" inputmode="tel" autocomplete="off" aria-describedby="ai-contact-phone-help">
                    <div id="ai-contact-phone-help" class="form-text">Например: +7 900 123-45-67.</div>
                    @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="ai-contact-messenger" class="form-label">Контакт в мессенджере</label>
                    <input type="text" id="ai-contact-messenger" name="contact_messenger" value="{{ old('contact_messenger', $contact['messenger']) }}" class="form-control @error('contact_messenger') is-invalid @enderror" maxlength="{{ \App\Services\Inquiries\InquiryContactData::MESSENGER_MAX_LENGTH }}" autocomplete="off" aria-describedby="ai-contact-messenger-help">
                    <div id="ai-contact-messenger-help" class="form-text">Например: Telegram @имя.</div>
                    @error('contact_messenger')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </fieldset>
        <div class="col-12">
            @error('contact')<div class="text-danger small mb-2" role="alert">{{ $message }}</div>@enderror
            @error('revision')<div class="text-danger small mb-2" role="alert">{{ $message }}</div>@enderror
            @if($canEditContact)
                <button type="submit" class="btn btn-primary">Сохранить контакт</button>
            @else
                <p class="text-muted mb-0">Обращение закрыто или оформлено в заявку — редактирование недоступно.</p>
            @endif
        </div>
    </form>

    @if($canEditContact && ! $contactEmpty)
        <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.contact.clear', $inquiry) }}" class="mt-2" data-submit-once>
            @csrf
            @method('DELETE')
            <input type="hidden" name="revision" value="{{ $contact['revision'] }}">
            <button type="submit" class="btn btn-outline-danger btn-sm">Очистить контакт</button>
        </form>
    @endif
</section>
