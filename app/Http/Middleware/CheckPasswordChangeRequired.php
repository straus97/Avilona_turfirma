<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordChangeRequired
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Если пользователь авторизован и требуется смена пароля
        if ($user && $user->password_change_required) {
            // Разрешаем доступ только к маршрутам смены пароля и выхода
            if (!$request->routeIs('password.change', 'password.change.update', 'logout')) {
                return redirect()->route('password.change')
                    ->with('warning', 'Пожалуйста, смените временный пароль для продолжения работы.');
            }
        }
        
        return $next($request);
    }
}
