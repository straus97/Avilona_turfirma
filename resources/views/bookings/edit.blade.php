@extends('layouts.main')

@section('title', 'Редактировать заявку #' . $booking->id . ' - Авилона')
@section('meta_description', 'Редактирование заявки на тур')

@section('content')
<main>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="mb-0">
                            <i class="bi bi-pencil"></i> Редактировать заявку #{{ $booking->id }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('bookings.update', $booking) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <!-- Статус -->
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Статус заявки <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('status') is-invalid @enderror" 
                                        id="status" 
                                        name="status" 
                                        required
                                        @if(auth()->user()->isTourist() && $booking->status !== \App\Models\Booking::STATUS_NEW) disabled @endif>
                                    @foreach(\App\Models\Booking::availableStatuses() as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" 
                                                {{ old('status', $booking->status) === $statusKey ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if(auth()->user()->isTourist() && $booking->status !== \App\Models\Booking::STATUS_NEW)
                                    <small class="text-muted">Статус нельзя изменить после начала обработки заявки</small>
                                @endif
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
