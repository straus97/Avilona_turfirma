<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sletat\SletatApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SletatController extends Controller
{
    private SletatApiService $sletatService;

    public function __construct(SletatApiService $sletatService)
    {
        $this->sletatService = $sletatService;
    }

    /**
     * Получить список стран
     */
    public function getCountries(): JsonResponse
    {
        try {
            $countries = $this->sletatService->getCountries();
            
            return response()->json([
                'success' => true,
                'data' => $countries,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get countries from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get countries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить города вылета
     */
    public function getDepartCities(): JsonResponse
    {
        try {
            $cities = $this->sletatService->getDepartCities();
            
            return response()->json([
                'success' => true,
                'data' => $cities,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get depart cities from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get depart cities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить курорты по стране
     */
    public function getCities(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => 'required|integer',
        ]);

        try {
            $cities = $this->sletatService->getCities($request->country_id);
            
            return response()->json([
                'success' => true,
                'data' => $cities,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get cities from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить отели по курорту
     */
    public function getHotels(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'required|integer',
        ]);

        try {
            $hotels = $this->sletatService->getHotels($request->city_id);
            
            return response()->json([
                'success' => true,
                'data' => $hotels,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get hotels from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get hotels',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить категории отелей
     */
    public function getHotelStars(): JsonResponse
    {
        try {
            $stars = $this->sletatService->getHotelStars();
            
            return response()->json([
                'success' => true,
                'data' => $stars,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get hotel stars from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get hotel stars',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить типы питания
     */
    public function getMeals(): JsonResponse
    {
        try {
            $meals = $this->sletatService->getMeals();
            
            return response()->json([
                'success' => true,
                'data' => $meals,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get meals from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get meals',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить туроператоров
     */
    public function getTourOperators(): JsonResponse
    {
        try {
            $operators = $this->sletatService->getTourOperators();
            
            return response()->json([
                'success' => true,
                'data' => $operators,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get tour operators from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tour operators',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить доступные даты тура
     */
    public function getTourDates(Request $request): JsonResponse
    {
        $request->validate([
            'city_from_id' => 'required|integer',
            'country_id' => 'required|integer',
        ]);

        try {
            $dates = $this->sletatService->getTourDates(
                $request->city_from_id,
                $request->country_id
            );
            
            return response()->json([
                'success' => true,
                'data' => $dates,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get tour dates from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tour dates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск туров
     */
    public function searchTours(Request $request): JsonResponse
    {
        $request->validate([
            'city_from_id' => 'required|integer',
            'country_id' => 'required|integer',
            'check_in' => 'required|date',
            'nights' => 'required|integer|min:1|max:30',
            'adults' => 'required|integer|min:1|max:10',
            'children' => 'integer|min:0|max:10',
        ]);

        try {
            $filters = $request->only([
                'city_from_id', 'country_id', 'check_in', 'nights',
                'adults', 'children', 'city_id', 'hotel_id',
                'hotel_stars', 'meal_id', 'price_from', 'price_to'
            ]);

            $results = $this->sletatService->searchTours($filters);
            
            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search tours from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search tours',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать поисковый запрос (асинхронно)
     */
    public function createSearchRequest(Request $request): JsonResponse
    {
        $request->validate([
            'city_from_id' => 'required|integer',
            'country_id' => 'required|integer',
            'check_in' => 'required|date',
            'nights' => 'required|integer|min:1|max:30',
            'adults' => 'required|integer|min:1|max:10',
            'children' => 'integer|min:0|max:10',
        ]);

        try {
            $filters = $request->only([
                'city_from_id', 'country_id', 'check_in', 'nights',
                'adults', 'children', 'city_id', 'hotel_id',
                'hotel_stars', 'meal_id', 'price_from', 'price_to'
            ]);

            $result = $this->sletatService->createSearchRequest($filters);
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create search request in Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create search request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить статус поискового запроса
     */
    public function getLoadState(Request $request): JsonResponse
    {
        $request->validate([
            'request_id' => 'required|integer',
        ]);

        try {
            $state = $this->sletatService->getLoadState($request->request_id);
            
            return response()->json([
                'success' => true,
                'data' => $state,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get load state from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get load state',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить результаты поиска
     */
    public function getSearchResults(Request $request): JsonResponse
    {
        $request->validate([
            'request_id' => 'required|integer',
        ]);

        try {
            $results = $this->sletatService->getSearchResults($request->request_id);
            
            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get search results from Sletat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get search results',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
