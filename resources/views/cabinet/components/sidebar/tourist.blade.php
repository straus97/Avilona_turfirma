<div class="menu-section">
    <div class="menu-section-title">Основное</div>
    <a href="{{ route('cabinet.dashboard') }}" class="menu-item {{ request()->routeIs('cabinet.dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i>
        <span>Главная</span>
    </a>
    <a href="{{ route('cabinet.bookings') }}" class="menu-item {{ request()->routeIs('cabinet.bookings*') ? 'active' : '' }}">
        <i class="bi bi-journal-text"></i>
        <span>Мои заявки</span>
        @if(isset($pendingBookingsCount) && $pendingBookingsCount > 0)
            <span class="menu-badge">{{ $pendingBookingsCount }}</span>
        @endif
    </a>
    <a href="{{ route('cabinet.chat') }}" class="menu-item {{ request()->routeIs('cabinet.chat*') ? 'active' : '' }}">
        <i class="bi bi-chat-dots"></i>
        <span>Чат с менеджером</span>
        @if(isset($unreadMessagesCount) && $unreadMessagesCount > 0)
            <span class="menu-badge">{{ $unreadMessagesCount }}</span>
        @endif
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Документы</div>
    <a href="{{ route('cabinet.documents.personal') }}" class="menu-item {{ request()->routeIs('cabinet.documents.personal') ? 'active' : '' }}">
        <i class="bi bi-file-earmark-person"></i>
        <span>Мои документы</span>
    </a>
    <a href="{{ route('cabinet.documents.bookings') }}" class="menu-item {{ request()->routeIs('cabinet.documents.bookings') ? 'active' : '' }}">
        <i class="bi bi-file-earmark-text"></i>
        <span>Документы по заявкам</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Дополнительно</div>
    <a href="{{ route('cabinet.bonus') }}" class="menu-item {{ request()->routeIs('cabinet.bonus') ? 'active' : '' }}">
        <i class="bi bi-gift"></i>
        <span>Бонусы</span>
    </a>
    <a href="{{ route('cabinet.wishlist') }}" class="menu-item {{ request()->routeIs('cabinet.wishlist') ? 'active' : '' }}">
        <i class="bi bi-heart"></i>
        <span>Избранное</span>
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
