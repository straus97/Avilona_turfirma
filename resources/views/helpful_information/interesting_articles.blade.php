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

<!-- Main Content -->
@section('content')
    {{--говорим какой блок будет отображаться как контент, каждый на своей странице будет разный--}}
    <main>
        <div class="container mt-3">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-12 col-lg-10">
                    <h1 class="text-center mb-3" style="font-size: 2rem;">Интересные статьи</h1>
                    <div class="row">
                        @foreach ($interesting_news as $item_news)
                            <div class="col-sm-6 col-lg-4 mb-3">
                                <div class="card">
                                    <img src="{{ $item_news->image }}" class="card-img-top"
                                         alt="{{ $item_news->title }}">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $item_news->title }}</h5>
                                        <p class="card-text">{!! Str::limit($item_news->content, 100)  !!}</p>
                                        <a href="{{route('helpful_information.show_interesting_news', $item_news->slug)}}"
                                           class="btn btn-primary">Подробнее</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <nav class="d-flex justify-content-center mt-3">
                        <ul class="pagination">
                            {{ $interesting_news->links() }}
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
@endsection

