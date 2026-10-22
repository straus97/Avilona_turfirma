<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Запись по заявке с повторной проверкой прав под блокировкой строки (AOA-01-I4, I3-F1).
 *
 * Первичная проверка в контроллере остаётся для быстрого отказа, но она выполняется по
 * заявке, прочитанной ДО записи: за это время администратор мог переназначить заявку.
 * Поэтому каждая запись здесь идёт в одной транзакции: блокировка строки заявки
 * (тот же lockForUpdate, что у BookingAssignment), повторное чтение актуального
 * ответственного и статуса, проверка права по ЗАБЛОКИРОВАННОЙ записи, правил переходов
 * и только затем запись. Отказ по правам выбрасывает AuthorizationException (HTTP 403,
 * как и раньше), ничего не записывается и события не отправляются.
 *
 * Событие BookingStatusChanged (а значит и письма) отправляется только ПОСЛЕ коммита.
 * Статус, оплата и правила переходов не меняются: используется Booking::persistTransition().
 */
class BookingLifecycle
{
    /**
     * Перевести заявку в статус $targetStatus от имени $actor.
     *
     * @param  string  $ability  способность BookingPolicy (confirm, cancel, complete)
     * @return bool false — переход недопустим в актуальном статусе (ничего не записано)
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function transition(Booking $booking, User $actor, string $ability, string $targetStatus): bool
    {
        return $this->apply($booking, $actor, $ability, $targetStatus, []);
    }

    /**
     * Обновить заявку (универсальное редактирование): необязательный переход статуса и поля.
     * Переход и поля записываются атомарно.
     *
     * @param  array<string, mixed>  $fields
     * @return bool false — запрошенный переход недопустим в актуальном статусе (ничего не записано)
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Booking $booking, User $actor, ?string $targetStatus, array $fields): bool
    {
        return $this->apply($booking, $actor, 'update', $targetStatus, $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function apply(Booking $booking, User $actor, string $ability, ?string $targetStatus, array $fields): bool
    {
        $oldStatus = null;

        $locked = DB::transaction(function () use ($booking, $actor, $ability, $targetStatus, $fields, &$oldStatus): ?Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            Gate::forUser($actor)->authorize($ability, $locked);

            if ($targetStatus !== null) {
                if (! $locked->canTransitionTo($targetStatus)) {
                    return null;
                }

                $oldStatus = $locked->persistTransition($targetStatus);
            }

            if ($fields !== []) {
                $locked->update($fields);
            }

            return $locked;
        });

        if ($locked === null) {
            return false;
        }

        // Заявка уже зафиксирована: уведомления — после коммита, вне блокировки.
        if ($oldStatus !== null) {
            $locked->dispatchStatusChanged($oldStatus);
        }

        return true;
    }
}
