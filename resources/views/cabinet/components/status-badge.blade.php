@props(['status'])

@php
    $statusConfig = [
        'new' => ['text' => 'Новая', 'class' => 'status-new', 'icon' => 'bi-file-earmark-plus'],
        'progress' => ['text' => 'В работе', 'class' => 'status-progress', 'icon' => 'bi-hourglass-split'],
        'confirmed' => ['text' => 'Подтверждена', 'class' => 'status-confirmed', 'icon' => 'bi-check-circle'],
        'cancelled' => ['text' => 'Отменена', 'class' => 'status-cancelled', 'icon' => 'bi-x-circle'],
        'completed' => ['text' => 'Завершена', 'class' => 'status-completed', 'icon' => 'bi-check-all'],
    ];
    
    $config = $statusConfig[$status] ?? ['text' => $status, 'class' => 'status-new', 'icon' => 'bi-question-circle'];
@endphp

<span class="status-badge {{ $config['class'] }}">
    <i class="bi {{ $config['icon'] }}"></i>
    {{ $config['text'] }}
</span>
