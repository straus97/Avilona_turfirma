@php
    /**
     * Активность пункта меню считается по тем же маршрутным шаблонам, что и
     * раньше; добавлено лишь единообразное выставление aria-current="page"
     * для активного пункта. Назначение и состав пунктов не меняются.
     */
    $navActive = fn (string ...$patterns): bool => request()->routeIs(...$patterns);
@endphp

<div class="menu-section">
    <div class="menu-section-title">Основное</div>
    @php($isActive = $navActive('cabinet.dashboard'))
    <a href="{{ route('cabinet.dashboard') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-speedometer2" aria-hidden="true"></i>
        <span>Главная</span>
    </a>
    @php($isActive = $navActive('cabinet.bookings*'))
    <a href="{{ route('cabinet.bookings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-journal-text" aria-hidden="true"></i>
        <span>Мои заявки</span>
    </a>
    @php($isActive = $navActive('cabinet.chat*'))
    <a href="{{ route('cabinet.chat') }}" data-chat-nav-unread @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <span>Чат с менеджером</span>
        @if(isset($unreadMessagesCount) && $unreadMessagesCount > 0)
            <span class="menu-badge">{{ $unreadMessagesCount }}</span>
        @endif
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Документы</div>
    @php($isActive = $navActive('cabinet.documents.personal'))
    <a href="{{ route('cabinet.documents.personal') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-file-earmark-person" aria-hidden="true"></i>
        <span>Мои документы</span>
    </a>
    @php($isActive = $navActive('cabinet.documents.bookings'))
    <a href="{{ route('cabinet.documents.bookings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        <span>Документы по заявкам</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Дополнительно</div>
    @php($isActive = $navActive('cabinet.bonus'))
    <a href="{{ route('cabinet.bonus') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-gift" aria-hidden="true"></i>
        <span>Бонусы</span>
    </a>
    @php($isActive = $navActive('cabinet.wishlist'))
    <a href="{{ route('cabinet.wishlist') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-heart" aria-hidden="true"></i>
        <span>Избранное</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Настройки</div>
    @php($isActive = $navActive('cabinet.profile'))
    <a href="{{ route('cabinet.profile') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-person" aria-hidden="true"></i>
        <span>Мой профиль</span>
    </a>
    @php($isActive = $navActive('cabinet.settings'))
    <a href="{{ route('cabinet.settings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-gear" aria-hidden="true"></i>
        <span>Настройки</span>
    </a>
</div>
