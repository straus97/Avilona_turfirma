<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke()
    {
        $cacheKey = 'contacts_page';
        $cacheTime = 60; // Время кеширования в минутах
        if (Cache::has($cacheKey)) {
            return response(Cache::get($cacheKey));
        }
        $response = view('contacts')->render();
        Cache::put($cacheKey, $response, $cacheTime);
        return response($response);
    }
}
