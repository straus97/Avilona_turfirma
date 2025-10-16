@extends('layouts.main')

@section('content')
    <div class="container text-center mt-5">
        <img src="{{ asset('img/errors/404_errors.png') }}" alt="404" width="400px">
        <h1 style="font-size: 2rem;">Ой! Похоже, вы заблудились...</h1>
        <p>Страница, которую вы ищете, не найдена. Но не беспокойтесь, мы поможем вам найти путь обратно!</p>
        <p>
            <a href="{{ route('home.index') }}" class="btn btn-primary">Вернуться на главную</a>
            <a href="{{ route('contact.index') }}" class="btn btn-primary">Связаться с нами</a>
        </p>
    </div>
@endsection
