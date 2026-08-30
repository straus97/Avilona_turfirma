@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)
@section('og_title', $title)
@section('og_description', $meta_description)
@section('twitter_title', $title)
@section('twitter_description', $meta_description)

@section('content')
    {{-- E2-A3-I1: миграция на систему E2. Легаси includes.sidebar_destinations
         больше не подключается (файл остаётся в репозитории). Список других
         направлений строится из того же $destination_title_menu. Динамические
         meta/og/twitter и вывод доверенного контента {!! !!} сохранены.
         QA-полиш: на >=1200px статья и медиа — в основной колонке, навигация
         («Все направления» / «Другие направления») и конверсия — в липкой
         правой колонке; на <1200px всё сворачивается в один поток. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Направления', 'url' => route('destination.index')],
                ['label' => $id_destination_image->title, 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">{{ $id_destination_image->title }}</h1>
            </section>

            @php
                $destinationMedia = $id_destination_image->image_large
                    ?: ($id_destination_image->image_small ?: null);
            @endphp

            <div class="e2-discovery-detail">
                <div class="e2-discovery-detail__main">
                    @include('includes.e2-discovery-media', [
                        'mediaSrc' => $destinationMedia,
                        'mediaAlt' => $destinationMedia ? $id_destination_image->title : '',
                        'mediaClass' => 'e2-detail-media',
                    ])

                    @if($id_destination_image->description)
                        <div class="e2-prose">
                            {!! $id_destination_image->description !!}
                        </div>
                    @endif
                </div>

                <aside class="e2-discovery-detail__rail" aria-label="Навигация и действия">
                    <div class="e2-discovery-detail__rail-inner">
                        <nav class="e2-rail-nav" aria-label="Навигация по разделу «Направления»">
                            <a class="e2-btn e2-btn--tertiary" href="{{ route('destination.index') }}">Все направления</a>
                        </nav>

                        <section class="e2-rail-block" aria-labelledby="e2-other-destinations-title">
                            <h2 id="e2-other-destinations-title" class="e2-rail-block__title">Другие направления</h2>
                            <div class="e2-chips">
                                @foreach($destination_title_menu as $item_destination_title_menu)
                                    @continue($item_destination_title_menu->id === $id_destination_image->id)
                                    @if($item_destination_title_menu->slug)
                                        <a class="e2-btn e2-btn--tertiary"
                                           href="{{ route('destinations.show_destinations_image', $item_destination_title_menu->slug) }}">{{ $item_destination_title_menu->title }}</a>
                                    @else
                                        <span class="e2-chip-text">{{ $item_destination_title_menu->title }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </section>

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
