@extends('cabinet.layouts.app')

@section('title', 'Редактировать статью')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Редактировать статью</h1>
    <p class="page-subtitle">{{ $article->title }}</p>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Редактирование</div>
    </div>

    <form method="POST" action="{{ route('cabinet.admin.articles.update', $article->id) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">Заголовок</label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $article->title) }}" required>
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Slug (необязательно)</label>
            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $article->slug) }}">
            @error('slug')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Изображение (URL)</label>
            <input type="url" name="image" class="form-control @error('image') is-invalid @enderror" value="{{ old('image', $article->image) }}" placeholder="https://...">
            @error('image')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Контент</label>
            <textarea name="content" rows="10" class="form-control @error('content') is-invalid @enderror" required>{{ old('content', $article->content) }}</textarea>
            @error('content')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('cabinet.admin.articles') }}" class="btn btn-outline-secondary">Отмена</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Сохранить
            </button>
        </div>
    </form>
</div>
@endsection
