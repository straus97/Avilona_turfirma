@extends('cabinet.layouts.app')

@section('title', 'Контент')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Контент</h1>
    <p class="page-subtitle">Интересные статьи и модерация отзывов</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row mb-4">
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Статей',
            'value' => $articlesCount,
            'icon' => 'bi-newspaper',
            'color' => 'primary'
        ])
    </div>
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'Отзывов',
            'value' => $reviewsCount,
            'icon' => 'bi-star',
            'color' => 'success'
        ])
    </div>
    <div class="col-md-4">
        @include('cabinet.components.stat-card', [
            'title' => 'На модерации',
            'value' => $pendingReviews,
            'icon' => 'bi-clock',
            'color' => 'warning'
        ])
    </div>
</div>

<div class="card-custom mb-4">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="card-title-custom">Интересные статьи</div>
        <a href="{{ route('cabinet.manager.articles.create') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-circle"></i> Создать статью
        </a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Заголовок</th>
                    <th>Slug</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentArticles as $article)
                    <tr>
                        <td>{{ $article->title }}</td>
                        <td class="text-muted">{{ $article->slug }}</td>
                        <td>{{ $article->created_at ? $article->created_at->format('d.m.Y') : '—' }}</td>
                        <td class="d-flex gap-2">
                            <a href="{{ route('helpful_information.show_interesting_news', $article->slug) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('cabinet.manager.articles.edit', $article->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('cabinet.manager.articles.delete', $article->id) }}" method="POST" onsubmit="return confirm('Удалить статью?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <a href="{{ route('cabinet.manager.articles') }}" class="btn btn-sm btn-outline-secondary">Все статьи</a>
    </div>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Последние отзывы</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Отзыв</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentReviews as $review)
                    <tr>
                        <td>{{ $review->name }}</td>
                        <td>{{ Str::limit($review->content, 80) }}</td>
                        <td>
                            @if($review->is_published)
                                <span class="badge bg-success">Опубликован</span>
                            @else
                                <span class="badge bg-warning text-dark">На модерации</span>
                            @endif
                        </td>
                        <td>{{ $review->created_at ? $review->created_at->format('d.m.Y') : '—' }}</td>
                        <td>
                            <a href="{{ route('cabinet.manager.reviews.edit', $review->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
