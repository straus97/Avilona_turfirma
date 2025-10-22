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
        'hotel_rating',
        'meal_type',
        'beach_line',
        'tour_operator',
        'resort',
        'max_tourists',
        'available_seats',
        'facilities',
        'included_services',
        'not_included_services',
        'image_url',
        'gallery',
        'is_active',
        'is_hot_deal',
        'is_charter',
        'is_direct',
        'adults',
        'children',
        'children_ages',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
        'price_child' => 'decimal:2',
        'hotel_rating' => 'decimal:1',
        'is_active' => 'boolean',
        'is_hot_deal' => 'boolean',
        'is_charter' => 'boolean',
        'is_direct' => 'boolean',
        'facilities' => 'array',
        'gallery' => 'array',
        'children_ages' => 'array',
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

    public function scopeByHotelRating($query, $rating)
    {
        return $query->where('hotel_rating', '>=', $rating);
    }

    public function scopeByTourOperator($query, $operator)
    {
        return $query->where('tour_operator', $operator);
    }

    public function scopeByBeachLine($query, $line)
    {
        return $query->where('beach_line', $line);
    }

    public function scopeByResort($query, $resort)
    {
        return $query->where('resort', $resort);
    }

    public function scopeCharterFlights($query)
    {
        return $query->where('is_charter', true);
    }

    public function scopeDirectFlights($query)
    {
        return $query->where('is_direct', true);
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
            ->when($filters['destination_city'] ?? null, function ($q, $city) {
                $q->where('destination_city', $city);
            })
            ->when($filters['resort'] ?? null, function ($q, $resort) {
                $q->where('resort', $resort);
            })
            ->when($filters['start_date'] ?? null, function ($q, $date) {
                $q->where('start_date', '>=', $date);
            })
            ->when($filters['end_date'] ?? null, function ($q, $date) {
                $q->where('end_date', '<=', $date);
            })
            ->when($filters['nights_min'] ?? null, function ($q, $nights) {
                $q->where('nights', '>=', $nights);
            })
            ->when($filters['nights_max'] ?? null, function ($q, $nights) {
                $q->where('nights', '<=', $nights);
            })
            ->when($filters['adults'] ?? null, function ($q, $adults) {
                $q->where('adults', '>=', $adults);
            })
            ->when($filters['children'] ?? null, function ($q, $children) {
                $q->where('children', '>=', $children);
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
            ->when($filters['hotel_rating'] ?? null, function ($q, $rating) {
                $q->where('hotel_rating', '>=', $rating);
            })
            ->when($filters['meal_type'] ?? null, function ($q, $type) {
                $q->where('meal_type', $type);
            })
            ->when($filters['beach_line'] ?? null, function ($q, $line) {
                $q->where('beach_line', $line);
            })
            ->when($filters['tour_operators'] ?? null, function ($q, $operators) {
                if (is_array($operators) && !empty($operators)) {
                    $q->whereIn('tour_operator', $operators);
                }
            })
            ->when($filters['is_charter'] ?? null, function ($q, $charter) {
                $q->where('is_charter', $charter);
            })
            ->when($filters['is_direct'] ?? null, function ($q, $direct) {
                $q->where('is_direct', $direct);
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
