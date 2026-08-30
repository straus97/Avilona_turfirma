@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)
@section('og_title', $title)
@section('og_description', $meta_description)
@section('twitter_title', $title)
@section('twitter_description', $meta_description)

@section('content')
    {{-- E2-A3-I1: миграция на систему E2. Легаси includes.sidebar больше не
         подключается (файл остаётся в репозитории). Динамические meta/og/twitter,
         вывод доверенного контента {!! !!} и семантика маршрута сохранены.
         Производная/выдуманная дата действия предложения не выводится.
         QA-полиш: на >=1200px контент — в основной колонке (легаси-таблицы
         занимают её ширину и при необходимости скроллятся локально),
         навигация и конверсия — в липкой правой колонке; на <1200px —
         один поток. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Специальные предложения', 'url' => route('for_our_clients.index')],
                ['label' => $id_special->title, 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">{{ $id_special->title }}</h1>
            </section>

            <div class="e2-discovery-detail">
                <div class="e2-discovery-detail__main">
                    @include('includes.e2-discovery-media', [
                        'mediaSrc' => $id_special->image,
                        'mediaAlt' => $id_special->image ? $id_special->title : '',
                        'mediaClass' => 'e2-detail-media',
                    ])

                    {{-- .e2-special-prose: см. unified.css — на >=1200px снимает
                         ограничение ширины именно с контейнера Special-контента,
                         чтобы легаси-таблицы (<table class="table">) занимали всю
                         основную колонку; текстовые блоки остаются читаемой меры.
                         Хранимый контент и вывод {!! !!} не изменяются. --}}
                    <div class="e2-prose e2-special-prose">
                        {!! $id_special->content !!}
                    </div>
                </div>

                <aside class="e2-discovery-detail__rail" aria-label="Навигация и действия">
                    <div class="e2-discovery-detail__rail-inner">
                        <nav class="e2-rail-nav" aria-label="Навигация по разделу «Специальные предложения»">
                            <a class="e2-btn e2-btn--tertiary"
                               href="{{ route('for_our_clients.index') }}">Все специальные предложения</a>
                        </nav>

                        @include('includes.e2-discovery-next-step', [
                            'listUrl' => null,
                            'listLabel' => null,
                        ])
                    </div>
                </aside>
            </div>
        </div>
    </main>
@endsection
