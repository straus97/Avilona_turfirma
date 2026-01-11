@props(['icon' => 'bi-inbox', 'title', 'description' => '', 'actionUrl' => null, 'actionText' => null])

<div class="card-custom text-center" style="padding: 3rem 2rem;">
    <div style="width: 80px; height: 80px; border-radius: 50%; background: #f3f4f6; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center;">
        <i class="bi {{ $icon }}" style="font-size: 2rem; color: #9ca3af;"></i>
    </div>
    <h4 style="font-weight: 600; color: #1f2937; margin-bottom: 0.5rem;">{{ $title }}</h4>
    @if($description)
        <p style="color: #6b7280; margin-bottom: 1.5rem;">{{ $description }}</p>
    @endif
    @if($actionUrl && $actionText)
        <a href="{{ $actionUrl }}" class="btn btn-primary">
            {{ $actionText }}
        </a>
    @endif
</div>
