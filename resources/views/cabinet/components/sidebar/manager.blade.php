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
    @php($isActive = $navActive('cabinet.manager.dashboard'))
    <a href="{{ route('cabinet.manager.dashboard') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-speedometer2" aria-hidden="true"></i>
        <span>Главная</span>
    </a>
    @php($isActive = $navActive('cabinet.manager.clients*'))
    <a href="{{ route('cabinet.manager.clients') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-people" aria-hidden="true"></i>
        <span>Мои клиенты</span>
    </a>
    @php($isActive = $navActive('cabinet.manager.bookings*'))
    <a href="{{ route('cabinet.manager.bookings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-journal-text" aria-hidden="true"></i>
        <span>Мои заявки</span>
        @if(isset($pendingBookingsCount) && $pendingBookingsCount > 0)
            <span class="menu-badge">{{ $pendingBookingsCount }}</span>
        @endif
    </a>
    @php($isActive = $navActive('cabinet.manager.chat*'))
    <a href="{{ route('cabinet.manager.chat') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <span>Чаты с клиентами</span>
        @if(isset($unreadMessagesCount) && $unreadMessagesCount > 0)
            <span class="menu-badge">{{ $unreadMessagesCount }}</span>
        @endif
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Документы</div>
    @php($isActive = $navActive('cabinet.manager.documents*'))
    <a href="{{ route('cabinet.manager.documents') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        <span>Мои документы</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Аналитика</div>
    @php($isActive = $navActive('cabinet.manager.statistics'))
    <a href="{{ route('cabinet.manager.statistics') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-graph-up" aria-hidden="true"></i>
        <span>Статистика</span>
    </a>
    @php($isActive = $navActive('cabinet.manager.finance'))
    <a href="{{ route('cabinet.manager.finance') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-cash-coin" aria-hidden="true"></i>
        <span>Мои комиссии</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Инструменты</div>
    @php($isActive = $navActive('cabinet.manager.content*', 'cabinet.manager.articles*', 'cabinet.manager.reviews*'))
    <a href="{{ route('cabinet.manager.content') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-file-richtext" aria-hidden="true"></i>
        <span>Контент</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Настройки</div>
    @php($isActive = $navActive('cabinet.manager.profile'))
    <a href="{{ route('cabinet.manager.profile') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-person" aria-hidden="true"></i>
        <span>Мой профиль</span>
    </a>
    @php($isActive = $navActive('cabinet.manager.settings'))
    <a href="{{ route('cabinet.manager.settings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-gear" aria-hidden="true"></i>
        <span>Настройки</span>
    </a>
</div>
