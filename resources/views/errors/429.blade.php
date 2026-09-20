@extends('errors.layout')

@section('title', 'Слишком много запросов')
@section('code', '429')
@section('heading', 'Слишком много запросов')
@section('message', 'Вы отправили слишком много запросов за короткое время. Подождите немного и попробуйте снова.')
