@php
    $errStatus = isset($exception) && method_exists($exception, 'getStatusCode')
        ? (int) $exception->getStatusCode()
        : 500;
@endphp
@extends('errors.layout')

@section('title', 'Сервис временно недоступен')
@section('code', $errStatus)
@section('heading', 'Сервис временно недоступен')
@section('message', 'На сервере возникла временная проблема. Попробуйте повторить запрос чуть позже.')
