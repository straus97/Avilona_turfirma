<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public $validatedData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($validatedData)
    {
        // В автоответ попадает только имя, и только если оно похоже на имя: произвольный
        // текст посетителя (ссылки, разметка, e-mail) не должен уходить от имени компании.
        $name = trim((string) ($validatedData['name'] ?? ''));
        $safeName = preg_match('/^[\p{L}\p{M}][\p{L}\p{M} \'’-]{0,59}$/u', $name) === 1 ? $name : '';

        $this->validatedData = ['name' => $safeName];
    }

    public function build()
    {
        return $this->markdown('emails.user-form-contacts')->with('validatedData', $this->validatedData);
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Информационное письмо об успешной отправки формы с сайта avilona.ru',
        );
    }
}
