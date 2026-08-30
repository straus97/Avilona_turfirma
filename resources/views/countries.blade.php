@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Страны - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на страницу Страны туристической фирмы Авилона. Откройте для себя широкий выбор стран с туристической фирмой Авилона. Узнайте о культуре, традициях, национальных блюдах и достопримечательностях каждой страны. Полезная информация для туристов и памятка путешественника.')
@section('meta_keywords', 'страны, культура, традиции, национальные блюда, достопримечательности, памятка туриста, туризм, туристическая фирма Авилона, туристическая фирма, туры, путевки, акции')

<!-- Main Content -->
@section('content')
    {{-- E2-A3-I1: страница миграции на систему E2. Легаси-сайдбар
         includes.sidebar_and_sorted_countries больше не подключается —
         фильтр перенесён в основную колонку как .e2-filter-bar. Файл
         партиала оставлен в репозитории (используется в других местах). --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Страны', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Страны</h1>
                <p class="e2-page-hero__intro">Здесь собраны страны, в которые мы помогаем организовать
                    поездку: коротко о культуре, традициях и полезных деталях для путешественника.
                    Выберите страну, чтобы узнать больше, — а формат отдыха можно посмотреть
                    в разделе «Направления».</p>
            </section>

            <form method="GET" action="{{ route('countries.index') }}" class="e2-filter-bar"
                  aria-label="Фильтр списка стран">
                <div class="e2-filter-bar__field">
                    <label for="title">Страна</label>
                    <input type="text" name="title" id="title" value="{{ request('title') }}"
                           placeholder="Например, Испания">
                </div>
                <div class="e2-filter-bar__field">
                    <label for="category">Категория</label>
                    <select name="category" id="category">
                        <option value="">Все категории</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}"
                                    @if(request('category') === $category) selected @endif>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="e2-filter-bar__actions">
                    <button type="submit" class="e2-btn e2-btn--primary e2-btn--sm">Найти</button>
                    <a href="{{ route('countries.index', ['reset' => true]) }}"
                       class="e2-btn e2-btn--tertiary e2-btn--sm">Сбросить</a>
                </div>
            </form>

            <div class="e2-doc-callout">
                <h2 class="e2-doc-callout__heading">Памятка выезжающему за рубеж</h2>
                <i class="bi bi-file-earmark-pdf e2-doc-callout__icon" aria-hidden="true"></i>
                <a href="{{ asset('/documents/Reminder_for_those_traveling_abroad_general.pdf') }}"
                   target="_blank" rel="noopener">
                    Памятка выезжающего за рубеж (общая)
                </a>
                <span class="e2-doc-callout__date">Дата обновления: 22 мая 2024 г.</span>
            </div>

            @if(isset($countries_image) && $countries_image->count() > 0)
                <div class="e2-discovery e2-grid e2-grid--3">
                    @foreach($countries_image as $item_countries_image)
                        @include('includes.e2-discovery-card', [
                            'href' => $item_countries_image->slug
                                ? route('countries.show_countries_image', $item_countries_image->slug)
                                : null,
                            'title' => $item_countries_image->title ?? 'Без названия',
                            'teaser' => $item_countries_image->description
                                ? \Illuminate\Support\Str::limit(strip_tags($item_countries_image->description), 140)
                                : null,
                            'meta' => $item_countries_image->category ?: null,
                            'mediaSrc' => $item_countries_image->image_small,
                            'mediaAlt' => $item_countries_image->title ?? 'Страна',
                        ])
                    @endforeach
                </div>
            @else
                <section class="e2-cta-band" aria-labelledby="e2-countries-empty-title">
                    <h2 id="e2-countries-empty-title" class="e2-cta-band__title">Не нашли подходящий вариант?</h2>
                    <p class="e2-cta-band__text">Попробуйте изменить условия фильтра, перейти к подбору тура
                        или связаться с менеджером — поможем выбрать поездку под ваши даты и бюджет.</p>
                    <div class="e2-cta-band__actions">
                        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                        <button type="button" class="e2-btn e2-btn--secondary"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="all">Связаться с менеджером</button>
                        <a class="e2-btn e2-btn--tertiary"
                           href="{{ route('countries.index', ['reset' => true]) }}">Показать все страны</a>
                    </div>
                </section>
            @endif
        </div>
    </main>
@endsection
