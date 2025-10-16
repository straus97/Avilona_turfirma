@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)

@section('content')
    <main>
        <div class="container mt-5">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-md-10">
                    <h1 class="text-center">{{$id_news->title}}</h1>
                    <hr>
                    <div class="p_img">
                        {!! $id_news->description !!}
                        <a href="{{route('helpful_news.index')}}" class="float-end btn btn-primary">Вернуться</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
