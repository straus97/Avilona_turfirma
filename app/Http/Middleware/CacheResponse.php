<?php

namespace App\Http\Middleware;

use App\Support\CookieConsent;
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

        $cacheKey = $this->cacheKey($request);

        if (Cache::has($cacheKey)) {
            return response(Cache::get($cacheKey));
        }

        $response = $next($request);

        if ($response->isSuccessful() && ! $this->containsSessionToken($request, $response->getContent())) {
            Cache::put($cacheKey, $response->getContent(), now()->addMinutes(60)); // Кешировать на 60 минут
        }

        return $response;
    }

    /**
     * NOV-PUBLIC-01: страница с формой несёт CSRF-токен текущей сессии. Кэш общий
     * для всех посетителей, поэтому такой ответ нельзя переотдавать другим: их POST
     * получил бы чужой токен и был бы отклонён (HTTP 419). Страницы с токеном
     * (например /contacts) всегда рендерятся заново для каждого посетителя.
     */
    protected function containsSessionToken(Request $request, mixed $content): bool
    {
        if (! $request->hasSession() || ! is_string($content)) {
            return false;
        }

        $token = $request->session()->token();

        return is_string($token) && $token !== '' && str_contains($content, $token);
    }

    protected function shouldNotCache(Request $request)
    {
        // Исключать если пользователь авторизован, или URL содержит 'profile', 'logout'
        return $request->user() || $request->is('profile*') || $request->is('logout');
    }

    /**
     * Stage 13: ключ кэша учитывает нормализованное (ровно три варианта)
     * состояние согласия на cookie аналитики, чтобы ответ, отрисованный для
     * одного состояния согласия, никогда не мог быть воспроизведён для
     * другого. Сырое значение cookie в ключ не попадает — только
     * нормализованное состояние из App\Support\CookieConsent.
     */
    protected function cacheKey(Request $request): string
    {
        $consentState = CookieConsent::normalize($request->cookie(CookieConsent::COOKIE_NAME));

        return $request->fullUrl() . '|consent:' . $consentState;
    }
}
