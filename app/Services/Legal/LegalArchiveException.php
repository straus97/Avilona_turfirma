<?php

namespace App\Services\Legal;

use RuntimeException;

/**
 * NOV-PUBLIC-LEGAL-01 S1-A: rejection of a legal registry, a legal fragment or a legal package.
 *
 * The reason is a fixed machine-readable code (for example `registry_noncanonical`). The detail is a short
 * structural description (field name, archive-relative path, version id). Document text is never placed in
 * the message, so a later caller may log the exception class and reason without leaking legal wording.
 */
final class LegalArchiveException extends RuntimeException
{
    public const MAX_DETAIL_BYTES = 512;

    public function __construct(public readonly string $reason, string $detail = '')
    {
        $detail = substr($detail, 0, self::MAX_DETAIL_BYTES);
        parent::__construct('legal archive rejected: ' . $reason . ($detail !== '' ? ' (' . $detail . ')' : ''));
    }
}
