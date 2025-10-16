<?php

namespace App\Http\Controllers\Destination;

use App\Http\Controllers\Controller;
use App\Models\Destination_image;
use Illuminate\Support\Facades\Cache;

class ImageController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'destination_image_' . $slug;
        $cacheTime = 60; // Время кеширования в минутах
        $id_destination_image = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Destination_image::where('slug', $slug)->firstOrFail();
        });
        $title = $id_destination_image->title . " | avilona.ru";
        $meta_description = "Узнайте больше о направлении " . $id_destination_image->title . " с туристической фирмой Авилона. Туристическая фирма Авилона предлагает 12 популярных туристических направлений. Узнайте больше о каждом направлении и выберите идеальное место для вашего отпуска.";
        $meta_keywords = "туристические направления, популярные направления, отдых, туризм, отпуск, туристическая фирма Авилона,туризм, направления, " . $id_destination_image->title;
        $destination_title_menu = Cache::remember('destination_title_menu', $cacheTime, function () {
            return Destination_image::orderBy('title', 'asc')->get();
        });
        return view(
            'destinations.show_destinations_image',
            compact('id_destination_image', 'title', 'meta_description', 'meta_keywords', 'destination_title_menu')
        );
    }
}
