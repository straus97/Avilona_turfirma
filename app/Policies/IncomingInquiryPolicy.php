<?php

namespace App\Policies;

use App\Models\IncomingInquiry;
use App\Models\User;

/**
 * Кто вправе действовать над входящим обращением (E5-A3).
 *
 * Политика отвечает только на вопрос «кто»: роль и ответственность. Допустимость
 * действия в текущем состоянии (new/in_progress/…) проверяет сервис внутри
 * транзакции, чтобы гонка не обходила проверку.
 *
 * Эффективная роль — по привычному приоритету admin > manager; турист и
 * пользователь без роли не имеют доступа ни к чему.
 */
class IncomingInquiryPolicy
{
    private function isStaff(User $user): bool
    {
        return $user->hasAnyRole([User::ROLE_ADMIN, User::ROLE_MANAGER]);
    }

    public function view(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isStaff($user);
    }

    /** Взять в работу может любой сотрудник (состояние проверяет сервис). */
    public function claim(User $user, IncomingInquiry $inquiry): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Выбор клиента, оформление заявки: администратор или ответственный менеджер.
     * Чужой менеджер не может обрабатывать обращение, взятое коллегой.
     */
    public function process(User $user, IncomingInquiry $inquiry): bool
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return true;
        }

        return $user->hasRole(User::ROLE_MANAGER)
            && $inquiry->assigned_to !== null
            && (int) $inquiry->assigned_to === (int) $user->id;
    }

    /** Закрыть без бронирования: как process, а ещё любой сотрудник — пока обращение ничьё. */
    public function close(User $user, IncomingInquiry $inquiry): bool
    {
        if ($this->process($user, $inquiry)) {
            return true;
        }

        return $this->isStaff($user) && $inquiry->assigned_to === null;
    }

    /** Переназначение — только администратор. */
    public function reassign(User $user, IncomingInquiry $inquiry): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
