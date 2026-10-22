<?php

namespace App\Mail\Concerns;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Письмо со ссылкой на чат заявки уходит только тому, кто участвует в заявке в момент
 * ФАКТИЧЕСКОЙ отправки (AOA-01-I4, I3-F2).
 *
 * Письмо ставится в очередь адресату, который был ответственным на тот момент; пока оно
 * ждёт воркера, заявку могли переназначить. Тогда прежний ответственный больше не вправе
 * получать письмо со сведениями о заявке: доставка пропускается намеренно — без отправки,
 * без повторов и без записи в failed_jobs. Исключения при реальной сбойной доставке
 * не перехватываются и остаются видны.
 *
 * Mailable задаёт заявку и получателя через deliveryBooking() / deliveryRecipient().
 * В журнал попадают только идентификаторы — ни текста заявки, ни сообщения, ни адреса.
 */
trait SkipsStaleChatRecipient
{
    /** Заявка, к чату которой ведёт письмо. */
    abstract protected function deliveryBooking(): Booking;

    /** Получатель письма; null — адресата больше нет. */
    abstract protected function deliveryRecipient(): ?User;

    /**
     * @param  \Illuminate\Contracts\Mail\Factory|\Illuminate\Contracts\Mail\Mailer  $mailer
     * @return \Illuminate\Mail\SentMessage|null
     */
    public function send($mailer)
    {
        $recipient = $this->deliveryRecipient();

        if (! $this->recipientParticipatesNow($recipient)) {
            Log::info('Queued booking e-mail skipped: recipient no longer participates in the booking', [
                'mailable' => static::class,
                'booking_id' => $this->deliveryBooking()->id,
                'recipient_id' => $recipient?->id,
            ]);

            return null;
        }

        return parent::send($mailer);
    }

    /** Актуальное состояние заявки читается из БД, а не берётся из сериализованного письма. */
    private function recipientParticipatesNow(?User $recipient): bool
    {
        if ($recipient === null) {
            return false;
        }

        $current = Booking::withTrashed()
            ->select(['id', 'user_id', 'manager_id'])
            ->find($this->deliveryBooking()->id);

        return $current !== null && $current->isChatParticipant($recipient);
    }
}
