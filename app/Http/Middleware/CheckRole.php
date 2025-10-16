<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();
        foreach ($roles as $role) {
            // Проверяем, есть ли у пользователя нужная роль
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return redirect('/404')->with('error', 'Извините, но у вас нету прав для просмотра этой страницы');
    }
}

