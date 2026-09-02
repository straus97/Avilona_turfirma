@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)
@section('og_title', $title)
@section('og_description', $meta_description)
@section('og_type', 'article')
@section('twitter_title', $title)
@section('twitter_description', $meta_description)

@section('content')
    {{-- E2-A5-I1: миграция на систему E2. Динамические meta/og/twitter из
         контроллера сохранены дословно; добавлен только og:type=article.
         Точная граница санитизации Article.content не меняется. Класс
         .article-content не переименовывается (публичный тест-контракт);
         .e2-prose добавляет читаемую типографику. Дата не выводится —
         достоверного поля публикации у статей нет. --}}
    @php
        $articleImage = trim((string) ($id_interesting_news->image ?? ''));
    @endphp
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Интересные статьи', 'url' => route('interesting_articles.index')],
                ['label' => $id_interesting_news->title, 'url' => null],
            ]])

            <article class="e2-editorial-detail e2-editorial-hero">
                <div class="e2-editorial-detail__inner">
                    <header class="e2-page-hero">
                        <h1 id="e2-page-hero-title" class="e2-page-hero__title">{{ $id_interesting_news->title }}</h1>
                    </header>

                    @if($articleImage !== '')
                        <div class="e2-detail-media">
                            <img src="{{ $id_interesting_news->image }}"
                                 alt="{{ $id_interesting_news->title }}" loading="lazy">
                        </div>
                    @endif

                    {{-- E1-FINAL-02 / E2-A5-I1: эшелон защиты — историческое
                         содержимое Article могло быть сохранено до появления
                         очистки на записи. Вызов санитайзера сохранён дословно. --}}
                    <div class="article-content e2-prose">
                        {!! \App\Support\NewsHtmlSanitizer::sanitize($id_interesting_news->content) !!}
                    </div>

                    <div class="e2-editorial-actions">
                        <a href="{{ route('interesting_articles.index') }}" class="e2-btn e2-btn--tertiary">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i> Вернуться к статьям
                        </a>
                    </div>
                </div>
            </article>

            <section class="e2-cta-band" aria-labelledby="e2-article-detail-cta-title">
                <h2 id="e2-article-detail-cta-title" class="e2-cta-band__title">Планируете путешествие?</h2>
                <p class="e2-cta-band__text">Подберём тур под ваши даты и бюджет или ответим на вопросы —
                    напишите менеджеру.</p>
                <div class="e2-cta-band__actions">
                    <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                    <button type="button" class="e2-btn e2-btn--secondary"
                            data-bs-toggle="modal" data-bs-target="#managerContactModal"
                            data-manager-mode="all">Связаться с менеджером</button>
                </div>
            </section>
        </div>
    </main>
@endsection
