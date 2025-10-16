<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Award;
use Illuminate\Support\Facades\Cache;

class AwardsController extends Controller
{
    public function __invoke()
    {
        $cacheTime = 60; // Время кеширования в минутах
        $awards = Cache::remember('awards_all', $cacheTime, function () {
            return Award::all();
        });
        return view('company.awards', compact('awards'));
    }
}
