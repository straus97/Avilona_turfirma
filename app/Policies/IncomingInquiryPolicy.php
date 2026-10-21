<?php

namespace App\Policies;

use App\Models\IncomingInquiry;
use App\Models\User;

/**
 * Кто вправе действовать над входящим обращением (E5-A3, AOA-01-I2A).
 *
 * Политика отвечает только на вопрос «кто»: роль и ответственность. Допустимость
 * действия в текущем состоянии (new/in_progress/…) проверяет сервис внутри
 * транзакции, чтобы гонка не обходила проверку.
 *
 * Правило владельца: администратор видит и ведёт все обращения; менеджер — только
 * те, что назначены ему сейчас. Назначать и переназначать может только
 * администратор. Турист и пользователь без роли не имеют доступа ни к чему.
 * Эффективная роль — по привычному приоритету admin > manager.
 */
class IncomingInquiryPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    private function isAssignedManager(User $user, IncomingInquiry $inquiry): bool
    {
        return $user->hasRole(User::ROLE_MANAGER)
            && $inquiry->assigned_to !== null
            && (int) $inquiry->assigned_to === (int) $user->id;
    }

    /** Видимость совпадает с IncomingInquiry::scopeVisibleTo(). */
    public function view(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isAdmin($user) || $this->isAssignedManager($user, $inquiry);
    }

    /** Взять обращение себе (самоназначение) — только администратор; менеджер не берёт чужое/ничьё. */
    public function claim(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isAdmin($user);
    }

    /** Первичное назначение ничьего обращения сотруднику — только администратор. */
    public function assign(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isAdmin($user);
    }

    /** Выбор клиента, оформление заявки: администратор или ответственный менеджер. */
    public function process(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isAdmin($user) || $this->isAssignedManager($user, $inquiry);
    }

    /** Закрыть без бронирования: как process (ничьё обращение менеджер закрыть не может). */
    public function close(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->process($user, $inquiry);
    }

    /** Смена уже назначенного ответственного — только администратор. */
    public function reassign(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isAdmin($user);
    }
}
