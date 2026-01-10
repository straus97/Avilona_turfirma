@extends('layouts.profile')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Управление контентом</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Статистика -->
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="bi bi-newspaper"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Новостей</span>
                            <span class="info-box-number">{{ $newsCount }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="bi bi-star"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Отзывов</span>
                            <span class="info-box-number">{{ $reviewsCount }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="bi bi-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Ожидают модерации</span>
                            <span class="info-box-number">{{ $pendingReviews }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Последние новости -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Последние новости</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Заголовок</th>
                                <th>Дата публикации</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentNews as $news)
                                <tr>
                                    <td>{{ $news->title }}</td>
                                    <td>{{ $news->pub_date ? \Carbon\Carbon::parse($news->pub_date)->format('d.m.Y') : '-' }}</td>
                                    <td>
                                        <a href="{{ route('helpful_news_id.index', $news->slug) }}" class="btn btn-sm btn-info" target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Последние отзывы -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Последние отзывы</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Пользователь</th>
                                <th>Отзыв</th>
                                <th>Рейтинг</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentReviews as $review)
                                <tr>
                                    <td>{{ $review->user->name ?? 'Гость' }}</td>
                                    <td>{{ Str::limit($review->review, 50) }}</td>
                                    <td>
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= $review->rating)
                                                <i class="bi bi-star-fill text-warning"></i>
                                            @else
                                                <i class="bi bi-star text-muted"></i>
                                            @endif
                                        @endfor
                                    </td>
                                    <td>{{ $review->created_at->format('d.m.Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
