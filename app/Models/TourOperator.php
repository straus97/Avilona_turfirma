<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TourOperator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'api_endpoint',
        'api_key',
        'api_secret',
        'api_config',
        'is_active',
        'auto_sync',
        'sync_interval',
        'last_sync_at',
        'last_successful_sync_at',
        'sync_errors_count',
        'last_error',
    ];

    protected $casts = [
        'api_config' => 'array',
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_successful_sync_at' => 'datetime',
    ];

    /**
     * Boot method для автоматической генерации slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($operator) {
            if (empty($operator->slug)) {
                $operator->slug = Str::slug($operator->name);
            }
        });
    }

    /**
     * Отношения
     */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'tour_operator', 'name');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAutoSync($query)
    {
        return $query->where('auto_sync', true);
    }

    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_sync_at')
              ->orWhereRaw('last_sync_at < DATE_SUB(NOW(), INTERVAL sync_interval MINUTE)');
        });
    }

    /**
     * Методы
     */
    public function canSync(): bool
    {
        if (!$this->is_active || !$this->auto_sync) {
            return false;
        }

        if (!$this->last_sync_at) {
            return true;
        }

        return $this->last_sync_at->addMinutes($this->sync_interval)->isPast();
    }

    public function markSyncSuccess(): void
    {
        $this->update([
            'last_sync_at' => now(),
            'last_successful_sync_at' => now(),
            'sync_errors_count' => 0,
            'last_error' => null,
        ]);
    }

    public function markSyncError(string $error): void
    {
        $this->update([
            'last_sync_at' => now(),
            'sync_errors_count' => $this->sync_errors_count + 1,
            'last_error' => $error,
        ]);
    }

    public function getApiConfig(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->api_config ?? [];
        }

        return data_get($this->api_config, $key, $default);
    }

    public function setApiConfig(string $key, $value): void
    {
        $config = $this->api_config ?? [];
        data_set($config, $key, $value);
        $this->update(['api_config' => $config]);
    }
}
