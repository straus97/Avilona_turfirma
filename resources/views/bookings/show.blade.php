@auth
    @extends('cabinet.layouts.app')
    
    @section('title', 'Заявка #' . $booking->id)
    
    @section('sidebar')
        @if(Auth::user()->isTourist())
            @include('cabinet.components.sidebar.tourist')
        @elseif(Auth::user()->isManager())
            @include('cabinet.components.sidebar.manager')
        @elseif(Auth::user()->isAdmin())
            @include('cabinet.components.sidebar.admin')
        @endif
    @endsection
    
    @section('content')
        <div class="page-header">
            <h1 class="page-title">Заявка #{{ $booking->id }}</h1>
            <p class="page-subtitle">{{ $booking->destination_country }}@if($booking->destination_city), {{ $booking->destination_city }}@endif</p>
        </div>
        
        <div class="row">
@else
    @extends('layouts.main')
    
    @section('title', 'Заявка #' . $booking->id . ' - Авилона')
    @section('meta_description', 'Детали заявки на тур')
    
    @section('content')
        <main>
            <div class="container mt-5">
                <div class="row">
@endauth
            <div class="col-md-8">
                <!-- Основная информация о заявке -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-{{ $booking->status_color }} text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">
                                <i class="bi bi-bookmark-fill"></i> Заявка #{{ $booking->id }}
                            </h3>
                            <span class="badge bg-white text-{{ $booking->status_color }} fs-6">
                                {{ $booking->status_label }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <!-- Направление -->
                        <div class="mb-4">
                            <h5 class="text-primary">
                                <i class="bi bi-geo-alt-fill"></i> Направление
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Город вылета:</strong><br>
                                        {{ $booking->departure_city }}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Страна:</strong><br>
                                        {{ $booking->destination_country }}
                                    </p>
                                </div>
                                @if($booking->destination_city)
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Курорт/Город:</strong><br>
                                            {{ $booking->destination_city }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <hr>

                        <!-- Даты и туристы -->
                        <div class="mb-4">
                            <h5 class="text-primary">
                                <i class="bi bi-calendar3"></i> Даты и туристы
                            </h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-1">
                                        <strong>Дата вылета:</strong><br>
                                        @if($booking->start_date_end && $booking->start_date_end != $booking->start_date)
                                            {{ $booking->start_date->format('d.m.Y') }} - {{ $booking->start_date_end->format('d.m.Y') }}
                                        @else
                                            {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указано' }}
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1">
                                        <strong>Ночей:</strong><br>
                                        @if($booking->nights_max && $booking->nights_max != $booking->nights)
                                            {{ $booking->nights }} - {{ $booking->nights_max }}
                                        @else
                                            {{ $booking->nights }}
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1">
                                        <strong>Туристов:</strong><br>
                                        {{ $booking->adults }} {{ str_plural($booking->adults, 'взрослый', 'взрослых', 'взрослых') }}
                                        @if($booking->children > 0)
                                            , {{ $booking->children }} {{ str_plural($booking->children, 'ребенок', 'ребенка', 'детей') }}
                                            @if($booking->children_ages && count($booking->children_ages) > 0)
                                                <br>
                                                <small class="text-muted">
                                                    Возраст детей: 
                                                    @foreach($booking->children_ages as $age)
                                                        {{ $age }} {{ str_plural($age, 'год', 'года', 'лет') }}@if(!$loop->last), @endif
                                                    @endforeach
                                                </small>
                                            @endif
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Стоимость -->
                        @if($booking->total_price)
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-cash-stack"></i> Стоимость
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Общая стоимость:</strong><br>
                                            <span class="text-success fs-4">{{ $booking->formatted_total_price }}</span>
                                        </p>
                                    </div>
                                    @if($booking->paid_amount > 0)
                                        <div class="col-md-6">
                                            <p class="mb-1">
                                                <strong>Оплачено:</strong><br>
                                                {{ number_format($booking->paid_amount, 0, ',', ' ') }} ₽
                                            </p>
                                            @if(!$booking->is_fully_paid)
                                                <p class="mb-1">
                                                    <strong>К оплате:</strong><br>
                                                    <span class="text-danger">{{ number_format($booking->remaining_amount, 0, ',', ' ') }} ₽</span>
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <hr>
                        @endif

                        <!-- Пожелания клиента -->
                        @if($booking->notes)
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-chat-left-text"></i> Пожелания клиента
                                </h5>
                                <p class="text-muted">{{ $booking->notes }}</p>
                            </div>
                            <hr>
                        @endif

                        <!-- Заметки менеджера -->
                        @if($booking->manager_notes && (auth()->user()->isManager() || auth()->user()->isAdmin()))
                            <div class="mb-4">
                                <h5 class="text-warning">
                                    <i class="bi bi-clipboard-check"></i> Заметки менеджера
                                </h5>
                                <p class="text-muted">{{ $booking->manager_notes }}</p>
                            </div>
                            <hr>
                        @endif

                        <!-- Информация о клиенте (для менеджера/админа) -->
                        @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-person"></i> Клиент
                                </h5>
                                <p class="mb-1"><strong>Имя:</strong> {{ $booking->user->name ?? 'Удален' }}</p>
                                <p class="mb-1"><strong>Email:</strong> {{ $booking->user->email ?? 'Нет email' }}</p>
                            </div>
                            <hr>
                        @endif

                        <!-- Информация о менеджере -->
                        @if($booking->manager)
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-person-badge"></i> Ваш менеджер
                                </h5>
                                <p class="mb-1"><strong>Имя:</strong> {{ $booking->manager->name }}</p>
                                <p class="mb-1"><strong>Email:</strong> {{ $booking->manager->email }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            Создано: {{ $booking->created_at->format('d.m.Y H:i') }}
                            @if($booking->updated_at->ne($booking->created_at))
                                | Обновлено: {{ $booking->updated_at->format('d.m.Y H:i') }}
                            @endif
                        </small>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 justify-content-between">
                            <a href="{{ route('cabinet.bookings') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> К списку заявок
                            </a>

                            <div class="d-flex flex-wrap gap-2">
                                <!-- Кнопки для туриста -->
                                @if(auth()->user()->isTourist())
                                    @can('cancel', $booking)
                                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                            @csrf
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-x-circle"></i> Отменить заявку
                                            </button>
                                        </form>
                                    @endcan
                                    @if($booking->status === App\Models\Booking::STATUS_NEW && !$booking->manager_id)
                                        <small class="text-muted align-self-center">
                                            <i class="bi bi-info-circle"></i> Вы можете отменить заявку до назначения менеджера
                                        </small>
                                    @elseif($booking->manager_id)
                                        <small class="text-muted align-self-center">
                                            <i class="bi bi-info-circle"></i> Заявка взята в работу менеджером. Для отмены свяжитесь с менеджером.
                                        </small>
                                    @endif
                                @endif

                                <!-- Кнопки для менеджера/админа -->
                                @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                    @if($booking->status !== App\Models\Booking::STATUS_CANCELLED)
                                        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-warning">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </a>

                                        @if($booking->status === App\Models\Booking::STATUS_PROGRESS)
                                            <form action="{{ route('bookings.confirm', $booking) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-check-circle"></i> Подтвердить
                                                </button>
                                            </form>
                                        @endif

                                        @if($booking->status === App\Models\Booking::STATUS_CONFIRMED)
                                            <form action="{{ route('bookings.complete', $booking) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-check-all"></i> Завершить
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                            @csrf
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-x-circle"></i> Отменить
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                <!-- Удаление для админа -->
                                @if(auth()->user()->isAdmin())
                                    <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить заявку? Это действие необратимо!')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-dark">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Боковая панель -->
            <div class="col-md-4">
                <!-- Назначение менеджера (для админа) -->
                @if(auth()->user()->isAdmin() && !$booking->manager)
                    <div class="card shadow mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-person-plus"></i> Назначить менеджера
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('bookings.assign-manager', $booking) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="manager_id" class="form-label">Выберите менеджера</label>
                                    <select name="manager_id" id="manager_id" class="form-select" required>
                                        <option value="">-- Выберите --</option>
                                        @foreach(\App\Models\User::whereHas('roles', function($q) { $q->where('name', 'manager'); })->get() as $manager)
                                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning w-100">
                                    Назначить
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- История статусов -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Статус заявки
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <span class="badge bg-{{ $booking->status_color }} float-end">Текущий</span>
                                <strong>{{ $booking->status_label }}</strong>
                                <br>
                                <small class="text-muted">{{ $booking->updated_at->diffForHumans() }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Информация -->
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle"></i> Информация
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            После подтверждения заявки менеджер свяжется с вами для уточнения деталей и оформления документов.
                        </p>
                        <p class="small text-muted mb-0">
                            При возникновении вопросов, обратитесь к своему менеджеру или в службу поддержки.
                        </p>
                    </div>
                </div>
            </div>
@auth
        </div>
    @endsection
@else
                </div>
            </div>
        </main>
    @endsection
@endauth
@endsection

@push('styles')
<style>
.gap-2 {
    gap: 0.5rem;
}
</style>
@endpush
