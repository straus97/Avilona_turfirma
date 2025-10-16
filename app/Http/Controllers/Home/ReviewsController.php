<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Reviews;
use Illuminate\Support\Facades\Cache;

class ReviewsController extends Controller
{
    public function __invoke(Reviews $id_reviews)
    {
        $cacheKey = 'review_' . $id_reviews->id;
        $cacheTime = 60; // Время кеширования в минутах
        $review = Cache::remember($cacheKey, $cacheTime, function () use ($id_reviews) {
            return $id_reviews;
        });
        return view('reviews.show', compact('review'));
    }
}
