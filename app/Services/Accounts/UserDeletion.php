<?php

namespace App\Services\Accounts;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Единственная охраняемая операция жёсткого удаления учётной записи (AOA-05-I3).
 *
 * Контракт:
 *  - внутри одной транзакции: блокировка строки удаляемого пользователя (и всех администраторов
 *    в порядке возрастания id — для защиты последнего администратора) → проверка защищённых
 *    связей → событие аудита → удаление; сбой любого шага откатывает всё;
 *  - первым оператором транзакции идёт блокирующее чтение без подзапросов, поэтому все
 *    последующие проверки видят уже зафиксированные записи конкурирующих писателей;
 *  - писатели, создающие записи со ссылкой на пользователя, берут блокировку его строки
 *    (BookingController::store, MessageController::store, сервисы назначения/оформления) либо
 *    получают её от InnoDB как разделяемую блокировку родительской строки внешнего ключа;
 *  - защищённые связи берутся из REFERENCES: тест сверяет перечень с фактическими внешними
 *    ключами схемы, поэтому новая ссылка на users не останется незамеченной;
 *  - отказ — типизированное UserDeletionRefused, сессия и данные не затрагиваются;
 *  - файлы удаляются только после фиксации транзакции (см. UserFileCleanup).
 *
 * Граница: охрана прикладная. Прямой SQL и каскады внешних ключей (CASCADE / SET NULL) БД
 * по-прежнему допускает до отдельной стадии смены ON DELETE.
 */
class UserDeletion
{
    public const CHANNEL_SELF_SERVICE = 'self_service';
    public const CHANNEL_ADMIN_PANEL = 'admin_panel';

    /**
     * Защищённые ссылки на users: «таблица.колонка» => категория. Любая такая ссылка запрещает
     * удаление (строки, помеченные удалёнными, учитываются — проверка идёт по сырой таблице).
     */
    public const PROTECTED_REFERENCES = [
        'bookings.user_id' => 'owned_bookings',
        'bookings.manager_id' => 'managed_bookings',
        'messages.sender_id' => 'messages',
        'messages.receiver_id' => 'messages',
        'messages.manager_id' => 'messages',
        'booking_documents.uploaded_by' => 'booking_documents',
        'booking_trip_reminder_deliveries.recipient_user_id' => 'reminders',
        'client_verification_requests.requested_by' => 'verification',
        'client_verification_requests.resolved_by' => 'verification',
        'client_verification_requests.resolved_user_id' => 'verification',
        'inquiries.created_by' => 'inquiries',
        'inquiries.assigned_to' => 'inquiries',
        'inquiries.client_user_id' => 'inquiries',
        'inquiries.client_linked_by' => 'inquiries',
        'inquiries.converted_by' => 'inquiries',
        'inquiries.closed_by' => 'inquiries',
        'inquiries.draft_updated_by' => 'inquiries',
        'inquiry_contacts.updated_by' => 'inquiries',
        'incoming_inquiries.assigned_to' => 'incoming_inquiries',
        'incoming_inquiries.client_user_id' => 'incoming_inquiries',
        'incoming_inquiries.client_linked_by' => 'incoming_inquiries',
        'incoming_inquiries.converted_by' => 'incoming_inquiries',
        'incoming_inquiries.closed_by' => 'incoming_inquiries',
        'bonus_accounts.referred_by' => 'bonus',
    ];

    /** Ссылки, проверяемые особым условием (непустой бонусный счёт). */
    public const CONDITIONAL_REFERENCES = [
        'bonus_accounts.user_id' => 'bonus',
    ];

    /**
     * Ссылки, удаляемые вместе с аккаунтом осознанно: роли, личные документы (файлы чистятся
     * после фиксации), запись согласия (срок хранения — открытое решение U-10/R-B).
     */
    public const ACCEPTED_REFERENCES = [
        'role_user.user_id',
        'user_documents.user_id',
        'user_registration_consents.user_id',
    ];

    private const MAX_LOCK_ATTEMPTS = 3;

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @throws UserDeletionRefused
     * @throws ModelNotFoundException
     */
    public function delete(User $target, User $actor, string $channel): void
    {
        $targetId = (int) $target->getKey();

        for ($attempt = 1; $attempt <= self::MAX_LOCK_ATTEMPTS; $attempt++) {
            $lockIds = $this->lockSet($targetId);

            try {
                $done = DB::transaction(fn (): bool => $this->deleteLocked($targetId, $lockIds, $actor, $channel), 3);
            } catch (UserDeletionRefused $e) {
                // Технический журнал: только идентификаторы и коды, без персональных данных.
                Log::warning('User account deletion refused', [
                    'actor_id' => (int) $actor->getKey(),
                    'target_user_id' => $targetId,
                    'reason' => $e->reason,
                    'categories' => $e->categories,
                ]);

                throw $e;
            }

            if ($done) {
                return;
            }
        }

        throw new RuntimeException('User deletion could not obtain a stable lock set.');
    }

    /**
     * Проверка допустимости без блокировок: используется внутри delete() под блокировкой и как
     * страховка в хуке модели User::deleting для любого прямого $user->delete().
     *
     * @throws UserDeletionRefused
     */
    public function assertDeletable(User $user): void
    {
        $id = (int) $user->getKey();

        if ($this->isActiveAdmin($id) && ! $this->otherActiveAdminExists($id)) {
            throw new UserDeletionRefused(UserDeletionRefused::REASON_LAST_ADMIN);
        }

        $categories = $this->protectedCategories($id);

        if ($categories !== []) {
            throw new UserDeletionRefused(UserDeletionRefused::REASON_PROTECTED_HISTORY, $categories);
        }
    }

    /** @return list<string> */
    public function protectedCategories(int $userId): array
    {
        $found = [];

        foreach (self::PROTECTED_REFERENCES as $reference => $category) {
            [$table, $column] = explode('.', $reference);

            if (DB::table($table)->where($column, $userId)->exists()) {
                $found[$category] = true;
            }
        }

        $hasBonusEvidence = DB::table('bonus_accounts')
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('balance', '<>', 0)
                    ->orWhere('total_earned', '<>', 0)
                    ->orWhere('total_spent', '<>', 0)
                    ->orWhereExists(
                        fn ($sub) => $sub->selectRaw('1')->from('bonus_transactions')
                            ->whereColumn('bonus_transactions.bonus_account_id', 'bonus_accounts.id')
                    );
            })
            ->exists();

        if ($hasBonusEvidence) {
            $found['bonus'] = true;
        }

        return array_keys($found);
    }

    /**
     * @param  list<int>  $lockIds
     */
    private function deleteLocked(int $targetId, array $lockIds, User $actor, string $channel): bool
    {
        // Первый оператор транзакции — блокирующее чтение без подзапросов (см. контракт выше).
        $locked = User::query()->whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $subject = $locked->get($targetId);

        if ($subject === null) {
            throw (new ModelNotFoundException())->setModel(User::class, [$targetId]);
        }

        // Набор администраторов мог измениться между расчётом набора и блокировкой.
        if (array_diff($this->adminIds(), $lockIds) !== []) {
            return false;
        }

        $this->assertDeletable($subject);

        $roles = $subject->roles()->orderBy('name')->pluck('name')->implode(',');

        $this->audit->record(AuditEventType::UserDeleted, $actor, 'user', $targetId, [
            'channel' => $channel,
            'target_roles' => $roles,
            'self_service' => $channel === self::CHANNEL_SELF_SERVICE,
        ]);

        $subject->delete();

        return true;
    }

    /** @return list<int> цель и все администраторы, по возрастанию id */
    private function lockSet(int $targetId): array
    {
        $ids = array_unique([...$this->adminIds(), $targetId]);
        sort($ids);

        return array_values($ids);
    }

    /** @return list<int> */
    private function adminIds(): array
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', Role::ADMIN)
            ->pluck('role_user.user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function isActiveAdmin(int $userId): bool
    {
        return User::query()->active()->whereKey($userId)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->exists();
    }

    private function otherActiveAdminExists(int $userId): bool
    {
        return User::query()->active()->whereKeyNot($userId)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->exists();
    }
}
