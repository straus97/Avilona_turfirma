<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourSearchController extends Controller
{
    /**
     * API поиск туров
     */
    public function search(Request $request): JsonResponse
    {
        // Валидация
        $validated = $request->validate([
            'departure_city' => 'nullable|string|max:100',
            'destination_country' => 'nullable|string|max:100',
            'destination_city' => 'nullable|string|max:100',
            'start_date' => 'nullable|date|after:today',
            'end_date' => 'nullable|date|after:start_date',
            'nights' => 'nullable|integer|min:1|max:30',
            'adults' => 'nullable|integer|min:1|max:10',
            'children' => 'nullable|integer|min:0|max:10',
            'hotel_stars' => 'nullable|integer|min:1|max:5',
            'meal_type' => 'nullable|string|in:BB,HB,FB,AI,UAI',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'beach_line' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0|max:10',
            'hotel_name' => 'nullable|string',
            'tour_operator' => 'nullable|string',
            'charter' => 'nullable|boolean',
            'direct_flight' => 'nullable|boolean',
            'instant_confirmation' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:popular,price_asc,price_desc,rating',
            'page' => 'nullable|integer|min:1',
        ]);
        
        // Базовый запрос
        $query = Tour::query()->active();
        
        // Применяем фильтры через scope
        $searchFilters = $validated;

        if (! empty($validated['tour_operator'])) {
            $searchFilters['tour_operators'] = [$validated['tour_operator']];
        }

        if (isset($validated['nights'])) {
            $searchFilters['nights_min'] = $validated['nights'];
            $searchFilters['nights_max'] = $validated['nights'];
        }

        if (isset($validated['rating'])) {
            $searchFilters['hotel_rating'] = $validated['rating'];
        }

        if (isset($validated['charter'])) {
            $searchFilters['is_charter'] = $validated['charter'];
        }

        $query->search($searchFilters);
        
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
                $query->orderBy('is_hot_deal', 'desc')
                      ->orderBy('created_at', 'desc');
                break;
        }
        
        // Пагинация
        $tours = $query->paginate(12);
        
        return response()->json([
            'success' => true,
            'data' => $tours->items(),
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
                'from' => $tours->firstItem(),
                'to' => $tours->lastItem(),
            ],
            'filters' => $validated,
        ]);
    }
    
    /**
     * Получить список городов отправления
     */
    public function getDepartureCities(): JsonResponse
    {
        $cities = Tour::active()
            ->select('departure_city')
            ->distinct()
            ->orderBy('departure_city')
            ->pluck('departure_city');
            
        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }
    
    /**
     * Получить список стран назначения
     */
    public function getDestinationCountries(): JsonResponse
    {
        $countries = Tour::active()
            ->select('destination_country')
            ->distinct()
            ->orderBy('destination_country')
            ->pluck('destination_country');
            
        return response()->json([
            'success' => true,
            'data' => $countries,
        ]);
    }
    
    /**
     * Получить курорты по стране
     */
    public function getResortsByCountry(Request $request): JsonResponse
    {
        $request->validate([
            'country' => 'required|string',
        ]);
        
        $resorts = Tour::active()
            ->where('destination_country', $request->country)
            ->select('destination_city')
            ->distinct()
            ->orderBy('destination_city')
            ->pluck('destination_city')
            ->filter();
            
        return response()->json([
            'success' => true,
            'data' => $resorts,
        ]);
    }
}
