<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;
use App\Models\OurClient;
use Illuminate\Support\Facades\Cache;

class ClientsController extends Controller
{
    public function __invoke()
    {
        $cacheKey = 'clients_page_' . request('page', 1); // Учитываем пагинацию
        $cacheTime = 60; // Время кеширования в минутах
        $special = Cache::remember($cacheKey, $cacheTime, function () {
            return OurClient::paginate(6); // показать 6 первые (актуальные) записи
        });
        return view('helpful_information.for_our_clients', compact('special'));
    }
}
