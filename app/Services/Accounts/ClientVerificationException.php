<?php

namespace App\Services\Accounts;

use App\Services\IncomingInquiries\IncomingInquiryWorkflowException;

/**
 * Ожидаемый отказ процесса проверки клиента (AOA-01-I7). Наследует исключение
 * обработки обращений, чтобы контроллер обращений показывал отказ как ошибку формы.
 * Текст никогда не содержит сведений о том, существует ли аккаунт с тем или иным email,
 * когда исключение адресовано менеджеру; администратору такие сведения разрешены.
 */
class ClientVerificationException extends IncomingInquiryWorkflowException
{
}
