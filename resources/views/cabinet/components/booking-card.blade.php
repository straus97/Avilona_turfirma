{{--
    Карточка заявки. Ранее весь контейнер был кликабельным <div> с onclick-
    навигацией (недоступно с клавиатуры). Теперь используется паттерн
    «растянутой ссылки»: настоящая ссылка на заголовке заявки покрывает всю
    карточку через ::after, а вложенная ссылка «Чат» остаётся отдельным
    интерактивным элементом над ней (без вложенных ссылок друг в друге).
    Назначение перехода — то же (bookings.show).
--}}
<div class="card-custom booking-card">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h5 class="mb-1" style="font-weight: 600; color: var(--cabinet-heading);">
                <a href="{{ route('bookings.show', $booking->id) }}" class="stretched-link text-reset text-decoration-none">
                    {{ $booking->destination_country }}
                    @if($booking->destination_city)
                        <span style="color: var(--cabinet-muted);">• {{ $booking->destination_city }}</span>
                    @endif
                </a>
            </h5>
            <div style="font-size: 0.875rem; color: var(--cabinet-muted);">
                <i class="bi bi-hash" aria-hidden="true"></i> Заявка #{{ $booking->id }}
                <span class="mx-2">•</span>
                <i class="bi bi-calendar3" aria-hidden="true"></i> {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указана' }}
            </div>
        </div>
        @include('cabinet.components.status-badge', ['status' => $booking->status])
    </div>

    <div class="row g-3 mb-3" style="font-size: 0.875rem;">
        <div class="col-6 col-md-3">
            <div style="color: var(--cabinet-muted); font-size: 0.75rem; margin-bottom: 0.25rem;">Город вылета</div>
            <div style="font-weight: 500;">{{ $booking->departure_city }}</div>
        </div>
        <div class="col-6 col-md-3">
            <div style="color: var(--cabinet-muted); font-size: 0.75rem; margin-bottom: 0.25rem;">Ночей</div>
            <div style="font-weight: 500;">
                {{ $booking->nights }}
                @if($booking->nights_max && $booking->nights_max != $booking->nights)
                    - {{ $booking->nights_max }}
                @endif
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div style="color: var(--cabinet-muted); font-size: 0.75rem; margin-bottom: 0.25rem;">Взрослых</div>
            <div style="font-weight: 500;">{{ $booking->adults }}</div>
        </div>
        @if($booking->children > 0)
            <div class="col-6 col-md-3">
                <div style="color: var(--cabinet-muted); font-size: 0.75rem; margin-bottom: 0.25rem;">Детей</div>
                <div style="font-weight: 500;">{{ $booking->children }}</div>
            </div>
        @endif
    </div>

    @if($booking->manager)
        <div class="d-flex align-items-center gap-2 pt-3 border-top">
            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                {{ strtoupper(substr($booking->manager->name, 0, 1)) }}
            </div>
            <div style="flex: 1;">
                <div style="font-size: 0.75rem; color: var(--cabinet-muted);">Менеджер</div>
                <div style="font-weight: 500; font-size: 0.875rem;">{{ $booking->manager->name }}</div>
            </div>
            <a href="{{ route('cabinet.chat', $booking->id) }}" class="btn btn-sm btn-outline-primary position-relative" style="z-index: 2;">
                <i class="bi bi-chat-dots" aria-hidden="true"></i> Чат
            </a>
        </div>
    @endif
</div>
