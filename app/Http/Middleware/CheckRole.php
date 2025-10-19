<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Проверяем, авторизован ли пользователь
        if (!$request->user()) {
            return redirect()->route('login')
                ->with('error', 'Необходимо войти в систему');
        }

        // Проверяем, есть ли у пользователя одна из требуемых ролей
        if (!$request->user()->hasAnyRole($roles)) {
            abort(403, 'У вас нет доступа к этой странице');
        }

        return $next($request);
    }
}

