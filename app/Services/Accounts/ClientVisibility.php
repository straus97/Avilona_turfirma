<?php

namespace App\Services\Accounts;

use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\InquiryAuthority;
use Illuminate\Database\Eloquent\Builder;

/**
 * Единое серверное правило видимости клиентов-туристов (AOA-01-I2B, G5).
 *
 *  - Администратор видит весь справочник активных туристов.
 *  - Менеджер видит только тех клиентов, с которыми у него СЕЙЧАС есть рабочая
 *    связь: клиент владеет заявкой, где менеджер — ответственный, либо клиент
 *    закреплён за обращением, назначенным этому менеджеру (IncomingInquiry, а с AOA-03-I3
 *    также самостоятельный Inquiry, пока он не закрыт — см. InquiryAuthority), либо подтверждён
 *    администратором по его запросу на проверку клиента (на ограниченный срок,
 *    AOA-01-I7). Историческое назначение доступа не даёт: после переназначения
 *    связь исчезает.
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
                    ->where('incoming_inquiries.assigned_to', $actor->id))
                // AOA-03-I3: клиент САМОСТОЯТЕЛЬНОГО Inquiry (без IncomingInquiry), которым менеджер
                // отвечает сейчас. Привязанные к IncomingInquiry Inquiry клиента НЕ раскрывают:
                // пока E5 работает на старой записи, клиента даёт она (ветка выше), а расходящийся
                // снимок Inquiry не должен ни дублировать, ни расширять эту видимость.
                ->orWhereIn('users.id', InquiryAuthority::scopeIndependent(Inquiry::query())
                    ->select('inquiries.client_user_id')
                    ->whereNotNull('inquiries.client_user_id')
                    ->where('inquiries.assigned_to', $actor->id)
                    // AOA-04-I6 (решение владельца): ЗАКРЫТОЕ самостоятельное обращение профиль клиента
                    // больше не раскрывает. Карточка обращения остаётся читаемой по InquiryPolicy, а доступ
                    // сохраняют другие источники: открытое обращение, заявка, подтверждение администратора.
                    ->where('inquiries.status', '!=', Inquiry::STATUS_CLOSED)
                    ->whereIn('inquiries.client_user_id', User::query()
                        ->select('users.id')
                        ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::TOURIST))))
                // AOA-01-I7: клиент, подтверждённый администратором по запросу ЭТОГО менеджера,
                // пока не истёк срок рабочего доступа (дальше его заменяет заявка/обращение).
                ->orWhereIn('users.id', ClientVerificationRequest::query()
                    ->select('client_verification_requests.resolved_user_id')
                    ->whereNotNull('client_verification_requests.resolved_user_id')
                    ->where('client_verification_requests.requested_by', $actor->id)
                    ->where('client_verification_requests.status', ClientVerificationRequest::STATUS_APPROVED)
                    ->where('client_verification_requests.access_expires_at', '>', now()));
        });
    }
}
