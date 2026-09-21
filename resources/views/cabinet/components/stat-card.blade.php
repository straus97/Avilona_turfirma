@php
    $color = $color ?? 'primary';
    $trend = $trend ?? null;
    $valueClass = $valueClass ?? '';
@endphp

<div class="card-custom stat-card" style="border-left: 4px solid var(--{{ $color }}-color);">
    <div class="stat-card__row d-flex align-items-center justify-content-between gap-3">
        <div class="stat-card__body">
            <div class="stat-card__label">
                {{ $title }}
            </div>
            <div class="stat-card__value {{ $valueClass }}" style="font-weight: 700; color: var(--cabinet-heading); line-height: 1;">
                {{ $value }}
            </div>
            @if($trend)
                <div style="font-size: 0.875rem; margin-top: 0.5rem;" class="text-{{ $trend > 0 ? 'success' : 'danger' }}">
                    <i class="bi bi-arrow-{{ $trend > 0 ? 'up' : 'down' }}" aria-hidden="true"></i>
                    {{ abs($trend) }}% за месяц
                </div>
            @endif
        </div>
        <div class="stat-card__icon" style="background: rgba(var(--bs-{{ $color }}-rgb), 0.12);">
            <i class="bi {{ $icon }}" style="color: var(--{{ $color }}-color);" aria-hidden="true"></i>
        </div>
    </div>
</div>
