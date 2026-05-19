<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly string $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->event === 'closed'
                ? __('Your HustleSafe support ticket has been closed')
                : __('Your HustleSafe support ticket is open'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support-ticket-status',
        );
    }
}
