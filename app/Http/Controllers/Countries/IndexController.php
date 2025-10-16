<?php

namespace App\Http\Controllers\Countries;

use App\Http\Controllers\Controller;
use App\Http\Filters\CategoryFilter;
use App\Http\Filters\TitleFilter;
use App\Models\Countries_image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $cacheKey = 'countries_index';
        $cacheTime = 60; // Время кеширования в минутах
        $query_reset = $request->query();
        if (isset($query_reset['reset'])) {
            $request->session()->forget('countries_filter');
            return redirect()->route('countries.index');
        }
        $countries_image = Cache::remember($cacheKey, $cacheTime, function () use ($request) {
            $query = Countries_image::query();
            // применяем фильтры
            (new CategoryFilter($request->all()))->apply($query);
            (new TitleFilter($request->all()))->apply($query);
            // получаем отфильтрованные записи
            return $query->orderBy('title', 'asc')->get();
        });
        // формируем список категорий
        $categories = Cache::remember('countries_categories', $cacheTime, function () {
            return Countries_image::pluck('category')->unique()->toArray();
        });
        return view('countries', compact('countries_image', 'categories'));
    }
}
