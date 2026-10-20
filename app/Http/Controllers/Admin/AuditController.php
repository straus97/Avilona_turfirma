<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Журнал бизнес-аудита для администратора (AOA-01-I1). Только чтение.
 *
 * Доступ ограничен middleware role:admin на маршруте. Показываются только
 * безопасные поля события; технический журнал приложения — отдельная страница.
 */
class AuditController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): View
    {
        $events = AuditEvent::query()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        // Один запрос на имена исполнителей страницы (без N+1); удалённый пользователь — просто «#id».
        $actorNames = User::query()
            ->whereIn('id', $events->pluck('actor_id')->filter()->unique()->values())
            ->pluck('name', 'id');

        return view('admin.audit', compact('events', 'actorNames'));
    }
}
