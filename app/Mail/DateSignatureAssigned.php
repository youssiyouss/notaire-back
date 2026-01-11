<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DateSignatureAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public $contract;
    public $clientName;
    public $signatureDate;

    /**
     * Create a new message instance.
     */
    public function __construct(Contract $contract, $clientName, $signatureDate)
    {
        $this->contract = $contract;
        $this->clientName = $clientName;
        $this->signatureDate = $signatureDate;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Date de signature assignée - ' . $this->contract->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.DateSignatureAssigned',
            with: [
                'contractNumber' => sprintf('CNT-%05d', $this->contract->id),
                'clientName' => $this->clientName,
                'signatureDate' => $this->signatureDate,
                'notary' => $this->contract->notaire ? $this->contract->notaire->nom . ' ' . $this->contract->notaire->prenom : 'N/A'
            ]
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
