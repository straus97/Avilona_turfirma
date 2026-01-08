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
        $cacheTime = 3600; // 1 час
        
        // Сброс фильтров
        if ($request->query('reset')) {
            $request->session()->forget('countries_filter');
            return redirect()->route('countries.index');
        }
        
        // Формируем ключ кэша с учетом фильтров
        $filters = $request->only(['category', 'title']);
        $cacheKey = 'countries_index_' . md5(serialize($filters));
        
        $countries_image = Cache::remember($cacheKey, $cacheTime, function () use ($request) {
            $query = Countries_image::select('id', 'title', 'slug', 'image_small', 'category');
            
            // Применяем фильтры
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }
            
            if ($request->filled('title')) {
                $query->where('title', 'like', '%' . $request->title . '%');
            }
            
            return $query->orderBy('title', 'asc')->get();
        });
        
        // Список категорий
        $categories = Cache::remember('countries_categories', $cacheTime, function () {
            return Countries_image::distinct()->pluck('category')->filter()->sort()->values()->toArray();
        });
        
        return view('countries', compact('countries_image', 'categories'));
    }
}
