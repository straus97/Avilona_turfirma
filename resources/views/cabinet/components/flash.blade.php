{{--
    Общая область флэш-сообщений и ошибок валидации для оболочки кабинета.

    Показывает существующие ключи, которыми уже пользуются контроллеры
    кабинета и middleware, без изменения самих ключей:
      - session('success')  — успешные действия (Cabinet/Booking-контроллеры)
      - session('status')   — успешные действия профиля/настроек/документов
      - session('error')    — ошибки уровня запроса
      - session('warning')  — CheckPasswordChangeRequired / вход по временному паролю
    Плюс сводка ошибок валидации ($errors).

    Доступность: контейнер — live-region (aria-live="polite"); сводка ошибок
    валидации — role="alert". Тип сообщения передаётся не только цветом, но и
    иконкой и текстовой меткой для скринридера.
--}}
@php
    $cabinetFlashMessages = collect([
        ['value' => session('success'), 'variant' => 'success', 'icon' => 'bi-check-circle-fill',        'label' => 'Успешно'],
        ['value' => session('status'),  'variant' => 'success', 'icon' => 'bi-check-circle-fill',        'label' => 'Готово'],
        ['value' => session('warning'), 'variant' => 'warning', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'Внимание'],
        ['value' => session('error'),   'variant' => 'danger',  'icon' => 'bi-exclamation-octagon-fill',  'label' => 'Ошибка'],
    ])->filter(fn ($message) => is_string($message['value']) && trim($message['value']) !== '')->values();

    // $errors делится middleware ShareErrorsFromSession в рамках HTTP-запроса;
    // при прямом рендере вью (например, в юнит-проверке layout) он может быть
    // не определён — тогда сводки ошибок валидации просто нет.
    $cabinetValidationErrors = (isset($errors) && $errors->any()) ? $errors->all() : [];
@endphp

@if($cabinetFlashMessages->isNotEmpty() || ! empty($cabinetValidationErrors))
    <div class="cabinet-flash" aria-live="polite">
        @foreach($cabinetFlashMessages as $message)
            <div class="alert cabinet-flash-item cabinet-flash-item--{{ $message['variant'] }} alert-dismissible" role="alert">
                <i class="bi {{ $message['icon'] }}" aria-hidden="true"></i>
                <div>
                    <span class="visually-hidden">{{ $message['label'] }}: </span>{{ $message['value'] }}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
            </div>
        @endforeach

        @if(! empty($cabinetValidationErrors))
            <div class="cabinet-flash-item cabinet-flash-item--danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>
                    <div class="cabinet-flash-title">Проверьте правильность заполнения формы</div>
                    <ul class="cabinet-flash-list">
                        @foreach($cabinetValidationErrors as $validationError)
                            <li>{{ $validationError }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endif
