@extends('cabinet.layouts.app')

@section('title', 'Интересные статьи')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Интересные статьи</h1>
    <p class="page-subtitle">Создание и редактирование статей</p>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card-custom">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="card-title-custom">Все статьи</div>
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
                @foreach($articles as $article)
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
    @if($articles->hasPages())
        <div class="card-footer">
            {{ $articles->links() }}
        </div>
    @endif
</div>
@endsection
