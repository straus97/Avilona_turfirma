@extends('layouts.main')

@section('title', 'Создать заявку - Авилона')
@section('meta_description', 'Создание заявки на тур')

@section('content')
<main>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="bi bi-plus-circle"></i> Создать заявку на тур
                        </h3>
                    </div>
                    <div class="card-body">
                        @if($tour)
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Вы создаете заявку на тур: <strong>{{ $tour->title }}</strong>
                            </div>
                        @endif

                        <form action="{{ route('bookings.store') }}" method="POST">
                            @csrf

                            @if($tour)
                                <input type="hidden" name="tour_id" value="{{ $tour->id }}">
                            @endif

                            <!-- Направление -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="departure_city" class="form-label">
                                        Город вылета <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control @error('departure_city') is-invalid @enderror" 
                                           id="departure_city" 
                                           name="departure_city" 
                                           value="{{ old('departure_city', $tour->departure_city ?? 'Москва') }}" 
                                           required>
                                    @error('departure_city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="destination_country" class="form-label">
                                        Страна <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control @error('destination_country') is-invalid @enderror" 
                                           id="destination_country" 
                                           name="destination_country" 
                                           value="{{ old('destination_country', $tour->destination_country ?? '') }}" 
                                           required>
                                    @error('destination_country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="destination_city" class="form-label">
                                    Курорт/Город
                                </label>
                                <input type="text" 
                                       class="form-control @error('destination_city') is-invalid @enderror" 
                                       id="destination_city" 
                                       name="destination_city" 
                                       value="{{ old('destination_city', $tour->destination_city ?? '') }}">
                                @error('destination_city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Даты и ночи -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">
                                        Дата вылета <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" 
                                           class="form-control @error('start_date') is-invalid @enderror" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="{{ old('start_date', $tour->start_date ?? '') }}" 
                                           min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                           required>
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="nights" class="form-label">
                                        Количество ночей <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" 
                                           class="form-control @error('nights') is-invalid @enderror" 
                                           id="nights" 
                                           name="nights" 
                                           value="{{ old('nights', $tour->nights ?? 7) }}" 
                                           min="1" 
                                           max="30" 
                                           required>
                                    @error('nights')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Туристы -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="adults" class="form-label">
                                        Взрослых <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" 
                                           class="form-control @error('adults') is-invalid @enderror" 
                                           id="adults" 
                                           name="adults" 
                                           value="{{ old('adults', 2) }}" 
                                           min="1" 
                                           max="10" 
                                           required>
                                    @error('adults')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="children" class="form-label">
                                        Детей
                                    </label>
                                    <input type="number" 
                                           class="form-control @error('children') is-invalid @enderror" 
                                           id="children" 
                                           name="children" 
                                           value="{{ old('children', 0) }}" 
                                           min="0" 
                                           max="10">
                                    @error('children')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Дополнительные пожелания -->
                            <div class="mb-3">
                                <label for="notes" class="form-label">
                                    Дополнительные пожелания
                                </label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" 
                                          id="notes" 
                                          name="notes" 
                                          rows="4" 
                                          placeholder="Укажите ваши пожелания по отелю, питанию, расположению и т.д.">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Информация -->
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Обратите внимание:</strong> После создания заявки наш менеджер свяжется с вами для уточнения деталей.
                            </div>

                            <!-- Кнопки -->
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('bookings.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Отмена
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Создать заявку
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

@push('styles')
<style>
.form-label .text-danger {
    font-size: 1.2em;
}
</style>
@endpush
