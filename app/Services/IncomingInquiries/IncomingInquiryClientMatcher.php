<?php

namespace App\Services\IncomingInquiries;

use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Подбор КАНДИДАТОВ среди существующих туристов для показа сотруднику.
 *
 * Кандидаты и поиск ограничены клиентами, видимыми сотруднику
 * (ClientVisibility, AOA-01-I2B): менеджер не находит чужого клиента по имени,
 * телефону или email. Совпадение email/телефона — только подсказка: сервис ничего не пишет и не
 * связывает. Владельца заявки определяет только явное решение сотрудника.
 */
class IncomingInquiryClientMatcher
{
    public const SEARCH_MIN_LENGTH = 2;
    private const LIMIT = 10;

    /** Пользователи, которых допустимо выбрать клиентом: активные туристы. */
    public static function touristQuery(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::TOURIST));
    }

    /**
     * @return Collection<int, array{user: User, reasons: list<string>}>
     */
    public function candidatesFor(IncomingInquiry $inquiry, User $actor): Collection
    {
        $email = $this->normalizedEmail($inquiry->client_email);
        $phone = $this->phoneTail($inquiry->client_phone);

        if ($email === null && $phone === null) {
            return collect();
        }

        $users = ClientVisibility::touristsVisibleTo($actor)
            ->where(function (Builder $q) use ($email, $phone): void {
                if ($email !== null) {
                    $q->whereRaw('LOWER(email) = ?', [$email]);
                }

                if ($phone !== null) {
                    $q->orWhereRaw($this->strippedPhoneSql() . ' LIKE ?', ['%' . $phone]);
                }
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return $users->map(function (User $user) use ($email, $phone): array {
            $reasons = [];

            if ($email !== null && $this->normalizedEmail($user->email) === $email) {
                $reasons[] = 'совпадает email';
            }

            if ($phone !== null && str_ends_with($this->digits($user->phone), $phone)) {
                $reasons[] = 'совпадает телефон';
            }

            return ['user' => $user, 'reasons' => $reasons];
        });
    }

    /**
     * Ручной поиск по имени, email или телефону (для случая, когда контакты
     * из обращения не совпали ни с одним аккаунтом).
     *
     * @return Collection<int, User>
     */
    public function search(?string $term, User $actor): Collection
    {
        $term = trim((string) $term);

        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            return collect();
        }

        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
        $digits = $this->digits($term);

        return ClientVisibility::touristsVisibleTo($actor)
            ->where(function (Builder $q) use ($like, $digits): void {
                $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]);

                if (strlen($digits) >= 4) {
                    $q->orWhereRaw($this->strippedPhoneSql() . ' LIKE ?', ['%' . $digits . '%']);
                }
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
    }

    private function normalizedEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function digits(?string $value): string
    {
        return (string) preg_replace('/\D+/', '', (string) $value);
    }

    /** Последние 10 цифр номера; короче 10 цифр для сопоставления не годится. */
    private function phoneTail(?string $phone): ?string
    {
        $digits = $this->digits($phone);

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /** Номер из БД без пробелов и знаков (одинаково для MySQL и SQLite). */
    private function strippedPhoneSql(): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";
    }
}
