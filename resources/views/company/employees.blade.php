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
                    @if(isset($employees) && $employees->count() > 0)
                        @foreach($employees as $item_employee)
                            <div class="col-md-4 mb-5">
                                <div class="card">
                                    @if($item_employee->image)
                                    <img src="{{ $item_employee->image }}" class="card-img-top rounded-3"
                                         alt="{{ $item_employee->name ?? 'Сотрудник' }}">
                                    @endif
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $item_employee->name ?? 'Без имени' }}</h5>
                                        @if($item_employee->position)
                                        <p class="card-text">{{ $item_employee->position }}</p>
                                        @endif
                                        @if($item_employee->tel)
                                        <p class="card-text">Телефон: <a href="tel:{{ $item_employee->tel }}"
                                                                         target="_blank">{{ $item_employee->tel }}</a></p>
                                        @endif
                                        @if($item_employee->email)
                                        <p class="card-text">Email: <a href="mailto:{{ $item_employee->email }}"
                                                                       target="_blank">{{ $item_employee->email }}</a></p>
                                        @endif
                                        <div class="social-icons text-center">
                                            @if($item_employee->whatsapp)
                                            <a href="https://wa.me/{{ $item_employee->whatsapp }}" target="_blank"><img
                                                    src="{{ asset('/img/whatsapp.png') }}" alt="whatsapp" width="45px"
                                                    class="mx-3"></a>
                                            @endif
                                            @if($item_employee->vk)
                                            <a href="{{ $item_employee->vk }}" target="_blank"><img
                                                    src="{{ asset('/img/vk.png') }}" alt="vk" width="45px" class="mx-3"></a>
                                            @endif
                                            @if($item_employee->email)
                                            <a href="mailto:{{ $item_employee->email }}" target="_blank"><img
                                                    src="{{ asset('/img/mail.png') }}" alt="email" width="45px"
                                                    class="mx-3"></a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="alert alert-info">
                            <p class="mb-0">Сотрудники пока не добавлены в базу данных.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
