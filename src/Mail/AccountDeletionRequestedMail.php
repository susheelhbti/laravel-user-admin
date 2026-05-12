<?php

namespace Susheelhbti\LaravelUserAdmin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object    $user,
        public readonly \DateTime $scheduledAt
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Account Deletion Scheduled');
    }

    public function content(): Content
    {
        return new Content(
            view: 'laravel-user-admin::emails.account-deletion-requested',
            with: ['user' => $this->user, 'scheduledAt' => $this->scheduledAt]
        );
    }
}
