<?php

namespace App\Services\Accounts;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Снятие и замена ролей учётной записи с защитой последнего активного администратора (AOA-05-I4).
 *
 * Это единственное место приложения, где у пользователя отбирается роль (пин — тест
 * AccountDeactivationTest): снятие роли администратора и замена роли, после которой администратора
 * не остаётся, откатываются типизированным отказом. Выдача роли (assignRole) последнего
 * администратора не лишает и остаётся в контроллере.
 *
 * Те же блокировки, что у отключения и удаления (ActiveAdminInvariant): два администратора не
 * могут одновременно лишить друг друга роли и оставить систему без администратора.
 * Остальная семантика ролей не меняется.
 */
class AccountRoleChange
{
    public function __construct(private readonly ActiveAdminInvariant $admins)
    {
    }

    /**
     * Снять роль. Возвращает true, если связь была удалена.
     *
     * @throws AccountChangeRefused
     * @throws ModelNotFoundException
     */
    public function detach(User $target, Role $role, User $actor): bool
    {
        return (bool) $this->guarded($target, $actor, fn (): int => $target->roles()->detach($role->id));
    }

    /**
     * Заменить роли одной ролью. Возвращает результат sync() (attached / detached / updated).
     *
     * @return array{attached: array, detached: array, updated: array}
     *
     * @throws AccountChangeRefused
     * @throws ModelNotFoundException
     */
    public function replace(User $target, Role $role, User $actor): array
    {
        return $this->guarded($target, $actor, fn (): array => $target->roles()->sync([$role->id]));
    }

    private function guarded(User $target, User $actor, \Closure $mutation): mixed
    {
        $targetId = (int) $target->getKey();

        return $this->admins->run([$targetId, (int) $actor->getKey()], function ($locked) use ($targetId, $mutation) {
            if ($locked->get($targetId) === null) {
                throw (new ModelNotFoundException())->setModel(User::class, [$targetId]);
            }

            $wasActiveAdmin = $this->admins->isActiveAdmin($targetId);

            $result = $mutation();

            if ($wasActiveAdmin && ! $this->admins->activeAdminExists()) {
                throw new AccountChangeRefused(AccountChangeRefused::REASON_LAST_ADMIN);
            }

            return $result;
        });
    }
}
