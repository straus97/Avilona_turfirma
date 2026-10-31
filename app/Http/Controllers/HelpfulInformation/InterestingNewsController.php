<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\Seo\PublicSeo;

class InterestingNewsController extends Controller
{
    public function __invoke($slug)
    {
        $id_interesting_news = Article::where('slug', $slug)->firstOrFail();
        $title = $id_interesting_news->title . " | avilona.ru";
        $meta_description = PublicSeo::entityDescription($id_interesting_news->title, $id_interesting_news->content, 'статья о путешествиях');
        $og_image = PublicSeo::imageUrl($id_interesting_news->image, PublicSeo::defaultImageUrl());
        return view(
            'helpful_information.show_interesting_news',
            compact('id_interesting_news', 'title', 'meta_description', 'og_image')
        );
    }
}
