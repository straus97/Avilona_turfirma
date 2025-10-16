<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Исключаем роуты, которые не должны кешироваться
        if ($this->shouldNotCache($request)) {
            return $next($request);
        }

        $cacheKey = $request->fullUrl();

        if (Cache::has($cacheKey)) {
            return response(Cache::get($cacheKey));
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, $response->getContent(), now()->addMinutes(60)); // Кешировать на 60 минут
        }

        return $response;
    }

    protected function shouldNotCache(Request $request)
    {
        // Исключать если пользователь авторизован, или URL содержит 'profile', 'logout'
        return $request->user() || $request->is('profile*') || $request->is('logout');
    }
}
