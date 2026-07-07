@extends('cabinet.layouts.app')

@section('title', 'База знаний')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">База знаний</h1>
    <p class="page-subtitle">Инструкции и полезные материалы</p>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Статьи</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Заголовок</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($articles as $article)
                    <tr>
                        <td>{{ $article->title }}</td>
                        <td>{{ $article->created_at ? $article->created_at->format('d.m.Y') : '—' }}</td>
                        <td>
                            <a href="{{ route('helpful_information.show_interesting_news', $article->slug) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                                <i class="bi bi-eye"></i>
                            </a>
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
