@extends('layouts.main')

@section('title', $title ?? $id_news->title . ' - Новости | Авилона')
@section('meta_description', $meta_description ?? Str::limit(strip_tags($id_news->description ?? ''), 160))
@section('meta_keywords', $meta_keywords ?? 'новости, туризм, путешествия')
@section('og_title', $title ?? $id_news->title . ' - Новости | Авилона')
@section('og_description', $meta_description ?? Str::limit(strip_tags($id_news->description ?? ''), 160))
@section('twitter_title', $title ?? $id_news->title . ' - Новости | Авилона')
@section('twitter_description', $meta_description ?? Str::limit(strip_tags($id_news->description ?? ''), 160))

@section('content')
    <main>
        <div class="container mt-5">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-md-10">
                    <article class="news-article">
                        <h1 class="text-center mb-4">{{ $id_news->title ?? 'Без названия' }}</h1>
                        
                        <div class="news-content">
                            {!! \App\Support\NewsHtmlSanitizer::sanitize($id_news->description) !!}
                        </div>
                        
                        <hr>
                        <div class="news-meta text-muted mb-4">
                            <small>
                                <i class="bi bi-calendar3"></i> 
                                {{ $id_news->pub_date ? \Carbon\Carbon::parse($id_news->pub_date)->translatedFormat('j F Y г.') : '' }}
                            </small>
                        </div>

                        @if($id_news->link)
                        <div class="mt-4">
                            <a href="{{ $id_news->link }}" 
                               target="_blank" 
                               class="btn btn-outline-primary">
                                <i class="bi bi-link-45deg"></i> Источник новости
                            </a>
                        </div>
                        @endif
                        
                        <div class="mt-4">
                            <a href="{{ route('helpful_news.index') }}" 
                               class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Вернуться к новостям
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('styles')
<style>
.news-article {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.news-content {
    line-height: 1.8;
    font-size: 1.1rem;
}

.news-content img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
    border-radius: 8px;
}

.news-content p {
    margin-bottom: 1rem;
}

.news-content h2,
.news-content h3 {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.news-meta {
    font-size: 0.9rem;
}
</style>
@endsection
