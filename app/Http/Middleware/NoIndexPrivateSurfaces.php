<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * NOV-PUBLIC-01: служебные и приватные поверхности не должны попадать в индекс.
 *
 * robots.txt — не контроль доступа и не гарантирует отсутствие URL в выдаче,
 * поэтому дополнительно отправляется заголовок X-Robots-Tag. Он ставится для
 * страниц входа/регистрации/восстановления, кабинетов, служебных маршрутов и
 * для любого ответа авторизованному пользователю. Публичные страницы
 * заголовка не получают.
 */
class NoIndexPrivateSurfaces
{
    public const PATTERNS = [
        'login',
        'register',
        'forgot-password',
        'reset-password*',
        'verify-email*',
        'email/*',
        'confirm-password',
        'password/*',
        'logout',
        'cabinet',
        'cabinet/*',
        'admin',
        'admin/*',
        'manager',
        'manager/*',
        'profile',
        'profile/*',
        'account',
        'messages/*',
        'bookings/*',
        'api/*',
        'captcha',
        'captcha/*',
        'reload-captcha',
        'sanctum/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // SEO_NOINDEX (staging) закрывает от индексации весь ответ; это страховка, а не доступ.
        if (config('seo.noindex') || $request->is(self::PATTERNS) || $request->user()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
