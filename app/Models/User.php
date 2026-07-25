<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Пути документов, которые нужно удалить после успешного удаления пользователя.
     *
     * @var array<int, string>
     */
    private array $documentPathsPendingDeletion = [];

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $personalDocumentPaths = $user
                ->userDocuments()
                ->pluck('file_path');

            $bookingIds = Booking::withTrashed()
                ->where('user_id', $user->id)
                ->pluck('id');

            $bookingDocumentPaths = BookingDocument::withTrashed()
                ->whereIn('booking_id', $bookingIds)
                ->pluck('file_path');

            $user->documentPathsPendingDeletion = $personalDocumentPaths
                ->merge($bookingDocumentPaths)
                ->filter(
                    fn ($path): bool =>
                        is_string($path)
                        && trim($path) !== ''
                )
                ->unique()
                ->values()
                ->all();
        });

        static::deleted(function (User $user): void {
            foreach (
                $user->documentPathsPendingDeletion
                as $path
            ) {
                Storage::disk('local')->delete($path);
            }

            $user->documentPathsPendingDeletion = [];
        });
    }

    // Константы для ролей
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_TOURIST = 'tourist';

    /**
     * Домен технических (недоставляемых) адресов, генерируемых при создании
     * клиента без email. RFC 2606: домен .invalid не маршрутизируется.
     */
    const TECHNICAL_EMAIL_DOMAIN = 'no-email.avilona.invalid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'birth_date',
        'gender',
        'address',
        'passport_number',
        'passport_issued_date',
        'passport_issued_by',
        'notification_settings',
        'is_active',
        'password_change_required',
        'temp_password',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Отношения
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function managedBookings()
    {
        return $this->hasMany(Booking::class, 'manager_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function userDocuments()
    {
        return $this->hasMany(UserDocument::class);
    }

    public function bonusAccount()
    {
        return $this->hasOne(BonusAccount::class);
    }

    /**
     * Получить количество непрочитанных сообщений
     */
    public function getUnreadMessagesCountAttribute()
    {
        return $this->receivedMessages()->where('is_read', false)->count();
    }

    /**
     * Проверка наличия роли у пользователя
     *
     * @param string|array $roles
     * @return bool
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return $this->roles()->whereIn('name', $roles)->exists();
        }
        return $this->roles()->where('name', $roles)->exists();
    }

    /**
     * Проверка наличия любой из ролей
     *
     * @param array $roles
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Назначить роль пользователю
     *
     * @param string $role
     * @return void
     */
    public function assignRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$roleModel->id]);
    }

    /**
     * Удалить роль у пользователя
     *
     * @param string $role
     * @return void
     */
    public function removeRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $this->roles()->detach($roleModel->id);
        }
    }

    /**
     * Scope для активных пользователей
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для пользователей по роли
     */
    public function scopeByRole($query, string $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }

    /**
     * Активные пользователи, которых можно назначить ответственным сотрудником
     * по заявке: менеджеры и администраторы (админ может вести заявку лично).
     * Единый источник правды для валидации назначения и списка в UI.
     */
    public function scopeAssignableToBookings($query)
    {
        return $query->active()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', [self::ROLE_MANAGER, self::ROLE_ADMIN]);
            });
    }

    /**
     * Является ли email техническим (сгенерированным системой), а не реальным
     * адресом клиента.
     *
     * Технические форматы:
     *   - текущий:  temp_<uuid>@no-email.avilona.invalid
     *   - legacy:   temp_<timestamp>@avilona.ru (генерировался прежним кодом)
     *
     * Обычные адреса на @avilona.ru техническими не считаются.
     */
    public function hasTechnicalEmail(): bool
    {
        $email = strtolower(trim((string) $this->email));

        if ($email === '') {
            return false;
        }

        if (str_ends_with($email, '@' . self::TECHNICAL_EMAIL_DOMAIN)) {
            return true;
        }

        return (bool) preg_match('/^temp_\d+@avilona\.ru$/', $email);
    }

    /**
     * Разрешена ли отправка email-уведомления по указанному разделу настроек
     * (например, 'new_messages', 'booking_updates').
     *
     * По умолчанию включено: null/пустой JSON, отсутствующие ключи,
     * а также повреждённый JSON или значение, декодированное не в массив,
     * трактуются как «включено». Отключить может только явное false —
     * либо у самого раздела, либо у общего email_notifications.
     */
    public function wantsEmailNotification(string $topic): bool
    {
        $settings = json_decode((string) $this->notification_settings, true);

        if (!is_array($settings)) {
            $settings = [];
        }

        if (($settings['email_notifications'] ?? true) === false) {
            return false;
        }

        return ($settings[$topic] ?? true) !== false;
    }

    /**
     * Проверка, является ли пользователь администратором
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Проверка, является ли пользователь менеджером
     */
    public function isManager(): bool
    {
        return $this->hasRole(self::ROLE_MANAGER);
    }

    /**
     * Проверка, является ли пользователь туристом
     */
    public function isTourist(): bool
    {
        return $this->hasRole(self::ROLE_TOURIST);
    }
}
