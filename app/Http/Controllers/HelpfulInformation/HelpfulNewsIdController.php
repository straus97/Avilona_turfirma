<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Support\Facades\Cache;

class HelpfulNewsIdController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'news_' . $slug;
        $cacheTime = 60; // Время кеширования в минутах
        $id_news = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return News::where('slug', $slug)->firstOrFail();
        });
        $title = $id_news->title . " | avilona.ru";
        $meta_description = "Последние новости о " . $id_news->title . " на сайте туристической фирмы Авилона. Актуальные новости о туризме и путешествиях от туристической фирмы Авилона. Читайте последние статьи и будьте в курсе всех событий в мире туризма.";
        $meta_keywords = "новости туризма, актуальные новости, путешествия, последние статьи, туристическая фирма Авилона, новости, " . $id_news->title;
        return view('news.show', compact('id_news', 'title', 'meta_description', 'meta_keywords'));
    }
}
