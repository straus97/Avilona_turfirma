<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\OurClient;
use App\Support\Seo\PublicSeo;
use Illuminate\Support\Facades\Cache;

class SpecialController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'special_' . $slug;
        $cacheTime = 3600; // 1 час (в секундах)
        $id_special = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return OurClient::where('slug', $slug)->firstOrFail();
        });
        $title = $id_special->title . " | avilona.ru";
        $meta_description = PublicSeo::entityDescription($id_special->title, $id_special->content, 'материал для клиентов');
        $og_image = PublicSeo::imageUrl($id_special->image, PublicSeo::defaultImageUrl());
        return view(
            'helpful_information.show_special',
            compact('id_special', 'title', 'meta_description', 'og_image')
        );
    }
}
