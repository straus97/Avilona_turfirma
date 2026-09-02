@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@php
    $pageTitle = 'Наши достижения - Туристическая фирма Авилона | avilona.ru';
    $pageDescription = 'Добро пожаловать на страницу достижений туристической фирмы Авилона. Туристическая фирма Авилона гордится своими достижениями. Ознакомьтесь с нашей галереей сертификатов, дипломов и свидетельств, подтверждающих наш высокий уровень обслуживания и доверие клиентов.';
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('meta_keywords', 'достижения, сертификаты, дипломы, свидетельства, туристическое агентство, высокое обслуживание, доверие клиентов, туристическая фирма')
@section('og_title', $pageTitle)
@section('og_description', $pageDescription)
@section('twitter_title', $pageTitle)
@section('twitter_description', $pageDescription)

<!-- Main Content -->
@section('content')
    {{-- E2-A4-I1: миграция на систему E2. Легаси includes.sidebar и col-md-10
         больше не подключаются. Ровно один H1. Контракт данных награды
         (id, image, category) не расширяется; контроллер/кэш (awards_all,
         3600 с) не трогаются. Модалка — декларативная Bootstrap, без JS. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Наши достижения', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Наши достижения</h1>
                <p class="e2-page-hero__intro">Здесь собраны дипломы, сертификаты и свидетельства
                    туристической фирмы «Авилона». Нажмите на плитку, чтобы открыть изображение целиком.</p>
            </section>

            @if(isset($awards) && $awards->count() > 0)
                <div class="e2-award-grid">
                    @foreach($awards as $item_award)
                        @include('includes.e2-award-tile', ['award' => $item_award])
                    @endforeach
                </div>

                <section class="e2-cta-band" aria-labelledby="awards-cta-title">
                    <h2 id="awards-cta-title" class="e2-cta-band__title">Готовы спланировать поездку?</h2>
                    <p class="e2-cta-band__text">Начните подбор тура или напишите менеджеру — поможем
                        выбрать поездку под ваши даты и бюджет.</p>
                    <div class="e2-cta-band__actions">
                        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                        <button type="button" class="e2-btn e2-btn--secondary"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="all">Связаться с менеджером</button>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('about_company.index') }}">О компании</a>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('employees.index') }}">Наш коллектив</a>
                    </div>
                </section>
            @else
                <section class="e2-cta-band" aria-labelledby="awards-empty-title">
                    <h2 id="awards-empty-title" class="e2-cta-band__title">Награды пока не добавлены</h2>
                    <p class="e2-cta-band__text">Вы можете перейти к подбору тура или связаться
                        с менеджером туристической фирмы «Авилона».</p>
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
