<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewConsent extends Model
{
    protected $table = 'review_consents';

    protected $fillable = [
        'review_id',
        'evidence_id',
        'consent_full_name',
        'consent_email',
        'user_agreement_accepted_at',
        'personal_data_consent_accepted_at',
        'review_publication_consent_accepted_at',
        'user_agreement_version',
        'personal_data_consent_version',
        'review_publication_consent_version',
        'publication_scope',
        'publication_conditions',
        'review_payload_sha256',
        'withdrawn_at',
    ];

    protected $hidden = [
        'consent_full_name',
        'consent_email',
        'publication_conditions',
    ];

    protected $casts = [
        'user_agreement_accepted_at' => 'datetime',
        'personal_data_consent_accepted_at' => 'datetime',
        'review_publication_consent_accepted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'publication_scope' => 'array',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Reviews::class, 'review_id');
    }
}
