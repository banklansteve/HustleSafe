<?php

namespace App\Mail;

use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestDisputeOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuestDispute $dispute,
        public readonly User $opener,
        public readonly User $recipient,
        public readonly string $recipientRole,
        public readonly int $responseHours,
    ) {}

    public function envelope(): Envelope
    {
        $this->dispute->loadMissing('quest');
        $title = $this->dispute->quest?->title ?? __('your contract');

        return new Envelope(
            subject: __('Action required: dispute opened on “:title”', ['title' => $title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quest-dispute-opened',
        );
    }
}
