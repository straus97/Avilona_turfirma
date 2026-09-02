@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@php
    $pageTitle = 'Сотрудники - Туристическая фирма Авилона | avilona.ru';
    $pageDescription = 'Добро пожаловать на страницу коллектива туристической фирмы Авилона. Познакомьтесь с командой туристической фирмы Авилона. Наши профессиональные сотрудники готовы помочь вам с выбором тура и предоставить всю необходимую информацию для вашего идеального отпуска.';
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('meta_keywords', 'сотрудники, команда, туристическое агентство, контактная информация, профессионалы, помощь в выборе тура, туристическая фирма')
@section('og_title', $pageTitle)
@section('og_description', $pageDescription)
@section('twitter_title', $pageTitle)
@section('twitter_description', $pageDescription)

<!-- Main Content -->
@section('content')
    {{-- E2-A4-I1: миграция на систему E2. Легаси includes.sidebar и col-md-10
         больше не подключаются. Ровно один H1. Контракт данных сотрудника
         (id, name, position, tel, email, whatsapp, vk, image) не расширяется;
         контроллер/кэш (employees_all, 3600 с) не трогаются. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Наш коллектив', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Наш коллектив</h1>
                <p class="e2-page-hero__intro">Менеджеры туристической фирмы «Авилона» — с ними можно связаться
                    напрямую по телефону или в мессенджере.</p>
            </section>

            @if(isset($employees) && $employees->count() > 0)
                <div class="e2-discovery e2-grid e2-grid--3">
                    @foreach($employees as $item_employee)
                        @include('includes.e2-person-card', ['employee' => $item_employee])
                    @endforeach
                </div>

                <section class="e2-cta-band" aria-labelledby="employees-cta-title">
                    <h2 id="employees-cta-title" class="e2-cta-band__title">Не знаете, к кому обратиться?</h2>
                    <p class="e2-cta-band__text">Начните подбор тура или напишите менеджеру — поможем
                        выбрать поездку под ваши даты и бюджет.</p>
                    <div class="e2-cta-band__actions">
                        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
                        <button type="button" class="e2-btn e2-btn--secondary"
                                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                                data-manager-mode="all">Связаться с менеджером</button>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('about_company.index') }}">О компании</a>
                        <a class="e2-btn e2-btn--tertiary" href="{{ route('awards.index') }}">Наши достижения</a>
                    </div>
                </section>
            @else
                <section class="e2-cta-band" aria-labelledby="employees-empty-title">
                    <h2 id="employees-empty-title" class="e2-cta-band__title">Информация о сотрудниках
                        сейчас недоступна</h2>
                    <p class="e2-cta-band__text">Вы можете связаться с туристической фирмой «Авилона» через
                        менеджера или перейти к подбору тура.</p>
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
