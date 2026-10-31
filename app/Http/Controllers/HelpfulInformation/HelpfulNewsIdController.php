<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\Seo\PublicSeo;

class HelpfulNewsIdController extends Controller
{
    public function __invoke($slug)
    {
        $id_news = News::where('slug', $slug)->firstOrFail();
        $title = $id_news->title . " | avilona.ru";
        $meta_description = PublicSeo::entityDescription($id_news->title, $id_news->description, 'новость о туризме');
        $og_image = PublicSeo::imageUrl($id_news->image, PublicSeo::defaultImageUrl());
        return view('news.show', compact('id_news', 'title', 'meta_description', 'og_image'));
    }
}
