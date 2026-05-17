<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminUserCreatedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $createdUser,
        public readonly User $createdBy,
        public readonly string $auditReason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Admin-created user account notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-user-created-notification',
        );
    }
}
