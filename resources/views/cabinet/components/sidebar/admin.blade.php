<div class="menu-section">
    <div class="menu-section-title">Основное</div>
    <a href="{{ route('cabinet.admin.dashboard') }}" class="menu-item {{ request()->routeIs('cabinet.admin.dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i>
        <span>Главная</span>
    </a>
    <a href="{{ route('cabinet.admin.profile') }}" class="menu-item {{ request()->routeIs('cabinet.admin.profile*') ? 'active' : '' }}">
        <i class="bi bi-person-circle"></i>
        <span>Мой профиль</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Управление</div>
    <a href="{{ route('cabinet.admin.users') }}" class="menu-item {{ request()->routeIs('cabinet.admin.users*') ? 'active' : '' }}">
        <i class="bi bi-people"></i>
        <span>Пользователи</span>
    </a>
    <a href="{{ route('cabinet.admin.bookings') }}" class="menu-item {{ request()->routeIs('cabinet.admin.bookings*') ? 'active' : '' }}">
        <i class="bi bi-journal-text"></i>
        <span>Все заявки</span>
    </a>
    <a href="{{ route('cabinet.admin.chats') }}" class="menu-item {{ request()->routeIs('cabinet.admin.chats*') ? 'active' : '' }}">
        <i class="bi bi-chat-dots"></i>
        <span>Все чаты</span>
    </a>
    <a href="{{ route('cabinet.admin.content') }}" class="menu-item {{ request()->routeIs('cabinet.admin.content*') ? 'active' : '' }}">
        <i class="bi bi-file-richtext"></i>
        <span>Контент</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Финансы</div>
    <a href="{{ route('cabinet.admin.finance') }}" class="menu-item {{ request()->routeIs('cabinet.admin.finance*') ? 'active' : '' }}">
        <i class="bi bi-cash-stack"></i>
        <span>Финансы</span>
    </a>
    <a href="{{ route('cabinet.admin.bonus') }}" class="menu-item {{ request()->routeIs('cabinet.admin.bonus*') ? 'active' : '' }}">
        <i class="bi bi-gift"></i>
        <span>Бонусная программа</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Система</div>
    <a href="{{ route('cabinet.admin.settings') }}" class="menu-item {{ request()->routeIs('cabinet.admin.settings*') ? 'active' : '' }}">
        <i class="bi bi-gear"></i>
        <span>Настройки</span>
    </a>
    <a href="{{ route('cabinet.admin.logs') }}" class="menu-item {{ request()->routeIs('cabinet.admin.logs') ? 'active' : '' }}">
        <i class="bi bi-file-text"></i>
        <span>Логи</span>
    </a>
</div>
