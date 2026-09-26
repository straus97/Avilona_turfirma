@php
    /**
     * Активность пункта меню считается по тем же маршрутным шаблонам, что и
     * раньше; добавлено лишь единообразное выставление aria-current="page"
     * для активного пункта. Назначение и состав пунктов не меняются.
     */
    $navActive = fn (string ...$patterns): bool => request()->routeIs(...$patterns);

    /**
     * Бейдж «Все чаты» — ТОЛЬКО сообщения, адресованные лично этому
     * администратору: строки Message, где receiver_id = его id и is_read = false.
     * Это его личный счётчик как назначенного обработчика заявок; он НЕ суммирует
     * manager_unread_count чужих менеджеров (это остаётся надзорной информацией
     * внутри списка веток, а не личным бейджем). Один ограниченный COUNT, либо
     * авторитетное значение, если вид его уже передал.
     */
    $sidebarUnreadMessagesCount = $unreadMessagesCount
        ?? \App\Models\Message::query()
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->count();
@endphp

<div class="menu-section">
    <div class="menu-section-title">Основное</div>
    @php($isActive = $navActive('cabinet.admin.dashboard'))
    <a href="{{ route('cabinet.admin.dashboard') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-speedometer2" aria-hidden="true"></i>
        <span>Главная</span>
    </a>
    @php($isActive = $navActive('cabinet.admin.profile*'))
    <a href="{{ route('cabinet.admin.profile') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span>Мой профиль</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Управление</div>
    @php($isActive = $navActive('cabinet.admin.users*'))
    <a href="{{ route('cabinet.admin.users') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-people" aria-hidden="true"></i>
        <span>Пользователи</span>
    </a>
    @php($isActive = $navActive('cabinet.manager.inquiries*'))
    <a href="{{ route('cabinet.manager.inquiries') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-inbox" aria-hidden="true"></i>
        <span>Входящие обращения</span>
    </a>
    @php($isActive = $navActive('cabinet.admin.bookings*'))
    <a href="{{ route('cabinet.admin.bookings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-journal-text" aria-hidden="true"></i>
        <span>Все заявки</span>
    </a>
    @php($isActive = $navActive('cabinet.admin.chats*'))
    <a href="{{ route('cabinet.admin.chats') }}" data-chat-nav-unread @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <span>Все чаты</span>
        @if($sidebarUnreadMessagesCount > 0)
            <span class="menu-badge">{{ $sidebarUnreadMessagesCount }}</span>
        @endif
    </a>
    @php($isActive = $navActive('cabinet.admin.content*'))
    <a href="{{ route('cabinet.admin.content') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-file-richtext" aria-hidden="true"></i>
        <span>Контент</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Финансы</div>
    @php($isActive = $navActive('cabinet.admin.finance*'))
    <a href="{{ route('cabinet.admin.finance') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-cash-stack" aria-hidden="true"></i>
        <span>Финансы</span>
    </a>
    @php($isActive = $navActive('cabinet.admin.bonus*'))
    <a href="{{ route('cabinet.admin.bonus') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-gift" aria-hidden="true"></i>
        <span>Бонусная программа</span>
    </a>
</div>

<div class="menu-section">
    <div class="menu-section-title">Система</div>
    @php($isActive = $navActive('cabinet.admin.settings*'))
    <a href="{{ route('cabinet.admin.settings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-gear" aria-hidden="true"></i>
        <span>Система</span>
    </a>
    @php($isActive = $navActive('cabinet.admin.logs'))
    <a href="{{ route('cabinet.admin.logs') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-file-text" aria-hidden="true"></i>
        <span>Логи</span>
    </a>
</div>
