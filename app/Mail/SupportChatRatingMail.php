<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Services\Support\CustomerSupportService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportChatRatingMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $ratingUrl;

    public function __construct(public readonly SupportTicket $ticket)
    {
        $this->ratingUrl = app(CustomerSupportService::class)->ratingUrl($ticket);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('How was your HustleSafe support experience?'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support-chat-rating',
        );
    }
}
