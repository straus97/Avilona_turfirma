<div class="menu-section">
    <div class="menu-section-title">Основное</div>
    <a href="{{ route('cabinet.manager.dashboard') }}" class="menu-item {{ request()->routeIs('cabinet.manager.dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>
    <a href="{{ route('cabinet.manager.clients') }}" class="menu-item {{ request()->routeIs('cabinet.manager.clients*') ? 'active' : '' }}">
        <i class="bi bi-people"></i>
        <span>Мои клиенты</span>
    </a>
    <a href="{{ route('cabinet.manager.bookings') }}" class="menu-item {{ request()->routeIs('cabinet.manager.bookings*') ? 'active' : '' }}">
        <i class="bi bi-journal-text"></i>
        <span>Мои заявки</span>
        @if(isset($pendingBookingsCount) && $pendingBookingsCount > 0)
            <span class="menu-badge">{{ $pendingBookingsCount }}</span>
        @endif
    </a>
    <a href="{{ route('cabinet.manager.chat') }}" class="menu-item {{ request()->routeIs('cabinet.manager.chat*') ? 'active' : '' }}">
        <i class="bi bi-chat-dots"></i>
        <span>Чаты с клиентами</span>
        @if(isset($unreadMessagesCount) && $unreadMessagesCount > 0)
            <span class="menu-badge">{{ $unreadMessagesCount }}</span>
        @endif
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Аналитика</div>
    <a href="{{ route('cabinet.manager.statistics') }}" class="menu-item {{ request()->routeIs('cabinet.manager.statistics') ? 'active' : '' }}">
        <i class="bi bi-graph-up"></i>
        <span>Статистика</span>
    </a>
    <a href="{{ route('cabinet.manager.finance') }}" class="menu-item {{ request()->routeIs('cabinet.manager.finance') ? 'active' : '' }}">
        <i class="bi bi-cash-coin"></i>
        <span>Мои комиссии</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Инструменты</div>
    <a href="{{ route('cabinet.manager.knowledge') }}" class="menu-item {{ request()->routeIs('cabinet.manager.knowledge') ? 'active' : '' }}">
        <i class="bi bi-book"></i>
        <span>База знаний</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Настройки</div>
    <a href="{{ route('cabinet.profile') }}" class="menu-item {{ request()->routeIs('cabinet.profile') ? 'active' : '' }}">
        <i class="bi bi-person"></i>
        <span>Мой профиль</span>
    </a>
    <a href="{{ route('cabinet.settings') }}" class="menu-item {{ request()->routeIs('cabinet.settings') ? 'active' : '' }}">
        <i class="bi bi-gear"></i>
        <span>Настройки</span>
    </a>
</div>
