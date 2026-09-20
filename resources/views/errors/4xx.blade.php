@php
    $errStatus = isset($exception) && method_exists($exception, 'getStatusCode')
        ? (int) $exception->getStatusCode()
        : 400;
@endphp
@extends('errors.layout')

@section('title', 'Не удалось обработать запрос')
@section('code', $errStatus)
@section('heading', 'Не удалось обработать запрос')
@section('message', 'Запрос не может быть выполнен. Проверьте адрес или данные и попробуйте ещё раз либо вернитесь на главную.')
