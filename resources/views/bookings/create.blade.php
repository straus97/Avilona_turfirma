@extends('cabinet.layouts.app')

@section('title', 'Новая заявка на тур')
@section('meta_description', 'Создание заявки на тур')

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
    $isStaffCreator = Auth::user()->hasAnyRole(['manager', 'admin']);
@endphp

<div class="page-header">
    <h1 class="page-title">Новая заявка на тур</h1>
    <p class="page-subtitle">
        @if($isStaffCreator)
            Заполните параметры поездки и данные клиента. Это заявка-обращение, а не бронирование в реальном времени.
        @else
            Опишите желаемую поездку — после отправки заявки ответственный сотрудник уточнит детали. Это заявка-обращение, а не бронирование в реальном времени.
        @endif
    </p>
</div>

<div class="booking-form">
    @if($tour)
        <div class="tc-notice mb-4">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <span>Заявка создаётся по каталожному туру: <strong>{{ $tour->title }}</strong></span>
        </div>
    @endif

    <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm" novalidate>
        @csrf

        @if($tour)
            <input type="hidden" name="tour_id" value="{{ $tour->id }}">
        @endif

        {{-- Клиент — только для менеджера и администратора --}}
        @if($isStaffCreator)
            <section class="card-custom" aria-labelledby="create-client-title">
                <div class="card-header-custom">
                    <h2 class="card-title-custom" id="create-client-title">
                        <i class="bi bi-person-circle" aria-hidden="true"></i> Клиент
                    </h2>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="isNewClient" name="is_new_client" value="1" {{ old('is_new_client') ? 'checked' : '' }}>
                    <label class="form-check-label" for="isNewClient">
                        <strong>Новый клиент</strong> — его ещё нет в базе
                    </label>
                </div>

                @error('client_email')
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $message }}
                    </div>
                @enderror

                <div id="existingClientBlock">
                    <label for="client_id" class="form-label">
                        Выберите клиента <span class="booking-req">*</span>
                    </label>
                    <select class="form-select @error('client_id') is-invalid @enderror" id="client_id" name="client_id">
                        <option value="">— Выберите клиента —</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }} ({{ $client->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('client_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div id="newClientBlock" style="display: none;">
                    <div class="mb-3">
                        <label for="client_name" class="form-label">
                            ФИО нового клиента <span class="booking-req">*</span>
                        </label>
                        <input type="text"
                               class="form-control @error('client_name') is-invalid @enderror"
                               id="client_name"
                               name="client_name"
                               value="{{ old('client_name') }}"
                               placeholder="Иванов Иван Иванович">
                        @error('client_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="client_email" class="form-label">
                            Email клиента <span class="booking-optional">— необязательно</span>
                        </label>
                        <input type="email"
                               class="form-control @error('client_email') is-invalid @enderror"
                               id="client_email"
                               name="client_email"
                               value="{{ old('client_email') }}"
                               placeholder="client@example.com">
                        @error('client_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            ФИО и email сохранятся. После регистрации клиента заявка привяжется к его аккаунту.
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Направление --}}
        <section class="card-custom" aria-labelledby="create-direction-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="create-direction-title">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Направление
                </h2>
            </div>

            <div class="booking-form-grid">
                <div class="mb-3">
                    <label for="departure_city" class="form-label">
                        Город вылета <span class="booking-req">*</span>
                    </label>
                    <input type="text"
                           class="form-control @error('departure_city') is-invalid @enderror"
                           id="departure_city"
                           name="departure_city"
                           value="{{ old('departure_city', $tour->departure_city ?? 'Санкт-Петербург') }}"
                           list="departureCitiesList"
                           required>
                    <datalist id="departureCitiesList">
                        @foreach($departureCities as $city)
                            <option value="{{ $city }}">
                        @endforeach
                    </datalist>
                    @error('departure_city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="destination_country" class="form-label">
                        Страна <span class="booking-req">*</span>
                    </label>
                    <input type="text"
                           class="form-control @error('destination_country') is-invalid @enderror"
                           id="destination_country"
                           name="destination_country"
                           value="{{ old('destination_country', $tour->destination_country ?? '') }}"
                           list="destinationCountriesList"
                           required>
                    <datalist id="destinationCountriesList">
                        @foreach($destinationCountries as $country)
                            <option value="{{ $country }}">
                        @endforeach
                    </datalist>
                    @error('destination_country')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="destination_city" class="form-label">
                        Курорт / город <span class="booking-optional">— необязательно</span>
                    </label>
                    <input type="text"
                           class="form-control @error('destination_city') is-invalid @enderror"
                           id="destination_city"
                           name="destination_city"
                           value="{{ old('destination_city', $tour->destination_city ?? '') }}"
                           list="destinationCitiesList"
                           placeholder="Сначала выберите страну">
                    <datalist id="destinationCitiesList">
                        <!-- Заполняется через JS по выбранной стране -->
                    </datalist>
                    @error('destination_city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Выберите из списка или введите свой вариант.</div>
                </div>
            </div>
        </section>

        {{-- Даты и длительность --}}
        <section class="card-custom" aria-labelledby="create-dates-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="create-dates-title">
                    <i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Даты и длительность
                </h2>
            </div>

            <div class="booking-form-grid">
                <div class="mb-3">
                    <label for="start_date" class="form-label">
                        Дата вылета (с) <span class="booking-req">*</span>
                    </label>
                    <input type="date"
                           class="form-control @error('start_date') is-invalid @enderror"
                           id="start_date"
                           name="start_date"
                           value="{{ old('start_date', $tour->start_date ?? '') }}"
                           min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                           required>
                    @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Начало диапазона желаемых дат.</div>
                </div>

                <div class="mb-3">
                    <label for="start_date_end" class="form-label">
                        Дата вылета (по) <span class="booking-optional">— необязательно</span>
                    </label>
                    <input type="date"
                           class="form-control @error('start_date_end') is-invalid @enderror"
                           id="start_date_end"
                           name="start_date_end"
                           value="{{ old('start_date_end') }}"
                           min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                    @error('start_date_end')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Конец диапазона, если даты гибкие.</div>
                </div>

                <div class="mb-3">
                    <label for="nights" class="form-label">
                        Ночей (от) <span class="booking-req">*</span>
                    </label>
                    <input type="number"
                           class="form-control @error('nights') is-invalid @enderror"
                           id="nights"
                           name="nights"
                           value="{{ old('nights', $tour->nights ?? 7) }}"
                           min="1"
                           max="30"
                           required>
                    @error('nights')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="nights_max" class="form-label">
                        Ночей (до) <span class="booking-optional">— необязательно</span>
                    </label>
                    <input type="number"
                           class="form-control @error('nights_max') is-invalid @enderror"
                           id="nights_max"
                           name="nights_max"
                           value="{{ old('nights_max') }}"
                           min="1"
                           max="30">
                    @error('nights_max')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Туристы --}}
        <section class="card-custom" aria-labelledby="create-tourists-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="create-tourists-title">
                    <i class="bi bi-people-fill" aria-hidden="true"></i> Туристы
                </h2>
            </div>

            <div class="booking-form-grid">
                <div class="mb-3">
                    <label for="adults" class="form-label">
                        Взрослых <span class="booking-req">*</span>
                    </label>
                    <input type="number"
                           class="form-control @error('adults') is-invalid @enderror"
                           id="adults"
                           name="adults"
                           value="{{ old('adults', 2) }}"
                           min="1"
                           max="10"
                           required>
                    @error('adults')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="children_count" class="form-label">
                        Детей
                    </label>
                    <select class="form-select @error('children') is-invalid @enderror" id="children_count" name="children">
                        @for($i = 0; $i <= 5; $i++)
                            <option value="{{ $i }}" {{ old('children', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                    </select>
                    @error('children')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div id="childrenAgesBlock" style="display: none;">
                <label class="form-label">Возраст детей на момент окончания поездки</label>
                <div id="childrenAgesContainer" class="booking-form-grid"></div>
            </div>
        </section>

        {{-- Дополнительно --}}
        <section class="card-custom" aria-labelledby="create-notes-title">
            <div class="card-header-custom">
                <h2 class="card-title-custom" id="create-notes-title">
                    <i class="bi bi-chat-left-text-fill" aria-hidden="true"></i> Пожелания и комментарии
                </h2>
            </div>

            <div class="mb-1">
                <label for="notes" class="form-label">
                    Дополнительная информация <span class="booking-optional">— необязательно</span>
                </label>
                <textarea class="form-control @error('notes') is-invalid @enderror"
                          id="notes"
                          name="notes"
                          rows="4"
                          placeholder="Пожелания по отелю, питанию, расположению, трансферу и другие важные детали">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </section>

        <div class="tc-notice mb-4">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <span>
                @if($isStaffCreator)
                    После создания заявка будет доступна для дальнейшей работы.
                @else
                    После отправки заявки сотрудник свяжется с вами для уточнения деталей.
                @endif
            </span>
        </div>

        <div class="booking-actions booking-actions--split">
            <a href="{{ route('cabinet.bookings') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Отмена
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Отправить заявку
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Переключение между существующим и новым клиентом
    const isNewClientCheckbox = document.getElementById('isNewClient');

    if (isNewClientCheckbox) {
        const existingClientBlock = document.getElementById('existingClientBlock');
        const newClientBlock = document.getElementById('newClientBlock');
        const clientIdSelect = document.getElementById('client_id');
        const clientNameInput = document.getElementById('client_name');
        const clientEmailInput = document.getElementById('client_email');

        // Синхронизация режима клиента. clearValues=false при первичной загрузке,
        // иначе значения old() затирались бы после ошибки валидации.
        function syncClientMode(clearValues) {
            if (isNewClientCheckbox.checked) {
                existingClientBlock.style.display = 'none';
                newClientBlock.style.display = 'block';
                clientIdSelect.removeAttribute('required');
                clientNameInput.setAttribute('required', 'required');

                if (clearValues) {
                    clientIdSelect.value = '';
                }
            } else {
                existingClientBlock.style.display = 'block';
                newClientBlock.style.display = 'none';
                clientIdSelect.setAttribute('required', 'required');
                clientNameInput.removeAttribute('required');

                if (clearValues) {
                    clientNameInput.value = '';
                    if (clientEmailInput) {
                        clientEmailInput.value = '';
                    }
                }
            }
        }

        isNewClientCheckbox.addEventListener('change', function() {
            syncClientMode(true);
        });

        // Первичная синхронизация выполняется всегда, в обоих режимах.
        syncClientMode(false);
    }

    // Управление возрастом детей
    function initChildrenAges(selectId, blockId, containerId) {
        const childrenCountSelect = document.getElementById(selectId);
        const childrenAgesBlock = document.getElementById(blockId);
        const childrenAgesContainer = document.getElementById(containerId);

        if (!childrenCountSelect || !childrenAgesBlock || !childrenAgesContainer) {
            return;
        }

        function updateChildrenAges() {
            const count = parseInt(childrenCountSelect.value);
            childrenAgesContainer.innerHTML = '';

            if (count > 0) {
                childrenAgesBlock.style.display = 'block';
                const oldAges = @json(old('children_ages', []));

                for (let i = 0; i < count; i++) {
                    const col = document.createElement('div');
                    col.className = 'mb-3';
                    const selectedAge = oldAges[i] || '';

                    let optionsHtml = '<option value="">Выберите возраст</option>';
                    for (let j = 0; j < 18; j++) {
                        const selected = selectedAge == j ? 'selected' : '';
                        optionsHtml += `<option value="${j}" ${selected}>${j} ${getYearWord(j)}</option>`;
                    }

                    col.innerHTML = `
                        <label class="form-label">Ребенок ${i + 1}</label>
                        <select class="form-select" name="children_ages[]" required>
                            ${optionsHtml}
                        </select>
                    `;
                    childrenAgesContainer.appendChild(col);
                }
            } else {
                childrenAgesBlock.style.display = 'none';
            }
        }

        childrenCountSelect.addEventListener('change', updateChildrenAges);

        if (parseInt(childrenCountSelect.value) > 0) {
            updateChildrenAges();
        }
    }

    initChildrenAges('children_count', 'childrenAgesBlock', 'childrenAgesContainer');

    function getYearWord(age) {
        const lastDigit = age % 10;
        const lastTwoDigits = age % 100;

        if (lastTwoDigits >= 11 && lastTwoDigits <= 19) {
            return 'лет';
        }
        if (lastDigit === 1) {
            return 'год';
        }
        if (lastDigit >= 2 && lastDigit <= 4) {
            return 'года';
        }
        return 'лет';
    }

    // Фильтрация курортов по выбранной стране
    function initDestinationFilter(countryId, cityId, listId) {
        const destinationCountryInput = document.getElementById(countryId);
        const destinationCityInput = document.getElementById(cityId);
        const destinationCitiesList = document.getElementById(listId);

        if (!destinationCountryInput || !destinationCityInput || !destinationCitiesList) {
            return;
        }

        destinationCountryInput.addEventListener('change', function() {
            const country = this.value.trim();

            if (!country) {
                destinationCitiesList.innerHTML = '';
                destinationCityInput.placeholder = 'Сначала выберите страну';
                return;
            }

            fetch(`{{ route('api.destination-cities') }}?country=${encodeURIComponent(country)}`)
                .then(response => response.json())
                .then(cities => {
                    destinationCitiesList.innerHTML = '';

                    if (cities.length > 0) {
                        cities.forEach(city => {
                            const option = document.createElement('option');
                            option.value = city;
                            destinationCitiesList.appendChild(option);
                        });
                        destinationCityInput.placeholder = 'Выберите курорт или введите свой';
                    } else {
                        destinationCityInput.placeholder = 'Введите название курорта';
                    }
                })
                .catch(error => {
                    console.error('Ошибка загрузки курортов:', error);
                    destinationCityInput.placeholder = 'Введите название курорта';
                });
        });

        if (destinationCountryInput.value.trim()) {
            destinationCountryInput.dispatchEvent(new Event('change'));
        }
    }

    initDestinationFilter('destination_country', 'destination_city', 'destinationCitiesList');
});
</script>
@endpush
