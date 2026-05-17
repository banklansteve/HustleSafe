<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperationsStaffInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $setupUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your HustleSafe operations console access'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.operations-staff-invitation',
        );
    }
}
