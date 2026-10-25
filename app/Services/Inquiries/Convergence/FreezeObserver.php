<?php

namespace App\Services\Inquiries\Convergence;

interface FreezeObserver
{
    /** Только чтение; ничего не блокирует и не пишет. */
    public function observe(): FreezeObservation;
}
