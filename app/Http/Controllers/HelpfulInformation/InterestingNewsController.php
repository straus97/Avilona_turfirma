<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Support\Facades\Cache;

class InterestingNewsController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'interesting_news_' . $slug;
        $cacheTime = 60; // Время кеширования в минутах
        $id_interesting_news = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Article::where('slug', $slug)->firstOrFail();
        });
        $title = $id_interesting_news->title . " | avilona.ru";
        $meta_description = "Читайте интересные статьи о " . $id_interesting_news->title . " на сайте туристической фирмы Авилона. Читайте интересные статьи о туризме и путешествиях на сайте туристической фирмы Авилона. Полезные советы, лайфхаки и информация о местах, которые стоит посетить.";
        $meta_keywords = "интересные статьи, туризм, путешествия, советы, лайфхаки, места для посещения, туристическая фирма Авилона, " . $id_interesting_news->title;
        return view(
            'helpful_information.show_interesting_news',
            compact('id_interesting_news', 'title', 'meta_description', 'meta_keywords')
        );
    }
}
