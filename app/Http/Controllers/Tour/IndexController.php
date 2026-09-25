<?php

namespace App\Http\Controllers\Tour;

use App\Http\Controllers\Controller;

class IndexController extends Controller
{
    /**
     * Страница поиска туров (E5-A1).
     *
     * Поиск выполняет встраиваемый модуль Tourvisor на клиенте; серверной
     * логики поиска, обращений к БД и внешним API здесь нет.
     */
    public function __invoke()
    {
        return view('tours.index');
    }
}
