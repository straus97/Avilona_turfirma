<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'document_type',
        'title',
        'file_path',
        'file_size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    /**
     * Связь с заявкой
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Связь с пользователем (кто загрузил)
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Виртуальный тип файла на основе расширения
     */
    public function getFileTypeAttribute(): string
    {
        return strtolower(pathinfo($this->file_path ?? '', PATHINFO_EXTENSION));
    }
}
