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
        <div class="container mt-5">
            <div class="row">
                @include('includes.sidebar')
                <div class="col-md-10">
                    <h1 class="text-center" style="font-size: 2rem;">Немного о
                        стране {{$id_countries_image->title}}</h1>
                    <hr>
                    @if($id_countries_image->image_large)
                    <div class="row justify-content-center">
                        <img src="{{ $id_countries_image->image_large }}" class="card-img-top w-75"
                             alt="{{$id_countries_image->title ?? 'Страна'}}">
                    </div>
                    <hr>
                    @endif
                    <div class="row">
                        <div>
                            @if($id_countries_image->description)
                            <p>{!! $id_countries_image->description !!}</p>
                            @endif
                            @if($id_countries_image->documents_url)
                            <h3>Памятка туристу</h3>
                            <div class="document-container">
                                <img src="{{ asset('/img/pdf.png') }}" alt="PDF"
                                     style="width: 40px; vertical-align: middle;">
                                <a href="{{ $id_countries_image->documents_url }}" target="_blank">
                                    {{$id_countries_image->title ?? 'Страна'}}. Памятка туристу
                                </a>
                                @if($id_countries_image->updated_at)
                                <span
                                    class="document-date">Дата обновления: {{\Carbon\Carbon::parse($id_countries_image->updated_at)->translatedFormat('j F Y г.')}}</span>
                                @endif
                            </div>
                            @endif
                            <a href="{{route('countries.index')}}" class="float-end btn btn-primary">Вернуться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
