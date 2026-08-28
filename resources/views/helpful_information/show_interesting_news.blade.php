@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)
@section('og_title', $title)
@section('og_description', $meta_description)
@section('twitter_title', $title)
@section('twitter_description', $meta_description)

@section('content')
    <main>
        <div class="container mt-3">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-12 col-md-10">
                    <h1 class="text-center" style="font-size: 2rem;">{{$id_interesting_news->title}}</h1>
                    <hr>
                    @if ($id_interesting_news->image)
                    <div class="row justify-content-center">
                        <img src="{{ $id_interesting_news->image }}" class="card-img-top w-75"
                             alt="{{$id_interesting_news->title}}">
                    </div>
                    <hr>
                    @endif
                    <div class="row">
                        <div class="">
                            {{-- E1-FINAL-02: эшелон защиты — историческое содержимое Article
                                 могло быть сохранено до появления очистки на записи. --}}
                            <div class="article-content">
                                {!! \App\Support\NewsHtmlSanitizer::sanitize($id_interesting_news->content) !!}
                            </div>
                            <a href="{{route('interesting_articles.index')}}" class="float-end btn btn-primary">Вернуться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
