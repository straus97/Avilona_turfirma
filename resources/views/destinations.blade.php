@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Направления - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на страницу Направления туристической фирмы Авилона. Туристическая фирма Авилона предлагает популярные туристические направления. Узнайте больше о каждом направлении и выберите идеальное место для вашего отпуска.')
@section('meta_keywords', 'туристические направления, популярные направления, отдых, туризм, отпуск, туристическая фирма Авилона,туризм, направления, туристическая фирма, туры, путевки, акции')

<!-- Main Content -->
@section('content')
    {{-- E2-A3-I1: миграция на систему E2. Легаси includes.sidebar больше не
         подключается (файл остаётся в репозитории). Живой запрос на
         отсутствующий растровый placeholder удалён — пустое изображение
         заменяет CSS/HTML-заглушка из includes.e2-discovery-media. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Направления', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Направления</h1>
                <p class="e2-page-hero__intro">«Направления» — это про формат и характер отдыха: пляжный,
                    экскурсионный, семейный или активный. Выберите подходящий вариант, а конкретную
                    страну можно подобрать в разделе «Страны».</p>
                <div class="e2-page-hero__actions">
                    <a class="e2-btn e2-btn--tertiary" href="{{ route('countries.index') }}">Смотреть страны</a>
                </div>
            </section>

            @if(isset($destination_image) && $destination_image->count() > 0)
                <div class="e2-discovery e2-grid e2-grid--3">
                    @foreach($destination_image as $item_destination_image)
                        @include('includes.e2-discovery-card', [
                            'href' => $item_destination_image->slug
                                ? route('destinations.show_destinations_image', $item_destination_image->slug)
                                : null,
                            'title' => $item_destination_image->title ?? 'Без названия',
                            'teaser' => $item_destination_image->description
                                ? \Illuminate\Support\Str::limit(strip_tags($item_destination_image->description), 140)
                                : null,
                            'meta' => null,
                            'mediaSrc' => $item_destination_image->image_small,
                            'mediaAlt' => $item_destination_image->title ?? 'Направление',
                        ])
                    @endforeach
                </div>
            @else
                <section class="e2-cta-band" aria-labelledby="e2-destinations-empty-title">
                    <h2 id="e2-destinations-empty-title" class="e2-cta-band__title">Не нашли подходящий вариант?</h2>
                    <p class="e2-cta-band__text">Можно перейти к подбору тура, посмотреть страны
                        или связаться с менеджером — поможем выбрать поездку под ваши даты и бюджет.</p>
                    <div class="e2-cta-band__actions">
                        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                        <button type="button" class="e2-btn e2-btn--secondary"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="all">Связаться с менеджером</button>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('countries.index') }}">Смотреть страны</a>
                    </div>
                </section>
            @endif
        </div>
    </main>
@endsection
