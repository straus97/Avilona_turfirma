<?php

use App\Http\Controllers\Api\TourSearchController;
use App\Http\Controllers\Api\SletatController;
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

// API маршруты для Sletat.ru
Route::prefix('sletat')->name('sletat.')->group(function () {
    // Справочники
    Route::get('/countries', [SletatController::class, 'getCountries'])->name('countries');
    Route::get('/depart-cities', [SletatController::class, 'getDepartCities'])->name('depart-cities');
    Route::get('/cities', [SletatController::class, 'getCities'])->name('cities');
    Route::get('/hotels', [SletatController::class, 'getHotels'])->name('hotels');
    Route::get('/hotel-stars', [SletatController::class, 'getHotelStars'])->name('hotel-stars');
    Route::get('/meals', [SletatController::class, 'getMeals'])->name('meals');
    Route::get('/tour-operators', [SletatController::class, 'getTourOperators'])->name('tour-operators');
    Route::get('/tour-dates', [SletatController::class, 'getTourDates'])->name('tour-dates');
    
    // Поиск туров
    Route::post('/search', [SletatController::class, 'searchTours'])->name('search');
    Route::post('/search/create', [SletatController::class, 'createSearchRequest'])->name('search.create');
    Route::get('/search/state', [SletatController::class, 'getLoadState'])->name('search.state');
    Route::get('/search/results', [SletatController::class, 'getSearchResults'])->name('search.results');
});
