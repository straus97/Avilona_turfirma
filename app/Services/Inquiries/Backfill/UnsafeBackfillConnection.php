<?php

namespace App\Services\Inquiries\Backfill;

use RuntimeException;

/** Подключение не прошло положительную проверку «одноразовая QA-база» (AOA-03-I2). */
final class UnsafeBackfillConnection extends RuntimeException
{
}
