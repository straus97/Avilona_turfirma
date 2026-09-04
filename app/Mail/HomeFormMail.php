<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HomeFormMail extends Mailable
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
        $this->validatedData = $validatedData;
    }

    /**
     * E2-A6-I1: «Тема» — необязательное поле формы. Если посетитель оставил
     * его пустым (или поле вовсе не пришло), тема письма не может стать
     * пустой/невалидной: используется детерминированный фолбэк, выведенный из
     * назначения формы, а не выдуманная клиентская семантика.
     */
    public const DEFAULT_SUBJECT = 'Новое сообщение с главной страницы — avilona.ru';

    private function resolvedSubject(): string
    {
        $subject = trim((string) ($this->validatedData['subject'] ?? ''));

        return $subject !== '' ? $subject : self::DEFAULT_SUBJECT;
    }

    public function build()
    {
        return $this->markdown('emails.contact-form-home')->with([
            'validatedData' => $this->validatedData,
            'subjectLine' => $this->resolvedSubject(),
        ]);
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: $this->resolvedSubject(),
        );
    }
}
