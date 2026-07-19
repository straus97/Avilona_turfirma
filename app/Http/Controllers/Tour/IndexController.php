<?php

namespace App\Http\Controllers\Tour;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IndexController extends Controller
{
    /**
     * Поиск и отображение туров
     */
    public function __invoke(Request $request)
    {
        // Базовый запрос с выборкой только нужных полей
        $query = Tour::query()
            ->active()
            ->select([
                'id', 'title', 'departure_city', 'destination_country',
                'destination_city', 'start_date', 'end_date', 'nights', 'price',
                'hotel_name', 'hotel_stars', 'meal_type', 'image_url',
                'is_hot_deal', 'adults', 'children', 'created_at'
            ]);
        
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

        // Обработка курортов из галочек
        if ($request->filled('resorts')) {
            $query->whereIn('destination_city', $request->resorts);
        }
        
        // Фильтрация по датам вылета
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
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
        
        // Фильтрация по количеству туристов
        if ($request->filled('adults')) {
            $query->where('adults', '>=', $request->adults);
        }
        
        if ($request->filled('children')) {
            $query->where('children', '>=', $request->children);
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
        
        // Фильтр по туроператорам
        if ($request->filled('tour_operators')) {
            $query->whereIn('tour_operator', $request->tour_operators);
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
        
        // Пагинация (12 туров на страницу)
        $tours = $query->paginate(12)->withQueryString();
        
        // Данные для фильтров (кэшируем на 1 час)
        $departureCities = Cache::remember('tour_departure_cities', 3600, function () {
            return Tour::active()
                ->select('departure_city')
                ->distinct()
                ->orderBy('departure_city')
                ->pluck('departure_city');
        });
            
        $destinationCountries = Cache::remember('tour_destination_countries', 3600, function () {
            return Tour::active()
                ->select('destination_country')
                ->distinct()
                ->orderBy('destination_country')
                ->pluck('destination_country');
        });
        
        return view('tours.index', compact(
            'tours',
            'departureCities',
            'destinationCountries'
        ));
    }
}
