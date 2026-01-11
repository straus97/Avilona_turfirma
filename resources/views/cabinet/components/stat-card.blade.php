@props(['title', 'value', 'icon', 'color' => 'primary', 'trend' => null])

<div class="card-custom" style="border-left: 4px solid var(--{{ $color }}-color);">
    <div class="d-flex align-items-center justify-content-between">
        <div style="flex: 1;">
            <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">
                {{ $title }}
            </div>
            <div style="font-size: 2rem; font-weight: 700; color: #1f2937; line-height: 1;">
                {{ $value }}
            </div>
            @if($trend)
                <div style="font-size: 0.875rem; margin-top: 0.5rem;" class="text-{{ $trend > 0 ? 'success' : 'danger' }}">
                    <i class="bi bi-arrow-{{ $trend > 0 ? 'up' : 'down' }}"></i>
                    {{ abs($trend) }}% за месяц
                </div>
            @endif
        </div>
        <div style="width: 60px; height: 60px; border-radius: 12px; background: rgba(var(--bs-{{ $color }}-rgb), 0.1); display: flex; align-items: center; justify-content: center;">
            <i class="bi {{ $icon }}" style="font-size: 1.75rem; color: var(--{{ $color }}-color);"></i>
        </div>
    </div>
</div>
