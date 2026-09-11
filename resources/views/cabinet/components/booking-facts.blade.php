@php
    /**
     * Переиспользуемый блок «ключ — значение» для shared-страниц заявки
     * (bookings/show, bookings/edit). Ничего не решает про авторизацию —
     * вызывающий код сам формирует список фактов для текущей роли.
     *
     * Ожидает $facts — список массивов:
     *   [
     *     'label'   => 'Город вылета',   // подпись (обязательно)
     *     'value'   => 'Санкт-Петербург', // значение; null/'' → строка не выводится
     *     'wide'    => false,             // на всю ширину сетки
     *     'variant' => null,              // null | 'muted' | 'price'
     *   ]
     *
     * Отсутствующие данные не печатаются как «null» — строка просто пропускается,
     * а вызывающий шаблон при необходимости показывает явное «Не указано».
     */
    $bookingFacts = collect($facts ?? [])
        ->filter(fn ($fact) => isset($fact['value']) && trim((string) $fact['value']) !== '')
        ->values();
@endphp

@if($bookingFacts->isNotEmpty())
    <dl class="booking-facts">
        @foreach($bookingFacts as $fact)
            <div class="booking-fact @if(!empty($fact['wide'])) booking-fact--wide @endif">
                <dt class="booking-fact__label">{{ $fact['label'] }}</dt>
                <dd class="booking-fact__value @if(!empty($fact['variant'])) booking-fact__value--{{ $fact['variant'] }} @endif">{{ $fact['value'] }}</dd>
            </div>
        @endforeach
    </dl>
@endif
