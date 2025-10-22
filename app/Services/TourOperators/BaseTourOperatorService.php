<?php

namespace App\Services\TourOperators;

use App\Models\Tour;
use App\Models\TourOperator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseTourOperatorService
{
    protected TourOperator $operator;
    protected array $config;

    public function __construct(TourOperator $operator)
    {
        $this->operator = $operator;
        $this->config = $operator->getApiConfig();
    }

    /**
     * Получить туры от туроператора
     */
    abstract public function fetchTours(array $filters = []): array;

    /**
     * Синхронизировать туры
     */
    public function syncTours(array $filters = []): bool
    {
        try {
            Log::info("Starting sync for operator: {$this->operator->name}");

            $toursData = $this->fetchTours($filters);
            
            if (empty($toursData)) {
                Log::warning("No tours received from operator: {$this->operator->name}");
                $this->operator->markSyncSuccess();
                return true;
            }

            $syncedCount = 0;
            foreach ($toursData as $tourData) {
                if ($this->createOrUpdateTour($tourData)) {
                    $syncedCount++;
                }
            }

            Log::info("Synced {$syncedCount} tours from operator: {$this->operator->name}");
            $this->operator->markSyncSuccess();

            return true;

        } catch (\Exception $e) {
            Log::error("Sync failed for operator {$this->operator->name}: " . $e->getMessage());
            $this->operator->markSyncError($e->getMessage());
            return false;
        }
    }

    /**
     * Создать или обновить тур
     */
    protected function createOrUpdateTour(array $tourData): bool
    {
        try {
            // Нормализуем данные тура
            $normalizedData = $this->normalizeTourData($tourData);

            // Ищем существующий тур по уникальному идентификатору
            $existingTour = Tour::where('tour_operator', $this->operator->name)
                ->where('external_id', $normalizedData['external_id'] ?? null)
                ->first();

            if ($existingTour) {
                // Обновляем существующий тур
                $existingTour->update($normalizedData);
                Log::debug("Updated tour: {$existingTour->id}");
            } else {
                // Создаем новый тур
                $tour = Tour::create($normalizedData);
                Log::debug("Created tour: {$tour->id}");
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to create/update tour: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Нормализовать данные тура для нашей базы
     */
    abstract protected function normalizeTourData(array $tourData): array;

    /**
     * Выполнить HTTP запрос к API
     */
    protected function makeRequest(string $endpoint, array $params = []): array
    {
        $url = rtrim($this->operator->api_endpoint, '/') . '/' . ltrim($endpoint, '/');
        
        $headers = $this->getAuthHeaders();
        
        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception("API request failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Получить заголовки авторизации
     */
    protected function getAuthHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->operator->api_key) {
            $headers['Authorization'] = 'Bearer ' . $this->operator->api_key;
        }

        return $headers;
    }

    /**
     * Получить список поддерживаемых стран
     */
    abstract public function getSupportedCountries(): array;

    /**
     * Получить список поддерживаемых городов отправления
     */
    abstract public function getSupportedDepartureCities(): array;
}
