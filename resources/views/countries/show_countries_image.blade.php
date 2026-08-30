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
         вывод доверенного контента {!! !!} и семантика documents_url сохранены.
         QA-полиш: на >=1200px статья и медиа живут в основной колонке, а
         существующая навигация/конверсия — в липкой правой колонке
         (.e2-discovery-detail); на <1200px всё сворачивается в один поток. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Страны', 'url' => route('countries.index')],
                ['label' => $id_countries_image->title, 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">{{ $id_countries_image->title }}</h1>
            </section>

            @php
                $countryMedia = $id_countries_image->image_large
                    ?: ($id_countries_image->image_small ?: null);
            @endphp

            <div class="e2-discovery-detail">
                <div class="e2-discovery-detail__main">
                    @include('includes.e2-discovery-media', [
                        'mediaSrc' => $countryMedia,
                        'mediaAlt' => $countryMedia ? $id_countries_image->title : '',
                        'mediaClass' => 'e2-detail-media',
                    ])

                    @if($id_countries_image->description)
                        <div class="e2-prose">
                            {!! $id_countries_image->description !!}
                        </div>
                    @endif

                    @if($id_countries_image->documents_url)
                        <div class="e2-doc-callout">
                            <h2 class="e2-doc-callout__heading">Памятка туристу</h2>
                            <i class="bi bi-file-earmark-pdf e2-doc-callout__icon" aria-hidden="true"></i>
                            <a href="{{ $id_countries_image->documents_url }}" target="_blank" rel="noopener">
                                {{ $id_countries_image->title ?? 'Страна' }}. Памятка туристу
                            </a>
                            @if($id_countries_image->updated_at)
                                <span class="e2-doc-callout__date">Дата обновления:
                                    {{ \Carbon\Carbon::parse($id_countries_image->updated_at)->translatedFormat('j F Y г.') }}</span>
                            @endif
                        </div>
                    @endif
                </div>

                <aside class="e2-discovery-detail__rail" aria-label="Навигация и действия">
                    <div class="e2-discovery-detail__rail-inner">
                        <nav class="e2-rail-nav" aria-label="Навигация по разделу «Страны»">
                            <a class="e2-btn e2-btn--tertiary" href="{{ route('countries.index') }}">Все страны</a>
                        </nav>

                        <section class="e2-rail-block" aria-labelledby="e2-see-also-title">
                            <h2 id="e2-see-also-title" class="e2-rail-block__title">Смотрите также</h2>
                            <div class="e2-chips">
                                <a class="e2-btn e2-btn--tertiary" href="{{ route('countries.index') }}">Страны</a>
                                <a class="e2-btn e2-btn--tertiary" href="{{ route('destination.index') }}">Направления</a>
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
