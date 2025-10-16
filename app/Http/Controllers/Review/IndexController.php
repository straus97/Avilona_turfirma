<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Reviews;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke()
    {
        $cacheKey = 'reviews_page_' . request('page', 1); // Учитываем пагинацию
        $cacheTime = 60; // Время кеширования в минутах
        $reviews = Cache::remember($cacheKey, $cacheTime, function () {
            return Reviews::where('is_published', 1)->orderBy('id', 'desc')->paginate(4); // показать все записи
        });
        return view('reviews', compact('reviews'));
    }
}
