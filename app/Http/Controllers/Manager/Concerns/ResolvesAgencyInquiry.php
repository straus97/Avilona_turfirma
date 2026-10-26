<?php

namespace App\Http\Controllers\Manager\Concerns;

use App\Models\Inquiry;
use App\Services\Inquiries\InquiryAuthority;
use Illuminate\Support\Facades\Gate;

/**
 * Единый вход к самостоятельному обращению агентства для контроллеров раздела (AOA-04).
 */
trait ResolvesAgencyInquiry
{
    /** Чужое/ничьё/бывшее — 404; также только самостоятельные (не привязанные к Tourvisor) обращения. */
    private function visible(Inquiry $inquiry): void
    {
        abort_unless(
            InquiryAuthority::provenance($inquiry) === InquiryAuthority::PROVENANCE_INDEPENDENT
                && Gate::allows('view', $inquiry),
            404
        );
    }
}
