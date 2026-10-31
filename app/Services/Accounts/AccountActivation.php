<?php

namespace App\Services\Accounts;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryAuthority;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Отключение и повторное включение учётной записи (AOA-05-I4).
 *
 * Отключение (users.is_active = false) — обратимое действие администратора: запись пользователя,
 * заявки, обращения, переписка, документы, аудит, финансы, история назначений и роли НЕ меняются
 * и НЕ стираются. Это не анонимизация и не юридическое удаление персональных данных.
 *
 * Контракт (одна транзакция, контракт блокировок — ActiveAdminInvariant):
 *  - действующее лицо перепроверяется по актуальной строке: активный администратор;
 *  - отключить собственную учётную запись нельзя (другой администратор сможет это сделать);
 *  - причина обязательна, сжимается по пробелам, проверяется валидатором метаданных аудита;
 *  - повтор уже достигнутого состояния — безопасный no-op: ни записи, ни события аудита;
 *  - состояние формы (статус + роли) сверяется с актуальным: расхождение — отказ «устарело»;
 *  - инвариант «остаётся активный администратор» проверяется после изменения и откатывает его;
 *  - событие user.deactivated / user.reactivated пишется в той же транзакции: сбой аудита откатывает
 *    смену статуса целиком, отказ события не оставляет.
 *
 * Отключение закрывает вход и действующие сессии (EnsureAccountIsActive, LoginRequest), меняет
 * токен «запомнить меня» и удаляет персональные токены API. Повторное включение возвращает только
 * доступ: оно не создаёт пользователя, заявку или назначение и не переписывает ответственность —
 * всё, что принадлежало учётной записи, остаётся ровно в том виде, в каком было.
 */
class AccountActivation
{
    public const REASON_MIN_LENGTH = 5;
    public const REASON_MAX_LENGTH = 200;

    public const RESULT_DEACTIVATED = 'deactivated';
    public const RESULT_REACTIVATED = 'reactivated';
    public const RESULT_UNCHANGED = 'unchanged';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly ActiveAdminInvariant $admins,
    ) {
    }

    /**
     * @return self::RESULT_*
     *
     * @throws AccountChangeRefused
     * @throws ModelNotFoundException
     */
    public function deactivate(User $target, User $actor, string $reason, string $expectedState): string
    {
        return $this->change($target, $actor, false, $reason, $expectedState);
    }

    /**
     * @return self::RESULT_*
     *
     * @throws AccountChangeRefused
     * @throws ModelNotFoundException
     */
    public function reactivate(User $target, User $actor, string $reason, string $expectedState): string
    {
        return $this->change($target, $actor, true, $reason, $expectedState);
    }

    /**
     * Состояние учётной записи, которое видел администратор на форме: статус и роли. Не содержит
     * персональных данных и меняется при смене статуса или ролей.
     */
    public function stateToken(User $user): string
    {
        $roles = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->getKey())
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->implode(',');

        $active = DB::table('users')->where('id', $user->getKey())->value('is_active');

        return ($active ? 'active' : 'inactive') . ':' . $roles;
    }

    /**
     * Незавершённая работа сотрудника, которую администратору придётся передать вручную после
     * отключения. Только количества — без имён и содержимого. Автоматической передачи нет.
     *
     * @return array{bookings: int, inquiries: int, incoming_inquiries: int}
     */
    public function openWorkload(User $user): array
    {
        $id = (int) $user->getKey();

        return [
            'bookings' => Booking::query()->where('manager_id', $id)
                ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS, Booking::STATUS_CONFIRMED])
                ->count(),
            'inquiries' => InquiryAuthority::scopeIndependent(Inquiry::query())
                ->where('inquiries.assigned_to', $id)
                ->where('inquiries.status', Inquiry::STATUS_IN_PROGRESS)
                ->count(),
            'incoming_inquiries' => IncomingInquiry::query()->actionable()
                ->where('assigned_to', $id)
                ->whereIn('workflow_state', [IncomingInquiry::WORKFLOW_NEW, IncomingInquiry::WORKFLOW_IN_PROGRESS])
                ->count(),
        ];
    }

    private function change(User $target, User $actor, bool $desired, string $reason, string $expectedState): string
    {
        $targetId = (int) $target->getKey();
        $actorId = (int) $actor->getKey();

        try {
            return $this->admins->run([$targetId, $actorId], function ($locked) use ($targetId, $actorId, $desired, $reason, $expectedState, $actor): string {
                $subject = $locked->get($targetId);

                if ($subject === null) {
                    throw (new ModelNotFoundException())->setModel(User::class, [$targetId]);
                }

                // Актуальная учётная запись действующего лица: роль и активность могли измениться после входа.
                if (! $locked->has($actorId) || ! $this->admins->isActiveAdmin($actorId)) {
                    throw new AccountChangeRefused(AccountChangeRefused::REASON_NOT_AUTHORIZED);
                }

                if ($actorId === $targetId) {
                    throw new AccountChangeRefused(
                        $desired || $this->otherActiveAdminExists($targetId)
                            ? AccountChangeRefused::REASON_SELF
                            : AccountChangeRefused::REASON_LAST_ADMIN
                    );
                }

                $cleanReason = $this->cleanReason($reason);

                // Повтор уже достигнутого состояния безвреден: ни записи, ни события.
                if ((bool) $subject->is_active === $desired) {
                    return self::RESULT_UNCHANGED;
                }

                if ($this->stateToken($subject) !== $expectedState) {
                    throw new AccountChangeRefused(AccountChangeRefused::REASON_STALE);
                }

                $wasActiveAdmin = $this->admins->isActiveAdmin($targetId);
                $roles = $this->roleNames($targetId);

                $attributes = ['is_active' => $desired];

                if (! $desired) {
                    // Токен «запомнить меня» перестаёт подходить сразу.
                    $attributes['remember_token'] = Str::random(60);
                }

                $subject->forceFill($attributes)->save();

                if (! $desired) {
                    $this->revokeSessionAccess($subject);
                }

                if ($wasActiveAdmin && ! $desired && ! $this->admins->activeAdminExists()) {
                    throw new AccountChangeRefused(AccountChangeRefused::REASON_LAST_ADMIN);
                }

                $this->audit->record(
                    $desired ? AuditEventType::UserReactivated : AuditEventType::UserDeactivated,
                    $actor,
                    'user',
                    $targetId,
                    [
                        'previous_active' => ! $desired,
                        'new_active' => $desired,
                        'target_roles' => $roles,
                        'reason' => $cleanReason,
                    ],
                );

                return $desired ? self::RESULT_REACTIVATED : self::RESULT_DEACTIVATED;
            });
        } catch (AccountChangeRefused $e) {
            // Технический журнал: только идентификаторы и коды, без персональных данных.
            Log::warning('Account status change refused', [
                'actor_id' => $actorId,
                'target_user_id' => $targetId,
                'requested_active' => $desired,
                'reason' => $e->reason,
            ]);

            throw $e;
        }
    }

    private function otherActiveAdminExists(int $userId): bool
    {
        return User::query()->active()->whereKeyNot($userId)
            ->whereHas('roles', fn ($q) => $q->where('name', User::ROLE_ADMIN))
            ->exists();
    }

    private function roleNames(int $userId): string
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->implode(',');
    }

    /**
     * Токены API и (при серверном хранилище сессий) строки сессий отключённого пользователя.
     * Файловые сессии удалить нельзя: их закрывает EnsureAccountIsActive при следующем запросе.
     */
    private function revokeSessionAccess(User $user): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey())
                ->delete();
        }

        $table = (string) config('session.table', 'sessions');

        if (config('session.driver') === 'database' && Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
            DB::table($table)->where('user_id', $user->getKey())->delete();
        }
    }

    /**
     * Причина обязательна, сжимается по пробелам и ограничена по длине; проверяется тем же
     * валидатором метаданных аудита (e-mail, телефоны, пути, токены отклоняются).
     */
    private function cleanReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        $length = mb_strlen($reason);

        if ($length < self::REASON_MIN_LENGTH || $length > self::REASON_MAX_LENGTH) {
            throw new AccountChangeRefused(
                AccountChangeRefused::REASON_INVALID_REASON,
                'Причина должна содержать от ' . self::REASON_MIN_LENGTH . ' до ' . self::REASON_MAX_LENGTH . ' символов.',
            );
        }

        try {
            AuditMetadata::validate(['reason' => $reason]);
        } catch (InvalidArgumentException) {
            throw new AccountChangeRefused(
                AccountChangeRefused::REASON_INVALID_REASON,
                'Причина не должна содержать email, телефоны, пути к файлам и длинные ключеподобные строки.',
            );
        }

        return $reason;
    }
}
