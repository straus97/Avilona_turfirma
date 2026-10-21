<?php

namespace App\Services\Bookings;

use RuntimeException;

/** Ожидаемый отказ назначения/переназначения: показывается как ошибка поля формы. */
class BookingAssignmentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'manager_id')
    {
        parent::__construct($message);
    }
}
