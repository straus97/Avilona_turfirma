<?php

namespace App\Services\IncomingInquiries;

use RuntimeException;

/**
 * Ожидаемый отказ бизнес-процесса обработки обращения (конфликт состояния,
 * неподходящий клиент и т.п.). Сообщение предназначено для показа сотруднику.
 */
class IncomingInquiryWorkflowException extends RuntimeException
{
    /** Ключ поля формы, к которому относится ошибка (для вывода под полем). */
    public function __construct(string $message, public readonly string $field = 'workflow')
    {
        parent::__construct($message);
    }
}
