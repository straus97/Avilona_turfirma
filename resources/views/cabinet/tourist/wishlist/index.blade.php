@extends('cabinet.layouts.app')

@section('title', 'Избранное')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Избранное</h1>
    <p class="page-subtitle">Туры и направления, которые вам понравились</p>
</div>

<!-- Пока пусто -->
<div class="card-custom">
    <div class="text-center py-5">
        <i class="bi bi-heart" style="font-size: 4rem; color: #d1d5db;"></i>
        <h4 class="mt-4 mb-2">У вас пока нет избранных туров</h4>
        <p class="text-muted mb-4">
            Добавляйте понравившиеся туры в избранное, чтобы вернуться к ним позже
        </p>
        <a href="{{ route('home.index') }}" class="btn btn-primary">
            <i class="bi bi-search"></i> Найти туры
        </a>
    </div>
</div>

<!-- Когда будут туры -->
@if(false)
<div class="row g-4">
    @for($i = 1; $i <= 6; $i++)
    <div class="col-md-6 col-lg-4">
        <div class="card-custom position-relative">
            <button class="btn btn-link position-absolute top-0 end-0 m-2 text-danger">
                <i class="bi bi-heart-fill"></i>
            </button>
            
            <img src="https://via.placeholder.com/400x250" class="card-img-top" alt="Tour">
            
            <div class="p-3">
                <h5 class="card-title">Турция, Анталия</h5>
                <p class="text-muted small mb-2">
                    <i class="bi bi-calendar3"></i> 7 ночей • 
                    <i class="bi bi-star-fill text-warning"></i> 4.8
                </p>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">от</span>
                        <h4 class="mb-0" style="color: var(--primary-color);">45 000 ₽</h4>
                    </div>
                    <a href="#" class="btn btn-outline-primary btn-sm">Подробнее</a>
                </div>
            </div>
        </div>
    </div>
    @endfor
</div>
@endif
@endsection
