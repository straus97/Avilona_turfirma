<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\Inquiries\InquiryAuthority;

/**
 * Кто вправе работать с обращением агентства (AOA-03-I1, правило владельца §A3).
 *
 * Политика отвечает только на вопрос «кто»: роль и текущая ответственность.
 * Допустимость действия в текущем статусе проверяет сервис внутри транзакции.
 *
 * Администратор видит и ведёт все обращения; менеджер — только назначенные ему
 * СЕЙЧАС (чужие, ничьи и бывшие — нет); турист и пользователь без роли не имеют
 * доступа к управлению обращениями. Правила не копируют IncomingInquiryPolicy
 * слепо: назначение и переназначение здесь намеренно не объявлены — это AOA-04.
 * Эффективная роль — по привычному приоритету admin > manager.
 *
 * Привязанное к IncomingInquiry обращение (AOA-03-I3): пока E5 работает на старой записи,
 * менеджер отвечает за него, только если назначение Inquiry совпадает с назначением
 * источника; при расхождении доступ менеджеру закрыт (см. InquiryAuthority).
 */
class InquiryPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    /** Ответственный менеджер СЕЙЧАС (для привязанного обращения — совпадающий с источником). */
    private function isAssignedManager(User $user, Inquiry $inquiry): bool
    {
        return InquiryAuthority::isResponsibleManager($inquiry, $user);
    }

    /** Видимость совпадает с Inquiry::scopeVisibleTo(). */
    public function view(User $user, Inquiry $inquiry): bool
    {
        return $this->isAdmin($user) || $this->isAssignedManager($user, $inquiry);
    }

    /**
     * Завести обращение вручную (AOA-04-I1): только менеджер, и только себе (создатель = ответственный,
     * §A4). Создание администратором и назначение другому сотруднику — отдельный срез AOA-04, поэтому
     * пользователь с ролью администратора (в том числе совмещающий роль менеджера, эффективная роль
     * admin > manager) обращение пока не создаёт. Действующую активность учётной записи и
     * пригодность к ведению заявок сервис создания перепроверяет под транзакцией.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_MANAGER) && ! $this->isAdmin($user);
    }

    /** Сохранить черновик: администратор или ответственный менеджер. */
    public function updateDraft(User $user, Inquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }
}
