@extends('layouts.main')

@section('title', 'Страница не найдена | avilona.ru')
@section('meta_description', 'Запрошенная страница не найдена на сайте туристической фирмы Авилона.')

@section('content')
    <main>
        <div class="container">
            <section class="e2-error-page" aria-labelledby="e2-page-hero-title">
                <img src="{{ asset('img/errors/404_errors.png') }}" alt="" class="e2-error-page__image">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Ой! Похоже, вы заблудились...</h1>
                <p class="e2-page-hero__intro">Страница, которую вы ищете, не найдена. Но не беспокойтесь, мы
                    поможем вам найти путь обратно!</p>
                <div class="e2-error-page__actions">
                    <a href="{{ route('home.index') }}" class="e2-btn e2-btn--primary">Вернуться на главную</a>
                    <a href="{{ route('contact.index') }}" class="e2-btn e2-btn--secondary">Связаться с нами</a>
                </div>
            </section>
        </div>
    </main>
@endsection
