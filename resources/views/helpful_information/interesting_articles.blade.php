@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title')
    @if (request()->has('page') && request()->get('page') >= 1)
        Интересные статьи - Страница {{ request()->get('page') }} | avilona.ru
    @else
        Интересные статьи - Туристическая фирма Авилона | avilona.ru
    @endif
@endsection
@section('meta_description', 'Добро пожаловать на страницу интересных статей туристической фирмы Авилона. Читайте интересные статьи о туризме и путешествиях на сайте туристической фирмы Авилона. Полезные советы, лайфхаки и информация о местах, которые стоит посетить.')
@section('meta_keywords', 'интересные статьи, туризм, путешествия, советы, лайфхаки, места для посещения, туристическая фирма Авилона, туристическая фирма, туры, путевки, акции')
@section('og_title', 'Интересные статьи о путешествиях — Туристическая фирма Авилона')
@section('og_description', 'Полезные советы, лайфхаки и рассказы о местах, которые стоит посетить, от туристической фирмы «Авилона».')
@section('twitter_title', 'Интересные статьи о путешествиях — Туристическая фирма Авилона')
@section('twitter_description', 'Полезные советы, лайфхаки и рассказы о местах, которые стоит посетить, от туристической фирмы «Авилона».')

<!-- Main Content -->
@section('content')
    {{-- E2-A5-I1: миграция на систему E2. Легаси includes.sidebar больше не
         подключается (файл остаётся в репозитории). Обычная серверная
         пагинация. Класс .card-text у тизера и подстрока «Статьи пока не
         добавлены» в пустом состоянии сохранены (публичные тест-контракты).
         Дата у статей не выводится — надёжного поля публикации нет. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Интересные статьи', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Интересные статьи</h1>
                <p class="e2-page-hero__intro">Полезные материалы о путешествиях: советы, лайфхаки и рассказы
                    о местах, которые стоит посетить. Выберите статью, чтобы прочитать её целиком.</p>
            </section>

            @if ($interesting_news->count() > 0)
                <div class="e2-editorial-list e2-grid e2-grid--3">
                    @foreach ($interesting_news as $item_news)
                        @include('includes.e2-editorial-card', [
                            'href' => $item_news->slug
                                ? route('helpful_information.show_interesting_news', $item_news->slug)
                                : null,
                            'title' => $item_news->title ?? 'Без названия',
                            'teaser' => \App\Helpers\TextHelper::plainExcerpt($item_news->content, 140),
                            'date' => null,
                            'mediaSrc' => $item_news->image,
                            'mediaAlt' => $item_news->title ?? 'Статья',
                        ])
                    @endforeach
                </div>

                <div class="e2-pagination">
                    {{ $interesting_news->appends(request()->query())->links() }}
                </div>
            @else
                <section class="e2-cta-band" aria-labelledby="e2-articles-empty-title">
                    <h2 id="e2-articles-empty-title" class="e2-cta-band__title">Статьи пока не добавлены</h2>
                    <p class="e2-cta-band__text">Загляните позже — мы регулярно публикуем новые материалы
                        о путешествиях. А спланировать поездку можно уже сейчас.</p>
                    <div class="e2-cta-band__actions">
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
