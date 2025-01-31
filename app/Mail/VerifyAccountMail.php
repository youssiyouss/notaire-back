<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class VerifyAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $verificationUrl;

    public function __construct($token)
    {
        $this->token = $token;
        $this->lang = App::currentLocale();
        $this->verificationUrl = url('api/verify-email', ['token' => $this->token]);

    }
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __("authController.verify_mail_title"),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auth.verify-account',
            with: ['verificationUrl' => $this->verificationUrl]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
