<?php

namespace App\Support\Notifications;

use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

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
 * Удалённое сообщение (AOA-01-I6, I5-F1): сообщения удаляются физически, поэтому
 * уведомление показывается только пока строка messages с его message_id существует — для
 * ВСЕХ ролей, включая администратора (надзор не расширяется до текста удалённого сообщения).
 * Это единый серверный фильтр: он покрывает и исторические снимки, созданные до исправления,
 * и любой путь удаления. Запись без читаемого message_id сверить не с чем → скрыта.
 * Дополнительно при удалении сообщения сохранённый снимок затирается (см. redactDeleted),
 * чтобы текст не оставался и в таблице notifications.
 *
 * Записи не удаляются: если заявка снова станет рабочей для пользователя, его
 * уведомления о ЖИВЫХ сообщениях вернутся. Прочие типы уведомлений этим классом не
 * затрагиваются (фильтр применяется только к NewMessageDatabaseNotification).
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
        $query->where('type', NewMessageDatabaseNotification::class)
            ->whereIn('data->message_id', Message::query()->select('messages.id'));

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

    /**
     * Затирает сохранённый снимок уведомлений о только что удалённом сообщении: текст превью
     * и признак вложения. Строки уведомлений остаются (история не теряется), а показ закрывает
     * restrict(). Вызывать в одной транзакции с удалением сообщения.
     */
    public static function redactDeleted(int $messageId): void
    {
        DatabaseNotification::query()
            ->where('type', NewMessageDatabaseNotification::class)
            ->where('data->message_id', $messageId)
            ->get()
            ->each(function (DatabaseNotification $notification): void {
                $data = is_array($notification->data) ? $notification->data : [];

                $notification->forceFill(['data' => array_merge($data, [
                    'preview' => 'Сообщение удалено',
                    'has_attachment' => false,
                    'deleted' => true,
                ])])->save();
            });
    }

    private static function bookingIds(string $ownerColumn, User $user): Builder
    {
        return Booking::query()->select('bookings.id')->where('bookings.' . $ownerColumn, $user->id);
    }
}
