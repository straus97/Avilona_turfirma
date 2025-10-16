@extends('layouts.main') {{--указываем какой шаблон layout будет главный--}}

@section('title', 'Наши достижения - Туристическая фирма Авилона | avilona.ru')
@section('meta_description', 'Добро пожаловать на страницу достижений туристической фирмы Авилона. Туристическая фирма Авилона гордится своими достижениями. Ознакомьтесь с нашей галереей сертификатов, дипломов и свидетельств, подтверждающих наш высокий уровень обслуживания и доверие клиентов.')
@section('meta_keywords', 'достижения, сертификаты, дипломы, свидетельства, туристическое агентство, высокое обслуживание, доверие клиентов, туристическая фирма')

<!-- Main Content -->
@section('content')
    {{--говорим какой блок будет отображаться как контент, каждый на своей странице будет разный--}}
    <main class="container mt-3">
        <div class="row">
            @include('includes.sidebar')
            <div class="col-md-10">
                <h1 class="text-center mb-3" style="font-size: 2rem;">Наши достижения</h1>
                <p>Ведь отпуск — это нечто большее, чем просто удачно купленный тур. Отпуск — это возможность. Это две
                    недели другой жизни, пусть маленький, но все же шанс вырваться из рутины, увидеть, надкусить,
                    попробовать что-то, чего в жизни еще не было, осуществить то, о чем давно мечталось.</p>
                <p>Позвольте себе воплотить в жизнь свои желания и идти навстречу новым приключениям.</p>
                <hr>
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-4">
                    @foreach ($awards as $item_partner)
                        <div class="col">
                            <div class="h-100">
                                <img src="{{ $item_partner->image }}" class="card-img-top p-3" alt=""
                                     data-bs-toggle="modal" data-bs-target="#partnerModal{{ $item_partner->id }}">
                                <!-- Modal -->
                                <div class="modal fade" id="partnerModal{{ $item_partner->id }}" tabindex="-1"
                                     aria-labelledby="partnerModalLabel{{ $item_partner->id }}" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"
                                                    id="partnerModalLabel{{ $item_partner->id }}">{{ $item_partner->category }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <img src="{{ $item_partner->image }}" class="img-fluid">
                                            </div>
                                        </div>
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
