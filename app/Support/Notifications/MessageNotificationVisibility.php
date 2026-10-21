<?php

namespace App\Support\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Какие уведомления о новых сообщениях по заявкам пользователь вправе видеть СЕЙЧАС
 * (AOA-01-I2B, G8).
 *
 * Уведомление хранит снимок (отправитель, превью) на момент отправки. После
 * переназначения заявки прежний ответственный не должен видеть этот снимок —
 * ни в списке, ни в счётчике, ни в превью шапки. Поэтому видимость вычисляется при
 * показе по ТЕКУЩЕЙ принадлежности заявки, а не по факту адресации уведомления:
 *
 *  - администратор — все (надзор сохраняется; открытие по-прежнему проверяется);
 *  - менеджер — только по заявкам, где он ответственный сейчас;
 *  - турист — только по своим заявкам;
 *  - уведомления без читаемого booking_id (старый/неожиданный формат) скрыты для
 *    всех, кроме администратора: безопасное закрытое поведение.
 *
 * Записи не удаляются: если заявка снова станет рабочей для пользователя, его
 * уведомления вернутся. Прочие типы уведомлений этим классом не затрагиваются
 * (фильтр применяется только к NewMessageDatabaseNotification).
 */
final class MessageNotificationVisibility
{
    /** Непрочитанные видимые уведомления о сообщениях (для счётчика и превью). */
    public static function unreadFor(User $user): MorphMany
    {
        return self::restrict($user->unreadNotifications(), $user);
    }

    /** @param  MorphMany<\Illuminate\Notifications\DatabaseNotification>  $query */
    public static function restrict(MorphMany $query, User $user): MorphMany
    {
        $query->where('type', NewMessageDatabaseNotification::class);

        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $query;
        }

        if ($user->hasRole(User::ROLE_MANAGER)) {
            return $query->whereIn('data->booking_id', self::bookingIds('manager_id', $user));
        }

        if ($user->hasRole(User::ROLE_TOURIST)) {
            return $query->whereIn('data->booking_id', self::bookingIds('user_id', $user));
        }

        return $query->whereRaw('1 = 0');
    }

    private static function bookingIds(string $ownerColumn, User $user): Builder
    {
        return Booking::query()->select('bookings.id')->where('bookings.' . $ownerColumn, $user->id);
    }
}
