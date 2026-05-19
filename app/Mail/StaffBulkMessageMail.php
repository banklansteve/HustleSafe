<?php

namespace App\Mail;

use App\Models\StaffBulkMessageRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffBulkMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly StaffBulkMessageRequest $messageRequest,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->messageRequest->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-bulk-message');
    }
}
