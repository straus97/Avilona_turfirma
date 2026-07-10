@php
    $layout = auth()->check() ? 'cabinet.layouts.app' : 'layouts.main';
@endphp

@extends($layout)

@section('title', auth()->check() ? 'Редактировать заявку #' . $booking->id : 'Редактировать заявку #' . $booking->id . ' - Авилона')
@section('meta_description', 'Редактирование заявки на тур')

@auth
    @section('sidebar')
        @if(Auth::user()->isTourist())
            @include('cabinet.components.sidebar.tourist')
        @elseif(Auth::user()->isManager())
            @include('cabinet.components.sidebar.manager')
        @elseif(Auth::user()->isAdmin())
            @include('cabinet.components.sidebar.admin')
        @endif
    @endsection
@endauth

@section('content')
@auth
    <div class="page-header">
        <h1 class="page-title">Редактировать заявку #{{ $booking->id }}</h1>
        <p class="page-subtitle">Обновление статуса и служебной информации</p>
    </div>
@endauth

@guest
<main>
    <div class="container mt-5">
        <div class="row justify-content-center">
@endguest
            <div class="col-md-8">
                <div class="@auth card-custom @else card shadow @endauth">
                    @auth
                        <div class="card-header-custom">
                            <div class="card-title-custom">
                                <i class="bi bi-pencil"></i> Редактировать заявку #{{ $booking->id }}
                            </div>
                        </div>
                    @else
                        <div class="card-header bg-warning text-dark">
                            <h3 class="mb-0">
                                <i class="bi bi-pencil"></i> Редактировать заявку #{{ $booking->id }}
                            </h3>
                        </div>
                        <div class="card-body">
                    @endauth
                        <form action="{{ route('bookings.update', $booking) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <!-- Статус -->
                            @php
                                $allowedStatuses = $booking->allowedStatusesForUpdate();
                                $allStatusLabels = \App\Models\Booking::availableStatuses();
                                $selectedStatus = in_array(old('status'), $allowedStatuses) ? old('status') : $booking->status;
                            @endphp
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Статус заявки <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('status') is-invalid @enderror" 
                                        id="status" 
                                        name="status" 
                                        required>
                                    @foreach($allowedStatuses as $statusKey)
                                        <option value="{{ $statusKey }}" 
                                                {{ $selectedStatus === $statusKey ? 'selected' : '' }}>
                                            {{ $allStatusLabels[$statusKey] ?? $statusKey }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Для менеджера и админа -->
                            @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                <div class="mb-3">
                                    <label for="total_price" class="form-label">
                                        Стоимость, ₽
                                    </label>
                                    <input type="number" 
                                           class="form-control @error('total_price') is-invalid @enderror" 
                                           id="total_price" 
                                           name="total_price" 
                                           value="{{ old('total_price', $booking->total_price) }}" 
                                           min="0" 
                                           step="0.01">
                                    @error('total_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="manager_notes" class="form-label">
                                        Заметки менеджера
                                    </label>
                                    <textarea class="form-control @error('manager_notes') is-invalid @enderror" 
                                              id="manager_notes" 
                                              name="manager_notes" 
                                              rows="4" 
                                              placeholder="Внутренние заметки, не видны клиенту">{{ old('manager_notes', $booking->manager_notes) }}</textarea>
                                    @error('manager_notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <!-- Для туриста (только если статус NEW) -->
                            @if(auth()->user()->isTourist() && $booking->status === \App\Models\Booking::STATUS_NEW)
                                <div class="mb-3">
                                    <label for="notes" class="form-label">
                                        Дополнительные пожелания
                                    </label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" 
                                              id="notes" 
                                              name="notes" 
                                              rows="4" 
                                              placeholder="Укажите ваши пожелания по отелю, питанию, расположению и т.д.">{{ old('notes', $booking->notes) }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <!-- Информация о заявке (только для просмотра) -->
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Информация о заявке</h6>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Направление:</strong><br>
                                            {{ $booking->departure_city }} → {{ $booking->destination_country }}
                                            @if($booking->destination_city), {{ $booking->destination_city }}@endif
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Дата вылета:</strong><br>
                                            {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указано' }}
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Ночей:</strong> {{ $booking->nights }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Туристов:</strong> 
                                            {{ $booking->adults }} взрослых, {{ $booking->children }} детей
                                        </p>
                                    </div>
                                </div>
                                <hr>
                                <small class="text-muted">
                                    Для изменения деталей тура свяжитесь с вашим менеджером
                                </small>
                            </div>

                            @if(auth()->user()->isTourist())
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Обратите внимание:</strong> После начала обработки заявки менеджером, редактирование будет недоступно.
                                </div>
                            @endif

                            <!-- Кнопки -->
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Отмена
                                </a>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-check-circle"></i> Сохранить изменения
                                </button>
                            </div>
                        </form>
                    @auth
                    @else
                        </div>
                    @endauth
                </div>
            </div>
@guest
        </div>
    </div>
</main>
@endguest
@endsection
