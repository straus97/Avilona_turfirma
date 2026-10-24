<?php

namespace App\Support\Chat;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Явный минимальный JSON-контракт сообщения чата (SEC-FIX-01).
 *
 * Раньше messages.index / messages.store сериализовали загруженные отношения
 * sender и receiver целиком: любой участник переписки получал паспортные данные,
 * адрес, дату рождения, контакты и служебные поля собеседника. Клиентскому чату
 * (public/js/cabinet-chat.js) нужны только поля самого сообщения; имя собеседника
 * рисуется сервером в разметке.
 *
 * Здесь работает белый список: ни сообщение, ни участник не сериализуются «как есть»,
 * поэтому новые колонки или правки $hidden в User не могут попасть в ответ чата
 * по умолчанию. avatar_path не выдаётся: во внутреннем хранилище это путь, а клиентам
 * аватар JSON не нужен (в разметке он рисуется инициалами).
 */
final class ChatMessagePresenter
{
    /** Поля сообщения, которые получает клиент. */
    private const MESSAGE_FIELDS = [
        'id',
        'booking_id',
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
        'read_at',
        'created_at',
        'updated_at',
    ];

    /** Поля участника переписки, которые получает клиент. */
    private const PARTICIPANT_FIELDS = ['id', 'name'];

    /**
     * @return array<string, mixed>
     */
    public static function present(Message $message): array
    {
        return Arr::only($message->attributesToArray(), self::MESSAGE_FIELDS) + [
            'attachment_download_url' => $message->attachment_download_url,
            'sender'                  => self::participant($message->sender),
            'receiver'                => self::participant($message->receiver),
        ];
    }

    /**
     * @param  iterable<int, Message>  $messages
     * @return array<int, array<string, mixed>>
     */
    public static function presentMany(iterable $messages): array
    {
        return Collection::make($messages)
            ->map(fn (Message $message): array => self::present($message))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function participant(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id'   => $user->id,
            'name' => $user->name,
        ];
    }
}
