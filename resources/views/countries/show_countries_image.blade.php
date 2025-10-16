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
                    <h1 class="text-center" style="font-size: 2rem;">Немного о
                        стране {{$id_countries_image->title}}</h1>
                    <hr>
                    <div class="row justify-content-center">
                        <img src="{{ $id_countries_image->image_large }}" class="card-img-top w-75"
                             alt="{{$id_countries_image->title}}">
                    </div>
                    <hr>
                    <div class="row">
                        <div>
                            <p>{!! $id_countries_image->description !!}</p>
                            <h3>Памятка туристу</h3>
                            <div class="document-container">
                                <img src="{{ asset('/img/pdf.png') }}" alt="PDF"
                                     style="width: 40px; vertical-align: middle;">
                                <a href="{{ $id_countries_image->documents_url }}" target="_blank">
                                    {{$id_countries_image->title}}. Памятка туристу
                                </a>
                                <span
                                    class="document-date">Дата обновления: {{Date::parse($id_countries_image->updated_at)->format('j F Y г.')}}</span>
                            </div>
                            <a href="{{route('countries.index')}}" class="float-end btn btn-primary">Вернуться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
