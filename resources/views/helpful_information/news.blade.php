@extends('layouts.main')

@section('title')
    @if (request()->has('page') && request()->get('page') >= 1)
        Новости - Страница {{ request()->get('page') }} | avilona.ru
    @else
        Новости - Туристическая фирма Авилона | avilona.ru
    @endif
@endsection
@section('meta_description', 'Добро пожаловать на страницу новостей о туризме туристической фирмы Авилона. Актуальные новости о туризме и путешествиях от туристической фирмы Авилона. Читайте последние статьи и будьте в курсе всех событий в мире туризма.')
@section('meta_keywords', 'новости туризма, актуальные новости, путешествия, последние статьи, туристическая фирма Авилона, туристическая фирма, туры, путевки, акции')

@section('content')
    <main>
        <div class="container mt-3">
            <div class="row">
                @include('includes.sidebar_and_sorted_news')
                <div class="col-12 col-lg-10">
                    <h1 class="text-center mb-3" style="font-size: 2rem;">Новости</h1>
                    @if(isset($news) && $news->count() > 0)
                    <div id="news-container" class="row">
                        @foreach ($news as $item)
                            <div class="col-sm-6 col-lg-4 mb-3">
                                <div class="card h-100 d-flex flex-column">
                                    @if($item->image)
                                    <img data-src="{{ $item->image }}" class="card-img-top lazyload"
                                         alt="{{ $item->title ?? 'Новость' }}"
                                         style="height: 200px; object-fit: cover;">
                                    @endif
                                    <div class="card-body flex-grow-1 d-flex flex-column">
                                        <h5 class="card-title">{{ $item->title ?? 'Без названия' }}</h5>
                                        @if($item->description)
                                        <p class="card-text flex-grow-1">{!! App\Helpers\TextHelper::formatNewsDescription($item->description) !!}</p>
                                        @endif
                                        @if($item->slug)
                                        <a href="{{ route('helpful_news_id.index', $item->slug) }}"
                                           class="btn btn-primary mt-auto">Подробнее</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <nav class="d-flex justify-content-center mt-3">
                        <ul class="pagination">
                            {{ $news->appends(request()->query())->links() }}
                        </ul>
                    </nav>
                    @else
                    <div class="alert alert-info">
                        <p class="mb-0">Новости пока не добавлены в базу данных.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
@push('scripts')
    <script>
        $(document).on('click', '.pagination a', function (event) {
            event.preventDefault();
            var url = $(this).attr('href');
            $.ajax({
                url: url,
                type: 'GET',
                success: function (response) {
                    $('#news-container').html($(response).find('#news-container').html());
                    $('.pagination').html($(response).find('.pagination').html());
                    lazySizes.init(); // Инициализация lazysizes после AJAX-загрузки
                }
            });
        });
        document.addEventListener('DOMContentLoaded', function () {
            lazySizes.init(); // Инициализация lazysizes при первой загрузке страницы
        });
    </script>
@endpush
