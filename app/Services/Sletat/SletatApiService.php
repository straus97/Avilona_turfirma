<?php

namespace App\Services\Sletat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SletatApiService
{
    private string $baseUrl = 'https://module.sletat.ru/Main.svc';
    private ?string $login = null;
    private ?string $password = null;
    private int $timeout = 30;

    public function __construct()
    {
        $this->login = config('services.sletat.login');
        $this->password = config('services.sletat.password');
    }

    /**
     * Авторизация в API Sletat.ru
     */
    public function authenticate(): bool
    {
        if (!$this->login || !$this->password) {
            Log::error('Sletat API credentials not configured');
            return false;
        }

        // Sletat.ru использует HTTP Basic Auth
        return true;
    }

    /**
     * Выполнить запрос к API
     */
    protected function makeRequest(string $method, array $params = []): array
    {
        $url = $this->baseUrl . '/' . $method;
        
        $response = Http::withBasicAuth($this->login, $this->password)
            ->timeout($this->timeout)
            ->get($url, $params);

        if (!$response->successful()) {
            Log::error("Sletat API request failed: {$response->body()}");
            throw new \Exception("API request failed: " . $response->body());
        }

        $data = $response->json();
        
        if (isset($data['IsError']) && $data['IsError']) {
            Log::error("Sletat API error: {$data['ErrorMessage']}");
            throw new \Exception("API error: " . $data['ErrorMessage']);
        }

        return $data;
    }

    /**
     * Получить список стран
     */
    public function getCountries(): array
    {
        $cacheKey = 'sletat_countries';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('GetCountries');
            return $response['GetCountriesResult']['Data'] ?? [];
        });
    }

    /**
     * Получить города вылета
     */
    public function getDepartCities(): array
    {
        $cacheKey = 'sletat_depart_cities';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('GetDepartCities');
            return $response['GetDepartCitiesResult']['Data'] ?? [];
        });
    }

    /**
     * Получить курорты по стране
     */
    public function getCities(int $countryId): array
    {
        $cacheKey = "sletat_cities_{$countryId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($countryId) {
            $response = $this->makeRequest('GetCities', ['countryId' => $countryId]);
            return $response['GetCitiesResult']['Data'] ?? [];
        });
    }

    /**
     * Получить отели по курорту
     */
    public function getHotels(int $cityId): array
    {
        $cacheKey = "sletat_hotels_{$cityId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($cityId) {
            $response = $this->makeRequest('GetHotels', ['cityId' => $cityId]);
            return $response['GetHotelsResult']['Data'] ?? [];
        });
    }

    /**
     * Получить категории отелей
     */
    public function getHotelStars(): array
    {
        $cacheKey = 'sletat_hotel_stars';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('GetHotelStars');
            return $response['GetHotelStarsResult']['Data'] ?? [];
        });
    }

    /**
     * Получить типы питания
     */
    public function getMeals(): array
    {
        $cacheKey = 'sletat_meals';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('GetMeals');
            return $response['GetMealsResult']['Data'] ?? [];
        });
    }

    /**
     * Получить туроператоров
     */
    public function getTourOperators(): array
    {
        $cacheKey = 'sletat_tour_operators';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('GetTourOperators');
            return $response['GetTourOperatorsResult']['Data'] ?? [];
        });
    }

    /**
     * Получить доступные даты тура
     */
    public function getTourDates(int $cityFromId, int $countryId): array
    {
        $cacheKey = "sletat_tour_dates_{$cityFromId}_{$countryId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($cityFromId, $countryId) {
            $response = $this->makeRequest('GetTourDates', [
                'cityFromId' => $cityFromId,
                'countryId' => $countryId
            ]);
            return $response['GetTourDatesResult']['Data'] ?? [];
        });
    }

    /**
     * Создать поисковый запрос
     */
    public function createSearchRequest(array $params): array
    {
        $response = $this->makeRequest('GetTours', $params);
        
        return [
            'requestId' => $response['GetToursResult']['Data']['RequestId'] ?? null,
            'executionTime' => $response['GetToursResult']['ExecutionTimeMs'] ?? 0,
        ];
    }

    /**
     * Получить статус поискового запроса
     */
    public function getLoadState(int $requestId): array
    {
        $response = $this->makeRequest('GetLoadState', ['requestId' => $requestId]);
        
        return $response['GetLoadStateResult']['Data'] ?? [];
    }

    /**
     * Получить результаты поиска
     */
    public function getSearchResults(int $requestId): array
    {
        $response = $this->makeRequest('GetTours', [
            'requestId' => $requestId,
            'updateResult' => '1'
        ]);
        
        return $response['GetToursResult']['Data'] ?? [];
    }

    /**
     * Актуализировать цену тура
     */
    public function actualizePrice(int $requestId, int $offerId, int $sourceId): array
    {
        $response = $this->makeRequest('ActualizePrice', [
            'requestId' => $requestId,
            'offerId' => $offerId,
            'sourceId' => $sourceId
        ]);
        
        return $response['ActualizePriceResult']['Data'] ?? [];
    }

    /**
     * Сохранить заказ тура
     */
    public function saveTourOrder(array $orderData): array
    {
        $response = $this->makeRequest('SaveTourOrder', $orderData);
        
        return $response['SaveTourOrderResult'] ?? [];
    }

    /**
     * Поиск туров с полными параметрами
     */
    public function searchTours(array $filters): array
    {
        // Создаем поисковый запрос
        $searchParams = $this->buildSearchParams($filters);
        $searchResult = $this->createSearchRequest($searchParams);
        
        if (!$searchResult['requestId']) {
            throw new \Exception('Failed to create search request');
        }

        $requestId = $searchResult['requestId'];
        
        // Ждем завершения поиска (максимум 2 минуты)
        $maxAttempts = 24; // 2 минуты по 5 секунд
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            sleep(5); // Ждем 5 секунд
            $attempt++;
            
            $loadState = $this->getLoadState($requestId);
            
            // Проверяем, завершен ли поиск
            if ($this->isSearchComplete($loadState)) {
                break;
            }
        }
        
        // Получаем результаты
        return $this->getSearchResults($requestId);
    }

    /**
     * Построить параметры поиска
     */
    protected function buildSearchParams(array $filters): array
    {
        $params = [];
        
        // Обязательные параметры
        if (isset($filters['cityFromId'])) {
            $params['cityFromId'] = $filters['cityFromId'];
        }
        
        if (isset($filters['countryId'])) {
            $params['countryId'] = $filters['countryId'];
        }
        
        if (isset($filters['checkIn'])) {
            $params['checkIn'] = $filters['checkIn'];
        }
        
        if (isset($filters['nights'])) {
            $params['nights'] = $filters['nights'];
        }
        
        if (isset($filters['adults'])) {
            $params['adults'] = $filters['adults'];
        }
        
        // Опциональные параметры
        if (isset($filters['children'])) {
            $params['children'] = $filters['children'];
        }
        
        if (isset($filters['cityId'])) {
            $params['cityId'] = $filters['cityId'];
        }
        
        if (isset($filters['hotelId'])) {
            $params['hotelId'] = $filters['hotelId'];
        }
        
        if (isset($filters['hotelStars'])) {
            $params['hotelStars'] = $filters['hotelStars'];
        }
        
        if (isset($filters['mealId'])) {
            $params['mealId'] = $filters['mealId'];
        }
        
        if (isset($filters['priceFrom'])) {
            $params['priceFrom'] = $filters['priceFrom'];
        }
        
        if (isset($filters['priceTo'])) {
            $params['priceTo'] = $filters['priceTo'];
        }
        
        return $params;
    }

    /**
     * Проверить, завершен ли поиск
     */
    protected function isSearchComplete(array $loadState): bool
    {
        // Логика проверки завершения поиска
        // В реальной реализации нужно анализировать статус каждого туроператора
        return true; // Упрощенная версия
    }
}
