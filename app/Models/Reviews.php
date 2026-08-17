<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reviews extends Model
{
    use HasFactory;
    use SoftDeletes; //использование "мягкое" удаление, чтобы потом можно было восстановить

    //public $somePropperty; //добавление своих свойств
    protected $table = 'reviews';
    protected $guarded = []; //разрешаем подключение к базе и добавление данных. То есть мы перестаем защищать базу
//    protected $guarded = false; //тоже самое что и выше

    protected $casts = [
        'is_moderator_edited' => 'boolean',
        'moderator_edited_at' => 'datetime',
    ];

    public function reviewConsent(): HasOne
    {
        return $this->hasOne(ReviewConsent::class, 'review_id');
    }
}
