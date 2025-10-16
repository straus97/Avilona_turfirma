<?php

namespace App\Http\Controllers\Countries;

use App\Http\Controllers\Controller;
use App\Models\Countries_image;
use Illuminate\Support\Facades\Cache;

class ImageController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'countries_image_' . $slug;
        $cacheTime = 60; // Время кеширования в минутах
        $id_countries_image = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Countries_image::where('slug', $slug)->firstOrFail();
        });
        $title = $id_countries_image->title . " | avilona.ru";
        $meta_description = "Узнайте больше о " . $id_countries_image->title . " и получите незабываемые впечатления с туристической фирмой Авилона. Откройте для себя 55 незабываемых страны с туристической фирмой Авилона. Узнайте о культуре, традициях, национальных блюдах и достопримечательностях каждой страны. Полезная информация для туристов и памятка путешественника.";
        $meta_keywords = "страны, культура, традиции, национальные блюда, достопримечательности, памятка туриста, туризм, туристическая фирма Авилона, " . $id_countries_image->title;
        return view(
            'countries.show_countries_image',
            compact('id_countries_image', 'title', 'meta_description', 'meta_keywords')
        );
    }
}
