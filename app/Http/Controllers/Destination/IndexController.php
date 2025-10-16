<?php

namespace App\Http\Controllers\Destination;

use App\Http\Controllers\Controller;
use App\Models\Destination_image;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke()
    {
        $cacheKey = 'destination_index';
        $cacheTime = 60; // Время кеширования в минутах
        $destination_image = Cache::remember($cacheKey, $cacheTime, function () {
            return Destination_image::orderBy('title', 'asc')->get();
        });
        return view('destinations', compact('destination_image'));
    }
}
