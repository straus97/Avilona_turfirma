@extends('cabinet.layouts.app')

@section('title', 'Фиксация отзыва согласия на публикацию')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Фиксация отзыва согласия на публикацию</h1>
</div>

<div class="card-custom">
    <div class="alert alert-warning" role="alert">
        Это действие фиксирует уже полученный и проверенный запрос автора на отзыв согласия. Отзыв будет немедленно снят с публикации, а согласие на публикацию — помечено как отозванное без возможности восстановления. Для повторной публикации в будущем потребуется новое согласие автора, оформленное заново.
    </div>

    <dl class="row">
        <dt class="col-sm-3">ID отзыва</dt>
        <dd class="col-sm-9">{{ $review->id }}</dd>

        <dt class="col-sm-3">Публичное имя автора</dt>
        <dd class="col-sm-9">{{ $review->name }}</dd>

        <dt class="col-sm-3">Текст отзыва</dt>
        <dd class="col-sm-9" style="white-space: pre-wrap;">{{ $review->content }}</dd>

        <dt class="col-sm-3">Текущий статус публикации</dt>
        <dd class="col-sm-9">{{ $review->is_published ? 'Опубликован' : 'Не опубликован' }}</dd>

        <dt class="col-sm-3">ФИО для оформления согласия</dt>
        <dd class="col-sm-9">{{ $consent->consent_full_name }}</dd>

        <dt class="col-sm-3">Email для оформления и отзыва согласия</dt>
        <dd class="col-sm-9">{{ $consent->consent_email }}</dd>
    </dl>

    <form method="POST" action="{{ route('cabinet.manager.reviews.withdraw-consent', $review) }}">
        @csrf
        <div class="form-check mb-3">
            <input class="form-check-input @error('withdrawal_confirmed') is-invalid @enderror" type="checkbox" name="withdrawal_confirmed" id="withdrawal_confirmed" value="1">
            <label class="form-check-label" for="withdrawal_confirmed">
                Я подтверждаю, что запрос автора на отзыв согласия получен, проверен и относится к этому отзыву.
            </label>
            @error('withdrawal_confirmed')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('cabinet.manager.reviews.edit', $review) }}" class="btn btn-outline-secondary">Отмена</a>
            <button type="submit" class="btn btn-danger">Зафиксировать отзыв согласия</button>
        </div>
    </form>
</div>
@endsection
