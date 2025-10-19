<?php

use App\Http\Controllers\Api\TourSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Публичные API маршруты для поиска туров
Route::prefix('tours')->name('tours.')->group(function () {
    Route::get('/search', [TourSearchController::class, 'search'])->name('search');
    Route::get('/departure-cities', [TourSearchController::class, 'getDepartureCities'])->name('departure-cities');
    Route::get('/destination-countries', [TourSearchController::class, 'getDestinationCountries'])->name('destination-countries');
    Route::get('/resorts', [TourSearchController::class, 'getResortsByCountry'])->name('resorts');
});
