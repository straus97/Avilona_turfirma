<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Reviews;
use App\Support\Seo\PublicSeo;

class IndexController extends Controller
{
    public function __invoke()
    {
        $reviews = Reviews::where('is_published', 1)
            ->whereDoesntHave('reviewConsent', function ($query) {
                $query->whereNotNull('withdrawn_at');
            })
            ->orderBy('id', 'desc')
            ->paginate(6); // E2-A6-I2: 2-колоночная десктопная сетка -> 3 ровных ряда
        PublicSeo::abortIfPageOutOfRange($reviews);
        return view('reviews', compact('reviews'));
    }
}
