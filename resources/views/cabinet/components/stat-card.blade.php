@php
    $color = $color ?? 'primary';
    $trend = $trend ?? null;
    $valueClass = $valueClass ?? '';
@endphp

<div class="card-custom stat-card" style="border-left: 4px solid var(--{{ $color }}-color);">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <div style="flex: 1; min-width: 0;">
            <div style="font-size: 0.75rem; color: var(--cabinet-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">
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
        <div style="width: 60px; height: 60px; border-radius: var(--cabinet-radius-md); background: rgba(var(--bs-{{ $color }}-rgb), 0.12); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="bi {{ $icon }}" style="font-size: 1.75rem; color: var(--{{ $color }}-color);" aria-hidden="true"></i>
        </div>
    </div>
</div>
