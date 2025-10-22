<?php

namespace App\Services\TourOperators;

use Carbon\Carbon;

class CoralTravelService extends BaseTourOperatorService
{
    /**
     * Получить туры от Coral Travel
     */
    public function fetchTours(array $filters = []): array
    {
        $params = $this->buildSearchParams($filters);
        
        $response = $this->makeRequest('/api/tours/search', $params);
        
        return $response['data']['tours'] ?? [];
    }

    /**
     * Построить параметры поиска для API
     */
    protected function buildSearchParams(array $filters): array
    {
        $params = [];

        if (isset($filters['departure_city'])) {
            $params['departure_city'] = $filters['departure_city'];
        }

        if (isset($filters['destination_country'])) {
            $params['destination_country'] = $filters['destination_country'];
        }

        if (isset($filters['start_date'])) {
            $params['check_in'] = $filters['start_date'];
        }

        if (isset($filters['nights'])) {
            $params['nights'] = $filters['nights'];
        }

        if (isset($filters['adults'])) {
            $params['adults'] = $filters['adults'];
        }

        if (isset($filters['children'])) {
            $params['children'] = $filters['children'];
        }

        if (isset($filters['price_min'])) {
            $params['price_from'] = $filters['price_min'];
        }

        if (isset($filters['price_max'])) {
            $params['price_to'] = $filters['price_max'];
        }

        if (isset($filters['hotel_stars'])) {
            $params['hotel_category'] = $filters['hotel_stars'];
        }

        if (isset($filters['meal_type'])) {
            $params['meal_type'] = $filters['meal_type'];
        }

        return $params;
    }

    /**
     * Нормализовать данные тура
     */
    protected function normalizeTourData(array $tourData): array
    {
        return [
            'external_id' => $tourData['id'] ?? null,
            'title' => $tourData['hotel_name'] ?? 'Тур от Coral Travel',
            'description' => $tourData['description'] ?? '',
            'price' => $tourData['price'] ?? 0,
            'price_child' => $tourData['price_child'] ?? null,
            'departure_city' => $tourData['departure_city'] ?? '',
            'destination_country' => $tourData['destination_country'] ?? '',
            'destination_city' => $tourData['destination_city'] ?? '',
            'start_date' => $this->parseDate($tourData['check_in'] ?? null),
            'end_date' => $this->parseDate($tourData['check_out'] ?? null),
            'nights' => $tourData['nights'] ?? 0,
            'hotel_name' => $tourData['hotel_name'] ?? '',
            'hotel_stars' => $tourData['hotel_category'] ?? null,
            'hotel_rating' => $tourData['hotel_rating'] ?? null,
            'meal_type' => $tourData['meal_type'] ?? null,
            'beach_line' => $tourData['beach_line'] ?? null,
            'tour_operator' => 'Coral Travel',
            'resort' => $tourData['resort'] ?? $tourData['destination_city'] ?? '',
            'max_tourists' => $tourData['max_tourists'] ?? 10,
            'available_seats' => $tourData['available_seats'] ?? 10,
            'facilities' => $tourData['facilities'] ?? [],
            'included_services' => $tourData['included_services'] ?? '',
            'not_included_services' => $tourData['not_included_services'] ?? '',
            'image_url' => $tourData['image_url'] ?? null,
            'gallery' => $tourData['gallery'] ?? [],
            'is_active' => true,
            'is_hot_deal' => $tourData['is_hot_deal'] ?? false,
            'is_charter' => $tourData['is_charter'] ?? true,
            'is_direct' => $tourData['is_direct'] ?? true,
            'adults' => $tourData['adults'] ?? 2,
            'children' => $tourData['children'] ?? 0,
            'children_ages' => $tourData['children_ages'] ?? [],
        ];
    }

    /**
     * Парсить дату
     */
    protected function parseDate($date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить поддерживаемые страны
     */
    public function getSupportedCountries(): array
    {
        return [
            'Турция', 'Египет', 'ОАЭ', 'Таиланд', 'Испания', 'Греция',
            'Кипр', 'Болгария', 'Хорватия', 'Черногория', 'Тунис',
            'Марокко', 'Доминикана', 'Куба', 'Мальдивы', 'Шри-Ланка'
        ];
    }

    /**
     * Получить поддерживаемые города отправления
     */
    public function getSupportedDepartureCities(): array
    {
        return [
            'Москва', 'Санкт-Петербург', 'Екатеринбург', 'Новосибирск',
            'Казань', 'Краснодар', 'Сочи', 'Ростов-на-Дону', 'Самара',
            'Уфа', 'Пермь', 'Воронеж', 'Волгоград', 'Красноярск'
        ];
    }
}
