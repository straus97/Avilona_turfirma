<?php

namespace App\Services\Accounts;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Единое серверное правило видимости клиентов-туристов (AOA-01-I2B, G5).
 *
 *  - Администратор видит весь справочник активных туристов.
 *  - Менеджер видит только тех клиентов, с которыми у него СЕЙЧАС есть рабочая
 *    связь: клиент владеет заявкой, где менеджер — ответственный, либо клиент
 *    закреплён за обращением, назначенным этому менеджеру. Историческое
 *    назначение доступа не даёт: после переназначения связь исчезает.
 *  - Остальные роли клиентов не видят.
 *
 * Правило применяется на уровне SQL (подзапросы), а не фильтрацией в Blade/JS.
 * Видимость клиента НЕ даёт доступа к другим его заявкам, переписке или
 * обращениям: те проверяются собственными политиками.
 */
class ClientVisibility
{
    /** Активные туристы, видимые указанному сотруднику. */
    public static function touristsVisibleTo(User $actor): Builder
    {
        $query = User::query()
            ->where('users.is_active', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::TOURIST));

        return self::scopeTo($query, $actor);
    }

    /** Ограничить произвольный запрос пользователей рабочими связями сотрудника. */
    public static function scopeTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole(User::ROLE_ADMIN)) {
            return $query;
        }

        if (! $actor->hasRole(User::ROLE_MANAGER)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $w) use ($actor): void {
            $w->whereIn('users.id', Booking::query()
                ->select('bookings.user_id')
                ->where('bookings.manager_id', $actor->id))
                ->orWhereIn('users.id', IncomingInquiry::query()
                    ->select('incoming_inquiries.client_user_id')
                    ->whereNotNull('incoming_inquiries.client_user_id')
                    ->where('incoming_inquiries.assigned_to', $actor->id));
        });
    }
}
