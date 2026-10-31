<?php

namespace App\Services\Accounts;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Инвариант «в системе всегда есть хотя бы один активный администратор» (AOA-05-I4).
 *
 * Прикладные операции, способные его нарушить (отключение учётной записи, снятие и замена роли
 * администратора, удаление — см. UserDeletion), сериализуются одним контрактом блокировок:
 *  - внутри одной транзакции блокируются строки users ВСЕХ держателей роли администратора, цели и
 *    действующего лица — по возрастанию id, поэтому две такие операции не могут взаимно заблокироваться;
 *  - первым оператором транзакции идёт блокирующее чтение по списку id без подзапросов: все
 *    последующие проверки видят уже зафиксированные записи конкурирующих писателей;
 *  - набор администраторов мог измениться между расчётом набора и блокировкой (выдана роль):
 *    тогда транзакция откатывается и набор вычисляется заново (до MAX_LOCK_ATTEMPTS раз).
 *
 * Границы: охрана прикладная — прямой SQL и каскады внешних ключей её не соблюдают.
 */
class ActiveAdminInvariant
{
    private const MAX_LOCK_ATTEMPTS = 3;

    /**
     * Выполнить $callback под блокировками набора. $callback получает заблокированные строки users
     * (по id) и вызывается внутри транзакции; исключение откатывает всё.
     *
     * @param  list<int>  $baseIds  цель и действующее лицо
     * @param  Closure(Collection<int, User>): mixed  $callback
     */
    public function run(array $baseIds, Closure $callback): mixed
    {
        for ($attempt = 1; $attempt <= self::MAX_LOCK_ATTEMPTS; $attempt++) {
            $lockIds = $this->lockSet($baseIds);

            [$done, $value] = DB::transaction(function () use ($lockIds, $callback): array {
                // Первый оператор транзакции — блокирующее чтение без подзапросов (см. контракт).
                $locked = User::query()->whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                // Набор администраторов мог измениться между расчётом набора и блокировкой.
                if (array_diff($this->adminIds(), $lockIds) !== []) {
                    return [false, null];
                }

                return [true, $callback($locked)];
            }, 3);

            if ($done) {
                return $value;
            }
        }

        throw new RuntimeException('Account change could not obtain a stable lock set.');
    }

    public function isActiveAdmin(int $userId): bool
    {
        return User::query()->active()->whereKey($userId)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->exists();
    }

    public function activeAdminExists(): bool
    {
        return User::query()->active()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->exists();
    }

    /** @return list<int> все держатели роли администратора (активные и нет), по возрастанию id */
    public function adminIds(): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', Role::ADMIN)
            ->pluck('role_user.user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $baseIds
     * @return list<int>
     */
    private function lockSet(array $baseIds): array
    {
        $ids = array_values(array_unique([...$this->adminIds(), ...array_map('intval', $baseIds)]));
        sort($ids);

        return $ids;
    }
}
