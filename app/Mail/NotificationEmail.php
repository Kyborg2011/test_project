<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Plain-text e-mail whose subject and body were prepared by the message producer.
 */
final class NotificationEmail extends Mailable
{
    public function __construct(
        public readonly string $messageSubject,
        public readonly string $messageBody,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->messageSubject);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.notification', with: ['body' => $this->messageBody]);
    }
}
