<?php

use App\Http\Controllers\Api\TourvisorWebhookController;
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

// E5-A4: прежние публичные API локального поиска туров (/api/tours/*) и прокси Sletat
// (/api/sletat/*) удалены: поиск туров выполняет модуль Tourvisor на /tours, а вызывающего
// кода у этих маршрутов не осталось.

// Tourvisor: уведомление об обращении (GET …/{webhookToken}?id=…&type=…). Без сессии/CSRF
// (группа api), без авторизации пользователя. Секретный токен пути (TOURVISOR_WEBHOOK_TOKEN)
// проверяется ДО контроллера; данные обращения загружаются отдельным серверным запросом.
// Порядок важен: сначала лимит по IP (считает и неверные токены), затем проверка токена.
Route::get('/webhooks/tourvisor/inquiries/{webhookToken}', TourvisorWebhookController::class)
    ->where('webhookToken', '[A-Za-z0-9_-]{1,128}')
    ->withoutMiddleware('throttle:api')
    ->middleware(['throttle:tourvisor-webhook', 'tourvisor.webhook'])
    ->name('webhooks.tourvisor.inquiries');
