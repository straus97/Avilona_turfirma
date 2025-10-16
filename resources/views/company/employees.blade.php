@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Сотрудники - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на страницу коллектива туристической фирмы Авилона. Познакомьтесь с командой туристической фирмы Авилона. Наши профессиональные сотрудники готовы помочь вам с выбором тура и предоставить всю необходимую информацию для вашего идеального отпуска.')
@section('meta_keywords', 'сотрудники, команда, туристическое агентство, контактная информация, профессионалы, помощь в выборе тура, туристическая фирма')

<!-- Main Content -->
@section('content')
    {{--говорим какой блок будет отображаться как контент, каждый на своей странице будет разный--}}
    <main class="container mt-3">
        <div class="row">
            @include('includes.sidebar')
            <div class="col-md-10">
                <div class="row">
                    <h1 class="text-center mb-3" style="font-size: 2rem;">Наш коллектив</h1>
                    @foreach($employees as $item_employee)
                        <div class="col-md-4 mb-5">
                            <div class="card">
                                <img src="{{ $item_employee->image }}" class="card-img-top rounded-3"
                                     alt="{{ $item_employee->name }}">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $item_employee->name }}</h5>
                                    <p class="card-text">{{ $item_employee->position }}</p>
                                    <p class="card-text">Телефон: <a href="tel:{{ $item_employee->whatsapp }}"
                                                                     target="_blank">{{ $item_employee->tel }}</a></p>
                                    <p class="card-text">Email: <a href="mailto:{{ $item_employee->email }}"
                                                                   target="_blank">{{ $item_employee->email }}</a></p>
                                    <div class="social-icons text-center">
                                        <a href="https://wa.me/{{ $item_employee->whatsapp }}" target="_blank"><img
                                                src="{{ asset('/img/whatsapp.png') }}" alt="whatsapp" width="45px"
                                                class="mx-3"></a>
                                        <a href="{{ $item_employee->vk }}" target="_blank"><img
                                                src="{{ asset('/img/vk.png') }}" alt="vk" width="45px" class="mx-3"></a>
                                        <a href="mailto:{{ $item_employee->email }}" target="_blank"><img
                                                src="{{ asset('/img/mail.png') }}" alt="email" width="45px"
                                                class="mx-3"></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </main>
@endsection
