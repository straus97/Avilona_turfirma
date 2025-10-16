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
                    <h1 class="text-center" style="font-size: 2rem;">{{$id_special->title}}</h1>
                    <hr>
                    <div class="row justify-content-center">
                        <img src="{{ $id_special->image }}" class="card-img-top w-75" alt="{{$id_special->title}}">
                    </div>
                    <hr>
                    <div class="row">
                        <div class="">
                            <p>{!! $id_special->content !!}</p>
                            <a href="{{route('for_our_clients.index')}}" class="float-end btn btn-primary">Вернуться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
