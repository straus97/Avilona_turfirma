@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)

@section('content')
    <main>
        <div class="container mt-3">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-12 col-md-10">
                    <h1 class="text-center" style="font-size: 2rem;">{{$id_interesting_news->title}}</h1>
                    <hr>
                    <div class="row justify-content-center">
                        <img src="{{ $id_interesting_news->image }}" class="card-img-top w-75"
                             alt="{{$id_interesting_news->title}}">
                    </div>
                    <hr>
                    <div class="row">
                        <div class="">
                            <p>{!! $id_interesting_news->content !!}</p>
                            <a href="{{route('interesting_articles.index')}}" class="float-end btn btn-primary">Вернуться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
