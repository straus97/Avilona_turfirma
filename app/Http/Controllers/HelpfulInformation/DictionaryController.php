<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class DictionaryController extends Controller
{
    public function __invoke()
    {
        $cacheKey = 'travel_dictionary_page';
        $cacheTime = 60; // Время кеширования в минутах
        if (Cache::has($cacheKey)) {
            return response(Cache::get($cacheKey));
        }
        $response = view('helpful_information.travel_dictionary')->render();
        Cache::put($cacheKey, $response, $cacheTime);
        return response($response);
    }
}
