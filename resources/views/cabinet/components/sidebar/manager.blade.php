@php
    /**
     * Активность пункта меню считается по тем же маршрутным шаблонам, что и
     * раньше; добавлено лишь единообразное выставление aria-current="page"
     * для активного пункта. Назначение и состав пунктов не меняются.
     */
    $navActive = fn (string ...$patterns): bool => request()->routeIs(...$patterns);

    /**
     * Бейдж «Чаты с клиентами» — личные непрочитанные текущего менеджера:
     * строки Message, где receiver_id = его id и is_read = false. НЕ считаются
     * строки уведомлений Laravel, сообщения другому менеджеру и админский надзор.
     *
     * Не каждое действие менеджера кладёт это значение в вид (напр. chat()), а
     * бейдж обязан вести себя одинаково при переходах и совпадать с AJAX-обновлением
     * после переключения ветки. Если авторитетное значение уже передано — берём
     * его, иначе один ограниченный COUNT для аутентифицированного пользователя.
     */
    $sidebarUnreadMessagesCount = $unreadMessagesCount
        ?? \App\Models\Message::query()
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->count();

    /**
     * Бейдж «Мои заявки» — реальные новые/в обработке заявки текущего
     * менеджера. Тот же устойчивый паттерн, что и для непрочитанных выше:
     * если авторитетное значение уже передано видом — берём его, иначе один
     * ограниченный COUNT для аутентифицированного пользователя.
     */
    $sidebarPendingBookingsCount = $pendingBookingsCount
        ?? \App\Models\Booking::query()
            ->where('manager_id', auth()->id())
            ->whereIn('status', [\App\Models\Booking::STATUS_NEW, \App\Models\Booking::STATUS_PROGRESS])
            ->count();
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
    @php($isActive = $navActive('cabinet.manager.inquiries*'))
    <a href="{{ route('cabinet.manager.inquiries') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-inbox" aria-hidden="true"></i>
        <span>Входящие обращения</span>
    </a>
    @php($isActive = $navActive('cabinet.manager.bookings*'))
    <a href="{{ route('cabinet.manager.bookings') }}" @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-journal-text" aria-hidden="true"></i>
        <span>Мои заявки</span>
        @if($sidebarPendingBookingsCount > 0)
            <span class="menu-badge">{{ $sidebarPendingBookingsCount }}</span>
        @endif
    </a>
    @php($isActive = $navActive('cabinet.manager.chat*'))
    <a href="{{ route('cabinet.manager.chat') }}" data-chat-nav-unread @class(['menu-item', 'active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <span>Чаты с клиентами</span>
        @if($sidebarUnreadMessagesCount > 0)
            <span class="menu-badge">{{ $sidebarUnreadMessagesCount }}</span>
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
