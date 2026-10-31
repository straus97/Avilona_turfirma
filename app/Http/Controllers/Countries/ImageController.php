<?php

namespace App\Http\Controllers\Countries;

use App\Http\Controllers\Controller;
use App\Support\Seo\PublicSeo;
use App\Models\Countries_image;
use Illuminate\Support\Facades\Cache;

class ImageController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'countries_image_' . $slug;
        $cacheTime = 3600; // 1 час (в секундах)
        $id_countries_image = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return Countries_image::where('slug', $slug)->firstOrFail();
        });
        $title = $id_countries_image->title . " | avilona.ru";
        $meta_description = PublicSeo::entityDescription($id_countries_image->title, $id_countries_image->description, 'информация о стране');
        $og_image = PublicSeo::imageUrl($id_countries_image->image_large ?: $id_countries_image->image_small, PublicSeo::defaultImageUrl());
        return view(
            'countries.show_countries_image',
            compact('id_countries_image', 'title', 'meta_description', 'og_image')
        );
    }
}
