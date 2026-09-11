@php
    /**
     * Единый источник presentation-формулировок статуса заявки для всех
     * ролей кабинета (E3-A3). Текст ярлыка берётся напрямую из доменного
     * контракта модели — App\Models\Booking::availableStatuses() — поэтому
     * канонический ярлык статуса `progress` («В обработке», та же
     * формулировка в письмах BookingStatusChanged и на страницах
     * менеджера/админа) не дублируется здесь. Компонент хранит только
     * визуальные метаданные (CSS-класс и иконку). Хранимые значения
     * статусов и Booking::transitionMap() не затрагиваются.
     */
    $statusLabels = \App\Models\Booking::availableStatuses();

    $statusMeta = [
        'new' => ['class' => 'status-new', 'icon' => 'bi-file-earmark-plus'],
        'progress' => ['class' => 'status-progress', 'icon' => 'bi-hourglass-split'],
        'confirmed' => ['class' => 'status-confirmed', 'icon' => 'bi-check-circle'],
        'cancelled' => ['class' => 'status-cancelled', 'icon' => 'bi-x-circle'],
        'completed' => ['class' => 'status-completed', 'icon' => 'bi-check-all'],
    ];

    $meta = $statusMeta[$status] ?? ['class' => 'status-new', 'icon' => 'bi-question-circle'];
    $statusText = $statusLabels[$status] ?? $status;
@endphp

<span class="status-badge {{ $meta['class'] }}">
    <i class="bi {{ $meta['icon'] }}" aria-hidden="true"></i>
    {{ $statusText }}
</span>
