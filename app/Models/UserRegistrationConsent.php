<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRegistrationConsent extends Model
{
    protected $table = 'user_registration_consents';

    protected $fillable = [
        'user_id',
        'user_agreement_accepted_at',
        'user_agreement_version',
        'personal_data_consent_accepted_at',
        'personal_data_consent_version',
    ];

    protected $casts = [
        'user_agreement_accepted_at' => 'datetime',
        'personal_data_consent_accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
