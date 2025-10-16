<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Countries_image;
use Illuminate\Support\Facades\Cache;

class AboutController extends Controller
{
    public function __invoke()
    {
        $cacheTime = 60; // Время кеширования в минутах
        $category_asia = Cache::remember('about_category_asia', $cacheTime, function () {
            return Countries_image::where('category', 'Азия')->get();
        });
        $category_africa = Cache::remember('about_category_africa', $cacheTime, function () {
            return Countries_image::where('category', 'Африка')->get();
        });
        $category_middle_east = Cache::remember('about_category_middle_east', $cacheTime, function () {
            return Countries_image::where('category', 'Ближний Восток')->get();
        });
        $category_europe = Cache::remember('about_category_europe', $cacheTime, function () {
            return Countries_image::where('category', 'Европа')->get();
        });
        $category_caribbean = Cache::remember('about_category_caribbean', $cacheTime, function () {
            return Countries_image::where('category', 'Карибский бассейн')->get();
        });
        return view(
            'company.about_company',
            compact('category_asia', 'category_africa', 'category_middle_east', 'category_europe', 'category_caribbean')
        );
    }
}
