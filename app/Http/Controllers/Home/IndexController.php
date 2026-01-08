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
        $cacheTime = 3600; // 1 час для главной страницы
        
        $news = Cache::remember('home_news', $cacheTime, function () {
            return News::select('id', 'title', 'link', 'description', 'image', 'pub_date')
                ->orderBy('pub_date', 'desc')
                ->take(4)
                ->get();
        });
        
        $reviews = Cache::remember('home_reviews', $cacheTime, function () {
            return Reviews::select('id', 'name', 'content', 'image', 'created_at')
                ->where('is_published', 1)
                ->orderBy('id', 'desc')
                ->take(3)
                ->get();
        });
        
        $best_offers = Cache::remember('home_best_offers', $cacheTime, function () {
            return Best_offer::select('id', 'title', 'content', 'image', 'created_at')
                ->orderBy('id', 'desc')
                ->take(4)
                ->get();
        });
        
        $partners = Cache::remember('home_partners', $cacheTime, function () {
            return Partner::select('id', 'name_partner', 'logo_partner')->get();
        });
        
        return view('home', compact('news', 'reviews', 'best_offers', 'partners'));
    }
}
