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

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly string $event,
        public readonly array $extra = [],
    ) {}

    public function envelope(): Envelope
    {
        $reference = $this->ticket->ticket_reference ?: ('#'.$this->ticket->id);

        $subject = match ($this->event) {
            'closed', 'resolved' => __('Your support request :ref has been resolved', ['ref' => $reference]),
            'update' => __('Update on your support request :ref', ['ref' => $reference]),
            default => __('We received your support request :ref', ['ref' => $reference]),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support-ticket-status',
        );
    }
}
