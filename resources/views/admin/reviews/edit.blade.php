@extends('cabinet.layouts.app')

@section('title', 'Редактировать отзыв')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Редактировать отзыв</h1>
    <p class="page-subtitle">{{ $review->name }}</p>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Редактирование</div>
    </div>

    <form method="POST" action="{{ route('cabinet.admin.reviews.update', $review->id) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">Имя</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $review->name) }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Тема (необязательно)</label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $review->title) }}">
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Если тема не указана, на сайте заголовок не отображается.</small>
        </div>
        @php
            $boyUrl = url('/img/user-boy.png');
            $girlUrl = url('/img/user-girl.png');
            $currentImage = old('image', $review->image);
            $genderValue = old('gender', $currentImage === $girlUrl ? 'girl' : 'boy');
        @endphp
        <input type="hidden" name="image" id="reviewImage" value="{{ $currentImage ?: $boyUrl }}">
        <div class="mb-3">
            <label class="form-label">Автор отзыва</label>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="gender" id="genderBoy" value="boy" {{ $genderValue === 'boy' ? 'checked' : '' }}>
                    <label class="form-check-label" for="genderBoy">Мужчина</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="gender" id="genderGirl" value="girl" {{ $genderValue === 'girl' ? 'checked' : '' }}>
                    <label class="form-check-label" for="genderGirl">Женщина</label>
                </div>
            </div>
            @error('gender')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Отзыв</label>
            <textarea name="content" rows="6" class="form-control @error('content') is-invalid @enderror" required>{{ old('content', $review->content) }}</textarea>
            @error('content')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <input type="hidden" name="is_published" value="0">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published', $review->is_published) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_published">Опубликовать на сайте</label>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('cabinet.admin.content') }}" class="btn btn-outline-secondary">Отмена</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Сохранить
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const reviewImageInput = document.getElementById('reviewImage');
    const boyUrl = @json(url('/img/user-boy.png'));
    const girlUrl = @json(url('/img/user-girl.png'));

    const setReviewImage = (value) => {
        reviewImageInput.value = value === 'girl' ? girlUrl : boyUrl;
    };

    const boyRadio = document.getElementById('genderBoy');
    const girlRadio = document.getElementById('genderGirl');
    if (boyRadio && girlRadio) {
        setReviewImage(girlRadio.checked ? 'girl' : 'boy');
        boyRadio.addEventListener('change', () => setReviewImage('boy'));
        girlRadio.addEventListener('change', () => setReviewImage('girl'));
    }
</script>
@endpush
@endsection
