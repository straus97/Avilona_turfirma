<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Best_offer extends Model
{
    use HasFactory;
    use SoftDeletes; //использование "мягкое" удаление, чтобы потом можно было восстановить

    //public $somePropperty; //добавление своих свойств
    protected $table = 'best_offers';
    protected $guarded = []; //разрешаем подключение к базе и добавление данных. То есть мы перестаем защищать базу
//    protected $guarded = false; //тоже самое что и выше
}
