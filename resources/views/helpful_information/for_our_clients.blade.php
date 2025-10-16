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
    {{--говорим какой блок будет отображаться как контент, каждый на своей странице будет разный--}}
    <main>
        <div class="container mt-3">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-12 col-lg-10">
                    <h1 class="text-center mb-3" style="font-size: 2rem;">Специальные предложения</h1>
                    <div class="row">
                        @foreach ($special as $item_special)
                            <div class="col-sm-6 col-lg-4 mb-3">
                                <div class="card">
                                    <img src="{{ $item_special->image }}" class="card-img-top"
                                         alt="{{ $item_special->title }}">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $item_special->title }}</h5>
                                        <p class="card-text">{!! Str::limit($item_special->content, 100) !!}</p>
                                        <a href="{{route('helpful_information.show_special', $item_special->slug)}}"
                                           class="btn btn-primary">Подробнее</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <nav class="d-flex justify-content-center mt-3">
                        <ul class="pagination">
                            {{ $special->links() }}
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
@endsection


