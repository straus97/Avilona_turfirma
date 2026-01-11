<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DestinationCity;
use Illuminate\Http\Request;

class DestinationCityController extends Controller
{
    /**
     * Получить курорты/города по стране
     */
    public function getCitiesByCountry(Request $request)
    {
        $country = $request->input('country');
        
        if (empty($country)) {
            return response()->json([]);
        }

        $cities = DestinationCity::getCitiesByCountry($country);

        return response()->json($cities);
    }
}
