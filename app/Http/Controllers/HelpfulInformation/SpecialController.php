<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\OurClient;
use Illuminate\Support\Facades\Cache;

class SpecialController extends Controller
{
    public function __invoke($slug)
    {
        $cacheKey = 'special_' . $slug;
        $cacheTime = 60; // Время кеширования в минутах
        $id_special = Cache::remember($cacheKey, $cacheTime, function () use ($slug) {
            return OurClient::where('slug', $slug)->firstOrFail();
        });
        $title = $id_special->title . " | avilona.ru";
        $meta_description = "Специальные предложения от туристической фирмы Авилона. Узнайте больше о " . $id_special->title . ". Выгодные предложения, акции и скидки от туристической фирмы Авилона. Ознакомьтесь с нашими специальными предложениями и примите участие в розыгрышах.";
        $meta_keywords = "специальные предложения, акции, скидки, розыгрыши, туристическая фирма Авилона, выгодные предложения, " . $id_special->title;
        return view(
            'helpful_information.show_special',
            compact('id_special', 'title', 'meta_description', 'meta_keywords')
        );
    }
}
