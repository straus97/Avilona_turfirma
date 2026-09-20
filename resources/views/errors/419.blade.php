@extends('errors.layout')

@section('title', 'Сессия истекла')
@section('code', '419')
@section('heading', 'Сессия истекла')
@section('message', 'Страница или форма были открыты слишком долго, и сессия истекла. Откройте страницу заново и повторите действие.')

@section('extra_action')
    <a href="{{ rescue(fn () => route('login'), '/login', false) }}" class="err-btn">Войти в кабинет</a>
@endsection
