<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Reviews;

class IndexController extends Controller
{
    public function __invoke()
    {
        $reviews = Reviews::where('is_published', 1)->orderBy('id', 'desc')->paginate(4); // показать все записи
        return view('reviews', compact('reviews'));
    }
}
