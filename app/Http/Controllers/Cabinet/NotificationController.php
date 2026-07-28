<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Открыть уведомление о новом сообщении и перейти в соответствующий чат.
     *
     * Уведомление разрешается только через связь текущего пользователя
     * (notifications()), поэтому чужая запись всегда выглядит как
     * несуществующая (404), а не как "нет доступа".
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();

        $databaseNotification = $user->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($databaseNotification->type !== NewMessageDatabaseNotification::class) {
            abort(404);
        }

        $data = $databaseNotification->data;

        if (!is_array($data) || ($data['type'] ?? null) !== 'new_message') {
            abort(404);
        }

        if (!array_key_exists('booking_id', $data) || !$this->isPositiveInteger($data['booking_id'])) {
            abort(404);
        }

        $booking = Booking::find((int) $data['booking_id']);

        if (!$booking) {
            abort(404);
        }

        // Приоритет ролей единый с остальным кабинетом: admin > manager > tourist.
        if ($user->hasAnyRole(['admin'])) {
            $routeName = 'cabinet.admin.chats';
        } elseif ($user->hasAnyRole(['manager'])) {
            if ((int) $booking->manager_id !== (int) $user->id) {
                abort(404);
            }
            $routeName = 'cabinet.manager.chat';
        } elseif ($user->hasAnyRole(['tourist'])) {
            if ((int) $booking->user_id !== (int) $user->id) {
                abort(404);
            }
            $routeName = 'cabinet.chat';
        } else {
            abort(404);
        }

        // Laravel сам не трогает read_at, если он уже установлен — идемпотентно.
        $databaseNotification->markAsRead();

        return redirect()->route($routeName, ['bookingId' => $booking->id]);
    }

    /**
     * booking_id из payload уведомления приходит как значение из JSON-колонки,
     * поэтому допускаем как int, так и числовую строку — но только строго
     * положительное целое.
     */
    private function isPositiveInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_string($value) && ctype_digit($value)) {
            return ((int) $value) > 0;
        }

        return false;
    }
}
