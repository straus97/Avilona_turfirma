@extends('layouts.main')

@section('title')
    @if (request()->has('page') && request()->get('page') >= 1)
        Новости - Страница {{ request()->get('page') }} | avilona.ru
    @else
        Новости - Туристическая фирма Авилона | avilona.ru
    @endif
@endsection
@section('meta_description', 'Добро пожаловать на страницу новостей о туризме туристической фирмы Авилона. Актуальные новости о туризме и путешествиях от туристической фирмы Авилона. Читайте последние статьи и будьте в курсе всех событий в мире туризма.')
@section('meta_keywords', 'новости туризма, актуальные новости, путешествия, последние статьи, туристическая фирма Авилона, туристическая фирма, туры, путевки, акции')
@section('og_title', 'Новости туризма — Туристическая фирма Авилона')
@section('og_description', 'Актуальные новости о туризме и путешествиях от туристической фирмы «Авилона».')
@section('twitter_title', 'Новости туризма — Туристическая фирма Авилона')
@section('twitter_description', 'Актуальные новости о туризме и путешествиях от туристической фирмы «Авилона».')

{{-- E2-A5-I1: RSS-автообнаружение только на странице списка новостей.
     Лента и её контроллер не редактируются — ссылка формируется через
     route('news.rss') в общем head-хуке (layouts.main @yield('head_extra')). --}}
@section('head_extra')
    <link rel="alternate" type="application/rss+xml" title="Новости туризма — Авилона"
          href="{{ route('news.rss') }}">
@endsection

@section('content')
    {{-- E2-A5-I1: миграция на систему E2. Легаси includes.sidebar_and_sorted_news
         больше не подключается (файл остаётся в репозитории). Фильтр по дате
         перенесён в компактную E2-панель. Пагинация — обычная серверная (AJAX
         и подмена #news-container через jQuery удалены; jQuery/lazysizes
         глобально не трогаем). id="news-container" и класс .card-text у тизера
         сохранены (публичные тест-контракты). --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Новости', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Новости</h1>
                <p class="e2-page-hero__intro">Актуальные новости туризма и путешествий: коротко о том,
                    что происходит в отрасли. Выберите материал, чтобы прочитать его целиком.</p>
            </section>

            <form method="GET" action="{{ route('helpful_news.index') }}" class="e2-editorial-filter"
                  aria-label="Фильтр новостей по дате">
                <div class="e2-editorial-filter__field">
                    <label for="date">Показать новости не позже даты</label>
                    <input type="date" name="date" id="date" value="{{ request('date') }}">
                </div>
                <div class="e2-editorial-filter__actions">
                    <button type="submit" class="e2-btn e2-btn--primary e2-btn--sm">Найти</button>
                    <a href="{{ route('helpful_news.index') }}"
                       class="e2-btn e2-btn--tertiary e2-btn--sm">Сбросить</a>
                </div>
                <p class="e2-editorial-filter__hint">Выводятся все записи от выбранной даты и ранее.</p>
            </form>

            @if(isset($news) && $news->count() > 0)
                <div id="news-container" class="e2-editorial-list e2-grid e2-grid--3">
                    @foreach ($news as $item)
                        @include('includes.e2-editorial-card', [
                            'href' => $item->slug ? route('helpful_news_id.index', $item->slug) : null,
                            'title' => $item->title ?? 'Без названия',
                            'teaser' => $item->description
                                ? \App\Helpers\TextHelper::formatNewsDescription($item->description)
                                : null,
                            'date' => $item->pub_date
                                ? \Carbon\Carbon::parse($item->pub_date)->translatedFormat('j F Y г.')
                                : null,
                            'mediaSrc' => $item->image,
                            'mediaAlt' => $item->title ?? 'Новость',
                        ])
                    @endforeach
                </div>

                <div class="e2-pagination">
                    {{ $news->appends(request()->query())->links() }}
                </div>
            @else
                <section class="e2-cta-band" aria-labelledby="e2-news-empty-title">
                    @if(request()->filled('date'))
                        <h2 id="e2-news-empty-title" class="e2-cta-band__title">Новостей по этому запросу нет</h2>
                        <p class="e2-cta-band__text">Попробуйте изменить дату фильтра или вернитесь к полному
                            списку новостей.</p>
                    @else
                        <h2 id="e2-news-empty-title" class="e2-cta-band__title">Новости пока не опубликованы</h2>
                        <p class="e2-cta-band__text">Мы регулярно публикуем материалы о туризме — загляните
                            позже. А спланировать поездку можно уже сейчас.</p>
                    @endif
                    <div class="e2-cta-band__actions">
                        @if(request()->filled('date'))
                            <a class="e2-btn e2-btn--tertiary" href="{{ route('helpful_news.index') }}">Все новости</a>
                        @endif
                        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                        <button type="button" class="e2-btn e2-btn--secondary"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="all">Связаться с менеджером</button>
                    </div>
                </section>
            @endif
        </div>
    </main>
@endsection
