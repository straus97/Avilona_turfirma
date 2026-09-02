@extends('layouts.main')

@section('title', $title ?? $id_news->title . ' - Новости | Авилона')
@section('meta_description', $meta_description ?? Str::limit(strip_tags($id_news->description ?? ''), 160))
@section('meta_keywords', $meta_keywords ?? 'новости, туризм, путешествия')
@section('og_title', $title ?? $id_news->title . ' - Новости | Авилона')
@section('og_description', $meta_description ?? Str::limit(strip_tags($id_news->description ?? ''), 160))
@section('og_type', 'article')
@section('twitter_title', $title ?? $id_news->title . ' - Новости | Авилона')
@section('twitter_description', $meta_description ?? Str::limit(strip_tags($id_news->description ?? ''), 160))

@section('content')
    @php
        // E2-A5-I1: граница безопасности публичного действия «Источник новости».
        // News.link приходит из внешнего RSS-фида и на приёме проверяется только
        // на непустоту/длину (RssNewsSyncService — не трогаем в этом слайсе,
        // санитайзер тоже). Здесь — рендер-граница: действие показывается только
        // если это валидный внешний http/https URL c хостом. Проверка
        // консервативная и многослойная (управляющие символы → parse_url схемы и
        // хоста → filter_var), потому что подстроки/parse_url поодиночке
        // обходятся TAB/LF/CR и protocol-трюками. Blade-экранирование href
        // сохраняется.
        $rawNewsLink = trim((string) ($id_news->link ?? ''));
        $newsSourceUrl = null;
        if ($rawNewsLink !== '' && preg_match('/[\x00-\x1F\x7F]/', $rawNewsLink) !== 1) {
            $scheme = strtolower((string) parse_url($rawNewsLink, PHP_URL_SCHEME));
            $host = (string) parse_url($rawNewsLink, PHP_URL_HOST);
            if (in_array($scheme, ['http', 'https'], true)
                && $host !== ''
                && filter_var($rawNewsLink, FILTER_VALIDATE_URL) !== false) {
                $newsSourceUrl = $rawNewsLink;
            }
        }
    @endphp
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Новости', 'url' => route('helpful_news.index')],
                ['label' => $id_news->title ?? 'Новость', 'url' => null],
            ]])

            <article class="e2-editorial-detail e2-editorial-hero">
                <div class="e2-editorial-detail__inner">
                    <header class="e2-page-hero">
                        <h1 id="e2-page-hero-title" class="e2-page-hero__title">{{ $id_news->title ?? 'Без названия' }}</h1>
                        @if($id_news->pub_date)
                            <p class="e2-editorial-meta">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                <time datetime="{{ \Carbon\Carbon::parse($id_news->pub_date)->toDateString() }}">{{ \Carbon\Carbon::parse($id_news->pub_date)->translatedFormat('j F Y г.') }}</time>
                            </p>
                        @endif
                    </header>

                    {{-- E1-A5-F1 / E2-A5-I1: точная граница санитизации News.description
                         сохранена дословно. Класс .news-content не переименовывается
                         (публичный тест-контракт); .e2-prose добавляет читаемую
                         типографику. Отдельного ведущего изображения над телом нет:
                         News.image извлекается RssNewsSyncService из первого <img>
                         в content:encoded, поэтому та же картинка уже присутствует
                         внутри тела новости (QA: дубль устранён). --}}
                    <div class="news-content e2-prose">
                        {!! \App\Support\NewsHtmlSanitizer::sanitize($id_news->description) !!}
                    </div>

                    <div class="e2-editorial-actions">
                        <a href="{{ route('helpful_news.index') }}" class="e2-btn e2-btn--tertiary">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i> Вернуться к новостям
                        </a>
                        @if($newsSourceUrl)
                            <a href="{{ $newsSourceUrl }}" target="_blank" rel="noopener noreferrer"
                               class="e2-btn e2-btn--secondary">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Источник новости
                            </a>
                        @endif
                    </div>
                </div>
            </article>

            <section class="e2-cta-band" aria-labelledby="e2-news-detail-cta-title">
                <h2 id="e2-news-detail-cta-title" class="e2-cta-band__title">Планируете путешествие?</h2>
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
