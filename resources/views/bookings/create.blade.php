@extends('layouts.main')

@section('title', 'Создать заявку - Авилона')
@section('meta_description', 'Создание заявки на тур')

@section('content')
<main>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-gradient-primary text-white py-3">
                        <h3 class="mb-0">
                            <i class="bi bi-plus-circle-fill"></i> Создать заявку на тур
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        @if($tour)
                            <div class="alert alert-info border-left-info">
                                <i class="bi bi-info-circle-fill"></i>
                                Вы создаете заявку на тур: <strong>{{ $tour->title }}</strong>
                            </div>
                        @endif

                        <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm">
                            @csrf

                            @if($tour)
                                <input type="hidden" name="tour_id" value="{{ $tour->id }}">
                            @endif

                            <!-- Информация о клиенте (только для менеджеров и админов) -->
                            @if(Auth::user()->hasAnyRole(['manager', 'admin']))
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-person-circle"></i> Информация о клиенте</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="isNewClient" name="is_new_client" value="1" {{ old('is_new_client') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="isNewClient">
                                            <strong>Новый клиент</strong> (клиента нет в базе)
                                        </label>
                                    </div>
                                    
                                    @error('client_email')
                                        <div class="alert alert-danger">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    <div id="existingClientBlock">
                                        <label for="client_id" class="form-label">
                                            Выберите клиента <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select @error('client_id') is-invalid @enderror" 
                                                id="client_id" 
                                                name="client_id">
                                            <option value="">-- Выберите клиента --</option>
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
                                                ФИО нового клиента <span class="text-danger">*</span>
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
                                                Email клиента (опционально)
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
                                            <small class="form-text text-muted">
                                                ФИО и email будут сохранены. После регистрации клиента в системе, заявка автоматически привяжется к его аккаунту
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Направление -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-geo-alt-fill"></i> Направление</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="departure_city" class="form-label">
                                                Город вылета <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('departure_city') is-invalid @enderror" 
                                                   id="departure_city" 
                                                   name="departure_city" 
                                                   value="{{ old('departure_city', $tour->departure_city ?? 'Москва') }}" 
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

                                        <div class="col-md-4">
                                            <label for="destination_country" class="form-label">
                                                Страна <span class="text-danger">*</span>
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

                                        <div class="col-md-4">
                                            <label for="destination_city" class="form-label">
                                                Курорт/Город
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('destination_city') is-invalid @enderror" 
                                                   id="destination_city" 
                                                   name="destination_city" 
                                                   value="{{ old('destination_city', $tour->destination_city ?? '') }}"
                                                   list="destinationCitiesList">
                                            <datalist id="destinationCitiesList">
                                                @foreach($destinationCities as $city)
                                                    <option value="{{ $city }}">
                                                @endforeach
                                            </datalist>
                                            @error('destination_city')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Даты и ночи -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-calendar-event-fill"></i> Даты поездки</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">
                                                Дата вылета <span class="text-danger">*</span>
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
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="nights" class="form-label">
                                                Количество ночей <span class="text-danger">*</span>
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
                                    </div>
                                </div>
                            </div>

                            <!-- Туристы -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Количество туристов</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="adults" class="form-label">
                                                Взрослых <span class="text-danger">*</span>
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

                                        <div class="col-md-6">
                                            <label for="children_count" class="form-label">
                                                Детей
                                            </label>
                                            <select class="form-select @error('children') is-invalid @enderror" 
                                                    id="children_count" 
                                                    name="children">
                                                @for($i = 0; $i <= 5; $i++)
                                                    <option value="{{ $i }}" {{ old('children', 0) == $i ? 'selected' : '' }}>
                                                        {{ $i }}
                                                    </option>
                                                @endfor
                                            </select>
                                            @error('children')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Возраст детей -->
                                    <div id="childrenAgesBlock" style="display: none;">
                                        <label class="form-label">Возраст детей на момент окончания поездки</label>
                                        <div id="childrenAgesContainer" class="row"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Дополнительные пожелания -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-chat-left-text-fill"></i> Дополнительная информация</h5>
                                </div>
                                <div class="card-body">
                                    <label for="notes" class="form-label">
                                        Пожелания и комментарии
                                    </label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" 
                                              id="notes" 
                                              name="notes" 
                                              rows="4" 
                                              placeholder="Укажите ваши пожелания по отелю, питанию, расположению, трансферу и другие важные детали...">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Информация -->
                            <div class="alert alert-info border-left-info">
                                <i class="bi bi-info-circle-fill"></i>
                                <strong>Обратите внимание:</strong> После создания заявки наш менеджер свяжется с вами в течение 24 часов для уточнения деталей и подтверждения бронирования.
                            </div>

                            <!-- Кнопки -->
                            <div class="d-flex justify-content-between mt-4">
                                <a href="{{ route('bookings.index') }}" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-arrow-left"></i> Отмена
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle-fill"></i> Создать заявку
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('styles')
<style>
.form-label .text-danger {
    font-size: 1.2em;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.border-left-info {
    border-left: 4px solid #36b9cc;
}

.card {
    border-radius: 10px;
    border: none;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
}

.shadow-lg {
    box-shadow: 0 1rem 3rem rgba(0,0,0,.175)!important;
}

.child-age-input {
    margin-bottom: 10px;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing form scripts...');
    
    // Переключение между существующим и новым клиентом
    const isNewClientCheckbox = document.getElementById('isNewClient');
    console.log('isNewClientCheckbox found:', isNewClientCheckbox);
    
    if (isNewClientCheckbox) {
        const existingClientBlock = document.getElementById('existingClientBlock');
        const newClientBlock = document.getElementById('newClientBlock');
        const clientIdSelect = document.getElementById('client_id');
        const clientNameInput = document.getElementById('client_name');
        const clientEmailInput = document.getElementById('client_email');
        
        console.log('All elements found:', {
            existingClientBlock,
            newClientBlock,
            clientIdSelect,
            clientNameInput,
            clientEmailInput
        });

        // Обработчик изменения чекбокса
        isNewClientCheckbox.addEventListener('change', function() {
            console.log('Checkbox changed:', this.checked);
            if (this.checked) {
                existingClientBlock.style.display = 'none';
                newClientBlock.style.display = 'block';
                clientIdSelect.removeAttribute('required');
                clientIdSelect.value = '';
                clientNameInput.setAttribute('required', 'required');
            } else {
                existingClientBlock.style.display = 'block';
                newClientBlock.style.display = 'none';
                clientIdSelect.setAttribute('required', 'required');
                clientNameInput.removeAttribute('required');
                clientNameInput.value = '';
                if (clientEmailInput) {
                    clientEmailInput.value = '';
                }
            }
        });
        
        // Триггерим событие при загрузке, если чекбокс был отмечен (после ошибки валидации)
        if (isNewClientCheckbox.checked) {
            isNewClientCheckbox.dispatchEvent(new Event('change'));
        }
        
        console.log('Event listener attached successfully');
    } else {
        console.log('isNewClientCheckbox not found - user is probably a tourist');
    }

    // Управление возрастом детей
    const childrenCountSelect = document.getElementById('children_count');
    const childrenAgesBlock = document.getElementById('childrenAgesBlock');
    const childrenAgesContainer = document.getElementById('childrenAgesContainer');

    childrenCountSelect.addEventListener('change', function() {
        const count = parseInt(this.value);
        childrenAgesContainer.innerHTML = '';
        
        if (count > 0) {
            childrenAgesBlock.style.display = 'block';
            for (let i = 0; i < count; i++) {
                const col = document.createElement('div');
                col.className = 'col-md-4 mb-3';
                col.innerHTML = `
                    <label class="form-label">Ребенок ${i + 1}</label>
                    <select class="form-select" name="children_ages[]" required>
                        <option value="">Выберите возраст</option>
                        ${Array.from({length: 18}, (_, j) => `<option value="${j}">${j} ${getYearWord(j)}</option>`).join('')}
                    </select>
                `;
                childrenAgesContainer.appendChild(col);
            }
        } else {
            childrenAgesBlock.style.display = 'none';
        }
    });

    // Триггерим событие при загрузке, если уже выбрано количество детей
    if (childrenCountSelect.value > 0) {
        childrenCountSelect.dispatchEvent(new Event('change'));
    }

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
});
</script>
@endpush
