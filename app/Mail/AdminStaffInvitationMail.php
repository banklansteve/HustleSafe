<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminStaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly User $invitedBy,
        public readonly string $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been added as a HustleSafe admin'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-staff-invitation',
        );
    }
}
