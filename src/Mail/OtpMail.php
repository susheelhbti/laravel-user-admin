<?php

namespace Susheelhbti\LaravelUserAdmin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $email
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Login OTP Code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'laravel-user-admin::emails.otp',
            with: ['code' => $this->code, 'email' => $this->email]
        );
    }
}
