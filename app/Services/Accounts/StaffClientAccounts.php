<?php

namespace App\Services\Accounts;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Единая безопасная логика учётных записей туристов, создаваемых сотрудниками
 * (входящие обращения и ручное оформление заявки).
 *
 * Контракт безопасности:
 *  - пароль — случайные 64 символа, хранится только хеш; значение никуда не
 *    возвращается, не пишется в users.temp_password, не логируется и не отправляется;
 *  - email НЕ считается подтверждённым (email_verified_at = null);
 *  - установка пароля — только стандартная ссылка сброса Laravel (Password broker);
 *  - транзакцию и проверку прав выполняет вызывающий код.
 */
class StaffClientAccounts
{
    /** Есть ли пользователь с таким email (без учёта регистра). */
    public function emailExists(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->exists();
    }

    /**
     * Создать активного туриста. Вызывать внутри транзакции: отсутствие роли
     * «tourist» приводит к исключению (firstOrFail) и откату.
     */
    public function createTourist(string $name, ?string $email, ?string $phone = null): User
    {
        $email = $email !== null ? trim($email) : null;
        $email = $email === '' ? null : $email;
        $phone = ($phone !== null && trim($phone) !== '') ? trim($phone) : null;

        $client = new User();
        $client->forceFill([
            'name' => $name,
            'email' => $email ?? $this->technicalEmail(),
            'phone' => $phone,
            'password' => Hash::make(Str::random(64)),
            'is_active' => true,
            'password_change_required' => false,
            'temp_password' => null,
            'email_verified_at' => null,
        ])->save();

        $client->assignRole(Role::TOURIST);

        return $client;
    }

    /**
     * Можно ли отправить ссылку установки пароля: только клиенту, который ещё не
     * активировал аккаунт и имеет настоящий email. Не даёт сотруднику способа
     * слать ссылки активным пользователям.
     */
    public function canSendPasswordSetup(User $client): bool
    {
        return ! $client->hasTechnicalEmail()
            && $client->email_verified_at === null
            && $client->last_login_at === null;
    }

    /** Отправить стандартную ссылку установки пароля. true — ссылка отправлена. */
    public function sendPasswordSetup(User $client): bool
    {
        if (! $this->canSendPasswordSetup($client)) {
            return false;
        }

        return Password::broker()->sendResetLink(['email' => $client->email]) === Password::RESET_LINK_SENT;
    }

    private function technicalEmail(): string
    {
        do {
            $email = 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN;
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
