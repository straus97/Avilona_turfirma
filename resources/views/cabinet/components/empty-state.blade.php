@php
    $icon = $icon ?? 'bi-inbox';
    $description = $description ?? '';
    $actionUrl = $actionUrl ?? null;
    $actionText = $actionText ?? null;
@endphp

<div class="card-custom empty-state text-center" style="padding: 3rem 2rem;">
    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--cabinet-surface-alt); margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center;">
        <i class="bi {{ $icon }}" style="font-size: 2rem; color: var(--cabinet-muted);" aria-hidden="true"></i>
    </div>
    <h4 style="font-weight: 600; color: var(--cabinet-heading); margin-bottom: 0.5rem;">{{ $title }}</h4>
    @if($description)
        <p style="color: var(--cabinet-muted); margin-bottom: 1.5rem;">{{ $description }}</p>
    @endif
    @if($actionUrl && $actionText)
        <a href="{{ $actionUrl }}" class="btn btn-primary">
            {{ $actionText }}
        </a>
    @endif
</div>
