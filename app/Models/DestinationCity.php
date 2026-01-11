<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationCity extends Model
{
    protected $fillable = [
        'country',
        'city',
        'is_popular',
        'is_auto_added',
    ];

    protected $casts = [
        'is_popular' => 'boolean',
        'is_auto_added' => 'boolean',
    ];

    /**
     * Получить курорты по стране
     */
    public static function getCitiesByCountry(string $country): array
    {
        return self::where('country', $country)
            ->orderBy('is_popular', 'desc')
            ->orderBy('city')
            ->pluck('city')
            ->toArray();
    }

    /**
     * Добавить город если его нет
     */
    public static function addCityIfNotExists(string $country, string $city): void
    {
        self::firstOrCreate(
            ['country' => $country, 'city' => $city],
            ['is_auto_added' => true]
        );
    }
}
