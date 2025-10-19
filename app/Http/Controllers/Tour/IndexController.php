<?php

namespace App\Http\Controllers\Tour;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    /**
     * Поиск и отображение туров
     */
    public function __invoke(Request $request)
    {
        // Базовый запрос
        $query = Tour::query()->active();
        
        // Применяем фильтры
        if ($request->filled('departure_city')) {
            $query->where('departure_city', $request->departure_city);
        }
        
        if ($request->filled('destination_country')) {
            $query->where('destination_country', $request->destination_country);
        }
        
        if ($request->filled('destination_city')) {
            $query->where('destination_city', $request->destination_city);
        }
        
        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        
        if ($request->filled('end_date')) {
            $query->where('start_date', '<=', $request->end_date);
        }
        
        if ($request->filled('nights_min')) {
            $query->where('nights', '>=', $request->nights_min);
        }
        
        if ($request->filled('nights_max')) {
            $query->where('nights', '<=', $request->nights_max);
        }
        
        if ($request->filled('nights')) {
            $query->where('nights', $request->nights);
        }
        
        if ($request->filled('hotel_stars')) {
            $query->where('hotel_stars', $request->hotel_stars);
        }
        
        if ($request->filled('meal_type')) {
            $query->where('meal_type', $request->meal_type);
        }
        
        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }
        
        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }
        
        // Сортировка
        $sortBy = $request->get('sort_by', 'popular');
        
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'rating':
                $query->orderBy('hotel_stars', 'desc');
                break;
            case 'popular':
            default:
                // Сначала горящие, потом по дате создания
                $query->orderBy('is_hot_deal', 'desc')
                      ->orderBy('created_at', 'desc');
                break;
        }
        
        // Пагинация
        $tours = $query->paginate(12)->withQueryString();
        
        // Данные для фильтров
        $departureCities = Tour::active()
            ->select('departure_city')
            ->distinct()
            ->pluck('departure_city');
            
        $destinationCountries = Tour::active()
            ->select('destination_country')
            ->distinct()
            ->pluck('destination_country');
        
        return view('tours.index', compact(
            'tours',
            'departureCities',
            'destinationCountries'
        ));
    }
}
