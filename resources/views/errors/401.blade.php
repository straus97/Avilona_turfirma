@extends('errors.layout')

@section('title', 'Требуется авторизация')
@section('code', '401')
@section('heading', 'Требуется авторизация')
@section('message', 'Эта страница доступна только после входа. Войдите в личный кабинет и попробуйте снова.')

@section('extra_action')
    <a href="{{ rescue(fn () => route('login'), '/login', false) }}" class="err-btn">Войти в кабинет</a>
@endsection
