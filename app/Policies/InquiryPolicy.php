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
 * слепо: назначение и передача (assign, AOA-04-I2) — только администратор.
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
     * §A4). Создание администратором с выбором ответственного — createForStaff (AOA-04-I3), поэтому
     * пользователь с ролью администратора (в том числе совмещающий роль менеджера, эффективная роль
     * admin > manager) создаёт обращение только по правилу createForStaff. Действующую активность учётной записи и
     * пригодность к ведению заявок сервис создания перепроверяет под транзакцией.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_MANAGER) && ! $this->isAdmin($user);
    }

    /**
     * Завести обращение от имени администратора и выбрать первого ответственного (AOA-04-I3). Правило
     * по маршруту: эффективная роль admin > manager, поэтому аккаунт с обеими ролями создаёт только
     * здесь (и обязан выбрать ответственного — в том числе себя), а не по правилам менеджера.
     */
    public function createForStaff(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Назначить или передать обращение другому сотруднику (AOA-04-I2): только администратор.
     * Менеджер не назначает ни чужие, ни свои обращения и не берёт ничьи себе. Допустимость
     * конкретного обращения (самостоятельное, в работе) и сотрудника проверяет сервис под блокировкой.
     */
    public function assign(User $user, Inquiry $inquiry): bool
    {
        return $this->isAdmin($user);
    }

    /** Сохранить черновик: администратор или ответственный менеджер. */
    public function updateDraft(User $user, Inquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }

    /**
     * Ввести, исправить или очистить непроверенный контакт клиента (AOA-04-I4): те же полномочия,
     * что у черновика — администратор или менеджер, отвечающий СЕЙЧАС. Бывший ответственный права
     * не имеет; допустимость самостоятельного открытого обращения проверяет сервис под блокировкой.
     */
    public function updateContact(User $user, Inquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }

    /**
     * Выбрать для обращения аккаунт клиента из ВИДИМЫХ сотруднику (AOA-04-I4): администратор из всего
     * справочника, менеджер — только из своих клиентов (ClientVisibility). Сама видимость кандидата
     * проверяется сервисом под блокировкой; здесь — только «кто вправе работать с обращением».
     */
    public function linkClient(User $user, Inquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }

    /**
     * Отправить администратору запрос на проверку клиента по обращению (AOA-04-I4, вариант C):
     * только менеджер, отвечающий за обращение сейчас. Администратор создаёт и привязывает клиентов
     * напрямую (эффективная роль admin > manager, как в create()).
     */
    public function requestClient(User $user, Inquiry $inquiry): bool
    {
        return ! $this->isAdmin($user) && $this->isAssignedManager($user, $inquiry);
    }

    /**
     * Создать клиента из обращения и отвязать ошибочно привязанного (AOA-04-I4): только администратор.
     * Менеджер не создаёт аккаунты и не меняет и не снимает привязку.
     */
    public function administerClient(User $user, Inquiry $inquiry): bool
    {
        return $this->isAdmin($user);
    }
}
