@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title')
    @if (request()->has('page') && request()->get('page') >= 1)
        Специальные предложения - Страница {{ request()->get('page') }} | avilona.ru
    @else
        Специальные предложения - Туристическая фирма Авилона | avilona.ru
    @endif
@endsection
@section('meta_description', 'Добро пожаловать на страницу специальных предложений для наших туристов туристической фирмы Авилона. Выгодные предложения, акции и скидки от туристической фирмы Авилона. Ознакомьтесь с нашими специальными предложениями и примите участие в розыгрышах.')
@section('meta_keywords', 'специальные предложения, акции, скидки, розыгрыши, туристическая фирма Авилона, выгодные предложения, туристическая фирма, туры, путевки')

<!-- Main Content -->
@section('content')
    {{-- E2-A3-I1: миграция на систему E2. Легаси includes.sidebar больше не
         подключается (файл остаётся в репозитории). Пагинация (6 на страницу),
         добавление query-string и семантика заголовка сохранены. Дата
         публикации предложений не выводится: our_clients.created_at не является
         достоверной бизнес-датой офера. --}}
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Специальные предложения', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Специальные предложения</h1>
                <p class="e2-page-hero__intro">Актуальные акции, скидки и специальные предложения
                    туристического агентства «Авилона». Выберите предложение, чтобы узнать условия.</p>
            </section>

            @if ($special->count() > 0)
                <div class="e2-discovery e2-grid e2-grid--3">
                    @foreach ($special as $item_special)
                        <article class="e2-card">
                            @include('includes.e2-discovery-media', [
                                'mediaSrc' => $item_special->image,
                                'mediaAlt' => $item_special->image ? ($item_special->title ?? 'Специальное предложение') : '',
                                'mediaClass' => 'e2-card__media',
                            ])
                            <div class="e2-card__body">
                                <h2 class="e2-card__title">{{ $item_special->title ?? 'Без названия' }}</h2>
                                <p class="e2-card__text">{{ \Illuminate\Support\Str::limit(strip_tags($item_special->content ?? ''), 140) }}</p>
                            </div>
                            @if($item_special->slug)
                                <div class="e2-card__footer">
                                    <a href="{{ route('helpful_information.show_special', $item_special->slug) }}"
                                       class="e2-btn e2-btn--primary e2-btn--sm">Подробнее</a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <nav class="d-flex justify-content-center mt-4" aria-label="Навигация по страницам">
                    <ul class="pagination">
                        {{ $special->appends(request()->query())->links() }}
                    </ul>
                </nav>
            @else
                <section class="e2-cta-band" aria-labelledby="e2-specials-empty-title">
                    <h2 id="e2-specials-empty-title" class="e2-cta-band__title">Не нашли подходящий вариант?</h2>
                    <p class="e2-cta-band__text">Можно перейти к подбору тура или связаться с менеджером —
                        поможем выбрать поездку под ваши даты и бюджет.</p>
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
