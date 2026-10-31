<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отключённая учётная запись (users.is_active = false) не может пользоваться уже открытой
 * сессией, токеном «запомнить меня» или токеном API (AOA-05-I4).
 *
 * Проверка идёт по строке в БД, а не по уже загруженной модели: отключение, выполненное после
 * входа, действует со следующего же запроса (для веб — на любой странице, включая публичные,
 * чтобы сессия была закрыта, а не просто не пускала в кабинет). Гость и активный пользователь
 * проходят без изменений. Строки пользователя нет — поведение прежнее (решает обычная аутентификация).
 *
 * Ответ не раскрывает причину: «сеанс завершён» совпадает с обычным завершением сеанса,
 * отключённый пользователь ничего не может сделать от своего имени, в том числе включить себя.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->isDeactivated($user->getAuthIdentifier())) {
            return $next($request);
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('login')->with('status', 'Сеанс завершён. Войдите снова.');
    }

    private function isDeactivated(mixed $id): bool
    {
        $flag = DB::table('users')->where('id', $id)->value('is_active');

        return $flag !== null && ! (bool) $flag;
    }
}
