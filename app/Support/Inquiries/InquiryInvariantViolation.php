<?php

namespace App\Support\Inquiries;

use LogicException;

/**
 * Нарушение инварианта модели Inquiry (AOA-03-I1): программная ошибка вызывающего
 * кода (несовместимые origin/status/ответственный), а не ожидаемый отказ пользователю.
 */
class InquiryInvariantViolation extends LogicException
{
}
