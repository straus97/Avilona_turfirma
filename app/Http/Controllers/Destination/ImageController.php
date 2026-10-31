<?php

namespace App\Http\Controllers\Destination;

use App\Http\Controllers\Controller;
use App\Support\Seo\PublicSeo;
use App\Models\Destination_image;
use Illuminate\Support\Facades\Cache;

class ImageController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'destination_image_' . $slug;
        $cacheTime = 3600; // 1 час (в секундах)
        $id_destination_image = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Destination_image::where('slug', $slug)->firstOrFail();
        });
        $title = $id_destination_image->title . " | avilona.ru";
        $meta_description = PublicSeo::entityDescription($id_destination_image->title, $id_destination_image->description, 'информация о направлении');
        $og_image = PublicSeo::imageUrl($id_destination_image->image_large ?: $id_destination_image->image_small, PublicSeo::defaultImageUrl());
        $destination_title_menu = Cache::remember('destination_title_menu', $cacheTime, function () {
            return Destination_image::orderBy('title', 'asc')->get();
        });
        return view(
            'destinations.show_destinations_image',
            compact('id_destination_image', 'title', 'meta_description', 'og_image', 'destination_title_menu')
        );
    }
}
