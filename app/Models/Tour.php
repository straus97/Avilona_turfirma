<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tour extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'price_child',
        'departure_city',
        'destination_country',
        'destination_city',
        'start_date',
        'end_date',
        'nights',
        'hotel_name',
        'hotel_stars',
        'meal_type',
        'max_tourists',
        'available_seats',
        'facilities',
        'image_url',
        'gallery',
        'is_active',
        'is_hot_deal',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
        'price_child' => 'decimal:2',
        'is_active' => 'boolean',
        'is_hot_deal' => 'boolean',
        'facilities' => 'array',
        'gallery' => 'array',
    ];

    /**
     * Boot method для автоматической генерации slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tour) {
            if (empty($tour->slug)) {
                $tour->slug = Str::slug($tour->title);
            }
            
            // Если available_seats не указаны, ставим max_tourists
            if (is_null($tour->available_seats)) {
                $tour->available_seats = $tour->max_tourists;
            }
        });
    }

    /**
     * Отношения
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Reviews::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHotDeals($query)
    {
        return $query->where('is_hot_deal', true)->where('is_active', true);
    }

    public function scopeByDestination($query, $country)
    {
        return $query->where('destination_country', $country);
    }

    public function scopeByDepartureCity($query, $city)
    {
        return $query->where('departure_city', $city);
    }

    public function scopeByPriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    public function scopeByDates($query, $startDate, $endDate)
    {
        return $query->where('start_date', '>=', $startDate)
                     ->where('end_date', '<=', $endDate);
    }

    public function scopeByHotelStars($query, $stars)
    {
        return $query->where('hotel_stars', $stars);
    }

    public function scopeAvailable($query)
    {
        return $query->where('available_seats', '>', 0);
    }

    public function scopeSearch($query, array $filters)
    {
        return $query
            ->when($filters['departure_city'] ?? null, function ($q, $city) {
                $q->where('departure_city', $city);
            })
            ->when($filters['destination_country'] ?? null, function ($q, $country) {
                $q->where('destination_country', $country);
            })
            ->when($filters['start_date'] ?? null, function ($q, $date) {
                $q->where('start_date', '>=', $date);
            })
            ->when($filters['nights'] ?? null, function ($q, $nights) {
                $q->where('nights', $nights);
            })
            ->when($filters['price_min'] ?? null, function ($q, $min) {
                $q->where('price', '>=', $min);
            })
            ->when($filters['price_max'] ?? null, function ($q, $max) {
                $q->where('price', '<=', $max);
            })
            ->when($filters['hotel_stars'] ?? null, function ($q, $stars) {
                $q->where('hotel_stars', $stars);
            })
            ->when($filters['meal_type'] ?? null, function ($q, $type) {
                $q->where('meal_type', $type);
            });
    }

    /**
     * Accessors
     */
    public function getDurationAttribute()
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 0, ',', ' ') . ' ₽';
    }

    public function getIsAvailableAttribute()
    {
        return $this->available_seats > 0;
    }

    public function getAvailabilityPercentAttribute()
    {
        if ($this->max_tourists == 0) return 0;
        return round(($this->available_seats / $this->max_tourists) * 100);
    }

    /**
     * Методы
     */
    public function decreaseAvailableSeats($count = 1)
    {
        if ($this->available_seats >= $count) {
            $this->decrement('available_seats', $count);
            return true;
        }
        return false;
    }

    public function increaseAvailableSeats($count = 1)
    {
        if ($this->available_seats + $count <= $this->max_tourists) {
            $this->increment('available_seats', $count);
            return true;
        }
        return false;
    }
}
