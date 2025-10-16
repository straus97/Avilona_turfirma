@extends('layouts.main')

@section('title', $title)
@section('meta_description', $meta_description)
@section('meta_keywords', $meta_keywords)

@section('content')
    <main>
        <div class="container mt-5">
            <div class="row">
                @include('includes.sidebar_destinations')
                <div class="col-md-10">
                    <h1 class="text-center" style="font-size: 2rem;">Немного о "{{$id_destination_image->title}}"</h1>
                    <hr>
                    <div class="row justify-content-center">
                        <img src="{{ $id_destination_image->image_large }}" class="card-img-top w-75"
                             alt="{{$id_destination_image->title}}">
                    </div>
                    <hr>
                    <div class="row">
                        <div class="">
                            <p>{!! $id_destination_image->description !!}</p>
                            <a href="{{route('destination.index')}}" class="float-end btn btn-primary">Вернуться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
