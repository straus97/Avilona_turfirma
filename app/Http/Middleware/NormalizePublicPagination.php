<?php

namespace App\Http\Middleware;

use App\Support\Seo\PublicSeo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * NOV-PUBLIC-03A: пагинируемые публичные списки (отзывы, новости, статьи,
 * «для наших клиентов»).
 *
 * 1. Помечает запрос как пагинируемый: только на таких маршрутах ?page=N (N >= 2)
 *    даёт самостоятельный canonical/title. На остальных страницах параметр page
 *    игнорируется контроллером, а значит не создаёт новую страницу.
 * 2. ?page=1 и любое значение, которое пагинатор Laravel читает как первую
 *    страницу (page=0, page=-3, page=abc, массив), отдаёт тот же документ, что и
 *    адрес без параметра. Такой дубль (в старой карте сайта было 3 URL ?page=1)
 *    уходит на постоянный 301 на тот же путь без page; остальные параметры
 *    (фильтры) сохраняются.
 *
 * Цель редиректа берётся только из пути самого маршрута (фиксированная
 * константа, не из ввода) и отдаётся относительным Location: хост из заголовка
 * Host не участвует, открытого редиректа нет. Петли нет: в цели параметра page
 * уже нет. Правило согласовано с PaginationState (тот же filter_var).
 */
class NormalizePublicPagination
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set(PublicSeo::PAGINATED_ATTRIBUTE, true);

        if ($request->isMethodSafe() && $request->query->has('page') && $this->readsAsFirstPage($request->query->all()['page'] ?? null)) {
            return $this->redirectToCleanUrl($request);
        }

        return $next($request);
    }

    /** Совпадает с резолвером страницы в Illuminate\Pagination\PaginationState. */
    private function readsAsFirstPage(mixed $raw): bool
    {
        $page = filter_var($raw, FILTER_VALIDATE_INT);

        return $page === false || $page < 2;
    }

    private function redirectToCleanUrl(Request $request): RedirectResponse
    {
        // Путь маршрута — константа из routes/public.php, без параметров и без пользовательского ввода.
        $path = '/' . ltrim($request->route()->uri(), '/');

        $query = $request->query->all();
        unset($query['page']);

        if ($query !== []) {
            $path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return new RedirectResponse($path, 301);
    }
}
