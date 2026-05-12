<?php

namespace Susheelhbti\LaravelUserAdmin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $user,
        public readonly ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Account Suspension Notice');
    }

    public function content(): Content
    {
        return new Content(
            view: 'laravel-user-admin::emails.user-suspended',
            with: ['user' => $this->user, 'reason' => $this->reason]
        );
    }
}
