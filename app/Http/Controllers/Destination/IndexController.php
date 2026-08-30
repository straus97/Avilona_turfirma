<?php

namespace App\Http\Controllers\Destination;

use App\Http\Controllers\Controller;
use App\Models\Destination_image;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke()
    {
        $destination_image = Cache::remember('destination_index', 3600, function () {
            return Destination_image::select('id', 'title', 'slug', 'image_small', 'description')
                ->orderBy('title', 'asc')
                ->get();
        });
        return view('destinations', compact('destination_image'));
    }
}
