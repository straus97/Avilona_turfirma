<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'uploaded_by_user_id',
        'name',
        'file_path',
        'file_type',
        'file_size',
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
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
