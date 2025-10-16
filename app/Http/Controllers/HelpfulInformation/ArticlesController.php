<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Support\Facades\Cache;

class ArticlesController extends Controller
{
    public function __invoke()
    {
        $cacheKey = 'interesting_news_page_' . request('page', 1); // Учитываем пагинацию
        $cacheTime = 60; // Время кеширования в минутах
        $interesting_news = Cache::remember($cacheKey, $cacheTime, function () {
            return Article::orderBy('id', 'desc')->paginate(6); // показать 6 записи, начиная с последних
        });
        return view('helpful_information.interesting_articles', compact('interesting_news'));
    }
}
