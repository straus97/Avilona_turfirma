@extends('cabinet.layouts.app')

@section('title', 'Избранное')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Избранное</h1>
    <p class="page-subtitle">Сохранённые туры и направления</p>
</div>

<div class="card-custom">
    <div class="tc-notice">
        <i class="bi bi-bookmark-heart" aria-hidden="true"></i>
        <span>
            Раздел «Избранное» пока в разработке — сохранять туры в личном кабинете
            нельзя. Если хотите вернуться к конкретному предложению, напишите о нём
            менеджеру в <a href="{{ route('cabinet.chat') }}">чат</a> или оставьте
            <a href="{{ route('bookings.create') }}">заявку</a>.
        </span>
    </div>
</div>
@endsection
