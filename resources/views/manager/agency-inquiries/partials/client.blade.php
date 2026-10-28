{{-- AOA-04-I4: аккаунт клиента обращения. Привязка — только явным действием; совпадение контактов аккаунт не выбирает. --}}
<section class="card-custom mb-4" aria-labelledby="agency-inquiry-client-title">
    <div class="card-header-custom">
        <h2 class="card-title-custom" id="agency-inquiry-client-title">Клиент (аккаунт в Avilona)</h2>
    </div>

    @error('client')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('user_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @error('expected_client_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    @if($clientLinked && $client)
        <div class="alert alert-success" role="status" id="agency-inquiry-client-linked">
            Привязан клиент: <strong class="text-break">{{ $client->name }}</strong>
            @if($isAdminActor)
                · <a href="{{ route('cabinet.admin.user-show', $client->id) }}">карточка клиента</a>
                @if($clientLinkedBy || $clientLinkedAt)
                    <span class="d-block small">Привязал: {{ $clientLinkedBy ?? 'сотрудник не определён' }}@if($clientLinkedAt) · {{ $clientLinkedAt->format('d.m.Y H:i') }}@endif</span>
                @endif
            @else
                <span class="d-block small">Клиент доступен вам в разделе «<a href="{{ route('cabinet.manager.clients') }}">Мои клиенты</a>», пока вы ведёте это обращение в работе. После закрытия обращения этот доступ прекращается. Доступ к его заявкам и переписке это не открывает.</span>
            @endif
        </div>

        @if($canAdministerClient)
            <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.client.unlink', $inquiry) }}" class="row g-2" data-submit-once novalidate>
                @csrf
                <input type="hidden" name="expected_client_id" value="{{ $inquiry->client_user_id }}">
                <div class="col-12">
                    <label for="ai-unlink-reason" class="form-label">Причина, если клиент привязан по ошибке</label>
                    <textarea id="ai-unlink-reason" name="reason" rows="2" required minlength="{{ \App\Services\Inquiries\InquiryClientAssociation::REASON_MIN_LENGTH }}" maxlength="{{ \App\Services\Inquiries\InquiryClientAssociation::REASON_MAX_LENGTH }}" class="form-control @error('reason') is-invalid @enderror" aria-describedby="ai-unlink-reason-help">{{ old('reason') }}</textarea>
                    <div id="ai-unlink-reason-help" class="form-text">От {{ \App\Services\Inquiries\InquiryClientAssociation::REASON_MIN_LENGTH }} до {{ \App\Services\Inquiries\InquiryClientAssociation::REASON_MAX_LENGTH }} символов; сохраняется в журнале. Не указывайте пароли, паспортные данные, телефоны и email клиентов.</div>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Снять привязку клиента</button>
                    <span class="text-muted small d-block mt-1">Аккаунт клиента, контакт и черновик не удаляются. Новую привязку нужно выбрать отдельно.</span>
                </div>
            </form>
        @endif
    @elseif($clientLinked)
        <div class="alert alert-secondary" role="status" id="agency-inquiry-client-archived">
            К обращению привязан аккаунт клиента. Профиль клиента вам недоступен: обращение закрыто, а другой рабочей связи с этим клиентом у вас нет.
        </div>
    @else
        <p class="text-muted" id="agency-inquiry-client-empty">Аккаунт клиента не привязан. Контакт выше — это не аккаунт; совпадение имени, телефона или email аккаунт не выбирает.</p>

        @if($canLinkClient)
            <h3 class="h6">{{ $isAdminActor ? 'Найти клиента в справочнике' : 'Выбрать из ваших клиентов' }}</h3>
            <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.client.search', $inquiry) }}" class="row g-2 align-items-end" data-submit-once novalidate>
                @csrf
                <div class="col-12 col-md-6">
                    <label for="ai-client-q" class="form-label">{{ $isAdminActor ? 'Имя, email или телефон' : 'Имя клиента, email или телефон' }}</label>
                    <input type="search" id="ai-client-q" name="client_q" value="{{ $clientSearchTerm }}" class="form-control @error('client_q') is-invalid @enderror" minlength="2" maxlength="100" autocomplete="off" aria-describedby="ai-client-q-help">
                    <div id="ai-client-q-help" class="form-text">{{ $isAdminActor ? 'Поиск по справочнику активных туристов.' : 'Поиск только среди клиентов, с которыми вы работаете сейчас.' }}</div>
                    @error('client_q')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-outline-primary">Найти</button>
                </div>
            </form>

            @if($clientSearchTerm !== '')
                <div class="mt-3" id="agency-inquiry-client-results">
                    @if($clientSearchResults->isEmpty())
                        <p class="text-muted mb-0">{{ $isAdminActor ? 'Активных туристов по этому запросу не найдено.' : 'Среди ваших клиентов совпадений нет. Если клиент уже обращался в агентство, отправьте запрос на проверку ниже.' }}</p>
                    @else
                        <ul class="list-group mb-2">
                            @foreach($clientSearchResults as $candidate)
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="text-break">
                                        <strong>{{ $candidate->name }}</strong>
                                        <div class="small text-muted">{{ $candidate->hasTechnicalEmail() ? 'email не указан' : $candidate->email }}@if($candidate->phone) · {{ $candidate->phone }}@endif</div>
                                    </div>
                                    <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.client.select', $inquiry) }}" data-submit-once>
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $candidate->id }}">
                                        <input type="hidden" name="expected_client_id" value="">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Выбрать клиента {{ $candidate->name }}">Выбрать</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        @endif

        @if(! $isAdminActor && ($canRequestClient || $myRequest))
            <hr>
            <h3 class="h6">Клиента нет в списке? Отправьте запрос на проверку</h3>
            <p class="text-muted small">Администратор проверит клиента и либо откроет вам существующий аккаунт, либо создаст новый. Ответ на запрос не зависит от того, есть ли клиент в системе.</p>

            @if($myRequest)
                <div class="alert {{ $myRequest->status === 'rejected' ? 'alert-warning' : 'alert-info' }}" role="status" id="agency-inquiry-request-state">
                    <strong>{{ $myRequest->managerStatusLabel() }}</strong> · {{ $myRequest->created_at?->format('d.m.Y H:i') }}
                    @if($myRequest->isPending())
                        <span class="d-block small">Администратор ещё не принял решение. Повторно отправлять запрос не нужно.</span>
                    @elseif($myRequest->status === 'rejected' && $myRequest->resolution_note)
                        <span class="d-block small">Комментарий администратора: {{ $myRequest->resolution_note }}</span>
                    @endif
                </div>
            @endif

            @if($canRequestClient && (! $myRequest || ! $myRequest->isPending()))
                <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.client.verification', $inquiry) }}" class="row g-3" data-submit-once novalidate>
                    @csrf
                    <div class="col-12 col-md-4">
                        <label for="ai-req-name" class="form-label">Имя клиента <span class="booking-req">*</span></label>
                        <input type="text" id="ai-req-name" name="client_name" value="{{ old('client_name', $contact['name']) }}" class="form-control @error('client_name') is-invalid @enderror" maxlength="255" required autocomplete="off">
                        @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="ai-req-email" class="form-label">Email (необязательно)</label>
                        <input type="email" id="ai-req-email" name="client_email" value="{{ old('client_email') }}" class="form-control @error('client_email') is-invalid @enderror" maxlength="255" autocomplete="off" aria-describedby="ai-req-email-help">
                        <div id="ai-req-email-help" class="form-text">Email не сохраняется в контакте обращения — только в запросе администратору.</div>
                        @error('client_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="ai-req-phone" class="form-label">Телефон (необязательно)</label>
                        <input type="text" id="ai-req-phone" name="client_phone" value="{{ old('client_phone', $contact['phone']) }}" class="form-control @error('client_phone') is-invalid @enderror" maxlength="20" inputmode="tel" autocomplete="off">
                        @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Отправить запрос на проверку клиента</button>
                        <p class="text-muted small mt-2 mb-0">Пароль не создаётся и никому не отправляется. Заявка при этом не создаётся.</p>
                    </div>
                </form>
            @endif
        @endif

        @if($isAdminActor)
            @if($pendingRequests->isNotEmpty())
                <hr>
                <h3 class="h6">Запросы менеджера на проверку клиента</h3>
                <ul class="list-group mb-3" id="agency-inquiry-pending-requests">
                    @foreach($pendingRequests as $pending)
                        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="text-break">{{ $pending->requester?->name ?? 'менеджер не определён' }} · {{ $pending->created_at?->format('d.m.Y H:i') }}</span>
                            <a href="{{ route('cabinet.admin.client-requests.show', $pending) }}" class="btn btn-sm btn-outline-primary">Рассмотреть запрос</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($canAdministerClient)
                <hr>
                <h3 class="h6">Нового клиента создавать, только если аккаунта точно нет</h3>
                <form method="POST" action="{{ route('cabinet.manager.agency-inquiries.client.create', $inquiry) }}" class="row g-3" data-submit-once novalidate>
                    @csrf
                    <input type="hidden" name="expected_client_id" value="">
                    <div class="col-12 col-md-4">
                        <label for="ai-new-name" class="form-label">Имя клиента <span class="booking-req">*</span></label>
                        <input type="text" id="ai-new-name" name="client_name" value="{{ old('client_name', $contact['name']) }}" class="form-control @error('client_name') is-invalid @enderror" maxlength="255" required autocomplete="off">
                        @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="ai-new-email" class="form-label">Email (необязательно)</label>
                        <input type="email" id="ai-new-email" name="client_email" value="{{ old('client_email') }}" class="form-control @error('client_email') is-invalid @enderror" maxlength="255" autocomplete="off">
                        @error('client_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="ai-new-phone" class="form-label">Телефон (необязательно)</label>
                        <input type="text" id="ai-new-phone" name="client_phone" value="{{ old('client_phone', $contact['phone']) }}" class="form-control @error('client_phone') is-invalid @enderror" maxlength="20" inputmode="tel" autocomplete="off">
                        @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Создать клиента и привязать</button>
                        <p class="text-muted small mt-2 mb-0">Пароль не создаётся и не показывается. Email считается неподтверждённым. Если такой email уже есть в системе, аккаунт не будет изменён — найдите его в справочнике выше.</p>
                    </div>
                </form>
            @endif
        @endif
    @endif
</section>
