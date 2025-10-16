<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Countries_image extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $table = 'countries_images';
    protected $guarded = []; // Разрешаем массовое назначение всех полей

    /**
     * Получить параметры для создания слага.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }
}

