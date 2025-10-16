<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Best_offer;
use App\Models\News;
use App\Models\Partner;
use App\Models\Reviews;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke()
    {
        // Ключи кеша для каждой части данных
        $newsCacheKey = 'home_news';
        $reviewsCacheKey = 'home_reviews';
        $bestOffersCacheKey = 'home_best_offers';
        $partnersCacheKey = 'home_partners';
        // Получаем данные из кеша или из базы данных
        $news = Cache::remember($newsCacheKey, 60, function () {
            return News::orderBy('pub_date', 'desc')->take(4)->get();
        });
        $reviews = Cache::remember($reviewsCacheKey, 60, function () {
            return Reviews::where('is_published', 1)->orderBy('id', 'desc')->take(3)->get();
        });
        $best_offers = Cache::remember($bestOffersCacheKey, 60, function () {
            return Best_offer::orderBy('id', 'desc')->take(4)->get();
        });
        $partners = Cache::remember($partnersCacheKey, 60, function () {
            return Partner::all();
        });
        return view('home', compact('news', 'reviews', 'best_offers', 'partners'));
    }
}
