<?php

namespace App\Policies;

use App\Models\ClientVerificationRequest;
use App\Models\User;

/**
 * Кто вправе работать с запросами на проверку клиента (AOA-01-I7).
 *
 * Решает и видит все запросы только администратор. Менеджер видит исключительно
 * собственные. Турист и пользователь без роли не имеют доступа ни к чему.
 */
class ClientVerificationRequestPolicy
{
    /** Принять решение по запросу (привязать, создать, отклонить): только администратор. */
    public function resolve(User $user, ClientVerificationRequest $request): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function view(User $user, ClientVerificationRequest $request): bool
    {
        return $user->hasRole(User::ROLE_ADMIN)
            || ($user->hasRole(User::ROLE_MANAGER) && (int) $request->requested_by === (int) $user->id);
    }
}
