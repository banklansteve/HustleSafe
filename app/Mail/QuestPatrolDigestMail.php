<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestPatrolDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $digest
     */
    public function __construct(
        public User $admin,
        public array $digest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HustleSafe patrol digest — '.$this->digest['date'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quest-patrol-digest',
        );
    }
}
