<?php

namespace App\Services\Accounts;

use RuntimeException;

/**
 * Ожидаемый отказ в удалении учётной записи (AOA-05-I3). Типизированный отказ, а не сбой:
 * контроллеры показывают его как сообщение формы и ничего не делают с сессией.
 *
 * Тексты не содержат количеств, имён и содержимого чужих записей; перечень категорий
 * показывается только администратору.
 */
class UserDeletionRefused extends RuntimeException
{
    public const REASON_LAST_ADMIN = 'last_admin';
    public const REASON_PROTECTED_HISTORY = 'protected_history';

    /** Адресаты текста отказа. */
    public const AUDIENCE_TOURIST = 'tourist';
    public const AUDIENCE_STAFF = 'staff';
    public const AUDIENCE_ADMIN = 'admin';

    public const SUPPORT_EMAIL = 'avilonatur@bk.ru';

    private const CATEGORY_LABELS = [
        'owned_bookings' => 'заявки клиента',
        'managed_bookings' => 'заявки, где пользователь ответственный',
        'messages' => 'переписка по заявкам',
        'booking_documents' => 'загруженные документы заявок',
        'reminders' => 'напоминания о поездках',
        'verification' => 'проверки клиентов',
        'inquiries' => 'обращения',
        'incoming_inquiries' => 'входящие обращения',
        'bonus' => 'бонусные записи',
    ];

    /**
     * @param  list<string>  $categories  внутренние коды категорий защищённых записей
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $categories = [],
    ) {
        parent::__construct('User deletion refused: ' . $reason);
    }

    public function userMessage(string $audience): string
    {
        if ($this->reason === self::REASON_LAST_ADMIN) {
            return 'Нельзя удалить последнего активного администратора. Сначала назначьте другого активного администратора.';
        }

        return match ($audience) {
            self::AUDIENCE_TOURIST => 'Аккаунт удалить нельзя: с ним связаны заявки, обращения или другие записи турагентства, '
                . 'которые мы обязаны сохранять. Вход в аккаунт и ваши данные в кабинете не изменились. '
                . 'Если вы хотите, чтобы мы рассмотрели вопрос об удалении или обработке ваших персональных данных, '
                . 'напишите нам на ' . self::SUPPORT_EMAIL . ' — обращение будет рассмотрено отдельно.',
            self::AUDIENCE_STAFF => 'Аккаунт удалить нельзя: с ним связана рабочая история турагентства '
                . '(сообщения клиентов, заявки, обращения или проверки клиентов), которую нужно сохранить. '
                . 'Ваш аккаунт остаётся доступным. По вопросам доступа обратитесь к администратору сайта.',
            default => 'Пользователя нельзя удалить: с ним связаны записи, которые должны сохраниться в истории агентства'
                . $this->categoryList()
                . '. Аккаунт не изменён. Отключение учётных записей в системе пока не реализовано.',
        };
    }

    private function categoryList(): string
    {
        $labels = [];

        foreach ($this->categories as $code) {
            if (isset(self::CATEGORY_LABELS[$code])) {
                $labels[] = self::CATEGORY_LABELS[$code];
            }
        }

        return $labels === [] ? '' : ' (' . implode(', ', $labels) . ')';
    }
}
