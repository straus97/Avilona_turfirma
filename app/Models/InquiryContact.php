<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Непроверенный контакт клиента обращения (AOA-04-I4): имя, телефон, контакт в мессенджере.
 *
 * Это НЕ Tourist User и НЕ пассажир заявки. Запись создаёт и меняет только InquiryContactStore
 * (блокировка обращения, версия, аудит). Строка не удаляется приложением (см. миграцию:
 * «надгробие» защищает счётчик версии). Значения не сериализуются неявно: все атрибуты скрыты,
 * наружу контакт отдаёт только явная проекция Inquiry-карточки для авторизованного сотрудника.
 */
class InquiryContact extends Model
{
    protected $primaryKey = 'inquiry_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = ['*'];

    protected $hidden = [
        'inquiry_id', 'contact_name', 'contact_phone', 'contact_messenger',
        'revision', 'updated_by', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'inquiry_id' => 'integer',
        'revision' => 'integer',
        'updated_by' => 'integer',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** Заполнено ли хотя бы одно поле (строка-«надгробие» после очистки — нет). */
    public function hasValues(): bool
    {
        return $this->contact_name !== null || $this->contact_phone !== null || $this->contact_messenger !== null;
    }
}
