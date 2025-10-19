<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory;
    use SoftDeletes; //использование "мягкое" удаление, чтобы потом можно было восстановить

    // Константы для ролей
    const ADMIN = 'admin';
    const MANAGER = 'manager';
    const TOURIST = 'tourist';

    protected $table = 'roles';

    protected $fillable = ['name', 'description'];

    /**
     * Отношения
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Получить все доступные роли с описаниями
     *
     * @return array
     */
    public static function availableRoles(): array
    {
        return [
            self::ADMIN => 'Администратор - полный доступ к системе',
            self::MANAGER => 'Менеджер - управление заявками и клиентами',
            self::TOURIST => 'Турист - просмотр и создание заявок',
        ];
    }
}
